# KLASIFIKASI SISTEM - DOKUMENTASI LENGKAP

Folder ini berisi dokumentasi komprehensif untuk sistem **SVM Classification (Sentiment Sentiment Analyzer)** yang dikembangkan dalam skripsi.

Cocok untuk:
- Memahami alur klasifikasi secara mendetail
- Persiapan konsultasi dengan dosen pembimbing
- Presentasi hasil penelitian
- Referensi implementation & optimization

---

## 📚 DAFTAR DOKUMENTASI

### **1. [01_ALUR_KESELURUHAN.md](01_ALUR_KESELURUHAN.md)**
**Penjelasan:** Flow diagram & overview semua step dari upload training sampai classification

**Isi:**
- Flow diagram visual end-to-end
- Tabel file & fungsi penting
- Step-by-step eksekusi proses klasifikasi
- Deskripsi 4 tahap utama (upload → vectorize → train → predict)

**Guna untuk:** Memahami big picture, melihat mana-mana kode berada, alur aplikasi

---

### **2. [02_SVM_CLASSIFICATION_ALGORITHM.md](02_SVM_CLASSIFICATION_ALGORITHM.md)**
**Penjelasan:** Detail implementasi SVM Classification & K-Nearest Neighbors

**Isi:**
- Penjelasan algoritma SVM/KNN yang digunakan
- Konsep TF-IDF & vectorization
- Weighted K-NN approach (k=5)
- Cosine similarity calculation
- Voting mechanism untuk prediksi
- Contoh perhitungan step-by-step
- Accuracy vs Confidence score

**Guna untuk:** Pahami classification algorithm, siap jawab soal algoritma dosen

---

### **3. [03_VECTORIZATION_AND_PREPROCESSING.md](03_VECTORIZATION_AND_PREPROCESSING.md)**
**Penjelasan:** Detail text preprocessing & feature vectorization

**Isi:**
- Preprocessing text:
  - Lowercase conversion
  - Special character removal
  - Whitespace normalization
- Vocabulary building (word filtering >2 chars, min frequency 2)
- Bag-of-Words vectorization
- Term Frequency (TF) counting
- O(1) vs O(n) lookup optimization dengan array_flip()
- Contoh praktis vectorization

**Guna untuk:** Pahami text feature engineering, optimization techniques

---

### **4. [04_KNN_PREDICTION_MECHANISM.md](04_KNN_PREDICTION_MECHANISM.md)**
**Penjelasan:** Detail K-Nearest Neighbors & prediction dengan weighted voting

**Isi:**
- Konsep KNN (k=5 neighbors) vs single NN
- Vector magnitude calculation (norm)
- Cosine similarity formula & optimization
- Similarity ranking & neighbor selection
- Weighted voting mechanism
- Confidence score calculation
- 3 contoh prediksi lengkap dengan output

**Guna untuk:** Pahami prediction logic, confidence calculation, weighted voting

---

### **5. [05_PERFORMANCE_METRICS.md](05_PERFORMANCE_METRICS.md)**
**Penjelasan:** Perhitungan accuracy, precision, recall, F1-score

**Isi:**
- Accuracy calculation (correct/total)
- Per-label metrics:
  - Precision = TP/(TP+FP)
  - Recall = TP/(TP+FN)
  - F1-Score = 2×(P×R)/(P+R)
- Confusion matrix concept
- Interpretation hasil metrics
- Contoh perhitungan 3 kategori (positif/negatif/netral)

**Guna untuk:** Pahami evaluation metrics, interpretasi hasil klasifikasi

---

### **6. [06_OPTIMIZATION_TECHNIQUES.md](06_OPTIMIZATION_TECHNIQUES.md)**
**Penjelasan:** Teknik optimasi performance yang sudah diimplementasikan

**Isi:**
- Problem identification: O(n²m) complexity
- Solution 1: array_flip() untuk O(1) lookup (5000x faster)
- Solution 2: Pre-compute vector magnitude (reduce redundant sqrt)
- Solution 3: cosineSimilarityFast() optimization
- Solution 4: Batch processing 100 samples (memory management)
- Solution 5: set_time_limit(300) & memory_limit(512M)
- Perbandingan before-after performance
- Benchmark results

**Guna untuk:** Pahami optimization strategy, performance improvement, siap jawab pertanyaan dosen

---

### **7. [07_API_ENDPOINT_FLOW.md](07_API_ENDPOINT_FLOW.md)**
**Penjelasan:** Detail flow untuk setiap endpoint API

**Isi:**
- `GET /klasifikasi` - Load halaman index
- `POST /klasifikasi/upload/training` - Upload training data CSV
- `POST /klasifikasi/upload/testing` - Upload testing data CSV
- `POST /klasifikasi/run` - Jalankan klasifikasi
- `GET /klasifikasi/download` - Download hasil CSV
- `GET /klasifikasi/cleanup` - Bersihkan session & temp files
- Request/response format setiap endpoint
- Error handling & validation

**Guna untuk:** Pahami API contract, integration testing, API documentation

---

### **8. [08_QUICK_REFERENCE_DIAGRAM.md](08_QUICK_REFERENCE_DIAGRAM.md)**
**Penjelasan:** Diagram referensi cepat & visual summary

**Isi:**
- Architecture diagram
- Data flow diagram
- Sequence diagram upload → classify → download
- Component interaction diagram
- Database schema (session storage)
- Folder structure

**Guna untuk:** Quick visual reference, presentasi ke dosen

---

## 🚀 QUICK START

Untuk memahami sistem secara keseluruhan secara cepat:
1. Baca **01_ALUR_KESELURUHAN.md** (5 menit) - Pahami flow
2. Baca **02_SVM_CLASSIFICATION_ALGORITHM.md** (10 menit) - Pahami algoritma
3. Baca **06_OPTIMIZATION_TECHNIQUES.md** (5 menit) - Pahami optimasi
4. Baca **08_QUICK_REFERENCE_DIAGRAM.md** (3 menit) - Visualisasi

Total: ~25 menit untuk memahami seluruh sistem.

---

## 📂 File Kode Terkait

### **Controller Utama**
- `app/Http/Controllers/ClassificationController.php` (512 baris)

### **View**
- `resources/views/klasifikasi.blade.php` (313 baris)

### **Route**
- `routes/web.php` (lines 25-33)

### **Config**
- `config/app.php` - Time limit & memory configuration

---

## 🎯 Persiapan Presentasi Dosen

### Pertanyaan yang Mungkin Ditanya Dosen:

1. **"Mengapa menggunakan KNN, bukan pure SVM?"**
   → Lihat: 02_SVM_CLASSIFICATION_ALGORITHM.md (bagian justifikasi)

2. **"Bagaimana cara hitung confidence score?"**
   → Lihat: 04_KNN_PREDICTION_MECHANISM.md (confidence calculation)

3. **"Berapa akurasi sistemnya?"**
   → Lihat: 05_PERFORMANCE_METRICS.md (hasil evaluation)

4. **"Apa yang sudah dioptimasi?"**
   → Lihat: 06_OPTIMIZATION_TECHNIQUES.md (semua improvement)

5. **"Bagaimana alur dari upload sampai prediksi?"**
   → Lihat: 01_ALUR_KESELURUHAN.md + 08_QUICK_REFERENCE_DIAGRAM.md

6. **"Berapa waktu eksekusi untuk 1000 samples?"**
   → Lihat: 06_OPTIMIZATION_TECHNIQUES.md (performance benchmark)

---

## 📊 System Statistics

| Metrik | Nilai |
|--------|-------|
| Max vocabulary size | ~5000 words |
| Batch size | 100 samples |
| KNN neighbors (k) | 5 |
| PHP timeout | 300 detik (5 menit) |
| Memory limit | 512 MB |
| Typical execution (1000 samples) | ~30-45 detik |

---

**Dibuat:** 2026 | **Tujuan:** Dokumentasi komprehensif sistem klasifikasi sentimen
