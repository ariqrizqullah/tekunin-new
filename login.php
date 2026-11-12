<?php
session_start();

$db_host = "127.0.0.1";
$db_user = "tekh7457_admin";       
$db_pass = "Tekunin2026";   
$db_name = "tekh7457_chat";       

$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
if ($conn->connect_error) die("Koneksi Database Gagal");

$conn->query("CREATE TABLE IF NOT EXISTS tk_customer_accounts (customer_id VARCHAR(50) PRIMARY KEY, username VARCHAR(100), password VARCHAR(100), phone VARCHAR(50), name VARCHAR(100), dvr_sn VARCHAR(100) DEFAULT '', cctv_sn VARCHAR(100) DEFAULT '', hdd_sn VARCHAR(100) DEFAULT '', active_date VARCHAR(50) DEFAULT '')");

// FAILSAFE RECOVERY: Jika session server hilang tapi browser masih punya cookie login
if (!isset($_SESSION['member_logged_in']) && isset($_COOKIE['tk_member_id'])) {
    $cid = $conn->real_escape_string($_COOKIE['tk_member_id']);
    $res = $conn->query("SELECT * FROM tk_customer_accounts WHERE customer_id = '$cid'");
    if ($res && $res->num_rows > 0) {
        $_SESSION['member_logged_in'] = true;
        $_SESSION['member_data'] = $res->fetch_assoc();
    }
}

// JIKA SUDAH LOGIN, ARAHKAN KE DASHBOARD
if (isset($_SESSION['member_logged_in']) && $_SESSION['member_logged_in'] === true) {
    header("Location: dashboard.php");
    exit;
}

$error_msg = "";
if (isset($_GET['err'])) {
    if ($_GET['err'] == 'session_lost') $error_msg = "Sesi terputus. Silakan login kembali.";
    if ($_GET['err'] == 'account_lost') $error_msg = "Data akun tidak ditemukan/dihapus Admin.";
}

// PROSES CEK LOGIN
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login_member'])) {
    $username = $conn->real_escape_string(trim($_POST['username']));
    $password = $conn->real_escape_string(trim($_POST['password']));

    $stmt = $conn->prepare("SELECT * FROM tk_customer_accounts WHERE username = ? AND password = ?");
    $stmt->bind_param("ss", $username, $password);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result && $result->num_rows > 0) {
        $data = $result->fetch_assoc();
        
        // 1. Simpan di Server (Session)
        $_SESSION['member_logged_in'] = true;
        $_SESSION['member_data'] = $data;
        
        // 2. Simpan di Browser (Cookie) selama 7 hari sebagai cadangan kuat
        setcookie("tk_member_id", $data['customer_id'], time() + (86400 * 7), "/");
        
        header("Location: dashboard.php");
        exit;
    } else {
        $error_msg = "Username atau Password salah!";
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Member Login | Tekunin CCTV</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style> body { font-family: 'Plus Jakarta Sans', sans-serif; } </style>
</head>
<body class="bg-slate-50 text-slate-800 flex items-center justify-center min-h-screen p-4">

    <div class="w-full max-w-sm bg-white rounded-3xl shadow-xl p-8 border border-slate-100">
        <div class="text-center mb-8">
            <div class="w-14 h-14 bg-sky-50 text-sky-600 rounded-2xl flex items-center justify-center text-2xl mx-auto mb-4">
                <i class="fa-solid fa-user-lock"></i>
            </div>
            <h1 class="text-xl font-black">Member Login</h1>
            <p class="text-xs text-slate-500 mt-1">Gunakan akses dari Teknisi Tekunin</p>
        </div>

        <?php if ($error_msg): ?>
            <div class="bg-rose-50 text-rose-600 text-xs font-bold p-3 rounded-xl mb-5 text-center">
                <i class="fa-solid fa-circle-exclamation"></i> <?= $error_msg ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="login.php" class="space-y-5">
            <div>
                <label class="block text-[11px] font-bold text-slate-700 mb-1.5 uppercase">Username</label>
                <input type="text" name="username" required class="w-full px-4 py-3 bg-slate-50 border rounded-2xl text-sm" placeholder="Masukkan username">
            </div>
            <div>
                <label class="block text-[11px] font-bold text-slate-700 mb-1.5 uppercase">Password</label>
                <input type="password" name="password" required class="w-full px-4 py-3 bg-slate-50 border rounded-2xl text-sm" placeholder="••••••••">
            </div>
            <button type="submit" name="login_member" class="w-full bg-sky-600 hover:bg-sky-500 text-white font-extrabold py-3.5 rounded-2xl text-sm transition-colors">
                Masuk Dashboard
            </button>
        </form>
    </div>

</body>
</html>