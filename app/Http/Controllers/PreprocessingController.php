<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PreprocessingController extends Controller
{
    public function index(Request $request)
    {
        $uploadedPath = $request->session()->get('pre_csv_path');
        $preview = $request->session()->get('pre_preview');
        $processed = $request->session()->get('pre_processed');

        return view('preprocessing', [
            'uploadedPath' => $uploadedPath,
            'preview' => $preview,
            'processed' => $processed,
        ]);
    }

    public function upload(Request $request)
    {
        $request->validate([
            'csv_file' => ['required', 'file', 'mimes:csv,txt'],
        ]);

        $file = $request->file('csv_file');
        $path = $file->storeAs('preprocessing', now()->format('Ymd_His') . '_' . $file->getClientOriginalName());

        // Read CSV preview (first 200 rows to be safe)
        $fullPath = Storage::path($path);
        [$header, $rows] = $this->readCsv($fullPath, 2000);
        // Drop unwanted columns like score and time/at from preview
        [$header, $rows] = $this->filterColumns($header, $rows, ['score', 'time', 'at']);

        $request->session()->put('pre_csv_path', $path);
        $request->session()->put('pre_preview', ['header' => $header, 'rows' => $rows]);
        $request->session()->forget('pre_processed');

        return redirect()->route('preprocessing.index')->with('status', 'File CSV berhasil diupload.');
    }

    public function run(Request $request)
    {
        $path = $request->session()->get('pre_csv_path');
        if (!$path || !Storage::exists($path)) {
            return redirect()->route('preprocessing.index')->with('error', 'Tidak ada file yang diupload.');
        }

        $fullPath = Storage::path($path);
        [$header, $rows] = $this->readCsv($fullPath, null);
        // Drop noisy columns that shouldn't be processed as text
        [$header, $rows] = $this->filterColumns($header, $rows, ['score', 'time', 'at']);

        // Determine tweet column
        $tweetIndex = $this->detectTweetColumnIndex($header);

        $processedRows = [];
        foreach ($rows as $row) {
            $tweet = $tweetIndex !== null && isset($row[$tweetIndex]) ? (string) $row[$tweetIndex] : '';

            // Skip empty or very short tweets
            if (mb_strlen(trim($tweet)) < 3) {
                $processedRows[] = [
                    'raw' => $tweet,
                    'case_folding' => '',
                    'cleansing' => '',
                    'normalisasi' => '',
                    'tokenizing' => '',
                    'filtering' => '',
                    'stemming' => '',
                ];
                continue;
            }

            $caseFolding = $this->caseFold($tweet);
            $cleansed = $this->cleanse($caseFolding);
            $normalized = $this->normalize($cleansed);
            $normalized = $this->normalizeElongation($normalized);
            $tokens = $this->tokenize($normalized);
            $filteredTokens = $this->filterStopwords($tokens);
            $stemmedTokens = $this->stemTokens($filteredTokens);

            // Skip if no meaningful tokens left
            if (empty($stemmedTokens)) {
                $processedRows[] = [
                    'raw' => $tweet,
                    'case_folding' => $caseFolding,
                    'cleansing' => $cleansed,
                    'normalisasi' => $normalized,
                    'tokenizing' => implode(' ', $tokens),
                    'filtering' => implode(' ', $filteredTokens),
                    'stemming' => '',
                ];
                continue;
            }

            $processedRows[] = [
                'raw' => $tweet,
                'case_folding' => $caseFolding,
                'cleansing' => $cleansed,
                'normalisasi' => $normalized,
                'tokenizing' => implode(' ', $tokens),
                'filtering' => implode(' ', $filteredTokens),
                'stemming' => implode(' ', $stemmedTokens),
            ];
        }

        $request->session()->put('pre_processed', [
            'header' => $header,
            'rows' => $processedRows,
        ]);

        return redirect()->route('preprocessing.index')->with('status', 'Preprocessing selesai.');
    }

    public function download(Request $request): StreamedResponse
    {
        $processed = $request->session()->get('pre_processed');
        if (!$processed) {
            return redirect()->route('preprocessing.index')->with('error', 'Belum ada hasil preprocessing.');
        }

        $filename = 'preprocessing_' . now()->format('Ymd_His') . '.csv';

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ];

        return response()->stream(function () use ($processed) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['preprocessed']);
            foreach ($processed['rows'] as $r) {
                fputcsv($out, [$r['stemming']]);
            }
            fclose($out);
        }, 200, $headers);
    }

    private function readCsv(string $path, ?int $limit = 200): array
    {
        $handle = fopen($path, 'r');
        if ($handle === false) {
            return [[], []];
        }
        $header = null;
        $rows = [];
        $count = 0;
        while (($data = fgetcsv($handle)) !== false) {
            if ($header === null) {
                $header = $data;
                continue;
            }
            $rows[] = $data;
            $count++;
            if ($limit !== null && $count >= $limit) {
                break;
            }
        }
        fclose($handle);
        return [$header ?: [], $rows];
    }

    private function filterColumns(array $header, array $rows, array $dropKeywords): array
    {
        if (empty($header)) {
            return [$header, $rows];
        }
        $dropIndices = [];
        foreach ($header as $idx => $name) {
            $lower = strtolower(trim((string) $name));
            foreach ($dropKeywords as $kw) {
                if ($lower === $kw || str_contains($lower, $kw)) {
                    $dropIndices[$idx] = true;
                    break;
                }
            }
        }

        if (empty($dropIndices)) {
            return [$header, $rows];
        }

        $newHeader = [];
        foreach ($header as $idx => $name) {
            if (!isset($dropIndices[$idx])) {
                $newHeader[] = $name;
            }
        }

        $newRows = [];
        foreach ($rows as $row) {
            $newRow = [];
            foreach ($row as $idx => $val) {
                if (!isset($dropIndices[$idx])) {
                    $newRow[] = $val;
                }
            }
            $newRows[] = $newRow;
        }

        return [$newHeader, $newRows];
    }

    private function detectTweetColumnIndex(array $header): ?int
    {
        if (empty($header)) return null;
        $candidates = ['tweet', 'text', 'content', 'message', 'body'];
        foreach ($header as $idx => $name) {
            $lower = strtolower(trim((string) $name));
            if (in_array($lower, $candidates, true)) {
                return $idx;
            }
        }
        // fallback to last column
        return count($header) ? count($header) - 1 : null;
    }

    private function caseFold(string $text): string
    {
        return mb_strtolower($text, 'UTF-8');
    }

    private function cleanse(string $text): string
    {
        // Strip URLs, emails, mentions, hashtags
        $text = preg_replace('/https?:\/\/\S+/ui', ' ', $text); // URLs
        $text = preg_replace('/\b[\w.+-]+@[\w.-]+\.[a-z]{2,}\b/ui', ' ', $text); // emails
        $text = preg_replace('/\b(rt|via)\b/ui', ' ', $text); // retweet markers
        $text = preg_replace('/[@#][\w_]+/u', ' ', $text); // mentions & hashtags
        
        // Remove excessive punctuation and quotes
        $text = preg_replace('/["""\'\'\']/', ' ', $text); // quotes
        $text = preg_replace('/[!]{2,}/', '!', $text); // multiple exclamation
        $text = preg_replace('/[?]{2,}/', '?', $text); // multiple question marks
        $text = preg_replace('/[.]{2,}/', '.', $text); // multiple dots
        
        // Remove emojis and special characters but keep basic punctuation
        $text = preg_replace('/[\x{1F600}-\x{1F64F}]/u', ' ', $text); // emojis
        $text = preg_replace('/[\x{1F300}-\x{1F5FF}]/u', ' ', $text); // symbols
        $text = preg_replace('/[\x{1F680}-\x{1F6FF}]/u', ' ', $text); // transport
        $text = preg_replace('/[\x{1F1E0}-\x{1F1FF}]/u', ' ', $text); // flags
        $text = preg_replace('/[\x{2600}-\x{26FF}]/u', ' ', $text); // misc symbols
        $text = preg_replace('/[\x{2700}-\x{27BF}]/u', ' ', $text); // dingbats
        
        // Keep only letters, spaces, and basic punctuation
        $text = preg_replace('/[^\p{L}\s.!?]/u', ' ', $text);
        
        // Clean up spaces
        $text = preg_replace('/\s+/u', ' ', $text);
        return trim($text);
    }

    private function normalize(string $text): string
    {
        // Expand slang normalization dictionary (common Indonesian/colloquial forms)
        $dictionary = [
            // Negation
            'gak' => 'tidak','ga' => 'tidak','nggak' => 'tidak','enggak' => 'tidak','tdk' => 'tidak','gk' => 'tidak','tak' => 'tidak','tk' => 'tidak',
            'yg' => 'yang','aja' => 'saja','krn' => 'karena','karna' => 'karena','klo' => 'kalau','kl' => 'kalau','kalo' => 'kalau','klu' => 'kalau',
            'utk' => 'untuk','u' => 'untuk','dgn' => 'dengan','dg' => 'dengan','sm' => 'sama','dr' => 'dari','dlm' => 'dalam','dlu' => 'dulu',
            'tp' => 'tapi','tpi' => 'tapi','sbnrnya' => 'sebenarnya','bgt' => 'banget','bangettt' => 'banget',
            'jd' => 'jadi','jg' => 'juga','ajaib' => 'ajaib','kluarga' => 'keluarga','org' => 'orang','tmn' => 'teman','temen' => 'teman',
            'skrg' => 'sekarang','nanti' => 'nanti','ntar' => 'nanti','kmrn' => 'kemarin','km' => 'kamu','kmu' => 'kamu','sy' => 'saya','aq' => 'aku','gue' => 'saya','gua' => 'saya',
            'krdt' => 'kredit','pinjol' => 'pinjaman online','pinjamanonline' => 'pinjaman online','bgtu' => 'begitu','trus' => 'terus','trs' => 'terus',
            'aps' => 'aplikasi','apk' => 'aplikasi','app' => 'aplikasi','aplikasi' => 'aplikasi',
            'blm' => 'belum','blum' => 'belum','blom' => 'belum','belom' => 'belum',
            'udh' => 'sudah','udah' => 'sudah','sdh' => 'sudah','suda' => 'sudah',
            'msh' => 'masih','msih' => 'masih','masi' => 'masih',
            'lg' => 'lagi','lgi' => 'lagi','lag' => 'lagi',
            'bkn' => 'bukan','bukan' => 'bukan','bkn' => 'bukan',
            'ato' => 'atau','atau' => 'atau','ata' => 'atau',
            'dgn' => 'dengan','dengan' => 'dengan','dngan' => 'dengan',
            'utk' => 'untuk','untuk' => 'untuk','untk' => 'untuk',
            'dr' => 'dari','dari' => 'dari','dri' => 'dari',
            'pd' => 'pada','pada' => 'pada','pda' => 'pada',
            'dlm' => 'dalam','dalam' => 'dalam','dlam' => 'dalam',
            'ke' => 'ke','k' => 'ke','k' => 'ke',
            'di' => 'di','d' => 'di','d' => 'di',
            
            // Specific to your data
            'smogha' => 'sangat','smoga' => 'semoga','smg' => 'semoga',
            'acc' => 'accept','accepted' => 'accept','approve' => 'accept',
            'cair' => 'cair','cairkan' => 'cair','pencairan' => 'cair',
            'proses' => 'proses','prosess' => 'proses','process' => 'proses',
            'cepat' => 'cepat','cepet' => 'cepat','cepatt' => 'cepat','fast' => 'cepat',
            'mudah' => 'mudah','mudah' => 'mudah','easy' => 'mudah',
            'bagus' => 'bagus','good' => 'bagus','best' => 'bagus','okay' => 'baik','ok' => 'baik',
            'buruk' => 'buruk','jelek' => 'buruk','bad' => 'buruk',
            'bantu' => 'bantu','membantu' => 'bantu','help' => 'bantu','helpful' => 'bantu',
            'pinjam' => 'pinjam','pinjaman' => 'pinjam','loan' => 'pinjam',
            'limit' => 'limit','batas' => 'limit',
            'bung' => 'bunga','bunga' => 'bunga','interest' => 'bunga',
            'tenor' => 'tenor','jangkawaktu' => 'tenor','jangka' => 'tenor',
            'lancar' => 'lancar','smooth' => 'lancar',
            'gagal' => 'gagal','fail' => 'gagal','failed' => 'gagal',
            'tolak' => 'tolak','reject' => 'tolak','rejected' => 'tolak',
            'kecewa' => 'kecewa','disappointed' => 'kecewa',
            'puas' => 'puas','satisfied' => 'puas','satisfaction' => 'puas',
            'risih' => 'risih','ganggu' => 'ganggu','disturb' => 'ganggu',
            'ribet' => 'ribet','complicated' => 'ribet','complex' => 'ribet',
            'mahal' => 'mahal','expensive' => 'mahal',
            'murah' => 'murah','cheap' => 'murah',
            'aman' => 'aman','safe' => 'aman','secure' => 'aman',
            'amanah' => 'amanah','trustworthy' => 'amanah',
            'respon' => 'respon','response' => 'respon','respond' => 'respon',
            'servis' => 'servis','service' => 'servis','layanan' => 'servis',
            'krem' => 'keren','keren' => 'keren','cool' => 'keren',
            'mantap' => 'mantap','awesome' => 'mantap','great' => 'mantap',
            'maknyus' => 'enak','enak' => 'enak','delicious' => 'enak',
            'bodog' => 'bodoh','bodoh' => 'bodoh','stupid' => 'bodoh',
            'penipuan' => 'penipuan','fraud' => 'penipuan','scam' => 'penipuan',
        ];
        $tokens = $this->tokenize($text);
        $normalized = array_map(function ($t) use ($dictionary) {
            return $dictionary[$t] ?? $t;
        }, $tokens);
        return implode(' ', $normalized);
    }

    private function normalizeElongation(string $text): string
    {
        // Reduce character repetitions (e.g., baguuuus -> bagus)
        $text = preg_replace('/(.)\1{2,}/u', '$1', $text);
        return $text;
    }

    private function tokenize(string $text): array
    {
        $text = trim($text);
        if ($text === '') return [];
        $parts = preg_split('/\s+/u', $text) ?: [];
        return array_values(array_filter($parts, fn($t) => $t !== ''));
    }

    private function filterStopwords(array $tokens): array
    {
        $stop = [
            // Common Indonesian stopwords
            'yang','dan','di','ke','dari','untuk','pada','dengan','atau','itu','ini','jadi','karena','agar','tidak','iya','sudah','ada','adalah',
            'aku','kamu','dia','kami','kita','mereka','apa','mengapa','bagaimana','sebuah','para','oleh','dalam','lu','gue','gua','sih','deh','kok','nih','tuh','ya','nah','loh','dong',
            'rt','via','dari','sebagai','bagi','bukan','kecuali','tanpa','para','serta','juga','boleh','mungkin','hanya','akan','telah','sedang','lagi','lah','pun','kah','nya','ku','mu',
            
            // Additional stopwords from your data
            'saya','saya','saya','saya','saya','saya','saya','saya','saya','saya',
            'terima','kasih','makasih','thanks','thank','you',
            'mohon','tolong','please','pls',
            'semoga','smoga','smg','hopefully',
            'jika','kalau','kalo','klo','if',
            'bisa','bisa','bisa','can','could',
            'sangat','banget','bgt','very','so',
            'sekali','sekali','sekali','once','one',
            'juga','juga','juga','also','too',
            'sudah','udah','udh','already','done',
            'belum','blm','blum','not yet','still',
            'akan','akan','akan','will','would',
            'telah','telah','telah','has','have',
            'sedang','sedang','sedang','currently','now',
            'lagi','lagi','lagi','again','more',
            'saja','aja','aja','just','only',
            'masih','msh','msih','still','yet',
            'hanya','hanya','hanya','only','just',
            'mungkin','mungkin','mungkin','maybe','perhaps',
            'boleh','boleh','boleh','may','can',
            'harus','harus','harus','must','should',
            'perlu','perlu','perlu','need','required',
            'mau','mau','mau','want','wanna',
            'ingin','ingin','ingin','want','wish',
            'bisa','bisa','bisa','can','able',
            'tidak','gak','ga','nggak','enggak','tdk','gk','tak','tk','no','not',
            'bukan','bkn','bukan','is not','not',
            'atau','ato','ata','or','either',
            'dan','dan','dan','and','plus',
            'dengan','dgn','dg','dengan','with',
            'untuk','utk','u','untuk','for',
            'dari','dr','dri','dari','from',
            'pada','pd','pda','pada','at',
            'dalam','dlm','dlam','dalam','in',
            'ke','ke','ke','ke','to',
            'di','di','di','di','at',
            'ini','ini','ini','ini','this',
            'itu','itu','itu','itu','that',
            'yang','yg','yang','yang','which',
            'saja','aja','aja','saja','just',
            'juga','jg','juga','juga','also',
            'lagi','lg','lgi','lagi','again',
            'masih','msh','msih','masih','still',
            'sudah','udh','udah','sudah','already',
            'belum','blm','blum','belum','not yet',
            'akan','akan','akan','akan','will',
            'telah','telah','telah','telah','has',
            'sedang','sedang','sedang','sedang','currently',
            'hanya','hanya','hanya','hanya','only',
            'mungkin','mungkin','mungkin','mungkin','maybe',
            'boleh','boleh','boleh','boleh','may',
            'harus','harus','harus','harus','must',
            'perlu','perlu','perlu','perlu','need',
            'mau','mau','mau','mau','want',
            'ingin','ingin','ingin','ingin','want',
            'bisa','bisa','bisa','bisa','can',
        ];
        
        // Filter out stopwords and very short words
        return array_values(array_filter($tokens, function ($t) use ($stop) {
            return !in_array($t, $stop, true) && mb_strlen($t) > 2;
        }));
    }

    private function stemTokens(array $tokens): array
    {
        // Improved Indonesian stemmer (more conservative)
        $suffixes = ['kan','i','an','lah','kah','pun','ku','mu','nya'];
        $prefixes = ['meng','meny','men','mem','me','peng','peny','pen','pem','pe','ber','be','ter','te','per','di','ke','se'];

        $stem = function (string $word) use ($suffixes, $prefixes): string {
            $w = $word;
            $originalLength = mb_strlen($w);
            
            // Skip stemming for very short words (less than 4 characters)
            if ($originalLength < 4) {
                return $w;
            }
            
            // Skip stemming for common words that shouldn't be stemmed
            $skipWords = ['sekali', 'penting', 'ting', 'us', 'dan', 'yang', 'ini', 'itu', 'ada', 'jadi', 'akan', 'sudah', 'bisa', 'harus', 'mau', 'ingin', 'perlu', 'boleh', 'mungkin', 'hanya', 'juga', 'saja', 'lagi', 'masih', 'sudah', 'belum', 'tidak', 'bukan', 'atau', 'dan', 'dengan', 'untuk', 'dari', 'pada', 'dalam', 'ke', 'di', 'adalah', 'akan', 'sudah', 'bisa', 'harus', 'mau', 'ingin', 'perlu', 'boleh', 'mungkin', 'hanya', 'juga', 'saja', 'lagi', 'masih', 'sudah', 'belum', 'tidak', 'bukan', 'atau'];
            
            if (in_array($w, $skipWords, true)) {
                return $w;
            }
            
            // More conservative suffix removal
            foreach ($suffixes as $s) {
                $suffixLength = mb_strlen($s);
                if ($originalLength > 5 && mb_substr($w, -$suffixLength) === $s) {
                    $stemmed = mb_substr($w, 0, $originalLength - $suffixLength);
                    // Only apply if stemmed word is at least 3 characters
                    if (mb_strlen($stemmed) >= 3) {
                        $w = $stemmed;
                        break;
                    }
                }
            }
            
            // More conservative prefix removal
            foreach ($prefixes as $p) {
                $prefixLength = mb_strlen($p);
                if (mb_strlen($w) > 6 && mb_substr($w, 0, $prefixLength) === $p) {
                    $stemmed = mb_substr($w, $prefixLength);
                    // Only apply if stemmed word is at least 3 characters
                    if (mb_strlen($stemmed) >= 3) {
                        $w = $stemmed;
                        break;
                    }
                }
            }
            
            // Final check: don't return words shorter than 2 characters
            return mb_strlen($w) >= 2 ? $w : $word;
        };

        return array_map(fn($t) => $stem($t), $tokens);
    }
}


