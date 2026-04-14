# 5. HELPER FUNCTIONS & STORAGE

**File:** `app/Http/Controllers/LabellingController.php`  
**Baris:** 264-380 (utilities)

---

## 1. READ CSV (`readCsv`)

**Baris:** 264-285

```php
private function readCsv(string $path, ?int $limit = 200): array {
    $handle = fopen($path, 'r');
    if ($handle === false) {
        return [[], []];
    }

    $header = null;
    $rows = [];
    $count = 0;

    while (($data = fgetcsv($handle)) !== false) {
        if ($header === null) {
            $header = $data;  // First row = header
            continue;
        }

        $rows[] = $data;
        $count++;

        // Stop kalo sudah reach limit
        if ($limit !== null && $count >= $limit) {
            break;
        }
    }

    fclose($handle);
    return [$header ?: [], $rows];
}
```

**Parameters:**
- `$path` → full file path
- `$limit` → max rows to read (null = read semua)

**Returns:**
- `[header, rows]` → array 2D

**Example:**
```php
// Read first 200 rows
[$header, $rows] = $this->readCsv('/path/to/file.csv', 200);
// Result: $header = ['tweet', 'id', 'date']
//         $rows = [['isi tweet 1', ...], ['isi tweet 2', ...], ...]

// Read all rows
[$header, allRows] = $this->readCsv('/path/to/file.csv', null);
```

---

## 2. FILTER COLUMNS (`filterColumns`)

**Baris:** 287-324

```php
private function filterColumns(
    array $header, 
    array $rows, 
    array $dropKeywords
): array {
    if (empty($header)) {
        return [$header, $rows];
    }

    // 1. Identify column indices to DROP
    $dropIndices = [];
    foreach ($header as $idx => $name) {
        $lower = strtolower(trim((string) $name));
        foreach ($dropKeywords as $kw) {
            // Match jika column name exactly = keyword atau contains keyword
            if ($lower === $kw || str_contains($lower, $kw)) {
                $dropIndices[$idx] = true;
                break;
            }
        }
    }

    // Jika tidak ada yang di-drop, return as-is
    if (empty($dropIndices)) {
        return [$header, $rows];
    }

    // 2. Build new header (exclude dropped columns)
    $newHeader = [];
    foreach ($header as $idx => $name) {
        if (!isset($dropIndices[$idx])) {
            $newHeader[] = $name;
        }
    }

    // 3. Build new rows (exclude dropped columns)
    $newRows = [];
    foreach ($rows as $row) {
        $newRow = [];
        foreach ($row as $idx => $val) {
            if (!isset($dropIndices[$idx])) {
                $newRow[] = $val;
            }
        }
        $newRows[] = $newRow;
    }

    return [$newHeader, $newRows];
}
```

**Parameters:**
- `$header` → array of column names
- `$rows` → array of data rows
- `$dropKeywords` → keywords to match (e.g., ['score', 'time', 'at'])

**Example:**
```php
$header = ['tweet', 'score', 'date', 'time', 'user_id'];
$rows = [
    ['isi1', 0.5, '2024-01-01', '10:00', 123],
    ['isi2', 0.8, '2024-01-02', '11:00', 124],
];

[$newHeader, $newRows] = $this->filterColumns($header, $rows, ['score', 'time']);

// Result:
// $newHeader = ['tweet', 'date', 'user_id']
// $newRows = [
//     ['isi1', '2024-01-01', 123],
//     ['isi2', '2024-01-02', 124],
// ]
```

---

## 3. DETECT TWEET COLUMN (`detectTweetColumnIndex`)

**Baris:** 326-345

```php
private function detectTweetColumnIndex(array $header): ?int {
    if (empty($header)) return null;

    // List of possible column names for tweet/text content
    $candidates = [
        'tweet', 'text', 'content', 'message', 'body', 'review', 'ulasan'
    ];

    // Check each header against candidates
    foreach ($header as $idx => $name) {
        $lower = strtolower(trim((string) $name));
        if (in_array($lower, $candidates, true)) {
            return $idx;  // Return index of matched column
        }
    }

    // Fallback: return last column index
    return count($header) ? count($header) - 1 : null;
}
```

**Parameters:**
- `$header` → column names

**Returns:**
- Index (int) of detected tweet column
- `null` jika tidak ada header

**Example:**
```php
$header = ['id', 'tweet', 'created_at'];
$index = $this->detectTweetColumnIndex($header);
// Result: 1 (index of 'tweet')

$header2 = ['id', 'content', 'user'];
$index2 = $this->detectTweetColumnIndex($header2);
// Result: 1 (index of 'content')

$header3 = ['id', 'data', 'other'];
$index3 = $this->detectTweetColumnIndex($header3);
// Result: 2 (fallback: last column)
```

---

## 4. GET LEARNED KEYWORDS (`getLearnedKeywords`)

**Baris:** 675-679

```php
private function getLearnedKeywords(Request $request): array {
    $learnedData = $request->session()->get('learned_keywords', []);
    return $learnedData;
}
```

**Returns:**
- Array dari user-learned keywords

**Format:**
```php
[
    'positive' => ['bagus', 'enak', ...],
    'negative' => ['buruk', 'lambat', ...],
    'neutral' => ['biasa', 'normal', ...]
]
```

---

## 5. SAVE LEARNED KEYWORDS (`saveLearnedKeywords`)

**Baris:** 681-683

```php
private function saveLearnedKeywords(Request $request, array $keywords): void {
    $request->session()->put('learned_keywords', $keywords);
}
```

**Parameters:**
- `$keywords` → array to store in session

---

## 6. LEARN FROM CORRECTIONS (`learnFromCorrections`)

**Baris:** 685-713

```php
private function learnFromCorrections(Request $request, array $labeledRows): void {
    $learnedKeywords = [
        'positive' => [],
        'negative' => [],
        'neutral' => []
    ];

    foreach ($labeledRows as $row) {
        $text = mb_strtolower($row['raw'], 'UTF-8');
        $sentiment = $row['sentiment'];

        // Split text into words
        $words = preg_split('/\s+/', $text);

        // Filter stopwords & short words
        $words = array_filter($words, function($word) {
            return mb_strlen($word) > 2 &&
                   !in_array($word, [
                       'yang', 'dan', 'di', 'ke', 'dari', 'untuk',
                       'pada', 'dengan', 'atau', 'itu', 'ini', 'jadi',
                       'karena', 'agar', 'tidak', 'iya', 'sudah', 'ada', 'adalah'
                   ]);
        });

        // Count word frequency per sentiment
        foreach ($words as $word) {
            if (!isset($learnedKeywords[$sentiment][$word])) {
                $learnedKeywords[$sentiment][$word] = 0;
            }
            $learnedKeywords[$sentiment][$word]++;
        }
    }

    $this->saveLearnedKeywords($request, $learnedKeywords);
}
```

**Contoh Execution:**
```
Input labeled rows:
[
    ['raw' => 'Produk bagus dan enak', 'sentiment' => 'positif'],
    ['raw' => 'Barang rusak dan lambat', 'sentiment' => 'negatif']
]

Processing:
1. Row 1: 'produk bagus dan enak' → positif
   - words = ['produk', 'bagus', 'enak'] (after filter)
   - increment: learnedKeywords['positive']['produk']++
   -           learnedKeywords['positive']['bagus']++
   -           learnedKeywords['positive']['enak']++

2. Row 2: 'barang rusak dan lambat' → negatif
   - words = ['barang', 'rusak', 'lambat']
   - increment: learnedKeywords['negative']['barang']++
   -           learnedKeywords['negative']['rusak']++
   -           learnedKeywords['negative']['lambat']++

Result:
learnedKeywords = [
    'positive' => ['produk' => 1, 'bagus' => 1, 'enak' => 1],
    'negative' => ['barang' => 1, 'rusak' => 1, 'lambat' => 1]
]
```

---

## STORAGE STRUCTURE

### **Directory Layout**

```
project_root/
├── storage/
│   ├── app/
│   │   ├── labelling/              ← Uploaded CSV files
│   │   │   ├── 20240110_093045_tweets.csv
│   │   │   ├── 20240110_093100_reviews.csv
│   │   │   └── ...
│   │   └── temp/                   ← Temporary JSON files (labelling in progress)
│   │       ├── temp_labeling_507ae8c4.json
│   │       ├── temp_labeling_6f3b2d9a.json
│   │       └── ...
│   ├── framework/
│   │   ├── cache/
│   │   ├── views/                  ← Compiled Blade templates
│   │   └── sessions/               ← Session files
│   └── logs/
├── routes/
│   └── web.php                     ← Route definitions
├── app/
│   ├── Http/
│   │   └── Controllers/
│   │       └── LabellingController.php
│   └── Models/
└── resources/
    └── views/
        └── labelling.blade.php     ← Frontend
```

### **Session Storage** (`_SESSION`)

```php
// After upload:
$_SESSION['label_csv_path'] = 'labelling/20240110_093045_tweets.csv'
$_SESSION['label_preview'] = [
    'header' => ['tweet', 'user_id', 'date'],
    'rows' => [[...], [...], ...]  // Max 200 rows
]

// After run:
$_SESSION['label_temp_file'] = 'temp_labeling_507ae8c4.json'
$_SESSION['label_total_data'] = 2500
$_SESSION['label_labeled'] = [
    'header' => [...],
    'rows' => [[...], ...],  // Current page (100 rows)
    'total_count' => 2500
]
$_SESSION['learned_keywords'] = [
    'positive' => ['bagus' => 5, 'enak' => 3, ...],
    'negative' => ['buruk' => 2, ...],
    'neutral' => [...]
]

// During pagination:
// Session['label_labeled']['rows'] updated dengan data halaman baru
```

### **Temporary JSON File Format** (`storage/app/temp/*.json`)

```json
{
    "header": ["tweet", "user_id", "date"],
    "rows": [
        {
            "raw": "Produk ini sangat bagus!",
            "sentiment": "positif",
            "confidence": 0.95
        },
        {
            "raw": "Barang rusak waktu sampai",
            "sentiment": "negatif",
            "confidence": 0.85
        },
        ...
    ],
    "total_count": 2500
}
```

**Mengapa JSON bukan relational database?**
- Session memory limited (~5MB)
- Bisa handle ratusan ribu rows di file
- Lock-free (no concurrency issues di development)
- Query-less (direct array operations)
- Easy cleanup (just delete file)

### **Downloaded CSV Format**

```csv
raw,sentiment,confidence
"Produk ini sangat bagus!",positif,0.95
"Barang rusak waktu sampai",negatif,0.85
"Biasa saja tidak spesial",netral,0.50
...
```

---

## DATA FLOW DIAGRAM

```
[CSV Upload]
     ↓
[storage/app/labelling/{timestamp}_{filename}.csv]
     ↓
[Read CSV] → [Filter Columns] → [Detect Tweet Column]
     ↓
[For Each Row: autoLabelSentiment() + calculateConfidence()]
     ↓
[storage/app/temp/temp_labeling_{id}.json]  ← All rows (full data)
     ↓
[Session] ← First 100 rows + metadata
     ↓
[Frontend Display + Pagination]
     ↓
[User Edit] → [Update JSON + Session]
     ↓
[Download] → [Read JSON] → [Stream CSV]
     ↓
[Cleanup] → [Delete JSON] → [Clear Session]
```
