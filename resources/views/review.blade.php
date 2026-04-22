<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Review Data</title>
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
        .btn.danger { background:#e53e3e; color:#fff; border-color:#e53e3e; }
        .note { font-size:12px; color:#4a5568; }
        .pill { display:inline-block; background:#f7fafc; border:1px solid #e2e8f0; border-radius:999px; padding:2px 8px; font-size:12px; }
        .sentiment-positif { background:#f0fff4; border-color:#9ae6b4; color:#22543d; }
        .sentiment-negatif { background:#fff5f5; border-color:#feb2b2; color:#9b2c2c; }
        .sentiment-netral { background:#f7fafc; border-color:#e2e8f0; color:#4a5568; }
        .sentiment-badge { display:inline-block; padding:2px 8px; border-radius:999px; font-size:11px; font-weight:600; }
        
        /* Sentiment Distribution Styles */
        .stats-container { display:grid; grid-template-columns:repeat(auto-fit, minmax(200px, 1fr)); gap:16px; margin:20px 0; }
        .stat-card { background:#ffffff; border:1px solid #e2e8f0; border-radius:12px; padding:16px; text-align:center; box-shadow:0 2px 8px rgba(0,0,0,0.05); }
        .stat-number { font-size:28px; font-weight:700; margin:8px 0 4px; }
        .stat-label { font-size:12px; color:#718096; text-transform:uppercase; letter-spacing:0.5px; }
        .stat-percentage { font-size:14px; font-weight:600; margin-top:4px; }
        .chart-container { background:#ffffff; border:1px solid #e2e8f0; border-radius:12px; padding:20px; margin:20px 0; box-shadow:0 2px 8px rgba(0,0,0,0.05); }
        .chart-title { font-size:16px; font-weight:600; margin:0 0 16px; color:#1a202c; }
        .chart-bars { display:flex; gap:12px; align-items:flex-end; height:120px; margin-bottom:8px; }
        .chart-bar { flex:1; background:linear-gradient(135deg, #667eea 0%, #764ba2 100%); border-radius:8px 8px 0 0; position:relative; min-height:20px; transition:all 0.3s ease; }
        .chart-bar:hover { transform:scale(1.05); }
        .chart-bar.positif { background:linear-gradient(135deg, #48bb78 0%, #38a169 100%); }
        .chart-bar.negatif { background:linear-gradient(135deg, #f56565 0%, #e53e3e 100%); }
        .chart-bar.netral { background:linear-gradient(135deg, #4299e1 0%, #3182ce 100%); }
        .chart-label { font-size:12px; text-align:center; font-weight:600; color:#4a5568; }
        .chart-value { position:absolute; top:-20px; left:50%; transform:translateX(-50%); font-size:12px; font-weight:600; color:#1a202c; }
        .summary-box { background:#f7fafc; border-left:4px solid #3182ce; padding:12px 16px; margin:16px 0; border-radius:0 8px 8px 0; }
        .summary-title { font-weight:600; color:#2d3748; margin-bottom:4px; }
        .summary-text { font-size:14px; color:#4a5568; line-height:1.5; }
        .section-header { display:flex; justify-content:space-between; align-items:center; margin:24px 0 16px; padding-bottom:8px; border-bottom:2px solid #e2e8f0; }
        .section-title { font-size:18px; font-weight:600; color:#1a202c; margin:0; }
        .upload-area { border:2px dashed #cbd5e0; border-radius:12px; padding:32px; text-align:center; margin:20px 0; transition:all 0.3s ease; }
        .upload-area:hover { border-color:#3182ce; background:#f7fafc; }
        .upload-icon { font-size:48px; margin-bottom:16px; }
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
                <li><a href="{{ url('/review') }}" class="active">Review Data</a></li>
                <li><a href="{{ url('/preprocessing') }}">Preprocessing</a></li>
                <li><a href="{{ url('/klasifikasi') }}">Klasifikasi</a></li>
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
                Review Data
            </h1>
            
            @if (session('status'))
                <p class="pill" style="margin-bottom:12px;">{{ session('status') }}</p>
            @endif
            @if (session('error'))
                <p class="pill" style="margin-bottom:12px;background:#fff5f5;border-color:#feb2b2;color:#9b2c2c;">{{ session('error') }}</p>
            @endif

            <!-- Upload Section -->
            <div class="upload-area">
                <div class="upload-icon">Upload</div>
                <h3 style="margin:0 0 8px; color:#1a202c;">Upload File Hasil Labelling</h3>
                <p style="margin:0 0 16px; color:#718096;">Upload file CSV yang berisi data dengan kolom sentiment/label</p>
                
                <form action="{{ route('review.upload') }}" method="post" enctype="multipart/form-data">
                    @csrf
                    <div class="row" style="justify-content:center;">
                        <input type="file" name="csv_file" accept=".csv,text/csv" required />
                        <button class="btn primary">Upload & Review</button>
                    </div>
                    @if (!empty($uploadedPath))
                        <div class="note" style="margin-top:12px;">File: {{ basename($uploadedPath) }}</div>
                    @endif
                </form>
            </div>

            @if (!empty($sentimentDistribution) && $sentimentDistribution['total'] > 0)
                <!-- Sentiment Distribution Statistics -->
                <div class="section-header">
                    <h2 class="section-title">Statistik Sebaran Sentiment</h2>
                    <span class="pill">{{ $sentimentDistribution['total'] }} Total Data</span>
                </div>
                
                <div class="stats-container">
                    <div class="stat-card">
                        <div class="stat-label">Total Data</div>
                        <div class="stat-number">{{ $sentimentDistribution['total'] }}</div>
                        <div style="font-size:12px; color:#718096;">Ulasan</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-label sentiment-positif" style="background:#f0fff4;border:1px solid #9ae6b4;color:#22543d;padding:4px 8px;border-radius:4px;display:inline-block;margin-bottom:8px;">Positif</div>
                        <div class="stat-number" style="color:#22543d;">{{ $sentimentDistribution['positif'] }}</div>
                        <div class="stat-percentage" style="color:#22543d;">{{ $sentimentDistribution['positif_percentage'] }}%</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-label sentiment-negatif" style="background:#fff5f5;border:1px solid #feb2b2;color:#9b2c2c;padding:4px 8px;border-radius:4px;display:inline-block;margin-bottom:8px;">Negatif</div>
                        <div class="stat-number" style="color:#9b2c2c;">{{ $sentimentDistribution['negatif'] }}</div>
                        <div class="stat-percentage" style="color:#9b2c2c;">{{ $sentimentDistribution['negatif_percentage'] }}%</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-label sentiment-netral" style="background:#f7fafc;border:1px solid #e2e8f0;color:#4a5568;padding:4px 8px;border-radius:4px;display:inline-block;margin-bottom:8px;">Netral</div>
                        <div class="stat-number" style="color:#4a5568;">{{ $sentimentDistribution['netral'] }}</div>
                        <div class="stat-percentage" style="color:#4a5568;">{{ $sentimentDistribution['netral_percentage'] }}%</div>
                    </div>
                </div>
                
                <!-- Chart Visualization -->
                <div class="chart-container">
                    <h4 class="chart-title">Visualisasi Sebaran Sentiment</h4>
                    <div class="chart-bars">
                        <div class="chart-bar positif" style="height: {{ max(20, $sentimentDistribution['positif_percentage'] * 1.2) }}px;">
                            <div class="chart-value">{{ $sentimentDistribution['positif_percentage'] }}%</div>
                        </div>
                        <div class="chart-bar negatif" style="height: {{ max(20, $sentimentDistribution['negatif_percentage'] * 1.2) }}px;">
                            <div class="chart-value">{{ $sentimentDistribution['negatif_percentage'] }}%</div>
                        </div>
                        <div class="chart-bar netral" style="height: {{ max(20, $sentimentDistribution['netral_percentage'] * 1.2) }}px;">
                            <div class="chart-value">{{ $sentimentDistribution['netral_percentage'] }}%</div>
                        </div>
                    </div>
                    <div class="chart-label" style="display:flex; gap:12px;">
                        <div style="flex:1;">Positif</div>
                        <div style="flex:1;">Negatif</div>
                        <div style="flex:1;">Netral</div>
                    </div>
                </div>
                
                <!-- Summary Analysis -->
                <div class="summary-box">
                    <div class="summary-title">Analisis Sebaran Data</div>
                    <div class="summary-text">
                        @if ($sentimentDistribution['positif'] > $sentimentDistribution['negatif'] && $sentimentDistribution['positif'] > $sentimentDistribution['netral'])
                            <strong>Positif Mendominasi!</strong> Sebagian besar ulasan ({{ $sentimentDistribution['positif_percentage'] }}%) memiliki sentimen positif. Ini menunjukkan bahwa konten secara umum memiliki nuansa yang baik.
                        @elseif ($sentimentDistribution['negatif'] > $sentimentDistribution['positif'] && $sentimentDistribution['negatif'] > $sentimentDistribution['netral'])
                            <strong>Negatif Mendominasi!</strong> Sebagian besar ulasan ({{ $sentimentDistribution['negatif_percentage'] }}%) memiliki sentimen negatif. Perlu perhatian khusus pada konten negatif.
                        @elseif ($sentimentDistribution['netral'] > $sentimentDistribution['positif'] && $sentimentDistribution['netral'] > $sentimentDistribution['negatif'])
                            <strong>Netral Mendominasi!</strong> Sebagian besar ulasan ({{ $sentimentDistribution['netral_percentage'] }}%) memiliki sentimen netral. Konten cenderung informatif dan objektif.
                        @else
                            <strong>Sebaran Seimbang!</strong> Sebaran sentiment cukup seimbang dengan variasi yang menarik. Data memiliki diversitas sentiment yang baik.
                        @endif
                    </div>
                </div>

                <!-- Data Preview Section -->
                @if (!empty($preview))
                    <div class="section-header">
                        <h2 class="section-title">Preview Data</h2>
                        <div style="display:flex; gap:8px; align-items:center;">
                            <span class="pill">100 data pertama</span>
                            <select id="sentiment-filter" onchange="filterData()" style="padding:4px 8px; border:1px solid #cbd5e0; border-radius:6px; font-size:12px;">
                                <option value="all">Semua Sentiment</option>
                                <option value="positif">Positif</option>
                                <option value="negatif">Negatif</option>
                                <option value="netral">Netral</option>
                            </select>
                        </div>
                    </div>
                    
                    <div style="overflow:auto;">
                        <table id="preview-table">
                            <thead>
                                <tr>
                                    <th style="width:5%;">No</th>
                                    <th style="width:55%;">Ulasan</th>
                                    <th style="width:15%;">Sentiment</th>
                                    <th style="width:25%;">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                @php
                                    $sentimentIndex = null;
                                    $textIndex = null;
                                    foreach ($preview['header'] as $i => $h) {
                                        $h_lower = strtolower($h);
                                        if ($sentimentIndex === null && ($h_lower === 'sentiment' || $h_lower === 'label')) {
                                            $sentimentIndex = $i;
                                        }
                                        if ($textIndex === null && ($h_lower === 'raw' || $h_lower === 'text' || $h_lower === 'tweet' || $h_lower === 'content')) {
                                            $textIndex = $i;
                                        }
                                    }
                                @endphp
                                
                                @foreach ($preview['rows'] as $i => $r)
                                    <tr data-sentiment="{{ strtolower($r[$sentimentIndex] ?? 'netral') }}">
                                        <td style="text-align:center; font-weight:600;">{{ $i + 1 }}</td>
                                        <td style="font-size:12px; line-height:1.3;">{{ $r[$textIndex] ?? ($r[0] ?? '') }}</td>
                                        <td style="text-align:center;">
                                            <span class="sentiment-badge sentiment-{{ strtolower($r[$sentimentIndex] ?? 'netral') }}">
                                                {{ ucfirst($r[$sentimentIndex] ?? 'netral') }}
                                            </span>
                                        </td>
                                        <td>
                                            <button class="btn" style="padding:4px 8px; font-size:11px;" onclick="viewDetails({{ $i }})">Lihat Detail</button>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            @else
                <!-- Empty State -->
                <div style="text-align:center; padding:48px; color:#718096;">
                    <div style="font-size:48px; margin-bottom:16px;">Upload</div>
                    <h3 style="margin:0 0 8px; color:#4a5568;">Belum Ada Data</h3>
                    <p style="margin:0;">Upload file CSV hasil labeling untuk melihat sebaran sentiment</p>
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

        function filterData() {
            const filter = document.getElementById('sentiment-filter').value;
            const rows = document.querySelectorAll('#preview-table tbody tr');
            
            rows.forEach(row => {
                const sentiment = row.getAttribute('data-sentiment');
                if (filter === 'all' || sentiment === filter) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        }

        function viewDetails(index) {
            // Get the row data
            const row = document.querySelector(`#preview-table tbody tr:nth-child(${index + 1})`);
            const cells = row.querySelectorAll('td');
            
            const text = cells[1].textContent;
            const sentiment = cells[2].textContent.trim();
            
            // Create modal
            const modal = document.createElement('div');
            modal.style.cssText = `
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                background: rgba(0,0,0,0.5);
                display: flex;
                align-items: center;
                justify-content: center;
                z-index: 1000;
            `;
            
            modal.innerHTML = `
                <div style="background: white; padding: 24px; border-radius: 12px; max-width: 500px; width: 90%; max-height: 80vh; overflow-y: auto;">
                    <h3 style="margin: 0 0 16px; color: #1a202c;">Detail Data</h3>
                    <div style="margin-bottom: 12px;">
                        <strong>Sentiment:</strong> <span class="sentiment-badge sentiment-${sentiment.toLowerCase()}">${sentiment}</span>
                    </div>
                    <div style="margin-bottom: 16px;">
                        <strong>Ulasan:</strong>
                        <p style="margin: 8px 0; padding: 12px; background: #f7fafc; border-radius: 8px; line-height: 1.5;">${text}</p>
                    </div>
                    <button onclick="this.parentElement.parentElement.remove()" style="padding: 8px 16px; background: #3182ce; color: white; border: none; border-radius: 6px; cursor: pointer;">Tutup</button>
                </div>
            `;
            
            document.body.appendChild(modal);
            modal.addEventListener('click', function(e) {
                if (e.target === modal) {
                    modal.remove();
                }
            });
        }
    </script>
</body>
</html>
