# 3. CONFIDENCE CALCULATION (`calculateConfidence`)

**File:** `app/Http/Controllers/LabellingController.php`  
**Baris:** 520-670

---

## Tujuan

Menghitung **tingkat kepercayaan** otomatis labelling pada skala 0.1 hingga 0.95 (bukan 1.0 karena itu untuk manual).

**Semakin tinggi confidence:**
- Sentiment terusan yakin, bisa langsung pakai
- Semakin mungkin minimal error prediksi

**Semakin rendah confidence:**
- Ambiguous/unclear, user sebaiknya cek manual
- Mungkin ada multiple sentiment dalam 1 kalimat

---

## Struktur Perhitungan (5 Faktor)

```
Confidence = 
    (Factor 1: Keyword Strength) 
    + (Factor 2: Text Characteristics)
    + (Factor 3: Intensifiers/Negation)
    + (Factor 4: Contradictory Sentiment)
    + (Factor 5: Emoji & Punctuation)
```

**Range Akhir:** 0.1 (sangat tidak yakin) sampai 0.95 (sangat yakin)

---

## FACTOR 1: KEYWORD STRENGTH (Line 537-596)

### **Definisi 3 Level Keyword**

**Strong Keywords (Weight = 0.3):**
```php
'sangat bagus', 'sangat baik', 'terbaik', 'perfect', 'awesome', 'brilliant',
'excellent', 'love', 'recommended', 'highly recommend'  // POSITIF

'sangat buruk', 'sangat jelek', 'terburuk', 'terrible', 'awful', 'horrible',
'disgusting', 'hate', 'worst', 'sucks'  // NEGATIF
```
**Baris:** 538-541, 544-547

**Medium Keywords (Weight = 0.2):**
```php
'bagus', 'baik', 'mantap', 'suka', 'senang', 'puas', 'great', 'good',
'nice', 'wonderful', 'enjoy', 'enak', 'lezat', 'praktis', 'berhasil'  // POSITIF
```
**Baris:** 548-551

**Weak Keywords (Weight = 0.1):**
```php
'ok', 'okay', 'fine', 'alright', 'bisa', 'lumayan', 'cukup', 'decent'  // POSITIF
```
**Baris:** 552-554

### **Penghitungan Score**

```php
$strongCount = 0;
$mediumCount = 0;
$weakCount = 0;

// Count berapa banyak keyword kuat ditemukan
foreach ($strongKeywords[$sentiment] as $keyword) {
    if (str_contains($text, $keyword)) {
        $strongCount++;  // Increment kalau ada match
    }
}

// Sama untuk medium & weak keywords
...

// Calculate base confidence
$confidence += ($strongCount * 0.3) + ($mediumCount * 0.2) + ($weakCount * 0.1);
```

**Baris:** 556-596, 617

**Contoh:**
- Text: "Sangat bagus sekali"
- `strongCount = 1` (ada "sangat bagus") → +0.3
- `mediumCount = 0`
- `weakCount = 0`
- Subtotal confidence = **0.3**

---

## FACTOR 2: TEXT CHARACTERISTICS (Line 598-608)

### **Text Length (>50 chars)**
```php
if ($textLength > 50) {
    $confidence += 0.1;  // Teks panjang = lebih detail = lebih yakin
}
```
**Baris:** 605-607

**Alasan:** Teks panjang biasanya punya lebih banyak context → lebih mudah ditentukan sentimentnya

### **Word Count (>10 words)**
```php
if ($wordCount > 10) {
    $confidence += 0.05;  // Lebih banyak kata = lebih detail
}
```
**Baris:** 608-610

---

## FACTOR 3: INTENSIFIERS & NEGATION (Line 612-630)

### **Intensifier Detection**
```php
$intensifiers = ['sangat', 'banget', 'sekali', 'really', 'very', 'so', 'extremely'];
$hasIntensifier = false;

foreach ($intensifiers as $int) {
    if (str_contains($text, $int)) {
        $hasIntensifier = true;
        break;
    }
}

if ($hasIntensifier) {
    $confidence += 0.15;  // Intensifier = user lebih jelas express sentiment
}
```
**Baris:** 612-627

**Alasan:** "Sangat bagus" lebih jelas daripada "bagus" → confidence lebih tinggi

### **Negation Detection**
```php
$negationWords = ['tidak', 'gak', 'ga', 'no', 'not', 'never'];
$hasNegation = false;

foreach ($negationWords as $neg) {
    if (str_contains($text, $neg)) {
        $hasNegation = true;
        break;
    }
}

if ($hasNegation) {
    $confidence -= 0.1;  // Negasi = lebih ambiguous
}
```
**Baris:** 629-638

**Alasan:** "Tidak bagus" bisa negatif atau sarcasm → kurangi confidence

---

## FACTOR 4: CONTRADICTORY SENTIMENT (Line 640-659)

**Tujuan:** Deteksi kalau 1 teks punya sentiment berlawanan

```php
// Jika sentiment = "positif", opposite = "negatif"
$oppositeSentiment = $sentiment === 'positif' ? 'negatif' : 'positif';
$oppositeCount = 0;

// Hitung ada berapa keyword dari sentiment berlawanan
foreach ($strongKeywords[$oppositeSentiment] as $keyword) {
    if (str_contains($text, $keyword)) {
        $oppositeCount++;
    }
}

// Kurangi confidence untuk setiap keyword berlawanan ditemukan
if ($oppositeCount > 0) {
    $confidence -= ($oppositeCount * 0.1);
}
```

**Baris:** 640-659

**Contoh:**
- Text: "Bagus tapi agak lambat"
- Sentiment = "positif" (dari "bagus")
- Ada keyword negatif "lambat" → oppositeCount = 1
- Confidence -= 0.1 (kuran due to contradiction)

---

## FACTOR 5: EMOJI & PUNCTUATION (Line 661-673)

### **Emoji Detection**
```php
$emojiCount = preg_match_all(
    '/[\x{1F600}-\x{1F64F}]|[\x{1F300}-\x{1F5FF}]|.../u',
    $text
);

if ($emojiCount > 0) {
    $confidence += 0.05;  // Emoji reinforce sentiment
}
```
**Baris:** 661-664

**Contoh:** "Bagus! 😍" → konfirmasi sentiment dengan emoji

### **Punctuation (Exclamation)**
```php
$exclamationCount = substr_count($text, '!');

if ($exclamationCount > 0) {
    $confidence += 0.03;  // ! = emphasis pada sentiment
}
```
**Baris:** 667-669

---

## NORMALISASI RANGE (Line 675-676)

```php
$confidence = max(0.1, min(0.95, $confidence));
return $confidence;
```

**Baris:** 675-677

**Penjelasan:**
- `max(0.1, ...)` = minimal confidence 0.1 (jangan 0)
- `min(..., 0.95)` = maksimal confidence 0.95 (0.96+ jarang karena punya ambiguity)
- Kalau calculation hasil negatif, set jadi 0.1
- Kalau hasil > 0.95, clip jadi 0.95

**Alasan:** 1.0 reserved untuk **manual labeling** (user pasti tahu)

---

## CONTOH PERHITUNGAN LENGKAP

**Input:**
```
Text: "Produk ini sangat bagus dan enak! Saya sangat suka! 😍😍"
Sentiment: "positif"
```

**Calculation Step-by-step:**

### **1. Keyword Strength (Factor 1)**
```
- "sangat bagus" → strong keyword → strongCount = 1
- "enak" → medium keyword → mediumCount = 1
- "suka" → medium keyword → mediumCount = 2
- "sangat suka" → strong? No, counted as medium "suka"

confidence += (1 * 0.3) + (2 * 0.2) + (0 * 0.1) = 0.7
Current: 0.7
```

### **2. Text Characteristics (Factor 2)**
```
- textLength = 52 char > 50 → confidence += 0.1
- wordCount = 11 words > 10 → confidence += 0.05

Current: 0.7 + 0.1 + 0.05 = 0.85
```

### **3. Intensifiers & Negation (Factor 3)**
```
- Ada "sangat" (intensifier) → confidence += 0.15
- Tidak ada negasi → confidence -= 0

Current: 0.85 + 0.15 = 1.0
```

### **4. Contradictory Sentiment (Factor 4)**
```
- Sentiment = "positif"
- Opposite = "negatif"
- Cari keyword negatif dalam text → tidak ada

Current: 1.0
```

### **5. Emoji & Punctuation (Factor 5)**
```
- emojiCount = 2 (😍😍) → confidence += 0.05
- exclamationCount = 2 → confidence += 0.03

Current: 1.0 + 0.05 + 0.03 = 1.08
```

### **6. Normalisasi**
```
max(0.1, min(0.95, 1.08)) = 0.95  ← dikap ke 0.95

FINAL CONFIDENCE = 0.95
```

---

## PERBANDINGAN 3 TEXT

| Text | Keyword | Char | Intensif | Contra | Emoji | Confidence | Catatan |
|------|---------|------|----------|--------|-------|------------|---------|
| "Bagus" | 0.2 | 0 | 0 | 0 | 0 | 0.2 | Minimal keyword |
| "Sangat bagus!" | 0.3 | 0 | 0.15 | 0 | 0.03 | 0.48 | Cukup yakin |
| "Sangat bagus, enak, praktis! Suka banget" | 0.7 | 0.1 | 0.15 | 0 | 0 | 0.95 | Sangat yakin |
| "Bagus tapi agak lambat" | 0.3 | 0.1 | 0 | -0.1 | 0 | 0.3 | Ada kontradiksi |

---

## MANUAL LABELING = 1.0

**File:** Line 172, 210-211  
**Code:**
```php
$labeled['rows'][$request->row_index]['confidence'] = 1.0;  // Manual = 100% percaya
```

**Alasan:** User yang label, pasti dia tahu (trust user judgment)

---

## USE CASE DALAM APLIKASI

**Frontend Indicator:**
```
Confidence 0.90+ → Green ✓ (trust auto-label)
Confidence 0.50-0.89 → Yellow ⚠ (review manual)
Confidence < 0.50 → Red ✗ (MUST review)
```

**Sorting/Filtering:**
- User bisa sort by confidence (rendah duluan → review manual)
- User bisa filter "confidence < 0.6" → show hanya yang perlu cek
