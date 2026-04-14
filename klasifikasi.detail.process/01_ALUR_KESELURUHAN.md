# 1. ALUR KESELURUHAN KLASIFIKASI SENTIMEN

## Flow Diagram End-to-End

```
USER UPLOAD TRAINING CSV
    ↓ [POST /klasifikasi/upload/training]
READ & STORE TRAINING CSV
    ↓ [show preview max 100 baris]
USER UPLOAD TESTING CSV
    ↓ [POST /klasifikasi/upload/testing]
READ & STORE TESTING CSV
    ↓ [show preview max 100 baris]
USER KLIK "JALANKAN SVM CLASSIFICATION"
    ↓ [POST /klasifikasi/run]
╔═══════════════════════════════════════════╗
║     PHASE 1: BUILD VOCABULARY              ║
║     buildVocabulary()                     ║
╚═══════════════════════════════════════════╝
    Scan semua training text
    Count setiap word
    Filter: hanya word panjangnya >2 chars, frekuensi ≥2
    Output: array vocabulary [word1, word2, ..., wordN]
    ↓
╔═══════════════════════════════════════════╗
║     PHASE 2: VECTORIZATION                 ║
║     vectorizeData()                       ║
╚═══════════════════════════════════════════╝
    Convert training texts → vector (TF)
    Convert testing texts → vector (TF)
    Output: 2D array vectors
    ↓
╔═══════════════════════════════════════════╗
║     PHASE 3: KNN TRAINING                  ║
║     (simpan training vectors + labels)    ║
╚═══════════════════════════════════════════╝
    Preparation untuk prediction phase
    ↓
╔═══════════════════════════════════════════╗
║     PHASE 4: BATCH PREDICTION              ║
║     runSVMClassificationBatch()            ║
║     Process 100 samples at a time          ║
╚═══════════════════════════════════════════╝
    LOOP setiap test vector:
    ├─ Calculate cosine similarity ke SEMUA training vectors
    ├─ Find top 5 most similar (KNN)
    ├─ Weighted voting untuk predict label
    ├─ Calculate confidence score
    └─ Simpan: [text, actual_label, predicted_label, confidence]
    ↓
╔═══════════════════════════════════════════╗
║     PHASE 5: CALCULATE METRICS             ║
║     calculateMetrics()                    ║
╚═══════════════════════════════════════════╝
    For each category (positif/negatif/netral):
    ├─ Count TP, FP, FN
    ├─ Calculate Precision, Recall, F1
    ↓
STORE RESULTS TO TEMP FILE (JSON)
    ↓ [100% prediction complete]
SIMPAN SUMMARY KE SESSION
    ↓
TAMPILKAN HASIL DI TABLE
    ├─ Accuracy
    ├─ Correct/Incorrect count
    ├─ Per-label metrics (P/R/F1)
    ↓
USER DAPAT DOWNLOAD CSV HASIL
    ↓ [GET /klasifikasi/download]
OUTPUT: CSV dengan kolom [text, actual_label, predicted_label, confidence]
```

---

## File & Fungsi Penting

### **Controller Utama**
**File:** `app/Http/Controllers/ClassificationController.php`

| Fungsi | Baris | Deskripsi |
|--------|-------|-----------|
| `index()` | 11-28 | Load halaman klasifikasi, ambil preview & results dari session |
| `uploadTraining()` | 30-48 | Handle upload training CSV → store → generate preview |
| `uploadTesting()` | 50-68 | Handle upload testing CSV → store → generate preview |
| `runClassification()` | 70-144 | **MAIN:** set timeout → read data → call classification batch → save results |
| `runSVMClassificationBatch()` | 232-277 | **CORE:** batch prediction dengan KNN voting |
| `buildVocabulary()` | 349-365 | Build word vocabulary dari training data |
| `vectorizeData()` | 367-384 | Convert text → TF vectors dengan O(1) lookup |
| `predictSVM()` | 386-441 | **CORE:** KNN similarity + weighted voting untuk 1 sample |
| `vectorMagnitude()` | 443-450 | Calculate L2 norm untuk vector |
| `cosineSimilarityFast()` | 452-464 | Calculate cosine similarity (optimized) |
| `calculateMetrics()` | 490-535 | Hitung P/R/F1 score per kategori |
| `downloadResults()` | 146-173 | Export hasil klasifikasi ke CSV |
| `cleanup()` | 175-190 | Bersihkan session & temp files |

---

## Step-by-Step Eksekusi Lengkap

### **Step 1: Upload Training CSV**
```
GET /klasifikasi
User input file training.csv

POST /klasifikasi/upload/training
1. Validate file: accept .csv atau .txt
2. Store ke: storage/classification/training_<timestamp>_<filename>.csv
3. Read CSV preview (max 100 baris pertama)
   - Parse header → ambil semua column names
   - Parse 100 baris data → [col1, col2, col3, ...]
4. Simpan ke session:
   - class_training_path = storage path
   - class_training_preview = {header: [...], rows: [...]}
5. Redirect ke /klasifikasi dengan status "Data training berhasil diupload"

Frontend: 
   - Tampilkan file name
   - Show preview table
```
**Kode:** Line 30-48

---

### **Step 2: Upload Testing CSV**
```
POST /klasifikasi/upload/testing
Sama seperti training, tapi tersimpan di session:
   - class_testing_path
   - class_testing_preview

Frontend:
   - Tampilkan file name
   - Show preview table
   - Enable tombol "Jalankan SVM Classification"
```
**Kode:** Line 50-68

---

### **Step 3: Run Classification - Main Loop**
```
POST /klasifikasi/run
Set execution environment:
├─ set_time_limit(300) → allow 5 menit execution
└─ ini_set('memory_limit', '512M') → 512MB memory

PHASE 1: Read Data
├─ Ambil path dari session (class_training_path, class_testing_path)
├─ Validate: file exists & accessible
├─ Read full CSV (not preview)
│  ├─ Parse all training rows
│  └─ Parse all testing rows
└─ Detect text & label columns (auto-detection)

PHASE 2: Data Preparation
├─ LOOP training rows:
│  ├─ Extract text & label
│  ├─ Preprocess text (lowercase + remove special chars)
│  └─ Simpan: [preprocessed_text, label]
├─ LOOP testing rows:
│  ├─ Extract text
│  ├─ Preprocess text
│  └─ Simpan: [preprocessed_text, actual_label/null]

PHASE 3: Call Classification
└─ results = runSVMClassificationBatch(trainingData, testingData)
     ├─ BUILD VOCABULARY dari training
     ├─ VECTORIZE training → trainingVectors
     ├─ BATCH PROCESS testing:
     │  └─ Process 100 test samples at a time
     └─ RETURN predictions array dengan accuracy & metrics

PHASE 4: Store Results
├─ Create temp file: storage/app/temp/temp_classification_<id>.json
├─ Write full results (semua predictions) ke temp file
├─ Simpan summary ke session:
│  ├─ class_temp_file = temp filename
│  ├─ class_results_summary = {accuracy, total_samples, correct_predictions, metrics}
└─ Redirect ke /klasifikasi dengan status "Klasifikasi selesai. Akurasi: X.XX%"

Frontend:
├─ Show loading spinner saat submit
├─ Disable button saat processing
├─ Auto-refresh atau long-poll untuk hasil
└─ Display results table setelah selesai
```
**Kode:** Line 70-144

---

### **Step 4: Batch Processing Detail**
```
runSVMClassificationBatch(trainingData, testingData)

1. BUILD VOCABULARY (Line 256)
   vocabulary = buildVocabulary(trainingData)
   /// Output: [positive, negative, ..., excellent]

2. VECTORIZE TRAINING DATA (Line 257)
   trainingVectors = vectorizeData(trainingData, vocabulary)
   /// Output: [[1,0,2,0,...], [0,1,0,1,...], ...]

3. BATCH PREDICTION (Line 261-276)
   FOR batch = 0; batch < total; batch += 100
   ├─ testBatch = trainingData[batch : batch+100]
   ├─ testBatchVectors = vectorizeData(testBatch, vocabulary)
   ├─ FOR each testVector in testBatchVectors:
   │  ├─ prediction = predictSVM(testVector, trainingVectors, trainingData)
   │  ├─ Store prediction result + text + actual_label
   │  └─ Count correct if predicted == actual_label
   │
   └─ Next batch...

4. CALCULATE ACCURACY
   accuracy = correct / total
   
5. CALCULATE METRICS
   metrics = calculateMetrics(predictions)
   /// For each label: precision, recall, f1_score

6. RETURN RESULTS
   {
      accuracy: 0.85,
      predictions: [...],  // semua prediction results
      metrics: {...},      // per-label metrics
      total_samples: 100,
      correct_predictions: 85
   }
```
**Kode:** Line 232-277

---

### **Step 5: Prediction untuk 1 Sample (KNN Voting)**
```
predictSVM(testVector, trainingVectors, trainingData)

1. COMPUTE TEST VECTOR MAGNITUDE
   testMagnitude = vectorMagnitude(testVector)
   /// If zero → return netral with 0 confidence

2. COMPUTE SIMILARITIES (Loop all training vectors)
   FOR i = 0 to len(trainingVectors):
   ├─ trainMagnitude = vectorMagnitude(trainingVectors[i])
   ├─ similarity = cosineSimilarityFast(testVector, trainVector, testMag, trainMag)
   └─ Store: {index: i, similarity: sim, label: trainingData[i]['label']}

3. SORT BY SIMILARITY (DESC)
   usort(similarities, [highest similarity first])
   /// [[sim=0.92, label=positif], [sim=0.88, label=positif], ...]

4. WEIGHTED VOTING (TOP 5 NEIGHBORS)
   FOR i = 0 to min(5, count(similarities)):
   ├─ label = similarities[i]['label']
   ├─ weight = similarities[i]['similarity']  // cosine similarity
   ├─ labelVotes[label] += weight
   ├─ totalWeight += weight
   
   Result votingnya:
   labelVotes = {
      'positif': 0.92 + 0.88 = 1.80,
      'negatif': 0.15,
      'netral': 0.07
   }

5. GET WINNER
   bestLabel = label dengan votes tertinggi (positif = 1.80)
   bestConfidence = labelVotes['positif'] / totalWeight = 1.80 / 2.02 = 0.89

6. RETURN
   {
      label: 'positif',
      confidence: 0.89
   }
```
**Kode:** Line 386-441

---

### **Step 6: Download Results**
```
GET /klasifikasi/download

1. Ambil temp file dari session: class_temp_file
2. Read JSON file dari storage/app/temp/
3. Loop setiap prediction, format CSV:
   text, actual_label, predicted_label, confidence
   "bagus banget", "positif", "positif", 0.92
   "jelek", "negatif", "negatif", 0.88
   ...
4. Stream CSV response ke browser
5. File download: classification_results_<timestamp>.csv
```
**Kode:** Line 146-173

---

### **Step 7: Cleanup**
```
GET /klasifikasi/cleanup

1. Delete temp file dari storage/app/temp/
2. Clear session variables:
   - class_temp_file
   - class_results_summary
   - class_training_path
   - class_testing_path
   - class_training_preview
   - class_testing_preview
3. Redirect ke /klasifikasi dengan status "Data berhasil dibersihkan"

Frontend: siap untuk upload baru
```
**Kode:** Line 175-190

---

## Preprocessing Text

```
preprocessText(text)

INPUT: "Produk BAGUS!!! 😊 Puas bgt"

1. mb_strtolower() → "produk bagus!!! 😊 puas bgt"

2. preg_replace('/[^\p{L}\s]/u', ' ', text)
   Remove semua non-letter & non-whitespace
   "produk bagus   puas bgt"

3. preg_replace('/\s+/u', ' ', text)
   Normalize multiple spaces → single space
   "produk bagus puas bgt"

4. trim()
OUTPUT: "produk bagus puas bgt"
```

---

## Column Detection

```
detectTextColumnIndex(header)
Search for column names (case-insensitive):
['text', 'tweet', 'content', 'message', 'body', 'review', 'ulasan', 'preprocessed']
Fallback: index 0 (first column)

detectLabelColumnIndex(header)
Search for column names:
['label', 'sentiment', 'class', 'category', 'target']
Fallback: index 1 (second column)
```

---

## Error Handling

| Error | Status | Response |
|-------|--------|----------|
| File not uploaded | 422 | Validation error message |
| Training data missing | 422 | "Data training belum diupload" |
| Testing data missing | 422 | "Data testing belum diupload" |
| Format tidak sesuai | 422 | "Format data tidak sesuai..." |
| Classification not done | 404 | "Belum ada hasil klasifikasi" |

---

## Summary Data Flow

```
TRAINING CSV
    ↓ [upload]
storage/classification/training_*.csv
    ↓ [read]
trainingData[] = [
    {text: "bagus", label: "positif"},
    {text: "jelek", label: "negatif"},
    ...
]
    ↓ [build vocab]
vocabulary[] = [positive, negative, jelek, bagus, ...]
    ↓ [vectorize]
trainingVectors[][] = [[1,0,0,2,...], [0,1,2,0,...], ...]
    ↓ [classify]
TESTING CSV → testingVectors → KNN prediction
    ↓ [calculate metrics]
predictions[] + accuracy + metrics
    ↓ [store temp]
storage/app/temp/temp_classification_*.json
    ↓ [display]
Frontend table + download button
```

