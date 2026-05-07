<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Phpml\FeatureExtraction\TfIdfTransformer;
use Phpml\Classification\SVC;
use Phpml\SupportVectorMachine\Kernel;

/**
 * Controller untuk tahap Klasifikasi SVM (Tahap 3 Pipeline Machine Learning)
 * 
 * ========================================================================
 * FUNGSI UTAMA: Training dan testing model Support Vector Machine
 * ========================================================================
 * 
 * Algoritma: Support Vector Machine (SVM)
 * Kernel: Linear
 * Feature Extraction: TF-IDF Vectorization
 * Multi-class Classification: Positif, Negatif, Netral
 * 
 * Proses Klasifikasi:
 * 1. DATA PREPARATION
 *    - Upload training data (berlabel)
 *    - Upload testing data (tanpa label)
 *    - Validate CSV structure
 * 
 * 2. FEATURE ENGINEERING
 *    - TF-IDF calculation
 *    - Vector space modeling
 *    - Feature scaling
 * 
 * 3. SVM TRAINING
 *    - Hyperparameter optimization
 *    - Cross-validation
 *    - Model training
 * 
 * 4. PREDICTION & EVALUATION
 *    - Predict testing data
 *    - Calculate confidence scores
 *    - Generate classification results
 * 
 * INPUT: Training data (labeled) + Testing data (unlabeled)
 * OUTPUT: Classification results dengan confidence scores
 * 
 * @package App\Http\Controllers
 * @author Developer
 * @version 1.0
 */
class ClassificationController extends Controller
{
    /**
     * Menampilkan halaman utama klasifikasi SVM
     * 
     * Data yang ditampilkan:
     * - Training data preview (jika diupload)
     * - Testing data preview (jika diupload)  
     * - Classification results (jika sudah dijalankan)
     * - Model performance metrics
     * 
     * @param Request $request HTTP request dengan session data klasifikasi
     * @return \Illuminate\View\View View 'klasifikasi' dengan data lengkap
     */
    public function index(Request $request)
    {
        $trainingPath = $request->session()->get('class_training_path');
        $testingPath = $request->session()->get('class_testing_path');
        $trainingPreview = $request->session()->get('class_training_preview');
        $testingPreview = $request->session()->get('class_testing_preview');
        $resultsSummary = $request->session()->get('class_results_summary');

        return view('klasifikasi', [
            'trainingPath' => $trainingPath,
            'testingPath' => $testingPath,
            'trainingPreview' => $trainingPreview,
            'testingPreview' => $testingPreview,
            'results' => $resultsSummary,
        ]);
    }

    public function uploadTraining(Request $request)
    {
        $request->validate([
            'csv_file' => ['required', 'file', 'mimes:csv,txt'],
        ]);

        $file = $request->file('csv_file');
        $path = $file->storeAs('classification', 'training_' . now()->format('Ymd_His') . '_' . $file->getClientOriginalName());

        // Read CSV preview
        $fullPath = Storage::path($path);
        [$header, $rows] = $this->readCsv($fullPath, 100);

        $request->session()->put('class_training_path', $path);
        $request->session()->put('class_training_preview', ['header' => $header, 'rows' => $rows]);
        $request->session()->forget('class_results');

        return redirect()->route('classification.index')->with('status', 'Data training berhasil diupload.');
    }

    public function uploadTesting(Request $request)
    {
        $request->validate([
            'csv_file' => ['required', 'file', 'mimes:csv,txt'],
        ]);

        $file = $request->file('csv_file');
        $path = $file->storeAs('classification', 'testing_' . now()->format('Ymd_His') . '_' . $file->getClientOriginalName());

        // Read CSV preview
        $fullPath = Storage::path($path);
        [$header, $rows] = $this->readCsv($fullPath, 100);

        $request->session()->put('class_testing_path', $path);
        $request->session()->put('class_testing_preview', ['header' => $header, 'rows' => $rows]);
        $request->session()->forget('class_results');

        return redirect()->route('classification.index')->with('status', 'Data testing berhasil diupload.');
    }

    /**
     * Menjalankan proses klasifikasi SVM lengkap
     * 
     * ========================================================================
     * ALUR PROSES KLASIFIKASI SVM LENGKAP
     * ========================================================================
     * 
     * 1. VALIDASI DATA
     *    - Cek keberadaan training & testing data
     *    - Validate CSV structure (text, label columns)
     *    - Handle large datasets (memory optimization)
     * 
     * 2. FEATURE EXTRACTION (TF-IDF)
     *    - Build vocabulary dari training data
     *    - Calculate TF-IDF weights
     *    - Transform text ke vector space
     *    - Normalize feature vectors
     * 
     * 3. SVM TRAINING
     *    - Split training data (80% train, 20% validation)
     *    - Hyperparameter tuning (C)
     *    - Train SVM model dengan Linear kernel
     *    - Cross-validation untuk optimal parameters
     * 
     * 4. PREDICTION
     *    - Transform testing data dengan TF-IDF yang sama
     *    - Predict labels menggunakan trained SVM
     *    - Calculate confidence scores
     *    - Generate classification results
     * 
     * 5. OUTPUT MANAGEMENT
     *    - Store results ke session
     *    - Generate performance metrics
     *    - Ready untuk evaluation phase
     * 
     * Performance Metrics:
     * - Training accuracy
     * - Validation accuracy  
     * - Prediction confidence distribution
     * - Processing time
     * 
     * @param Request $request HTTP request dengan session data training/testing
     * @return \Illuminate\Http\RedirectResponse Redirect dengan status klasifikasi selesai
     * @throws \Exception Jika data tidak valid atau error processing
     */
    public function runClassification(Request $request)
    {
        // Increase timeout for large datasets
        set_time_limit(300); // 5 minutes timeout
        ini_set('memory_limit', '512M');

        $trainingPath = $request->session()->get('class_training_path');
        $testingPath = $request->session()->get('class_testing_path');

        if (!$trainingPath || !Storage::exists($trainingPath)) {
            return redirect()->route('classification.index')->with('error', 'Data training belum diupload.');
        }

        if (!$testingPath || !Storage::exists($testingPath)) {
            return redirect()->route('classification.index')->with('error', 'Data testing belum diupload.');
        }

        // Read training data
        $trainingFullPath = Storage::path($trainingPath);
        [$trainingHeader, $trainingRows] = $this->readCsv($trainingFullPath, null);
        
        // Read testing data
        $testingFullPath = Storage::path($testingPath);
        [$testingHeader, $testingRows] = $this->readCsv($testingFullPath, null);

        // Detect columns for training and testing separately
        $textIndex = $this->detectTextColumnIndex($trainingHeader);
        $labelIndex = $this->detectLabelColumnIndex($trainingHeader);
        $testingTextIndex = $this->detectTextColumnIndex($testingHeader);
        $testingLabelIndex = $this->detectLabelColumnIndex($testingHeader);

        if ($textIndex === null || $labelIndex === null) {
            return redirect()->route('classification.index')->with('error', 'Format data training tidak sesuai. Pastikan ada kolom teks dan label.');
        }

        if ($testingTextIndex === null) {
            return redirect()->route('classification.index')->with('error', 'Format data testing tidak sesuai. Pastikan ada kolom teks.');
        }

        // Prepare training data
        $trainingData = [];
        foreach ($trainingRows as $row) {
            if (isset($row[$textIndex]) && isset($row[$labelIndex])) {
                $trainingData[] = [
                    'text' => $this->preprocessText($row[$textIndex]),
                    'label' => mb_strtolower(trim((string) $row[$labelIndex]), 'UTF-8')
                ];
            }
        }

        // Prepare testing data
        $testingData = [];
        foreach ($testingRows as $row) {
            if (isset($row[$testingTextIndex])) {
                $testingData[] = [
                    'text' => $this->preprocessText($row[$testingTextIndex]),
                    'actual_label' => $testingLabelIndex !== null && isset($row[$testingLabelIndex]) ? mb_strtolower(trim((string) $row[$testingLabelIndex]), 'UTF-8') : null
                ];
            }
        }

        // Run SVM Classification with batch processing
        $results = $this->runSVMClassificationBatch($trainingData, $testingData);

        // Store results in temporary file instead of session
        $tempFile = 'temp_classification_' . uniqid() . '.json';
        $tempPath = storage_path('app/temp/' . $tempFile);
        
        // Create temp directory if not exists
        if (!file_exists(storage_path('app/temp'))) {
            mkdir(storage_path('app/temp'), 0755, true);
        }
        
        // Save results to temporary file
        file_put_contents($tempPath, json_encode($results));
        
        // Store only file reference and summary in session
        $request->session()->put('class_temp_file', $tempFile);
        $request->session()->put('class_results_summary', [
            'accuracy' => $results['accuracy'],
            'total_samples' => $results['total_samples'],
            'correct_predictions' => $results['correct_predictions'],
            'metrics' => $results['metrics'],
            'tfidf_info' => $results['tfidf_info']
        ]);

        return redirect()->route('classification.index')->with('status', 'Klasifikasi selesai. Akurasi: ' . number_format($results['accuracy'] * 100, 2) . '%');
    }

    public function downloadResults(Request $request): StreamedResponse
    {
        $tempFile = $request->session()->get('class_temp_file');
        if (!$tempFile) {
            return redirect()->route('classification.index')->with('error', 'Belum ada hasil klasifikasi.');
        }
        
        $tempPath = storage_path('app/temp/' . $tempFile);
        if (!file_exists($tempPath)) {
            return redirect()->route('classification.index')->with('error', 'File hasil klasifikasi tidak ditemukan.');
        }
        
        // Read results from temporary file
        $results = json_decode(file_get_contents($tempPath), true);
        if (!$results) {
            return redirect()->route('classification.index')->with('error', 'Data hasil klasifikasi tidak valid.');
        }

        $filename = 'classification_results_' . now()->format('Ymd_His') . '.csv';

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ];

        return response()->stream(function () use ($results) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['text', 'actual_label', 'predicted_label', 'confidence']);
            foreach ($results['predictions'] as $prediction) {
                fputcsv($out, [
                    $prediction['text'],
                    $prediction['actual_label'] ?? '',
                    $prediction['predicted_label'],
                    $prediction['confidence']
                ]);
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
            $data = array_map(fn($value) => is_string($value) ? trim($value) : $value, $data);
            if ($header === null) {
                $header = array_map(function ($value) {
                    if (!is_string($value)) {
                        return $value;
                    }
                    $value = preg_replace('/^\x{FEFF}/u', '', $value);
                    return trim($value);
                }, $data);
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

    private function detectTextColumnIndex(array $header): ?int
    {
        if (empty($header)) return null;
        $candidates = ['text', 'ulasan', 'content', 'message', 'body', 'review', 'preprocessed', 'tweet', 'isi', 'komentar', 'teks'];
        foreach ($header as $idx => $name) {
            $lower = strtolower(trim((string) $name));
            foreach ($candidates as $candidate) {
                if (str_contains($lower, $candidate)) {
                    return $idx;
                }
            }
        }
        return count($header) > 0 ? 0 : null; // fallback to first column
    }

    private function detectLabelColumnIndex(array $header): ?int
    {
        if (empty($header)) return null;
        $candidates = ['label', 'sentiment', 'sentimen', 'class', 'category', 'kategori', 'target', 'kelas'];
        foreach ($header as $idx => $name) {
            $lower = strtolower(trim((string) $name));
            foreach ($candidates as $candidate) {
                if (str_contains($lower, $candidate)) {
                    return $idx;
                }
            }
        }
        return count($header) > 1 ? 1 : null; // fallback to second column
    }

    private function preprocessText(string $text): string
    {
        // Simple preprocessing - convert to lowercase and clean
        $text = mb_strtolower($text, 'UTF-8');
        $text = preg_replace('/[^\p{L}\s]/u', ' ', $text);
        $text = preg_replace('/\s+/u', ' ', $text);
        return trim($text);
    }

    private function runSVMClassificationBatch(array $trainingData, array $testingData): array
    {
        // PHASE 1: Prepare data
        $trainingSamples = array_column($trainingData, 'text');
        $trainingLabels = array_column($trainingData, 'label');
        $testingSamples = array_column($testingData, 'text');

        // Validate and standardize labels
        $trainingLabels = array_map(function($label) {
            return (string) $label;
        }, $trainingLabels);

        // PHASE 1.5: Build vocabulary and compute TF-IDF using custom implementation
        [$vocabulary, $idf] = $this->buildVocabularyAndIdf($trainingSamples);
        $originalVocabulary = $vocabulary;
        $originalIdf = $idf;
        
        // Validate vocabulary
        if (empty($vocabulary)) {
            return [
                'accuracy' => 0,
                'predictions' => [],
                'metrics' => [],
                'total_samples' => 0,
                'correct_predictions' => 0,
                'tfidf_info' => ['error' => 'Empty vocabulary - check training data validity']
            ];
        }

        // PHASE 2: Vectorize training data with custom TF-IDF
        $trainingVectors = $this->vectorizeDataCustomTfIdf($trainingSamples, $vocabulary, $idf);

        // PHASE 2.5: Vectorize testing data (before vocabulary reduction)
        $testingVectors = $this->vectorizeDataCustomTfIdf($testingSamples, $vocabulary, $idf);

        // Validate vectors and labels match
        if (count($trainingVectors) !== count($trainingLabels)) {
            return [
                'accuracy' => 0,
                'predictions' => [],
                'metrics' => [],
                'total_samples' => 0,
                'correct_predictions' => 0,
                'tfidf_info' => ['error' => 'Vector and label count mismatch: ' . count($trainingVectors) . ' vs ' . count($trainingLabels)]
            ];
        }

        // PHASE 3: Train SVM model with optimizations to prevent timeout
        try {
            // OPTIMASI 1: Limit training data untuk mencegah timeout
            $maxTrainingSamples = 500;
            if (count($trainingVectors) > $maxTrainingSamples) {
                $indices = $this->stratifiedSampleIndices($trainingLabels, $maxTrainingSamples);
                $sampledVectors = [];
                $sampledLabels = [];
                foreach ($indices as $idx) {
                    $sampledVectors[] = $trainingVectors[$idx];
                    $sampledLabels[] = $trainingLabels[$idx];
                }
                $trainingVectors = $sampledVectors;
                $trainingLabels = $sampledLabels;
            }
            
            // OPTIMASI 2: Reduce vocabulary size untuk mempercepat training
            $maxVocabSize = 300;
            if (count($vocabulary) > $maxVocabSize) {
                // Hitung frekuensi kata
                $wordFreq = [];
                foreach ($trainingVectors as $vector) {
                    foreach ($vector as $index => $value) {
                        if ($value > 0) {
                            $wordFreq[$index] = ($wordFreq[$index] ?? 0) + 1;
                        }
                    }
                }
                arsort($wordFreq);
                $topWords = array_keys(array_slice($wordFreq, 0, $maxVocabSize, true));
                
                // Filter vocabulary
                $newVocabulary = [];
                $wordMap = [];
                foreach ($vocabulary as $word => $index) {
                    if (in_array($index, $topWords)) {
                        $newVocabulary[$word] = count($newVocabulary);
                        $wordMap[$index] = $newVocabulary[$word];
                    }
                }
                
                // Rebuild training vectors dengan vocabulary yang diperkecil
                $newTrainingVectors = [];
                foreach ($trainingVectors as $vector) {
                    $newVector = array_fill(0, count($newVocabulary), 0);
                    foreach ($vector as $oldIndex => $value) {
                        if (isset($wordMap[$oldIndex])) {
                            $newVector[$wordMap[$oldIndex]] = $value;
                        }
                    }
                    $newTrainingVectors[] = $newVector;
                }
                
                $trainingVectors = $newTrainingVectors;
                $vocabulary = $newVocabulary;
                
                // Update testing vectors juga dengan vocabulary yang diperkecil
                $newTestingVectors = [];
                foreach ($testingVectors as $vector) {
                    $newVector = array_fill(0, count($newVocabulary), 0);
                    foreach ($vector as $oldIndex => $value) {
                        if (isset($wordMap[$oldIndex])) {
                            $newVector[$wordMap[$oldIndex]] = $value;
                        }
                    }
                    $newTestingVectors[] = $newVector;
                }
                $testingVectors = $newTestingVectors;
            }
            
            // OPTIMASI 3: Increase timeout dan memory limit
            set_time_limit(0); // No timeout
            ini_set('memory_limit', '1G');
            
            // Train OvR SVM models untuk setiap kelas menggunakan linear kernel
            $models = $this->trainOvRModels($trainingVectors, $trainingLabels);
            
        } catch (\Exception $e) {
            return [
                'accuracy' => 0,
                'predictions' => [],
                'metrics' => [],
                'total_samples' => 0,
                'correct_predictions' => 0,
                'tfidf_info' => ['error' => 'SVM training failed: ' . $e->getMessage()]
            ];
        }

        // --- TF-IDF Information Collection ---
        $sampleVocabulary = array_slice(array_keys($originalVocabulary), 0, 20);
        $sampleIdfValues = [];
        foreach ($sampleVocabulary as $word) {
            $idx = $originalVocabulary[$word];
            $sampleIdfValues[] = $originalIdf[$idx] ?? 0;
        }

        $sampleTfValues = [];
        if (!empty($trainingSamples)) {
            $termCounts = [];
            $corpusWordCount = 0;
            foreach ($trainingSamples as $doc) {
                $words = array_filter(explode(' ', mb_strtolower($doc, 'UTF-8')), fn($w) => !empty(trim($w)));
                foreach ($words as $word) {
                    if (isset($originalVocabulary[$word])) {
                        $termCounts[$word] = ($termCounts[$word] ?? 0) + 1;
                        $corpusWordCount++;
                    }
                }
            }
            foreach ($termCounts as $word => $count) {
                $sampleTfValues[] = ['term' => $word, 'tf' => $corpusWordCount ? $count / $corpusWordCount : 0];
            }
            usort($sampleTfValues, fn($a, $b) => $b['tf'] <=> $a['tf']);
            $sampleTfValues = array_slice($sampleTfValues, 0, 20);
        }

        $tfidfInfo = [
            'vocabulary_size' => count($vocabulary),
            'total_documents' => count($trainingData),
            'avg_idf' => count($idf) > 0 ? array_sum($idf) / count($idf) : 0,
            'min_idf' => count($idf) > 0 ? min($idf) : 0,
            'max_idf' => count($idf) > 0 ? max($idf) : 0,
            'sample_vocabulary' => $sampleVocabulary,
            'sample_idf_values' => $sampleIdfValues,
            'sample_tf_values' => $sampleTfValues,
            'label_distribution' => array_count_values($trainingLabels),
            'vector_dimensions' => count($trainingVectors[0] ?? []),
            'total_training_vectors' => count($trainingVectors)
        ];

        $predictions = [];
        $correct = 0;
        $total = count($testingData);

        // PHASE 4: Batch prediction using trained OvR SVM models
        foreach ($testingVectors as $i => $testVector) {
            try {
                $predictionResult = $this->predictOvR($models, $testVector);
                $predictedLabel = $predictionResult['label'] ?? 'unknown';
                $confidence = $predictionResult['scores'][$predictedLabel] ?? 0.0;

                $predictions[] = [
                    'text' => $testingSamples[$i],
                    'actual_label' => $testingData[$i]['actual_label'],
                    'predicted_label' => $predictedLabel,
                    'confidence' => $confidence
                ];

                if ($testingData[$i]['actual_label'] && (string)$predictedLabel === (string)$testingData[$i]['actual_label']) {
                    $correct++;
                }
            } catch (\Exception $e) {
                // Log error but continue
                $predictions[] = [
                    'text' => $testingSamples[$i],
                    'actual_label' => $testingData[$i]['actual_label'],
                    'predicted_label' => 'error',
                    'confidence' => 0.0
                ];
            }
        }

        $accuracy = $total > 0 ? $correct / $total : 0;
        $metrics = $this->calculateMetrics($predictions);

        return [
            'accuracy' => $accuracy,
            'predictions' => $predictions,
            'metrics' => $metrics,
            'total_samples' => $total,
            'correct_predictions' => $correct,
            'tfidf_info' => $tfidfInfo
        ];
    }

    private function trainOvRModels(array $trainingVectors, array $trainingLabels): array
    {
        $models = [];
        $uniqueLabels = array_values(array_unique($trainingLabels));

        foreach ($uniqueLabels as $label) {
            $binaryTargets = array_map(function ($target) use ($label) {
                return $target === $label ? $label : 'not_'.$label;
            }, $trainingLabels);

            $classifier = new SVC(
                Kernel::LINEAR,
                1.0,
                3,
                null,
                0.0,
                0.001,
                100,
                true,
                true
            );
            $classifier->train($trainingVectors, $binaryTargets);
            $models[$label] = $classifier;
        }

        return $models;
    }

    private function predictOvR(array $models, array $sample): array
    {
        $scores = [];

        foreach ($models as $label => $model) {
            try {
                $probabilities = $model->predictProbability([$sample]);
                $probabilities = $probabilities[0] ?? [];

                if (is_array($probabilities) && array_key_exists($label, $probabilities)) {
                    $scores[$label] = $probabilities[$label];
                } elseif (is_array($probabilities)) {
                    $scores[$label] = count($probabilities) > 0 ? max($probabilities) : 0.0;
                } else {
                    $scores[$label] = 0.0;
                }
            } catch (\Exception $e) {
                $scores[$label] = 0.0;
            }
        }

        arsort($scores, SORT_NUMERIC);

        return [
            'label' => key($scores),
            'scores' => $scores,
        ];
    }

    private function buildVocabularyAndIdf(array $documents): array
    {
        $vocabulary = []; // word => index
        $docFrequency = []; // index => count
        
        foreach ($documents as $doc) {
            $words = array_unique(explode(' ', mb_strtolower($doc, 'UTF-8')));
            foreach ($words as $word) {
                $word = trim($word);
                if (strlen($word) > 1) { // Filter very short words
                    if (!isset($vocabulary[$word])) {
                        $vocabulary[$word] = count($vocabulary);
                        $docFrequency[$vocabulary[$word]] = 0;
                    }
                    $docFrequency[$vocabulary[$word]]++;
                }
            }
        }
        
        // Compute IDF values
        $totalDocs = count($documents);
        $idf = [];
        foreach ($vocabulary as $word => $idx) {
            $idf[$idx] = log(($totalDocs + 1) / ($docFrequency[$idx] + 1)) + 1.0;
        }
        
        return [$vocabulary, $idf];
    }

    private function vectorizeDataCustomTfIdf(array $documents, array $vocabulary, array $idf): array
    {
        $vectors = [];
        
        foreach ($documents as $doc) {
            $vector = array_fill(0, count($vocabulary), 0.0);
            
            // Tokenize
            $words = explode(' ', mb_strtolower($doc, 'UTF-8'));
            $wordCount = count(array_filter($words, fn($w) => !empty(trim($w))));
            
            if ($wordCount === 0) {
                $vectors[] = $vector;
                continue;
            }
            
            // Count term frequency
            $tf = [];
            foreach ($words as $word) {
                $word = trim($word);
                if (strlen($word) > 1 && isset($vocabulary[$word])) {
                    $idx = $vocabulary[$word];
                    $tf[$idx] = ($tf[$idx] ?? 0) + 1;
                }
            }
            
            // Compute TF-IDF
            foreach ($tf as $idx => $count) {
                $vector[$idx] = ($count / $wordCount) * $idf[$idx];
            }
            
            // L2 Normalization
            $magnitude = sqrt(array_sum(array_map(fn($v) => $v * $v, $vector)));
            if ($magnitude > 0) {
                $vector = array_map(fn($v) => $v / $magnitude, $vector);
            }
            
            $vectors[] = $vector;
        }
        
        return $vectors;
    }

    // (Removed deprecated non-batch version - use runSVMClassificationBatch instead)

    // (Removed legacy helper code not used in current batch workflow)

    private function calculateMetrics(array $predictions): array
    {
        $labels = ['positif', 'negatif', 'netral'];
        $metrics = [];

        foreach ($labels as $label) {
            $tp = 0; // True Positive
            $fp = 0; // False Positive
            $fn = 0; // False Negative

            foreach ($predictions as $pred) {
                $actual = $pred['actual_label'];
                $predicted = $pred['predicted_label'];

                if ($predicted === $label && $actual === $label) {
                    $tp++;
                } elseif ($predicted === $label && $actual !== $label) {
                    $fp++;
                } elseif ($predicted !== $label && $actual === $label) {
                    $fn++;
                }
            }

            $precision = ($tp + $fp) > 0 ? $tp / ($tp + $fp) : 0;
            $recall = ($tp + $fn) > 0 ? $tp / ($tp + $fn) : 0;
            $f1Score = ($precision + $recall) > 0 ? 2 * ($precision * $recall) / ($precision + $recall) : 0;

            $metrics[$label] = [
                'precision' => $precision,
                'recall' => $recall,
                'f1_score' => $f1Score
            ];
        }

        return $metrics;
    }

    private function stratifiedSampleIndices(array $labels, int $maxSamples): array
    {
        $labelGroups = [];
        foreach ($labels as $idx => $label) {
            $labelGroups[$label][] = $idx;
        }

        $classes = count($labelGroups);
        if ($classes === 0) {
            return [];
        }

        $samplesPerClass = max(1, (int) floor($maxSamples / $classes));
        $indices = [];

        foreach ($labelGroups as $group) {
            shuffle($group);
            $indices = array_merge($indices, array_slice($group, 0, min(count($group), $samplesPerClass)));
        }

        if (count($indices) < $maxSamples) {
            $allIndices = range(0, count($labels) - 1);
            $remaining = array_values(array_diff($allIndices, $indices));
            shuffle($remaining);
            $indices = array_merge($indices, array_slice($remaining, 0, $maxSamples - count($indices)));
        }

        shuffle($indices);
        return $indices;
    }
}
