# 3. VECTORIZATION & PREPROCESSING DETAIL

## Text Preprocessing Pipeline

### Tujuan
Mengubah text mentah menjadi format yang konsisten & siap untuk vectorization

### Pipeline

```
Raw Text
    ↓
[1] LOWERCASE
    ↓
[2] REMOVE SPECIAL CHARS & EMOJI
    ↓
[3] NORMALIZE WHITESPACE
    ↓
[4] TRIM
    ↓
Cleaned Text
```

### Step 1: Lowercase Conversion

**Fungsi:** Normalisasi huruf besar & kecil

```php
$text = mb_strtolower($text, 'UTF-8');
```

**Contoh:**
```
Input:  "PRODUK Bagus!!! Umum"
Output: "produk bagus!!! umum"
```

**Alasan:**
- "BAGUS" = "bagus" → treat sama
- Supaya vocabulary tidak duplicate: [BAGUS, Bagus, bagus] → [bagus]

---

### Step 2: Remove Special Characters & Emoji

**Fungsi:** Hilangkan simbol, emoji, angka, karakter non-letter

```php
$text = preg_replace('/[^\p{L}\s]/u', ' ', $text);
```

**Regex Breakdown:**
```
/[^\p{L}\s]/u

[^...]  = NOT (negation)
\p{L}   = Unicode letter category (a-z, A-Z, ä, é, ñ, dll)
\s      = whitespace (space, tab, newline)
/u      = Unicode flag

Effect: Keep ONLY letters & whitespace, replace everything else with space
```

**Contoh:**
```
Input:  "produk bagus!!! 😊 Puas #amanat123"
Output: "produk bagus    puas  amanat   "
         (symbols & emoji replaced with space)
```

**Alasan:**
- Emoji & special chars tidak carry sentiment info
- Bisa jadi noise dalam vectorization
- "amanat123" → "amanat" (lebih clean)

---

### Step 3: Normalize Whitespace

**Fungsi:** Konsolidasikan multiple spaces ke single space

```php
$text = preg_replace('/\s+/u', ' ', $text);
```

**Regex Breakdown:**
```
/\s+/u

\s+  = 1 atau lebih whitespace characters
/u   = Unicode flag

Effect: Replace consecutive whitespace dengan single space
```

**Contoh:**
```
Input:  "produk bagus    puas  amanat   "
Output: "produk bagus puas amanat "
        (multiple spaces → single space)
```

**Alasan:**
- Step 2 create multiple spaces
- Multiple spaces tidak meaningful
- Standardize spacing untuk consistency

---

### Step 4: Trim

**Fungsi:** Remove leading/trailing whitespace

```php
$text = trim($text);
```

**Contoh:**
```
Input:  " produk bagus puas amanat "
Output: "produk bagus puas amanat"
```

### Full Example

```
Raw:       "😍 PRODUK BAGUS!!!  Puas sekali #amazing 👍"
└─ Step 1: "😍 produk bagus!!!  puas sekali #amazing 👍"
└─ Step 2: "   produk bagus       puas sekali  amazing  "
└─ Step 3: " produk bagus puas sekali amazing "
└─ Step 4: "produk bagus puas sekali amazing"
Cleaned:   "produk bagus puas sekali amazing"
```

### Kode Lengkap

```php
private function preprocessText(string $text): string
{
    // Step 1: Convert to lowercase (UTF-8 aware)
    $text = mb_strtolower($text, 'UTF-8');
    
    // Step 2: Remove all non-letter & non-whitespace characters
    $text = preg_replace('/[^\p{L}\s]/u', ' ', $text);
    
    // Step 3: Normalize multiple whitespace to single space
    $text = preg_replace('/\s+/u', ' ', $text);
    
    // Step 4: Trim edges
    return trim($text);
}
```

---

## Vocabulary Building & Filtering

### Tujuan
Mengidentifikasi words yang relevan untuk classification, hilangkan noise

### Process

**Input:** Semua preprocessed training texts

```
Text 1: "produk bagus puas"
Text 2: "jelek tidak bagus"
Text 3: "cukup lumayan baik"
...
Text N: "bagus sekali"
```

**Step 1: Count Word Frequencies**

```php
$wordCounts = [];
foreach ($trainingData as $data) {
    $words = explode(' ', $data['text']);
    foreach ($words as $word) {
        if (strlen($word) > 2) {  // Filter 1: min length 3
            $wordCounts[$word] = ($wordCounts[$word] ?? 0) + 1;
        }
    }
}
```

**Result:**
```
wordCounts = {
  "produk": 25,
  "bagus": 145,
  "puas": 38,
  "jelek": 8,
  "cukup": 3,
  "lumayan": 12,
  "baik": 67,
  "tidak": 4,
  ...
}
```

**Step 2: Filter by Minimum Frequency**

```php
$vocabulary = array_keys(
    array_filter($wordCounts, fn($count) => $count >= 2)
);
```

**Keputusan Filtering:**
```
wordCounts >= 2?

"produk": 25   → ✓ Keep
"bagus": 145   → ✓ Keep
"puas": 38     → ✓ Keep
"jelek": 8     → ✓ Keep
"cukup": 3     → ✓ Keep
"lumayan": 12  → ✓ Keep
"baik": 67     → ✓ Keep
"tidak": 4     → ✓ Keep
"aneh": 1      → ✗ Remove (rare)
"unik": 1      → ✗ Remove (rare)
```

**Final Vocabulary:**
```php
vocabulary = [
  0 => "produk",
  1 => "bagus",
  2 => "puas",
  3 => "jelek",
  4 => "cukup",
  5 => "lumayan",
  6 => "baik",
  7 => "tidak",
  ...
] // ~2000-5000 words
```

### Why Filter by Minimum Frequency?

1. **Rare words ≠ good features**
   - "aneh" appears 1x → might be typo
   - Not enough data untuk reliable classification

2. **Reduce noise**
   - Typos: "baag", "bagu", "baguus" → different words, same meaning
   - Misspellings create false features

3. **Computational efficiency**
   - 10,000 words → larger vectors → slower similarity calc
   - 2,000 words → smaller vectors → 25x faster

4. **Memory efficiency**
   - Smaller vocabulary → smaller vectors in memory
   - Important untuk batch processing

### Vocabulary Size Impact

| Vocab Size | Pros | Cons |
|------------|------|------|
| 1,000 | ✅ Fast, ✅ Small memory | ❌ Loss of info |
| 5,000 | ✅ Good balance | ✅ Normal speed |
| 10,000 | ❌ Slow, ❌ Memory heavy | ✅ More detail |

Our system: **~3,000-5,000** words (balanced approach)

---

## TF (Term Frequency) Vectorization

### Tujuan
Convert text tokens → numeric vector untuk similarity calculation

### Konsep

```
Text: "bagus puas bagus"
Vocabulary: ["produk", "bagus", "puas", "jelek", ...]
             [  0    ,   1   ,   2  ,   3    ]

Vector:  [0, 2, 1, 0, ...]
         ↑  ↑  ↑  ↑
         |  |  |  +-- "jelek": 0x
         |  |  +------ "puas": 1x
         |  +--------- "bagus": 2x
         +------------ "produk": 0x
```

### Algorithm

```php
$vocabularyMap = array_flip($vocabulary);
// Convert: [word1, word2, ...] → [word1=>0, word2=>1, ...]

$vector = array_fill(0, count($vocabulary), 0);
// Initialize: [0, 0, 0, 0, ..., 0]

$words = explode(' ', $text);
// Split text into words

foreach ($words as $word) {
    if (isset($vocabularyMap[$word])) {
        $vector[$vocabularyMap[$word]]++;
    }
}
// Count word occurrences
```

### Step-by-Step Example

```
vocabulary = ["bagus", "puas", "jelek"]
vocabularyMap = ["bagus"=>0, "puas"=>1, "jelek"=>2]

text = "bagus puas bagus"
words = ["bagus", "puas", "bagus"]

Initial vector: [0, 0, 0]

Process word 1: "bagus"
  → index = vocabularyMap["bagus"] = 0
  → vector[0]++ → [1, 0, 0]

Process word 2: "puas"
  → index = vocabularyMap["puas"] = 1
  → vector[1]++ → [1, 1, 0]

Process word 3: "bagus"
  → index = vocabularyMap["bagus"] = 0
  → vector[0]++ → [2, 1, 0]

Result vector: [2, 1, 0]
```

### Multiple Texts Vectorization

```
Vocabulary: ["bagus", "puas", "jelek", "cukup"]

Text 1: "bagus puas"
Vector 1: [1, 1, 0, 0]

Text 2: "jelek cukup"
Vector 2: [0, 0, 1, 1]

Text 3: "bagus bagus puas puas"
Vector 3: [2, 2, 0, 0]

Result: 2D Array
[
  [1, 1, 0, 0],
  [0, 0, 1, 1],
  [2, 2, 0, 0],
  ...
]
```

---

## Optimization: O(1) vs O(n) Lookup

### The Problem

```
naive approach with array_search:

FOR each test vector:
  FOR each word in text:
    index = array_search(word, vocabulary)  // ← O(n) operation
    vector[index]++
```

**Complexity Analysis:**
```
Scenario: 100 test samples × 100 words average × 5000 vocab size

Naive approach:
= 100 × 100 × 5000 = 50,000,000 comparisons
= Each comparison: string comparison cost
= Total: ~5-30 seconds

Optimized approach:
= 100 × 100 × (hash lookup ≈ O(1)) = 10,000 hash operations
= Each operation: instant (hash table)
= Total: ~10-50 milliseconds

Speed improvement: 100x to 3000x faster ✅
```

### The Solution

```php
// Create hash map (O(1) for lookups)
$vocabularyMap = array_flip($vocabulary);

// Example:
// vocabulary   = ["bagus", "puas", "jelek"]
// vocabularyMap = ["bagus"=>0, "puas"=>1, "jelek"=>2]

// Usage:
if (isset($vocabularyMap[$word])) {  // O(1) lookup
    $vector[$vocabularyMap[$word]]++;
}
```

### Performance Comparison

| Operation | Complexity | Time (10k words) |
|-----------|-----------|-----------------|
| array_search() | O(n) | ~100ms |
| isset() on hash | O(1) | <1ms |
| Speedup | 100x | **100x faster** |

### Kode Lengkap (Optimized)

```php
private function vectorizeData(array $data, array $vocabulary): array
{
    // Create word-to-index mapping for O(1) lookup
    $vocabularyMap = array_flip($vocabulary);
    $vocabCount = count($vocabulary);
    
    $vectors = [];
    foreach ($data as $item) {
        // Initialize vector with zeros
        $vector = array_fill(0, $vocabCount, 0);
        
        // Split text into words
        $words = explode(' ', $item['text']);
        
        // Count word frequencies
        foreach ($words as $word) {
            // O(1) lookup using hash table
            if (isset($vocabularyMap[$word])) {
                $index = $vocabularyMap[$word];
                $vector[$index]++;
            }
        }
        
        $vectors[] = $vector;
    }
    
    return $vectors;
}
```

---

## Summary Pipeline

```
Raw Texts
    ↓
[PREPROCESS]
├─ lowercase
├─ remove special chars
├─ normalize whitespace
├─ trim
    ↓
Cleaned Texts
    ↓
[BUILD VOCABULARY]
├─ count word frequencies
├─ filter: length > 2
├─ filter: frequency >= 2
    ↓
Vocabulary (2-5k words)
    ↓
[VECTORIZE]
├─ create vocabularyMap (array_flip) for O(1) lookup
├─ for each text:
│  └─ count word occurrences → TF vector
├─ result: 2D array of vectors
    ↓
TF Vectors (ready for similarity calc)
```

**Performance Summary:**
- Preprocessing: O(n) where n = total chars
- Vocabulary building: O(n×m) where m = avg words
- Vectorization: O(n×m) with O(1) lookup (after optimization)

