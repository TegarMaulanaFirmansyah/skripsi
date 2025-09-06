# API Documentation

This document describes the API endpoints and data structures for the Sentiment Analysis application.

## 📋 Overview

The application provides RESTful API endpoints for:
- Data preprocessing
- Sentiment labelling
- Model classification
- Result evaluation

## 🔗 Base URL

```
http://localhost:8000/api
```

## 📊 Data Formats

### Input CSV Format
```csv
text,label
"aplikasi bagus sekali",positif
"pelayanan buruk",negatif
"biasa saja",netral
```

### Response Format
```json
{
    "success": true,
    "message": "Operation completed successfully",
    "data": {
        // Response data
    },
    "errors": []
}
```

## 🔧 Preprocessing API

### Upload CSV for Preprocessing
```http
POST /api/preprocessing/upload
Content-Type: multipart/form-data

csv_file: [file]
```

**Response:**
```json
{
    "success": true,
    "message": "File uploaded successfully",
    "data": {
        "filename": "preprocessing_20250106_163800_ulasan.csv",
        "preview": {
            "header": ["userName", "content"],
            "rows": [
                ["Surrahmad Irfandi srg", "bodog"],
                ["Indah Toke99", "smogha di ACC"]
            ]
        }
    }
}
```

### Run Preprocessing
```http
POST /api/preprocessing/run
Content-Type: application/json

{
    "text_column": 1
}
```

**Response:**
```json
{
    "success": true,
    "message": "Preprocessing completed",
    "data": {
        "total_processed": 1100,
        "preview": [
            {
                "raw": "aplikasi bagus sekali",
                "case_folding": "aplikasi bagus sekali",
                "cleansing": "aplikasi bagus sekali",
                "normalisasi": "aplikasi bagus sekali",
                "tokenizing": "aplikasi bagus sekali",
                "filtering": "aplikasi bagus sekali",
                "stemming": "aplikasi bagus bagus"
            }
        ]
    }
}
```

### Download Preprocessing Results
```http
GET /api/preprocessing/download
```

**Response:** CSV file download

## 🏷️ Labelling API

### Upload CSV for Labelling
```http
POST /api/labelling/upload
Content-Type: multipart/form-data

csv_file: [file]
```

**Response:**
```json
{
    "success": true,
    "message": "File uploaded successfully",
    "data": {
        "filename": "labelling_20250106_163800_ulasan.csv",
        "preview": {
            "header": ["userName", "content"],
            "rows": [
                ["Surrahmad Irfandi srg", "bodog"],
                ["Indah Toke99", "smogha di ACC"]
            ]
        }
    }
}
```

### Run Auto Labelling
```http
POST /api/labelling/run
Content-Type: application/json

{
    "text_column": 1
}
```

**Response:**
```json
{
    "success": true,
    "message": "Auto labelling completed",
    "data": {
        "total_processed": 1100,
        "preview": [
            {
                "raw": "aplikasi bagus sekali",
                "sentiment": "positif",
                "confidence": 0.85
            }
        ],
        "learned_keywords": {
            "positive": ["bagus", "baik"],
            "negative": ["buruk", "jelek"],
            "neutral": ["biasa", "normal"]
        }
    }
}
```

### Update Labels (Bulk)
```http
POST /api/labelling/bulk-update
Content-Type: application/json

{
    "changes": {
        "0": "positif",
        "1": "negatif",
        "2": "netral"
    },
    "page": 1
}
```

**Response:**
```json
{
    "success": true,
    "message": "Berhasil mengupdate 3 label",
    "data": {
        "updated_count": 3
    }
}
```

### Download Labelling Results
```http
GET /api/labelling/download
```

**Response:** CSV file download

## 🤖 Classification API

### Upload Training Data
```http
POST /api/classification/upload/training
Content-Type: multipart/form-data

csv_file: [file]
```

**Response:**
```json
{
    "success": true,
    "message": "Training data uploaded successfully",
    "data": {
        "filename": "training_20250106_163800_data.csv",
        "preview": {
            "header": ["text", "label"],
            "rows": [
                ["aplikasi bagus", "positif"],
                ["pelayanan buruk", "negatif"]
            ]
        }
    }
}
```

### Upload Testing Data
```http
POST /api/classification/upload/testing
Content-Type: multipart/form-data

csv_file: [file]
```

**Response:**
```json
{
    "success": true,
    "message": "Testing data uploaded successfully",
    "data": {
        "filename": "testing_20250106_163800_data.csv",
        "preview": {
            "header": ["text", "label"],
            "rows": [
                ["aplikasi bagus sekali", "positif"],
                ["pelayanan sangat buruk", "negatif"]
            ]
        }
    }
}
```

### Run Classification
```http
POST /api/classification/run
```

**Response:**
```json
{
    "success": true,
    "message": "Classification completed",
    "data": {
        "accuracy": 0.852,
        "total_samples": 700,
        "correct_predictions": 596,
        "metrics": {
            "positif": {
                "precision": 0.82,
                "recall": 0.88,
                "f1_score": 0.85
            },
            "negatif": {
                "precision": 0.87,
                "recall": 0.83,
                "f1_score": 0.85
            },
            "netral": {
                "precision": 0.86,
                "recall": 0.84,
                "f1_score": 0.85
            }
        }
    }
}
```

### Download Classification Results
```http
GET /api/classification/download
```

**Response:** CSV file download

## 📈 Evaluation API

### Upload Results for Evaluation
```http
POST /api/evaluation/upload
Content-Type: multipart/form-data

csv_file: [file]
method_name: "SVM"
```

**Response:**
```json
{
    "success": true,
    "message": "Results uploaded successfully",
    "data": {
        "method_name": "SVM",
        "sample_count": 700,
        "metrics": {
            "accuracy": 0.852,
            "precision": 0.85,
            "recall": 0.85,
            "f1_score": 0.85
        }
    }
}
```

### Compare Methods
```http
POST /api/evaluation/compare
```

**Response:**
```json
{
    "success": true,
    "message": "Comparison completed",
    "data": {
        "results": [
            {
                "method_name": "Random Forest",
                "sample_count": 700,
                "accuracy": 0.873,
                "precision": 0.87,
                "recall": 0.87,
                "f1_score": 0.87
            },
            {
                "method_name": "SVM",
                "sample_count": 700,
                "accuracy": 0.852,
                "precision": 0.85,
                "recall": 0.85,
                "f1_score": 0.85
            }
        ],
        "best_method": "Random Forest",
        "best_accuracy": 0.873,
        "total_methods": 2
    }
}
```

### Generate Confusion Matrix
```http
POST /api/evaluation/confusion-matrix
Content-Type: application/json

{
    "method_name": "SVM"
}
```

**Response:**
```json
{
    "success": true,
    "message": "Confusion matrix generated",
    "data": {
        "method_name": "SVM",
        "matrix": {
            "positif": {
                "positif": 45,
                "negatif": 3,
                "netral": 2
            },
            "negatif": {
                "positif": 2,
                "negatif": 38,
                "netral": 5
            },
            "netral": {
                "positif": 1,
                "negatif": 4,
                "netral": 40
            }
        },
        "labels": ["positif", "negatif", "netral"],
        "total_samples": 140
    }
}
```

### Download Evaluation Report
```http
GET /api/evaluation/download
```

**Response:** CSV file download

## 🧹 Cleanup API

### Cleanup Preprocessing Data
```http
GET /api/preprocessing/cleanup
```

**Response:**
```json
{
    "success": true,
    "message": "Preprocessing data cleaned up"
}
```

### Cleanup Labelling Data
```http
GET /api/labelling/cleanup
```

**Response:**
```json
{
    "success": true,
    "message": "Labelling data cleaned up"
}
```

### Cleanup Classification Data
```http
GET /api/classification/cleanup
```

**Response:**
```json
{
    "success": true,
    "message": "Classification data cleaned up"
}
```

### Cleanup Evaluation Data
```http
GET /api/evaluation/cleanup
```

**Response:**
```json
{
    "success": true,
    "message": "Evaluation data cleaned up"
}
```

## 📊 Data Structures

### Preprocessing Result
```json
{
    "raw": "string",
    "case_folding": "string",
    "cleansing": "string",
    "normalisasi": "string",
    "tokenizing": "string",
    "filtering": "string",
    "stemming": "string"
}
```

### Labelling Result
```json
{
    "raw": "string",
    "sentiment": "positif|negatif|netral",
    "confidence": 0.0-1.0
}
```

### Classification Result
```json
{
    "text": "string",
    "actual_label": "positif|negatif|netral",
    "predicted_label": "positif|negatif|netral",
    "confidence": 0.0-1.0
}
```

### Metrics
```json
{
    "accuracy": 0.0-1.0,
    "precision": 0.0-1.0,
    "recall": 0.0-1.0,
    "f1_score": 0.0-1.0,
    "metrics_per_class": {
        "positif": {
            "precision": 0.0-1.0,
            "recall": 0.0-1.0,
            "f1_score": 0.0-1.0
        }
    }
}
```

## ⚠️ Error Responses

### Validation Error
```json
{
    "success": false,
    "message": "Validation failed",
    "errors": {
        "csv_file": ["The csv file field is required."],
        "method_name": ["The method name field is required."]
    }
}
```

### File Not Found
```json
{
    "success": false,
    "message": "File not found",
    "errors": []
}
```

### Processing Error
```json
{
    "success": false,
    "message": "Processing failed",
    "errors": ["Error message details"]
}
```

## 🔐 Authentication

Currently, the API does not require authentication. For production use, consider implementing:

- API key authentication
- JWT tokens
- OAuth 2.0
- Rate limiting

## 📝 Rate Limiting

Default rate limits:
- 60 requests per minute per IP
- 1000 requests per hour per IP

## 🧪 Testing

### Using cURL

```bash
# Upload file
curl -X POST http://localhost:8000/api/preprocessing/upload \
  -F "csv_file=@data.csv"

# Run preprocessing
curl -X POST http://localhost:8000/api/preprocessing/run \
  -H "Content-Type: application/json" \
  -d '{"text_column": 1}'
```

### Using Postman

1. Import the API collection
2. Set base URL to `http://localhost:8000/api`
3. Configure environment variables
4. Run requests

## 📚 SDK Examples

### JavaScript/Node.js
```javascript
const FormData = require('form-data');
const fs = require('fs');

const form = new FormData();
form.append('csv_file', fs.createReadStream('data.csv'));

fetch('http://localhost:8000/api/preprocessing/upload', {
    method: 'POST',
    body: form
})
.then(response => response.json())
.then(data => console.log(data));
```

### Python
```python
import requests

url = 'http://localhost:8000/api/preprocessing/upload'
files = {'csv_file': open('data.csv', 'rb')}

response = requests.post(url, files=files)
data = response.json()
print(data)
```

### PHP
```php
$url = 'http://localhost:8000/api/preprocessing/upload';
$data = ['csv_file' => new CURLFile('data.csv')];

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

$response = curl_exec($ch);
curl_close($ch);

$result = json_decode($response, true);
print_r($result);
```

## 🔄 Webhooks

Currently not implemented. Future versions may include:
- Processing completion notifications
- Error alerts
- Progress updates

## 📞 Support

For API support:
- Create an issue on GitHub
- Check the documentation
- Review error logs
- Contact the development team

---

This API documentation provides comprehensive information about all available endpoints, data structures, and usage examples for the Sentiment Analysis application.
