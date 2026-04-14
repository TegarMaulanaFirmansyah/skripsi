<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Evaluasi Model</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        body { margin: 0; font-family: 'Poppins', system-ui, -apple-system, Segoe UI, Roboto, 'Helvetica Neue', Arial, 'Noto Sans', 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol'; background:#f7fafc; color:#1a202c; }
        .container { min-height: 100vh; display:flex; align-items:stretch; justify-content:center; padding:32px; gap:24px; }
        .sidebar { width: 260px; background:#ffffff; border-radius:16px; box-shadow:0 10px 30px rgba(0,0,0,.08); padding:24px; height: auto; display:flex; flex-direction:column; transition: transform 0.3s ease; }
        .sidebar.hidden { transform: translateX(-100%); position: absolute; z-index: 1000; }
        .brand { font-weight:700; font-size:18px; margin:0 0 16px; letter-spacing:.3px; }
        .nav { list-style:none; padding:0; margin:0; display:flex; flex-direction:column; gap:8px; }
        .nav a { display:block; padding:10px 12px; border-radius:10px; color:#1a202c; text-decoration:none; font-weight:600; font-size:14px; border:1px solid transparent; }
        .nav a:hover { background:#f7fafc; border-color:#e2e8f0; }
        .nav a.active { background:#edf2f7; border-color:#cbd5e0; }
        .content { flex:1; background:#ffffff; border-radius:16px; box-shadow:0 10px 30px rgba(0,0,0,.08); padding:32px; }
        .title { font-size:24px; line-height:1.4; font-weight:700; margin:0 0 16px; display:flex; align-items:center; gap:12px; }
        .menu-toggle { background:none; border:none; cursor:pointer; padding:4px; display:flex; flex-direction:column; gap:3px; }
        .menu-toggle span { width:20px; height:2px; background:#1a202c; transition:0.3s; }
        .menu-toggle:hover span { background:#3182ce; }
        .row { display:flex; gap:12px; flex-wrap:wrap; align-items:center; }
        .btn { appearance:none; border:1px solid #cbd5e0; background:#edf2f7; color:#1a202c; padding:8px 12px; border-radius:8px; font-weight:600; cursor:pointer; }
        .btn.primary { background:#3182ce; color:#fff; border-color:#3182ce; }
        .btn.success { background:#38a169; color:#fff; border-color:#38a169; }
        .btn.warning { background:#d69e2e; color:#fff; border-color:#d69e2e; }
        .btn.info { background:#4299e1; color:#fff; border-color:#4299e1; }
        .btn.danger { background:#e53e3e; color:#fff; border-color:#e53e3e; }
        .note { font-size:12px; color:#4a5568; }
        .tabs { display:flex; gap:8px; margin:12px 0; }
        .tab-btn { border:1px solid #cbd5e0; background:#fff; color:#1a202c; padding:6px 10px; border-radius:8px; font-weight:600; cursor:pointer; }
        .tab-btn.active { background:#1a202c; color:#fff; border-color:#1a202c; }
        table { width:100%; border-collapse:collapse; font-size:13px; }
        th, td { border:1px solid #e2e8f0; padding:8px; text-align:left; vertical-align:top; }
        th { background:#f7fafc; font-weight:600; }
        .pill { display:inline-block; background:#f7fafc; border:1px solid #e2e8f0; border-radius:999px; padding:2px 8px; font-size:12px; }
        .sentiment-positif { background:#f0fff4; border-color:#9ae6b4; color:#22543d; }
        .sentiment-negatif { background:#fff5f5; border-color:#feb2b2; color:#9b2c2c; }
        .sentiment-netral { background:#f7fafc; border-color:#e2e8f0; color:#4a5568; }
        .sentiment-badge { display:inline-block; padding:2px 8px; border-radius:999px; font-size:11px; font-weight:600; }
        .metrics-grid { display:grid; grid-template-columns:repeat(auto-fit, minmax(200px, 1fr)); gap:16px; margin:16px 0; }
        .metric-card { background:#f7fafc; border:1px solid #e2e8f0; border-radius:8px; padding:16px; text-align:center; }
        .metric-value { font-size:24px; font-weight:700; color:#3182ce; margin-bottom:4px; }
        .metric-label { font-size:12px; color:#4a5568; text-transform:uppercase; letter-spacing:0.5px; }
        .upload-section { background:#f7fafc; border:1px solid #e2e8f0; border-radius:8px; padding:16px; margin-bottom:16px; }
        .upload-section h3 { margin:0 0 12px; font-size:16px; color:#1a202c; }
        .form-group { margin-bottom:12px; }
        .form-group label { display:block; margin-bottom:4px; font-weight:600; font-size:14px; }
        .form-group input, .form-group select { width:100%; padding:8px 12px; border:1px solid #cbd5e0; border-radius:6px; font-size:14px; }
        .comparison-table { margin:16px 0; }
        .best-method { background:#f0fff4; border:1px solid #9ae6b4; border-radius:8px; padding:12px; margin:16px 0; }
        .confusion-matrix { margin:16px 0; }
        .confusion-matrix table { text-align:center; }
        .confusion-matrix th, .confusion-matrix td { padding:12px; }
        .confusion-matrix .diagonal { background:#f0fff4; }
        .confusion-matrix .off-diagonal { background:#fff5f5; }
        .file-list { margin:16px 0; }
        .file-item { background:#f7fafc; border:1px solid #e2e8f0; border-radius:8px; padding:12px; margin-bottom:8px; display:flex; justify-content:space-between; align-items:center; }
        .file-info { flex:1; }
        .file-name { font-weight:600; font-size:14px; margin-bottom:4px; }
        .file-meta { font-size:12px; color:#4a5568; }
        .file-actions { display:flex; gap:8px; }
        .status-message { padding:12px; border-radius:8px; margin-bottom:16px; }
        .status-success { background:#f0fff4; border:1px solid #9ae6b4; color:#22543d; }
        .status-error { background:#fff5f5; border:1px solid #feb2b2; color:#9b2c2c; }
        .status-info { background:#e6f3ff; border:1px solid #b3d9ff; color:#0066cc; }
        @media (max-width: 768px) {
            .container { flex-direction:column; padding:16px; }
            .sidebar { width:100%; position:relative; }
            .sidebar.hidden { transform:none; position:relative; }
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Sidebar -->
        <div class="sidebar" id="sidebar">
            <div class="brand">📊 Evaluasi Model</div>
            <ul class="nav">
                <li><a href="{{ route('dashboard') }}">🏠 Dashboard</a></li>
                <li><a href="{{ route('preprocessing.index') }}">🔧 Preprocessing</a></li>
                <li><a href="{{ route('labelling.index') }}">🏷️ Labelling</a></li>
                <li><a href="{{ route('classification.index') }}">🤖 Klasifikasi</a></li>
                <li><a href="{{ route('evaluation.index') }}" class="active">📈 Evaluasi</a></li>
            </ul>
        </div>

        <!-- Main Content -->
        <div class="content">
            <div class="title">
                <button class="menu-toggle" onclick="toggleSidebar()">
                    <span></span>
                    <span></span>
                    <span></span>
                </button>
                📈 Evaluasi Model Sentimen
            </div>

            @if (session('status'))
                <div class="status-message status-success">{{ session('status') }}</div>
            @endif

            @if (session('error'))
                <div class="status-message status-error">{{ session('error') }}</div>
            @endif

            <!-- Upload Results Section -->
            <div class="upload-section">
                <h3>📤 Upload Hasil Klasifikasi</h3>
                <form action="{{ route('evaluation.upload') }}" method="post" enctype="multipart/form-data">
                    @csrf
                    <div class="row">
                        <div class="form-group" style="flex:1;">
                            <label for="method_name">Nama Metode:</label>
                            <input type="text" id="method_name" name="method_name" placeholder="Contoh: SVM, Naive Bayes, Random Forest" required>
                        </div>
                        <div class="form-group" style="flex:1;">
                            <label for="csv_file">File CSV Hasil:</label>
                            <input type="file" id="csv_file" name="csv_file" accept=".csv,.txt" required>
                        </div>
                        <div style="align-self:end;">
                            <button type="submit" class="btn primary">📤 Upload</button>
                        </div>
                    </div>
                    <div class="note">
                        Format CSV harus berisi kolom: text, actual_label, predicted_label, confidence
                    </div>
                </form>
            </div>

            <!-- Uploaded Files List -->
            @if (!empty($uploadedFiles))
                <div class="file-list">
                    <h3>📁 File yang Diupload ({{ count($uploadedFiles) }})</h3>
                    @foreach ($uploadedFiles as $file)
                        <div class="file-item">
                            <div class="file-info">
                                <div class="file-name">{{ $file['method_name'] }}</div>
                                <div class="file-meta">
                                    {{ $file['sample_count'] }} samples | 
                                    Akurasi: {{ number_format($file['metrics']['accuracy'] * 100, 2) }}% | 
                                    Upload: {{ $file['uploaded_at'] }}
                                </div>
                            </div>
                            <div class="file-actions">
                                <span class="pill sentiment-positif">{{ number_format($file['metrics']['accuracy'] * 100, 1) }}%</span>
                            </div>
                        </div>
                    @endforeach
                </div>

                <!-- Action Buttons -->
                <div class="row" style="margin:20px 0;">
                    @if (count($uploadedFiles) >= 2)
                        <form action="{{ route('evaluation.compare') }}" method="post" style="display:inline;">
                            @csrf
                            <button type="submit" class="btn success">🔄 Bandingkan Metode</button>
                        </form>
                    @endif
                    
                    @if (!empty($uploadedFiles))
                        <form action="{{ route('evaluation.confusion-matrix') }}" method="post" style="display:inline;">
                            @csrf
                            <select name="method_name" required style="margin-right:8px; padding:8px;">
                                <option value="">Pilih Metode untuk Confusion Matrix</option>
                                @foreach ($uploadedFiles as $file)
                                    <option value="{{ $file['method_name'] }}">{{ $file['method_name'] }}</option>
                                @endforeach
                            </select>
                            <button type="submit" class="btn info">📊 Confusion Matrix</button>
                        </form>
                    @endif
                    
                    @if (!empty($comparisonResults) || !empty($confusionMatrix))
                        <a href="{{ route('evaluation.download') }}" class="btn warning">📥 Download Report</a>
                    @endif
                    
                    <a href="{{ route('evaluation.cleanup') }}" class="btn danger" onclick="return confirm('Yakin ingin membersihkan semua data?')">🗑️ Bersihkan</a>
                </div>
            @endif

            <!-- Comparison Results -->
            @if (!empty($comparisonResults))
                <div style="margin-top:24px;">
                    <h3>🏆 Hasil Perbandingan Metode</h3>
                    
                    <div class="best-method">
                        <strong>🥇 Metode Terbaik:</strong> {{ $comparisonResults['best_method'] }} 
                        (Akurasi: {{ number_format($comparisonResults['best_accuracy'] * 100, 2) }}%)
                    </div>

                    <div class="comparison-table">
                        <table>
                            <thead>
                                <tr>
                                    <th>Ranking</th>
                                    <th>Metode</th>
                                    <th>Samples</th>
                                    <th>Akurasi</th>
                                    <th>Precision</th>
                                    <th>Recall</th>
                                    <th>F1-Score</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($comparisonResults['results'] as $index => $result)
                                    <tr class="{{ $index === 0 ? 'best-method' : '' }}">
                                        <td style="text-align:center; font-weight:600;">
                                            @if ($index === 0) 🥇
                                            @elseif ($index === 1) 🥈
                                            @elseif ($index === 2) 🥉
                                            @else {{ $index + 1 }}
                                            @endif
                                        </td>
                                        <td><strong>{{ $result['method_name'] }}</strong></td>
                                        <td style="text-align:center;">{{ $result['sample_count'] }}</td>
                                        <td style="text-align:center;">
                                            <span class="pill sentiment-positif">{{ number_format($result['accuracy'] * 100, 2) }}%</span>
                                        </td>
                                        <td style="text-align:center;">{{ number_format($result['precision'] * 100, 2) }}%</td>
                                        <td style="text-align:center;">{{ number_format($result['recall'] * 100, 2) }}%</td>
                                        <td style="text-align:center;">{{ number_format($result['f1_score'] * 100, 2) }}%</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif

            <!-- Confusion Matrix -->
            @if (!empty($confusionMatrix))
                <div style="margin-top:24px;">
                    <h3>📊 Confusion Matrix - {{ $confusionMatrix['method_name'] }}</h3>
                    
                    <!-- Summary Cards -->
                    <div class="metrics-grid" style="margin-bottom:20px;">
                        @php
                            $totalCorrect = 0;
                            $totalIncorrect = 0;
                            foreach ($confusionMatrix['labels'] as $label) {
                                $totalCorrect += $confusionMatrix['matrix'][$label][$label] ?? 0;
                            }
                            $totalIncorrect = $confusionMatrix['total_samples'] - $totalCorrect;
                            $accuracy = $confusionMatrix['total_samples'] > 0 ? ($totalCorrect / $confusionMatrix['total_samples']) * 100 : 0;
                        @endphp
                        
                        <div class="metric-card" style="background:#f0fff4; border-color:#9ae6b4;">
                            <div class="metric-value" style="color:#22543d;">{{ number_format($accuracy, 1) }}%</div>
                            <div class="metric-label">Akurasi</div>
                        </div>
                        <div class="metric-card" style="background:#f0fff4; border-color:#9ae6b4;">
                            <div class="metric-value" style="color:#22543d;">{{ $totalCorrect }}</div>
                            <div class="metric-label">Prediksi Benar</div>
                        </div>
                        <div class="metric-card" style="background:#fff5f5; border-color:#feb2b2;">
                            <div class="metric-value" style="color:#9b2c2c;">{{ $totalIncorrect }}</div>
                            <div class="metric-label">Prediksi Salah</div>
                        </div>
                        <div class="metric-card">
                            <div class="metric-value">{{ $confusionMatrix['total_samples'] }}</div>
                            <div class="metric-label">Total Samples</div>
                        </div>
                    </div>

                    <!-- Confusion Matrix Table Only -->
                    <div style="margin:20px 0;">
                        <h4 style="margin:0 0 12px; font-size:14px; color:#4a5568;">📋 Tabel Confusion Matrix</h4>
                        <div class="confusion-matrix">
                            <table>
                                <thead>
                                    <tr>
                                        <th style="background:#f7fafc; font-weight:600;">Actual \ Predicted</th>
                                        @foreach ($confusionMatrix['labels'] as $label)
                                            <th style="background:#f7fafc; font-weight:600; text-align:center;">{{ ucfirst($label) }}</th>
                                        @endforeach
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($confusionMatrix['labels'] as $actualLabel)
                                        <tr>
                                            <th style="background:#f7fafc; font-weight:600;">{{ ucfirst($actualLabel) }}</th>
                                            @foreach ($confusionMatrix['labels'] as $predictedLabel)
                                                @php
                                                    $value = $confusionMatrix['matrix'][$actualLabel][$predictedLabel] ?? 0;
                                                    $isCorrect = $actualLabel === $predictedLabel;
                                                    $percentage = $confusionMatrix['total_samples'] > 0 ? ($value / $confusionMatrix['total_samples']) * 100 : 0;
                                                @endphp
                                                <td style="text-align:center; font-weight:600; position:relative; {{ $isCorrect ? 'background:#f0fff4; color:#22543d;' : 'background:#fff5f5; color:#9b2c2c;' }}">
                                                    <div style="font-size:16px;">{{ $value }}</div>
                                                    <div style="font-size:10px; opacity:0.7;">{{ number_format($percentage, 1) }}%</div>
                                                </td>
                                            @endforeach
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Detailed Analysis -->
                    <div style="background:#f7fafc; border:1px solid #e2e8f0; border-radius:8px; padding:16px; margin:20px 0;">
                        <h4 style="margin:0 0 12px; font-size:14px;">🔍 Analisis Detail per Kategori</h4>
                        <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(200px, 1fr)); gap:12px;">
                            @foreach ($confusionMatrix['labels'] as $label)
                                @php
                                    $correct = $confusionMatrix['matrix'][$label][$label] ?? 0;
                                    $totalForLabel = 0;
                                    foreach ($confusionMatrix['labels'] as $predLabel) {
                                        $totalForLabel += $confusionMatrix['matrix'][$label][$predLabel] ?? 0;
                                    }
                                    $recall = $totalForLabel > 0 ? ($correct / $totalForLabel) * 100 : 0;
                                    
                                    $totalPredicted = 0;
                                    foreach ($confusionMatrix['labels'] as $actLabel) {
                                        $totalPredicted += $confusionMatrix['matrix'][$actLabel][$label] ?? 0;
                                    }
                                    $precision = $totalPredicted > 0 ? ($correct / $totalPredicted) * 100 : 0;
                                    
                                    // Calculate F1-Score
                                    $f1Score = ($precision + $recall) > 0 ? (2 * $precision * $recall) / ($precision + $recall) : 0;
                                @endphp
                                <div style="background:white; border:1px solid #e2e8f0; border-radius:6px; padding:12px;">
                                    <div style="font-weight:600; margin-bottom:8px; color:#1a202c;">{{ ucfirst($label) }}</div>
                                    <div style="font-size:12px; color:#4a5568; margin-bottom:4px;">
                                        <strong>Recall:</strong> {{ number_format($recall, 1) }}% ({{ $correct }}/{{ $totalForLabel }})
                                    </div>
                                    <div style="font-size:12px; color:#4a5568; margin-bottom:4px;">
                                        <strong>Precision:</strong> {{ number_format($precision, 1) }}% ({{ $correct }}/{{ $totalPredicted }})
                                    </div>
                                    <div style="font-size:12px; color:#4a5568;">
                                        <strong>F1-Score:</strong> {{ number_format($f1Score, 1) }}%
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>

                    <!-- Legend -->
                    <div style="background:#e6f3ff; border:1px solid #b3d9ff; border-radius:8px; padding:12px; margin:16px 0;">
                        <h4 style="margin:0 0 8px; font-size:14px; color:#0066cc;">📖 Panduan Membaca Confusion Matrix</h4>
                        <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(250px, 1fr)); gap:12px; font-size:13px; color:#0066cc;">
                            <div>
                                <strong>🎯 Cara Membaca:</strong><br>
                                • <strong>Baris</strong> = Label sebenarnya (Actual)<br>
                                • <strong>Kolom</strong> = Label yang diprediksi (Predicted)<br>
                                • <strong>Angka</strong> = Jumlah data
                            </div>
                            <div>
                                <strong>✅ Prediksi Benar (Hijau):</strong><br>
                                • Diagonal utama (kiri atas → kanan bawah)<br>
                                • Contoh: Positif → Positif = 45 data
                            </div>
                            <div>
                                <strong>❌ Prediksi Salah (Merah):</strong><br>
                                • Di luar diagonal utama<br>
                                • Contoh: Positif → Negatif = 3 data
                            </div>
                            <div>
                                <strong>📊 Metrik Evaluasi:</strong><br>
                                • <strong>Precision</strong> = TP/(TP+FP)<br>
                                • <strong>Recall</strong> = TP/(TP+FN)<br>
                                • <strong>F1-Score</strong> = 2×(Precision×Recall)/(Precision+Recall)
                            </div>
                        </div>
                    </div>
                </div>
            @endif

            <!-- Info Section -->
            @if (empty($uploadedFiles))
                <div class="status-message status-info">
                    <h4>📋 Cara Menggunakan Evaluasi Model:</h4>
                    <ol style="margin:8px 0; padding-left:20px;">
                        <li><strong>Upload Hasil:</strong> Upload file CSV hasil klasifikasi dari berbagai metode</li>
                        <li><strong>Bandingkan:</strong> Bandingkan performa antar metode (minimal 2 metode)</li>
                        <li><strong>Confusion Matrix:</strong> Analisis detail prediksi untuk metode tertentu</li>
                        <li><strong>Download Report:</strong> Export laporan evaluasi lengkap</li>
                    </ol>
                    <p><strong>Format CSV yang dibutuhkan:</strong> text, actual_label, predicted_label, confidence</p>
                </div>
            @endif
        </div>
    </div>

    <script>
        function toggleSidebar() {
            const sidebar = document.querySelector('.sidebar');
            const isHidden = sidebar.classList.contains('hidden');
            
            if (isHidden) {
                sidebar.classList.remove('hidden');
            } else {
                sidebar.classList.add('hidden');
            }
        }
    </script>
</body>
</html>
