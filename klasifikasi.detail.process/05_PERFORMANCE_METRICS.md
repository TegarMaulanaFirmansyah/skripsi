# 5. PERFORMANCE METRICS & EVALUATION

## Accuracy Calculation

### Formula:
```
Accuracy = (Correct Predictions) / (Total Predictions)

Range: 0-1 atau 0-100%
```

### Example:
```
Total test samples: 100
Correct predictions: 85
Incorrect predictions: 15

Accuracy = 85 / 100 = 0.85 = 85%
```

### Implementation:
```php
$correct = 0;
$total = count($testingData);

foreach ($testingVectors as $i => $testVector) {
    $prediction = $this->predictSVM($testVector, $trainingVectors, $trainingData);
    
    // Check if prediction correct
    if ($testingData[$i]['actual_label'] && 
        $prediction['label'] === $testingData[$i]['actual_label']) {
        $correct++;
    }
}

$accuracy = $total > 0 ? $correct / $total : 0;
// Result: 0.85
```

---

## Per-Label Metrics (Precision, Recall, F1)

### Confusion Matrix Concept

```
                   PREDICTED
                   Pos   Neg   Neu
        ┌─────────────────────────┐
        │ TP  FN  ? │  POSITIF   │
ACTUAL  │ FP  TN  ? │  NEGATIF   │
        │ ?   ?  ? │  NETRAL    │
        └─────────────────────────┘

TP (True Positive)   = Predicted Positif, Actual Positif ✅
FP (False Positive)  = Predicted Positif, Actual Negatif ❌
FN (False Negative)  = Predicted Negatif, Actual Positif ❌
TN (True Negative)   = Predicted Negatif, Actual Negatif ✅
```

### Precision: "Dari prediksi positif saya, berapa persen yang benar?"

Formula:
```
Precision = TP / (TP + FP)

Range: 0-1
Interpretation:
  1.0 = Perfect, semua positif prediction benar
  0.5 = 50% dari positif prediction benar
  0.0 = Semua positif prediction salah
```

Example:
```
Predicted Positif: 50
  - Correct (TP): 40
  - Incorrect (FP): 10

Precision = 40 / (40 + 10) = 40 / 50 = 0.80 = 80%
Meaning: 80% dari prediksi positif saya benar
```

### Recall: "Dari semua yang seharusnya positif, berapa persen yang aku tangkap?"

Formula:
```
Recall = TP / (TP + FN)

Range: 0-1
Interpretation:
  1.0 = Perfect, tangkap semua label positif yang sebenarnya
  0.5 = Tangkap 50% dari label positif yang sebenarnya
  0.0 = Tidak tangkap satupun
```

Example:
```
Actual Positif: 60
  - Correctly predicted: 40
  - Missed (FN): 20

Recall = 40 / (40 + 20) = 40 / 60 = 0.67 = 67%
Meaning: Saya hanya catch 67% dari kasus positif sebenarnya
```

### F1-Score: "Harmonic mean dari Precision & Recall"

Formula:
```
F1 = 2 × (Precision × Recall) / (Precision + Recall)

Range: 0-1
Interpretation:
  1.0 = Perfect balance antara precision & recall
  0.5 = OK-OK aja
  0.0 = Buruk
```

Example:
```
Precision = 0.80
Recall = 0.67

F1 = 2 × (0.80 × 0.67) / (0.80 + 0.67)
   = 2 × 0.536 / 1.47
   = 1.072 / 1.47
   = 0.729 ≈ 0.73
```

### Why Harmonic Mean?
```
Arithmetic mean = (P + R) / 2 = (0.80 + 0.67) / 2 = 0.735
Harmonic mean = F1 = 0.729

Harmonic mean:
✅ Penalize imbalance (jika P >> R atau sebaliknya)
✅ Lebih fair untuk measure di dataset yang imbalanced
❌ Lebih kompleks dari arithmetic mean
```

---

## Practical Example: 3-Category Classification

### Data:

```
Total test samples: 100

Predictions vs Actuals:
                    Predicted P  Predicted N  Predicted Neutral
Actual P (30)             25             3          2
Actual N (40)              2            36          2
Actual Neutral (30)        3             1         26
```

### Calculations:

**Category 1: POSITIF**
```
TP (Predicted P, Actual P) = 25
FP (Predicted P, Actual N) + (Predicted P, Actual Neutral) = 2 + 3 = 5
FN (Predicted N, Actual P) + (Predicted Neutral, Actual P) = 3 + 2 = 5

Precision_P = 25 / (25 + 5) = 25 / 30 = 0.833
Recall_P = 25 / (25 + 5) = 25 / 30 = 0.833
F1_P = 2 × (0.833 × 0.833) / (0.833 + 0.833)
     = 2 × 0.694 / 1.666
     = 0.833
```

**Category 2: NEGATIF**
```
TP = 36
FP = 3 + 1 = 4
FN = 3 + 1 = 4

Precision_N = 36 / 40 = 0.900
Recall_N = 36 / 40 = 0.900
F1_N = 0.900
```

**Category 3: NETRAL**
```
TP = 26
FP = 2 + 2 = 4
FN = 2 + 2 = 4

Precision_Netral = 26 / 30 = 0.867
Recall_Netral = 26 / 30 = 0.867
F1_Netral = 0.867
```

### Overall Results:

```
Category    Precision  Recall  F1-Score
─────────────────────────────────────
Positif     0.833      0.833   0.833
Negatif     0.900      0.900   0.900
Netral      0.867      0.867   0.867
─────────────────────────────────────
Average     0.867      0.867   0.867

Overall Accuracy: 87/100 = 0.87 = 87%
```

---

## Implementation

### Database Tracking:

```php
private function calculateMetrics(array $predictions): array
{
    $labels = ['positif', 'negatif', 'netral'];
    $metrics = [];

    foreach ($labels as $label) {
        $tp = 0;  // True Positive
        $fp = 0;  // False Positive
        $fn = 0;  // False Negative

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

        // Calculate metrics
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
```

---

## Interpreting Results

### Ideal Scenario:
```
Precision = 1.0  (all positive predictions correct)
Recall = 1.0     (catch all positives)
F1 = 1.0         (perfect classifier)
Accuracy = 1.0   (100% correct overall)
```

### Good Model:
```
F1 >= 0.75       (good balance)
Accuracy >= 0.80 (80%+ correct)
Consistent metrics across categories
```

### Average Model:
```
F1 = 0.60-0.75
Accuracy = 0.70-0.80
Some categories better than others
```

### Poor Model:
```
F1 < 0.60
Accuracy < 0.70
Unreliable predictions
```

---

## Typical System Performance

Based on our classification system:

```
Scenario 1: Clean, balanced dataset
├─ Accuracy: 85-90%
├─ Precision: 0.82-0.88
├─ Recall: 0.82-0.88
└─ F1: 0.82-0.88

Scenario 2: Imbalanced dataset (60% pos, 30% neg, 10% neu)
├─ Accuracy: 75-85%
├─ Precision: varies (0.65-0.95)
├─ Recall: varies (0.65-0.90)
└─ F1: 0.70-0.85

Scenario 3: Noisy data (typos, slang, mixed lang)
├─ Accuracy: 65-80%
├─ Precision: 0.60-0.75
├─ Recall: 0.60-0.75
└─ F1: 0.60-0.75
```

---

## Confusion Matrix Visualization

### Display on Frontend:

```
┌─────────────────────────────────────┐
│ CONFUSION MATRIX                    │
├─────────────────────────────────────┤
│                Predicted            │
│           Pos   Neg   Neu           │
│  Pos       25     3     2           │
│  Neg        2    36     2           │
│  Neu        3     1    26           │
└─────────────────────────────────────┘

Color coding:
├─ Diagonal (correct): Green background
└─ Off-diagonal (wrong): Red background
```

---

## Practical Thresholds

```
If confidence < 0.50:
├─ UNRELIABLE prediction
├─ Consider manual review
└─ May want human annotation

If confidence 0.50-0.70:
├─ MODERATE confidence
├─ Usable but not perfect
└─ Good for training data augmentation

If confidence > 0.70:
├─ GOOD confidence
├─ Reliable for most applications
└─ Safe to use automatically

If F1 < 0.70 for any category:
├─ Category needs improvement
├─ May need more training data for that label
└─ Check for class imbalance issues
```

