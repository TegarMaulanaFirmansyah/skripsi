# 2. Pure SVM CLASSIFICATION ALGORITHM & IMPLEMENTATION

## Konsep Dasar

Dokumen ini menjelaskan pendekatan klasifikasi berbasis Support Vector Machine (SVM) — algoritma supervised learning yang mencari hyperplane terbaik untuk memisahkan kelas dengan margin maksimal. Fokus: pipeline end-to-end (preprocessing → TF‑IDF → scaling → training SVM → prediksi & kalibrasi probabilitas).

---

## Mengapa Pure SVM?

- Margin-based classifier yang kuat untuk teks berdimensi tinggi.
- Efektif pada banyak tugas klasifikasi teks, seringkali lebih baik daripada KNN untuk generalisasi.
- Mendukung kernel (linear, RBF, polynomial) untuk menangani non-linearitas.
- Dengan kalibrasi probabilitas (Platt scaling / isotonic) bisa menghasilkan probabilitas prediksi.

Trade-offs:
- Membutuhkan fase training (vs KNN yang lazy).
- Perlu tuning hyperparameter (`C`, `kernel`, `gamma`).
- Untuk dataset sangat besar, training bisa mahal — gunakan linear SVM (liblinear) atau SGD sebagai approximasi.

---

## Algorithm Architecture

```
┌─────────────────────────────────────┐
│ 1. TEXT PREPROCESSING               │
│ (lowercase, remove stopwords, etc)  │
└──────────────┬──────────────────────┘
               ↓
┌─────────────────────────────────────┐
│ 2. VECTORIZATION (TF-IDF)           │
│ (text → sparse TF-IDF vector)       │
└──────────────┬──────────────────────┘
               ↓
┌─────────────────────────────────────┐
│ 3. FEATURE SCALING / NORMALIZATION  │
│ (L2 normalize / standardize)       │
└──────────────┬──────────────────────┘
               ↓
┌─────────────────────────────────────┐
│ 4. SVM TRAINING                      │
│ (fit SVM with chosen kernel & C)     │
└──────────────┬──────────────────────┘
               ↓
┌─────────────────────────────────────┐
│ 5. PROBABILITY CALIBRATION (opsional)│
│ (Platt scaling / isotonic)           │
└──────────────┬──────────────────────┘
               ↓
┌─────────────────────────────────────┐
│ 6. PREDICTION (decision function)    │
│ (argmax or calibrated probabilities) │
└─────────────────────────────────────┘
```

---

## Fase 1: Text Preprocessing

Langkah mirip dengan pipeline teks umum:
- Lowercase
- Hapus karakter non‑huruf (emoji, tanda baca) jika tidak relevan
- Tokenisasi
- Optional: stopword removal, stemming/lemmatization

Contoh fungsi PHP (sederhana):

```php
private function preprocessText(string $text): string
{
    $text = mb_strtolower($text, 'UTF-8');
    $text = preg_replace('/[^\\p{L}\\s]/u', ' ', $text);
    $text = preg_replace('/\\s+/u', ' ', $text);
    return trim($text);
}
```

---

## Fase 2: VectORIZATION — TF‑IDF

Untuk SVM, gunakan TF‑IDF (bukan raw TF) karena TF‑IDF menurunkan bobot kata yang terlalu sering muncul di banyak dokumen.

TF‑IDF formula singkat:
- tf(t,d) = count(t in d)
- idf(t) = log((N+1) / (df(t)+1)) + 1
- tfidf(t,d) = tf(t,d) * idf(t)

Implementasi tip: bangun `vocabulary` yang terpilih (frekuensi minimum, max features), lalu hitung TF‑IDF untuk setiap dokumen. Representasi biasanya berupa sparse vector.

Pseudo‑PHP (vectorize + idf precompute):

```php
//$vocabulary: [word => index]
//$docFreq: [wordIndex => df]
//$N: jumlah dokumen
private function computeIdf(array $docFreq, int $N): array {
    $idf = [];
    foreach ($docFreq as $i => $df) {
        $idf[$i] = log(($N + 1) / ($df + 1)) + 1.0;
    }
    return $idf;
}

private function tfidfVector(string $text, array $vocabulary, array $idf): array {
    $words = explode(' ', $text);
    $vec = [];
    foreach ($words as $w) {
        if (isset($vocabulary[$w])) {
            $i = $vocabulary[$w];
            $vec[$i] = ($vec[$i] ?? 0) + 1;
        }
    }
    // multiply by idf
    foreach ($vec as $i => $tf) $vec[$i] = $tf * $idf[$i];
    return $vec; // sparse associative array index=>value
}
```

Untuk produksi gunakan library yang efisien (scikit-learn / scipy sparse) atau simpan fitur TF‑IDF pra‑komputasi.

---

## Fase 3: Feature Scaling / Normalization

SVM (terutama linear SVM dengan hinge loss) sensitif ke skala fitur. Untuk teks biasanya lakukan L2 normalization pada TF‑IDF vector (membuat vektor unit norm) sehingga kernel linear cocok dan stabil.

L2 normalization:
$$ v = \\\frac{v}{\\\|v\\\|_2} $$

---

## Fase 4: SVM Training

Objective (soft margin SVM, primal form):
$$ \\\min_{w,b,\\\\xi} \\\frac{1}{2} \\\|w\\\|^2 + C \\\sum_{i=1}^N \\\xi_i $$$
subject to
$$ y_i (w^T x_i + b) \\\ge 1 - \\\xi_i, \\\quad \\\xi_i \\\ge 0 $$

Pilihan kernel umum:
- `linear` — sangat baik untuk TF‑IDF teks berdimensi tinggi
- `rbf` — menangani non-linear separability jika diperlukan
- `poly` — polinomial

Hyperparameter penting:
- `C` (regularization): tradeoff margin vs training error
- `gamma` (untuk RBF / poly): skala kernel

Contoh pelatihan cepat dengan scikit‑learn (direkomendasikan untuk eksperimen):

```python
from sklearn.svm import SVC
from sklearn.pipeline import Pipeline
from sklearn.feature_extraction.text import TfidfVectorizer
from sklearn.model_selection import GridSearchCV

pipeline = Pipeline([
    ('tfidf', TfidfVectorizer(max_features=50000, min_df=2)),
    ('clf', SVC(kernel='linear', probability=True))
])

param_grid = {
    'clf__C': [0.1, 1, 10]
}

grid = GridSearchCV(pipeline, param_grid, cv=5, scoring='f1_macro', n_jobs=-1)
grid.fit(X_train, y_train)
print(grid.best_params_, grid.best_score_)
model = grid.best_estimator_
```

Untuk dataset besar gunakan `LinearSVC` (liblinear backend) atau `SGDClassifier(loss="hinge")` untuk approximasi lebih cepat.

---

## Fase 5: Probability Calibration (opsional)

`SVC(..., probability=True)` mengaktifkan Platt scaling (internal) tetapi lebih aman melakukan kalibrasi terpisah menggunakan `CalibratedClassifierCV`:

```python
from sklearn.calibration import CalibratedClassifierCV
calibrated = CalibratedClassifierCV(grid.best_estimator_.named_steps['clf'], cv=3)
calibrated.fit(X_val_tfidf, y_val)
probs = calibrated.predict_proba(X_test_tfidf)
```

---

## Fase 6: Prediction & Confidence

- Untuk prediksi gunakan `decision_function` (jarak ke hyperplane) atau probabilitas terkalibrasi untuk confidence.
- Pilih label = argmax(proba) atau argmax(decision_function) jika probabilitas tidak dikalibrasi.

Contoh inference singkat (Python):

```python
labels = model.predict(texts)
probs = model.predict_proba(texts)  # jika tersedia
```

Jika implementasi backend utama adalah PHP, simpan model (pickle) dan panggil service Python untuk inference (REST / CLI), atau ekspor fitur dan gunakan executable LIBSVM.

---

## Contoh: LIBSVM (CLI) workflow

1. Convert features ke format LIBSVM: label index:value index:value ...
2. Train: `svm-train -s 0 -t 0 -c 1 train.libsvm model.svm`  (linear kernel)
3. Predict: `svm-predict test.libsvm model.svm output.txt`

Untuk probabilitas: `svm-train -b 1 ...` dan `svm-predict -b 1 ...`.

---

## Evaluasi dan Tuning

- Gunakan stratified CV (k=5/10) untuk hyperparameter tuning.
- Metrics: accuracy, precision/recall/F1 (per kelas), confusion matrix.
- Jika kelas imbalance, gunakan class weights (`class_weight='balanced'` di scikit‑learn) atau sampling.

---

## Deployment Notes

- Untuk latency rendah, gunakan linear SVM dan simpan TF‑IDF vectorizer + model; lakukan inference native di Python atau via microservice.
- Jika harus tetap di PHP, simpan model output (support vectors) dan implementasi evaluasi SVM di PHP bukan ideal; lebih baik panggil model Python.

---

## Ringkasan Praktis

- Gunakan `TfidfVectorizer` + `LinearSVC` untuk teks; lakukan CV tuning `C`.
- Lakukan L2 normalization, gunakan probabilitas terkalibrasi untuk confidence.
- Untuk produksi: ekspor model, sediakan inference API (Python) dan panggil dari aplikasi Laravel.

---

Referensi singkat:
- Cortes, C. & Vapnik, V. (1995). Support-vector networks.
- scikit-learn SVM documentation
