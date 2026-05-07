<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Klasifikasi Sentimen</title>
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
        .confidence-bar { width:60px; height:4px; background:#e2e8f0; border-radius:2px; overflow:hidden; }
        .confidence-fill { height:100%; background:#3182ce; transition:width 0.3s; }
        .correct { background:#f0fff4; }
        .incorrect { background:#fff5f5; }
        @keyframes spin { to { transform: rotate(360deg); } }
        @media (min-width:1024px){ .container{ gap:32px } }
    </style>
</head>
<body>
    <div class="container">
        <aside class="sidebar">
            <p class="brand">Menu</p>
            <ul class="nav">
                <li><a href="{{ url('/') }}">Dashboard</a></li>
                <li><a href="{{ url('/labelling') }}">Labelling</a></li>
                <li><a href="{{ url('/review') }}">Review Data</a></li>
                <li><a href="{{ url('/preprocessing') }}">Preprocessing</a></li>
                <li><a href="{{ url('/klasifikasi') }}" class="active">Klasifikasi</a></li>
                <li><a href="{{ url('/evaluasi') }}">Evaluasi</a></li>
            </ul>
        </aside>
        <div class="content">
            <h1 class="title">
                <button class="menu-toggle" onclick="toggleSidebar()">
                    <span></span>
                    <span></span>
                    <span></span>
                </button>
                Klasifikasi Sentimen SVM
            </h1>
            @if (session('status'))
                <p class="pill" style="margin-bottom:12px;">{{ session('status') }}</p>
            @endif
            @if (session('error'))
                <p class="pill" style="margin-bottom:12px;background:#fff5f5;border-color:#feb2b2;color:#9b2c2c;">{{ session('error') }}</p>
            @endif

            <!-- Upload Training Data -->
            <div class="upload-section">
                <h3>📚 Data Training</h3>
                <form action="{{ route('classification.upload.training') }}" method="post" enctype="multipart/form-data" style="margin-bottom:12px;">
                    @csrf
                    <div class="row">
                        <input type="file" name="csv_file" accept=".csv,text/csv" />
                        <button class="btn">Upload Training</button>
                        @if (!empty($trainingPath))
                            <span class="note">File: {{ basename($trainingPath) }}</span>
                        @endif
                    </div>
                    @error('csv_file')
                        <div class="note" style="color:#c53030;">{{ $message }}</div>
                    @enderror
                </form>
            </div>

            <!-- Upload Testing Data -->
            <div class="upload-section">
                <h3>🧪 Data Testing</h3>
                <form action="{{ route('classification.upload.testing') }}" method="post" enctype="multipart/form-data" style="margin-bottom:16px;">
                    @csrf
                    <div class="row">
                        <input type="file" name="csv_file" accept=".csv,text/csv" />
                        <button class="btn">Upload Testing</button>
                        @if (!empty($testingPath))
                            <span class="note">File: {{ basename($testingPath) }}</span>
                        @endif
                    </div>
                    @error('csv_file')
                        <div class="note" style="color:#c53030;">{{ $message }}</div>
                    @enderror
                </form>
            </div>

            <!-- Run Classification -->
            <form action="{{ route('classification.run') }}" method="post" style="margin-bottom:24px;" id="classificationForm">
                @csrf
                <div class="row">
                    <button class="btn primary" id="runButton" {{ (empty($trainingPath) || empty($testingPath)) ? 'disabled' : '' }}>🚀 Jalankan SVM Classification</button>
                    @if (!empty($results))
                        <a class="btn success" href="{{ route('classification.download') }}">📥 Download Hasil</a>
                        <a class="btn" href="{{ route('classification.cleanup') }}" style="background:#ef4444;color:white;" onclick="return confirm('Yakin ingin membersihkan semua data?')">🗑️ Bersihkan</a>
                    @endif
                </div>
            </form>

            <!-- Loading Indicator with TF-IDF Steps -->
            <div id="loadingIndicator" style="display:none; margin-bottom:24px; padding:16px; background:#e6f3ff; border:1px solid #b3d9ff; border-radius:8px;">
                <h4 style="margin:0 0 12px; color:#0066cc; text-align:center;">🔄 Processing Steps:</h4>
                
                <div style="display:flex; flex-direction:column; gap:8px; margin-bottom:16px;">
                    <div id="step-vocabulary" class="processing-step">
                        <div style="display:flex; align-items:center; gap:8px;">
                            <span style="font-size:12px; color:#475569;">📚 Building Vocabulary...</span>
                            <div class="progress-bar">
                                <div class="progress-fill"></div>
                            </div>
                        </div>
                    </div>
                    <div id="step-tfidf" class="processing-step">
                        <div style="display:flex; align-items:center; gap:8px;">
                            <span style="font-size:12px; color:#475569;">🔤 Computing TF-IDF...</span>
                            <div class="progress-bar">
                                <div class="progress-fill"></div>
                            </div>
                        </div>
                    </div>
                    <div id="step-training" class="processing-step">
                        <div style="display:flex; align-items:center; gap:8px;">
                            <span style="font-size:12px; color:#475569;">🤖 Training SVM Model...</span>
                            <div class="progress-bar">
                                <div class="progress-fill"></div>
                            </div>
                        </div>
                    </div>
                    <div id="step-prediction" class="processing-step">
                        <div style="display:flex; align-items:center; gap:8px;">
                            <span style="font-size:12px; color:#475569;">🎯 Making Predictions...</span>
                            <div class="progress-bar">
                                <div class="progress-fill"></div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div style="text-align:center;">
                    <div style="width:24px; height:24px; border:3px solid #4299e1; border-top-color:#90cdf4; border-radius:50%; animation:spin 1s linear infinite; display:inline-block;"></div>
                    <span style="color:#0066cc; font-weight:600;">Sedang memproses data...</span>
                </div>
            </div>

            <style>
            .processing-step {
                background:#f8fafc;
                border:1px solid #e2e8f0;
                border-radius:6px;
                padding:8px;
                opacity:0.5;
                transition:all 0.3s ease;
            }
            
            .processing-step.active {
                opacity:1;
                background:#dbeafe;
                border-color:#3b82f6;
            }
            
            .progress-bar {
                flex:1;
                height:4px;
                background:#e2e8f0;
                border-radius:2px;
                overflow:hidden;
            }
            
            .progress-fill {
                height:100%;
                width:0%;
                background:#3b82f6;
                transition:width 0.3s ease;
            }
            
            .processing-step.active .progress-fill {
                animation:progress 2s ease-in-out infinite;
            }
            
            @keyframes progress {
                0% { width:0%; }
                50% { width:70%; }
                100% { width:100%; }
            }
            
            @keyframes spin {
                0% { transform:rotate(0deg); }
                100% { transform:rotate(360deg); }
            }
            </style>

            @if (!empty($trainingPreview) || !empty($testingPreview) || !empty($results))
                <div class="tabs">
                    @if (!empty($trainingPreview))
                        <button type="button" class="tab-btn" id="tab-training" onclick="showTab('training')">Data Training</button>
                    @endif
                    @if (!empty($testingPreview))
                        <button type="button" class="tab-btn" id="tab-testing" onclick="showTab('testing')">Data Testing</button>
                    @endif
                    @if (!empty($results))
                        <button type="button" class="tab-btn" id="tab-results" onclick="showTab('results')">Hasil Klasifikasi</button>
                    @endif
                </div>
            @endif

            @if (!empty($trainingPreview))
                <div id="section-training" style="display:none;">
                    <h3 style="margin:12px 0 8px; font-size:16px;">📚 Preview Data Training</h3>
                    <div style="overflow:auto;">
                        <table>
                            <thead>
                                <tr>
                                    @foreach ($trainingPreview['header'] as $h)
                                        <th>{{ $h }}</th>
                                    @endforeach
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($trainingPreview['rows'] as $r)
                                    <tr>
                                        @foreach ($r as $c)
                                            <td>{{ $c }}</td>
                                        @endforeach
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif

            @if (!empty($testingPreview))
                <div id="section-testing" style="display:none;">
                    <h3 style="margin:12px 0 8px; font-size:16px;">🧪 Preview Data Testing</h3>
                    <div style="overflow:auto;">
                        <table>
                            <thead>
                                <tr>
                                    @foreach ($testingPreview['header'] as $h)
                                        <th>{{ $h }}</th>
                                    @endforeach
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($testingPreview['rows'] as $r)
                                    <tr>
                                        @foreach ($r as $c)
                                            <td>{{ $c }}</td>
                                        @endforeach
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif

            @if (!empty($results))
                <div id="section-results" style="display:none;">
                    <h3 style="margin:20px 0 8px; font-size:16px;">📊 Hasil Klasifikasi SVM</h3>
                    
                    <!-- TF-IDF Information Section -->
                    <div style="background:#f0f9ff; border:1px solid #bae6fd; border-radius:8px; padding:16px; margin-bottom:20px;">
                        <h4 style="margin:0 0 12px; font-size:14px; color:#0369a1;">🔤 TF-IDF Processing Information</h4>
                        
                        <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(200px, 1fr)); gap:12px; margin-bottom:16px;">
                            <div style="background:white; padding:12px; border-radius:6px; box-shadow:0 1px 3px rgba(0,0,0,0.1);">
                                <div style="font-size:18px; font-weight:700; color:#0369a1;">{{ $results['tfidf_info']['vocabulary_size'] ?? 0 }}</div>
                                <div style="font-size:11px; color:#64748b;">Vocabulary Size</div>
                            </div>
                            <div style="background:white; padding:12px; border-radius:6px; box-shadow:0 1px 3px rgba(0,0,0,0.1);">
                                <div style="font-size:18px; font-weight:700; color:#0369a1;">{{ $results['tfidf_info']['total_documents'] ?? 0 }}</div>
                                <div style="font-size:11px; color:#64748b;">Total Documents</div>
                            </div>
                            <div style="background:white; padding:12px; border-radius:6px; box-shadow:0 1px 3px rgba(0,0,0,0.1);">
                                <div style="font-size:18px; font-weight:700; color:#0369a1;">{{ number_format($results['tfidf_info']['avg_idf'] ?? 0, 3) }}</div>
                                <div style="font-size:11px; color:#64748b;">Average IDF</div>
                            </div>
                            <div style="background:white; padding:12px; border-radius:6px; box-shadow:0 1px 3px rgba(0,0,0,0.1);">
                                <div style="font-size:18px; font-weight:700; color:#0369a1;">{{ $results['tfidf_info']['vector_dimensions'] ?? 0 }}</div>
                                <div style="font-size:11px; color:#64748b;">Vector Dimensions</div>
                            </div>
                        </div>
                        
                        <!-- Sample Vocabulary & IDF Values -->
                        @if(isset($results['tfidf_info']['sample_vocabulary']))
                        <div style="background:white; padding:12px; border-radius:6px; box-shadow:0 1px 3px rgba(0,0,0,0.1);">
                            <h5 style="margin:0 0 8px; font-size:12px; color:#475569;">Sample Vocabulary & IDF Values:</h5>
                            <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(200px, 1fr)); gap:6px; font-size:11px;">
                                @foreach($results['tfidf_info']['sample_vocabulary'] as $index => $word)
                                    <div style="display:flex; justify-content:space-between; align-items:center; padding:4px 6px; background:#f8fafc; border-radius:4px; border:1px solid #e2e8F0;">
                                        <span style="font-weight:600; color:#1e293b;">{{ $word }}</span>
                                        <span style="color:#0369a1; font-weight:500;">IDF: {{ number_format($results['tfidf_info']['sample_idf_values'][$index] ?? 0, 3) }}</span>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                        @endif

                        <!-- TF Information -->
                        @if(!empty($results['tfidf_info']['sample_tf_values']))
                        <div style="background:white; padding:12px; border-radius:6px; box-shadow:0 1px 3px rgba(0,0,0,0.1);">
                            <h5 style="margin:0 0 8px; font-size:12px; color:#475569;">Sample Term Frequency (TF) Values:</h5>
                            <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(200px, 1fr)); gap:6px; font-size:11px;">
                                @foreach($results['tfidf_info']['sample_tf_values'] as $index => $tf)
                                    <div style="display:flex; justify-content:space-between; align-items:center; padding:4px 6px; background:#fef3c7; border-radius:4px; border:1px solid #fde68a;">
                                        <span style="font-weight:600; color:#92400e;">{{ $tf['term'] ?? 'N/A' }}</span>
                                        <span style="color:#92400e; font-weight:500;">TF: {{ number_format($tf['tf'] ?? 0, 3) }}</span>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                        @elseif(isset($results['tfidf_info']))
                        <div style="background:white; padding:12px; border-radius:6px; box-shadow:0 1px 3px rgba(0,0,0,0.1);">
                            <h5 style="margin:0 0 8px; font-size:12px; color:#475569;">Sample Term Frequency (TF) Values:</h5>
                            <p style="margin:0; font-size:12px; color:#475569;">TF values belum tersedia untuk dokumen sampel. Pastikan data training sudah terupload dengan teks yang valid.</p>
                        </div>
                        @endif

                        <!-- SVM Process Explanation -->
                        <div style="background:white; padding:12px; border-radius:6px; box-shadow:0 1px 3px rgba(0,0,0,0.1);">
                            <h5 style="margin:0 0 8px; font-size:12px; color:#475569;">🧠 Proses Support Vector Machine (SVM)</h5>
                            <div style="font-size:11px; line-height:1.5; color:#374151;">
                                <p style="margin:0 0 8px;"><strong>1. Feature Extraction (TF-IDF):</strong></p>
                                <ul style="margin:0 0 8px 16px; padding:0;">
                                    <li><strong>TF (Term Frequency):</strong> Menghitung seberapa sering kata muncul dalam dokumen. Formula: TF = (jumlah kata dalam dokumen) / (total kata dalam dokumen)</li>
                                    <li><strong>IDF (Inverse Document Frequency):</strong> Menghitung kepentingan kata. Kata yang jarang muncul di banyak dokumen memiliki nilai IDF tinggi. Formula yang dipakai di kode: IDF = log((total dokumen + 1) / (jumlah dokumen yang mengandung kata + 1)) + 1</li>
                                    <li><strong>TF-IDF:</strong> Kombinasi TF dan IDF untuk memberikan bobot pada kata. Kata yang sering muncul dalam dokumen tapi jarang di corpus lain akan memiliki TF-IDF tinggi.</li>
                                </ul>
                                @if(isset($results['tfidf_info']['label_distribution']))
                                    <p style="margin:0 0 8px;"><strong>Distribusi label training:</strong></p>
                                    <ul style="margin:0 0 8px 16px; padding:0;">
                                        @foreach($results['tfidf_info']['label_distribution'] as $label => $count)
                                            <li>{{ ucfirst($label) }}: {{ $count }} sampel</li>
                                        @endforeach
                                    </ul>
                                @endif
                                @if(isset($results['tfidf_info']['error']))
                                    <p style="margin:0; color:#b91c1c;"><strong>TF-IDF Error:</strong> {{ $results['tfidf_info']['error'] }}</p>
                                @endif
                            </div>
                                
                                <p style="margin:12px 0 8px;"><strong>2. SVM Training (One-vs-Rest):</strong></p>
                                <ul style="margin:0 0 8px 16px; padding:0;">
                                    <li><strong>Multi-class Classification:</strong> Untuk 3 kelas (positif, negatif, netral), SVM membangun 3 classifier binary</li>
                                    <li><strong>Hyperplane:</strong> Mencari hyperplane optimal yang memisahkan data antar kelas dengan margin maksimum</li>
                                    <li><strong>Support Vectors:</strong> Data points yang terdekat dengan hyperplane dan menentukan posisi hyperplane</li>
                                    <li><strong>Linear Kernel:</strong> Kode menggunakan SVM linear pada implementasi saat ini</li>
                                </ul>
                                
                                <p style="margin:12px 0 8px;"><strong>3. Prediction:</strong></p>
                                <ul style="margin:0 0 8px 16px; padding:0;">
                                    <li><strong>Vector Transform:</strong> Data testing diubah ke vector TF-IDF yang sama dengan training data</li>
                                    <li><strong>Prediction Score:</strong> Model menghasilkan probabilitas untuk masing-masing kelas</li>
                                    <li><strong>Confidence Score:</strong> Nilai confidence diambil dari probabilitas model, bukan langsung dari jarak hyperplane</li>
                                    <li><strong>Final Decision:</strong> Kelas dengan confidence tertinggi dipilih sebagai prediksi</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Metrics Summary -->
                    <div class="metrics-grid">
                        <div class="metric-card">
                            <div class="metric-value">{{ number_format($results['accuracy'] * 100, 2) }}%</div>
                            <div class="metric-label">Akurasi</div>
                        </div>
                        <div class="metric-card">
                            <div class="metric-value">{{ $results['correct_predictions'] }}</div>
                            <div class="metric-label">Benar</div>
                        </div>
                        <div class="metric-card">
                            <div class="metric-value">{{ $results['total_samples'] - $results['correct_predictions'] }}</div>
                            <div class="metric-label">Salah</div>
                        </div>
                        <div class="metric-card">
                            <div class="metric-value">{{ $results['total_samples'] }}</div>
                            <div class="metric-label">Total Sample</div>
                        </div>
                    </div>

                    <!-- Detailed Metrics -->
                    <h4 style="margin:20px 0 8px; font-size:14px;">📈 Detail Metrik per Kategori</h4>
                    <div style="overflow:auto; margin-bottom:20px;">
                        <table>
                            <thead>
                                <tr>
                                    <th>Kategori</th>
                                    <th>Precision</th>
                                    <th>Recall</th>
                                    <th>F1-Score</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($results['metrics'] as $label => $metric)
                                    <tr>
                                        <td><span class="sentiment-badge sentiment-{{ $label }}">{{ ucfirst($label) }}</span></td>
                                        <td>{{ number_format($metric['precision'] * 100, 2) }}%</td>
                                        <td>{{ number_format($metric['recall'] * 100, 2) }}%</td>
                                        <td>{{ number_format($metric['f1_score'] * 100, 2) }}%</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <!-- Note about detailed predictions -->
                    <div style="background:#e6f3ff; border:1px solid #b3d9ff; border-radius:8px; padding:16px; margin:20px 0;">
                        <h4 style="margin:0 0 8px; font-size:14px; color:#0066cc;">📋 Informasi Detail Prediksi</h4>
                        <p style="margin:0; font-size:13px; color:#0066cc;">
                            Detail prediksi untuk setiap data testing telah disimpan dan dapat di-download melalui tombol "Download Hasil" di atas. 
                            File CSV akan berisi kolom: text, actual_label, predicted_label, dan confidence.
                        </p>
                    </div>
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

        function showTab(name) {
            const trainingBtn = document.getElementById('tab-training');
            const testingBtn = document.getElementById('tab-testing');
            const resultsBtn = document.getElementById('tab-results');
            const trainingSection = document.getElementById('section-training');
            const testingSection = document.getElementById('section-testing');
            const resultsSection = document.getElementById('section-results');

            if (trainingBtn) trainingBtn.classList.remove('active');
            if (testingBtn) testingBtn.classList.remove('active');
            if (resultsBtn) resultsBtn.classList.remove('active');
            
            if (trainingSection) trainingSection.style.display = 'none';
            if (testingSection) testingSection.style.display = 'none';
            if (resultsSection) resultsSection.style.display = 'none';

            if (name === 'training') {
                if (trainingBtn) trainingBtn.classList.add('active');
                if (trainingSection) trainingSection.style.display = '';
            } else if (name === 'testing') {
                if (testingBtn) testingBtn.classList.add('active');
                if (testingSection) testingSection.style.display = '';
            } else if (name === 'results') {
                if (resultsBtn) resultsBtn.classList.add('active');
                if (resultsSection) resultsSection.style.display = '';
            }
        }

        // Handle form submission with loading indicator and step progress
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.getElementById('classificationForm');
            const runButton = document.getElementById('runButton');
            const loadingIndicator = document.getElementById('loadingIndicator');

            if (form && runButton && loadingIndicator) {
                form.addEventListener('submit', function() {
                    runButton.disabled = true;
                    runButton.style.opacity = '0.6';
                    loadingIndicator.style.display = 'block';
                    
                    // Start step progress animation
                    showStepProgress();
                });
            }

            if (document.getElementById('tab-results')) {
                showTab('results');
            } else if (document.getElementById('tab-testing')) {
                showTab('testing');
            } else if (document.getElementById('tab-training')) {
                showTab('training');
            }
        });

        // Function to show step progress animation
        function showStepProgress() {
            const steps = ['vocabulary', 'tfidf', 'training', 'prediction'];
            const delays = [0, 1000, 2000, 3000]; // Delay in milliseconds
            
            steps.forEach((step, index) => {
                setTimeout(() => {
                    // Remove active class from all steps
                    document.querySelectorAll('.processing-step').forEach(el => {
                        el.classList.remove('active');
                    });
                    
                    // Add active class to current step
                    const currentStep = document.getElementById('step-' + step);
                    if (currentStep) {
                        currentStep.classList.add('active');
                    }
                }, delays[index]);
            });
        }
    </script>
</body>
</html>
