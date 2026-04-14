# 7. API ENDPOINT FLOW

## Overview

```
API endpoints di routes/web.php (lines 25-33)

GET    /klasifikasi                      → Load page
POST   /klasifikasi/upload/training      → Upload training CSV
POST   /klasifikasi/upload/testing       → Upload testing CSV
POST   /klasifikasi/run                  → Run classification
GET    /klasifikasi/download             → Download results
GET    /klasifikasi/cleanup              → Clear session
```

---

## 1. GET /klasifikasi

### Purpose:
Load halaman klasifikasi dengan data dari session

### Request:
```
GET /klasifikasi HTTP/1.1
```

### Response:
HTML page dengan form upload + preview + results

### Implementation:
```php
public function index(Request $request)
{
    $trainingPath = $request->session()->get('class_training_path');
    $testingPath = $request->session()->get('class_testing_path');
    $trainingPreview = $request->session()->get('class_training_preview');
    $testingPreview = $request->session()->get('class_testing_preview');
    $resultsSummary = $request->session()->get('class_results_summary');

    return view('klasifikasi', [
        'trainingPath' => $trainingPath,
        'testingPath' => $testingPath,
        'trainingPreview' => $trainingPreview,
        'testingPreview' => $testingPreview,
        'results' => $resultsSummary,
    ]);
}
```

### Response Data:
```php
[
    'trainingPath' => 'classification/training_20260302_143502_data.csv',
    'testingPath' => 'classification/testing_20260302_143650_data.csv',
    'trainingPreview' => [
        'header' => ['id', 'text', 'label'],
        'rows' => [
            ['1', 'produk bagus', 'positif'],
            ['2', 'jelek', 'negatif'],
            // ...max 100 rows
        ]
    ],
    'testingPreview' => [...],
    'results' => [
        'accuracy' => 0.85,
        'total_samples' => 100,
        'correct_predictions' => 85,
        'metrics' => [
            'positif' => ['precision' => 0.88, 'recall' => 0.82, ...],
            'negatif' => [...],
            'netral' => [...]
        ]
    ]
]
```

---

## 2. POST /klasifikasi/upload/training

### Purpose:
Handle training data upload

### Request:
```
POST /klasifikasi/upload/training HTTP/1.1
Content-Type: multipart/form-data

csv_file: <binary file data>
_token: <CSRF token>
```

### Validation:
```php
$request->validate([
    'csv_file' => ['required', 'file', 'mimes:csv,txt'],
]);
```

Rules:
- File required
- Must be file
- Accepted types: .csv, .txt
- No size limit (default 100MB)

### Processing:
```
1. Save file to storage/classification/
   Name: training_<YmdHis>_<original_filename>.csv
   
2. Read CSV to memory:
   - Read header (first row)
   - Read 100 preview rows
   
3. Store in session:
   - class_training_path = storage path
   - class_training_preview = {header, rows}
   - Clear previous results
```

### Success Response:
```
Redirect to /klasifikasi
with status: "Data training berhasil diupload."

Session updated:
├─ class_training_path = 'classification/training_...'
├─ class_training_preview = {header, rows}
└─ class_results = cleared
```

### Error Response:
```
422 Unprocessable Entity

Error message:
"csv_file" => "The csv file field is required."
```

### Frontend Behavior:
```
1. Show file input
2. User select file
3. Click "Upload Training"
4. File sends to server
5. Page reload with preview table displayed
6. "Upload Testing" button now enabled
```

---

## 3. POST /klasifikasi/upload/testing

### Purpose:
Handle testing data upload

### Request, Validation, Processing:
Same as training upload

### Session Storage:
```
- class_testing_path
- class_testing_preview
```

### Frontend Behavior:
```
Same as training upload
+ Enable "Jalankan SVM Classification" button after successful upload
```

---

## 4. POST /klasifikasi/run

### Purpose:
Run classification algorithm

### Request:
```
POST /klasifikasi/run HTTP/1.1
Content-Type: application/x-www-form-urlencoded

_token: <CSRF token>

(No other parameters - uses session data)
```

### Prerequisites:
```
Session must have:
├─ class_training_path (exists as file)
├─ class_testing_path (exists as file)
└─ Both files readable
```

### Processing:

**Phase 1: Validation**
```
if (!$trainingPath || !Storage::exists($trainingPath))
    → return error "Data training belum diupload."
    
if (!$testingPath || !Storage::exists($testingPath))
    → return error "Data testing belum diupload."
```

**Phase 2: Configuration**
```php
set_time_limit(300);              // 5 min timeout
ini_set('memory_limit', '512M');  // 512MB ram
```

**Phase 3: Read Data**
```
Read full training CSV
├─ Parse all rows
├─ Detect text & label columns
└─ Preprocess each text

Read full testing CSV
├─ Parse all rows
├─ Detect text column
└─ Preprocess each text
```

**Phase 4: Classification**
```
results = runSVMClassificationBatch(trainingData, testingData)
├─ Build vocabulary
├─ Vectorize data
├─ Batch predict (100 samples at a time)
└─ Calculate metrics
```

**Phase 5: Store Results**
```
1. Create temp file: storage/app/temp/temp_classification_<id>.json
2. Write full results to temp file (all predictions)
3. Store in session:
   ├─ class_temp_file = 'temp_classification_...'
   └─ class_results_summary = {accuracy, total_samples, correct_predictions, metrics}
```

### Response:
```
Redirect to /klasifikasi
with status: "Klasifikasi selesai. Akurasi: 85.50%"

Frontend:
├─ Show loading spinner disappear
├─ Results table appear
├─ Tab switch to "Hasil Klasifikasi"
└─ Download button enabled
```

### Error Responses:
```
Error 1: Training data missing
├─ Status: 422
└─ Message: "Data training belum diupload."

Error 2: Testing data missing
├─ Status: 422
└─ Message: "Data testing belum diupload."

Error 3: Format invalid
├─ Status: 422
└─ Message: "Format data tidak sesuai. Pastikan ada kolom text dan label."
```

### Execution Time:
```
Sample size | Time
─────────────────
100         | 1s
500         | 2s
1000        | 2.5s
5000        | 10s
10000       | 15s
50000       | 60s
```

---

## 5. GET /klasifikasi/download

### Purpose:
Download classification results as CSV

### Request:
```
GET /klasifikasi/download HTTP/1.1
```

### Prerequisites:
```
Session must have:
└─ class_temp_file (exists)
```

### Processing:
```
1. Get temp file path from session
2. Check if file exists
3. Read JSON file
4. Convert to CSV format
5. Stream response to browser
```

### CSV Format:
```
Headers: text, actual_label, predicted_label, confidence

Row 1: "produk bagus", "positif", "positif", 0.92
Row 2: "jelek", "negatif", "negatif", 0.88
Row 3: "cukup", "netral", "negatif", 0.45
...
```

### Response:
```
HTTP/1.1 200 OK
Content-Type: text/csv
Content-Disposition: attachment; filename="classification_results_20260302_143800.csv"

[CSV content]
```

### Browser Behavior:
```
→ Trigger download dialog
→ Save as classification_results_<timestamp>.csv
→ Can open in Excel / Sheets
```

### Error Response:
```
Error: No classification results

Status: 404
Message: "Belum ada hasil klasifikasi."
```

---

## 6. GET /klasifikasi/cleanup

### Purpose:
Clear all session data & temp files

### Request:
```
GET /klasifikasi/cleanup HTTP/1.1
```

### Processing:
```
1. Get temp file path from session
2. If exists, delete file
3. Clear all session variables:
   ├─ class_temp_file
   ├─ class_results_summary
   ├─ class_training_path
   ├─ class_testing_path
   ├─ class_training_preview
   └─ class_testing_preview
```

### Response:
```
Redirect to /klasifikasi
with status: "Data berhasil dibersihkan."

Page shows empty form again
- No preview tables
- No results table
- Ready for new upload/classification
```

---

## Full User Flow Example

```
User action 1: GET /klasifikasi
  └─ Server: Load empty form
  └─ Browser: Show upload form

User action 2: POST upload training
  └─ Server: Save file, read preview, update session
  └─ Browser: Show training preview table

User action 3: POST upload testing
  └─ Server: Save file, read preview, update session
  └─ Browser: Show testing preview table

User action 4: POST classification run
  └─ Server: Execute algorithm, save results, update session
  └─ Browser: Show loading spinner, then results table

User action 5: GET download
  └─ Server: Stream CSV file
  └─ Browser: Download results_20260302_143800.csv

User action 6: GET cleanup (optional)
  └─ Server: Delete temp file, clear session
  └─ Browser: Reset to empty form
```

---

## Error Handling Summary

| Endpoint | Error Type | HTTP Status | Message |
|----------|-----------|------------|---------|
| /upload/training | No file | 422 | File required |
| /upload/training | Wrong type | 422 | Must be CSV |
| /run | No training | 422 | Training not uploaded |
| /run | No testing | 422 | Testing not uploaded |
| /run | Invalid format | 422 | Format not valid |
| /download | No results | 404 | No results yet |
| All | CSRF fail | 419 | Token mismatch |

---

## Session Data Structure

```php
// After training upload
$_SESSION = [
    'class_training_path' => 'classification/training_20260302_143502_data.csv',
    'class_training_preview' => [
        'header' => ['id', 'text', 'label'],
        'rows' => [...]
    ],
    // other session data...
]

// After testing upload
$_SESSION = [
    'class_testing_path' => 'classification/testing_20260302_143650_data.csv',
    'class_testing_preview' => [
        'header' => ['id', 'text'],
        'rows' => [...]
    ],
    // + training data from before...
]

// After classification run
$_SESSION = [
    'class_temp_file' => 'temp_classification_65d2a8b9f1c3e.json',
    'class_results_summary' => [
        'accuracy' => 0.85,
        'total_samples' => 100,
        'correct_predictions' => 85,
        'metrics' => [...]
    ],
    // + training & testing data from before...
]
```

