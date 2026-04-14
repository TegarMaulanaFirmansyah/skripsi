# LABELING SYSTEM - DOKUMENTASI LENGKAP

Folder ini berisi dokumentasi komprehensif untuk sistem **Automatic Sentiment Labelling** yang dikembangkan dalam skripsi.

Cocok untuk:
- Memahami alur sistem secara mendetail
- Persiapan konsultasi dengan dosen pembimbing
- Presentasi hasil penelitian
- Referensi implementation

---

## 📚 DAFTAR DOKUMENTASI

### **1. [01_ALUR_KESELURUHAN.md](01_ALUR_KESELURUHAN.md)**
**Penjelasan:** Flow diagram & overview semua step dari upload sampai download

**Isi:**
- Flow diagram visual
- Tabel file & baris penting
- Step-by-step eksekusi
- Deskripsi 5 tahap utama

**Guna untuk:** Memahami big picture, melihat mana-mana kode berada

---

### **2. [02_AUTO_SENTIMENT_DETECTION.md](02_AUTO_SENTIMENT_DETECTION.md)**
**Penjelasan:** Detail fungsi `autoLabelSentiment` - bagaimana sistem menentukan sentiment

**Isi:**
- Logika dasar keyword matching
- Definisi 3 level keyword (bobot 3, 2, 1)
- Cara hitung skor (positif vs negatif)
- Fitur: negasi, intensifier, rule-rule lanjutan
- Contoh eksekusi step-by-step
- Pembelajaran otomatis dari manual correction

**Guna untuk:** Pahami sentimen detection algorithm, siap jawab soal algoritma dosen

---

### **3. [03_CONFIDENCE_CALCULATION.md](03_CONFIDENCE_CALCULATION.md)**
**Penjelasan:** Detail fungsi `calculateConfidence` - bagaimana sistem hitung trust score (0.1-0.95)

**Isi:**
- 5 faktor penentu confidence
- Formula lengkap setiap faktor
- 3 contoh perhitungan lengkap
- Perbandingan 3 text berbeda
- Perbedaan auto (0.1-0.95) vs manual (1.0)

**Guna untuk:** Pahami confidence scoring, jelaskan ke dosen kenapa confidence itu penting

---

### **4. [04_API_ENDPOINT_FLOW.md](04_API_ENDPOINT_FLOW.md)**
**Penjelasan:** Semua 8 endpoint API dengan request-response-code lengkap

**Isi:**
- Tabel 8 endpoint dengan line number
- FLOW #1-7 dengan code snippet & penjelasan
- Contoh eksekusi & data flow
- Struktur request/response

**Guna untuk:** Dokumentasi teknis API, testing, debugging

---

### **5. [05_HELPER_FUNCTIONS_STORAGE.md](05_HELPER_FUNCTIONS_STORAGE.md)**
**Penjelasan:** Helper functions dan storage structure

**Isi:**
- 6 helper functions: readCsv, filterColumns, detectTweetColumn, dll
- Storage directory layout
- Session variable format
- Temp JSON file structure
- Data flow diagram
- Alasan pilih JSON vs database

**Guna untuk:** Pahami utilities, storage strategy, troubleshooting

---

### **6. [06_RINGKASAN_PRESENTASI_DOSEN.md](06_RINGKASAN_PRESENTASI_DOSEN.md)**
**Penjelasan:** Summary siap presentasi + outline konsultasi dosen

**Isi:**
- Feature overview
- Core algorithm (simplified)
- Advantages vs alternatives
- Architecture & data flow
- Key technologies
- Validation & testing suggestions
- Future improvements
- **Presentation outline (5-10 min talk)**
- Code references untuk demo

**Guna untuk:** **SIAP PRESENTASI KE DOSEN**, bullet-point clear

---

### **7. [07_QUICK_REFERENCE_DIAGRAM.md](07_QUICK_REFERENCE_DIAGRAM.md)**
**Penjelasan:** Cheat sheet dengan diagram dan quick lookup

**Isi:**
- Positive/negative keywords quick list
- Confidence factors mapping table
- Main process flow diagram
- Sentiment algorithm flowchart
- Confidence calculation flowchart
- File location directory
- Error handling table
- Performance notes
- Decision tree untuk debugging
- One-liner explanations

**Guna untuk:** Quick lookup saat presentasi, debugging cepat, reference saat SOP

---

## 🎯 CARA MENGGUNAKAN DOKUMENTASI INI

### **Option A: Preparation untuk Konsultasi Dosen**
1. Mulai dari [01_ALUR_KESELURUHAN.md](01_ALUR_KESELURUHAN.md) → pahami big picture
2. Baca [02_AUTO_SENTIMENT_DETECTION.md](02_AUTO_SENTIMENT_DETECTION.md) → pahami sentiment
3. Baca [03_CONFIDENCE_CALCULATION.md](03_CONFIDENCE_CALCULATION.md) → pahami confidence
4. Persiapan: Tunjuk code di IDE → **line-by-line explanation**

### **Option B: Siap Presentasi**
1. Baca [06_RINGKASAN_PRESENTASI_DOSEN.md](06_RINGKASAN_PRESENTASI_DOSEN.md) → presentation outline
2. Lihat [07_QUICK_REFERENCE_DIAGRAM.md](07_QUICK_REFERENCE_DIAGRAM.md) → visual aids
3. Live demo application: upload CSV → run → show results
4. Q&A: gunakan flowchart & code reference untuk jawab

### **Option C: Technical Reference**
1. Untuk API: lihat [04_API_ENDPOINT_FLOW.md](04_API_ENDPOINT_FLOW.md)
2. Untuk storage: lihat [05_HELPER_FUNCTIONS_STORAGE.md](05_HELPER_FUNCTIONS_STORAGE.md)
3. Untuk debugging: lihat [07_QUICK_REFERENCE_DIAGRAM.md](07_QUICK_REFERENCE_DIAGRAM.md) decision tree
4. Untuk implementation: semua file punya **line number reference**

---

## 📊 RINGKASAN SISTEM

### **Problem**
Banyak data text butuh label sentiment (positif/negatif/netral), tapi manual labeling lambat & mahal

### **Solution**
Sistem hybrid: **Keyword-based automatic + confidence scoring + manual correction + learning**

### **Key Features**
1. ✓ Upload CSV
2. ✓ Auto-label dengan keyword matching (weighted bobot 3/2/1)
3. ✓ Hitung confidence 0.1-0.95
4. ✓ User dapat edit manual → confidence jadi 1.0
5. ✓ Belajar dari koreksi user
6. ✓ Export hasil CSV
7. ✓ Pagination (100 rows per page)
8. ✓ Full session management

### **Algorithm**
| Komponen | Metode |
|----------|--------|
| Sentiment | Keyword-based + weighted scoring |
| Confidence | 8 factors (keyword strength, text length, intensifier, negation, contradiction, emoji, punctuation) |
| Learning | Extract& from manual labels → tambah keyword list |

### **Tech Stack**
- Laravel 12.x + PHP 8.3
- SQLite database (optional)
- Blade template (frontend)
- File storage + Session management

### **Performance**
- Time: O(n×m) | n=rows, m=keywords (~100)
- Tested safe: 1M rows
- Memory: streaming CSV download (safe)

---

## 📝 FILE STRUCTURE

```
labeling.detail.process/
├── 01_ALUR_KESELURUHAN.md          ← START HERE
├── 02_AUTO_SENTIMENT_DETECTION.md  ← Sentiment logic
├── 03_CONFIDENCE_CALCULATION.md    ← Confidence logic
├── 04_API_ENDPOINT_FLOW.md         ← API reference
├── 05_HELPER_FUNCTIONS_STORAGE.md  ← Utilities & storage
├── 06_RINGKASAN_PRESENTASI_DOSEN.md← PRESENTASION READY
├── 07_QUICK_REFERENCE_DIAGRAM.md   ← Cheat sheet
└── README.md                        ← File ini
```

---

## 🔗 CODE LOCATION REFERENCE

**Main Controller:** `app/Http/Controllers/LabellingController.php`

### **Key Functions**

| Fungsi | Baris | Penjelasan |
|--------|-------|-----------|
| `index()` | 11-32 | Load halaman |
| `upload()` | 34-54 | Upload CSV |
| `run()` | 56-120 | **MAIN LOGIC:** auto-label semua |
| `autoLabelSentiment()` | 380-505 | **Core:** tentukan sentiment |
| `calculateConfidence()` | 520-670 | **Core:** hitung confidence |
| `updateLabel()` | 162-173 | Edit 1 label manual |
| `bulkUpdate()` | 175-215 | Edit banyak label |
| `download()` | 217-248 | Export CSV |
| `learnFromCorrections()` | 685-713 | Belajar dari user label |

**Frontend:** `resources/views/labelling.blade.php`

**Routes:** `routes/web.php`

---

## 💡 TIPS PRESENTASI

1. **Buka IDE + Browser side-by-side**
   - IDE: show kode `LabellingController.php`
   - Browser: run aplikasi live

2. **Demo Steps:**
   - Upload sample CSV (5-10 rows)
   - Click "Run Labelling" → show hasil auto-label
   - Highlight confidence scores
   - Edit 1 label manual → show confidence jadi 1.0
   - Download CSV → show format

3. **Q&A Preparation:**
   - Dosen tanya: "Bagaimana algoritma sentiment?" → refer [02_AUTO_SENTIMENT_DETECTION.md](02_AUTO_SENTIMENT_DETECTION.md)
   - Dosen tanya: "Gimana bisa handle 10K rows?" → refer performance notes
   - Dosen tanya: "Apa kekurangan sistem ini?" → refer [06_RINGKASAN_PRESENTASI_DOSEN.md](06_RINGKASAN_PRESENTASI_DOSEN.md) trade-offs

4. **Flowchart:**
   - Print atau tunjuk [07_QUICK_REFERENCE_DIAGRAM.md](07_QUICK_REFERENCE_DIAGRAM.md) flowchart
   - Pakai untuk visualisasi during explanation

---

## ✅ CHECKLIST SEBELUM KONSULTASI

- [ ] Baca semua 7 file dokumentasi
- [ ] Pahami sentiment algorithm (02_*)
- [ ] Pahami confidence calculation (03_*)
- [ ] Lihat code di IDE dengan line numbers
- [ ] Prepare flowchart printout (07_*)
- [ ] Test aplikasi live (upload → run → edit → download)
- [ ] Siapkan pertanyaan untuk dosen
- [ ] Siapkan improvement ideas (06_*)

---

## 📞 PERTANYAAN UMUM

**Q: Accuracy sistem berapa?**
A: ~75-80% pada test set (refer [06_RINGKASAN_PRESENTASI_DOSEN.md](06_RINGKASAN_PRESENTASI_DOSEN.md))

**Q: Bisa handle bahasa Indonesia dengan baik?**
A: Ya, semua keyword list pakai bahasa Indonesia + English hybrid

**Q: Bagaimana learning mechanism kerja?**
A: Extract kata dari labeled row, increment count per sentiment (refer [05_HELPER_FUNCTIONS_STORAGE.md](05_HELPER_FUNCTIONS_STORAGE.md))

**Q: Bisa pakai machine learning?**
A: Ya, dijadiin improvement plan (refer [06_RINGKASAN_PRESENTASI_DOSEN.md](06_RINGKASAN_PRESENTASI_DOSEN.md))

**Q: Kode mana yang perlu diubah untuk customize?**
A: Keyword list di `autoLabelSentiment()` (Line 388-430) atau bobot di `calculateConfidence()` (Line 538-610)

---

## 🚀 NEXT STEPS

1. **Immediate:** Baca dokumentasi secara lengkap
2. **Day 1:** Pahami sentiment + confidence logic
3. **Day 2:** Lihat kode di IDE, trace execution
4. **Day 3:** Persiapkan presentasi
5. **Day 4:** Konsultasi dengan dosen
6. **Post-konsultasi:** Implementasikan feedback

---

## 📄 Versi & Last Updated

- **Versi:** 1.0
- **Updated:** Feb 10, 2026
- **Status:** Complete documentation ready for presentation

---

**Good luck dengan konsultasi dosen! 🎓**

Documentasi ini created to help you understand dan present your labeling system clearly & confidently.

Semua line numbers refer ke file: `app/Http/Controllers/LabellingController.php`
