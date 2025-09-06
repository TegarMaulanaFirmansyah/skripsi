<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Preprocessing</title>
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
        @media (min-width:1024px){ .container{ gap:32px } }
    </style>
</head>
<body>
    <div class="container">
        <aside class="sidebar">
            <p class="brand">Menu</p>
            <ul class="nav">
                <li><a href="{{ url('/') }}">Dashboard</a></li>
                <li><a href="{{ url('/preprocessing') }}" class="active">Preprocessing</a></li>
                <li><a href="{{ url('/labelling') }}">Labelling</a></li>
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
                Preprocessing
            </h1>
            @if (session('status'))
                <p class="pill" style="margin-bottom:12px;">{{ session('status') }}</p>
            @endif
            @if (session('error'))
                <p class="pill" style="margin-bottom:12px;background:#fff5f5;border-color:#feb2b2;color:#9b2c2c;">{{ session('error') }}</p>
            @endif

            <form action="{{ route('preprocessing.upload') }}" method="post" enctype="multipart/form-data" style="margin-bottom:16px;">
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

            <form action="{{ route('preprocessing.run') }}" method="post" style="margin-bottom:24px;">
                @csrf
                <div class="row">
                    <button class="btn primary" {{ empty($uploadedPath) ? 'disabled' : '' }}>Preprocessing data</button>
                    @if (!empty($processed))
                        <a class="btn success" href="{{ route('preprocessing.download') }}">Download Data</a>
                    @endif
                </div>
            </form>

            @if (!empty($preview) || !empty($processed))
                <div class="tabs">
                    @if (!empty($preview))
                        <button type="button" class="tab-btn" id="tab-preview" onclick="showTab('preview')">Preview Awal</button>
                    @endif
                    @if (!empty($processed))
                        <button type="button" class="tab-btn" id="tab-result" onclick="showTab('result')">Hasil Preprocessing</button>
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

            @if (!empty($processed))
                <div id="section-result" style="display:none;">
                    <h3 style="margin:20px 0 8px; font-size:16px;">Hasil Preprocessing</h3>
                    <div style="overflow:auto;">
                        <table>
                        <thead>
                            <tr>
                                <th style="width:5%;">No</th>
                                <th style="width:20%;">Tweet Asli</th>
                                <th style="width:15%;">Case Folding</th>
                                <th style="width:15%;">Cleansing</th>
                                <th style="width:15%;">Normalisasi</th>
                                <th style="width:15%;">Tokenizing</th>
                                <th style="width:15%;">Filtering</th>
                                <th style="width:15%;">Stemming</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($processed['rows'] as $i => $r)
                                <tr>
                                    <td style="text-align:center; font-weight:600;">{{ $i + 1 }}</td>
                                    <td style="font-size:12px; line-height:1.3;">{{ $r['raw'] }}</td>
                                    <td style="font-size:12px; line-height:1.3;">{{ $r['case_folding'] }}</td>
                                    <td style="font-size:12px; line-height:1.3;">{{ $r['cleansing'] }}</td>
                                    <td style="font-size:12px; line-height:1.3;">{{ $r['normalisasi'] }}</td>
                                    <td style="font-size:12px; line-height:1.3;">{{ $r['tokenizing'] }}</td>
                                    <td style="font-size:12px; line-height:1.3;">{{ $r['filtering'] }}</td>
                                    <td style="font-size:12px; line-height:1.3;">{{ $r['stemming'] }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                        </table>
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
                // Show sidebar
                sidebar.classList.remove('hidden');
            } else {
                // Hide sidebar
                sidebar.classList.add('hidden');
            }
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


