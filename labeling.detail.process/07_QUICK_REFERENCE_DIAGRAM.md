# 7. QUICK REFERENCE & DIAGRAM

---

## SENTIMENT DETECTION QUICK REFERENCE

### **Positive Keywords (Bobot: 3, 2, 1)**

**Bobot 3 (SANGAT POSITIF):**
```
sangat bagus, sangat baik, sangat puas, sangat senang, sangat suka,
terbaik, terlalu bagus, perf perfect, awesome, amazing, brilliant,
excellent, love, loved, recommend, highly recommend
```

**Bobot 2 (POSITIF):**
```
bagus, baik, mantap, keren, suka, senang, puas, great, good, nice,
wonderful, memuaskan, kepuasan, enjoy, fun, enak, lezat, nyaman,
mudah, simple, praktis, berhasil, sukses, menang, profit, untung,
benefit, helpful, useful, effective, efficient, fast, cepat, lancar,
smooth, aman, safe, secure, amanah, trustworthy
```

**Bobot 1 (LEMAH POSITIF):**
```
ok, okay, fine, alright, bisa, boleh, mungkin, hampir, lumayan,
cukup, decent, acceptable, satisfied, satisfaction
```

**Source:** [LabellingController.php](app/Http/Controllers/LabellingController.php#L388)

---

### **Negative Keywords (Bobot: 3, 2, 1)**

**Bobot 3 (SANGAT NEGATIF):**
```
sangat buruk, sangat jelek, sangat kecewa, sangat menyesal, sangat gagal,
terburuk, terlalu buruk, terlalu jelek, benar-benar buruk, benar-benar jelek,
terrible, awful, horrible, disgusting, hate, hated, hating, worst,
sucks, sucked, disappointed, disappointing
```

**Bobot 2 (NEGATIF):**
```
buruk, jelek, gagal, fail, failed, failing, error, salah, wrong,
rusak, broken, menyesal, kecewa, regret, boring, membosankan,
ribet, sulit, difficult, hard, complicated, complex, confusing,
mahal, expensive, overpriced, rugi, loss, kerugian, waste,
lambat, slow, delay, terlambat, late, menunggu, waiting,
ganggu, disturb, disturbing, annoying, frustrating, frustrated,
penipuan, fraud, scam, scamming, cheat, cheating, fake
```

**Bobot 1 (LEMAH NEGATIF):**
```
tidak bagus, tidak baik, tidak suka, tidak senang, tidak puas,
gak bagus, ga bagus, nggak bagus, enggak bagus, tdk bagus,
bukan bagus, bukan baik, bukan suka, bukan senang, bukan puas,
biasa, mediocre, average, standar, normal, so-so, meh
```

**Source:** [LabellingController.php](app/Http/Controllers/LabellingController.php#L416)

---

## CONFIDENCE FACTORS QUICK REFERENCE

### **Factor Mapping**

| # | Factor | Formula | Line |
|---|--------|---------|------|
| 1 | Keyword Strength | `(strong×0.3) + (medium×0.2) + (weak×0.1)` | 617 |
| 2 | Text Length | `+0.1 if len > 50` | 605-607 |
| 3 | Word Count | `+0.05 if words > 10` | 608-610 |
| 4 | Intensifier | `+0.15 if "sangat"/"very"/etc` | 612-627 |
| 5 | Negation | `-0.1 if "tidak"/"gak"/etc` | 629-638 |
| 6 | Contradiction | `-0.1 × opposite_count` | 640-659 |
| 7 | Emoji | `+0.05 if emoji found` | 661-664 |
| 8 | Exclamation | `+0.03 if "!" found` | 667-669 |

**Final Step:** `max(0.1, min(0.95, total))` [Line 675-676]

---

## WORKFLOW DIAGRAM

### **Main Process Flow**

```
┌─────────────────────────────────────────────────────────┐
│ 1. USER UPLOAD CSV                                      │
│    POST /labelling/upload                               │
│    - Validate file                                      │
│    - Store to storage/app/labelling/                    │
│    - Read 200 first rows (preview)                      │
│    - Filter columns (score, time, at)                   │
│    - Save to session                                    │
└─────────────────────────────────────────────────────────┘
                          ↓
┌─────────────────────────────────────────────────────────┐
│ 2. SYSTEM READY FOR LABELLING                           │
│    Frontend show CSV preview                            │
│    User click "RUN LABELLING" button                    │
└─────────────────────────────────────────────────────────┘
                          ↓
┌─────────────────────────────────────────────────────────┐
│ 3. AUTO-LABEL ALL ROWS                                  │
│    POST /labelling/run                                  │
│                                                          │
│    For each row in CSV:                                 │
│    ├─ Extract text content                              │
│    ├─ Call autoLabelSentiment($text)                    │
│    │   └─ Check keywords, bobot, negation, intensifier  │
│    │   └─ Compare positve vs negative score             │
│    │   └─ Return: "positif"/"negatif"/"netral"         │
│    │                                                     │
│    ├─ Call calculateConfidence($text, $sentiment)       │
│    │   └─ 8 factors → weighted score                    │
│    │   └─ Normalize to 0.1-0.95 range                   │
│    │   └─ Return: float confidence                      │
│    │                                                     │
│    └─ Save row: [raw_text, sentiment, confidence]       │
│                                                          │
│    Simpan semua data:                                   │
│    - JSON temp file                                     │
│    - Session (first 100 rows + metadata)                │
└─────────────────────────────────────────────────────────┘
                          ↓
┌─────────────────────────────────────────────────────────┐
│ 4. DISPLAY RESULTS WITH PAGINATION                      │
│    Frontend show labeled data                           │
│    - Table 100 rows per page                            │
│    - Each row: [text, sentiment, confidence]            │
│    - User dapat navigate pages                          │
│    - User dapat klik dropdown untuk edit label          │
└─────────────────────────────────────────────────────────┘
                          ↓
┌─────────────────────────────────────────────────────────┐
│ 5. MANUAL CORRECTION (Optional)                         │
│    POST /labelling/update-label OR /labelling/bulk-update│
│    - User edit sentiment dropdownnya                    │
│    - System update JSON + session                       │
│    - Set confidence = 1.0 (user is trusted)             │
│    - Learn from correction                              │
└─────────────────────────────────────────────────────────┘
                          ↓
┌─────────────────────────────────────────────────────────┐
│ 6. EXPORT RESULTS                                       │
│    GET /labelling/download                              │
│    - Read full JSON temp file                           │
│    - Stream as CSV                                      │
│    - File: labelling_YYYYMMDD_HHMMSS.csv               │
│    - Columns: [raw, sentiment, confidence]              │
└─────────────────────────────────────────────────────────┘
                          ↓
┌─────────────────────────────────────────────────────────┐
│ 7. CLEANUP                                              │
│    POST /labelling/cleanup                              │
│    - Delete temp JSON file                              │
│    - Clear session variables                            │
│    - Ready untuk upload file baru                       │
└─────────────────────────────────────────────────────────┘
```

---

## SENTIMENT ALGORITHM FLOWCHART

```
START
  ↓
INPUT: text = "Sangat bagus tapi agak lambat"
  ↓
NORMALIZE: text = "sangat bagus tapi agak lambat"
  ↓
CHECK POSITIVE KEYWORDS
├─ "sangat bagus" found → positiveScore += 3
└─ No more matches
  → positiveScore = 3
  ↓
CHECK NEGATIVE KEYWORDS
├─ "lambat" found → negativeScore += 2
└─ No more matches
  → negativeScore = 2
  ↓
CHECK NEGATION ("tidak", "gak", etc)
├─ Found? NO
  ↓
CHECK INTENSIFIER ("sangat", "very", etc)
├─ Found "sangat" → hasIntensifier = true
├─ positiveScore (3) > negativeScore (2)?
└─ YES → positiveScore += 1 → 4
  ↓
FINAL COMPARISON
├─ positiveScore (4) > negativeScore (2) and > neutralScore (0)?
├─ YES
  ↓
RETURN: "positif"
  ↓
END
```

---

## CONFIDENCE CALCULATION FLOWCHART

```
START
  ↓
INPUT: text = "Sangat bagus tapi agak lambat!", sentiment = "positif"
  ↓
FACTOR 1: KEYWORD STRENGTH
├─ strongCount = 1 ("sangat bagus")
├─ mediumCount = 0
├─ weakCount = 0
└─ confidence += (1×0.3) + (0×0.2) + (0×0.1) = 0.3
  ↓
FACTOR 2: TEXT CHARACTERISTICS
├─ textLength = 36 > 50? NO
├─ wordCount = 7 > 10? NO
├─ No changes
└─ confidence = 0.3
  ↓
FACTOR 3: INTENSIFIER
├─ Has "sangat"? YES
├─ positiveScore > negativeScore? YES
└─ confidence += 0.15 → 0.45
  ↓
FACTOR 4: NEGATION
├─ Has negation? NO
└─ confidence = 0.45
  ↓
FACTOR 5: CONTRADICTION
├─ Opposite sentiment ("negatif") keywords?
├─ Found "lambat" → oppositeCount = 1
└─ confidence -= 0.1 → 0.35
  ↓
FACTOR 6: EMOJI & PUNCTUATION
├─ Emoji count = 0
├─ Exclamation count = 1
└─ confidence += 0.03 → 0.38
  ↓
NORMALIZE
├─ confidence = max(0.1, min(0.95, 0.38))
└─ confidence = 0.38
  ↓
RETURN: 0.38 (confidence)
  ↓
END
```

---

## FILE LOCATION QUICK REFERENCE

### **Main Controller**
```
📁 app/Http/Controllers/
  📄 LabellingController.php
     ├─ index() [11]
     ├─ upload() [34]
     ├─ run() [56]  ← MAIN PROCESS
     ├─ getPage() [122]
     ├─ updateLabel() [162]
     ├─ bulkUpdate() [175]
     ├─ download() [217]
     ├─ cleanup() [249]
     ├─ readCsv() [264]
     ├─ filterColumns() [287]
     ├─ detectTweetColumnIndex() [326]
     ├─ autoLabelSentiment() [380]  ← SENTIMENT LOGIC
     ├─ calculateConfidence() [520]  ← CONFIDENCE LOGIC
     ├─ getLearnedKeywords() [675]
     ├─ saveLearnedKeywords() [681]
     └─ learnFromCorrections() [685]
```

### **Frontend**
```
📁 resources/views/
  📄 labelling.blade.php
     ├─ Upload form
     ├─ CSV preview
     ├─ Results table
     ├─ Pagination
     ├─ Edit controls
     └─ Download button
```

### **Routes**
```
📁 routes/
  📄 web.php
     ├─ GET /labelling
     ├─ POST /labelling/upload
     ├─ POST /labelling/run
     ├─ GET /labelling/getpage
     ├─ POST /labelling/update-label
     ├─ POST /labelling/bulk-update
     ├─ GET /labelling/download
     └─ POST /labelling/cleanup
```

### **Storage**
```
📁 storage/app/
  📁 labelling/          ← Uploaded CSV files
     ├─ 20240110_093045_tweets.csv
     └─ ...
  📁 temp/              ← Working JSON files
     ├─ temp_labeling_507ae8c4.json
     └─ ...
```

---

## ERROR HANDLING QUICK REFERENCE

| Kondisi | Handler | Return |
|---------|---------|--------|
| File tidak valid | upload validation | error message |
| CSV tidak diupload | run() | error message |
| Row index invalid | updateLabel | error message |
| Halaman tidak valid | getPage | error message |
| Temp file missing | download | error message |

**All errors:** Redirect with `->with('error', 'Pesan')` [Laravel session]

---

## PERFORMANCE NOTES

### **Time Complexity**
```
Upload CSV:     O(n) | n = rows to preview (max 200)
Run labelling:  O(n×m) | n = total rows, m = keyword count (~100)
Update label:   O(1) | direct array access
Download:       O(n) | n = total rows (streaming, memory safe)
```

### **Space Complexity**
```
Session max:    ~5MB
Temp file:      Proportional to result size (scale OK up to 1M rows)
```

### **Tested & Safe For**
- ✓ 100K rows (small project)
- ✓ 1M rows (medium project)
- ⚠ 10M+ rows (consider database migration)

---

## DECISION TREE: DEBUG FLOW

```
Issue: Sentiment tidak akurat?
├─ Check keyword list? [LabellingController.php:388-430]
├─ Check bobot definition? [LabellingController.php:445-475]
├─ Test dengan simple text? "bagus" should = positif
└─ Check negation/intensifier logic? [LabellingController.php:468-495]

Issue: Confidence terlalu rendah/tinggi?
├─ Check Factor 1 (keyword strength) [Line 617]
├─ Check Factor 2 (text length) [Line 605-610]
├─ Check Factor 4 (contradiction) [Line 640-659]
└─ Adjust thresholds? [Line 675-676]

Issue: Data tidak tersimpan?
├─ Check temp file created? [Line 107, 289]
├─ Check session limit? [Line 117]
├─ Check file permissions? [storage/app/temp/]
└─ Check cleanup? [Line 249-262]

Issue: User edit tidak ter-apply?
├─ Check row_index valid? [Line 163]
├─ Check bulkUpdate globalIndex? [Line 204]
├─ Check JSON file updated? [Line 211]
└─ Check session refreshed? [Line 214]
```

---

## ONE-LINER EXPLANATIONS

| Aspek | Penjelasan Singkat |
|-------|-------------------|
| **Sentiment** | Count keyword matches dengan bobot, bandingkan skor |
| **Confidence** | 8 factors → weighted score (0.1-0.95) |
| **Learning** | Extract kata dari manual correction → tambah keyword list |
| **Pagination** | Baca all data dari JSON, slice 100 rows per page |
| **Download** | Stream CSV dari temp JSON (memory safe) |
| **Session** | Store state (file references, first page data, keywords) |
| **Bobot** | Keyword strength level: 3 (strong), 2 (medium), 1 (weak) |
