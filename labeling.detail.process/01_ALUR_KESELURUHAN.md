# 1. ALUR KESELURUHAN LABELLING

## Flow Diagram
```
USER UPLOAD CSV
    ↓ [POST /labelling/upload]
READ & STORE CSV
    ↓ [show preview]
USER KLIK "RUN LABELLING"
    ↓ [POST /labelling/run]
AUTO-LABEL SETIAP BARIS (autoLabelSentiment)
    ↓
HITUNG CONFIDENCE (calculateConfidence)
    ↓
SIMPAN KE SESSION + TEMP FILE
    ↓
TAMPILKAN DI TABLE (100 baris per halaman)
    ↓
USER BISA EDIT LABEL MANUAL
    ↓ [POST /labelling/update-label ATAU /labelling/bulk-update]
UPDATE SENTIMENT & CONFIDENCE = 1.0
    ↓
USER DOWNLOAD CSV HASIL
    ↓ [GET /labelling/download]
OUTPUT: CSV dengan kolom [raw, sentiment, confidence]
```

---

## File & Baris Penting

### **1. Controller Utama**
**File:** `app/Http/Controllers/LabellingController.php`

| Fungsi | Baris | Deskripsi |
|--------|-------|-----------|
| `index()` | 11-32 | Load halaman labelling |
| `upload()` | 34-54 | Handle upload CSV |
| `run()` | 56-120 | **MAIN LOGIC:** baca CSV → auto-label semua → hitung confidence → simpan |
| `autoLabelSentiment()` | 380-485 | **Core:** tentukan sentiment positif/negatif/netral |
| `calculateConfidence()` | 520-670 | **Core:** hitung score kepercayaan (0.1-0.95) |
| `updateLabel()` | 162-173 | Edit 1 label manual → confidence jadi 1.0 |
| `bulkUpdate()` | 175-215 | Edit banyak label sekaligus |
| `download()` | 217-248 | Export hasil ke CSV |

---

## Step-by-Step Eksekusi

### **Step 1: Upload CSV**
```
POST /labelling/upload
Upload file CSV → Simpan ke storage/labelling/
Read 200 baris pertama → Filter kolom noisy (score, time, at)
Tampilkan preview di frontend
```
**Kode:** Line 34-54

### **Step 2: Run Auto-Labelling**
```
POST /labelling/run (Line 56)
├─ Read seluruh CSV (bukan preview)
├─ Detect tweet column (cari kolom bernama 'tweet', 'text', 'content', dll)
├─ LOOP setiap baris:
│  ├─ Ambil teks tweet
│  ├─ PANGGIL autoLabelSentiment() → dapatkan sentiment
│  ├─ PANGGIL calculateConfidence() → dapatkan score
│  └─ Simpan: [raw_text, sentiment, confidence]
├─ Simpan semua ke temp file JSON (Line 107)
├─ Simpan 100 baris pertama ke session
└─ Redirect ke halaman labelling
```
**Kode:** Line 56-120

### **Step 3: Display dengan Pagination**
```
Frontend tampilkan 100 baris per halaman
User bisa navigate halaman 1, 2, 3, dst
```
**Kode:** Line 122-161 (getPage)

### **Step 4: Manual Edit Label**
```
User klik dropdown sentiment pada baris tertentu
POST /labelling/update-label OR /labelling/bulk-update
├─ Update sentiment di temp file
├─ Set confidence = 1.0 (karena manual)
└─ Reload halaman
```
**Kode:** Line 162-215

### **Step 5: Download Hasil**
```
User klik "Download"
GET /labelling/download
├─ Baca semua data dari temp file
├─ Stream CSV ke browser
└─ File: labelling_YYYYMMDD_HHMMSS.csv
```
**Kode:** Line 217-248
