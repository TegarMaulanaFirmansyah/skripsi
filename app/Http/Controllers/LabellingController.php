<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Controller untuk tahap Labelling Sentimen (Tahap 2 Pipeline Machine Learning)
 * 
 * ========================================================================
 * FUNGSI UTAMA: Memberi label sentimen pada data yang sudah di-preprocessing
 * ========================================================================
 * 
 * Kategori Sentimen:
 * - POSITIF: Opini baik tentang pinjol (pujian, kepuasan, rekomendasi)
 * - NEGATIF: Opisi buruk tentang pinjol (keluhan, penipuan, bunga tinggi)
 * - NETRAL: Opisi netral (informasi, fakta, tidak condong)
 * 
 * Metode Labelling:
 * 1. AUTOMATIC: VADER-based sentiment analysis (Hutto & Gilbert, 2014)
 * 2. MANUAL: Human correction interface
 * 3. HYBRID: Auto-label + manual correction
 * 4. LEARNING: Machine learning dari manual corrections
 * 
 * Algoritma Automatic Labelling:
 * - VADER-inspired lexicon dengan valence scores (-2.5 hingga +2.5)
 * - Compound score calculation dengan intensifier handling (1.293x)
 * - Negation handling dengan flip factor (-0.74x)
 * - Confidence normalization (0.1 hingga 0.95)
 * 
 * INPUT: Data preprocessing hasil PreprocessingController
 * OUTPUT: Data berlabel siap untuk training SVM
 * 
 * Reference: Hutto, C.J., & Gilbert, E.E. (2014). VADER: A Parsimonious Rule-based Model 
 * for Sentiment Analysis of Social Media Text. ICWSM 2014.
 * 
 * @package App\Http\Controllers
 * @author Developer  
 * @version 2.0 (VADER-based)
 */
class LabellingController extends Controller
{
    /**
     * Menampilkan halaman utama labelling
     * 
     * Fitur yang ditampilkan:
     * - Upload interface untuk data preprocessing
     * - Preview data yang akan di-label
     * - Hasil labelling (auto + manual)
     * - Pagination untuk dataset besar (100 data/page)
     * - Learned keywords dari previous corrections
     * 
     * @param Request $request HTTP request dengan pagination & session data
     * @return \Illuminate\View\View View 'labelling' dengan data lengkap
     */
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

    /**
     * Menjalankan proses auto-labelling sentimen
     * 
     * ========================================================================
     * ALUR PROSES AUTO-LABELLING (VADER-BASED)
     * ========================================================================
     * 
     * 1. VALIDASI INPUT
     *    - Load data preprocessing dari session
     *    - Detect kolom teks otomatis
     *    - Prepare learning dari previous corrections
     * 
     * 2. VADER-BASED AUTO-LABELLING
     *    - VADER-inspired lexicon sentiment analysis
     *    - Compound score calculation (Hutto & Gilbert, 2014)
     *    - Intensifier handling (boost factor: 1.293)
     *    - Negation handling (flip factor: -0.74)
     *    - Valence-based confidence scoring
     * 
     * 3. LEARNING MECHANISM
     *    - Learn dari manual corrections sebelumnya
     *    - Update keyword weights
     *    - Improve future accuracy
     * 
     * 4. OUTPUT MANAGEMENT
     *    - Store ke temporary file (bukan session)
     *    - Pagination support untuk dataset besar
     *    - Ready untuk manual correction
     * 
     * Algorithm: VADER-based sentiment analysis with Indonesian lexicon
     * Reference: Hutto & Gilbert (2014) - VADER: A Parsimonious Rule-based Model
     * Confidence: 0.1 - 0.95 (normalized from VADER compound score)
     * 
     * @param Request $request HTTP request dengan session data preprocessing
     * @return \Illuminate\Http\RedirectResponse Redirect dengan status labelling selesai
     * @throws \Exception Jika file tidak ditemukan atau error processing
     */
    public function run(Request $request)
    {
        try {
            $path = $request->session()->get('label_csv_path');
            if (!$path || !Storage::exists($path)) {
                return redirect()->route('labelling.index')->with('error', 'Tidak ada file yang diupload.');
            }

            $fullPath = Storage::path($path);
            if (!file_exists($fullPath)) {
                return redirect()->route('labelling.index')->with('error', 'File CSV tidak ditemukan.');
            }

            [$header, $rows] = $this->readCsv($fullPath, null);
            if (empty($header) || empty($rows)) {
                return redirect()->route('labelling.index')->with('error', 'File CSV kosong atau tidak valid.');
            }

            // Drop noisy columns that shouldn't be processed as text
            [$header, $rows] = $this->filterColumns($header, $rows, ['score', 'time', 'at']);

            // Determine ulasan column
            $ulasanIndex = $this->detectUlasanColumnIndex($header);
            if ($ulasanIndex === null) {
                return redirect()->route('labelling.index')->with('error', 'Tidak dapat menemukan kolom teks/ulasan. Pastikan file CSV memiliki kolom teks.');
            }

            $labeledRows = [];
            foreach ($rows as $index => $row) {
                $ulasan = $ulasanIndex !== null && isset($row[$ulasanIndex]) ? (string) $row[$ulasanIndex] : '';
                
                // Skip empty rows
                if (empty(trim($ulasan))) {
                    continue;
                }
                
                // Auto-labeling based on keywords
                $sentiment = $this->autoLabelSentiment($request, $ulasan);
                $vaderScore = $this->calculateConfidence($ulasan, $sentiment, $request);

                // Debug: Log VADER score untuk checking
                \Log::info("Text: {$ulasan} | Sentiment: {$sentiment} | VADER Score: {$vaderScore}");

                $labeledRows[] = [
                    'raw' => $ulasan,
                    'sentiment' => $sentiment,
                    'confidence' => $vaderScore, // Ini sekarang VADER score
                ];
            }

            if (empty($labeledRows)) {
                return redirect()->route('labelling.index')->with('error', 'Tidak ada data yang dapat diproses. Pastikan file CSV memiliki data teks yang valid.');
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
            $tempDir = storage_path('app/temp');
            if (!file_exists($tempDir)) {
                if (!mkdir($tempDir, 0755, true)) {
                    return redirect()->route('labelling.index')->with('error', 'Tidak dapat membuat direktori temporary.');
                }
            }
            
            // Save all data to temporary file
            $jsonData = json_encode([
                'header' => $header,
                'rows' => $labeledRows,
                'total_count' => count($labeledRows),
            ]);
            
            if (file_put_contents($tempPath, $jsonData) === false) {
                return redirect()->route('labelling.index')->with('error', 'Tidak dapat menyimpan data ke file temporary.');
            }
            
            // Store only file reference and first page data in session
            $request->session()->put('label_temp_file', $tempFile);
            $request->session()->put('label_total_data', count($labeledRows));
            
            // Calculate sentiment distribution
            $sentimentDistribution = $this->calculateSentimentDistribution($labeledRows);
            $request->session()->put('sentiment_distribution', $sentimentDistribution);

            $request->session()->put('label_labeled', [
                'header' => $header,
                'rows' => array_slice($labeledRows, 0, 100), // Limit to first 100 rows
                'total_count' => count($labeledRows),
            ]);

            return redirect()->route('labelling.index')->with('status', 'Labelling selesai. ' . count($labeledRows) . ' data berhasil diproses. Silakan download data dan upload ke Review Data untuk melihat sebaran sentiment.');

        } catch (\Exception $e) {
            \Log::error('Auto Labelling Error: ' . $e->getMessage());
            return redirect()->route('labelling.index')->with('error', 'Terjadi kesalahan saat proses auto labelling: ' . $e->getMessage());
        }
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

    private function detectUlasanColumnIndex(array $header): ?int
    {
        if (empty($header)) return null;
        $candidates = ['ulasan', 'text', 'content', 'message', 'body', 'review'];
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
        
        // VADER lexicon for Indonesian sentiment analysis
        // Based on Hutto & Gilbert (2014) - VADER: A Parsimonious Rule-based Model
        $vaderLexicon = [
            // Positive words (positive valence)
            'sangat bagus' => 0.25, 'sangat baik' => 0.25, 'sangat puas' => 0.25, 'terbaik' => 0.25,
            'terlalu bagus' => 0.24, 'terlalu baik' => 0.24, 'benar-benar bagus' => 0.24, 'benar-benar baik' => 0.24,
            'perfect' => 0.25, 'sempurna' => 0.25, 'awesome' => 0.25, 'amazing' => 0.25, 'fantastic' => 0.25,
            'brilliant' => 0.25, 'excellent' => 0.25, 'love' => 0.25, 'loved' => 0.25, 'loving' => 0.25,
            'recommended' => 0.25, 'recommend' => 0.25, 'highly recommend' => 0.25,
            'bagus' => 0.20, 'baik' => 0.20, 'mantap' => 0.20, 'keren' => 0.20, 'suka' => 0.20,
            'senang' => 0.20, 'puas' => 0.20, 'great' => 0.20, 'good' => 0.20, 'nice' => 0.20,
            'wonderful' => 0.20, 'memuaskan' => 0.20, 'kepuasan' => 0.20, 'enjoy' => 0.20, 'fun' => 0.20,
            'enak' => 0.20, 'lezat' => 0.20, 'nyaman' => 0.20, 'mudah' => 0.20, 'simple' => 0.20,
            'praktis' => 0.20, 'berhasil' => 0.20, 'sukses' => 0.20, 'menang' => 0.20, 'profit' => 0.20,
            'untung' => 0.20, 'benefit' => 0.20, 'helpful' => 0.20, 'useful' => 0.20, 'effective' => 0.20,
            'efficient' => 0.20, 'fast' => 0.20, 'cepat' => 0.20, 'lancar' => 0.20, 'smooth' => 0.20,
            'aman' => 0.20, 'safe' => 0.20, 'secure' => 0.20, 'amanah' => 0.20, 'trustworthy' => 0.20,
            'ok' => 0.15, 'okay' => 0.15, 'fine' => 0.15, 'alright' => 0.15, 'bisa' => 0.15,
            'boleh' => 0.15, 'mungkin' => 0.15, 'hampir' => 0.15, 'hampir bagus' => 0.15, 'lumayan' => 0.15,
            'cukup' => 0.15, 'decent' => 0.15, 'acceptable' => 0.15, 'satisfied' => 0.15, 'satisfaction' => 0.15,
            
            // Negative words (negative valence)
            'sangat buruk' => -0.25, 'sangat jelek' => -0.25, 'sangat kecewa' => -0.25, 'sangat menyesal' => -0.25,
            'sangat gagal' => -0.25, 'terburuk' => -0.25, 'terlalu buruk' => -0.25, 'terlalu jelek' => -0.25,
            'benar-benar buruk' => -0.25, 'benar-benar jelek' => -0.25, 'terrible' => -0.25, 'awful' => -0.25,
            'horrible' => -0.25, 'disgusting' => -0.25, 'hate' => -0.25, 'hated' => -0.25, 'hating' => -0.25,
            'worst' => -0.25, 'sucks' => -0.25, 'sucked' => -0.25, 'sucking' => -0.25, 'disappointed' => -0.25,
            'disappointing' => -0.25,
            'buruk' => -0.20, 'jelek' => -0.20, 'gagal' => -0.20, 'fail' => -0.20, 'failed' => -0.20,
            'failing' => -0.20, 'error' => -0.20, 'salah' => -0.20, 'wrong' => -0.20, 'rusak' => -0.20,
            'broken' => -0.20, 'menyesal' => -0.20, 'kecewa' => -0.20, 'regret' => -0.20, 'boring' => -0.20,
            'membosankan' => -0.20, 'ribet' => -0.20, 'sulit' => -0.20, 'difficult' => -0.20, 'hard' => -0.20,
            'complicated' => -0.20, 'complex' => -0.20, 'confusing' => -0.20, 'mahal' => -0.20, 'expensive' => -0.20,
            'overpriced' => -0.20, 'rugi' => -0.20, 'loss' => -0.20, 'kerugian' => -0.20, 'waste' => -0.20,
            'lambat' => -0.20, 'slow' => -0.20, 'delay' => -0.20, 'terlambat' => -0.20, 'late' => -0.20,
            'menunggu' => -0.20, 'waiting' => -0.20, 'ganggu' => -0.20, 'disturb' => -0.20, 'disturbing' => -0.20,
            'annoying' => -0.20, 'frustrating' => -0.20, 'frustrated' => -0.20, 'penipuan' => -0.20,
            'fraud' => -0.20, 'scam' => -0.20, 'scamming' => -0.20, 'cheat' => -0.20, 'cheating' => -0.20,
            'fake' => -0.20,
            'tidak bagus' => -0.15, 'tidak baik' => -0.15, 'tidak suka' => -0.15, 'tidak senang' => -0.15,
            'tidak puas' => -0.15, 'gak bagus' => -0.15, 'ga bagus' => -0.15, 'nggak bagus' => -0.15,
            'enggak bagus' => -0.15, 'tdk bagus' => -0.15, 'bukan bagus' => -0.15, 'bukan baik' => -0.15,
            'bukan suka' => -0.15, 'bukan senang' => -0.15, 'bukan puas' => -0.15, 'biasa' => -0.15,
            'mediocre' => -0.15, 'average' => -0.15, 'standar' => -0.15, 'normal' => -0.15, 'so-so' => -0.15, 'meh' => -0.15,
            
            // Neutral words (valence near 0)
            'netral' => 0.0, 'neutral' => 0.0, 'biasa' => 0.0, 'normal' => 0.0, 'standar' => 0.0,
            'average' => 0.0, 'mediocre' => 0.0, 'tidak tahu' => 0.0, 'gak tahu' => 0.0,
            'mungkin' => 0.0, 'perhaps' => 0.0, 'maybe' => 0.0, 'bisa jadi' => 0.0, 'kemungkinan' => 0.0,
            'probable' => 0.0
        ];
        
        // Merge dengan learned keywords
        if (isset($learnedKeywords['positive'])) {
            foreach ($learnedKeywords['positive'] as $keyword) {
                $vaderLexicon[$keyword] = 0.15; // Default positive valence untuk learned words
            }
        }
        
        if (isset($learnedKeywords['negative'])) {
            foreach ($learnedKeywords['negative'] as $keyword) {
                $vaderLexicon[$keyword] = -0.15; // Default negative valence untuk learned words
            }
        }
        
        // VADER ORIGINAL: Single compound score calculation
        $compoundScore = 0.0;
        
        // Calculate compound score dengan menjumlahkan semua valence
        foreach ($vaderLexicon as $keyword => $valence) {
            if (str_contains($text, $keyword)) {
                $compoundScore += $valence;
            }
        }
        
        // Handle intensifiers (VADER rule: intensifier boosts valence)
        $intensifiers = ['sangat', 'banget', 'sekali', 'terlalu', 'benar-benar', 'really', 'very', 'so', 'extremely', 'highly'];
        foreach ($intensifiers as $intensifier) {
            if (str_contains($text, $intensifier)) {
                $compoundScore *= 1.293; // VADER intensifier boost
                break;
            }
        }
        
        // Handle negation (VADER rule: negation flips valence)
        $negationWords = ['tidak', 'gak', 'ga', 'nggak', 'enggak', 'tdk', 'gk', 'tak', 'tk', 'bukan', 'bkn', 'no', 'not', 'never', 'neither', 'nor'];
        foreach ($negationWords as $negation) {
            if (str_contains($text, $negation)) {
                $compoundScore *= -0.74; // VADER negation factor
                break;
            }
        }
        
        // VADER ORIGINAL: Standard threshold logic
        if ($compoundScore >= 0.05) {
            return 'positif';
        } elseif ($compoundScore <= -0.05) {
            return 'negatif';
        } else {
            return 'netral';
        }
    }

    private function calculateConfidence(string $text, string $sentiment, Request $request): float
    {
        $text = mb_strtolower($text, 'UTF-8');
        
        // Get learned keywords untuk consistency dengan autoLabelSentiment
        $learnedKeywords = $this->getLearnedKeywords($request);
        
        // VADER lexicon (sama persis dengan autoLabelSentiment)
        $vaderLexicon = [
            // Positive words (positive valence)
            'sangat bagus' => 0.25, 'sangat baik' => 0.25, 'sangat puas' => 0.25, 'terbaik' => 0.25,
            'terlalu bagus' => 0.24, 'terlalu baik' => 0.24, 'benar-benar bagus' => 0.24, 'benar-benar baik' => 0.24,
            'perfect' => 0.25, 'sempurna' => 0.25, 'awesome' => 0.25, 'amazing' => 0.25, 'fantastic' => 0.25,
            'brilliant' => 0.25, 'excellent' => 0.25, 'love' => 0.25, 'loved' => 0.25, 'loving' => 0.25,
            'recommended' => 0.25, 'recommend' => 0.25, 'highly recommend' => 0.25,
            'bagus' => 0.20, 'baik' => 0.20, 'mantap' => 0.20, 'keren' => 0.20, 'suka' => 0.20,
            'senang' => 0.20, 'puas' => 0.20, 'great' => 0.20, 'good' => 0.20, 'nice' => 0.20,
            'wonderful' => 0.20, 'memuaskan' => 0.20, 'kepuasan' => 0.20, 'enjoy' => 0.20, 'fun' => 0.20,
            'enak' => 0.20, 'lezat' => 0.20, 'nyaman' => 0.20, 'mudah' => 0.20, 'simple' => 0.20,
            'praktis' => 0.20, 'berhasil' => 0.20, 'sukses' => 0.20, 'menang' => 0.20, 'profit' => 0.20,
            'untung' => 0.20, 'benefit' => 0.20, 'helpful' => 0.20, 'useful' => 0.20, 'effective' => 0.20,
            'efficient' => 0.20, 'fast' => 0.20, 'cepat' => 0.20, 'lancar' => 0.20, 'smooth' => 0.20,
            'aman' => 0.20, 'safe' => 0.20, 'secure' => 0.20, 'amanah' => 0.20, 'trustworthy' => 0.20,
            'ok' => 0.15, 'okay' => 0.15, 'fine' => 0.15, 'alright' => 0.15, 'bisa' => 0.15,
            'boleh' => 0.15, 'mungkin' => 0.15, 'hampir' => 0.15, 'hampir bagus' => 0.15, 'lumayan' => 0.15,
            'cukup' => 0.15, 'decent' => 0.15, 'acceptable' => 0.15, 'satisfied' => 0.15, 'satisfaction' => 0.15,
            
            // Negative words (negative valence)
            'sangat buruk' => -0.25, 'sangat jelek' => -0.25, 'sangat kecewa' => -0.25, 'sangat menyesal' => -0.25,
            'sangat gagal' => -0.25, 'terburuk' => -0.25, 'terlalu buruk' => -0.25, 'terlalu jelek' => -0.25,
            'benar-benar buruk' => -0.25, 'benar-benar jelek' => -0.25, 'terrible' => -0.25, 'awful' => -0.25,
            'horrible' => -0.25, 'disgusting' => -0.25, 'hate' => -0.25, 'hated' => -0.25, 'hating' => -0.25,
            'worst' => -0.25, 'sucks' => -0.25, 'sucked' => -0.25, 'sucking' => -0.25, 'disappointed' => -0.25,
            'disappointing' => -0.25,
            'buruk' => -0.20, 'jelek' => -0.20, 'gagal' => -0.20, 'fail' => -0.20, 'failed' => -0.20,
            'failing' => -0.20, 'error' => -0.20, 'salah' => -0.20, 'wrong' => -0.20, 'rusak' => -0.20,
            'broken' => -0.20, 'menyesal' => -0.20, 'kecewa' => -0.20, 'regret' => -0.20, 'boring' => -0.20,
            'membosankan' => -0.20, 'ribet' => -0.20, 'sulit' => -0.20, 'difficult' => -0.20, 'hard' => -0.20,
            'complicated' => -0.20, 'complex' => -0.20, 'confusing' => -0.20, 'mahal' => -0.20, 'expensive' => -0.20,
            'overpriced' => -0.20, 'rugi' => -0.20, 'loss' => -0.20, 'kerugian' => -0.20, 'waste' => -0.20,
            'lambat' => -0.20, 'slow' => -0.20, 'delay' => -0.20, 'terlambat' => -0.20, 'late' => -0.20,
            'menunggu' => -0.20, 'waiting' => -0.20, 'ganggu' => -0.20, 'disturb' => -0.20, 'disturbing' => -0.20,
            'annoying' => -0.20, 'frustrating' => -0.20, 'frustrated' => -0.20, 'penipuan' => -0.20,
            'fraud' => -0.20, 'scam' => -0.20, 'scamming' => -0.20, 'cheat' => -0.20, 'cheating' => -0.20,
            'fake' => -0.20,
            'tidak bagus' => -0.15, 'tidak baik' => -0.15, 'tidak suka' => -0.15, 'tidak senang' => -0.15,
            'tidak puas' => -0.15, 'gak bagus' => -0.15, 'ga bagus' => -0.15, 'nggak bagus' => -0.15,
            'enggak bagus' => -0.15, 'tdk bagus' => -0.15, 'bukan bagus' => -0.15, 'bukan baik' => -0.15,
            'bukan suka' => -0.15, 'bukan senang' => -0.15, 'bukan puas' => -0.15, 'biasa' => -0.15,
            'mediocre' => -0.15, 'average' => -0.15, 'standar' => -0.15, 'normal' => -0.15, 'so-so' => -0.15, 'meh' => -0.15,
            
            // Neutral words (valence near 0)
            'netral' => 0.0, 'neutral' => 0.0, 'biasa' => 0.0, 'normal' => 0.0, 'standar' => 0.0,
            'average' => 0.0, 'mediocre' => 0.0, 'tidak tahu' => 0.0, 'gak tahu' => 0.0,
            'mungkin' => 0.0, 'perhaps' => 0.0, 'maybe' => 0.0, 'bisa jadi' => 0.0, 'kemungkinan' => 0.0,
            'probable' => 0.0
        ];
        
        // Merge dengan learned keywords
        if (isset($learnedKeywords['positive'])) {
            foreach ($learnedKeywords['positive'] as $keyword) {
                $vaderLexicon[$keyword] = 0.15;
            }
        }
        
        if (isset($learnedKeywords['negative'])) {
            foreach ($learnedKeywords['negative'] as $keyword) {
                $vaderLexicon[$keyword] = -0.15;
            }
        }
        
        // VADER ORIGINAL: Single compound score calculation (sama dengan autoLabelSentiment)
        $compoundScore = 0.0;
        
        // Calculate compound score dengan menjumlahkan semua valence
        foreach ($vaderLexicon as $keyword => $valence) {
            if (str_contains($text, $keyword)) {
                $compoundScore += $valence;
            }
        }
        
        // Handle intensifiers (VADER rule: intensifier boosts valence)
        $intensifiers = ['sangat', 'banget', 'sekali', 'terlalu', 'benar-benar', 'really', 'very', 'so', 'extremely', 'highly'];
        foreach ($intensifiers as $intensifier) {
            if (str_contains($text, $intensifier)) {
                $compoundScore *= 1.293; // VADER intensifier boost
                break;
            }
        }
        
        // Handle negation (VADER rule: negation flips valence)
        $negationWords = ['tidak', 'gak', 'ga', 'nggak', 'enggak', 'tdk', 'gk', 'tak', 'tk', 'bukan', 'bkn', 'no', 'not', 'never', 'neither', 'nor'];
        foreach ($negationWords as $negation) {
            if (str_contains($text, $negation)) {
                $compoundScore *= -0.74; // VADER negation factor
                break;
            }
        }
        
        // VADER ORIGINAL: Return compound score langsung (bukan confidence)
        // Compound score menunjukkan arah dan kekuatan sentimen
        // > 0 = positif, < 0 = negatif, ~0 = netral
        return $compoundScore;
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

    /**
     * Calculate sentiment distribution from labeled data
     * 
     * @param array $labeledRows Array of labeled data
     * @return array Sentiment distribution statistics
     */
    private function calculateSentimentDistribution(array $labeledRows): array
    {
        $distribution = [
            'positif' => 0,
            'negatif' => 0,
            'netral' => 0,
            'total' => count($labeledRows)
        ];

        foreach ($labeledRows as $row) {
            $sentiment = $row['sentiment'] ?? 'netral';
            if (isset($distribution[$sentiment])) {
                $distribution[$sentiment]++;
            }
        }

        // Calculate percentages
        if ($distribution['total'] > 0) {
            $distribution['positif_percentage'] = round(($distribution['positif'] / $distribution['total']) * 100, 1);
            $distribution['negatif_percentage'] = round(($distribution['negatif'] / $distribution['total']) * 100, 1);
            $distribution['netral_percentage'] = round(($distribution['netral'] / $distribution['total']) * 100, 1);
        } else {
            $distribution['positif_percentage'] = 0;
            $distribution['negatif_percentage'] = 0;
            $distribution['netral_percentage'] = 0;
        }

        return $distribution;
    }

    
    /**
     * Independent review data page
     */
    public function independentReview(Request $request)
    {
        $uploadedPath = $request->session()->get('review_csv_path');
        $preview = $request->session()->get('review_preview');
        $sentimentDistribution = $request->session()->get('review_sentiment_distribution', []);

        return view('review', [
            'uploadedPath' => $uploadedPath,
            'preview' => $preview,
            'sentimentDistribution' => $sentimentDistribution,
        ]);
    }

    /**
     * Upload file for independent review
     */
    public function uploadReview(Request $request)
    {
        $request->validate([
            'csv_file' => ['required', 'file', 'mimes:csv,txt'],
        ]);

        $file = $request->file('csv_file');
        $path = $file->storeAs('review', now()->format('Ymd_His') . '_' . $file->getClientOriginalName());

        // Read CSV
        $fullPath = Storage::path($path);
        [$header, $rows] = $this->readCsv($fullPath, null);

        // Validate CSV structure - must have sentiment column
        $sentimentIndex = $this->findColumnIndex($header, ['sentiment', 'label']);
        if ($sentimentIndex === null) {
            return redirect()->route('review.index')->with('error', 'File CSV harus memiliki kolom "sentiment" atau "label".');
        }

        // Calculate sentiment distribution
        $sentimentDistribution = $this->calculateSentimentDistributionFromRows($rows, $sentimentIndex);

        // Store in session
        $request->session()->put('review_csv_path', $path);
        $request->session()->put('review_preview', ['header' => $header, 'rows' => array_slice($rows, 0, 100)]);
        $request->session()->put('review_sentiment_distribution', $sentimentDistribution);
        $request->session()->put('review_total_data', count($rows));

        return redirect()->route('review.index')->with('status', 'File berhasil diupload. Siap direview!');
    }

    /**
     * Find column index by name
     */
    private function findColumnIndex(array $header, array $possibleNames): ?int
    {
        foreach ($possibleNames as $name) {
            $index = array_search(strtolower($name), array_map('strtolower', $header));
            if ($index !== false) {
                return (int) $index;
            }
        }
        return null;
    }

    /**
     * Calculate sentiment distribution from rows
     */
    private function calculateSentimentDistributionFromRows(array $rows, int $sentimentIndex): array
    {
        $distribution = [
            'positif' => 0,
            'negatif' => 0,
            'netral' => 0,
            'total' => count($rows)
        ];

        foreach ($rows as $row) {
            $sentiment = isset($row[$sentimentIndex]) ? strtolower(trim((string) $row[$sentimentIndex])) : 'netral';
            
            // Normalize sentiment values
            if ($sentiment === 'positive' || $sentiment === 'positif') {
                $distribution['positif']++;
            } elseif ($sentiment === 'negative' || $sentiment === 'negatif') {
                $distribution['negatif']++;
            } elseif ($sentiment === 'neutral' || $sentiment === 'netral') {
                $distribution['netral']++;
            } else {
                // Default to netral for unknown values
                $distribution['netral']++;
            }
        }

        // Calculate percentages
        if ($distribution['total'] > 0) {
            $distribution['positif_percentage'] = round(($distribution['positif'] / $distribution['total']) * 100, 1);
            $distribution['negatif_percentage'] = round(($distribution['negatif'] / $distribution['total']) * 100, 1);
            $distribution['netral_percentage'] = round(($distribution['netral'] / $distribution['total']) * 100, 1);
        } else {
            $distribution['positif_percentage'] = 0;
            $distribution['negatif_percentage'] = 0;
            $distribution['netral_percentage'] = 0;
        }

        return $distribution;
    }
}

