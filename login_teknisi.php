<?php
// Login teknisi — cek akun dari database (dibuat admin di Akses Member > Akses Teknisi)
header('Cache-Control: no-store, no-cache, must-revalidate');
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login Teknisi | Tekunin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style> body { font-family: 'Plus Jakarta Sans', sans-serif; } </style>
</head>
<body class="bg-slate-100 min-h-screen flex items-center justify-center p-4">
    <div class="bg-white rounded-3xl shadow-xl border border-slate-200 w-full max-w-sm p-8">
        <div class="text-center mb-6">
            <div class="w-14 h-14 rounded-2xl bg-sky-600 text-white flex items-center justify-center mx-auto mb-3 shadow-lg shadow-sky-600/30">
                <i class="fa-solid fa-screwdriver-wrench text-xl"></i>
            </div>
            <h1 class="font-black text-slate-900 text-lg">Login Teknisi</h1>
            <p class="text-xs text-slate-500 mt-1">Akun dibuat Admin: Akses Member → Akses Teknisi</p>
        </div>
        <form id="login-form" onsubmit="return doLogin(event)" action="javascript:void(0)" method="get" class="space-y-4">
            <div>
                <label class="block text-xs font-bold text-slate-700 mb-1.5 uppercase">Username</label>
                <input type="text" id="user" required autocomplete="username" class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-2xl text-sm font-medium focus:outline-none focus:border-sky-500" placeholder="Username dari admin">
            </div>
            <div>
                <label class="block text-xs font-bold text-slate-700 mb-1.5 uppercase">Password</label>
                <input type="password" id="pass" required autocomplete="current-password" class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-2xl text-sm font-medium focus:outline-none focus:border-sky-500" placeholder="Password">
            </div>
            <div id="err" class="hidden text-xs text-rose-600 font-bold bg-rose-50 border border-rose-100 p-3 rounded-xl text-center"></div>
            <button type="submit" id="btn-login" class="w-full bg-sky-600 hover:bg-sky-500 text-white font-extrabold py-3.5 rounded-2xl shadow-lg shadow-sky-600/20 active:scale-95 text-sm transition-all">Masuk Work Order</button>
        </form>
    </div>
    <script>
        try { if (sessionStorage.getItem('teknisi_logged') === 'true') window.location.replace('teknisi.php'); } catch (e) {}
        async function doLogin(e) {
            if (e) e.preventDefault();
            var u = (document.getElementById('user').value || '').trim();
            var p = (document.getElementById('pass').value || '').trim();
            var err = document.getElementById('err');
            var btn = document.getElementById('btn-login');
            err.classList.add('hidden');
            if (!u || !p) { err.textContent = 'Username dan password wajib diisi.'; err.classList.remove('hidden'); return false; }
            btn.disabled = true; btn.textContent = 'Memeriksa...';
            try {
                var res = await fetch('admin.php?api=login_technician', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ username: u, password: p })
                });
                var json = await res.json();
                if (json && json.success && json.data) {
                    sessionStorage.setItem('teknisi_logged', 'true');
                    sessionStorage.setItem('teknisi_user', json.data.name || json.data.username);
                    sessionStorage.setItem('teknisi_id', json.data.id || '');
                    window.location.replace('teknisi.php');
                    return false;
                }
                err.textContent = (json && json.msg) ? json.msg : 'Data teknisi tidak ditemukan';
                err.classList.remove('hidden');
            } catch (ex) {
                err.textContent = 'Gagal koneksi ke server. Coba lagi.';
                err.classList.remove('hidden');
            } finally {
                btn.disabled = false; btn.textContent = 'Masuk Work Order';
            }
            return false;
        }
    </script>
</body>
</html>