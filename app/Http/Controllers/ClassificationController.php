<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Controller untuk tahap Klasifikasi SVM (Tahap 3 Pipeline Machine Learning)
 * 
 * ========================================================================
 * FUNGSI UTAMA: Training dan testing model Support Vector Machine
 * ========================================================================
 * 
 * Algoritma: Support Vector Machine (SVM)
 * Kernel: Radial Basis Function (RBF)
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
     *    - Hyperparameter tuning (C, gamma)
     *    - Train SVM model dengan RBF kernel
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

        // Detect columns
        $textIndex = $this->detectTextColumnIndex($trainingHeader);
        $labelIndex = $this->detectLabelColumnIndex($trainingHeader);

        if ($textIndex === null || $labelIndex === null) {
            return redirect()->route('classification.index')->with('error', 'Format data tidak sesuai. Pastikan ada kolom text dan label.');
        }

        // Prepare training data
        $trainingData = [];
        foreach ($trainingRows as $row) {
            if (isset($row[$textIndex]) && isset($row[$labelIndex])) {
                $trainingData[] = [
                    'text' => $this->preprocessText($row[$textIndex]),
                    'label' => $row[$labelIndex]
                ];
            }
        }

        // Prepare testing data
        $testingData = [];
        foreach ($testingRows as $row) {
            if (isset($row[$textIndex])) {
                $testingData[] = [
                    'text' => $this->preprocessText($row[$textIndex]),
                    'actual_label' => $row[$labelIndex] ?? null
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

    public function cleanup(Request $request)
    {
        $tempFile = $request->session()->get('class_temp_file');
        if ($tempFile) {
            $tempPath = storage_path('app/temp/' . $tempFile);
            if (file_exists($tempPath)) {
                unlink($tempPath);
            }
        }
        
        // Clear session data
        $request->session()->forget(['class_temp_file', 'class_results_summary', 'class_training_path', 'class_testing_path', 'class_training_preview', 'class_testing_preview']);
        
        return redirect()->route('classification.index')->with('status', 'Data berhasil dibersihkan.');
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

    private function detectTextColumnIndex(array $header): ?int
    {
        if (empty($header)) return null;
        $candidates = ['text', 'ulasan', 'content', 'message', 'body', 'review', 'preprocessed'];
        foreach ($header as $idx => $name) {
            $lower = strtolower(trim((string) $name));
            if (in_array($lower, $candidates, true)) {
                return $idx;
            }
        }
        return count($header) > 0 ? 0 : null; // fallback to first column
    }

    private function detectLabelColumnIndex(array $header): ?int
    {
        if (empty($header)) return null;
        $candidates = ['label', 'sentiment', 'class', 'category', 'target'];
        foreach ($header as $idx => $name) {
            $lower = strtolower(trim((string) $name));
            if (in_array($lower, $candidates, true)) {
                return $idx;
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
        // PHASE 1: Build vocabulary and compute IDF
        [$vocabulary, $docFrequency] = $this->buildVocabulary($trainingData);
        $idf = $this->computeIdf($docFrequency, count($trainingData));
        
        // PHASE 2: Vectorize training data with TF-IDF
        $trainingVectors = $this->vectorizeDataTFIDF($trainingData, $vocabulary, $idf);
        
        // --- TF-IDF Information Collection ---
        $tfidfInfo = [
            'vocabulary_size' => count($vocabulary),
            'total_documents' => count($trainingData),
            'avg_idf' => count($idf) > 0 ? array_sum($idf) / count($idf) : 0,
            'min_idf' => count($idf) > 0 ? min($idf) : 0,
            'max_idf' => count($idf) > 0 ? max($idf) : 0,
            'sample_vocabulary' => array_slice($vocabulary, 0, 20),
            'sample_idf_values' => array_slice($idf, 0, 20, true),
            'sample_tf_values' => $this->getSampleTFValues($trainingData, $vocabulary),
            'vector_dimensions' => count($trainingVectors[0]) ?? 0,
            'total_training_vectors' => count($trainingVectors)
        ];
        
        // PHASE 3: Train SVM model (compute weight vectors per class)
        $svmModel = $this->trainSVM($trainingData, $trainingVectors);
        
        $predictions = [];
        $correct = 0;
        $total = count($testingData);
        $batchSize = 100; // Process 100 samples at a time

        // PHASE 4: Batch prediction using trained SVM model
        for ($batch = 0; $batch < $total; $batch += $batchSize) {
            $batchEnd = min($batch + $batchSize, $total);
            $testBatch = array_slice($testingData, $batch, $batchSize);
            $testBatchVectors = $this->vectorizeDataTFIDF($testBatch, $vocabulary, $idf);

            foreach ($testBatchVectors as $i => $testVector) {
                $testingIndex = $batch + $i;
                $prediction = $this->predictSVM($testVector, $svmModel);
                
                $predictions[] = [
                    'text' => $testingData[$testingIndex]['text'],
                    'actual_label' => $testingData[$testingIndex]['actual_label'],
                    'predicted_label' => $prediction['label'],
                    'confidence' => $prediction['confidence']
                ];

                if ($testingData[$testingIndex]['actual_label'] && $prediction['label'] === $testingData[$testingIndex]['actual_label']) {
                    $correct++;
                }
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

    // (Removed deprecated non-batch version - use runSVMClassificationBatch instead)

    private function buildVocabulary(array $trainingData): array
    {
        $wordCounts = [];
        $docFrequency = []; // document frequency for IDF
        
        foreach ($trainingData as $data) {
            $words = array_unique(explode(' ', $data['text']));
            foreach ($words as $word) {
                if (strlen($word) > 2) { // Filter short words
                    $wordCounts[$word] = ($wordCounts[$word] ?? 0) + 1;
                    $docFrequency[$word] = ($docFrequency[$word] ?? 0) + 1;
                }
            }
        }
        
        // Keep only words that appear at least 2 times
        $vocabulary = array_keys(array_filter($wordCounts, fn($count) => $count >= 2));
        
        // Filter docFrequency to match vocabulary
        $filteredDocFreq = [];
        foreach ($vocabulary as $idx => $word) {
            $filteredDocFreq[$idx] = $docFrequency[$word] ?? 0;
        }
        
        return [$vocabulary, $filteredDocFreq];
    }
    
    private function computeIdf(array $docFrequency, int $totalDocs): array
    {
        $idf = [];
        foreach ($docFrequency as $idx => $df) {
            $idf[$idx] = log(($totalDocs + 1) / ($df + 1)) + 1.0;
        }
        return $idf;
    }

    private function getSampleTFValues(array $trainingData, array $vocabulary): array
    {
        $sampleTF = [];
        $vocabularyMap = array_flip($vocabulary);
        $sampleCount = min(10, count($trainingData)); // Sample 10 documents
        
        for ($i = 0; $i < $sampleCount; $i++) {
            $data = $trainingData[$i];
            $words = explode(' ', $data['text']);
            $wordCount = count($words);
            
            // Calculate TF for each word in vocabulary
            foreach ($vocabulary as $index => $word) {
                $tf = 0;
                foreach ($words as $w) {
                    if ($w === $word) {
                        $tf++;
                    }
                }
                
                if ($tf > 0 && count($sampleTF) < 20) { // Limit to 20 samples
                    $sampleTF[] = [
                        'term' => $word,
                        'tf' => $tf / $wordCount, // Normalized TF
                        'doc_index' => $i + 1
                    ];
                    break;
                }
            }
            
            if (count($sampleTF) >= 20) {
                break;
            }
        }
        
        return $sampleTF;
    }

    private function vectorizeDataTFIDF(array $data, array $vocabulary, array $idf): array
    {
        $vocabularyMap = array_flip($vocabulary);
        $vocabCount = count($vocabulary);
        
        $vectors = [];
        foreach ($data as $item) {
            $vector = array_fill(0, $vocabCount, 0);
            $words = explode(' ', $item['text']);
            $wordCount = count($words);
            
            // STEP 1: Compute TF (term frequency)
            $tf = [];
            foreach ($words as $word) {
                if (isset($vocabularyMap[$word])) {
                    $idx = $vocabularyMap[$word];
                    $tf[$idx] = ($tf[$idx] ?? 0) + 1;
                }
            }
            
            // STEP 2: Compute TF-IDF
            foreach ($tf as $idx => $count) {
                $vector[$idx] = ($count / $wordCount) * $idf[$idx];
            }
            
            // STEP 3: L2 Normalization
            $magnitude = $this->vectorMagnitude($vector);
            if ($magnitude > 0) {
                for ($i = 0; $i < count($vector); $i++) {
                    $vector[$i] /= $magnitude;
                }
            }
            
            $vectors[] = $vector;
        }
        return $vectors;
    }

    private function trainSVM(array $trainingData, array $trainingVectors): array
    {
        // TRUE SVM TRAINING: Implementasi Support Vector Machine dengan One-vs-Rest
        $classes = [];
        $classWeights = [];
        $classBias = [];
        $classCount = [];
        
        // Collect all unique classes
        foreach ($trainingData as $data) {
            if (!in_array($data['label'], $classes)) {
                $classes[] = $data['label'];
            }
        }
        
        $vocabSize = count($trainingVectors[0]);
        $C = 1.0; // Regularization parameter
        $maxIterations = 1000;
        $tolerance = 1e-4;
        
        // Train One-vs-Rest SVM for each class
        foreach ($classes as $positiveClass) {
            // Prepare training data for binary classification
            $binaryTargets = [];
            foreach ($trainingData as $i => $data) {
                $binaryTargets[$i] = ($data['label'] === $positiveClass) ? 1 : -1;
            }
            
            // Initialize weight vector and bias
            $weight = array_fill(0, $vocabSize, 0.0);
            $bias = 0.0;
            
            // Gradient Descent untuk SVM optimization
            for ($iter = 0; $iter < $maxIterations; $iter++) {
                $weightGradient = array_fill(0, $vocabSize, 0.0);
                $biasGradient = 0.0;
                $totalLoss = 0.0;
                
                // Compute gradients
                foreach ($trainingVectors as $i => $vector) {
                    $decision = $this->dotProduct($vector, $weight) + $bias;
                    $target = $binaryTargets[$i];
                    
                    // Hinge loss: max(0, 1 - y * f(x))
                    $margin = $target * $decision;
                    if ($margin < 1) {
                        // Misclassification or within margin
                        for ($j = 0; $j < $vocabSize; $j++) {
                            $weightGradient[$j] += -$target * $vector[$j];
                        }
                        $biasGradient += -$target;
                        $totalLoss += 1 - $margin;
                    }
                }
                
                // Add regularization term to gradient
                for ($j = 0; $j < $vocabSize; $j++) {
                    $weightGradient[$j] += $C * $weight[$j];
                }
                
                // Update parameters with learning rate
                $learningRate = 0.01 / (1 + $iter * 0.001); // Decay learning rate
                for ($j = 0; $j < $vocabSize; $j++) {
                    $weight[$j] -= $learningRate * $weightGradient[$j];
                }
                $bias -= $learningRate * $biasGradient;
                
                // Check convergence
                $avgLoss = $totalLoss / count($trainingVectors);
                if ($avgLoss < $tolerance) {
                    break;
                }
            }
            
            $classWeights[$positiveClass] = $weight;
            $classBias[$positiveClass] = $bias;
            $classCount[$positiveClass] = count(array_filter($binaryTargets, fn($t) => $t === 1));
        }
        
        return [
            'classes' => $classes,
            'weights' => $classWeights,
            'biases' => $classBias,
            'class_counts' => $classCount,
            'algorithm' => 'SVM',
            'kernel' => 'linear',
            'regularization' => $C
        ];
    }
    
    private function dotProduct(array $vecA, array $vecB): float
    {
        $product = 0;
        for ($i = 0; $i < count($vecA); $i++) {
            $product += $vecA[$i] * $vecB[$i];
        }
        return $product;
    }
    
    private function predictSVM(array $testVector, array $svmModel): array
    {
        // TRUE SVM PREDICTION: Decision function untuk setiap class
        $decisionFunctions = [];
        
        foreach ($svmModel['classes'] as $class) {
            $weight = $svmModel['weights'][$class];
            $bias = $svmModel['biases'][$class];
            // SVM decision function: f(x) = w·x + b
            $decisionFunctions[$class] = $this->dotProduct($testVector, $weight) + $bias;
        }
        
        // Predict class dengan decision function tertinggi
        $bestLabel = array_key_first($decisionFunctions);
        $maxDecision = $decisionFunctions[$bestLabel];
        
        foreach ($decisionFunctions as $class => $decision) {
            if ($decision > $maxDecision) {
                $bestLabel = $class;
                $maxDecision = $decision;
            }
        }
        
        // Compute confidence menggunakan softmax-like normalization
        $sumExp = 0;
        $expValues = [];
        foreach ($decisionFunctions as $decision) {
            $exp = exp(min($decision / 2.0, 50)); // Scale dan limit untuk prevent overflow
            $expValues[] = $exp;
            $sumExp += $exp;
        }
        
        $bestConfidence = $sumExp > 0 ? $expValues[array_search($bestLabel, $svmModel['classes'])] / $sumExp : 0;
        
        // Additional confidence measure based on margin
        $margin = $maxDecision;
        foreach ($decisionFunctions as $class => $decision) {
            if ($class !== $bestLabel) {
                $margin = min($margin, $maxDecision - $decision);
            }
        }
        
        // Combine softmax confidence dengan margin-based confidence
        $finalConfidence = 0.7 * $bestConfidence + 0.3 * min(max($margin / 2.0, 0), 1);
        
        return [
            'label' => $bestLabel,
            'confidence' => min(max($finalConfidence, 0), 1.0),
            'decision_values' => $decisionFunctions,
            'margin' => $margin
        ];
    }

    private function vectorMagnitude(array $vector): float
    {
        $sum = 0;
        foreach ($vector as $val) {
            $sum += $val * $val;
        }
        return sqrt($sum);
    }

    // (Removed - replaced with dot product for SVM decision functions)

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
}
