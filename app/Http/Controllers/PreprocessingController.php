<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Controller untuk tahap preprocessing data teks (Tahap 1 Pipeline Machine Learning)
 * 
 * ========================================================================
 * FUNGSI UTAMA: Membersihkan dan memproses data mentah dari media sosial
 * ========================================================================
 * 
 * Proses preprocessing yang dilakukan:
 * 1. Case Folding - Konversi semua huruf menjadi lowercase
 * 2. Cleansing - Menghapus URL, mention, hashtag, karakter khusus
 * 3. Normalisasi - Mengubah slang/colloquial words ke bahasa baku
 * 4. Tokenizing - Memecah teks menjadi kata-kata individual
 * 5. Stopword Removal - Menghapus kata-kata umum yang tidak bernilai sentimen
 * 6. Stemming - Mengembalikan kata ke bentuk dasarnya
 * 
 * INPUT: Data mentah dari Twitter/Facebook (format CSV)
 * OUTPUT: Data bersih siap untuk proses labeling
 * 
 * @package App\Http\Controllers
 * @author Developer
 * @version 1.0
 */
class PreprocessingController extends Controller
{
    /**
     * Menampilkan halaman utama preprocessing
     * 
     * Fungsi: Menampilkan interface preprocessing dengan data yang sudah diupload
     * Data yang ditampilkan:
     * - Path file CSV yang diupload
     * - Preview data (header + 100 baris pertama)
     * - Hasil preprocessing (jika sudah dijalankan)
     * 
     * @param Request $request HTTP request object
     * @return \Illuminate\View\View View 'preprocessing' dengan data session
     */
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

    /**
     * Upload file CSV data mentah untuk preprocessing
     * 
     * Proses upload:
     * 1. Validasi file (CSV/TXT only)
     * 2. Generate nama file unik dengan timestamp
     * 3. Simpan ke storage/app/preprocessing/
     * 4. Baca preview data (2000 baris pertama)
     * 5. Filter kolom yang tidak diperlukan (score, time, at)
     * 6. Simpan path dan preview ke session
     * 
     * Format CSV yang diharapkan:
     * - Kolom wajib: ulasan/text/content
     * - Kolom opsional: score, time, at (akan dihapus)
     * - Dari labelling: raw, sentiment, confidence
     * 
     * @param Request $request HTTP request dengan file upload
     * @return \Illuminate\Http\RedirectResponse Redirect ke halaman preprocessing dengan status
     * @throws \Illuminate\Validation\ValidationException Jika file tidak valid
     */
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
        
        // Check if this is labelled data (from labelling step)
        $isLabelledData = $this->isLabelledData($header);
        
        if ($isLabelledData) {
            // For labelled data, keep all columns (raw, sentiment, confidence)
            $request->session()->put('pre_is_labelled', true);
        } else {
            // For raw data, drop unwanted columns like score and time/at
            [$header, $rows] = $this->filterColumns($header, $rows, ['score', 'time', 'at']);
            $request->session()->put('pre_is_labelled', false);
        }

        $request->session()->put('pre_csv_path', $path);
        $request->session()->put('pre_preview', ['header' => $header, 'rows' => $rows]);
        $request->session()->forget('pre_processed');

        return redirect()->route('preprocessing.index')->with('status', 'File CSV berhasil diupload.');
    }

    /**
     * Menjalankan proses preprocessing lengkap pada dataset
     * 
     * ========================================================================
     * ALUR PROSES PREPROCESSING LENGKAP
     * ========================================================================
     * 
     * 1. VALIDASI INPUT
     *    - Cek keberadaan file di session
     *    - Baca semua data dari CSV
     *    - Filter kolom yang tidak relevan (untuk data mentah)
     *    - Deteksi kolom teks otomatis
     *    - Cek apakah data sudah berlabel
     * 
     * 2. PREPROCESSING PER BARIS (6 Tahap)
     *    - Case Folding: "SAYA Suka" → "saya suka"
     *    - Cleansing: "cek @user http://url" → "cek"
     *    - Normalisasi: "gak" → "tidak", "bgus" → "bagus"
     *    - Tokenizing: "saya suka" → ["saya", "suka"]
     *    - Stopword Removal: ["saya", "suka"] → ["suka"]
     *    - Stemming: ["suka"] → ["suka"]
     * 
     * 3. VALIDASI OUTPUT
     *    - Skip data kosong (< 3 karakter)
     *    - Skip data tanpa tokens meaningful
     *    - Simpan semua tahap untuk debugging
     *    - Untuk data berlabel: pertahankan label
     * 
     * 4. SIMPAN HASIL
     *    - Store ke session untuk display
     *    - Redirect dengan status sukses
     * 
     * @param Request $request HTTP request dengan session data preprocessing
     * @return \Illuminate\Http\RedirectResponse Redirect dengan status preprocessing selesai
     * @throws \Exception Jika file tidak ditemukan atau error processing
     */
    public function run(Request $request)
    {
        $path = $request->session()->get('pre_csv_path');
        if (!$path || !Storage::exists($path)) {
            return redirect()->route('preprocessing.index')->with('error', 'Tidak ada file yang diupload.');
        }

        $fullPath = Storage::path($path);
        [$header, $rows] = $this->readCsv($fullPath, null);
        
        // Check if this is labelled data
        $isLabelledData = $request->session()->get('pre_is_labelled', false);
        
        if ($isLabelledData) {
            // For labelled data, process only text but keep labels
            return $this->processLabelledData($request, $header, $rows);
        } else {
            // For raw data, use original processing
            return $this->processRawData($request, $header, $rows);
        }
    }

    /**
     * Download hasil preprocessing dalam format CSV
     * 
     * Output format:
     * - Untuk data mentah: Header 'preprocessed', Data: 1 kolom hasil stemming
     * - Untuk data berlabel: Header 'preprocessed,sentiment', Data: hasil + label (tanpa confidence)
     * - Filename: preprocessing_YYYYMMDD_HHMMSS.csv
     * 
     * @param Request $request HTTP request dengan session data preprocessing
     * @return \Symfony\Component\HttpFoundation\StreamedResponse File CSV download
     * @return \Illuminate\Http\RedirectResponse Jika belum ada hasil preprocessing
     */
    public function download(Request $request): StreamedResponse
    {
        $processed = $request->session()->get('pre_processed');
        if (!$processed) {
            return redirect()->route('preprocessing.index')->with('error', 'Belum ada hasil preprocessing.');
        }

        $isLabelledData = $request->session()->get('pre_is_labelled', false);
        $filename = 'preprocessing_' . now()->format('Ymd_His') . '.csv';

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ];

        return response()->stream(function () use ($processed, $isLabelledData) {
            $out = fopen('php://output', 'w');
            
            if ($isLabelledData) {
                // Output for labelled data: preprocessed text + original labels (no confidence)
                fputcsv($out, ['preprocessed', 'sentiment']);
                foreach ($processed['rows'] as $r) {
                    fputcsv($out, [$r['stemming'], $r['sentiment']]);
                }
            } else {
                // Output for raw data: only preprocessed text
                fputcsv($out, ['preprocessed']);
                foreach ($processed['rows'] as $r) {
                    fputcsv($out, [$r['stemming']]);
                }
            }
            
            fclose($out);
        }, 200, $headers);
    }

    /**
     * Membaca file CSV dengan optimalisasi memory
     * 
     * Fitur:
     * - Handle file besar dengan limit parameter
     * - Auto-detect header CSV
     * - Error handling untuk file tidak valid
     * - Memory efficient untuk dataset besar
     * 
     * @param string $path Full path ke file CSV
     * @param int|null $limit Jumlah baris maksimal yang dibaca (null = semua)
     * @return array Array [header, rows] dengan header dan data rows
     */
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

    /**
     * Deteksi otomatis kolom yang berisi teks/ulasan
     * 
     * Strategi deteksi:
     * 1. Cari kolom dengan nama common: ulasan, text, content, message, body
     * 2. Case-insensitive matching
     * 3. Fallback ke kolom terakhir jika tidak ditemukan
     * 
     * @param array $header Array nama kolom dari CSV
     * @return int|null Index kolom teks atau null jika tidak ada
     */
    private function detectUlasanColumnIndex(array $header): ?int
    {
        if (empty($header)) return null;
        $candidates = ['ulasan', 'text', 'content', 'message', 'body'];
        foreach ($header as $idx => $name) {
            $lower = strtolower(trim((string) $name));
            if (in_array($lower, $candidates, true)) {
                return $idx;
            }
        }
        // fallback to last column
        return count($header) ? count($header) - 1 : null;
    }

    /**
     * Tahap 1 Preprocessing: Case Folding
     * 
     * Fungsi: Konversi semua karakter ke huruf kecil
     * Tujuan: Standardisasi case untuk konsistensi processing
     * 
     * Contoh:
     * Input: "SAYA Suka PINJOL Online"
     * Output: "saya suka pinjol online"
     * 
     * @param string $text Teks yang akan di-case folding
     * @return string Teks dalam lowercase (UTF-8 compatible)
     */
    private function caseFold(string $text): string
    {
        return mb_strtolower($text, 'UTF-8');
    }

    /**
     * Tahap 2 Preprocessing: Text Cleansing
     * 
     * Fungsi: Menghapus elemen-elemen yang tidak relevan dari teks media sosial
     * 
     * Yang dihapus:
     * - URLs (http://, https://)
     * - Email addresses
     * - Repost markers (rt, via)
     * - Mention usernames (@username)
     * - Hashtags (#hashtag)
     * - Excessive punctuation dan quotes
     * - Numbers dan special characters
     * - Extra whitespace
     * 
     * Contoh:
     * Input: "cek @user http://site.com #pinjol rt via"
     * Output: "cek"
     * 
     * @param string $text Teks yang akan di-cleansing
     * @return string Teks bersih dari noise elements
     * 387 merupakan fungsi untuk cleaning
     */
    private function cleanse(string $text): string
    {
        // hapus URLs, emails, mentions, hashtags
        $text = preg_replace('/https?:\/\/\S+/ui', ' ', $text); // URLs
        $text = preg_replace('/\b[\w.+-]+@[\w.-]+\.[a-z]{2,}\b/ui', ' ', $text); // emails
        $text = preg_replace('/\b(rt|via)\b/ui', ' ', $text); // repost markers
        $text = preg_replace('/[@#][\w_]+/u', ' ', $text); // mentions & hashtags
        
        // Remove tanda kutip dan tanda baca
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
        
        // Keep only huruf dan spasi
        preg_replace('/[^\p{L}\s]/u', ' ', $text);
        
        // Clean up spasi berlebih
        $text = preg_replace('/\s+/u', ' ', $text);
        return trim($text);
    }
        //fungsi untuk normalisasi kata
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
        //tokenize text memecahkan kalimat menjadi kata
        $tokens = $this->tokenize($text);
        $normalized = array_map(function ($t) use ($dictionary) {
            return $dictionary[$t] ?? $t;
        }, $tokens);
        //kembalikan kata menjadi kalimat
        return implode(' ', $normalized);
    }

    private function normalizeElongation(string $text): string
    {
        // Reduce character repetitions (e.g., baguuuus -> bagus)
        $text = preg_replace('/(.)\1{2,}/u', '$1', $text);
        return $text;
    }
    //memecah kalimat mendjadi kata-kata atau token
    private function tokenize(string $text): array
    {
        $text = trim($text);
        if ($text === '') return [];
        //memecah kata berdasarkan spasi
        $parts = preg_split('/\s+/u', $text) ?: [];
        return array_values(array_filter($parts, fn($t) => $t !== ''));
    }
    //stopword removal menghapus kata yang tidak penting
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
        //stemming mengubah kata menjadi dasar
    private function stemTokens(array $tokens): array
    {
        // Improved Indonesian stemmer (more conservative)
        $suffixes = ['kan','i','an','lah','kah','pun','ku','mu','nya'];
        $prefixes = ['meng','meny','men','mem','me','peng','peny','pen','pem','pe','ber','be','ter','te','per','di','ke','se'];
        //memproses kata satu per satu
        $stem = function (string $word) use ($suffixes, $prefixes): string {
            $w = $word;
            $originalLength = mb_strlen($w);
            
            // Skip stemming jika kata pendek
            if ($originalLength < 4) {
                return $w;
            }
            
            // Skip stemming for kata tertentu
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

    /**
     * Check if CSV data is from labelling step
     * 
     * @param array $header CSV header columns
     * @return bool True if data contains labelling columns
     */
    private function isLabelledData(array $header): bool
    {
        if (empty($header)) {
            return false;
        }
        
        $headerLower = array_map('strtolower', $header);
        
        // Check for labelling-specific columns
        return in_array('raw', $headerLower) && 
               (in_array('sentiment', $headerLower) || in_array('label', $headerLower));
    }

    /**
     * Process labelled data - only preprocess text but keep labels
     * 
     * @param Request $request HTTP request
     * @param array $header CSV header
     * @param array $rows CSV data rows
     * @return \Illuminate\Http\RedirectResponse
     */
    private function processLabelledData(Request $request, array $header, array $rows)
    {
        // Find column indices
        $rawIndex = $this->findColumnIndex($header, ['raw', 'text', 'tweet', 'content']);
        $sentimentIndex = $this->findColumnIndex($header, ['sentiment', 'label']);

        $processedRows = [];
        foreach ($rows as $row) {
            $rawText = $rawIndex !== null && isset($row[$rawIndex]) ? (string) $row[$rawIndex] : '';
            $sentiment = $sentimentIndex !== null && isset($row[$sentimentIndex]) ? (string) $row[$sentimentIndex] : 'netral';

            // Preprocess the text (same steps as original)
            if (mb_strlen(trim($rawText)) < 3) {
                $processedRows[] = [
                    'raw' => $rawText,
                    'case_folding' => '',
                    'cleansing' => '',
                    'normalisasi' => '',
                    'tokenizing' => '',
                    'filtering' => '',
                    'stemming' => '',
                    'sentiment' => $sentiment,
                ];
                continue;
            }

            $caseFolding = $this->caseFold($rawText);
            $cleansed = $this->cleanse($caseFolding);
            $normalized = $this->normalize($cleansed);
            $normalized = $this->normalizeElongation($normalized);
            $tokens = $this->tokenize($normalized);
            $filteredTokens = $this->filterStopwords($tokens);
            $stemmedTokens = $this->stemTokens($filteredTokens);

            if (empty($stemmedTokens)) {
                $processedRows[] = [
                    'raw' => $rawText,
                    'case_folding' => $caseFolding,
                    'cleansing' => $cleansed,
                    'normalisasi' => $normalized,
                    'tokenizing' => implode(' ', $tokens),
                    'filtering' => implode(' ', $filteredTokens),
                    'stemming' => '',
                    'sentiment' => $sentiment,
                ];
                continue;
            }

            $processedRows[] = [
                'raw' => $rawText,
                'case_folding' => $caseFolding,
                'cleansing' => $cleansed,
                'normalisasi' => $normalized,
                'tokenizing' => implode(' ', $tokens),
                'filtering' => implode(' ', $filteredTokens),
                'stemming' => implode(' ', $stemmedTokens),
                'sentiment' => $sentiment,
            ];
        }

        $request->session()->put('pre_processed', [
            'header' => $header,
            'rows' => $processedRows,
        ]);

        return redirect()->route('preprocessing.index')->with('status', 'Preprocessing data berlabel selesai. Label dipertahankan tanpa confidence.');
    }

    /**
     * Process raw data (original preprocessing logic)
     * 
     * @param Request $request HTTP request
     * @param array $header CSV header
     * @param array $rows CSV data rows
     * @return \Illuminate\Http\RedirectResponse
     */
    private function processRawData(Request $request, array $header, array $rows)
    {
        // Drop noisy columns that shouldn't be processed as text
        [$header, $rows] = $this->filterColumns($header, $rows, ['score', 'time', 'at']);

        // Determine ulasan column
        $ulasanIndex = $this->detectUlasanColumnIndex($header);

        $processedRows = [];
        foreach ($rows as $row) {
            $ulasan = $ulasanIndex !== null && isset($row[$ulasanIndex]) ? (string) $row[$ulasanIndex] : '';

            // Skip empty or very short ulasan
            if (mb_strlen(trim($ulasan)) < 3) {
                $processedRows[] = [
                    'raw' => $ulasan,
                    'case_folding' => '',
                    'cleansing' => '',
                    'normalisasi' => '',
                    'tokenizing' => '',
                    'filtering' => '',
                    'stemming' => '',
                ];
                continue;
            }

            $caseFolding = $this->caseFold($ulasan);
            $cleansed = $this->cleanse($caseFolding);
            $normalized = $this->normalize($cleansed);
            $normalized = $this->normalizeElongation($normalized);
            $tokens = $this->tokenize($normalized);
            $filteredTokens = $this->filterStopwords($tokens);
            $stemmedTokens = $this->stemTokens($filteredTokens);

            // Skip if no meaningful tokens left
            if (empty($stemmedTokens)) {
                $processedRows[] = [
                    'raw' => $ulasan,
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
                'raw' => $ulasan,
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

    /**
     * Find column index by possible names
     * 
     * @param array $header CSV header
     * @param array $possibleNames Possible column names
     * @return int|null Column index or null if not found
     */
    private function findColumnIndex(array $header, array $possibleNames): ?int
    {
        if (empty($header)) {
            return null;
        }
        
        foreach ($header as $index => $columnName) {
            $lowerName = strtolower(trim((string) $columnName));
            if (in_array($lowerName, array_map('strtolower', $possibleNames), true)) {
                return $index;
            }
        }
        
        return null;
    }
}


