# 4. API ENDPOINT & FLOW

**File:** `app/Http/Controllers/LabellingController.php`  
**Routes:** `routes/web.php` (line TBD)

---

## Endpoint List

| Method | Route | Function | Line | Deskripsi |
|--------|-------|----------|------|-----------|
| GET | `/labelling` | `index()` | 11 | Load halaman labelling |
| POST | `/labelling/upload` | `upload()` | 34 | Upload CSV file |
| POST | `/labelling/run` | `run()` | 56 | **RUN AUTO-LABEL** |
| GET | `/labelling/getpage` | `getPage()` | 122 | Pagination (load halaman) |
| POST | `/labelling/update-label` | `updateLabel()` | 162 | Edit 1 label manual |
| POST | `/labelling/bulk-update` | `bulkUpdate()` | 175 | Edit multiple label |
| GET | `/labelling/download` | `download()` | 217 | Export hasil CSV |
| POST | `/labelling/cleanup` | `cleanup()` | 249 | Reset session & delete temp file |

---

## FLOW #1: UPLOAD CSV

**Request:**
```
POST /labelling/upload
Content-Type: multipart/form-data

csv_file: [file-binary]
```

**Controller Code (Line 34-54):**
```php
public function upload(Request $request) {
    // 1. Validate file
    $request->validate([
        'csv_file' => ['required', 'file', 'mimes:csv,txt'],
    ]);

    // 2. Store file ke storage/labelling/
    $file = $request->file('csv_file');
    $path = $file->storeAs(
        'labelling', 
        now()->format('Ymd_His') . '_' . $file->getClientOriginalName()
    );

    // 3. Read first 200 rows untuk preview
    $fullPath = Storage::path($path);
    [$header, $rows] = $this->readCsv($fullPath, 2000);

    // 4. Filter noisy columns (score, time, at)
    [$header, $rows] = $this->filterColumns(
        $header, $rows, 
        ['score', 'time', 'at']
    );

    // 5. Simpan ke session
    $request->session()->put('label_csv_path', $path);
    $request->session()->put('label_preview', ['header' => $header, 'rows' => $rows]);
    $request->session()->forget('label_labeled');

    return redirect()->route('labelling.index')
        ->with('status', 'File CSV berhasil diupload.');
}
```

**Session Variables Created:**
- `label_csv_path` → path to uploaded file
- `label_preview` → [header, rows] untuk preview
- `label_labeled` → dihapus (ready untuk run)

**Response:**
```
Redirect ke /labelling dengan message "File CSV berhasil diupload."
```

---

## FLOW #2: RUN AUTO-LABELLING (MAIN LOGIC)

**Request:**
```
POST /labelling/run
```

**Controller Code (Line 56-120):**

```php
public function run(Request $request) {
    // 1. Validasi file sudah diupload
    $path = $request->session()->get('label_csv_path');
    if (!$path || !Storage::exists($path)) {
        return redirect()->route('labelling.index')
            ->with('error', 'Tidak ada file yang diupload.');
    }

    // 2. BACA FULL CSV (bukan preview)
    $fullPath = Storage::path($path);
    [$header, $rows] = $this->readCsv($fullPath, null);  // null = read semua
    [$header, $rows] = $this->filterColumns($header, $rows, ['score', 'time', 'at']);

    // 3. DETEKSI TWEET COLUMN INDEX
    $tweetIndex = $this->detectTweetColumnIndex($header);

    // 4. LOOP SETIAP BARIS → AUTO-LABEL
    $labeledRows = [];
    foreach ($rows as $row) {
        // Get tweet text
        $tweet = $tweetIndex !== null && isset($row[$tweetIndex]) 
            ? (string) $row[$tweetIndex] 
            : '';
        
        // ===== CALL autoLabelSentiment() =====
        $sentiment = $this->autoLabelSentiment($request, $tweet);
        
        // ===== CALL calculateConfidence() =====
        $confidence = $this->calculateConfidence($tweet, $sentiment);

        $labeledRows[] = [
            'raw' => $tweet,
            'sentiment' => $sentiment,
            'confidence' => $confidence,
        ];
    }

    // 5. LEARN FROM EXISTING CORRECTIONS
    $existingLabeled = $request->session()->get('label_labeled');
    if ($existingLabeled && isset($existingLabeled['rows'])) {
        $this->learnFromCorrections($request, $existingLabeled['rows']);
    }

    // 6. SAVE TO TEMP FILE (karena session terbatas)
    $tempFile = 'temp_labeling_' . uniqid() . '.json';
    $tempPath = storage_path('app/temp/' . $tempFile);
    
    if (!file_exists(storage_path('app/temp'))) {
        mkdir(storage_path('app/temp'), 0755, true);
    }
    
    file_put_contents($tempPath, json_encode([
        'header' => $header,
        'rows' => $labeledRows,
        'total_count' => count($labeledRows),
    ]));

    // 7. SAVE TO SESSION (untuk display)
    $request->session()->put('label_temp_file', $tempFile);
    $request->session()->put('label_total_data', count($labeledRows));
    $request->session()->put('label_labeled', [
        'header' => $header,
        'rows' => array_slice($labeledRows, 0, 100),  // First 100 rows only
        'total_count' => count($labeledRows),
    ]);

    return redirect()->route('labelling.index')
        ->with('status', 'Labelling selesai.');
}
```

**Process Timeline:**
```
1. Read CSV file
2. Filter columns
3. Detect tweet column
4. LOOP all rows:
   4.1 Extract text
   4.2 autoLabelSentiment() → "positif"/"negatif"/"netral"
   4.3 calculateConfidence() → float 0.1-0.95
   4.4 Save [raw, sentiment, confidence]
5. Save all data → temp JSON file
6. Load first 100 rows → session
7. Redirect & show success
```

**Variables in Session After Run:**
- `label_temp_file` → name of JSON temp file
- `label_total_data` → total rows count
- `label_labeled` → {header, rows[100], total_count}

---

## FLOW #3: PAGINATION (GET PAGE)

**Request:**
```
GET /labelling?page=2
```

**Controller Code (Line 122-161):**

```php
public function getPage(Request $request) {
    $page = (int) $request->get('page', 1);
    $perPage = 100;

    // Get temp file dari session
    $tempFile = $request->session()->get('label_temp_file');
    if (!$tempFile) {
        return redirect()->route('labelling.index')
            ->with('error', 'Tidak ada data yang tersedia.');
    }

    // Read dari JSON temp file
    $tempPath = storage_path('app/temp/' . $tempFile);
    $fileData = json_decode(file_get_contents($tempPath), true);
    $allData = $fileData['rows'] ?? [];
    $header = $fileData['header'] ?? [];

    // Validate page number
    $totalData = count($allData);
    $totalPages = ceil($totalData / $perPage);
    
    if ($page < 1 || $page > $totalPages) {
        return redirect()->route('labelling.index')
            ->with('error', 'Halaman tidak valid.');
    }

    // Calculate offset & slice data
    $offset = ($page - 1) * $perPage;
    $pageData = array_slice($allData, $offset, $perPage);

    // Update session dengan data halaman saat ini
    $request->session()->put('label_labeled', [
        'header' => $header,
        'rows' => $pageData,
        'total_count' => $totalData,
    ]);

    return redirect()->route('labelling.index', ['page' => $page]);
}
```

**Contoh:**
```
Total rows = 2500
Page size = 100
Total pages = 25

User ke page 2:
- offset = (2 - 1) * 100 = 100
- Tampilkan rows[100-199]
```

---

## FLOW #4: UPDATE LABEL MANUAL (SINGLE)

**Request:**
```
POST /labelling/update-label

{
  "row_index": 5,
  "sentiment": "negatif"
}
```

**Controller Code (Line 162-173):**

```php
public function updateLabel(Request $request) {
    $request->validate([
        'row_index' => 'required|integer|min:0',
        'sentiment' => 'required|in:positif,negatif,netral',
    ]);

    // Get current page data dari session
    $labeled = $request->session()->get('label_labeled');
    if (!$labeled || !isset($labeled['rows'][$request->row_index])) {
        return redirect()->route('labelling.index')
            ->with('error', 'Data tidak ditemukan.');
    }

    // Update sentiment & confidence
    $labeled['rows'][$request->row_index]['sentiment'] = $request->sentiment;
    $labeled['rows'][$request->row_index]['confidence'] = 1.0;  // ← MANUAL = 100% trust

    // Save kembali ke session
    $request->session()->put('label_labeled', $labeled);

    return redirect()->route('labelling.index')
        ->with('status', 'Label berhasil diupdate.');
}
```

**Effect:**
- `row_index` = posisi di halaman saat ini (0-99)
- Sentiment diubah ke pilihan user
- Confidence set ke 1.0 (full trust)
- Session updated
- Frontend reload halaman

---

## FLOW #5: UPDATE LABEL BULK (MULTIPLE)

**Request:**
```
POST /labelling/bulk-update

{
  "page": 2,
  "changes": {
    "0": "positif",
    "1": "negatif",
    "3": "netral"
  }
}
```

**Controller Code (Line 175-215):**

```php
public function bulkUpdate(Request $request) {
    $request->validate([
        'changes' => 'required|array',
        'changes.*' => 'in:positif,negatif,netral',
    ]);

    $labeled = $request->session()->get('label_labeled');
    if (!$labeled) {
        return response()->json(['success' => false]);
    }

    $changes = $request->input('changes', []);
    $currentPage = (int) $request->get('page', 1);
    $perPage = 100;
    $globalOffset = ($currentPage - 1) * $perPage;

    // Get temp file
    $tempFile = $request->session()->get('label_temp_file');
    if ($tempFile) {
        $tempPath = storage_path('app/temp/' . $tempFile);
        if (file_exists($tempPath)) {
            // Read all data
            $fileData = json_decode(file_get_contents($tempPath), true);
            $allData = $fileData['rows'] ?? [];

            // Update changes in full dataset
            $updatedCount = 0;
            foreach ($changes as $rowIndex => $sentiment) {
                $globalIndex = $globalOffset + (int) $rowIndex;
                if (isset($allData[$globalIndex])) {
                    $allData[$globalIndex]['sentiment'] = $sentiment;
                    $allData[$globalIndex]['confidence'] = 1.0;
                    $updatedCount++;
                }
            }

            // Save back to file
            $fileData['rows'] = $allData;
            file_put_contents($tempPath, json_encode($fileData));

            // Update session
            $pageData = array_slice($allData, $globalOffset, $perPage);
            $labeled['rows'] = $pageData;
            $request->session()->put('label_labeled', $labeled);
        }
    }

    return response()->json([
        'success' => true,
        'message' => "Berhasil mengupdate {$updatedCount} label.",
        'updated_count' => $updatedCount
    ]);
}
```

**Example Execution:**
```
Page 2, changes = {0: "positif", 3: "negatif"}
global_offset = (2-1) * 100 = 100
global_index_0 = 100 + 0 = 100
global_index_3 = 100 + 3 = 103

Update rows[100] → "positif"
Update rows[103] → "negatif"
```

---

## FLOW #6: DOWNLOAD CSV

**Request:**
```
GET /labelling/download
```

**Controller Code (Line 217-248):**

```php
public function download(Request $request): StreamedResponse {
    $tempFile = $request->session()->get('label_temp_file');
    if (!$tempFile) {
        return redirect()->route('labelling.index')
            ->with('error', 'Belum ada hasil labelling.');
    }

    $tempPath = storage_path('app/temp/' . $tempFile);
    $fileData = json_decode(file_get_contents($tempPath), true);
    $allData = $fileData['rows'] ?? [];

    if (empty($allData)) {
        return redirect()->route('labelling.index')
            ->with('error', 'Tidak ada data untuk didownload.');
    }

    $filename = 'labelling_' . now()->format('Ymd_His') . '.csv';
    
    return response()->stream(function () use ($allData) {
        $out = fopen('php://output', 'w');
        fputcsv($out, ['raw', 'sentiment', 'confidence']);  // Header
        
        // Write all rows
        foreach ($allData as $r) {
            fputcsv($out, [$r['raw'], $r['sentiment'], $r['confidence']]);
        }
        
        fclose($out);
    }, 200, [
        'Content-Type' => 'text/csv',
        'Content-Disposition' => 'attachment; filename="' . $filename . '"',
    ]);
}
```

**Output CSV Format:**
```csv
raw,sentiment,confidence
"Sangat bagus banget",positif,0.95
"Tidak suka",negatif,0.75
"Biasa saja",netral,0.5
```

---

## FLOW #7: CLEANUP

**Request:**
```
POST /labelling/cleanup
```

**Controller Code (Line 249-262):**

```php
public function cleanup(Request $request) {
    $tempFile = $request->session()->get('label_temp_file');
    if ($tempFile) {
        $tempPath = storage_path('app/temp/' . $tempFile);
        if (file_exists($tempPath)) {
            unlink($tempPath);  // Delete JSON temp file
        }
    }

    // Clear all session data
    $request->session()->forget([
        'label_temp_file',
        'label_total_data',
        'label_labeled',
        'label_csv_path',
        'label_preview'
    ]);

    return redirect()->route('labelling.index')
        ->with('status', 'Data berhasil dibersihkan.');
}
```

**Effect:**
- Hapus temporary JSON file
- Clear session
- Reset halaman ke kosong (siap upload file baru)
