<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="description" content="Aplikasi ujian sekolah (CBT) untuk admin, guru, siswa, dan orang tua: bank soal, jadwal ujian, penilaian, e-learning, dan rapor.">
    <meta name="robots" content="noindex, nofollow">
    <title inertia>{{ config('app.name', 'CBT Sekolah') }}</title>
    <script nonce="{{ \Illuminate\Support\Facades\Vite::cspNonce() }}">
        // Terapkan tema sebelum halaman dilukis agar tidak berkedip terang.
        (() => {
            try {
                const saved = localStorage.getItem('cbt.theme');
                const dark = saved ? saved === 'dark' : window.matchMedia('(prefers-color-scheme: dark)').matches;
                document.documentElement.classList.toggle('dark', dark);
            } catch (error) {
                // localStorage bisa diblokir; abaikan dan pakai tema terang.
            }
        })();
    </script>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @inertiaHead
</head>
<body class="font-sans antialiased">
    @inertia
</body>
</html>