<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Labelling</title>
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
        .btn.danger { background:#e53e3e; color:#fff; border-color:#e53e3e; }
        .btn.link { background:#fff; }
        .note { font-size:12px; color:#4a5568; }
        .tabs { display:flex; gap:8px; margin:12px 0; }
        .tab-btn { border:1px solid #cbd5e0; background:#fff; color:#1a202c; padding:6px 10px; border-radius:8px; font-weight:600; cursor:pointer; }
        .tab-btn.active { background:#1a202c; color:#fff; border-color:#1a202c; }
        table { width:100%; border-collapse:collapse; font-size:13px; }
        th, td { border:1px solid #e2e8f0; padding:8px; text-align:left; vertical-align:top; }
        th { background:#f7fafc; font-weight:600; }
        .step-diff { background:#f0f9ff; border-left:3px solid #3182ce; padding:4px 8px; margin:2px 0; }
        .step-label { font-weight:600; color:#3182ce; font-size:12px; margin-bottom:4px; }
        .step-content { font-size:13px; line-height:1.4; }
        .pill { display:inline-block; background:#f7fafc; border:1px solid #e2e8f0; border-radius:999px; padding:2px 8px; font-size:12px; }
        .sentiment-positif { background:#f0fff4; border-color:#9ae6b4; color:#22543d; }
        .sentiment-negatif { background:#fff5f5; border-color:#feb2b2; color:#9b2c2c; }
        .sentiment-netral { background:#f7fafc; border-color:#e2e8f0; color:#4a5568; }
        .sentiment-badge { display:inline-block; padding:2px 8px; border-radius:999px; font-size:11px; font-weight:600; }
        .confidence-bar { width:60px; height:4px; background:#e2e8f0; border-radius:2px; overflow:hidden; }
        .confidence-fill { height:100%; background:#3182ce; transition:width 0.3s; }
        .label-form { display:inline-flex; gap:4px; align-items:center; }
        .label-select { padding:2px 6px; border:1px solid #cbd5e0; border-radius:4px; font-size:11px; }
        .label-btn { padding:2px 6px; font-size:10px; }
        @media (min-width:1024px){ .container{ gap:32px } }
    </style>
</head>
<body>
    <div class="container">
        <aside class="sidebar">
            <p class="brand">Menu</p>
            <ul class="nav">
                <li><a href="{{ url('/') }}">Dashboard</a></li>
                <li><a href="{{ url('/preprocessing') }}">Preprocessing</a></li>
                <li><a href="{{ url('/labelling') }}" class="active">Labelling</a></li>
                <li><a href="{{ url('/klasifikasi') }}">Klasifikasi</a></li>
            </ul>
        </aside>
        <div class="content">
            <h1 class="title">
                <button class="menu-toggle" onclick="toggleSidebar()">
                    <span></span>
                    <span></span>
                    <span></span>
                </button>
                Labelling
            </h1>
            @if (session('status'))
                <p class="pill" style="margin-bottom:12px;">{{ session('status') }}</p>
            @endif
            @if (session('error'))
                <p class="pill" style="margin-bottom:12px;background:#fff5f5;border-color:#feb2b2;color:#9b2c2c;">{{ session('error') }}</p>
            @endif

            <form action="{{ route('labelling.upload') }}" method="post" enctype="multipart/form-data" style="margin-bottom:16px;">
                @csrf
                <div class="row">
                    <input type="file" name="csv_file" accept=".csv,text/csv" />
                    <button class="btn">Upload</button>
                    @if (!empty($uploadedPath))
                        <span class="note">File: {{ basename($uploadedPath) }}</span>
                    @endif
                </div>
                @error('csv_file')
                    <div class="note" style="color:#c53030;">{{ $message }}</div>
                @enderror
            </form>

            <form action="{{ route('labelling.run') }}" method="post" style="margin-bottom:16px;">
                @csrf
                <div class="row">
                    <button class="btn primary" {{ empty($uploadedPath) ? 'disabled' : '' }}>Auto Labeling</button>
                    @if (!empty($labeled))
                        <button type="button" class="btn success" onclick="saveAllChanges()">Save All Changes</button>
                        <a class="btn warning" href="{{ route('labelling.download') }}">Download Data</a>
                        <a class="btn" href="{{ route('labelling.cleanup') }}" style="background:#ef4444;color:white;" onclick="return confirm('Yakin ingin membersihkan semua data?')">🗑️ Bersihkan</a>
                    @endif
                </div>
            </form>

            @if (!empty($preview) || !empty($labeled))
                <div class="tabs">
                    @if (!empty($preview))
                        <button type="button" class="tab-btn" id="tab-preview" onclick="showTab('preview')">Preview Awal</button>
                    @endif
                    @if (!empty($labeled))
                        <button type="button" class="tab-btn" id="tab-result" onclick="showTab('result')">Hasil Labelling</button>
                    @endif
                </div>
            @endif

            @if (!empty($preview))
                <div id="section-preview" style="display:none;">
                    <h3 style="margin:12px 0 8px; font-size:16px;">Preview Awal</h3>
                    <div style="overflow:auto;">
                        <table>
                            <thead>
                                <tr>
                                    @foreach ($preview['header'] as $h)
                                        <th>{{ $h }}</th>
                                    @endforeach
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($preview['rows'] as $r)
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

            @if (!empty($labeled))
                <div id="section-result" style="display:none;">
                    <h3 style="margin:20px 0 8px; font-size:16px;">Hasil Labelling</h3>
                    @if (isset($labeled['total_count']) && $labeled['total_count'] > count($labeled['rows']))
                        <div class="pill" style="margin-bottom:12px;background:#fff3cd;border-color:#ffeaa7;color:#856404;">
                            Menampilkan 100 data pertama dari total {{ $labeled['total_count'] }} data. 
                            Download untuk melihat semua hasil.
                        </div>
                    @endif
                    
                    @if (!empty($learnedKeywords) && (count($learnedKeywords['positive'] ?? []) > 0 || count($learnedKeywords['negative'] ?? []) > 0 || count($learnedKeywords['neutral'] ?? []) > 0))
                        <div class="pill" style="margin-bottom:12px;background:#e6f3ff;border-color:#b3d9ff;color:#0066cc;">
                            <strong>🧠 AI Learning:</strong> Sistem telah mempelajari {{ count($learnedKeywords['positive'] ?? []) + count($learnedKeywords['negative'] ?? []) + count($learnedKeywords['neutral'] ?? []) }} keyword baru dari koreksi manual Anda.
                        </div>
                    @endif
                    <div style="overflow:auto;">
                        <table>
                        <thead>
                            <tr>
                                <th style="width:5%;">No</th>
                                <th style="width:40%;">Ulasan Asli</th>
                                <th style="width:15%;">Sentimen</th>
                                <th style="width:15%;">Confidence</th>
                                <th style="width:25%;">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($labeled['rows'] as $i => $r)
                                <tr>
                                    <td style="text-align:center; font-weight:600;">{{ $i + 1 }}</td>
                                    <td style="font-size:12px; line-height:1.3;">{{ $r['raw'] }}</td>
                                    <td style="text-align:center;">
                                        <span class="sentiment-badge sentiment-{{ $r['sentiment'] }}">
                                            {{ ucfirst($r['sentiment']) }}
                                        </span>
                                    </td>
                                    <td style="text-align:center;">
                                        <div class="confidence-bar">
                                            <div class="confidence-fill" style="width: {{ $r['confidence'] * 100 }}%"></div>
                                        </div>
                                        <small style="color:#4a5568;">{{ number_format($r['confidence'] * 100, 1) }}%</small>
                                    </td>
                                    <td>
                                        <select class="label-select" data-row="{{ $i }}" onchange="updateLabel({{ $i }}, this.value)">
                                            <option value="positif" {{ $r['sentiment'] === 'positif' ? 'selected' : '' }}>Positif</option>
                                            <option value="negatif" {{ $r['sentiment'] === 'negatif' ? 'selected' : '' }}>Negatif</option>
                                            <option value="netral" {{ $r['sentiment'] === 'netral' ? 'selected' : '' }}>Netral</option>
                                        </select>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                        </table>
                    </div>
                    
                    @if ($totalPages > 1)
                        <div style="margin-top: 20px; text-align: center;">
                            <div style="display: inline-flex; gap: 8px; align-items: center;">
                                @if ($currentPage > 1)
                                    <a href="{{ route('labelling.page', ['page' => $currentPage - 1]) }}" class="btn">← Sebelumnya</a>
                                @endif
                                
                                <span style="padding: 8px 12px; background: #f7fafc; border: 1px solid #e2e8f0; border-radius: 8px; font-size: 14px;">
                                    Halaman {{ $currentPage }} dari {{ $totalPages }}
                                </span>
                                
                                @if ($currentPage < $totalPages)
                                    <a href="{{ route('labelling.page', ['page' => $currentPage + 1]) }}" class="btn">Selanjutnya →</a>
                                @endif
                            </div>
                            
                            <div style="margin-top: 8px; font-size: 12px; color: #4a5568;">
                                Menampilkan {{ (($currentPage - 1) * $perPage) + 1 }} - {{ min($currentPage * $perPage, $totalData) }} dari {{ $totalData }} data
                            </div>
                        </div>
                    @endif
                </div>
            @endif
        </div>
    </div>

    <script>
        // Store changes locally before saving
        let pendingChanges = {};

        function toggleSidebar() {
            const sidebar = document.querySelector('.sidebar');
            const isHidden = sidebar.classList.contains('hidden');
            
            if (isHidden) {
                // Show sidebar
                sidebar.classList.remove('hidden');
            } else {
                // Hide sidebar
                sidebar.classList.add('hidden');
            }
        }

        // Update label locally
        function updateLabel(rowIndex, sentiment) {
            pendingChanges[rowIndex] = sentiment;
            
            // Update visual feedback
            const row = document.querySelector(`select[data-row="${rowIndex}"]`).closest('tr');
            const sentimentCell = row.querySelector('.sentiment-badge');
            sentimentCell.textContent = sentiment.charAt(0).toUpperCase() + sentiment.slice(1);
            sentimentCell.className = `sentiment-badge sentiment-${sentiment}`;
        }

        // Save all changes
        function saveAllChanges() {
            if (Object.keys(pendingChanges).length === 0) {
                alert('Tidak ada perubahan untuk disimpan.');
                return;
            }

            // Create form data
            const formData = new FormData();
            formData.append('_token', document.querySelector('meta[name="csrf-token"]').getAttribute('content'));
            formData.append('page', {{ $currentPage ?? 1 }});
            
            Object.keys(pendingChanges).forEach(rowIndex => {
                formData.append(`changes[${rowIndex}]`, pendingChanges[rowIndex]);
            });

            // Send request
            fetch('{{ route("labelling.bulk-update") }}', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert(`Berhasil menyimpan ${Object.keys(pendingChanges).length} perubahan.`);
                    pendingChanges = {};
                    location.reload();
                } else {
                    alert('Gagal menyimpan perubahan: ' + (data.message || 'Unknown error'));
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Terjadi kesalahan saat menyimpan.');
            });
        }

        // Tabs logic
        function showTab(name) {
            const previewBtn = document.getElementById('tab-preview');
            const resultBtn = document.getElementById('tab-result');
            const previewSection = document.getElementById('section-preview');
            const resultSection = document.getElementById('section-result');

            if (previewBtn) previewBtn.classList.remove('active');
            if (resultBtn) resultBtn.classList.remove('active');
            if (previewSection) previewSection.style.display = 'none';
            if (resultSection) resultSection.style.display = 'none';

            if (name === 'preview') {
                if (previewBtn) previewBtn.classList.add('active');
                if (previewSection) previewSection.style.display = '';
            } else if (name === 'result') {
                if (resultBtn) resultBtn.classList.add('active');
                if (resultSection) resultSection.style.display = '';
            }
        }

        // Auto select the first available tab
        document.addEventListener('DOMContentLoaded', function() {
            if (document.getElementById('tab-result')) {
                showTab('result');
            } else if (document.getElementById('tab-preview')) {
                showTab('preview');
            }
        });
    </script>
</body>
</html>