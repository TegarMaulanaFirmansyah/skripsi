<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        body { margin: 0; font-family: 'Poppins', system-ui, -apple-system, Segoe UI, Roboto, 'Helvetica Neue', Arial, 'Noto Sans', 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol'; background:#f7fafc; color:#1a202c; }
        .container { min-height: 100vh; display:flex; align-items:stretch; justify-content:center; padding:32px; gap:24px; }
        .sidebar { width: 260px; background:#ffffff; border-radius:16px; box-shadow:0 10px 30px rgba(0,0,0,.08); padding:24px; height: auto; display:flex; flex-direction:column; }
        .brand { font-weight:700; font-size:18px; margin:0 0 16px; letter-spacing:.3px; }
        .nav { list-style:none; padding:0; margin:0; display:flex; flex-direction:column; gap:8px; }
        .nav a { display:block; padding:10px 12px; border-radius:10px; color:#1a202c; text-decoration:none; font-weight:600; font-size:14px; border:1px solid transparent; }
        .nav a:hover { background:#f7fafc; border-color:#e2e8f0; }
        .nav a.active { background:#edf2f7; border-color:#cbd5e0; }
        .card { flex:1; background:#ffffff; border-radius:16px; box-shadow:0 10px 30px rgba(0,0,0,.08); padding:32px; text-align:center; }
        .title { font-size:24px; line-height:1.4; font-weight:700; text-transform:uppercase; letter-spacing:.5px; margin:0 0 24px; }
        .logo { width:220px; height:auto; display:block; margin:0 auto 8px; }
        .subtitle { font-size:14px; color:#4a5568; margin:0; }
        @media (min-width:640px){ .title{ font-size:28px } .logo{ width:260px } }
        @media (min-width:1024px){ .title{ font-size:32px } .logo{ width:280px } .container{ gap:32px } }
    </style>
    <!-- Place your image at public/images/logo.png -->
</head>
<body>
    <div class="container">
        <aside class="sidebar">
            <p class="brand">Menu</p>
            <ul class="nav">
                <li><a href="{{ url('/') }}" class="{{ request()->is('/') ? 'active' : '' }}">🏠 Dashboard</a></li>
                <li><a href="{{ url('/preprocessing') }}" class="{{ request()->is('preprocessing') ? 'active' : '' }}">🔧 Preprocessing</a></li>
                <li><a href="{{ url('/labelling') }}" class="{{ request()->is('labelling') ? 'active' : '' }}">🏷️ Labelling</a></li>
                <li><a href="{{ url('/klasifikasi') }}" class="{{ request()->is('klasifikasi') ? 'active' : '' }}">🤖 Klasifikasi</a></li>
                <li><a href="{{ url('/evaluasi') }}" class="{{ request()->is('evaluasi') ? 'active' : '' }}">📈 Evaluasi</a></li>
            </ul>
        </aside>
        <div class="card">
            <h1 class="title">ANALISIS SENTIMEN MASYARAKAT TERHADAP PINJAMAN ONLINE MENGGUNAKAN METODE SUPPORT VECTOR MACHINE</h1>
            <img src="{{ asset('images/logo.png') }}" alt="Logo" class="logo">
            <p class="subtitle">Dashboard</p>
        </div>
    </div>
</body>
</html>


