<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class LabellingController extends Controller
{
    public function index(Request $request)
    {
        $uploadedPath = $request->session()->get('label_csv_path');
        $preview = $request->session()->get('label_preview');
        $labeled = $request->session()->get('label_labeled');
        $learnedKeywords = $request->session()->get('learned_keywords', []);
        
        // Pagination
        $page = (int) $request->get('page', 1);
        $perPage = 100;
        $totalData = $request->session()->get('label_total_data', 0);
        $totalPages = ceil($totalData / $perPage);

        return view('labelling', [
            'uploadedPath' => $uploadedPath,
            'preview' => $preview,
            'labeled' => $labeled,
            'learnedKeywords' => $learnedKeywords,
            'currentPage' => $page,
            'totalPages' => $totalPages,
            'totalData' => $totalData,
            'perPage' => $perPage,
        ]);
    }

    public function upload(Request $request)
    {
        $request->validate([
            'csv_file' => ['required', 'file', 'mimes:csv,txt'],
        ]);

        $file = $request->file('csv_file');
        $path = $file->storeAs('labelling', now()->format('Ymd_His') . '_' . $file->getClientOriginalName());

        // Read CSV preview (first 200 rows to be safe)
        $fullPath = Storage::path($path);
        [$header, $rows] = $this->readCsv($fullPath, 2000);
        // Drop unwanted columns like score and time/at from preview
        [$header, $rows] = $this->filterColumns($header, $rows, ['score', 'time', 'at']);

        $request->session()->put('label_csv_path', $path);
        $request->session()->put('label_preview', ['header' => $header, 'rows' => $rows]);
        $request->session()->forget('label_labeled');

        return redirect()->route('labelling.index')->with('status', 'File CSV berhasil diupload.');
    }

    public function run(Request $request)
    {
        $path = $request->session()->get('label_csv_path');
        if (!$path || !Storage::exists($path)) {
            return redirect()->route('labelling.index')->with('error', 'Tidak ada file yang diupload.');
        }

        $fullPath = Storage::path($path);
        [$header, $rows] = $this->readCsv($fullPath, null);
        // Drop noisy columns that shouldn't be processed as text
        [$header, $rows] = $this->filterColumns($header, $rows, ['score', 'time', 'at']);

        // Determine tweet column
        $tweetIndex = $this->detectTweetColumnIndex($header);

        $labeledRows = [];
        foreach ($rows as $row) {
            $tweet = $tweetIndex !== null && isset($row[$tweetIndex]) ? (string) $row[$tweetIndex] : '';
            
            // Auto-labeling based on keywords (simple approach)
            $sentiment = $this->autoLabelSentiment($request, $tweet);

            $labeledRows[] = [
                'raw' => $tweet,
                'sentiment' => $sentiment,
                'confidence' => $this->calculateConfidence($tweet, $sentiment),
            ];
        }

        // Learn from any existing manual corrections
        $existingLabeled = $request->session()->get('label_labeled');
        if ($existingLabeled && isset($existingLabeled['rows'])) {
            $this->learnFromCorrections($request, $existingLabeled['rows']);
        }

        // Store data in temporary file instead of session
        $tempFile = 'temp_labeling_' . uniqid() . '.json';
        $tempPath = storage_path('app/temp/' . $tempFile);
        
        // Create temp directory if not exists
        if (!file_exists(storage_path('app/temp'))) {
            mkdir(storage_path('app/temp'), 0755, true);
        }
        
        // Save all data to temporary file
        file_put_contents($tempPath, json_encode([
            'header' => $header,
            'rows' => $labeledRows,
            'total_count' => count($labeledRows),
        ]));
        
        // Store only file reference and first page data in session
        $request->session()->put('label_temp_file', $tempFile);
        $request->session()->put('label_total_data', count($labeledRows));
        $request->session()->put('label_labeled', [
            'header' => $header,
            'rows' => array_slice($labeledRows, 0, 100), // Limit to first 100 rows
            'total_count' => count($labeledRows),
        ]);

        return redirect()->route('labelling.index')->with('status', 'Labelling selesai.');
    }

    public function getPage(Request $request)
    {
        $page = (int) $request->get('page', 1);
        $perPage = 100;
        
        $tempFile = $request->session()->get('label_temp_file');
        if (!$tempFile) {
            return redirect()->route('labelling.index')->with('error', 'Tidak ada data yang tersedia.');
        }
        
        $tempPath = storage_path('app/temp/' . $tempFile);
        if (!file_exists($tempPath)) {
            return redirect()->route('labelling.index')->with('error', 'File data tidak ditemukan.');
        }
        
        // Read data from temporary file
        $fileData = json_decode(file_get_contents($tempPath), true);
        $allData = $fileData['rows'] ?? [];
        $header = $fileData['header'] ?? [];
        
        if (empty($allData)) {
            return redirect()->route('labelling.index')->with('error', 'Tidak ada data yang tersedia.');
        }
        
        $totalData = count($allData);
        $totalPages = ceil($totalData / $perPage);
        
        if ($page < 1 || $page > $totalPages) {
            return redirect()->route('labelling.index')->with('error', 'Halaman tidak valid.');
        }
        
        $offset = ($page - 1) * $perPage;
        $pageData = array_slice($allData, $offset, $perPage);
        
        // Update session with current page data only
        $request->session()->put('label_labeled', [
            'header' => $header,
            'rows' => $pageData,
            'total_count' => $totalData,
        ]);
        
        return redirect()->route('labelling.index', ['page' => $page]);
    }

    public function updateLabel(Request $request)
    {
        $request->validate([
            'row_index' => 'required|integer|min:0',
            'sentiment' => 'required|in:positif,negatif,netral',
        ]);

        $labeled = $request->session()->get('label_labeled');
        if (!$labeled || !isset($labeled['rows'][$request->row_index])) {
            return redirect()->route('labelling.index')->with('error', 'Data tidak ditemukan.');
        }

        $labeled['rows'][$request->row_index]['sentiment'] = $request->sentiment;
        $labeled['rows'][$request->row_index]['confidence'] = 1.0; // Manual labeling = 100% confidence

        $request->session()->put('label_labeled', $labeled);

        return redirect()->route('labelling.index')->with('status', 'Label berhasil diupdate.');
    }

    public function bulkUpdate(Request $request)
    {
        $request->validate([
            'changes' => 'required|array',
            'changes.*' => 'in:positif,negatif,netral',
        ]);

        $labeled = $request->session()->get('label_labeled');
        if (!$labeled) {
            return response()->json(['success' => false, 'message' => 'Data tidak ditemukan.']);
        }

        $changes = $request->input('changes', []);
        $updatedCount = 0;

        // Get current page number to calculate global row indices
        $currentPage = (int) $request->get('page', 1);
        $perPage = 100;
        $globalOffset = ($currentPage - 1) * $perPage;

        $tempFile = $request->session()->get('label_temp_file');
        
        if ($tempFile) {
            $tempPath = storage_path('app/temp/' . $tempFile);
            if (file_exists($tempPath)) {
                // Read all data from file
                $fileData = json_decode(file_get_contents($tempPath), true);
                $allData = $fileData['rows'] ?? [];
                
                // Update changes in the full dataset
                foreach ($changes as $rowIndex => $sentiment) {
                    $globalIndex = $globalOffset + (int) $rowIndex;
                    if (isset($allData[$globalIndex])) {
                        $allData[$globalIndex]['sentiment'] = $sentiment;
                        $allData[$globalIndex]['confidence'] = 1.0; // Manual labeling = 100% confidence
                        $updatedCount++;
                    }
                }
                
                // Save updated data back to file
                $fileData['rows'] = $allData;
                file_put_contents($tempPath, json_encode($fileData));
                
                // Update current page data in session
                $pageData = array_slice($allData, $globalOffset, $perPage);
                $labeled['rows'] = $pageData;
                $request->session()->put('label_labeled', $labeled);
            }
        }

        return response()->json([
            'success' => true, 
            'message' => "Berhasil mengupdate {$updatedCount} label.",
            'updated_count' => $updatedCount
        ]);
    }

    public function download(Request $request): StreamedResponse
    {
        $tempFile = $request->session()->get('label_temp_file');
        if (!$tempFile) {
            return redirect()->route('labelling.index')->with('error', 'Belum ada hasil labelling.');
        }
        
        $tempPath = storage_path('app/temp/' . $tempFile);
        if (!file_exists($tempPath)) {
            return redirect()->route('labelling.index')->with('error', 'File data tidak ditemukan.');
        }
        
        // Read all data from temporary file
        $fileData = json_decode(file_get_contents($tempPath), true);
        $allData = $fileData['rows'] ?? [];
        
        if (empty($allData)) {
            return redirect()->route('labelling.index')->with('error', 'Tidak ada data untuk didownload.');
        }

        $filename = 'labelling_' . now()->format('Ymd_His') . '.csv';

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ];

        return response()->stream(function () use ($allData) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['raw', 'sentiment', 'confidence']);
            
            // Download all data from file
            foreach ($allData as $r) {
                fputcsv($out, [$r['raw'], $r['sentiment'], $r['confidence']]);
            }
            
            fclose($out);
        }, 200, $headers);
    }

    public function cleanup(Request $request)
    {
        $tempFile = $request->session()->get('label_temp_file');
        if ($tempFile) {
            $tempPath = storage_path('app/temp/' . $tempFile);
            if (file_exists($tempPath)) {
                unlink($tempPath);
            }
        }
        
        // Clear session data
        $request->session()->forget(['label_temp_file', 'label_total_data', 'label_labeled', 'label_csv_path', 'label_preview']);
        
        return redirect()->route('labelling.index')->with('status', 'Data berhasil dibersihkan.');
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
        $candidates = ['tweet', 'text', 'content', 'message', 'body', 'review', 'ulasan'];
        foreach ($header as $idx => $name) {
            $lower = strtolower(trim((string) $name));
            if (in_array($lower, $candidates, true)) {
                return $idx;
            }
        }
        // fallback to last column
        return count($header) ? count($header) - 1 : null;
    }

    private function autoLabelSentiment(Request $request, string $text): string
    {
        $text = mb_strtolower($text, 'UTF-8');
        
        // Get learned keywords from manual corrections
        $learnedKeywords = $this->getLearnedKeywords($request);
        
        // Enhanced positive keywords with weights
        $positiveKeywords = array_merge([
            // Strong positive (weight: 3)
            'sangat bagus', 'sangat baik', 'sangat puas', 'sangat senang', 'sangat suka',
            'terbaik', 'terlalu bagus', 'terlalu baik', 'benar-benar bagus', 'benar-benar baik',
            'perfect', 'sempurna', 'awesome', 'amazing', 'fantastic', 'brilliant', 'excellent',
            'love', 'loved', 'loving', 'recommended', 'recommend', 'highly recommend',
            
            // Medium positive (weight: 2)
            'bagus', 'baik', 'mantap', 'keren', 'suka', 'senang', 'puas', 'great', 'good', 'nice',
            'wonderful', 'memuaskan', 'kepuasan', 'enjoy', 'fun', 'enak', 'lezat', 'nyaman',
            'mudah', 'simple', 'praktis', 'berhasil', 'sukses', 'menang', 'profit', 'untung',
            'benefit', 'helpful', 'useful', 'effective', 'efficient', 'fast', 'cepat',
            'lancar', 'smooth', 'aman', 'safe', 'secure', 'amanah', 'trustworthy',
            
            // Weak positive (weight: 1)
            'ok', 'okay', 'fine', 'alright', 'bisa', 'boleh', 'mungkin', 'hampir', 'hampir bagus',
            'lumayan', 'cukup', 'decent', 'acceptable', 'satisfied', 'satisfaction'
        ], $learnedKeywords['positive'] ?? []);
        
        // Enhanced negative keywords with weights
        $negativeKeywords = array_merge([
            // Strong negative (weight: 3)
            'sangat buruk', 'sangat jelek', 'sangat kecewa', 'sangat menyesal', 'sangat gagal',
            'terburuk', 'terlalu buruk', 'terlalu jelek', 'benar-benar buruk', 'benar-benar jelek',
            'terrible', 'awful', 'horrible', 'disgusting', 'hate', 'hated', 'hating',
            'worst', 'sucks', 'sucked', 'sucking', 'disappointed', 'disappointing',
            
            // Medium negative (weight: 2)
            'buruk', 'jelek', 'gagal', 'fail', 'failed', 'failing', 'error', 'salah', 'wrong',
            'rusak', 'broken', 'menyesal', 'kecewa', 'regret', 'boring', 'membosankan',
            'ribet', 'sulit', 'difficult', 'hard', 'complicated', 'complex', 'confusing',
            'mahal', 'expensive', 'overpriced', 'rugi', 'loss', 'kerugian', 'waste',
            'lambat', 'slow', 'delay', 'terlambat', 'late', 'menunggu', 'waiting',
            'ganggu', 'disturb', 'disturbing', 'annoying', 'frustrating', 'frustrated',
            'penipuan', 'fraud', 'scam', 'scamming', 'cheat', 'cheating', 'fake',
            
            // Weak negative (weight: 1)
            'tidak bagus', 'tidak baik', 'tidak suka', 'tidak senang', 'tidak puas',
            'gak bagus', 'ga bagus', 'nggak bagus', 'enggak bagus', 'tdk bagus',
            'bukan bagus', 'bukan baik', 'bukan suka', 'bukan senang', 'bukan puas',
            'biasa', 'mediocre', 'average', 'standar', 'normal', 'so-so', 'meh'
        ], $learnedKeywords['negative'] ?? []);
        
        // Neutral indicators
        $neutralKeywords = [
            'netral', 'neutral', 'biasa', 'normal', 'standar', 'average', 'mediocre',
            'ok', 'okay', 'fine', 'alright', 'so-so', 'meh', 'tidak tahu', 'gak tahu',
            'mungkin', 'perhaps', 'maybe', 'bisa jadi', 'kemungkinan', 'probable'
        ];
        
        // Calculate weighted scores
        $positiveScore = 0;
        $negativeScore = 0;
        $neutralScore = 0;
        
        // Check for strong positive patterns
        foreach ($positiveKeywords as $keyword) {
            if (str_contains($text, $keyword)) {
                if (str_contains($keyword, 'sangat ') || str_contains($keyword, 'terlalu ') || 
                    str_contains($keyword, 'benar-benar ') || in_array($keyword, ['perfect', 'sempurna', 'awesome', 'amazing', 'fantastic', 'brilliant', 'excellent', 'love', 'loved', 'loving', 'recommended', 'recommend', 'highly recommend'])) {
                    $positiveScore += 3; // Strong positive
                } elseif (in_array($keyword, ['bagus', 'baik', 'mantap', 'keren', 'suka', 'senang', 'puas', 'great', 'good', 'nice', 'wonderful', 'memuaskan', 'kepuasan', 'enjoy', 'fun', 'enak', 'lezat', 'nyaman', 'mudah', 'simple', 'praktis', 'berhasil', 'sukses', 'menang', 'profit', 'untung', 'benefit', 'helpful', 'useful', 'effective', 'efficient', 'fast', 'cepat', 'lancar', 'smooth', 'aman', 'safe', 'secure', 'amanah', 'trustworthy'])) {
                    $positiveScore += 2; // Medium positive
                } else {
                    $positiveScore += 1; // Weak positive
                }
            }
        }
        
        // Check for strong negative patterns
        foreach ($negativeKeywords as $keyword) {
            if (str_contains($text, $keyword)) {
                if (str_contains($keyword, 'sangat ') || str_contains($keyword, 'terlalu ') || 
                    str_contains($keyword, 'benar-benar ') || in_array($keyword, ['terrible', 'awful', 'horrible', 'disgusting', 'hate', 'hated', 'hating', 'worst', 'sucks', 'sucked', 'sucking', 'disappointed', 'disappointing'])) {
                    $negativeScore += 3; // Strong negative
                } elseif (in_array($keyword, ['buruk', 'jelek', 'gagal', 'fail', 'failed', 'failing', 'error', 'salah', 'wrong', 'rusak', 'broken', 'menyesal', 'kecewa', 'regret', 'boring', 'membosankan', 'ribet', 'sulit', 'difficult', 'hard', 'complicated', 'complex', 'confusing', 'mahal', 'expensive', 'overpriced', 'rugi', 'loss', 'kerugian', 'waste', 'lambat', 'slow', 'delay', 'terlambat', 'late', 'menunggu', 'waiting', 'ganggu', 'disturb', 'disturbing', 'annoying', 'frustrating', 'frustrated', 'penipuan', 'fraud', 'scam', 'scamming', 'cheat', 'cheating', 'fake'])) {
                    $negativeScore += 2; // Medium negative
                } else {
                    $negativeScore += 1; // Weak negative
                }
            }
        }
        
        // Check for neutral patterns
        foreach ($neutralKeywords as $keyword) {
            if (str_contains($text, $keyword)) {
                $neutralScore += 1;
            }
        }
        
        // Check for negation patterns that might reverse sentiment
        $negationWords = ['tidak', 'gak', 'ga', 'nggak', 'enggak', 'tdk', 'gk', 'tak', 'tk', 'bukan', 'bkn', 'no', 'not', 'never', 'neither', 'nor'];
        $hasNegation = false;
        foreach ($negationWords as $neg) {
            if (str_contains($text, $neg)) {
                $hasNegation = true;
                break;
            }
        }
        
        // If there's negation, reduce the dominant score
        if ($hasNegation) {
            if ($positiveScore > $negativeScore) {
                $positiveScore = max(0, $positiveScore - 1);
            } elseif ($negativeScore > $positiveScore) {
                $negativeScore = max(0, $negativeScore - 1);
            }
        }
        
        // Check for intensifiers that boost sentiment
        $intensifiers = ['sangat', 'banget', 'sekali', 'terlalu', 'benar-benar', 'really', 'very', 'so', 'extremely', 'highly'];
        $hasIntensifier = false;
        foreach ($intensifiers as $int) {
            if (str_contains($text, $int)) {
                $hasIntensifier = true;
                break;
            }
        }
        
        // If there's intensifier, boost the dominant score
        if ($hasIntensifier) {
            if ($positiveScore > $negativeScore) {
                $positiveScore += 1;
            } elseif ($negativeScore > $positiveScore) {
                $negativeScore += 1;
            }
        }
        
        // Determine sentiment based on weighted scores
        if ($positiveScore > $negativeScore && $positiveScore > $neutralScore) {
            return 'positif';
        } elseif ($negativeScore > $positiveScore && $negativeScore > $neutralScore) {
            return 'negatif';
        } else {
            return 'netral';
        }
    }

    private function calculateConfidence(string $text, string $sentiment): float
    {
        $text = mb_strtolower($text, 'UTF-8');
        
        // Enhanced confidence calculation based on multiple factors
        $confidence = 0.0;
        
        // Factor 1: Keyword strength and frequency
        $strongKeywords = [
            'positif' => ['sangat bagus', 'sangat baik', 'sangat puas', 'terbaik', 'perfect', 'sempurna', 'awesome', 'amazing', 'fantastic', 'brilliant', 'excellent', 'love', 'loved', 'recommended', 'highly recommend'],
            'negatif' => ['sangat buruk', 'sangat jelek', 'sangat kecewa', 'terburuk', 'terrible', 'awful', 'horrible', 'disgusting', 'hate', 'hated', 'worst', 'sucks', 'disappointed'],
            'netral' => ['netral', 'neutral', 'biasa', 'normal', 'standar', 'average', 'mediocre', 'ok', 'okay', 'fine', 'alright']
        ];
        
        $mediumKeywords = [
            'positif' => ['bagus', 'baik', 'mantap', 'keren', 'suka', 'senang', 'puas', 'great', 'good', 'nice', 'wonderful', 'memuaskan', 'enjoy', 'fun', 'enak', 'nyaman', 'mudah', 'berhasil', 'sukses', 'aman', 'amanah'],
            'negatif' => ['buruk', 'jelek', 'gagal', 'fail', 'error', 'salah', 'rusak', 'menyesal', 'kecewa', 'boring', 'ribet', 'sulit', 'mahal', 'lambat', 'ganggu', 'penipuan', 'fraud'],
            'netral' => ['biasa', 'normal', 'standar', 'average', 'mediocre', 'ok', 'okay', 'fine', 'alright', 'so-so', 'meh']
        ];
        
        $weakKeywords = [
            'positif' => ['ok', 'okay', 'fine', 'alright', 'bisa', 'boleh', 'mungkin', 'lumayan', 'cukup', 'decent', 'acceptable'],
            'negatif' => ['tidak bagus', 'tidak baik', 'gak bagus', 'ga bagus', 'nggak bagus', 'bukan bagus', 'biasa', 'mediocre', 'average'],
            'netral' => ['tidak tahu', 'gak tahu', 'mungkin', 'perhaps', 'maybe', 'bisa jadi', 'kemungkinan']
        ];
        
        // Count keyword matches by strength
        $strongCount = 0;
        $mediumCount = 0;
        $weakCount = 0;
        
        if (isset($strongKeywords[$sentiment])) {
            foreach ($strongKeywords[$sentiment] as $keyword) {
                if (str_contains($text, $keyword)) {
                    $strongCount++;
                }
            }
        }
        
        if (isset($mediumKeywords[$sentiment])) {
            foreach ($mediumKeywords[$sentiment] as $keyword) {
                if (str_contains($text, $keyword)) {
                    $mediumCount++;
                }
            }
        }
        
        if (isset($weakKeywords[$sentiment])) {
            foreach ($weakKeywords[$sentiment] as $keyword) {
                if (str_contains($text, $keyword)) {
                    $weakCount++;
                }
            }
        }
        
        // Factor 2: Text length and complexity
        $textLength = mb_strlen($text);
        $wordCount = count(preg_split('/\s+/', $text));
        
        // Factor 3: Intensifiers and negation
        $intensifiers = ['sangat', 'banget', 'sekali', 'terlalu', 'benar-benar', 'really', 'very', 'so', 'extremely', 'highly'];
        $negationWords = ['tidak', 'gak', 'ga', 'nggak', 'enggak', 'tdk', 'gk', 'tak', 'tk', 'bukan', 'bkn', 'no', 'not', 'never'];
        
        $hasIntensifier = false;
        $hasNegation = false;
        
        foreach ($intensifiers as $int) {
            if (str_contains($text, $int)) {
                $hasIntensifier = true;
                break;
            }
        }
        
        foreach ($negationWords as $neg) {
            if (str_contains($text, $neg)) {
                $hasNegation = true;
                break;
            }
        }
        
        // Calculate base confidence from keyword strength
        $confidence += ($strongCount * 0.3) + ($mediumCount * 0.2) + ($weakCount * 0.1);
        
        // Adjust for text characteristics
        if ($textLength > 50) {
            $confidence += 0.1; // Longer text usually more confident
        }
        
        if ($wordCount > 10) {
            $confidence += 0.05; // More words usually more confident
        }
        
        // Adjust for intensifiers
        if ($hasIntensifier) {
            $confidence += 0.15; // Intensifiers increase confidence
        }
        
        // Adjust for negation (reduces confidence)
        if ($hasNegation) {
            $confidence -= 0.1; // Negation reduces confidence
        }
        
        // Factor 4: Contradictory sentiment detection
        $oppositeSentiment = $sentiment === 'positif' ? 'negatif' : ($sentiment === 'negatif' ? 'positif' : 'netral');
        $oppositeCount = 0;
        
        if (isset($strongKeywords[$oppositeSentiment])) {
            foreach ($strongKeywords[$oppositeSentiment] as $keyword) {
                if (str_contains($text, $keyword)) {
                    $oppositeCount++;
                }
            }
        }
        
        if (isset($mediumKeywords[$oppositeSentiment])) {
            foreach ($mediumKeywords[$oppositeSentiment] as $keyword) {
                if (str_contains($text, $keyword)) {
                    $oppositeCount++;
                }
            }
        }
        
        // Reduce confidence if contradictory sentiment found
        if ($oppositeCount > 0) {
            $confidence -= ($oppositeCount * 0.1);
        }
        
        // Factor 5: Emoji and punctuation analysis
        $emojiCount = preg_match_all('/[\x{1F600}-\x{1F64F}]|[\x{1F300}-\x{1F5FF}]|[\x{1F680}-\x{1F6FF}]|[\x{1F1E0}-\x{1F1FF}]|[\x{2600}-\x{26FF}]|[\x{2700}-\x{27BF}]/u', $text);
        $exclamationCount = substr_count($text, '!');
        $questionCount = substr_count($text, '?');
        
        if ($emojiCount > 0) {
            $confidence += 0.05; // Emojis add context
        }
        
        if ($exclamationCount > 0) {
            $confidence += 0.03; // Exclamations add emphasis
        }
        
        // Normalize confidence to 0.1 - 0.95 range
        $confidence = max(0.1, min(0.95, $confidence));
        
        return $confidence;
    }

    private function getLearnedKeywords(Request $request): array
    {
        $learnedData = $request->session()->get('learned_keywords', []);
        return $learnedData;
    }

    private function saveLearnedKeywords(Request $request, array $keywords): void
    {
        $request->session()->put('learned_keywords', $keywords);
    }

    private function learnFromCorrections(Request $request, array $labeledRows): void
    {
        $learnedKeywords = [
            'positive' => [],
            'negative' => [],
            'neutral' => []
        ];

        foreach ($labeledRows as $row) {
            $text = mb_strtolower($row['raw'], 'UTF-8');
            $sentiment = $row['sentiment'];
            
            // Extract words from text
            $words = preg_split('/\s+/', $text);
            $words = array_filter($words, function($word) {
                return mb_strlen($word) > 2 && !in_array($word, ['yang', 'dan', 'di', 'ke', 'dari', 'untuk', 'pada', 'dengan', 'atau', 'itu', 'ini', 'jadi', 'karena', 'agar', 'tidak', 'iya', 'sudah', 'ada', 'adalah']);
            });

            foreach ($words as $word) {
                if (!isset($learnedKeywords[$sentiment][$word])) {
                    $learnedKeywords[$sentiment][$word] = 0;
                }
                $learnedKeywords[$sentiment][$word]++;
            }
        }

        // Keep only words that appear multiple times and are significant
        foreach ($learnedKeywords as $sentiment => $words) {
            $learnedKeywords[$sentiment] = array_keys(array_filter($words, function($count) {
                return $count >= 2; // Word must appear at least 2 times
            }));
        }

        $this->saveLearnedKeywords($request, $learnedKeywords);
    }
}

