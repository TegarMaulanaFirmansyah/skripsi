# 2. AUTO SENTIMENT DETECTION (`autoLabelSentiment`)

**File:** `app/Http/Controllers/LabellingController.php`  
**Baris:** 380-485

---

## Logika Dasar

Sistem mencari **kata-kata kunci** dalam teks dengan **bobot berbeda**, kemudian membandingkan skor positif vs negatif.

```php
// Pseudocode (Line 380-382)
private function autoLabelSentiment(Request $request, string $text): string {
    // ... (kode sebenarnya ada di bawah)
}
```

---

## 1. INISIALISASI TEXT (Line 383-384)

```php
$text = mb_strtolower($text, 'UTF-8');  // Ubah ke lowercase & handle karakter special
$learnedKeywords = $this->getLearnedKeywords($request);  // Load keywords dari user corrections
```

**Fungsi:** Normalisasi teks → semua huruf kecil → ready untuk matching

---

## 2. DEFINISI KEYWORD LIST (Line 388-415)

### **POSITIVE KEYWORDS - 3 LEVEL**

```php
$positiveKeywords = array_merge([
    // ========== BOBOT 3 (SANGAT POSITIF) ==========
    'sangat bagus', 'sangat baik', 'sangat puas', 'sangat senang',
    'terbaik', 'terlalu bagus', 'perfect', 'awesome', 'brilliant',
    'love', 'loved', 'recommended', 'highly recommend',
    
    // ========== BOBOT 2 (POSITIF) ==========
    'bagus', 'baik', 'mantap', 'keren', 'suka', 'senang', 'puas',
    'great', 'good', 'nice', 'wonderful', 'memuaskan', 'enjoy',
    'enak', 'lezat', 'nyaman', 'mudah', 'simple', 'praktis',
    'berhasil', 'sukses', 'aman', 'safe', 'secure', 'fast', 'cepat',
    
    // ========== BOBOT 1 (LEMAH POSITIF) ==========
    'ok', 'okay', 'fine', 'alright', 'bisa', 'boleh', 'lumayan',
    'cukup', 'decent', 'acceptable'
], $learnedKeywords['positive'] ?? []);
```

**Baris:** 388-415  
**Catatan:** User-learned keywords ditambahkan di akhir

---

## 3. HITUNG SKOR KEYWORD (Line 437-466)

### **Pseudo-code:**
```python
for setiap keyword di positiveKeywords:
    if keyword ada di teks:
        if keyword termasuk kategori BOBOT 3:
            positiveScore += 3
        elif keyword termasuk kategori BOBOT 2:
            positiveScore += 2
        else:
            positiveScore += 1
```

**Kode Lengkap (Line 443-466):**
```php
$positiveScore = 0;
$negativeScore = 0;
$neutralScore = 0;

// Check positive keywords
foreach ($positiveKeywords as $keyword) {
    if (str_contains($text, $keyword)) {
        if (str_contains($keyword, 'sangat ') || 
            str_contains($keyword, 'terlalu ') || 
            in_array($keyword, ['perfect', 'awesome', 'love', ...])) {
            $positiveScore += 3;  // BOBOT 3
        } elseif (in_array($keyword, ['bagus', 'baik', 'nice', 'great', ...])) {
            $positiveScore += 2;  // BOBOT 2
        } else {
            $positiveScore += 1;  // BOBOT 1
        }
    }
}

// Sama untuk negative & neutral
...
```

**Hasil:**
- Jika teks = "sangat bagus banget"
  - Keyword "sangat bagus" (bobot 3) → score = 3
  - Keyword "bagus" (bobot 2) → score = 2 lagi? NO, sudah tercakup
  - Total positiveScore = 3

---

## 4. CEK NEGASI (Line 468-475)

**Tujuan:** Jika ada "tidak" atau "gak", kurangi skor dominan

```php
$negationWords = ['tidak', 'gak', 'ga', 'nggak', 'no', 'not'];
$hasNegation = false;

foreach ($negationWords as $neg) {
    if (str_contains($text, $neg)) {
        $hasNegation = true;
        break;
    }
}

// Jika ada negasi, kurangi skor dominan sebesar 1
if ($hasNegation) {
    if ($positiveScore > $negativeScore) {
        $positiveScore = max(0, $positiveScore - 1);
    } elseif ($negativeScore > $positiveScore) {
        $negativeScore = max(0, $negativeScore - 1);
    }
}
```

**Baris:** 468-478  
**Contoh:**
- Teks: "Tidak bagus" (negatif tapi ada 'tidak')
- Original: negativeScore = 2, positiveScore = 0
- Setelah negasi: negativeScore -= 1 → 1
- Jadi lebih netral

---

## 5. CEKINTENSIFIER (Line 480-495)

**Tujuan:** Jika ada "sangat", "very", "extremely", tambah skor dominan

```php
$intensifiers = ['sangat', 'banget', 'really', 'very', 'so', 'extremely', 'highly'];
$hasIntensifier = false;

foreach ($intensifiers as $int) {
    if (str_contains($text, $int)) {
        $hasIntensifier = true;
        break;
    }
}

if ($hasIntensifier) {
    if ($positiveScore > $negativeScore) {
        $positiveScore += 1;  // Boost positif
    } elseif ($negativeScore > $positiveScore) {
        $negativeScore += 1;  // Boost negatif
    }
}
```

**Baris:** 480-495  
**Contoh:**
- Teks: "Sangat bagus"
- Sebelum intensifier: positiveScore = 2 (dari "bagus")
- Ada "sangat" → positiveScore += 1 → 3

---

## 6. TENTUKAN SENTIMENT (Line 497-505)

**Logika Akhir:**

```php
if ($positiveScore > $negativeScore && $positiveScore > $neutralScore) {
    return 'positif';
} elseif ($negativeScore > $positiveScore && $negativeScore > $neutralScore) {
    return 'negatif';
} else {
    return 'netral';
}
```

**Baris:** 499-505

**Tabel Keputusan:**
| positiveScore | negativeScore | neutralScore | HASIL |
|---------------|---------------|--------------|-------|
| 3 | 2 | 1 | **POSITIF** |
| 1 | 3 | 2 | **NEGATIF** |
| 2 | 2 | 1 | **NETRAL** (tie → netral) |
| 0 | 0 | 0 | **NETRAL** (no keyword) |

---

## CONTOH EKSEKUSI

**Input Text:** "Sangat bagus dan enak sekali!"

**Step-by-step:**

1. **Lowercase:** "sangat bagus dan enak sekali!"

2. **Hitung Skor:**
   - Keyword "sangat bagus" → positiveScore += 3
   - Keyword "bagus" → (sudah ada di "sangat bagus", skip atau count lagi?)
   - Keyword "enak" → positiveScore += 2
   - Keyword "sekali" → intensifier
   - negativeScore = 0

3. **Cek Negasi:** Tidak ada → skip

4. **Cek Intensifier:** Ada "sangat" + positiveScore > 0 → positiveScore += 1

5. **Final Skor:**
   ```
   positiveScore = 3 + 2 + 1 = 6
   negativeScore = 0
   ```

6. **Hasil:** 6 > 0 && 6 > 0 → **SENTIMEN = "POSITIF"**

---

## PEMBELAJARAN DARI MANUAL CORRECTION

**File:** `app/Http/Controllers/LabellingController.php`  
**Fungsi:** `learnFromCorrections()` - Line 687  
**Source:** `updateLabel()` & `bulkUpdate()` calls

Setiap kali user manually label, sistem **extract kata-kata** dari teks tersebut dan **tambahkan ke kata kunci list** untuk prediksi berikutnya.

```php
private function learnFromCorrections(Request $request, array $labeledRows): void {
    $learnedKeywords = ['positive' => [], 'negative' => [], 'neutral' => []];
    
    foreach ($labeledRows as $row) {
        $text = mb_strtolower($row['raw'], 'UTF-8');
        $sentiment = $row['sentiment'];  // 'positif', 'negatif', atau 'netral'
        
        $words = preg_split('/\s+/', $text);
        // Filter kata-kata pendek dan stopwords
        $words = array_filter($words, function($word) {
            return mb_strlen($word) > 2 && 
                   !in_array($word, ['yang', 'dan', 'di', 'ke', ...]);
        });
        
        foreach ($words as $word) {
            // Increment count untuk setiap kata dalam sentiment category
            if (!isset($learnedKeywords[$sentiment][$word])) {
                $learnedKeywords[$sentiment][$word] = 0;
            }
            $learnedKeywords[$sentiment][$word]++;
        }
    }
    
    $this->saveLearnedKeywords($request, $learnedKeywords);
}
```

**Baris:** 687-713

**Contoh:**
- User label "bagus banget" → POSITIF
- Sistem extract: ["bagus", "banget"]
- Tambah ke: `learnedKeywords['positive']['bagus']++`
- Nanti prediksi teks baru pakai keyword ini juga
