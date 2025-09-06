<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class EvaluationController extends Controller
{
    public function index(Request $request)
    {
        $uploadedFiles = $request->session()->get('eval_uploaded_files', []);
        $comparisonResults = $request->session()->get('eval_comparison_results');
        $confusionMatrix = $request->session()->get('eval_confusion_matrix');

        return view('evaluasi', [
            'uploadedFiles' => $uploadedFiles,
            'comparisonResults' => $comparisonResults,
            'confusionMatrix' => $confusionMatrix,
        ]);
    }

    public function uploadResults(Request $request)
    {
        $request->validate([
            'csv_file' => ['required', 'file', 'mimes:csv,txt'],
            'method_name' => ['required', 'string', 'max:50'],
        ]);

        $file = $request->file('csv_file');
        $methodName = $request->input('method_name');
        
        // Read and validate CSV structure
        $path = $file->getRealPath();
        [$header, $rows] = $this->readCsv($path, 100);
        
        // Validate required columns
        if (!$this->validateCsvStructure($header)) {
            return redirect()->route('evaluation.index')->with('error', 'Format CSV tidak valid. Pastikan ada kolom: text, actual_label, predicted_label, confidence');
        }

        // Store file
        $filename = 'eval_' . $methodName . '_' . now()->format('Ymd_His') . '.csv';
        $storedPath = $file->storeAs('evaluation', $filename);

        // Calculate metrics for this method
        $metrics = $this->calculateMethodMetrics($rows, $header);

        // Store in session
        $uploadedFiles = $request->session()->get('eval_uploaded_files', []);
        $uploadedFiles[] = [
            'filename' => $filename,
            'method_name' => $methodName,
            'path' => $storedPath,
            'metrics' => $metrics,
            'sample_count' => count($rows),
            'uploaded_at' => now()->format('Y-m-d H:i:s')
        ];
        
        $request->session()->put('eval_uploaded_files', $uploadedFiles);
        $request->session()->forget(['eval_comparison_results', 'eval_confusion_matrix']);

        return redirect()->route('evaluation.index')->with('status', "Hasil metode '{$methodName}' berhasil diupload.");
    }

    public function compareMethods(Request $request)
    {
        $uploadedFiles = $request->session()->get('eval_uploaded_files', []);
        
        if (count($uploadedFiles) < 2) {
            return redirect()->route('evaluation.index')->with('error', 'Minimal 2 metode untuk perbandingan.');
        }

        $comparisonResults = [];
        $bestMethod = null;
        $bestAccuracy = 0;

        foreach ($uploadedFiles as $file) {
            $fullPath = Storage::path($file['path']);
            [$header, $rows] = $this->readCsv($fullPath, null);
            
            $metrics = $this->calculateMethodMetrics($rows, $header);
            
            $comparisonResults[] = [
                'method_name' => $file['method_name'],
                'sample_count' => count($rows),
                'accuracy' => $metrics['accuracy'],
                'precision' => $metrics['precision'],
                'recall' => $metrics['recall'],
                'f1_score' => $metrics['f1_score'],
                'metrics_per_class' => $metrics['metrics_per_class']
            ];

            if ($metrics['accuracy'] > $bestAccuracy) {
                $bestAccuracy = $metrics['accuracy'];
                $bestMethod = $file['method_name'];
            }
        }

        // Sort by accuracy descending
        usort($comparisonResults, fn($a, $b) => $b['accuracy'] <=> $a['accuracy']);

        $request->session()->put('eval_comparison_results', [
            'results' => $comparisonResults,
            'best_method' => $bestMethod,
            'best_accuracy' => $bestAccuracy,
            'total_methods' => count($comparisonResults)
        ]);

        return redirect()->route('evaluation.index')->with('status', 'Perbandingan metode selesai.');
    }

    public function generateConfusionMatrix(Request $request)
    {
        $methodName = $request->input('method_name');
        $uploadedFiles = $request->session()->get('eval_uploaded_files', []);
        
        $selectedFile = null;
        foreach ($uploadedFiles as $file) {
            if ($file['method_name'] === $methodName) {
                $selectedFile = $file;
                break;
            }
        }

        if (!$selectedFile) {
            return redirect()->route('evaluation.index')->with('error', 'Metode tidak ditemukan.');
        }

        $fullPath = Storage::path($selectedFile['path']);
        [$header, $rows] = $this->readCsv($fullPath, null);
        
        $confusionMatrix = $this->buildConfusionMatrix($rows, $header);
        
        $request->session()->put('eval_confusion_matrix', [
            'method_name' => $methodName,
            'matrix' => $confusionMatrix['matrix'],
            'labels' => $confusionMatrix['labels'],
            'total_samples' => $confusionMatrix['total_samples']
        ]);

        return redirect()->route('evaluation.index')->with('status', "Confusion matrix untuk '{$methodName}' berhasil dibuat.");
    }

    public function downloadReport(Request $request): StreamedResponse
    {
        $comparisonResults = $request->session()->get('eval_comparison_results');
        $confusionMatrix = $request->session()->get('eval_confusion_matrix');
        
        if (!$comparisonResults) {
            return redirect()->route('evaluation.index')->with('error', 'Belum ada hasil perbandingan untuk di-download.');
        }

        $filename = 'evaluation_report_' . now()->format('Ymd_His') . '.csv';

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ];

        return response()->stream(function () use ($comparisonResults, $confusionMatrix) {
            $out = fopen('php://output', 'w');
            
            // Write comparison results
            fputcsv($out, ['EVALUATION REPORT - COMPARISON RESULTS']);
            fputcsv($out, ['Generated at:', now()->format('Y-m-d H:i:s')]);
            fputcsv($out, ['']);
            
            fputcsv($out, ['Method Name', 'Sample Count', 'Accuracy (%)', 'Precision (%)', 'Recall (%)', 'F1-Score (%)']);
            foreach ($comparisonResults['results'] as $result) {
                fputcsv($out, [
                    $result['method_name'],
                    $result['sample_count'],
                    number_format($result['accuracy'] * 100, 2),
                    number_format($result['precision'] * 100, 2),
                    number_format($result['recall'] * 100, 2),
                    number_format($result['f1_score'] * 100, 2)
                ]);
            }
            
            fputcsv($out, ['']);
            fputcsv($out, ['Best Method:', $comparisonResults['best_method']]);
            fputcsv($out, ['Best Accuracy:', number_format($comparisonResults['best_accuracy'] * 100, 2) . '%']);
            
            // Write confusion matrix if available
            if ($confusionMatrix) {
                fputcsv($out, ['']);
                fputcsv($out, ['CONFUSION MATRIX - ' . $confusionMatrix['method_name']]);
                fputcsv($out, ['']);
                
                // Header row
                $headerRow = ['Actual \\ Predicted'];
                foreach ($confusionMatrix['labels'] as $label) {
                    $headerRow[] = ucfirst($label);
                }
                fputcsv($out, $headerRow);
                
                // Matrix rows
                foreach ($confusionMatrix['labels'] as $actualLabel) {
                    $row = [ucfirst($actualLabel)];
                    foreach ($confusionMatrix['labels'] as $predictedLabel) {
                        $row[] = $confusionMatrix['matrix'][$actualLabel][$predictedLabel] ?? 0;
                    }
                    fputcsv($out, $row);
                }
            }
            
            fclose($out);
        }, 200, $headers);
    }

    public function cleanup(Request $request)
    {
        $uploadedFiles = $request->session()->get('eval_uploaded_files', []);
        
        // Delete uploaded files
        foreach ($uploadedFiles as $file) {
            if (Storage::exists($file['path'])) {
                Storage::delete($file['path']);
            }
        }
        
        // Clear session data
        $request->session()->forget(['eval_uploaded_files', 'eval_comparison_results', 'eval_confusion_matrix']);
        
        return redirect()->route('evaluation.index')->with('status', 'Data evaluasi berhasil dibersihkan.');
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

    private function validateCsvStructure(array $header): bool
    {
        $requiredColumns = ['text', 'actual_label', 'predicted_label', 'confidence'];
        $headerLower = array_map('strtolower', $header);
        
        foreach ($requiredColumns as $required) {
            if (!in_array($required, $headerLower)) {
                return false;
            }
        }
        
        return true;
    }

    private function calculateMethodMetrics(array $rows, array $header): array
    {
        $textIndex = array_search('text', array_map('strtolower', $header));
        $actualIndex = array_search('actual_label', array_map('strtolower', $header));
        $predictedIndex = array_search('predicted_label', array_map('strtolower', $header));
        $confidenceIndex = array_search('confidence', array_map('strtolower', $header));

        if ($textIndex === false || $actualIndex === false || $predictedIndex === false) {
            return ['accuracy' => 0, 'precision' => 0, 'recall' => 0, 'f1_score' => 0, 'metrics_per_class' => []];
        }

        $labels = ['positif', 'negatif', 'netral'];
        $metricsPerClass = [];
        $totalCorrect = 0;
        $totalSamples = 0;

        foreach ($labels as $label) {
            $tp = 0; // True Positive
            $fp = 0; // False Positive
            $fn = 0; // False Negative

            foreach ($rows as $row) {
                $actual = strtolower(trim($row[$actualIndex] ?? ''));
                $predicted = strtolower(trim($row[$predictedIndex] ?? ''));

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

            $metricsPerClass[$label] = [
                'precision' => $precision,
                'recall' => $recall,
                'f1_score' => $f1Score,
                'true_positives' => $tp,
                'false_positives' => $fp,
                'false_negatives' => $fn
            ];

            $totalCorrect += $tp;
        }

        $totalSamples = count($rows);
        $accuracy = $totalSamples > 0 ? $totalCorrect / $totalSamples : 0;

        // Calculate macro averages
        $avgPrecision = array_sum(array_column($metricsPerClass, 'precision')) / count($labels);
        $avgRecall = array_sum(array_column($metricsPerClass, 'recall')) / count($labels);
        $avgF1Score = array_sum(array_column($metricsPerClass, 'f1_score')) / count($labels);

        return [
            'accuracy' => $accuracy,
            'precision' => $avgPrecision,
            'recall' => $avgRecall,
            'f1_score' => $avgF1Score,
            'metrics_per_class' => $metricsPerClass,
            'total_samples' => $totalSamples,
            'correct_predictions' => $totalCorrect
        ];
    }

    private function buildConfusionMatrix(array $rows, array $header): array
    {
        $actualIndex = array_search('actual_label', array_map('strtolower', $header));
        $predictedIndex = array_search('predicted_label', array_map('strtolower', $header));

        if ($actualIndex === false || $predictedIndex === false) {
            return ['matrix' => [], 'labels' => [], 'total_samples' => 0];
        }

        $labels = ['positif', 'negatif', 'netral'];
        $matrix = [];
        $totalSamples = count($rows);

        // Initialize matrix
        foreach ($labels as $actual) {
            $matrix[$actual] = [];
            foreach ($labels as $predicted) {
                $matrix[$actual][$predicted] = 0;
            }
        }

        // Fill matrix
        foreach ($rows as $row) {
            $actual = strtolower(trim($row[$actualIndex] ?? ''));
            $predicted = strtolower(trim($row[$predictedIndex] ?? ''));
            
            if (in_array($actual, $labels) && in_array($predicted, $labels)) {
                $matrix[$actual][$predicted]++;
            }
        }

        return [
            'matrix' => $matrix,
            'labels' => $labels,
            'total_samples' => $totalSamples
        ];
    }
}
