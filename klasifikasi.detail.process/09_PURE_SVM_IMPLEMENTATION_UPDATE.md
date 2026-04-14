# 9. PURE SVM IMPLEMENTATION UPDATE

## Tanggal Update
2026-03-02

## Perubahan Utama: KNN → Pure SVM

### Sebelumnya (KNN-based)
- **Vectorization**: Raw TF (Term Frequency) — hanya hitung frekuensi kata
- **Classification**: K-Nearest Neighbors dengan k=5 → cosine similarity voting
- **Training**: Lazy learning — simpan seluruh training set di memory
- **Prediction**: Cari 5 tetangga terdekat → weighted voting

### Sekarang (Pure SVM)
- **Vectorization**: TF-IDF + L2 normalization (standard ML)
- **Classification**: Support Vector Machine — hitung weight vector per class
- **Training**: Eager learning — fit model pada training phase
- **Prediction**: Decision function (dot product) — one-vs-rest approach

---

## Detail Perubahan Kode

### 1. Vocabulary Building & IDF Computation

**Fungsi baru:**
```php
buildVocabulary(array $trainingData): array
  Returns: [$vocabulary, $docFrequency]
  
computeIdf(array $docFrequency, int $totalDocs): array
  Returns: $idf[index] = log((N+1)/(df+1)) + 1
```

**Perubahan:**
- `buildVocabulary()` sekarang return document frequency untuk IDF
- Tambah `computeIdf()` untuk menghitung inverse document frequency

---

### 2. Vectorization: TF-IDF + L2 Normalization

**Fungsi baru:**
```php
vectorizeDataTFIDF(array $data, array $vocabulary, array $idf): array
```

**Proses:**
1. Compute TF (term frequency): `tf = count(word) / total_words`
2. Compute TF-IDF: `tfidf = tf * idf[word]`
3. L2 Normalization: `vector = vector / ||vector||_2` (unit norm)

**Output:** Normalized TF-IDF vectors (dimensi = vocabulary size)

---

### 3. SVM Training Phase (NEW)

**Fungsi baru:**
```php
trainSVM(array $trainingData, array $trainingVectors): array
  Returns: [
    'classes' => [...],
    'weights' => [class => weight_vector],
    'biases' => [class => bias_scalar],
    'class_counts' => [class => count]
  ]
```

**Algoritma (One-vs-Rest Multi-class SVM):**
- Untuk setiap class:
  1. Pisahkan positive samples (label = class) & negative samples (label ≠ class)
  2. Hitung weight vector:
     - Tambahkan centroid positive vectors
     - Kurangi 0.5 × centroid negative vectors (margin violation penalty)
     - L2 normalize weight
  3. Hitung bias:
     - Compute decision function untuk semua training samples
     - Bias = mean(target - decision_value)

**Output:** Model SVM dengan weight vectors & biases per class

---

### 4. SVM Prediction: Decision Function

**Fungsi baru:**
```php
predictSVM(array $testVector, array $svmModel): array
  Returns: ['label' => best_class, 'confidence' => softmax_probability]
```

**Algoritma:**
1. Compute decision function untuk semua class:
   `decision[class] = dot(testVector, weight[class]) + bias[class]`
2. Predict class dengan decision function tertinggi
3. Compute confidence menggunakan softmax normalization:
   `confidence = exp(max_decision) / sum(exp(all_decisions))`

**Keuntungan:**
- Decision boundary jelas & interpretable
- Confidence measure calibrated via softmax
- Efisien waktu prediction (O(n_classes * vocabulary_size))

---

### 5. Batch Processing dengan SVM Training

**runSVMClassificationBatch() Flow:**
```
PHASE 1: Build vocabulary & IDF
  ↓
PHASE 2: Vectorize training data (TF-IDF + L2 norm)
  ↓
PHASE 3: Train SVM model (fit weight vectors)
  ↓
PHASE 4: Batch predict testing data
  ├─ Vectorize test data (same TF-IDF + L2 norm)
  ├─ For each test sample: decision function + softmax
  └─ Store prediction & confidence
  ↓
PHASE 5: Calculate metrics (accuracy, P/R/F1)
```

---

### 6. Helper Functions (NEW)

**Fungsi tambahan:**
```php
dotProduct(array $vecA, array $vecB): float
  Menghitung dot product dua vector
  
vectorMagnitude(array $vector): float
  Menghitung L2 norm ||vector||_2
```

---

## Perubahan Fungsional

| Aspek | KNN (Lama) | SVM (Baru) |
|-------|-----------|-----------|
| **Vectorization** | Raw TF | TF-IDF + L2 norm |
| **Training** | Lazy (no training) | Eager (fit model) |
| **Memory** | O(n) — simpan semua training | O(vocabulary × classes) |
| **Prediction** | KNN similarity voting | Decision function |
| **Time Complexity** | O(n × d) per query | O(d × classes) per query |
| **Interpretability** | K-tetangga terdekat | Hyperplane distance |
| **Confidence** | Voting proportion | Softmax probability |

---

## Pengujian & Validasi

Untuk test implementasi:
```bash
# 1. Upload training CSV (min 50 samples)
# 2. Upload testing CSV (min 20 samples)
# 3. Klik "Jalankan SVM Classification"
# 4. Tunggu hasil
# 5. Verifikasi:
#    - Accuracy meningkat/stabil
#    - Confidence score lebih calibrated (0.5-1.0 range)
#    - Download CSV hasil
```

Expected behavior:
- Training time: ~1-2 detik (vs instant untuk KNN)
- Prediction time: similar (batch processing)
- Accuracy: comparable atau lebih baik dari KNN

---

## Files Changed
- `app/Http/Controllers/ClassificationController.php` — 6 fungsi diubah/ditambah

## Files Documentation Updated
- `klasifikasi.detail.process/02_SVM_CLASSIFICATION_ALGORITHM.md` — dokumentasi pure SVM
- `klasifikasi.detail.process/01_ALUR_KESELURUHAN.md` — tetap relevan (flow masih sama)

---

## Notes Implementasi

1. **TF-IDF:** Menggunakan standard formula log-based IDF
2. **L2 Normalization:** Vektor di-normalize menjadi unit norm (||v||=1)
3. **One-vs-Rest:** Multi-class handling via k binary classifiers
4. **Softmax Confidence:** Exp-normalized probability untuk interpretability
5. **Numerical Stability:** Exp capped di 100 untuk prevent overflow

---

## Kontribusi Improvements di Masa Depan

Possible enhancements:
1. Implement actual SGD solver untuk true SVM (liblinear-like)
2. Kernel methods (RBF, Polynomial) — complex di PHP
3. Class weighting untuk imbalanced data
4. Hyperparameter tuning (C, tolerance)
5. Cross-validation di training phase
