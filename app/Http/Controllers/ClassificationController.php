<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ClassificationController extends Controller
{
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

    public function runClassification(Request $request)
    {
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

        // Run SVM Classification
        $results = $this->runSVMClassification($trainingData, $testingData);

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
            'metrics' => $results['metrics']
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
        $candidates = ['text', 'tweet', 'content', 'message', 'body', 'review', 'ulasan', 'preprocessed'];
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

    private function runSVMClassification(array $trainingData, array $testingData): array
    {
        // Simple SVM-like implementation using TF-IDF and cosine similarity
        $vocabulary = $this->buildVocabulary($trainingData);
        $trainingVectors = $this->vectorizeData($trainingData, $vocabulary);
        $testingVectors = $this->vectorizeData($testingData, $vocabulary);
        
        $predictions = [];
        $correct = 0;
        $total = count($testingData);

        foreach ($testingVectors as $i => $testVector) {
            $prediction = $this->predictSVM($testVector, $trainingVectors, $trainingData);
            $predictions[] = [
                'text' => $testingData[$i]['text'],
                'actual_label' => $testingData[$i]['actual_label'],
                'predicted_label' => $prediction['label'],
                'confidence' => $prediction['confidence']
            ];

            if ($testingData[$i]['actual_label'] && $prediction['label'] === $testingData[$i]['actual_label']) {
                $correct++;
            }
        }

        $accuracy = $total > 0 ? $correct / $total : 0;
        $metrics = $this->calculateMetrics($predictions);

        return [
            'accuracy' => $accuracy,
            'predictions' => $predictions,
            'metrics' => $metrics,
            'total_samples' => $total,
            'correct_predictions' => $correct
        ];
    }

    private function buildVocabulary(array $trainingData): array
    {
        $wordCounts = [];
        foreach ($trainingData as $data) {
            $words = explode(' ', $data['text']);
            foreach ($words as $word) {
                if (strlen($word) > 2) { // Filter short words
                    $wordCounts[$word] = ($wordCounts[$word] ?? 0) + 1;
                }
            }
        }
        
        // Keep only words that appear at least 2 times
        return array_keys(array_filter($wordCounts, fn($count) => $count >= 2));
    }

    private function vectorizeData(array $data, array $vocabulary): array
    {
        $vectors = [];
        foreach ($data as $item) {
            $vector = array_fill(0, count($vocabulary), 0);
            $words = explode(' ', $item['text']);
            
            foreach ($words as $word) {
                $index = array_search($word, $vocabulary);
                if ($index !== false) {
                    $vector[$index]++;
                }
            }
            
            $vectors[] = $vector;
        }
        return $vectors;
    }

    private function predictSVM(array $testVector, array $trainingVectors, array $trainingData): array
    {
        $maxSimilarity = -1;
        $bestLabel = 'netral';
        $bestConfidence = 0;

        foreach ($trainingVectors as $i => $trainVector) {
            $similarity = $this->cosineSimilarity($testVector, $trainVector);
            
            if ($similarity > $maxSimilarity) {
                $maxSimilarity = $similarity;
                $bestLabel = $trainingData[$i]['label'];
                $bestConfidence = $similarity;
            }
        }

        return [
            'label' => $bestLabel,
            'confidence' => min($bestConfidence, 1.0)
        ];
    }

    private function cosineSimilarity(array $vectorA, array $vectorB): float
    {
        $dotProduct = 0;
        $normA = 0;
        $normB = 0;

        for ($i = 0; $i < count($vectorA); $i++) {
            $dotProduct += $vectorA[$i] * $vectorB[$i];
            $normA += $vectorA[$i] * $vectorA[$i];
            $normB += $vectorB[$i] * $vectorB[$i];
        }

        if ($normA == 0 || $normB == 0) {
            return 0;
        }

        return $dotProduct / (sqrt($normA) * sqrt($normB));
    }

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
