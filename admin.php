<?php
// ==========================================
// PENGATURAN DATABASE & API ADMIN
// ==========================================
$db_host = "127.0.0.1";
$db_user = "tekh7457_admin";       
$db_pass = "Tekunin2026";   
$db_name = "tekh7457_chat";       
$api_key = "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";  

// ==========================================
// HELPER: POTONG STOK OTOMATIS DARI SN PADA WORK ORDER
// - 1 SN = 1 unit, mengurangi tk_stock.jumlah sebanyak 1
// - Dicatat di tk_stock_history (tipe KELUAR, oleh = "WO <id>")
// - tk_stock_wo_log mencegah potong ganda untuk pasangan (WO, SN) yang sama
//   (aman walau teknisi kirim ulang form / admin klik approve berulang)
// - SN yang tidak ada di stok / stok habis dilewati, tidak membatalkan WO
// ==========================================
function tk_stock_deduct_for_wo($conn, $woId, $snFields, $woType = 'INSTALASI') {
    $conn->query("CREATE TABLE IF NOT EXISTS tk_stock (
        sn VARCHAR(100) PRIMARY KEY,
        grup VARCHAR(100) DEFAULT '',
        jumlah DOUBLE DEFAULT 0,
        satuan VARCHAR(30) DEFAULT '',
        brand VARCHAR(100) DEFAULT '',
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )");
    $conn->query("CREATE TABLE IF NOT EXISTS tk_stock_history (
        id INT AUTO_INCREMENT PRIMARY KEY,
        sn VARCHAR(100),
        grup VARCHAR(100) DEFAULT '',
        jumlah DOUBLE DEFAULT 0,
        satuan VARCHAR(30) DEFAULT '',
        brand VARCHAR(100) DEFAULT '',
        tipe VARCHAR(10) DEFAULT 'MASUK',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    $chk = $conn->query("SHOW COLUMNS FROM tk_stock_history LIKE 'oleh'");
    if ($chk && $chk->num_rows === 0) { $conn->query("ALTER TABLE tk_stock_history ADD COLUMN oleh VARCHAR(60) DEFAULT 'Admin'"); }
    $conn->query("CREATE TABLE IF NOT EXISTS tk_stock_wo_log (
        wo_id VARCHAR(50) NOT NULL,
        sn VARCHAR(100) NOT NULL,
        kode VARCHAR(40) DEFAULT NULL,
        tipe_wo VARCHAR(20) DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (wo_id, sn)
    )");
    // Tabel versi sebelumnya belum punya kolom kode & tipe_wo -> tambahkan otomatis
    foreach (['kode' => "ALTER TABLE tk_stock_wo_log ADD COLUMN kode VARCHAR(40) DEFAULT NULL",
              'tipe_wo' => "ALTER TABLE tk_stock_wo_log ADD COLUMN tipe_wo VARCHAR(20) DEFAULT NULL"] as $col => $ddl) {
        $c = $conn->query("SHOW COLUMNS FROM tk_stock_wo_log LIKE '$col'");
        if ($c && $c->num_rows === 0) { $conn->query($ddl); }
    }

    // Kumpulkan semua SN unik dari semua kolom (dipisah koma, buang anotasi "(...)" dan "-")
    $sns = [];
    foreach ($snFields as $raw) {
        foreach (explode(',', (string)$raw) as $item) {
            $item = trim(preg_replace('/\s*\(.*\)$/', '', trim($item)));
            if ($item === '' || $item === '-') continue;
            $key = mb_strtolower($item);
            if (!isset($sns[$key])) $sns[$key] = $item;
        }
    }

    // Kode transaksi stok: INST-XXXXXX (instalasi) / MT-XXXXXX (maintenance)
    $isMaint = (strtoupper((string)$woType) === 'MAINTENANCE');
    $prefix  = $isMaint ? 'MT-' : 'INST-';
    $tipeWo  = $isMaint ? 'MAINTENANCE' : 'INSTALASI';
    $kode = '';
    // WO yang pernah diproses memakai kode yang sama; WO baru dibuatkan kode unik
    $q = $conn->prepare("SELECT kode FROM tk_stock_wo_log WHERE wo_id = ? AND kode IS NOT NULL AND kode <> '' LIMIT 1");
    $q->bind_param("s", $woId); $q->execute();
    $r = $q->get_result(); $row = $r ? $r->fetch_assoc() : null; $q->close();
    if ($row) {
        $kode = $row['kode'];
    } else {
        for ($i = 0; $i < 50 && $kode === ''; $i++) {
            $cand = $prefix . strtoupper(substr(md5($woId . '|' . $i), 0, 6));
            $q = $conn->prepare("SELECT 1 FROM tk_stock_wo_log WHERE kode = ? LIMIT 1");
            $q->bind_param("s", $cand); $q->execute();
            $r = $q->get_result(); $used = ($r && $r->num_rows > 0); $q->close();
            if (!$used) $kode = $cand;
        }
        if ($kode === '') $kode = $prefix . strtoupper(substr(md5($woId . microtime(true)), 0, 8));
    }
    $oleh = mb_substr($kode, 0, 60);
    $result = [];
    foreach ($sns as $sn) {
        $conn->begin_transaction();
        $st = $conn->prepare("SELECT sn, grup, jumlah, satuan, brand FROM tk_stock WHERE sn = ? FOR UPDATE");
        $st->bind_param("s", $sn); $st->execute();
        $res = $st->get_result(); $cur = $res ? $res->fetch_assoc() : null; $st->close();

        if (!$cur) { $conn->rollback(); $result[] = ['sn' => $sn, 'status' => 'tidak_ada_di_stok']; continue; }
        if ((float)$cur['jumlah'] < 1) { $conn->rollback(); $result[] = ['sn' => $cur['sn'], 'status' => 'stok_habis']; continue; }

        // Penanda anti-potong-ganda
        $lg = $conn->prepare("INSERT IGNORE INTO tk_stock_wo_log (wo_id, sn, kode, tipe_wo) VALUES (?, ?, ?, ?)");
        $lg->bind_param("ssss", $woId, $cur['sn'], $kode, $tipeWo); $lg->execute();
        $baru = ($lg->affected_rows > 0); $lg->close();
        if (!$baru) { $conn->rollback(); $result[] = ['sn' => $cur['sn'], 'status' => 'sudah_diproses']; continue; }

        $one = 1.0; $tipe = 'KELUAR';
        $u = $conn->prepare("UPDATE tk_stock SET jumlah = jumlah - ? WHERE sn = ?");
        $u->bind_param("ds", $one, $cur['sn']); $ok = $u->execute(); $u->close();
        if ($ok) {
            $h = $conn->prepare("INSERT INTO tk_stock_history (sn, grup, jumlah, satuan, brand, tipe, oleh) VALUES (?,?,?,?,?,?,?)");
            $h->bind_param("ssdssss", $cur['sn'], $cur['grup'], $one, $cur['satuan'], $cur['brand'], $tipe, $oleh);
            $ok = $h->execute(); $h->close();
        }
        if ($ok) { $conn->commit(); $result[] = ['sn' => $cur['sn'], 'status' => 'dipotong', 'kode' => $kode]; }
        else { $conn->rollback(); $result[] = ['sn' => $cur['sn'], 'status' => 'gagal']; }
    }
    return $result;
}

function tk_stock_summary_text($result) {
    $n = 0; $skip = []; $kode = '';
    foreach ($result as $r) {
        if ($r['status'] === 'dipotong') { $n++; $kode = $r['kode'] ?? $kode; }
        elseif ($r['status'] === 'tidak_ada_di_stok') $skip[] = $r['sn'] . ' (tidak ada di stok)';
        elseif ($r['status'] === 'stok_habis') $skip[] = $r['sn'] . ' (stok 0)';
    }
    $t = $n . ' SN dipotong dari stok' . ($kode !== '' ? ' (kode ' . $kode . ')' : '');
    if ($skip) $t .= '. Dilewati: ' . implode(', ', $skip);
    return $t;
}

$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
if ($conn->connect_error) {
    if (isset($_GET['api'])) {
        header('Content-Type: application/json');
        echo json_encode(["success" => false, "msg" => "Koneksi DB Gagal: " . $conn->connect_error]);
        exit;
    }
} else {
    $conn->set_charset("utf8mb4");
    
    $conn->query("CREATE TABLE IF NOT EXISTS tk_products (id VARCHAR(50) PRIMARY KEY, name VARCHAR(150), group_name VARCHAR(50), badge VARCHAR(50), price DOUBLE, image TEXT, description TEXT)");
    $conn->query("CREATE TABLE IF NOT EXISTS tk_orders (id VARCHAR(50) PRIMARY KEY, date VARCHAR(50), rawDate VARCHAR(50), name VARCHAR(100), phone VARCHAR(50), address TEXT, total DOUBLE, bank VARCHAR(50), status VARCHAR(50), trackingStatus VARCHAR(50), dpAmount DOUBLE, remainingAmount DOUBLE, warrantyType VARCHAR(50), warrantyDueDate VARCHAR(50), items TEXT, proofImage LONGTEXT)");
    
    // TABEL TIKET LAPORAN KENDALA DARI USER
    $conn->query("CREATE TABLE IF NOT EXISTS tk_tickets (
        id VARCHAR(50) PRIMARY KEY, 
        customer_id VARCHAR(50), 
        nama VARCHAR(100), 
        tingkat_masalah VARCHAR(20), 
        judul VARCHAR(150), 
        deskripsi TEXT, 
        status VARCHAR(50) DEFAULT 'MENUNGGU', 
        proof_image LONGTEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");

    // TABEL WORK ORDER TEKNISI LAPANGAN & MAINTENANCE
    $conn->query("CREATE TABLE IF NOT EXISTS tk_technician_orders (
        id VARCHAR(50) PRIMARY KEY,
        customer_id VARCHAR(50),
        nama VARCHAR(100),
        dvr_sn TEXT DEFAULT '',
        cctv_sn TEXT DEFAULT '',
        hdd_sn TEXT DEFAULT '',
        kabel_qty INT DEFAULT 0,
        proof_image LONGTEXT,
        proof_image_dvr LONGTEXT,
        proof_image_cctv LONGTEXT,
        proof_image_hdd LONGTEXT,
        status VARCHAR(50) DEFAULT 'SELESAI',
        wo_type VARCHAR(50) DEFAULT 'INSTALASI',
        catatan_maintenance TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    
    $cols_to_check_orders = [
        'trackingStatus' => "ALTER TABLE tk_orders ADD COLUMN trackingStatus VARCHAR(50) DEFAULT 'PROSES'",
        'dpAmount' => "ALTER TABLE tk_orders ADD COLUMN dpAmount DOUBLE DEFAULT 0",
        'remainingAmount' => "ALTER TABLE tk_orders ADD COLUMN remainingAmount DOUBLE DEFAULT 0",
        'warrantyType' => "ALTER TABLE tk_orders ADD COLUMN warrantyType VARCHAR(50) DEFAULT ''",
        'warrantyDueDate' => "ALTER TABLE tk_orders ADD COLUMN warrantyDueDate VARCHAR(50) DEFAULT ''",
        'proofImage' => "ALTER TABLE tk_orders ADD COLUMN proofImage LONGTEXT",
        'bank' => "ALTER TABLE tk_orders ADD COLUMN bank VARCHAR(50) DEFAULT ''",
        // === ALUR PEMBAYARAN MEMBER <-> ADMIN (bukti TF, konfirmasi admin) ===
        'payStatus' => "ALTER TABLE tk_orders ADD COLUMN payStatus VARCHAR(20) DEFAULT ''",
        'payBank' => "ALTER TABLE tk_orders ADD COLUMN payBank VARCHAR(150) DEFAULT ''",
        'payStage' => "ALTER TABLE tk_orders ADD COLUMN payStage VARCHAR(30) DEFAULT ''",
        'payAmount' => "ALTER TABLE tk_orders ADD COLUMN payAmount DOUBLE DEFAULT 0",
        'payAt' => "ALTER TABLE tk_orders ADD COLUMN payAt DATETIME NULL DEFAULT NULL",
        'payReviewedAt' => "ALTER TABLE tk_orders ADD COLUMN payReviewedAt DATETIME NULL DEFAULT NULL",
        // Tagihan sisa DP setelah instalasi selesai (dikirim admin ke riwayat invoice member)
        'settleSent' => "ALTER TABLE tk_orders ADD COLUMN settleSent TINYINT DEFAULT 0",
        'settleSentAt' => "ALTER TABLE tk_orders ADD COLUMN settleSentAt DATETIME NULL DEFAULT NULL",
        // Pesanan produk dari dashboard member (order ulang)
        'orderSource' => "ALTER TABLE tk_orders ADD COLUMN orderSource VARCHAR(20) DEFAULT ''",
        'customerId' => "ALTER TABLE tk_orders ADD COLUMN customerId VARCHAR(50) DEFAULT ''",
        'orderNote' => "ALTER TABLE tk_orders ADD COLUMN orderNote TEXT"
    ];

    foreach ($cols_to_check_orders as $col_name => $alter_query) {
        $chk = $conn->query("SHOW COLUMNS FROM tk_orders LIKE '$col_name'");
        if ($chk && $chk->num_rows == 0) {
            @$conn->query($alter_query);
        }
    }

    $cols_to_check_tech = [
        'wo_type' => "ALTER TABLE tk_technician_orders ADD COLUMN wo_type VARCHAR(50) DEFAULT 'INSTALASI'",
        'catatan_maintenance' => "ALTER TABLE tk_technician_orders ADD COLUMN catatan_maintenance TEXT",
        'proof_image_dvr' => "ALTER TABLE tk_technician_orders ADD COLUMN proof_image_dvr LONGTEXT",
        'proof_image_cctv' => "ALTER TABLE tk_technician_orders ADD COLUMN proof_image_cctv LONGTEXT",
        'proof_image_hdd' => "ALTER TABLE tk_technician_orders ADD COLUMN proof_image_hdd LONGTEXT",
        // SN PERANGKAT LAIN + DESKRIPSI per unit (notes_json) dari teknisi
        'other_sn' => "ALTER TABLE tk_technician_orders ADD COLUMN other_sn TEXT",
        'proof_image_other' => "ALTER TABLE tk_technician_orders ADD COLUMN proof_image_other LONGTEXT",
        'notes_json' => "ALTER TABLE tk_technician_orders ADD COLUMN notes_json LONGTEXT"
    ];

    foreach ($cols_to_check_tech as $col_name => $alter_query) {
        $chk = $conn->query("SHOW COLUMNS FROM tk_technician_orders LIKE '$col_name'");
        if ($chk && $chk->num_rows == 0) {
            @$conn->query($alter_query);
        }
    }

    @$conn->query("ALTER TABLE tk_technician_orders MODIFY COLUMN dvr_sn TEXT");
    @$conn->query("ALTER TABLE tk_technician_orders MODIFY COLUMN cctv_sn TEXT");
    @$conn->query("ALTER TABLE tk_technician_orders MODIFY COLUMN hdd_sn TEXT");

    $cols_to_check_tickets = [
        'proof_image' => "ALTER TABLE tk_tickets ADD COLUMN proof_image LONGTEXT"
    ];

    foreach ($cols_to_check_tickets as $col_name => $alter_query) {
        $chk = $conn->query("SHOW COLUMNS FROM tk_tickets LIKE '$col_name'");
        if ($chk && $chk->num_rows == 0) {
            @$conn->query($alter_query);
        }
    }

    $conn->query("CREATE TABLE IF NOT EXISTS tk_chats (id INT AUTO_INCREMENT PRIMARY KEY, session_id VARCHAR(50), sender VARCHAR(20), message TEXT, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP)");
    $conn->query("CREATE TABLE IF NOT EXISTS tk_chat_sessions (session_id VARCHAR(50) PRIMARY KEY, is_bot_active INT DEFAULT 1, admin_name VARCHAR(100) DEFAULT '', last_update TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP)");
    $conn->query("CREATE TABLE IF NOT EXISTS tk_members (id VARCHAR(50) PRIMARY KEY, username VARCHAR(100), password VARCHAR(100), perms TEXT)");

    // CHAT LAPORAN KENDALA: tabel sendiri, terpisah dari Live Chat (tk_chats / tk_chat_sessions)
    $conn->query("CREATE TABLE IF NOT EXISTS tk_ticket_chats (id INT AUTO_INCREMENT PRIMARY KEY, ticket_id VARCHAR(50), sender VARCHAR(100), message TEXT, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, INDEX idx_ticket_id (ticket_id))");
    $conn->query("CREATE TABLE IF NOT EXISTS tk_migrations (name VARCHAR(80) PRIMARY KEY, done_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP)");
    // Migrasi sekali jalan: chat lama milik tiket dipindah dari Live Chat ke tabel chat laporan kendala
    $mgChk = $conn->query("SELECT name FROM tk_migrations WHERE name = 'split_ticket_chats_v1'");
    if ($mgChk && $mgChk->num_rows == 0) {
        $conn->query("INSERT INTO tk_ticket_chats (ticket_id, sender, message, created_at) SELECT session_id, sender, message, created_at FROM tk_chats WHERE session_id IN (SELECT id FROM tk_tickets) ORDER BY id ASC");
        $conn->query("DELETE FROM tk_chats WHERE session_id IN (SELECT id FROM tk_tickets)");
        $conn->query("DELETE FROM tk_chat_sessions WHERE session_id IN (SELECT id FROM tk_tickets)");
        $conn->query("INSERT IGNORE INTO tk_migrations (name) VALUES ('split_ticket_chats_v1')");
    }
    
    // Alias ID pelanggan lama -> baru (agar user/teknisi yang masih memakai ID lama tetap terhubung)
    $conn->query("CREATE TABLE IF NOT EXISTS tk_customer_id_aliases (old_id VARCHAR(50) PRIMARY KEY, new_id VARCHAR(50), changed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP)");
    $conn->query("CREATE TABLE IF NOT EXISTS tk_customer_accounts (customer_id VARCHAR(50) PRIMARY KEY, username VARCHAR(100), password VARCHAR(100), phone VARCHAR(50), name VARCHAR(100), dvr_sn TEXT DEFAULT '', cctv_sn TEXT DEFAULT '', hdd_sn TEXT DEFAULT '', active_date VARCHAR(50) DEFAULT '')");

    // Akun login teknisi lapangan (hanya yang dibuat admin boleh masuk teknisi.php)
    $conn->query("CREATE TABLE IF NOT EXISTS tk_technician_accounts (
        id VARCHAR(50) PRIMARY KEY,
        username VARCHAR(100) UNIQUE,
        password VARCHAR(100),
        name VARCHAR(100) DEFAULT '',
        phone VARCHAR(50) DEFAULT '',
        is_active INT DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    
    @$conn->query("ALTER TABLE tk_customer_accounts MODIFY COLUMN dvr_sn TEXT");
    @$conn->query("ALTER TABLE tk_customer_accounts MODIFY COLUMN cctv_sn TEXT");
    @$conn->query("ALTER TABLE tk_customer_accounts MODIFY COLUMN hdd_sn TEXT");

    $cols_to_check_customer = [
        'dvr_sn' => "ALTER TABLE tk_customer_accounts ADD COLUMN dvr_sn TEXT DEFAULT ''",
        'cctv_sn' => "ALTER TABLE tk_customer_accounts ADD COLUMN cctv_sn TEXT DEFAULT ''",
        'hdd_sn' => "ALTER TABLE tk_customer_accounts ADD COLUMN hdd_sn TEXT DEFAULT ''",
        'other_sn' => "ALTER TABLE tk_customer_accounts ADD COLUMN other_sn TEXT",
        'active_date' => "ALTER TABLE tk_customer_accounts ADD COLUMN active_date VARCHAR(50) DEFAULT ''"
    ];

    foreach ($cols_to_check_customer as $col_name => $alter_query) {
        $chk = $conn->query("SHOW COLUMNS FROM tk_customer_accounts LIKE '$col_name'");
        if ($chk && $chk->num_rows == 0) {
            @$conn->query($alter_query);
        }
    }
}

// Apakah ID ini adalah tiket Laporan Kendala yang masih aktif?
function tk_is_ticket($conn, $sid) {
    $sid = (string)$sid;
    if ($sid === '') return false;
    $st = $conn->prepare("SELECT 1 FROM tk_tickets WHERE id = ? LIMIT 1");
    if (!$st) return false;
    $st->bind_param("s", $sid);
    $st->execute();
    $r = $st->get_result();
    $ok = $r && $r->num_rows > 0;
    $st->close();
    return $ok;
}

// Tentukan kanal chat: 'ticket' (Chat Laporan Kendala) atau 'live' (Live Chat CS).
// Klien baru mengirim channel eksplisit; klien lama (tanpa channel) dikenali otomatis dari ID tiket.
function tk_chat_channel($conn, $sid, $channel = '') {
    $channel = strtolower(trim((string)$channel));
    if ($channel === 'ticket' || $channel === 'live') return $channel;
    return tk_is_ticket($conn, $sid) ? 'ticket' : 'live';
}

// Terjemahkan ID pelanggan lama ke ID terbaru (jika pernah diubah admin)
function tk_resolve_cid($conn, $cid) {
    $cid = (string)$cid;
    if ($cid === '') return $cid;
    $st = $conn->prepare("SELECT 1 FROM tk_customer_accounts WHERE customer_id = ? LIMIT 1");
    if ($st) {
        $st->bind_param("s", $cid);
        $st->execute();
        $r = $st->get_result();
        $exists = $r && $r->num_rows > 0;
        $st->close();
        if ($exists) return $cid; // ID ini masih aktif, tidak perlu diterjemahkan
    }
    for ($i = 0; $i < 5; $i++) {
        $st = $conn->prepare("SELECT new_id FROM tk_customer_id_aliases WHERE old_id = ? LIMIT 1");
        if (!$st) break;
        $st->bind_param("s", $cid);
        $st->execute();
        $r = $st->get_result();
        $row = $r ? $r->fetch_assoc() : null;
        $st->close();
        if ($row && $row['new_id'] !== '' && $row['new_id'] !== $cid) { $cid = $row['new_id']; } else { break; }
    }
    return $cid;
}

$api = $_GET['api'] ?? '';
if ($api) {
    
    // Ubah ID Pelanggan: otomatis ikut berubah di akun member (user), laporan teknisi, dan tiket
    if ($api == 'rename_customer_id') {
        header('Content-Type: application/json');
        $d = json_decode(file_get_contents('php://input'), true);
        $old = trim((string)($d['old_id'] ?? ''));
        $new = trim((string)($d['new_id'] ?? ''));
        if ($old === '' || $new === '') { echo json_encode(["success" => false, "msg" => "ID lama dan ID baru wajib diisi."]); exit; }
        if ($old === $new) { echo json_encode(["success" => false, "msg" => "ID baru sama dengan ID lama."]); exit; }
        if (!preg_match('/^[A-Za-z0-9._-]{1,50}$/', $new)) {
            echo json_encode(["success" => false, "msg" => "ID baru hanya boleh huruf, angka, titik, strip (-) dan underscore (_), maksimal 50 karakter."]);
            exit;
        }
        $st = $conn->prepare("SELECT customer_id FROM tk_customer_accounts WHERE customer_id = ?");
        $st->bind_param("s", $new); $st->execute();
        $rr = $st->get_result(); $dup = $rr && $rr->num_rows > 0; $st->close();
        if ($dup) { echo json_encode(["success" => false, "msg" => "ID '$new' sudah dipakai pelanggan lain."]); exit; }
        $st = $conn->prepare("SELECT customer_id FROM tk_customer_accounts WHERE customer_id = ?");
        $st->bind_param("s", $old); $st->execute();
        $rr = $st->get_result(); $found = $rr && $rr->num_rows > 0; $st->close();
        if (!$found) { echo json_encode(["success" => false, "msg" => "Akun member dengan ID '$old' belum ada. Buat Akses Member dulu, baru ID bisa diubah."]); exit; }

        $conn->begin_transaction();
        $ok = true;
        foreach (['tk_customer_accounts', 'tk_technician_orders', 'tk_tickets'] as $tbl) {
            $st = $conn->prepare("UPDATE $tbl SET customer_id = ? WHERE customer_id = ?");
            if (!$st) { $ok = false; break; }
            $st->bind_param("ss", $new, $old);
            if (!$st->execute()) { $ok = false; }
            $st->close();
            if (!$ok) break;
        }
        if ($ok) {
            // Rantai alias: semua ID lama sebelumnya ikut diarahkan ke ID terbaru
            $st = $conn->prepare("UPDATE tk_customer_id_aliases SET new_id = ? WHERE new_id = ?");
            if ($st) { $st->bind_param("ss", $new, $old); $st->execute(); $st->close(); }
            $st = $conn->prepare("DELETE FROM tk_customer_id_aliases WHERE old_id = ?");
            if ($st) { $st->bind_param("s", $new); $st->execute(); $st->close(); }
            $st = $conn->prepare("REPLACE INTO tk_customer_id_aliases (old_id, new_id) VALUES (?, ?)");
            if ($st) { $st->bind_param("ss", $old, $new); if (!$st->execute()) $ok = false; $st->close(); } else { $ok = false; }
        }
        if ($ok) { $conn->commit(); echo json_encode(["success" => true, "msg" => "ID Pelanggan berhasil diubah dan tersinkron ke user & teknisi.", "new_id" => $new]); }
        else { $conn->rollback(); echo json_encode(["success" => false, "msg" => "Gagal mengubah ID: " . $conn->error]); }
        exit;
    }

    if ($api == 'save_technician_order') {
        header('Content-Type: application/json');
        $d = json_decode(file_get_contents('php://input'), true);
        if ($d) {
            $id = $d['id'] ?? 'WO-' . time();
            $cid = tk_resolve_cid($conn, $d['customer_id'] ?? '');
            $nama = $d['nama'] ?? '';
            $dvr = $d['dvr_sn'] ?? '';
            $cctv = $d['cctv_sn'] ?? '';
            $hdd = $d['hdd_sn'] ?? '';
            $kabel = $d['jumlah_kabel'] ?? 0;
            $img = $d['proof_image'] ?? '';
            $img_dvr = $d['proof_image_dvr'] ?? '';
            $img_cctv = $d['proof_image_cctv'] ?? '';
            $img_hdd = $d['proof_image_hdd'] ?? '';
            $wo_type = $d['wo_type'] ?? 'INSTALASI';
            $catatan = $d['catatan_maintenance'] ?? '';
            $other = $d['other_sn'] ?? '';
            $img_other = $d['proof_image_other'] ?? '';
            $notes_json = $d['notes_json'] ?? '';
            if (is_array($notes_json)) $notes_json = json_encode($notes_json);
            if (!is_string($notes_json) || strlen($notes_json) > 200000 || ($notes_json !== '' && json_decode($notes_json) === null)) $notes_json = '';

            // INSTALASI: status menunggu approve admin. MAINTENANCE: langsung SELESAI.
            $wo_status = (strtoupper($wo_type) === 'INSTALASI') ? 'MENUNGGU KONFIRMASI' : 'SELESAI';

            $stmt = $conn->prepare("REPLACE INTO tk_technician_orders (id, customer_id, nama, dvr_sn, cctv_sn, hdd_sn, kabel_qty, proof_image, proof_image_dvr, proof_image_cctv, proof_image_hdd, status, wo_type, catatan_maintenance, other_sn, proof_image_other, notes_json) VALUES (?,?,?,?,?,?,?,?,?,?,?, ?, ?, ?, ?, ?, ?)");
            if ($stmt) {
                $stmt->bind_param("ssssssissssssssss", $id, $cid, $nama, $dvr, $cctv, $hdd, $kabel, $img, $img_dvr, $img_cctv, $img_hdd, $wo_status, $wo_type, $catatan, $other, $img_other, $notes_json);
                $stmt->execute();
                $stmt->close();
            }

            // STOK: WO Maintenance langsung SELESAI (tanpa approve) -> potong stok sekarang.
            // WO Instalasi dipotong saat admin menekan APPROVE (lihat confirm_technician_order).
            $stok_result = [];
            if (strtoupper($wo_type) === 'MAINTENANCE') {
                $stok_result = tk_stock_deduct_for_wo($conn, $id, [$dvr, $cctv, $hdd, $other], 'MAINTENANCE');
            }

            // Merge SN ke akun member (cari by customer_id, lalu by nama) — jangan hapus SN lama
            $mergeSn = function($old, $new) {
                $oldArr = array_values(array_filter(array_map('trim', explode(',', (string)$old))));
                $newArr = array_values(array_filter(array_map('trim', explode(',', (string)$new))));
                $merged = $oldArr;
                foreach ($newArr as $item) {
                    $pureNew = trim(preg_replace('/\s*\(.*\)$/', '', $item));
                    $hasAnnotation = (strpos($item, '(') !== false);
                    $foundIdx = -1;
                    foreach ($merged as $idx => $m) {
                        $pureOld = trim(preg_replace('/\s*\(.*\)$/', '', $m));
                        if (strcasecmp($pureOld, $pureNew) === 0) { $foundIdx = $idx; break; }
                    }
                    if ($foundIdx >= 0) {
                        if ($hasAnnotation) $merged[$foundIdx] = $item;
                    } else {
                        $merged[] = $item;
                    }
                }
                return implode(', ', $merged);
            };

            $existing = null;
            $targetCid = $cid;
            if (!empty($cid)) {
                $stmt_get = $conn->prepare("SELECT customer_id, dvr_sn, cctv_sn, hdd_sn, other_sn FROM tk_customer_accounts WHERE customer_id = ?");
                if ($stmt_get) {
                    $stmt_get->bind_param("s", $cid);
                    $stmt_get->execute();
                    $existing = $stmt_get->get_result()->fetch_assoc();
                    $stmt_get->close();
                }
            }
            // Fallback: cari akun member berdasarkan nama
            if (!$existing && !empty($nama)) {
                $stmt_name = $conn->prepare("SELECT customer_id, dvr_sn, cctv_sn, hdd_sn, other_sn FROM tk_customer_accounts WHERE name = ? LIMIT 1");
                if ($stmt_name) {
                    $stmt_name->bind_param("s", $nama);
                    $stmt_name->execute();
                    $existing = $stmt_name->get_result()->fetch_assoc();
                    $stmt_name->close();
                    if ($existing) $targetCid = $existing['customer_id'];
                }
            }

            if ($existing && $targetCid) {
                $final_dvr  = (!empty($dvr))  ? $mergeSn($existing['dvr_sn']  ?? '', $dvr)  : ($existing['dvr_sn']  ?? '');
                $final_cctv = (!empty($cctv)) ? $mergeSn($existing['cctv_sn'] ?? '', $cctv) : ($existing['cctv_sn'] ?? '');
                $final_hdd  = (!empty($hdd))  ? $mergeSn($existing['hdd_sn']  ?? '', $hdd)  : ($existing['hdd_sn']  ?? '');
                $final_other = (!empty($other)) ? $mergeSn($existing['other_sn'] ?? '', $other) : ($existing['other_sn'] ?? '');
                $stmt_cust = $conn->prepare("UPDATE tk_customer_accounts SET dvr_sn = ?, cctv_sn = ?, hdd_sn = ?, other_sn = ? WHERE customer_id = ?");
                if ($stmt_cust) {
                    $stmt_cust->bind_param("sssss", $final_dvr, $final_cctv, $final_hdd, $final_other, $targetCid);
                    $stmt_cust->execute();
                    $stmt_cust->close();
                }
            }

            echo json_encode(["success" => true, "msg" => "Data Work Order berhasil disimpan", "stok" => $stok_result]);
        } else {
            echo json_encode(["success" => false, "msg" => "Data tidak valid (body kosong/terlalu besar. post_max_size=" . ini_get("post_max_size") . ")"]);
        }
        exit;
    }

    // Tombol APPROVE terpisah di admin → ubah status menjadi DATA APPROVE
    if ($api == 'confirm_technician_order') {
        header('Content-Type: application/json');
        $d = json_decode(file_get_contents('php://input'), true);
        $id = $d['id'] ?? '';
        if ($id === '') {
            echo json_encode(["success" => false, "msg" => "ID tidak valid"]);
            exit;
        }
        $stmt = $conn->prepare("UPDATE tk_technician_orders SET status = 'DATA APPROVE' WHERE id = ? AND status = 'MENUNGGU KONFIRMASI'");
        if ($stmt) {
            $stmt->bind_param("s", $id);
            $stmt->execute();
            $affected = $stmt->affected_rows;
            $stmt->close();
            if ($affected > 0) {
                // STOK: potong stok dari semua SN pada WO ini
                $stok_result = [];
                $q = $conn->prepare("SELECT dvr_sn, cctv_sn, hdd_sn, other_sn, wo_type FROM tk_technician_orders WHERE id = ?");
                if ($q) {
                    $q->bind_param("s", $id); $q->execute();
                    $wo = $q->get_result()->fetch_assoc(); $q->close();
                    if ($wo) {
                        $stok_result = tk_stock_deduct_for_wo($conn, $id, [$wo['dvr_sn'], $wo['cctv_sn'], $wo['hdd_sn'], $wo['other_sn']], ($wo['wo_type'] ?? '') !== '' ? $wo['wo_type'] : 'INSTALASI');
                    }
                }
                echo json_encode(["success" => true, "msg" => "Data berhasil di-APPROVE. " . tk_stock_summary_text($stok_result), "stok" => $stok_result]);
            } else {
                echo json_encode(["success" => false, "msg" => "WO tidak ditemukan atau sudah di-approve sebelumnya"]);
            }
        } else {
            echo json_encode(["success" => false, "msg" => $conn->error]);
        }
        exit;
    }

    // Upload foto Work Order satu per satu (request kecil, aman di HP)
    if ($api == 'save_technician_photo') {
        header('Content-Type: application/json');
        $rawIn = file_get_contents('php://input');
        $d = json_decode($rawIn, true);
        if (!$d || !is_array($d)) {
            echo json_encode(["success" => false, "msg" => "Body JSON tidak valid / terlalu besar"]);
            exit;
        }
        $id = $d['id'] ?? '';
        $field = $d['field'] ?? '';
        $idx = intval($d['index'] ?? 0);
        $photo = $d['photo'] ?? '';
        $map = ['main' => 'proof_image', 'dvr' => 'proof_image_dvr', 'cctv' => 'proof_image_cctv', 'hdd' => 'proof_image_hdd', 'other' => 'proof_image_other'];

        $photoOk = is_string($photo) && strpos($photo, 'data:image/') === 0 && strlen($photo) > 32;
        if ($id === '' || !isset($map[$field]) || !$photoOk || $idx < 0 || $idx > 50) {
            echo json_encode(["success" => false, "msg" => "Data foto tidak valid"]);
            exit;
        }
        $col = $map[$field];

        $chk = $conn->prepare("SELECT id FROM tk_technician_orders WHERE id = ?");
        if ($chk) {
            $chk->bind_param("s", $id);
            $chk->execute();
            $exists = $chk->get_result()->fetch_assoc();
            $chk->close();
            if (!$exists) {
                echo json_encode(["success" => false, "msg" => "Work Order tidak ditemukan"]);
                exit;
            }
        }

        if ($field === 'main') {
            $stmt = $conn->prepare("UPDATE tk_technician_orders SET proof_image = ? WHERE id = ?");
            if ($stmt) {
                $stmt->bind_param("ss", $photo, $id);
                $ok = $stmt->execute();
                $err = $stmt->error;
                $stmt->close();
                if (!$ok) {
                    echo json_encode(["success" => false, "msg" => "Gagal simpan foto: " . $err]);
                    exit;
                }
            }
        } else {
            $stmt = $conn->prepare("SELECT $col AS val FROM tk_technician_orders WHERE id = ?");
            $stmt->bind_param("s", $id);
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            $arr = json_decode($row['val'] ?? '', true);
            if (!is_array($arr)) $arr = [];
            while (count($arr) <= $idx) $arr[] = '';
            $arr[$idx] = $photo;
            $json = json_encode($arr);
            $stmt = $conn->prepare("UPDATE tk_technician_orders SET $col = ? WHERE id = ?");
            if ($stmt) {
                $stmt->bind_param("ss", $json, $id);
                $ok = $stmt->execute();
                $err = $stmt->error;
                $stmt->close();
                if (!$ok) {
                    echo json_encode(["success" => false, "msg" => "Gagal simpan foto: " . $err]);
                    exit;
                }
            }
        }
        echo json_encode(["success" => true, "msg" => "Foto tersimpan"]);
        exit;
    }

    if ($api == 'get_technician_orders') {
        header('Content-Type: application/json');
        $list = [];
        
        $res = $conn->query("SELECT * FROM tk_technician_orders ORDER BY created_at DESC");
        if($res) {
            while($r = $res->fetch_assoc()) {
                $list[] = $r;
            }
        }
        
        $resTickets = $conn->query("SELECT id, customer_id, nama, judul AS catatan_maintenance, proof_image, created_at, 'MAINTENANCE' AS wo_type, status FROM tk_tickets ORDER BY created_at DESC");
        if($resTickets) {
            while($rt = $resTickets->fetch_assoc()) {
                $alreadyInWo = false;
                foreach($list as $existingWo) {
                    if($existingWo['id'] === $rt['id']) {
                        $alreadyInWo = true;
                        break;
                    }
                }
                if(!$alreadyInWo) {
                    $rt['dvr_sn'] = '';
                    $rt['cctv_sn'] = '';
                    $rt['hdd_sn'] = '';
                    $rt['other_sn'] = '';
                    $rt['proof_image_other'] = '';
                    $rt['notes_json'] = '';
                    $rt['kabel_qty'] = 0;
                    $list[] = $rt;
                }
            }
        }

        echo json_encode(["success" => true, "data" => $list]);
        exit;
    }

    if ($api == 'delete_technician_order') {
        header('Content-Type: application/json');
        $id = $conn->real_escape_string($_GET['id'] ?? '');
        if ($id) {
            $conn->query("DELETE FROM tk_technician_orders WHERE id = '$id'");
            $conn->query("DELETE FROM tk_tickets WHERE id = '$id'");
            echo json_encode(["success" => true, "msg" => "Work Order / Laporan Teknisi berhasil dihapus"]);
        } else {
            echo json_encode(["success" => false, "msg" => "ID tidak valid"]);
        }
        exit;
    }

    if ($api == 'get_tickets') {
        header('Content-Type: application/json');
        $res = $conn->query("SELECT * FROM tk_tickets ORDER BY created_at DESC");
        $list = [];
        if($res) {
            while($r = $res->fetch_assoc()) {
                $list[] = $r;
            }
        }
        echo json_encode(["success" => true, "data" => $list]);
        exit;
    }

    if ($api == 'update_ticket_status') {
        header('Content-Type: application/json');
        $d = json_decode(file_get_contents('php://input'), true);
        $tid = $d['ticket_id'] ?? '';
        $status = $d['status'] ?? 'MENUNGGU';
        if ($tid) {
            $chkStatusStmt = $conn->prepare("SELECT status FROM tk_tickets WHERE id = ?");
            $chkStatusStmt->bind_param("s", $tid);
            $chkStatusStmt->execute();
            $oldStatusRes = $chkStatusStmt->get_result()->fetch_assoc();
            $oldStatus = $oldStatusRes['status'] ?? '';
            $chkStatusStmt->close();

            if ($status === 'SELESAI' || $status === 'SELESAI RAMPUNG') {
                $stmt = $conn->prepare("DELETE FROM tk_tickets WHERE id = ?");
                if ($stmt) {
                    $stmt->bind_param("s", $tid);
                    $stmt->execute();
                    $stmt->close();
                }
                $stmtChatDel = $conn->prepare("DELETE FROM tk_ticket_chats WHERE ticket_id = ?");
                if ($stmtChatDel) {
                    $stmtChatDel->bind_param("s", $tid);
                    $stmtChatDel->execute();
                    $stmtChatDel->close();
                }
            } else {
                $stmt = $conn->prepare("UPDATE tk_tickets SET status = ? WHERE id = ?");
                if ($stmt) {
                    $stmt->bind_param("ss", $status, $tid);
                    $stmt->execute();
                    $stmt->close();
                }

                if ($oldStatus !== $status) {
                    $sysMsg = "ADMIN TELAH MENGUBAH STATUS: $status";
                    $stmtChat = $conn->prepare("INSERT INTO tk_ticket_chats (ticket_id, sender, message) VALUES (?, 'system', ?)");
                    if ($stmtChat) {
                        $stmtChat->bind_param("ss", $tid, $sysMsg);
                        $stmtChat->execute();
                        $stmtChat->close();
                    }
                }
            }
            echo json_encode(["success" => true]);
        } else {
            echo json_encode(["success" => false, "msg" => "ID Tiket tidak valid"]);
        }
        exit;
    }

    // ==========================================
    // EXPORT / IMPORT PER TAB
    // Setiap tab punya file backup sendiri: tabel => [nama_tabel_db, kolom_primary_key]
    // ==========================================
    $TK_BACKUP_TABS = [
        'pesanan'   => ['label' => 'Pesanan Masuk',      'tables' => ['orders' => ['tk_orders', 'id']]],
        'teknisi'   => ['label' => 'Laporan Teknisi',    'tables' => ['technician_orders' => ['tk_technician_orders', 'id']]],
        'laporan'   => ['label' => 'Laporan Kendala',    'tables' => ['tickets' => ['tk_tickets', 'id'], 'ticket_chats' => ['tk_ticket_chats', 'id']]],
        'katalog'   => ['label' => 'Katalog Produk',     'tables' => ['products' => ['tk_products', 'id']]],
        'livechat'  => ['label' => 'Live Chat',          'tables' => ['chat_sessions' => ['tk_chat_sessions', 'session_id'], 'chats' => ['tk_chats', 'id']]],
        'akses'     => ['label' => 'Akses Member',       'tables' => [
                            'customer_accounts'   => ['tk_customer_accounts', 'customer_id'],
                            'customer_id_aliases' => ['tk_customer_id_aliases', 'old_id'],
                            'technician_accounts' => ['tk_technician_accounts', 'id']
                        ]],
        'anggota'   => ['label' => 'Akun Anggota',       'tables' => ['members' => ['tk_members', 'id']]],
        // Database Pelanggan = rekap otomatis dari tk_orders, jadi hanya bisa di-export
        'pelanggan' => ['label' => 'Database Pelanggan', 'tables' => [], 'export_only' => true]
    ];

    if ($api == 'export_tab') {
        $tab = $_GET['tab'] ?? '';
        if (!isset($TK_BACKUP_TABS[$tab])) {
            header('Content-Type: application/json');
            echo json_encode(["success" => false, "msg" => "Tab backup tidak dikenal."]);
            exit;
        }
        $cfg = $TK_BACKUP_TABS[$tab];
        $jf = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR
            | (defined('JSON_INVALID_UTF8_SUBSTITUTE') ? JSON_INVALID_UTF8_SUBSTITUTE : 0);

        @set_time_limit(0);
        while (ob_get_level()) { @ob_end_clean(); }
        header('Content-Type: application/json; charset=utf-8');
        header('Content-Disposition: attachment; filename="tekunin_' . $tab . '_' . date('Y-m-d_H-i-s') . '.json"');

        echo '{"app":"tekunin-backup","version":2,"tab":' . json_encode($tab) . ',"label":' . json_encode($cfg['label'], $jf)
           . ',"exported_at":' . json_encode(date('Y-m-d H:i:s')) . ',"tables":{';

        if ($tab === 'pelanggan') {
            // Rekap pelanggan dari pesanan (tanpa bukti transfer, jadi ringan)
            $map = [];
            $res = $conn->query("SELECT name, phone, address, total FROM tk_orders");
            if ($res) {
                while ($r = $res->fetch_assoc()) {
                    $k = ($r['phone'] !== null && $r['phone'] !== '') ? $r['phone'] : $r['name'];
                    if (!isset($map[$k])) $map[$k] = ['name' => $r['name'], 'phone' => $r['phone'], 'address' => $r['address'], 'order_count' => 0, 'total_spent' => 0];
                    $map[$k]['order_count']++;
                    $map[$k]['total_spent'] += (float)$r['total'];
                }
                $res->free();
            }
            echo '"customers":' . json_encode(array_values($map), $jf);
        } else {
            $firstTable = true;
            foreach ($cfg['tables'] as $key => $def) {
                echo ($firstTable ? '' : ',') . json_encode($key) . ':[';
                $firstTable = false;
                // Streaming per baris supaya aman untuk tabel besar (bukti gambar base64)
                $res = $conn->query("SELECT * FROM `" . $def[0] . "`", MYSQLI_USE_RESULT);
                if ($res) {
                    $i = 0;
                    while ($r = $res->fetch_assoc()) {
                        echo ($i++ ? ',' : '') . json_encode($r, $jf);
                    }
                    $res->free();
                }
                echo ']';
            }
        }
        echo '}}';
        exit;
    }

    if ($api == 'import_tab') {
        header('Content-Type: application/json; charset=utf-8');
        @set_time_limit(0);
        $tab = $_GET['tab'] ?? '';
        if (!isset($TK_BACKUP_TABS[$tab])) {
            echo json_encode(["success" => false, "msg" => "Tab backup tidak dikenal."]);
            exit;
        }
        $cfg = $TK_BACKUP_TABS[$tab];
        if (!empty($cfg['export_only'])) {
            echo json_encode(["success" => false, "msg" => $cfg['label'] . " hanya bisa di-export (datanya otomatis dihitung dari Pesanan Masuk)."]);
            exit;
        }

        $data = json_decode(file_get_contents('php://input'), true);
        if (!is_array($data)) {
            echo json_encode(["success" => false, "msg" => "Format file backup tidak valid."]);
            exit;
        }

        // File backup baru membawa penanda tab: harus cocok dengan tab yang sedang dibuka
        if (isset($data['tab']) && $data['tab'] !== $tab) {
            $from = $TK_BACKUP_TABS[$data['tab']]['label'] ?? (string)$data['tab'];
            echo json_encode(["success" => false, "msg" => "File ini adalah backup tab \"$from\", bukan \"" . $cfg['label'] . "\". Import dibatalkan."]);
            exit;
        }
        // File backup lama (satu file semua data) tetap bisa dipakai: hanya bagian milik tab ini yang diambil
        $payload = (isset($data['tables']) && is_array($data['tables'])) ? $data['tables'] : $data;

        $summary = [];
        $found = false;
        $okTotal = 0; $failTotal = 0;
        try {
            $conn->begin_transaction();
            foreach ($cfg['tables'] as $key => $def) {
                if (!isset($payload[$key]) || !is_array($payload[$key])) continue;
                $found = true;
                $table = $def[0]; $pk = $def[1];

                // Hanya kolom yang benar-benar ada di tabel yang dipakai (aman dari kolom asing / versi lama)
                $cols = [];
                $rc = $conn->query("SHOW COLUMNS FROM `$table`");
                if ($rc) { while ($c = $rc->fetch_assoc()) $cols[$c['Field']] = true; $rc->free(); }

                $ok = 0; $fail = 0; $stmts = [];
                foreach ($payload[$key] as $row) {
                    if (!is_array($row)) { $fail++; continue; }
                    $use = [];
                    foreach ($row as $k => $v) {
                        if (!isset($cols[$k]) || $v === null || is_array($v)) continue;
                        $use[$k] = is_bool($v) ? (string)(int)$v : (string)$v;
                    }
                    if (!isset($use[$pk]) || $use[$pk] === '') { $fail++; continue; }

                    $sig = implode('|', array_keys($use));
                    if (!isset($stmts[$sig])) {
                        $names = implode(',', array_map(function ($c) { return "`$c`"; }, array_keys($use)));
                        $ph = implode(',', array_fill(0, count($use), '?'));
                        $stmts[$sig] = $conn->prepare("REPLACE INTO `$table` ($names) VALUES ($ph)");
                    }
                    $st = $stmts[$sig];
                    if (!$st) { $fail++; continue; }
                    $vals = array_values($use);
                    $st->bind_param(str_repeat('s', count($vals)), ...$vals);
                    if ($st->execute()) $ok++; else $fail++;
                }
                foreach ($stmts as $st) { if ($st) $st->close(); }
                $summary[$key] = ["ok" => $ok, "gagal" => $fail];
                $okTotal += $ok; $failTotal += $fail;
            }
            $conn->commit();
        } catch (Throwable $e) {
            @$conn->rollback();
            echo json_encode(["success" => false, "msg" => "Import gagal: " . $e->getMessage()]);
            exit;
        }

        if (!$found) {
            echo json_encode(["success" => false, "msg" => "File ini tidak berisi data untuk tab " . $cfg['label'] . "."]);
            exit;
        }
        $msg = "Import " . $cfg['label'] . " selesai: $okTotal data masuk" . ($failTotal ? ", $failTotal dilewati (ID kosong/tidak valid)" : "") . ".";
        echo json_encode(["success" => true, "msg" => $msg, "summary" => $summary]);
        exit;
    }

    if ($api == 'clear_inactive_chats') {
        header('Content-Type: application/json');
        $res = $conn->query("SELECT session_id FROM tk_chat_sessions WHERE is_bot_active = 1");
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                $sid = $conn->real_escape_string($row['session_id']);
                $conn->query("DELETE FROM tk_chats WHERE session_id = '$sid'");
                $conn->query("DELETE FROM tk_chat_sessions WHERE session_id = '$sid'");
            }
        }
        echo json_encode(["success" => true, "msg" => "Riwayat chat nonaktif berhasil dibersihkan."]);
        exit;
    }

    header('Content-Type: application/json');

    if ($api == 'save_customer_account') {
        $d = json_decode(file_get_contents('php://input'), true);
        if ($d) {
            $cid = $d['customer_id'] ?? 'CUST-' . time();
            $uname = $d['username'] ?? '';
            $pass = $d['password'] ?? '';
            $phone = $d['phone'] ?? '';
            $name = $d['name'] ?? '';
            $dvr_sn = $d['dvr_sn'] ?? '';
            $cctv_sn = $d['cctv_sn'] ?? '';
            $hdd_sn = $d['hdd_sn'] ?? '';
            $other_sn = $d['other_sn'] ?? '';
            $active_date = $d['active_date'] ?? '';
            // from_admin=true → edit penuh dari panel admin (timpa semua field sesuai form)
            $fromAdmin = !empty($d['from_admin']);
            if (!$fromAdmin) $cid = tk_resolve_cid($conn, $cid);

            // Gabungkan SN lama + baru tanpa duplikat (untuk update dari teknisi)
            $mergeSn = function($old, $new) {
                $oldArr = array_values(array_filter(array_map('trim', explode(',', (string)$old))));
                $newArr = array_values(array_filter(array_map('trim', explode(',', (string)$new))));
                $merged = $oldArr;
                foreach ($newArr as $item) {
                    $pureNew = trim(preg_replace('/\s*\(.*\)$/', '', $item));
                    $hasAnnotation = (strpos($item, '(') !== false);
                    $foundIdx = -1;
                    foreach ($merged as $idx => $m) {
                        $pureOld = trim(preg_replace('/\s*\(.*\)$/', '', $m));
                        if (strcasecmp($pureOld, $pureNew) === 0) { $foundIdx = $idx; break; }
                    }
                    if ($foundIdx >= 0) {
                        if ($hasAnnotation) $merged[$foundIdx] = $item;
                    } else {
                        $merged[] = $item;
                    }
                }
                return implode(', ', $merged);
            };

            $stmt_get = $conn->prepare("SELECT * FROM tk_customer_accounts WHERE customer_id = ?");
            $existing = null;
            if ($stmt_get) {
                $stmt_get->bind_param("s", $cid);
                $stmt_get->execute();
                $existing = $stmt_get->get_result()->fetch_assoc();
                $stmt_get->close();
            }

            if ($existing) {
                if ($fromAdmin) {
                    // Edit dari admin: pakai nilai form sebagai sumber kebenaran
                    $final_uname = ($uname !== '') ? $uname : ($existing['username'] ?? '');
                    $final_pass  = ($pass !== '')  ? $pass  : ($existing['password'] ?? '');
                    $final_phone = $phone;
                    $final_name  = $name;
                    $final_active = $active_date;
                    $final_dvr  = $dvr_sn;
                    $final_cctv = $cctv_sn;
                    $final_hdd  = $hdd_sn;
                    $final_other = array_key_exists('other_sn', $d) ? $other_sn : ($existing['other_sn'] ?? '');
                } else {
                    // Update dari teknisi/WO: jangan hapus SN lama; jangan timpa user/pass yang sudah ada
                    $final_uname = (!empty($existing['username'])) ? $existing['username'] : (($uname !== '') ? $uname : '');
                    $final_pass  = (!empty($existing['password'])) ? $existing['password'] : (($pass !== '') ? $pass : '');
                    $final_phone = ($phone !== '') ? $phone : ($existing['phone'] ?? '');
                    $final_name  = ($name !== '')  ? $name  : ($existing['name'] ?? '');
                    $final_active = ($active_date !== '') ? $active_date : ($existing['active_date'] ?? '');
                    $final_dvr  = ($dvr_sn !== '')  ? $mergeSn($existing['dvr_sn'] ?? '', $dvr_sn)  : ($existing['dvr_sn'] ?? '');
                    $final_cctv = ($cctv_sn !== '') ? $mergeSn($existing['cctv_sn'] ?? '', $cctv_sn) : ($existing['cctv_sn'] ?? '');
                    $final_hdd  = ($hdd_sn !== '')  ? $mergeSn($existing['hdd_sn'] ?? '', $hdd_sn)  : ($existing['hdd_sn'] ?? '');
                    $final_other = ($other_sn !== '') ? $mergeSn($existing['other_sn'] ?? '', $other_sn) : ($existing['other_sn'] ?? '');
                }

                $stmt = $conn->prepare("UPDATE tk_customer_accounts SET username=?, password=?, phone=?, name=?, dvr_sn=?, cctv_sn=?, hdd_sn=?, active_date=?, other_sn=? WHERE customer_id=?");
                if ($stmt) {
                    $stmt->bind_param("ssssssssss", $final_uname, $final_pass, $final_phone, $final_name, $final_dvr, $final_cctv, $final_hdd, $final_active, $final_other, $cid);
                    $ok = $stmt->execute();
                    $stmt->close();
                    if (!$ok) {
                        echo json_encode(["success" => false, "msg" => "Gagal update: " . $conn->error]);
                        exit;
                    }
                }
            } else {
                $stmt = $conn->prepare("INSERT INTO tk_customer_accounts (customer_id, username, password, phone, name, dvr_sn, cctv_sn, hdd_sn, active_date, other_sn) VALUES (?,?,?,?,?,?,?,?,?,?)");
                if ($stmt) {
                    $stmt->bind_param("ssssssssss", $cid, $uname, $pass, $phone, $name, $dvr_sn, $cctv_sn, $hdd_sn, $active_date, $other_sn);
                    $stmt->execute();
                    $stmt->close();
                }
            }
            echo json_encode(["success" => true]);
        } else {
            echo json_encode(["success" => false, "msg" => "Data tidak valid"]);
        }
        exit;
    }

    if ($api == 'delete_customer_account') {
        $id = $conn->real_escape_string($_GET['id'] ?? '');
        $conn->query("DELETE FROM tk_customer_accounts WHERE customer_id = '$id'");
        echo json_encode(["success" => true]);
        exit;
    }

    // ===== AKUN TEKNISI (login teknisi.php) =====
    if ($api == 'save_technician_account') {
        header('Content-Type: application/json');
        $d = json_decode(file_get_contents('php://input'), true);
        if (!$d) {
            echo json_encode(["success" => false, "msg" => "Data tidak valid"]);
            exit;
        }
        $id = $d['id'] ?? ('TECH-' . time());
        $uname = trim($d['username'] ?? '');
        $pass = trim($d['password'] ?? '');
        $name = trim($d['name'] ?? '');
        $phone = trim($d['phone'] ?? '');
        $active = isset($d['is_active']) ? intval($d['is_active']) : 1;
        if ($uname === '' || $pass === '') {
            echo json_encode(["success" => false, "msg" => "Username dan password wajib"]);
            exit;
        }
        $stmt = $conn->prepare("REPLACE INTO tk_technician_accounts (id, username, password, name, phone, is_active) VALUES (?,?,?,?,?,?)");
        if ($stmt) {
            $stmt->bind_param("sssssi", $id, $uname, $pass, $name, $phone, $active);
            $stmt->execute();
            $stmt->close();
            echo json_encode(["success" => true, "id" => $id]);
        } else {
            echo json_encode(["success" => false, "msg" => $conn->error]);
        }
        exit;
    }

    if ($api == 'delete_technician_account') {
        header('Content-Type: application/json');
        $id = $conn->real_escape_string($_GET['id'] ?? '');
        if ($id) {
            $conn->query("DELETE FROM tk_technician_accounts WHERE id = '$id'");
            echo json_encode(["success" => true]);
        } else {
            echo json_encode(["success" => false, "msg" => "ID tidak valid"]);
        }
        exit;
    }

    if ($api == 'login_technician') {
        header('Content-Type: application/json');
        $d = json_decode(file_get_contents('php://input'), true);
        $uname = trim($d['username'] ?? '');
        $pass = trim($d['password'] ?? '');
        if ($uname === '' || $pass === '') {
            echo json_encode(["success" => false, "msg" => "Data teknisi tidak ditemukan"]);
            exit;
        }
        $stmt = $conn->prepare("SELECT id, username, name, phone, is_active FROM tk_technician_accounts WHERE username = ? AND password = ? LIMIT 1");
        if ($stmt) {
            $stmt->bind_param("ss", $uname, $pass);
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            if ($row && intval($row['is_active']) === 1) {
                echo json_encode(["success" => true, "data" => [
                    "id" => $row['id'],
                    "username" => $row['username'],
                    "name" => $row['name'] ?: $row['username'],
                    "phone" => $row['phone'] ?? ''
                ]]);
            } else {
                echo json_encode(["success" => false, "msg" => "Data teknisi tidak ditemukan"]);
            }
        } else {
            echo json_encode(["success" => false, "msg" => "Data teknisi tidak ditemukan"]);
        }
        exit;
    }
    
    if ($api == 'check_order') {
        $id = $_GET['id'] ?? '';
        $stmt = $conn->prepare("SELECT status, trackingStatus FROM tk_orders WHERE id = ?");
        if ($stmt) {
            $stmt->bind_param("s", $id);
            $stmt->execute();
            $res = $stmt->get_result();
            if ($res && $res->num_rows > 0) {
                $row = $res->fetch_assoc();
                $ts = !empty($row['trackingStatus']) ? $row['trackingStatus'] : 'PROSES';
                echo json_encode(["success" => true, "data" => ["status" => $ts]]);
            } else {
                echo json_encode(["success" => false, "msg" => "Pesanan telah diselesaikan atau dihapus"]);
            }
            $stmt->close();
        } else {
            echo json_encode(["success" => false, "msg" => "Database error"]);
        }
        exit;
    }

    if ($api == 'user_confirm_done') {
        $d = json_decode(file_get_contents('php://input'), true);
        $id = $d['id'] ?? '';
        if ($id) {
            $stmt = $conn->prepare("UPDATE tk_orders SET trackingStatus = 'SELESAI RAMPUNG' WHERE id = ?");
            if ($stmt) {
                $stmt->bind_param("s", $id);
                $stmt->execute();
                $stmt->close();
            }
            
            $sysMsg = "USER TELAH KONFIRMASI PENGERJAAN TELAH SELESAI untuk Invoice: $id";
            $escId = $conn->real_escape_string($id);
            $conn->query("INSERT IGNORE INTO tk_chat_sessions (session_id, is_bot_active) VALUES ('$escId', 0)");
            $stmtChat = $conn->prepare("INSERT INTO tk_chats (session_id, sender, message) VALUES (?, 'system', ?)");
            if ($stmtChat) {
                $stmtChat->bind_param("ss", $id, $sysMsg);
                $stmtChat->execute();
                $stmtChat->close();
            }
            
            echo json_encode(["success" => true]);
        } else {
            echo json_encode(["success" => false]);
        }
        exit;
    }

    if ($api == 'get_data') {
        $data = ['products' => [], 'orders' => [], 'sessions' => [], 'members' => [], 'customer_accounts' => [], 'tickets' => [], 'technician_orders' => [], 'technician_accounts' => []];
        $res = $conn->query("SELECT * FROM tk_products ORDER BY id DESC");
        if($res) { while($row = $res->fetch_assoc()) $data['products'][] = $row; }
        
        // Bukti TF (base64) TIDAK ikut dikirim di polling agar ringan & tidak gagal; cukup flag hasProof.
        // Gambar diambil sesuai kebutuhan lewat api=get_order_proof.
        $res2 = false;
        $ord_cols = []; $has_legacy_proof = false;
        $rc_cols = $conn->query("SHOW COLUMNS FROM tk_orders");
        if ($rc_cols) {
            while ($c_col = $rc_cols->fetch_assoc()) {
                if ($c_col['Field'] === 'proof_image') $has_legacy_proof = true;
                if (!in_array($c_col['Field'], ['proofImage', 'proof_image'], true)) {
                    $ord_cols[] = '`' . str_replace('`', '', $c_col['Field']) . '`';
                }
            }
        }
        if (!empty($ord_cols)) {
            $proof_expr = "(CASE WHEN (proofImage IS NOT NULL AND proofImage <> '')" . ($has_legacy_proof ? " OR (proof_image IS NOT NULL AND proof_image <> '')" : "") . " THEN 1 ELSE 0 END)";
            $res2 = $conn->query("SELECT " . implode(',', $ord_cols) . ", $proof_expr AS hasProof FROM tk_orders ORDER BY rawDate DESC");
        }
        if (!$res2) { $res2 = $conn->query("SELECT * FROM tk_orders ORDER BY rawDate DESC"); }
        if($res2) {
            while($row = $res2->fetch_assoc()) {
                $row['items'] = json_decode($row['items'] ?? '[]', true) ?: [];
                if (isset($row['hasProof'])) {
                    $row['hasProof'] = intval($row['hasProof']) === 1;
                } else {
                    $row['hasProof'] = !empty($row['proofImage']) || !empty($row['proof_image']);
                }
                unset($row['proofImage'], $row['proof_image']);
                $data['orders'][] = $row;
            }
        }
        
        $res3 = $conn->query("SELECT s.session_id, s.is_bot_active, s.admin_name, (SELECT message FROM tk_chats c WHERE c.session_id = s.session_id ORDER BY created_at DESC LIMIT 1) as last_message FROM tk_chat_sessions s WHERE s.session_id NOT IN (SELECT id FROM tk_tickets) ORDER BY s.last_update DESC");
        if($res3) { while($row = $res3->fetch_assoc()) $data['sessions'][] = $row; }

        $res4 = $conn->query("SELECT * FROM tk_members");
        if($res4) { while($row = $res4->fetch_assoc()) { $row['perms'] = json_decode($row['perms'], true); $data['members'][] = $row; } }

        $res5 = $conn->query("SELECT * FROM tk_customer_accounts");
        if($res5) { while($row = $res5->fetch_assoc()) { $data['customer_accounts'][] = $row; } }

        $res6 = $conn->query("SELECT * FROM tk_tickets ORDER BY created_at DESC");
        if($res6) { while($row = $res6->fetch_assoc()) { $data['tickets'][] = $row; } }

        $res7 = $conn->query("SELECT * FROM tk_technician_orders ORDER BY created_at DESC");
        if($res7) { while($row = $res7->fetch_assoc()) { $data['technician_orders'][] = $row; } }

        $res8 = $conn->query("SELECT * FROM tk_technician_accounts ORDER BY created_at DESC");
        if($res8) { while($row = $res8->fetch_assoc()) { $data['technician_accounts'][] = $row; } }

        $json_flags_gd = (defined('JSON_INVALID_UTF8_SUBSTITUTE') ? JSON_INVALID_UTF8_SUBSTITUTE : 0) | JSON_PARTIAL_OUTPUT_ON_ERROR;
        echo json_encode(["success" => true, "data" => $data], $json_flags_gd);
        exit;
    }

    if ($api == 'save_product') {
        $d = json_decode(file_get_contents('php://input'), true);
        if($d) {
            $stmt = $conn->prepare("REPLACE INTO tk_products (id, name, group_name, badge, price, image, description) VALUES (?,?,?,?,?,?,?)");
            if ($stmt) {
                $stmt->bind_param("ssssdss", $d['id'], $d['name'], $d['group_name'], $d['badge'], $d['price'], $d['image'], $d['description']);
                $stmt->execute();
                $stmt->close();
            }
        }
        echo json_encode(["success" => true]);
        exit;
    }

    if ($api == 'delete_product') {
        $id = $conn->real_escape_string($_GET['id'] ?? '');
        $conn->query("DELETE FROM tk_products WHERE id = '$id'");
        echo json_encode(["success" => true]);
        exit;
    }

    // Admin mengirim tagihan SISA DP ke riwayat invoice member (setelah instalasi selesai)
    if ($api == 'send_remaining_dp') {
        header('Content-Type: application/json');
        $d = json_decode(file_get_contents('php://input'), true);
        $oid = (string)($d['id'] ?? '');
        if ($oid === '') { echo json_encode(["success" => false, "msg" => "ID invoice tidak valid"]); exit; }
        $stmt = $conn->prepare("UPDATE tk_orders SET settleSent = 1, settleSentAt = NOW(), remainingAmount = total - dpAmount WHERE id = ? AND status = 'DP' AND trackingStatus = 'SELESAI RAMPUNG' AND (total - dpAmount) > 0.5 AND payStatus = 'DITERIMA' AND payStage = 'DP' AND (settleSent IS NULL OR settleSent = 0)");
        if ($stmt) {
            $stmt->bind_param("s", $oid);
            $stmt->execute();
            $aff = $stmt->affected_rows;
            $stmt->close();
            if ($aff > 0) echo json_encode(["success" => true]);
            else echo json_encode(["success" => false, "msg" => "Syarat belum terpenuhi: status harus DP, instalasi selesai, DP sudah di-APPROVED, ada sisa tagihan, dan belum pernah dikirim."]);
        } else {
            echo json_encode(["success" => false, "msg" => $conn->error]);
        }
        exit;
    }

    // Pesanan produk milik satu pelanggan (tanpa foto bukti) — untuk teknisi.php / panel lain
    if ($api == 'get_customer_orders') {
        header('Content-Type: application/json');
        $cid_q = tk_resolve_cid($conn, (string)($_GET['customer_id'] ?? ''));
        $list_o = [];
        if ($cid_q !== '') {
            $stmt = $conn->prepare("SELECT o.id, o.`date`, o.rawDate, o.name, o.phone, o.address, o.total, o.status, o.trackingStatus, o.items, o.orderSource, o.customerId, o.orderNote FROM tk_orders o LEFT JOIN tk_customer_accounts a ON a.customer_id = ? WHERE o.customerId = ? OR (a.customer_id IS NOT NULL AND ((o.phone = a.phone AND a.phone <> '') OR (o.name = a.name AND a.name <> ''))) ORDER BY o.rawDate DESC LIMIT 100");
            if ($stmt) {
                $stmt->bind_param("ss", $cid_q, $cid_q);
                $stmt->execute();
                $rq = $stmt->get_result();
                if ($rq) while ($ro = $rq->fetch_assoc()) { $ro['items'] = json_decode((string)($ro['items'] ?? ''), true) ?: []; $list_o[] = $ro; }
                $stmt->close();
            }
        }
        echo json_encode(["success" => true, "data" => $list_o], (defined('JSON_INVALID_UTF8_SUBSTITUTE') ? JSON_INVALID_UTF8_SUBSTITUTE : 0) | JSON_PARTIAL_OUTPUT_ON_ERROR);
        exit;
    }

    // Ambil gambar bukti TF satu pesanan (dipanggil admin saat klik "Cek Bukti Transfer")
    if ($api == 'get_order_proof') {
        header('Content-Type: application/json');
        $oid = (string)($_GET['id'] ?? '');
        $img = '';
        $has_legacy = false;
        $rcl = $conn->query("SHOW COLUMNS FROM tk_orders LIKE 'proof_image'");
        if ($rcl && $rcl->num_rows > 0) $has_legacy = true;
        $stmt = $conn->prepare("SELECT proofImage" . ($has_legacy ? ", proof_image AS legacy_proof" : "") . " FROM tk_orders WHERE id = ? LIMIT 1");
        if ($stmt) {
            $stmt->bind_param("s", $oid);
            $stmt->execute();
            $rp = $stmt->get_result();
            $rowp = $rp ? $rp->fetch_assoc() : null;
            $stmt->close();
            if ($rowp) {
                $img = (string)($rowp['proofImage'] ?? '');
                if ($img === '' && $has_legacy) $img = (string)($rowp['legacy_proof'] ?? '');
            }
        }
        if ($img === '') {
            echo json_encode(["success" => false, "msg" => "Bukti transfer belum ada"]);
        } else {
            echo json_encode(["success" => true, "image" => $img]);
        }
        exit;
    }

    // Admin menyetujui (DITERIMA) atau menolak (DITOLAK) bukti TF yang dikirim member
    if ($api == 'review_order_payment') {
        header('Content-Type: application/json');
        $d = json_decode(file_get_contents('php://input'), true);
        $oid = (string)($d['id'] ?? '');
        $decision = strtoupper(trim((string)($d['decision'] ?? '')));
        if ($oid === '' || !in_array($decision, ['DITERIMA', 'DITOLAK'], true)) {
            echo json_encode(["success" => false, "msg" => "Data tidak valid"]);
            exit;
        }
        $stmt = $conn->prepare("UPDATE tk_orders SET payStatus = ?, payReviewedAt = NOW() WHERE id = ? AND payStatus = 'MENUNGGU'");
        if ($stmt) {
            $stmt->bind_param("ss", $decision, $oid);
            $stmt->execute();
            $affected = $stmt->affected_rows;
            $stmt->close();
            if ($affected > 0) {
                $lunas = false;
                if ($decision === 'DITERIMA') {
                    // Pelunasan sisa DP (atau pembayaran penuh) diterima -> status otomatis LUNAS
                    $stl = $conn->prepare("UPDATE tk_orders SET status = 'LUNAS', remainingAmount = 0 WHERE id = ? AND (payStage = 'SISA' OR payAmount >= total - 0.5)");
                    if ($stl) {
                        $stl->bind_param("s", $oid);
                        $stl->execute();
                        $lunas = $stl->affected_rows > 0;
                        $stl->close();
                    }
                }
                echo json_encode(["success" => true, "lunas" => $lunas]);
            } else {
                echo json_encode(["success" => false, "msg" => "Tidak ada pembayaran yang menunggu konfirmasi untuk invoice ini (mungkin sudah diproses)."]);
            }
        } else {
            echo json_encode(["success" => false, "msg" => $conn->error]);
        }
        exit;
    }

    // Upload bukti TF terpisah (dipanggil dari index.php setelah save_order)
    if ($api == 'upload_order_proof') {
        header('Content-Type: application/json');
        $d = json_decode(file_get_contents('php://input'), true);
        if (!$d || empty($d['id'])) {
            echo json_encode(["success" => false, "msg" => "ID pesanan tidak valid"]);
            exit;
        }
        $id = $d['id'];
        $proof = $d['proofImage'] ?? $d['proof_image'] ?? '';
        if (empty($proof) || strpos($proof, 'data:image/') !== 0) {
            echo json_encode(["success" => false, "msg" => "Data gambar tidak valid"]);
            exit;
        }

        $stmt = $conn->prepare("UPDATE tk_orders SET proofImage = ? WHERE id = ?");
        if ($stmt) {
            $stmt->bind_param("ss", $proof, $id);
            $ok = $stmt->execute();
            $err = $stmt->error;
            $stmt->close();
            if ($ok) {
                echo json_encode(["success" => true, "msg" => "Bukti TF berhasil disimpan"]);
            } else {
                echo json_encode(["success" => false, "msg" => "Gagal simpan: " . $err]);
            }
        } else {
            echo json_encode(["success" => false, "msg" => $conn->error]);
        }
        exit;
    }

    if ($api == 'save_order') {
        $d = json_decode(file_get_contents('php://input'), true);
        if($d && !empty($d['id'])) {
            $oid = (string)$d['id'];
            $items = json_encode($d['items'] ?? []);
            $trackingStatus = $d['trackingStatus'] ?? 'PROSES';
            // Terima proofImage atau proof_image (hanya dipakai jika benar-benar berisi gambar)
            $proof = $d['proofImage'] ?? $d['proof_image'] ?? '';
            $hasValidProof = is_string($proof) && strpos($proof, 'data:image/') === 0;

            $f_date = (string)($d['date'] ?? ''); $f_raw = (string)($d['rawDate'] ?? '');
            $f_name = (string)($d['name'] ?? ''); $f_phone = (string)($d['phone'] ?? '');
            $f_addr = (string)($d['address'] ?? ''); $f_total = (float)($d['total'] ?? 0);
            $f_bank = (string)($d['bank'] ?? ''); $f_status = (string)($d['status'] ?? '');
            $f_dp = (float)($d['dpAmount'] ?? 0); $f_rem = (float)($d['remainingAmount'] ?? 0);
            $f_wt = (string)($d['warrantyType'] ?? ''); $f_wd = (string)($d['warrantyDueDate'] ?? '');

            $exists = false;
            $stc = $conn->prepare("SELECT 1 FROM tk_orders WHERE id = ? LIMIT 1");
            if ($stc) {
                $stc->bind_param("s", $oid);
                $stc->execute();
                $rr = $stc->get_result();
                $exists = $rr && $rr->num_rows > 0;
                $stc->close();
            }

            if ($exists) {
                // UPDATE (bukan REPLACE) agar bukti TF & status pembayaran dari member tidak ikut terhapus
                $stmt = $conn->prepare("UPDATE tk_orders SET `date`=?, rawDate=?, name=?, phone=?, address=?, total=?, bank=?, status=?, trackingStatus=?, dpAmount=?, remainingAmount=?, warrantyType=?, warrantyDueDate=?, items=? WHERE id=?");
                if ($stmt) {
                    $stmt->bind_param("sssssdsssddssss", $f_date, $f_raw, $f_name, $f_phone, $f_addr, $f_total, $f_bank, $f_status, $trackingStatus, $f_dp, $f_rem, $f_wt, $f_wd, $items, $oid);
                    $stmt->execute();
                    $stmt->close();
                }
                if (strtoupper(trim($f_status)) !== 'DP') {
                    $stz = $conn->prepare("UPDATE tk_orders SET settleSent = 0 WHERE id = ?");
                    if ($stz) { $stz->bind_param("s", $oid); $stz->execute(); $stz->close(); }
                }
                if ($hasValidProof) {
                    $stp = $conn->prepare("UPDATE tk_orders SET proofImage = ? WHERE id = ?");
                    if ($stp) { $stp->bind_param("ss", $proof, $oid); $stp->execute(); $stp->close(); }
                }
            } else {
                $proof_ins = $hasValidProof ? $proof : '';
                $stmt = $conn->prepare("REPLACE INTO tk_orders (id, date, rawDate, name, phone, address, total, bank, status, trackingStatus, dpAmount, remainingAmount, warrantyType, warrantyDueDate, items, proofImage) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
                if ($stmt) {
                    $stmt->bind_param("ssssssdsssddssss", $oid, $f_date, $f_raw, $f_name, $f_phone, $f_addr, $f_total, $f_bank, $f_status, $trackingStatus, $f_dp, $f_rem, $f_wt, $f_wd, $items, $proof_ins);
                    $stmt->execute();
                    $stmt->close();
                }
            }
        }
        echo json_encode(["success" => true]);
        exit;
    }

    if ($api == 'delete_order') {
        $id = $conn->real_escape_string($_GET['id'] ?? '');
        $conn->query("DELETE FROM tk_orders WHERE id = '$id'");
        $conn->query("DELETE FROM tk_technician_orders WHERE id = '$id'");
        $conn->query("DELETE FROM tk_chats WHERE session_id = '$id'");
        $conn->query("DELETE FROM tk_chat_sessions WHERE session_id = '$id'");
        echo json_encode(["success" => true]);
        exit;
    }

    if ($api == 'save_member') {
        $d = json_decode(file_get_contents('php://input'), true);
        if($d) {
            $perms = json_encode($d['perms']);
            $stmt = $conn->prepare("REPLACE INTO tk_members (id, username, password, perms) VALUES (?,?,?,?)");
            if ($stmt) {
                $stmt->bind_param("ssss", $d['id'], $d['username'], $d['password'], $perms);
                $stmt->execute();
                $stmt->close();
            }
        }
        echo json_encode(["success" => true]);
        exit;
    }

    if ($api == 'delete_member') {
        $id = $conn->real_escape_string($_GET['id'] ?? '');
        $conn->query("DELETE FROM tk_members WHERE id = '$id'");
        echo json_encode(["success" => true]);
        exit;
    }

    if ($api == 'chat_history') {
        $sessionRaw = $_GET['session_id'] ?? '';
        $session = $conn->real_escape_string($sessionRaw);
        if (tk_chat_channel($conn, $sessionRaw, $_GET['channel'] ?? '') === 'ticket') {
            // CHAT LAPORAN KENDALA (terpisah dari Live Chat)
            $res = $conn->query("SELECT sender, message FROM tk_ticket_chats WHERE ticket_id = '$session' ORDER BY id ASC");
        } else {
            // LIVE CHAT CS
            $res = $conn->query("SELECT sender, message FROM tk_chats WHERE session_id = '$session' ORDER BY created_at ASC, id ASC");
        }
        $msgs = [];
        if($res) { while($row = $res->fetch_assoc()) $msgs[] = ['sender' => $row['sender'], 'text' => $row['message']]; }
        echo json_encode(["success" => true, "messages" => $msgs]);
        exit;
    }

    if ($api == 'send_chat') {
        $d = json_decode(file_get_contents('php://input'), true);
        $session = $d['session_id'] ?? 'DEFAULT'; 
        $msg = $d['message'] ?? ''; 
        $sender = $d['sender'] ?? 'customer';

        // CHAT LAPORAN KENDALA: simpan di tabel sendiri, tanpa sesi Live Chat dan tanpa balasan bot AI
        if (tk_chat_channel($conn, $session, $d['channel'] ?? ($_GET['channel'] ?? '')) === 'ticket') {
            if (!tk_is_ticket($conn, $session)) {
                echo json_encode(["success" => false, "msg" => "Tiket tidak ditemukan atau sudah selesai."]);
                exit;
            }
            $stmtT = $conn->prepare("INSERT INTO tk_ticket_chats (ticket_id, sender, message) VALUES (?, ?, ?)");
            if ($stmtT) {
                $stmtT->bind_param("sss", $session, $sender, $msg);
                $stmtT->execute();
                $stmtT->close();
            }
            echo json_encode(["success" => true, "channel" => "ticket"]);
            exit;
        }

        // LIVE CHAT CS (alur asli, tidak berubah)
        $escSession = $conn->real_escape_string($session);
        $conn->query("INSERT IGNORE INTO tk_chat_sessions (session_id, is_bot_active) VALUES ('$escSession', 1)");
        $conn->query("UPDATE tk_chat_sessions SET last_update = CURRENT_TIMESTAMP WHERE session_id = '$escSession'");

        $stmt = $conn->prepare("INSERT INTO tk_chats (session_id, sender, message) VALUES (?, ?, ?)");
        if ($stmt) {
            $stmt->bind_param("sss", $session, $sender, $msg);
            $stmt->execute();
            $stmt->close();
        }

        if ($sender == 'customer') {
            $res = $conn->query("SELECT is_bot_active FROM tk_chat_sessions WHERE session_id = '$escSession'");
            $row = $res ? $res->fetch_assoc() : null;
            
            if ($row && $row['is_bot_active'] == 1) {
                $katalog_text = "";
                $q_prod = $conn->query("SELECT name, group_name, price, description FROM tk_products ORDER BY price ASC");
                if ($q_prod && $q_prod->num_rows > 0) {
                    while($prod = $q_prod->fetch_assoc()) {
                        $harga_format = "Rp " . number_format($prod['price'], 0, ',', '.');
                        $katalog_text .= "- {$prod['name']} (Brand: {$prod['group_name']}) | Harga Resmi: {$harga_format}\n  Deskripsi: {$prod['description']}\n\n";
                    }
                } else {
                    $katalog_text = "Belum ada produk terdaftar di katalog saat ini.";
                }

                $prompt = "Anda adalah 'Asisten Pintar Tekunin AI', sebuah Artificial Intelligence multibahasa tingkat lanjut yang dirancang untuk menjadi yang paling cerdas, akurat, dan adaptif dalam segala aspek ilmu pengetahuan, teknologi, bisnis, pemecahan masalah, maupun layanan pelanggan.
=== DAFTAR KATALOG & HARGA RESMI SAAT INI ===
$katalog_text
=============================================";

                $post = json_encode([
                    "model" => "openai/gpt-oss-120b", 
                    "messages" => [
                        ["role" => "system", "content" => $prompt], 
                        ["role" => "user", "content" => $msg]
                    ],
                    "temperature" => 0.7
                ]);
                
                $ch = curl_init("https://api.groq.com/openai/v1/chat/completions");
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
                curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
                curl_setopt($ch, CURLOPT_HTTPHEADER, [
                    "Authorization: Bearer " . trim($api_key),
                    "Content-Type: application/json"
                ]);
                curl_setopt($ch, CURLOPT_TIMEOUT, 30);
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                
                $api_response = curl_exec($ch);
                $curl_error = curl_error($ch);
                $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);

                $ai_reply = "";
                if (!$curl_error && $http_code == 200) {
                    $ai_data = json_decode($api_response, true);
                    if (isset($ai_data['choices'][0]['message']['content'])) {
                        $ai_reply = trim($ai_data['choices'][0]['message']['content']);
                    }
                }

                if (empty($ai_reply)) {
                    $ai_reply = "Halo! Terima kasih telah menghubungi Tekunin CCTV.";
                }
                
                $stmt2 = $conn->prepare("INSERT INTO tk_chats (session_id, sender, message) VALUES (?, 'bot', ?)");
                if ($stmt2) {
                    $stmt2->bind_param("ss", $session, $ai_reply);
                    $stmt2->execute();
                    $stmt2->close();
                }
            }
        }
        echo json_encode(["success" => true]);
        exit;
    }

    if ($api == 'takeover') {
        $d = json_decode(file_get_contents('php://input'), true);
        $session = $d['session_id'] ?? '';
        $admin = $d['admin_name'] ?? 'Admin';
        $escSession = $conn->real_escape_string($session);
        $escAdmin = $conn->real_escape_string($admin);
        $conn->query("UPDATE tk_chat_sessions SET is_bot_active = 0, admin_name = '$escAdmin' WHERE session_id = '$escSession'");
        
        $sysMsg = "Admin ($admin) yang bertugas.";
        $stmt = $conn->prepare("INSERT INTO tk_chats (session_id, sender, message) VALUES (?, 'system', ?)");
        if ($stmt) {
            $stmt->bind_param("ss", $session, $sysMsg);
            $stmt->execute();
            $stmt->close();
        }
        
        echo json_encode(["success" => true]);
        exit;
    }

    if ($api == 'return_bot') {
        $d = json_decode(file_get_contents('php://input'), true);
        $session = $d['session_id'] ?? '';
        $escSession = $conn->real_escape_string($session);
        $conn->query("UPDATE tk_chat_sessions SET is_bot_active = 1, admin_name = '' WHERE session_id = '$escSession'");
        
        $sysMsg = "Admin Meninggalkan Chat, dilanjutkan CS Tekunin";
        $stmt = $conn->prepare("INSERT INTO tk_chats (session_id, sender, message) VALUES (?, 'system', ?)");
        if ($stmt) {
            $stmt->bind_param("ss", $session, $sysMsg);
            $stmt->execute();
            $stmt->close();
        }
        
        echo json_encode(["success" => true]);
        exit;
    }

    // ==========================================
    // STOK BARANG (tab baru) - tabel dibuat otomatis, tidak menyentuh tabel lama
    // ==========================================
    if (in_array($api, ['stock_list', 'stock_save', 'stock_delete', 'stock_history_clear', 'stock_import'], true)) {
        header('Content-Type: application/json');
        $conn->query("CREATE TABLE IF NOT EXISTS tk_stock (
            sn VARCHAR(100) PRIMARY KEY,
            grup VARCHAR(100) DEFAULT '',
            jumlah DOUBLE DEFAULT 0,
            satuan VARCHAR(30) DEFAULT '',
            brand VARCHAR(100) DEFAULT '',
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )");
        $conn->query("CREATE TABLE IF NOT EXISTS tk_stock_history (
            id INT AUTO_INCREMENT PRIMARY KEY,
            sn VARCHAR(100),
            grup VARCHAR(100) DEFAULT '',
            jumlah DOUBLE DEFAULT 0,
            satuan VARCHAR(30) DEFAULT '',
            brand VARCHAR(100) DEFAULT '',
            tipe VARCHAR(10) DEFAULT 'MASUK',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )");
        // Kolom pencatat transaksi (Admin / nama teknisi) - ditambahkan otomatis bila belum ada
        $chk = $conn->query("SHOW COLUMNS FROM tk_stock_history LIKE 'oleh'");
        if ($chk && $chk->num_rows === 0) { $conn->query("ALTER TABLE tk_stock_history ADD COLUMN oleh VARCHAR(60) DEFAULT 'Admin'"); }

        if ($api == 'stock_list') {
            $stock = []; $hist = [];
            $r = $conn->query("SELECT sn, grup, jumlah, satuan, brand FROM tk_stock ORDER BY updated_at DESC, sn ASC");
            if ($r) { while ($row = $r->fetch_assoc()) { $row['jumlah'] = (float)$row['jumlah']; $stock[] = $row; } }
            $r = $conn->query("SELECT id, sn, grup, jumlah, satuan, brand, tipe, oleh, created_at FROM tk_stock_history ORDER BY id DESC LIMIT 500");
            if ($r) { while ($row = $r->fetch_assoc()) { $row['jumlah'] = (float)$row['jumlah']; $row['oleh'] = $row['oleh'] ?: 'Admin'; $hist[] = $row; } }
            // rev = sidik jari data. Bila klien kirim rev yang sama, tidak perlu kirim ulang seluruh data (realtime hemat bandwidth)
            $rev = md5(json_encode([$stock, $hist]));
            if (isset($_GET['rev']) && $_GET['rev'] === $rev) { echo json_encode(["success" => true, "unchanged" => true, "rev" => $rev]); exit; }
            echo json_encode(["success" => true, "rev" => $rev, "stock" => $stock, "history" => $hist]);
            exit;
        }

        // HAPUS RIWAYAT: hanya mengosongkan tk_stock_history. Jumlah stok (tk_stock) tidak berubah.
        // tk_stock_wo_log sengaja dibiarkan agar WO yang sudah memotong stok tidak terpotong ganda.
        if ($api == 'stock_history_clear') {
            $cnt = 0;
            $r = $conn->query("SELECT COUNT(*) AS c FROM tk_stock_history");
            if ($r && ($row = $r->fetch_assoc())) $cnt = (int)$row['c'];
            if ($conn->query("DELETE FROM tk_stock_history")) { echo json_encode(["success" => true, "deleted" => $cnt]); }
            else { echo json_encode(["success" => false, "msg" => "Gagal menghapus riwayat: " . $conn->error]); }
            exit;
        }

        // IMPORT STOK (CSV / JSON / SQL sudah di-parse di browser; server hanya menerima baris data yang tervalidasi,
        // sehingga file SQL tidak pernah dieksekusi langsung ke database).
        // mode 'add'     : jumlah di file DITAMBAHKAN ke stok yang ada (dicatat MASUK)
        // mode 'replace' : stok DISAMAKAN dengan jumlah di file (selisih dicatat MASUK/KELUAR)
        if ($api == 'stock_import') {
            $in = json_decode(file_get_contents('php://input'), true);
            if (!is_array($in) || !isset($in['rows']) || !is_array($in['rows'])) { echo json_encode(["success" => false, "msg" => "Data import tidak valid."]); exit; }
            $mode = (($in['mode'] ?? 'add') === 'replace') ? 'replace' : 'add';
            if (count($in['rows']) > 5000) { echo json_encode(["success" => false, "msg" => "Maksimal 5000 baris per import."]); exit; }
            $merged = []; $skipped = 0; $errors = [];
            foreach ($in['rows'] as $i => $r) {
                if (!is_array($r)) { $skipped++; continue; }
                $rsn = trim((string)($r['sn'] ?? ''));
                $jml = str_replace(',', '.', trim((string)($r['jumlah'] ?? '0')));
                if ($rsn === '' || mb_strlen($rsn) > 100) { $skipped++; if (count($errors) < 10) $errors[] = "Baris " . ($i + 1) . ": SN kosong / terlalu panjang"; continue; }
                if ($jml === '') $jml = '0';
                if (!is_numeric($jml) || !is_finite((float)$jml) || (float)$jml < 0) { $skipped++; if (count($errors) < 10) $errors[] = "Baris " . ($i + 1) . " (" . $rsn . "): jumlah tidak valid"; continue; }
                $q = (float)$jml;
                $g = mb_substr(trim((string)($r['grup'] ?? '')), 0, 100);
                $u = mb_substr(trim((string)($r['satuan'] ?? '')), 0, 30);
                $b = mb_substr(trim((string)($r['brand'] ?? '')), 0, 100);
                $key = mb_strtolower($rsn);
                if (!isset($merged[$key])) { $merged[$key] = ['sn' => $rsn, 'grup' => $g, 'satuan' => $u, 'brand' => $b, 'jumlah' => $q]; }
                else {
                    // SN dobel dalam satu file: mode tambah = dijumlahkan, mode timpa = baris terakhir yang dipakai
                    $merged[$key]['jumlah'] = ($mode === 'add') ? $merged[$key]['jumlah'] + $q : $q;
                    if ($g !== '') $merged[$key]['grup'] = $g;
                    if ($u !== '') $merged[$key]['satuan'] = $u;
                    if ($b !== '') $merged[$key]['brand'] = $b;
                }
            }
            $baru = 0; $diperbarui = 0; $sama = 0;
            $ohlh = 'Import';
            $conn->begin_transaction();
            $okAll = true;
            foreach ($merged as $it) {
                $rsn = $it['sn']; $q = $it['jumlah'];
                $st = $conn->prepare("SELECT sn, jumlah, grup, satuan, brand FROM tk_stock WHERE sn = ? FOR UPDATE");
                $st->bind_param("s", $rsn); $st->execute();
                $res = $st->get_result(); $cur = $res ? $res->fetch_assoc() : null; $st->close();
                $g = $it['grup']; $u = $it['satuan']; $b = $it['brand'];
                if ($cur) {
                    // Data lama dipertahankan bila kolom di file kosong
                    if ($g === '') $g = $cur['grup'];
                    if ($u === '') $u = $cur['satuan'];
                    if ($b === '') $b = $cur['brand'];
                    $old = (float)$cur['jumlah'];
                    $new = ($mode === 'add') ? $old + $q : $q;
                    $diff = $new - $old;
                    if ($diff == 0 && $g === $cur['grup'] && $u === $cur['satuan'] && $b === $cur['brand']) { $sama++; continue; }
                    $st = $conn->prepare("UPDATE tk_stock SET jumlah = ?, grup = ?, satuan = ?, brand = ? WHERE sn = ?");
                    $st->bind_param("dssss", $new, $g, $u, $b, $cur['sn']);
                    $okAll = $st->execute(); $st->close();
                    $histSn = $cur['sn'];
                    $diperbarui++;
                } else {
                    $diff = $q;
                    $st = $conn->prepare("INSERT INTO tk_stock (sn, grup, jumlah, satuan, brand) VALUES (?,?,?,?,?)");
                    $st->bind_param("ssdss", $rsn, $g, $q, $u, $b);
                    $okAll = $st->execute(); $st->close();
                    $histSn = $rsn;
                    $baru++;
                }
                if ($okAll && $diff != 0) {
                    $tipeH = $diff > 0 ? 'MASUK' : 'KELUAR'; $qh = abs($diff);
                    $st = $conn->prepare("INSERT INTO tk_stock_history (sn, grup, jumlah, satuan, brand, tipe, oleh) VALUES (?,?,?,?,?,?,?)");
                    $st->bind_param("ssdssss", $histSn, $g, $qh, $u, $b, $tipeH, $ohlh);
                    $okAll = $st->execute(); $st->close();
                }
                if (!$okAll) break;
            }
            if ($okAll) {
                $conn->commit();
                echo json_encode(["success" => true, "baru" => $baru, "diperbarui" => $diperbarui, "sama" => $sama, "dilewati" => $skipped, "errors" => $errors]);
            } else {
                $conn->rollback();
                echo json_encode(["success" => false, "msg" => "Import dibatalkan (tidak ada data yang berubah): " . $conn->error]);
            }
            exit;
        }

        $d = json_decode(file_get_contents('php://input'), true);
        if (!is_array($d)) { echo json_encode(["success" => false, "msg" => "Data tidak valid."]); exit; }
        $sn = trim((string)($d['sn'] ?? ''));
        if ($sn === '' || mb_strlen($sn) > 100) { echo json_encode(["success" => false, "msg" => "SN wajib diisi (maks. 100 karakter)."]); exit; }
        $oleh = mb_substr(trim((string)($d['oleh'] ?? '')), 0, 60);
        if ($oleh === '') $oleh = 'Admin';

        if ($api == 'stock_delete') {
            $conn->begin_transaction();
            $st = $conn->prepare("SELECT grup, jumlah, satuan, brand FROM tk_stock WHERE sn = ? FOR UPDATE");
            $st->bind_param("s", $sn); $st->execute();
            $res = $st->get_result(); $row = $res ? $res->fetch_assoc() : null; $st->close();
            if (!$row) { $conn->rollback(); echo json_encode(["success" => false, "msg" => "SN tidak ditemukan di stok."]); exit; }
            $ok = true;
            if ((float)$row['jumlah'] > 0) {
                $tipe = 'KELUAR'; $qty = (float)$row['jumlah'];
                $st = $conn->prepare("INSERT INTO tk_stock_history (sn, grup, jumlah, satuan, brand, tipe, oleh) VALUES (?,?,?,?,?,?,?)");
                $st->bind_param("ssdssss", $sn, $row['grup'], $qty, $row['satuan'], $row['brand'], $tipe, $oleh);
                $ok = $st->execute(); $st->close();
            }
            if ($ok) {
                $st = $conn->prepare("DELETE FROM tk_stock WHERE sn = ?");
                $st->bind_param("s", $sn); $ok = $st->execute(); $st->close();
            }
            if ($ok) { $conn->commit(); echo json_encode(["success" => true]); }
            else { $conn->rollback(); echo json_encode(["success" => false, "msg" => "Gagal menghapus: " . $conn->error]); }
            exit;
        }

        // stock_save
        $grup   = mb_substr(trim((string)($d['grup'] ?? '')), 0, 100);
        $satuan = mb_substr(trim((string)($d['satuan'] ?? '')), 0, 30);
        $brand  = mb_substr(trim((string)($d['brand'] ?? '')), 0, 100);
        $qty    = (float)($d['jumlah'] ?? 0);
        $tipe   = strtoupper((string)($d['tipe'] ?? 'MASUK'));
        if (!in_array($tipe, ['MASUK', 'KELUAR'], true)) $tipe = 'MASUK';
        if (!is_finite($qty) || $qty <= 0) { echo json_encode(["success" => false, "msg" => "Jumlah harus lebih dari 0."]); exit; }

        $conn->begin_transaction();
        $st = $conn->prepare("SELECT jumlah, grup, satuan, brand FROM tk_stock WHERE sn = ? FOR UPDATE");
        $st->bind_param("s", $sn); $st->execute();
        $res = $st->get_result(); $cur = $res ? $res->fetch_assoc() : null; $st->close();
        $ok = true; $msg = '';

        if ($tipe === 'KELUAR') {
            if (!$cur) { $ok = false; $msg = "SN '$sn' belum ada di stok."; }
            elseif ((float)$cur['jumlah'] < $qty) { $ok = false; $msg = "Stok tidak cukup. Tersedia: " . (float)$cur['jumlah'] . " " . $cur['satuan']; }
            else {
                // Barang keluar: pakai grup/satuan/brand yang tercatat bila form dikosongkan
                if ($grup === '') $grup = $cur['grup'];
                if ($satuan === '') $satuan = $cur['satuan'];
                if ($brand === '') $brand = $cur['brand'];
                $st = $conn->prepare("UPDATE tk_stock SET jumlah = jumlah - ? WHERE sn = ?");
                $st->bind_param("ds", $qty, $sn); $ok = $st->execute(); $st->close();
            }
        } else {
            if ($cur) {
                $st = $conn->prepare("UPDATE tk_stock SET jumlah = jumlah + ?, grup = ?, satuan = ?, brand = ? WHERE sn = ?");
                $st->bind_param("dssss", $qty, $grup, $satuan, $brand, $sn);
            } else {
                $st = $conn->prepare("INSERT INTO tk_stock (sn, grup, jumlah, satuan, brand) VALUES (?,?,?,?,?)");
                $st->bind_param("ssdss", $sn, $grup, $qty, $satuan, $brand);
            }
            $ok = $st->execute(); $st->close();
        }
        if ($ok) {
            $st = $conn->prepare("INSERT INTO tk_stock_history (sn, grup, jumlah, satuan, brand, tipe, oleh) VALUES (?,?,?,?,?,?,?)");
            $st->bind_param("ssdssss", $sn, $grup, $qty, $satuan, $brand, $tipe, $oleh);
            $ok = $st->execute(); $st->close();
        }
        if ($ok) { $conn->commit(); echo json_encode(["success" => true]); }
        else { $conn->rollback(); echo json_encode(["success" => false, "msg" => $msg !== '' ? $msg : ("Gagal menyimpan: " . $conn->error)]); }
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="id" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#0C2327">
    <title>Admin Dashboard | Tekunin CCTV</title>
    <link id="site-favicon" rel="icon" type="image/png" href="logo.png">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Onest:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: { sans: ['Onest', 'Plus Jakarta Sans', 'ui-sans-serif', 'system-ui', 'sans-serif'] },
                    /* Palet "lensa": sky -> teal, slate -> abu kehijauan agar seluruh konten dinamis ikut serasi */
                    colors: {
                        sky:   { 50:'#EAF7F7', 100:'#D2EEEF', 200:'#A9DDE0', 300:'#74C5CA', 400:'#3DAAB1', 500:'#198F97', 600:'#0F7A82', 700:'#0C6169', 800:'#0B4D54', 900:'#0A3C42', 950:'#062429' },
                        slate: { 50:'#F4F7F7', 100:'#E8EEEE', 200:'#D5DFDF', 300:'#B7C6C7', 400:'#8DA2A4', 500:'#66797C', 600:'#4B5E61', 700:'#384A4D', 800:'#243538', 900:'#14262A', 950:'#0B1A1D' }
                    },
                    borderRadius: { 'xl': '0.7rem', '2xl': '0.85rem', '3xl': '1.1rem' },
                    animation: {
                        'fade-in-up': 'fadeInUp 0.7s cubic-bezier(0.16, 1, 0.3, 1) forwards',
                        'slide-in-right': 'slideInRight 0.4s cubic-bezier(0.16, 1, 0.3, 1) forwards',
                        'pop-in': 'popIn 0.3s cubic-bezier(0.16, 1, 0.3, 1) forwards',
                    }
                }
            }
        }
    </script>
    <style>
        :root {
            --ink-950:#081B1E; --ink-900:#0C2327; --ink-800:#12303A; --ink-700:#194047;
            --ink-line:rgba(255,255,255,.07); --ink-text:#9FB8BB; --ink-mute:#6E8E92;
            --lamp:#F5A524; --lamp-ink:#3A2500; --paper:#EDF1F0; --teal:#0F7A82;
        }
        html, body { height: 100%; }
        body { height: 100vh; height: 100dvh; font-feature-settings: "ss01"; }

        ::-webkit-scrollbar { width: 6px; height: 6px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: #B7C6C7; border-radius: 10px; }
        .hide-scrollbar::-webkit-scrollbar { display: none; }
        .hide-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }
        .glass-dark { background: rgba(12, 35, 39, 0.94); backdrop-filter: blur(16px); }

        @keyframes popIn { from { opacity: 0; transform: translateY(8px) scale(.97); } to { opacity: 1; transform: none; } }
        @keyframes slideInRight { from { opacity: 0; transform: translateX(24px); } to { opacity: 1; transform: none; } }
        @keyframes fadeInUp { from { opacity: 0; transform: translateY(14px); } to { opacity: 1; transform: none; } }
        @keyframes tkPulse { 0% { box-shadow: 0 0 0 0 rgba(245,165,36,.55); } 70% { box-shadow: 0 0 0 7px rgba(245,165,36,0); } 100% { box-shadow: 0 0 0 0 rgba(245,165,36,0); } }
        @keyframes tkBlink { 50% { opacity: .2; } }

        /* ============ LOGIN ============ */
        #login-section .tk-art { display: none; position: relative; width: 54%; background: var(--ink-950); color: #fff; padding: 44px 52px; flex-direction: column; justify-content: space-between; overflow: hidden; }
        @media (min-width: 1024px) { #login-section .tk-art { display: flex; } }
        #login-section .tk-art-brand { display: flex; align-items: center; gap: 12px; font-weight: 800; font-size: 16px; }
        #login-section .tk-wall { display: grid; grid-template-columns: repeat(2, 1fr); gap: 14px; width: 100%; max-width: 580px; margin: 28px 0; }
        #login-section .tk-cam {
            position: relative; aspect-ratio: 16 / 10; border-radius: 10px; padding: 14px 16px;
            background: repeating-linear-gradient(0deg, rgba(255,255,255,.035) 0 1px, transparent 1px 3px), #0F2D32;
            border: 1px solid rgba(255,255,255,.08); display: flex; flex-direction: column; justify-content: space-between; overflow: hidden;
        }
        #login-section .tk-cam::before, #login-section .tk-cam::after { content: ""; position: absolute; width: 14px; height: 14px; border: 0 solid rgba(245,165,36,.85); }
        #login-section .tk-cam::before { left: 8px; top: 8px; border-left-width: 2px; border-top-width: 2px; }
        #login-section .tk-cam::after { right: 8px; bottom: 8px; border-right-width: 2px; border-bottom-width: 2px; }
        #login-section .tk-cam-name { font-size: 13.5px; font-weight: 700; color: #fff; line-height: 1.2; }
        #login-section .tk-cam-ch { font-size: 11.5px; color: var(--ink-mute); font-weight: 500; }
        #login-section .tk-cam-ic { position: absolute; right: 18px; top: 50%; transform: translateY(-50%); font-size: 34px; color: rgba(255,255,255,.1); }
        #login-section .tk-cam-foot { display: flex; align-items: center; justify-content: space-between; font-size: 11.5px; color: var(--ink-text); font-variant-numeric: tabular-nums; }
        #login-section .tk-rec { display: inline-flex; align-items: center; gap: 6px; color: #fff; font-weight: 700; font-size: 11px; letter-spacing: .04em; }
        #login-section .tk-rec i { width: 7px; height: 7px; border-radius: 50%; background: #F43F5E; animation: tkBlink 1.4s infinite; }
        #login-section .tk-art h1 { font-size: 30px; line-height: 1.2; font-weight: 800; max-width: 470px; letter-spacing: -.01em; }
        #login-section .tk-art p { color: var(--ink-text); font-size: 14px; margin-top: 10px; max-width: 440px; line-height: 1.6; }

        #login-section .tk-formside { flex: 1; display: flex; align-items: center; justify-content: center; padding: 28px 20px; }
        #login-section .tk-card { width: 100%; max-width: 400px; animation: popIn .45s cubic-bezier(.16,1,.3,1) both; }
        #login-section .tk-mobile-brand { display: flex; align-items: center; gap: 10px; font-weight: 800; color: var(--ink-900); margin-bottom: 28px; }
        @media (min-width: 1024px) { #login-section .tk-mobile-brand { display: none; } }
        #login-section .tk-mobile-brand .tk-brand-mark { background: var(--ink-900); }
        #login-section .tk-card h2 { font-size: 26px; font-weight: 800; color: #14262A; letter-spacing: -.01em; }
        #login-section .tk-card .tk-lead { color: #66797C; font-size: 14px; margin: 6px 0 26px; }
        #login-section .tk-label { display: block; font-size: 12.5px; font-weight: 600; color: #4B5E61; margin-bottom: 6px; }
        #login-section .tk-input { width: 100%; height: 48px; padding: 0 14px; background: #fff; border: 1px solid #D5DFDF; border-radius: 12px; font-size: 14px; font-weight: 500; color: #14262A; transition: border-color .15s, box-shadow .15s; }
        #login-section .tk-input:focus { outline: none; border-color: var(--teal); box-shadow: 0 0 0 3px rgba(23,142,150,.18); }
        #login-section .tk-passwrap { position: relative; }
        #login-section .tk-passwrap .tk-input { padding-right: 46px; }
        #login-section .tk-eye { position: absolute; right: 4px; top: 4px; width: 40px; height: 40px; border-radius: 9px; color: #66797C; display: grid; place-items: center; }
        #login-section .tk-eye:hover { background: #E8EEEE; }
        #login-section .tk-error { font-size: 12.5px; color: #BE123C; font-weight: 600; background: #FFF1F2; border: 1px solid #FECDD3; padding: 10px 12px; border-radius: 10px; }
        #login-section .tk-submit { width: 100%; height: 48px; border-radius: 12px; background: var(--teal); color: #fff; font-size: 14px; font-weight: 700; display: flex; align-items: center; justify-content: center; gap: 8px; transition: background .15s, transform .1s; }
        #login-section .tk-submit:hover { background: #0C6169; }
        #login-section .tk-submit:active { transform: scale(.985); }
        #login-section :focus-visible { outline: 2px solid var(--lamp); outline-offset: 2px; }
        #login-section input:focus-visible { outline: none; }

        /* ============ SHELL: SIDEBAR ============ */
        #admin-section { background: var(--paper); }
        #admin-section .tk-aside { width: 272px; flex: none; height: 100%; display: flex; flex-direction: column; background: var(--ink-900); color: var(--ink-text); border-right: 1px solid #081B1E; transition: width .22s ease; }
        #admin-section .tk-brand { display: flex; align-items: center; gap: 12px; padding: 20px 18px 14px; }
        .tk-brand-mark { width: 40px; height: 40px; border-radius: 12px; background: var(--ink-700); display: grid; place-items: center; flex: none; }
        #admin-section .tk-brand-name { color: #fff; font-weight: 800; font-size: 15px; line-height: 1.15; }
        #admin-section .tk-brand-sub { font-size: 12px; color: var(--ink-mute); margin-top: 2px; }
        #admin-section .tk-drawer-close { display: none; margin-left: auto; width: 36px; height: 36px; border-radius: 10px; color: var(--ink-text); place-items: center; }
        #admin-section .tk-drawer-close:hover { background: var(--ink-800); color: #fff; }

        #admin-section .tk-nav-scroll { flex: 1; min-height: 0; overflow-y: auto; padding: 2px 12px 12px; }
        #admin-section .tk-group-title { padding: 16px 10px 6px; font-size: 12px; font-weight: 600; color: var(--ink-mute); }
        #admin-section .tk-nav { position: relative; display: flex; align-items: center; gap: 12px; width: 100%; padding: 7px 10px; border-radius: 12px; color: var(--ink-text); font-size: 13.5px; font-weight: 600; text-align: left; transition: background .15s, color .15s; }
        #admin-section .tk-nav:hover { background: var(--ink-800); color: #fff; }
        #admin-section .tk-nav-ic { width: 34px; height: 34px; border-radius: 10px; display: grid; place-items: center; background: rgba(255,255,255,.05); font-size: 14px; flex: none; transition: background .15s, color .15s; }
        #admin-section .tk-nav.tk-active { background: var(--ink-700); color: #fff; }
        #admin-section .tk-nav.tk-active .tk-nav-ic { background: var(--lamp); color: var(--lamp-ink); }
        #admin-section .tk-nav.tk-active::before { content: ""; position: absolute; left: -12px; top: 9px; bottom: 9px; width: 4px; border-radius: 0 4px 4px 0; background: var(--lamp); }
        #admin-section .tk-nav-label { flex: 1; min-width: 0; line-height: 1.25; }
        #admin-section .tk-badge { background: #E11D48; color: #fff; border-radius: 999px; padding: 1px 8px; font-size: 11px; font-weight: 800; min-width: 22px; text-align: center; }

        #admin-section .tk-subnav { display: none; margin: 2px 0 6px 27px; padding-left: 14px; border-left: 1px solid var(--ink-line); }
        #admin-section .tk-navgroup.tk-open .tk-subnav { display: block; }
        #admin-section .tk-sub { display: block; width: 100%; text-align: left; padding: 6px 10px; border-radius: 8px; font-size: 12.5px; font-weight: 500; color: var(--ink-mute); transition: background .15s, color .15s; }
        #admin-section .tk-sub:hover { background: var(--ink-800); color: #fff; }

        #admin-section .tk-user { display: flex; align-items: center; gap: 10px; padding: 12px; border-top: 1px solid var(--ink-line); }
        #admin-section .tk-avatar { width: 38px; height: 38px; border-radius: 50%; background: var(--ink-700); color: #fff; display: grid; place-items: center; font-weight: 800; font-size: 14px; flex: none; }
        #admin-section .tk-user-meta { min-width: 0; }
        #admin-section .tk-user-name { color: #fff; font-size: 13px; font-weight: 700; line-height: 1.2; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        #admin-section .tk-user-role { font-size: 12px; color: var(--ink-mute); display: flex; align-items: center; gap: 6px; margin-top: 1px; }
        #admin-section .tk-dot { width: 7px; height: 7px; border-radius: 50%; background: #34D399; flex: none; }
        #admin-section .tk-logout { margin-left: auto; width: 38px; height: 38px; border-radius: 10px; display: grid; place-items: center; color: var(--ink-text); flex: none; transition: background .15s, color .15s; }
        #admin-section .tk-logout:hover { background: rgba(225,29,72,.18); color: #FDA4AF; }
        #admin-section .tk-aside :focus-visible { outline: 2px solid var(--lamp); outline-offset: -2px; border-radius: 12px; }

        /* Sidebar mode ringkas (desktop) */
        @media (min-width: 1024px) {
            #admin-section.tk-collapsed .tk-aside { width: 76px; }
            #admin-section.tk-collapsed .tk-brand { justify-content: center; padding-left: 0; padding-right: 0; }
            #admin-section.tk-collapsed .tk-brand-text,
            #admin-section.tk-collapsed .tk-nav-label,
            #admin-section.tk-collapsed .tk-subnav,
            #admin-section.tk-collapsed .tk-user-meta { display: none; }
            #admin-section.tk-collapsed .tk-nav { justify-content: center; padding: 7px 0; }
            #admin-section.tk-collapsed .tk-nav.tk-active::before { left: -12px; }
            #admin-section.tk-collapsed .tk-group-title { height: 1px; background: var(--ink-line); margin: 12px 10px; padding: 0; font-size: 0; color: transparent; }
            #admin-section.tk-collapsed .tk-badge { position: absolute; top: 1px; right: 6px; padding: 0 5px; min-width: 18px; font-size: 10px; }
            #admin-section.tk-collapsed .tk-user { flex-direction: column; padding: 12px 8px; }
            #admin-section.tk-collapsed .tk-logout { margin-left: 0; }
        }

        /* Sidebar sebagai drawer (mobile / tablet) */
        #tk-overlay { display: none; }
        @media (max-width: 1023.98px) {
            #admin-section .tk-aside { position: fixed; top: 0; bottom: 0; left: 0; z-index: 45; width: 290px; transform: translateX(-102%); transition: transform .26s cubic-bezier(.16,1,.3,1); box-shadow: 12px 0 40px rgba(0,0,0,.35); }
            #admin-section.tk-drawer .tk-aside { transform: none; }
            #admin-section .tk-drawer-close { display: grid; }
            #tk-overlay { display: block; position: fixed; inset: 0; z-index: 44; background: rgba(5,16,18,.55); opacity: 0; pointer-events: none; transition: opacity .2s; }
            #admin-section.tk-drawer #tk-overlay { opacity: 1; pointer-events: auto; }
        }

        /* ============ SHELL: TOPBAR & KONTEN ============ */
        #admin-section .tk-topbar { height: 64px; flex: none; display: flex; align-items: center; gap: 12px; padding: 0 16px; background: #fff; border-bottom: 1px solid #DCE5E5; }
        @media (min-width: 768px) { #admin-section .tk-topbar { padding: 0 32px; } }
        #admin-section .tk-iconbtn { width: 40px; height: 40px; border-radius: 10px; display: grid; place-items: center; color: #4B5E61; flex: none; transition: background .15s; }
        #admin-section .tk-iconbtn:hover { background: #E8EEEE; }
        #admin-section .tk-crumb { min-width: 0; display: flex; flex-direction: column; line-height: 1.2; }
        #admin-section .tk-crumb-group { font-size: 12px; color: #66797C; font-weight: 600; }
        #admin-section .tk-crumb-title { font-size: 16.5px; font-weight: 800; color: #14262A; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; letter-spacing: -.005em; }
        #admin-section .tk-topright { margin-left: auto; display: flex; align-items: center; gap: 14px; }
        #admin-section .tk-sync { display: none; align-items: center; gap: 9px; font-size: 12px; font-weight: 600; color: #4B5E61; background: #F4F7F7; border: 1px solid #DCE5E5; border-radius: 999px; padding: 6px 13px; }
        @media (min-width: 768px) { #admin-section .tk-sync { display: inline-flex; } }
        #admin-section .tk-lamp { width: 8px; height: 8px; border-radius: 50%; background: var(--lamp); animation: tkPulse 2s infinite; }
        #admin-section .tk-clock { text-align: right; line-height: 1.2; font-variant-numeric: tabular-nums; }
        #admin-section .tk-clock span { display: block; font-size: 14px; font-weight: 800; color: #14262A; }
        #admin-section .tk-clock small { display: none; font-size: 11.5px; font-weight: 500; color: #66797C; }
        @media (min-width: 640px) { #admin-section .tk-clock small { display: block; } }

        #admin-scroll { background: var(--paper); }

        /* ============ KOMPONEN BERSAMA ============ */
        #admin-section .tk-tool { display: inline-flex; align-items: center; gap: 7px; padding: 8px 12px; border-radius: 10px; border: 1px solid #D5DFDF; background: #fff; color: #243538; font-size: 12px; font-weight: 700; transition: background .15s, border-color .15s; }
        #admin-section .tk-tool:hover { background: #F4F7F7; border-color: #B7C6C7; }
        #admin-section .tk-tool i { font-size: 12px; }
        #admin-section .tk-tool-export i { color: #059669; }
        #admin-section .tk-tool-import i { color: #D97706; }
        #admin-section .tk-tool-danger i { color: #E11D48; }
        #admin-section .tk-tool-danger:hover { background: #FFF1F2; border-color: #FDA4AF; }

        #admin-section label.uppercase, #admin-section h4.uppercase, #admin-section h5.uppercase { text-transform: none; letter-spacing: 0; }
        #admin-section :focus-visible { outline: 2px solid var(--lamp); outline-offset: 2px; }
        #admin-section input:focus-visible, #admin-section select:focus-visible, #admin-section textarea:focus-visible { outline: none; }
        #admin-section input:focus, #admin-section select:focus, #admin-section textarea:focus { box-shadow: 0 0 0 3px rgba(23,142,150,.16); }
        #admin-section .shadow-sm { box-shadow: 0 1px 0 rgba(12,35,39,.03), 0 1px 3px rgba(12,35,39,.06); }
        #admin-section button, #admin-section label { -webkit-tap-highlight-color: transparent; }

        @media (prefers-reduced-motion: reduce) {
            *, *::before, *::after { animation: none !important; transition: none !important; scroll-behavior: auto !important; }
        }
    </style>
</head>
<body class="bg-slate-50 text-slate-800 antialiased flex flex-col selection:bg-sky-500 selection:text-white overflow-hidden">

    <!-- Login Section -->
    <div id="login-section" class="flex w-full flex-1 min-h-0 relative z-50 bg-[#EDF1F0]">
        <div class="tk-art" aria-hidden="true">
            <div class="tk-art-brand">
                <span class="tk-brand-mark">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="9" stroke="#F5A524" stroke-width="2"/><circle cx="12" cy="12" r="4.2" stroke="#fff" stroke-width="2"/><circle cx="14" cy="10" r="1.2" fill="#fff"/></svg>
                </span>
                Tekunin CCTV
            </div>
            <div>
                <div class="tk-wall">
                    <div class="tk-cam"><div><div class="tk-cam-name">Pesanan masuk</div><div class="tk-cam-ch">Kamera 1</div></div><i class="fa-solid fa-inbox tk-cam-ic"></i><div class="tk-cam-foot"><span class="tk-tc">00:00:00</span><span class="tk-rec"><i></i>REC</span></div></div>
                    <div class="tk-cam"><div><div class="tk-cam-name">Laporan teknisi</div><div class="tk-cam-ch">Kamera 2</div></div><i class="fa-solid fa-screwdriver-wrench tk-cam-ic"></i><div class="tk-cam-foot"><span class="tk-tc">00:00:00</span><span class="tk-rec"><i></i>REC</span></div></div>
                    <div class="tk-cam"><div><div class="tk-cam-name">Laporan kendala</div><div class="tk-cam-ch">Kamera 3</div></div><i class="fa-solid fa-ticket tk-cam-ic"></i><div class="tk-cam-foot"><span class="tk-tc">00:00:00</span><span class="tk-rec"><i></i>REC</span></div></div>
                    <div class="tk-cam"><div><div class="tk-cam-name">Live chat</div><div class="tk-cam-ch">Kamera 4</div></div><i class="fa-solid fa-comments tk-cam-ic"></i><div class="tk-cam-foot"><span class="tk-tc">00:00:00</span><span class="tk-rec"><i></i>REC</span></div></div>
                </div>
            </div>
            <div>
                <h1>Pantau pesanan, teknisi, dan pelanggan dari satu panel.</h1>
                <p>Status pembayaran, work order lapangan, laporan kendala, dan percakapan pelanggan tersusun rapi di satu tempat.</p>
            </div>
        </div>

        <div class="tk-formside">
            <div class="tk-card">
                <div class="tk-mobile-brand">
                    <span class="tk-brand-mark">
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="9" stroke="#F5A524" stroke-width="2"/><circle cx="12" cy="12" r="4.2" stroke="#fff" stroke-width="2"/><circle cx="14" cy="10" r="1.2" fill="#fff"/></svg>
                    </span>
                    Tekunin CCTV
                </div>
                <h2>Masuk ke panel admin</h2>
                <p class="tk-lead">Gunakan akun admin Tekunin untuk melanjutkan.</p>
                <form onsubmit="handleLogin(event)" class="space-y-4">
                    <div>
                        <label class="tk-label" for="login-user">Username</label>
                        <input type="text" id="login-user" required autocomplete="username" class="tk-input" placeholder="">
                    </div>
                    <div>
                        <label class="tk-label" for="login-pass">Password</label>
                        <div class="tk-passwrap">
                            <input type="password" id="login-pass" required autocomplete="current-password" class="tk-input" placeholder="">
                            <button type="button" class="tk-eye" onclick="tkTogglePass(this)" aria-label="Tampilkan password"><i class="fa-solid fa-eye"></i></button>
                        </div>
                    </div>
                    <div id="login-error" class="hidden tk-error" role="alert">Username atau Password salah!</div>
                    <button type="submit" class="tk-submit"><i class="fa-solid fa-lock text-[12px]"></i> Masuk ke panel</button>
                </form>
            </div>
        </div>
    </div>

    <!-- Admin Section -->
    <div id="admin-section" class="hidden flex-col lg:flex-row w-full flex-1 min-h-0 relative z-10">
        <div id="tk-overlay" onclick="tkCloseDrawer()"></div>

        <!-- SIDEBAR -->
        <aside class="tk-aside" aria-label="Navigasi admin">
            <div class="tk-brand">
                <span class="tk-brand-mark">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="9" stroke="#F5A524" stroke-width="2"/><circle cx="12" cy="12" r="4.2" stroke="#fff" stroke-width="2"/><circle cx="14" cy="10" r="1.2" fill="#fff"/></svg>
                </span>
                <div class="tk-brand-text">
                    <div class="tk-brand-name">Tekunin CCTV</div>
                    <div class="tk-brand-sub">Panel admin</div>
                </div>
                <button type="button" class="tk-drawer-close" onclick="tkCloseDrawer()" aria-label="Tutup menu"><i class="fa-solid fa-xmark"></i></button>
            </div>

            <nav class="tk-nav-scroll hide-scrollbar">
                <div class="tk-group-title">Operasional</div>
                <button type="button" onclick="switchAdminTab('pelanggan')" id="tab-btn-pelanggan" class="tk-nav tk-active" title="Pesanan Masuk" aria-current="page">
                    <span class="tk-nav-ic"><i class="fa-solid fa-inbox"></i></span><span class="tk-nav-label">Pesanan Masuk</span>
                </button>
                <button type="button" onclick="switchAdminTab('teknisi-reports')" id="tab-btn-teknisi-reports" class="tk-nav" title="Laporan Teknisi &amp; W.O.">
                    <span class="tk-nav-ic"><i class="fa-solid fa-screwdriver-wrench"></i></span><span class="tk-nav-label">Laporan Teknisi &amp; W.O.</span>
                </button>
                <button type="button" onclick="switchAdminTab('laporan')" id="tab-btn-laporan" class="tk-nav" title="Laporan Kendala">
                    <span class="tk-nav-ic"><i class="fa-solid fa-ticket"></i></span><span class="tk-nav-label">Laporan Kendala</span><span id="badge-ticket-count" class="tk-badge hidden">0</span>
                </button>
                <button type="button" onclick="switchAdminTab('live-chat')" id="tab-btn-live-chat" class="tk-nav" title="Live Chat CS">
                    <span class="tk-nav-ic"><i class="fa-solid fa-comments"></i></span><span class="tk-nav-label">Live Chat CS</span>
                </button>

                <div class="tk-group-title">Data</div>
                <button type="button" onclick="switchAdminTab('katalog')" id="tab-btn-katalog" class="tk-nav" title="Katalog Produk">
                    <span class="tk-nav-ic"><i class="fa-solid fa-box-open"></i></span><span class="tk-nav-label">Katalog Produk</span>
                </button>
                <button type="button" onclick="switchAdminTab('stok')" id="tab-btn-stok" class="tk-nav" title="Stok Barang">
                    <span class="tk-nav-ic"><i class="fa-solid fa-boxes-stacked"></i></span><span class="tk-nav-label">Stok Barang</span>
                </button>
                <div class="tk-navgroup" id="navgrp-akses-member">
                    <button type="button" onclick="switchAdminTab('akses-member')" id="tab-btn-akses-member" class="tk-nav" title="Akses Member">
                        <span class="tk-nav-ic"><i class="fa-solid fa-id-card"></i></span><span class="tk-nav-label">Akses Member</span>
                    </button>
                    <div class="tk-subnav">
                        <button type="button" class="tk-sub" onclick="tkGoSection('akses-member','admin-section-data-pelanggan')">Database pelanggan</button>
                    </div>
                </div>

                <div class="tk-group-title">Pengaturan</div>
                <div class="tk-navgroup" id="navgrp-tambah-anggota">
                    <button type="button" onclick="switchAdminTab('tambah-anggota')" id="tab-btn-tambah-anggota" class="tk-nav" title="Akun Anggota">
                        <span class="tk-nav-ic"><i class="fa-solid fa-users-gear"></i></span><span class="tk-nav-label">Akun Anggota</span>
                    </button>
                    <div class="tk-subnav">
                        <button type="button" class="tk-sub" onclick="tkGoSection('tambah-anggota','admin-section-akses-teknisi')">Akses teknisi</button>
                    </div>
                </div>
            </nav>

            <div class="tk-user">
                <span class="tk-avatar" id="tk-avatar">A</span>
                <div class="tk-user-meta">
                    <div class="tk-user-name" id="tk-user-name">Admin</div>
                    <div class="tk-user-role"><span class="tk-dot"></span><span id="admin-user-badge">Aktif</span></div>
                </div>
                <button type="button" class="tk-logout" onclick="logoutAdmin()" title="Keluar" aria-label="Keluar"><i class="fa-solid fa-right-from-bracket"></i></button>
            </div>
        </aside>

        <!-- AREA UTAMA -->
        <div class="flex flex-col flex-1 min-w-0 min-h-0">
            <header class="tk-topbar">
                <button type="button" class="tk-iconbtn" onclick="tkToggleSidebar()" aria-label="Buka atau tutup menu"><i class="fa-solid fa-bars"></i></button>
                <div class="tk-crumb">
                    <span class="tk-crumb-group" id="tk-crumb-group">Operasional</span>
                    <span class="tk-crumb-title" id="tk-crumb-title">Pesanan Masuk</span>
                </div>
                <div class="tk-topright">
                    <span class="tk-sync"><span class="tk-lamp"></span>Sinkron otomatis tiap 5 detik</span>
                    <div class="tk-clock"><span id="tk-clock-time">--:--:--</span><small id="tk-clock-date"></small></div>
                </div>
            </header>

            <div id="admin-scroll" class="flex-1 min-h-0 overflow-y-auto">
            <div class="tk-content p-4 md:p-8 space-y-6 md:space-y-8 max-w-[1480px] mx-auto w-full">

            <!-- TAB 1: PESANAN PO -->
            <div id="admin-tab-pelanggan" class="space-y-6">
                <div class="bg-white p-5 md:p-6 rounded-3xl border border-slate-200/80 shadow-sm flex flex-col md:flex-row md:items-center justify-between gap-4">
                    <div>
                        <h4 class="font-black text-slate-900 text-base">Kelola Pesanan Masuk</h4>
                        <p class="text-xs text-slate-500">Ubah status pembayaran, update real-time progress tracking, dan atur garansi.</p>
                    </div>
                    <div class="flex items-center gap-3 flex-wrap">
                        <span id="admin-customer-count" class="text-xs bg-emerald-100 text-emerald-700 font-extrabold px-3.5 py-2 rounded-full whitespace-nowrap w-fit">0 Pesanan</span>
                        <div class="flex gap-2 flex-wrap">
                            <button onclick="exportTab('pesanan')" title="Export hanya data Pesanan Masuk" class="tk-tool tk-tool-export"><i class="fa-solid fa-download"></i> Export</button>
                            <label title="Import data Pesanan Masuk" class="tk-tool tk-tool-import cursor-pointer"><i class="fa-solid fa-upload"></i> Import <input type="file" accept=".json" onchange="importTab(this,'pesanan')" class="hidden"></label>
                            <button onclick="clearInactiveChats()" class="tk-tool tk-tool-danger"><i class="fa-solid fa-broom"></i> Bersihkan Chat</button>
                        </div>
                    </div>
                </div>
                
                <div class="bg-white rounded-3xl border border-slate-200/80 overflow-hidden shadow-sm">
                    <div id="admin-customer-table" class="divide-y divide-slate-100 bg-white font-medium text-slate-700"></div>
                </div>
            </div>

            <!-- TAB LAPORAN TEKNISI & W.O. DENGAN 2 SUB TAB TERPISAH -->
            <div id="admin-tab-teknisi-reports" class="space-y-6 hidden">
                <div class="bg-white p-5 md:p-6 rounded-3xl border border-slate-200/80 shadow-sm flex flex-col md:flex-row md:items-center justify-between gap-4">
                    <div>
                        <h4 class="font-black text-slate-900 text-base flex items-center gap-2">
                            <i class="fa-solid fa-screwdriver-wrench text-sky-600"></i> Rekapitulasi Work Order (W.O.) Teknisi
                        </h4>
                        <p class="text-xs text-slate-500">Pemisahan data rekapitulasi kerja instalasi dan maintenance secara real-time.</p>
                    </div>
                    <div class="flex gap-2 flex-wrap items-center">
                            <button onclick="exportTab('teknisi')" title="Export hanya data Laporan Teknisi" class="tk-tool tk-tool-export"><i class="fa-solid fa-download"></i> Export</button>
                            <label title="Import data Laporan Teknisi" class="tk-tool tk-tool-import cursor-pointer"><i class="fa-solid fa-upload"></i> Import <input type="file" accept=".json" onchange="importTab(this,'teknisi')" class="hidden"></label>
                        <button onclick="loadAdminTechReports()" class="px-4 py-2 bg-sky-600 hover:bg-sky-500 text-white text-xs font-bold rounded-xl shadow-sm transition-all">
                            <i class="fa-solid fa-rotate mr-1"></i> Muat Ulang
                        </button>
                    </div>
                </div>

                <!-- SUB TAB HEADER -->
                <div class="flex bg-white p-2 rounded-2xl border border-slate-200 shadow-sm gap-2 w-fit">
                    <button onclick="switchTechSubTab('instalasi')" id="sub-tab-btn-instalasi" class="px-5 py-2.5 rounded-xl bg-sky-600 text-white text-xs font-black transition-all flex items-center gap-2">
                        <i class="fa-solid fa-house-chimney"></i> WO INSTALASI (BIRU)
                    </button>
                    <button onclick="switchTechSubTab('maintenance')" id="sub-tab-btn-maintenance" class="px-5 py-2.5 rounded-xl text-slate-600 hover:bg-slate-100 text-xs font-black transition-all flex items-center gap-2">
                        <i class="fa-solid fa-screwdriver-wrench text-rose-600"></i> WO MAINTENANCE (MERAH)
                    </button>
                </div>

                <!-- SUB TAB CONTENT: INSTALASI -->
                <div id="tech-sub-panel-instalasi" class="bg-white rounded-3xl border border-slate-200/80 overflow-hidden shadow-sm">
                    <div id="admin-tech-instalasi-table" class="divide-y divide-slate-100 bg-white font-medium text-slate-700 text-sm"></div>
                </div>

                <!-- SUB TAB CONTENT: MAINTENANCE -->
                <div id="tech-sub-panel-maintenance" class="bg-white rounded-3xl border border-slate-200/80 overflow-hidden shadow-sm hidden">
                    <div id="admin-tech-maintenance-table" class="divide-y divide-slate-100 bg-white font-medium text-slate-700 text-sm"></div>
                </div>
            </div>

            <!-- TAB TIKET LAPORAN KENDALA -->
            <div id="admin-tab-laporan" class="space-y-6 hidden">
                <div class="bg-white p-5 md:p-6 rounded-3xl border border-slate-200/80 shadow-sm flex flex-col md:flex-row md:items-center justify-between gap-4">
                    <div>
                        <h4 class="font-black text-slate-900 text-base flex items-center gap-2"><i class="fa-solid fa-headset text-rose-600"></i> Kelola Laporan Kendala Pelanggan</h4>
                        <p class="text-xs text-slate-500">Terima laporan masuk dan ubah status penanganan secara realtime.</p>
                    </div>
                    <div class="flex items-center gap-3 flex-wrap">
                        <span id="admin-ticket-total-count" class="text-xs bg-rose-100 text-rose-700 font-extrabold px-3.5 py-2 rounded-full whitespace-nowrap w-fit">0 Laporan</span>
                            <button onclick="exportTab('laporan')" title="Export hanya data Laporan Kendala" class="tk-tool tk-tool-export"><i class="fa-solid fa-download"></i> Export</button>
                            <label title="Import data Laporan Kendala" class="tk-tool tk-tool-import cursor-pointer"><i class="fa-solid fa-upload"></i> Import <input type="file" accept=".json" onchange="importTab(this,'laporan')" class="hidden"></label>
                    </div>
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    <div class="lg:col-span-1 bg-white rounded-3xl border border-slate-200/80 overflow-hidden shadow-sm flex flex-col h-[650px]">
                        <div class="p-4 bg-slate-900 text-white font-bold text-xs uppercase tracking-wider flex justify-between items-center">
                            <span>Daftar Tiket Masuk</span>
                            <span id="ticket-badge-sub" class="bg-rose-600 px-2 py-0.5 rounded-full text-[10px]">0 Aktif</span>
                        </div>
                        <div id="admin-ticket-list" class="flex-1 overflow-y-auto divide-y divide-slate-100"></div>
                    </div>

                    <div class="lg:col-span-2 bg-white rounded-3xl border border-slate-200/80 overflow-hidden shadow-sm flex flex-col h-[650px]">
                        <div id="ticket-chat-header" class="p-4 bg-slate-50 border-b border-slate-200 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                            <div>
                                <h5 class="font-black text-slate-900 text-sm" id="tc-title">Pilih tiket laporan</h5>
                                <p class="text-[11px] text-slate-500" id="tc-subtitle">Detail kendala akan muncul di sini</p>
                            </div>
                            <div class="flex items-center gap-2 flex-wrap">
                                <div id="tc-status-container" class="hidden flex items-center gap-2">
                                    <span class="text-[10px] font-extrabold text-slate-600 uppercase">Status:</span>
                                    <select id="tc-status-select" onchange="updateTicketStatusFromSelect(this.value)" class="text-xs px-3 py-2 bg-white border border-slate-300 rounded-xl font-bold text-slate-800 focus:outline-none focus:border-sky-500 shadow-sm">
                                        <option value="MENUNGGU">🟡 MENUNGGU</option>
                                        <option value="PROSES">🔵 PROSES/TEKNISI VISIT</option>
                                        <option value="TEKNISI VISIT">🟢 SELESAI</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div id="ticket-chat-messages" class="flex-1 p-4 md:p-5 overflow-y-auto space-y-3 bg-slate-50/50 text-xs md:text-sm">
                            <div class="text-center text-slate-400 my-auto h-full flex items-center justify-center font-medium">Belum ada tiket yang dipilih. Klik salah satu tiket di sebelah kiri.</div>
                        </div>

                        <div class="p-3 bg-white border-t border-slate-200 flex gap-2 items-center">
                            <input id="ticket-chat-input" type="text" placeholder="Tulis balasan teknisi untuk laporan ini..." class="flex-1 border border-slate-200 p-3 rounded-2xl text-xs md:text-sm focus:outline-none focus:border-sky-500 bg-slate-50 font-medium" onkeydown="if(event.key === 'Enter') sendTicketReply()" disabled>
                            <button id="ticket-send-btn" onclick="sendTicketReply()" class="bg-sky-600 hover:bg-sky-500 text-white px-5 py-3 rounded-2xl text-xs md:text-sm font-bold shadow-md transition-all disabled:opacity-50" disabled><i class="fa-solid fa-paper-plane mr-1"></i> Kirim</button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- TAB 2: KATALOG -->
            <div id="admin-tab-katalog" class="space-y-6 hidden">
                <div class="bg-white p-5 md:p-8 rounded-3xl border border-slate-200/80 shadow-sm">
                    <div class="flex items-center justify-between mb-6 flex-wrap gap-3">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 rounded-2xl bg-sky-50 text-sky-600 flex items-center justify-center shadow-sm"><i class="fa-solid fa-box-open"></i></div>
                            <h4 id="admin-form-title" class="text-base font-black text-slate-900">Tambah Produk Baru</h4>
                        </div>
                        <div class="flex gap-2 flex-wrap">
                            <button onclick="exportTab('katalog')" title="Export hanya data Katalog Produk" class="tk-tool tk-tool-export"><i class="fa-solid fa-download"></i> Export</button>
                            <label title="Import data Katalog Produk" class="tk-tool tk-tool-import cursor-pointer"><i class="fa-solid fa-upload"></i> Import <input type="file" accept=".json" onchange="importTab(this,'katalog')" class="hidden"></label>
                            <button onclick="clearInactiveChats()" class="tk-tool tk-tool-danger"><i class="fa-solid fa-broom"></i> Bersihkan Chat</button>
                        </div>
                    </div>
                    <input type="hidden" id="edit-product-id">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                        <div class="group">
                            <label class="block text-[12.5px] font-semibold text-slate-600 mb-1.5">Nama Paket</label>
                            <input type="text" id="admin-name" placeholder="Cth: Paket CCTV Hikvision" class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-2xl text-sm font-medium focus:outline-none focus:border-sky-500">
                        </div>
                        <div class="group">
                            <label for="admin-group" class="block text-[12.5px] font-semibold text-slate-600 mb-1.5">Grup / Merek</label>
                            <div class="relative">
                                <select id="admin-group" class="w-full appearance-none px-4 py-3 pr-10 bg-slate-50 border border-slate-200 rounded-2xl text-sm font-medium focus:outline-none focus:border-sky-500 cursor-pointer"></select>
                                <i class="fa-solid fa-chevron-down pointer-events-none absolute right-4 top-1/2 -translate-y-1/2 text-[11px] text-slate-400"></i>
                            </div>
                            <p class="text-[11px] text-slate-400 mt-1.5">Pilihan mengikuti grup produk di beranda tekunin.net.</p>
                        </div>
                        <div class="group">
                            <label class="block text-[12.5px] font-semibold text-slate-600 mb-1.5">Label / Badge</label>
                            <input type="text" id="admin-badge" placeholder="Cth: Terlaris" class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-2xl text-sm font-medium focus:outline-none focus:border-sky-500">
                        </div>
                        <div class="group">
                            <label class="block text-[12.5px] font-semibold text-slate-600 mb-1.5">Harga (Rp)</label>
                            <input type="number" id="admin-price" placeholder="Cth: 3200000" class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-2xl text-sm font-medium focus:outline-none focus:border-sky-500">
                        </div>
                        <div class="group md:col-span-2">
                            <label class="block text-[12.5px] font-semibold text-slate-600 mb-1.5">URL Gambar / Link Foto</label>
                            <input type="url" id="admin-image" placeholder="https://..." class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-2xl text-sm font-medium focus:outline-none focus:border-sky-500">
                        </div>
                        <div class="md:col-span-2 group">
                            <label class="block text-[12.5px] font-semibold text-slate-600 mb-1.5">Deskripsi Fitur</label>
                            <textarea id="admin-desc" rows="3" placeholder="Deskripsikan spesifikasi..." class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-2xl text-sm font-medium focus:outline-none focus:border-sky-500 resize-none"></textarea>
                        </div>
                    </div>
                    <div class="flex justify-end gap-3 mt-6 pt-5 border-t border-slate-100">
                        <button id="admin-cancel-edit" onclick="resetAdminForm()" class="hidden px-6 py-3 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-2xl text-xs font-bold">Batal</button>
                        <button onclick="saveAdminProduct()" class="px-7 py-3 bg-sky-600 hover:bg-sky-500 text-white rounded-2xl text-xs font-bold shadow-lg shadow-sky-600/30 flex items-center gap-2">
                            <i class="fa-solid fa-floppy-disk"></i> Simpan Data
                        </button>
                    </div>
                </div>

                <div class="bg-white rounded-3xl border border-slate-200/80 overflow-hidden shadow-sm">
                    <div class="px-5 py-4 border-b border-slate-100 flex justify-between items-center bg-slate-50/50">
                        <h4 class="font-bold text-slate-900 text-sm">Katalog Produk</h4>
                        <div class="flex items-center gap-2">
                            <label class="tk-tool tk-tool-import cursor-pointer"><i class="fa-solid fa-file-import"></i> Import Produk (JSON) <input type="file" accept=".json" onchange="importProductsJson(this)" class="hidden"></label>
                            <span id="admin-product-count" class="text-xs bg-sky-100 text-sky-700 font-extrabold px-3 py-1 rounded-full">0 Data</span>
                        </div>
                    </div>
                    <div id="admin-product-table" class="divide-y divide-slate-100 bg-white font-medium text-slate-700 text-sm"></div>
                </div>
            </div>

            <!-- TAB STOK BARANG -->
            <div id="admin-tab-stok" class="space-y-6 hidden">
                <div class="bg-white p-5 md:p-8 rounded-3xl border border-slate-200/80 shadow-sm">
                    <div class="flex items-center gap-3 mb-6">
                        <div class="w-10 h-10 rounded-2xl bg-sky-50 text-sky-600 flex items-center justify-center shadow-sm"><i class="fa-solid fa-boxes-stacked"></i></div>
                        <div>
                            <h4 class="text-base font-black text-slate-900">Form Stok Barang</h4>
                            <p class="text-xs text-slate-500">Catat barang masuk atau keluar. Stok dihitung otomatis per SN.</p>
                        </div>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
                        <div class="group">
                            <label class="block text-[12.5px] font-semibold text-slate-600 mb-1.5">SN</label>
                            <input type="text" id="stok-sn" maxlength="100" placeholder="Cth: SN-DVR-0001" class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-2xl text-sm font-medium focus:outline-none focus:border-sky-500">
                        </div>
                        <div class="group">
                            <label class="block text-[12.5px] font-semibold text-slate-600 mb-1.5">Grup</label>
                            <input type="text" id="stok-grup" maxlength="100" list="stok-grup-list" placeholder="Cth: DVR / Kamera / HDD" class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-2xl text-sm font-medium focus:outline-none focus:border-sky-500">
                            <datalist id="stok-grup-list"></datalist>
                        </div>
                        <div class="group">
                            <label class="block text-[12.5px] font-semibold text-slate-600 mb-1.5">Jumlah</label>
                            <input type="number" id="stok-jumlah" min="0" step="any" placeholder="Cth: 10" class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-2xl text-sm font-medium focus:outline-none focus:border-sky-500">
                        </div>
                        <div class="group">
                            <label class="block text-[12.5px] font-semibold text-slate-600 mb-1.5">Satuan</label>
                            <input type="text" id="stok-satuan" maxlength="30" list="stok-satuan-list" placeholder="Cth: Unit" class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-2xl text-sm font-medium focus:outline-none focus:border-sky-500">
                            <datalist id="stok-satuan-list"><option value="Unit"><option value="Pcs"><option value="Meter"><option value="Roll"><option value="Set"><option value="Box"></datalist>
                        </div>
                        <div class="group">
                            <label class="block text-[12.5px] font-semibold text-slate-600 mb-1.5">Brand</label>
                            <input type="text" id="stok-brand" maxlength="100" list="stok-brand-list" placeholder="Cth: Hikvision" class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-2xl text-sm font-medium focus:outline-none focus:border-sky-500">
                            <datalist id="stok-brand-list"></datalist>
                        </div>
                        <div class="group">
                            <label class="block text-[12.5px] font-semibold text-slate-600 mb-1.5">Jenis Transaksi</label>
                            <div class="relative">
                                <select id="stok-tipe" class="w-full appearance-none px-4 py-3 pr-10 bg-slate-50 border border-slate-200 rounded-2xl text-sm font-medium focus:outline-none focus:border-sky-500 cursor-pointer">
                                    <option value="MASUK">Barang Masuk (+)</option>
                                    <option value="KELUAR">Barang Keluar (-)</option>
                                </select>
                                <i class="fa-solid fa-chevron-down pointer-events-none absolute right-4 top-1/2 -translate-y-1/2 text-[11px] text-slate-400"></i>
                            </div>
                        </div>
                    </div>
                    <div class="flex justify-end gap-3 mt-6 pt-5 border-t border-slate-100">
                        <button type="button" onclick="resetStockForm()" class="px-6 py-3 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-2xl text-xs font-bold">Reset</button>
                        <button type="button" id="stok-save-btn" onclick="saveStock()" class="px-7 py-3 bg-sky-600 hover:bg-sky-500 text-white rounded-2xl text-xs font-bold shadow-lg shadow-sky-600/30 flex items-center gap-2">
                            <i class="fa-solid fa-floppy-disk"></i> Simpan Transaksi
                        </button>
                    </div>
                </div>

                <div class="bg-white rounded-3xl border border-slate-200/80 overflow-hidden shadow-sm">
                    <div class="px-5 py-4 border-b border-slate-100 flex justify-between items-center gap-3 flex-wrap bg-slate-50/50">
                        <h4 class="font-bold text-slate-900 text-sm">Riwayat Transaksi</h4>
                        <div class="flex items-center gap-2">
                            <select id="stok-hist-filter" onchange="renderStockHistory()" class="px-3 py-2 bg-white border border-slate-200 rounded-xl text-xs font-bold focus:outline-none focus:border-sky-500 cursor-pointer">
                                <option value="">Semua Transaksi</option>
                                <option value="INST">Instalasi (INST-)</option>
                                <option value="MT">Maintenance (MT-)</option>
                                <option value="MANUAL">Manual (Admin/Teknisi)</option>
                            </select>
                            <input type="text" id="stok-hist-search" oninput="renderStockHistory()" placeholder="Cari SN / grup / brand / kode WO..." class="px-3.5 py-2 bg-white border border-slate-200 rounded-xl text-xs font-medium focus:outline-none focus:border-sky-500 w-48 md:w-64">
                            <span id="stok-hist-count" class="text-xs bg-sky-100 text-sky-700 font-extrabold px-3 py-1 rounded-full whitespace-nowrap">0 Data</span>
                            <button type="button" id="stok-hist-clear-btn" onclick="clearStockHistory()" title="Hapus seluruh riwayat transaksi" class="px-3.5 py-2 bg-rose-50 hover:bg-rose-100 text-rose-600 rounded-xl text-xs font-bold flex items-center gap-1.5 whitespace-nowrap"><i class="fa-solid fa-trash-can"></i> Hapus Riwayat</button>
                        </div>
                    </div>
                    <div class="overflow-x-auto max-h-[420px] overflow-y-auto">
                        <table class="w-full text-sm text-left">
                            <thead class="bg-slate-50 text-[11px] uppercase tracking-wider text-slate-500 sticky top-0">
                                <tr>
                                    <th class="px-5 py-3 font-bold whitespace-nowrap">Waktu</th>
                                    <th class="px-5 py-3 font-bold">Jenis</th>
                                    <th class="px-5 py-3 font-bold">SN</th>
                                    <th class="px-5 py-3 font-bold">Grup</th>
                                    <th class="px-5 py-3 font-bold text-right">Jumlah</th>
                                    <th class="px-5 py-3 font-bold">Satuan</th>
                                    <th class="px-5 py-3 font-bold">Brand</th>
                                    <th class="px-5 py-3 font-bold">Oleh / Kode WO</th>
                                </tr>
                            </thead>
                            <tbody id="stok-hist-body" class="divide-y divide-slate-100 font-medium text-slate-700"></tbody>
                        </table>
                    </div>
                </div>

                <div class="bg-white rounded-3xl border border-slate-200/80 overflow-hidden shadow-sm">
                    <div class="px-5 py-4 border-b border-slate-100 flex justify-between items-center gap-3 flex-wrap bg-slate-50/50">
                        <h4 class="font-bold text-slate-900 text-sm">Stok Barang</h4>
                        <div class="flex items-center gap-2">
                            <input type="text" id="stok-search" oninput="renderStockTable()" placeholder="Cari SN / grup / brand..." class="px-3.5 py-2 bg-white border border-slate-200 rounded-xl text-xs font-medium focus:outline-none focus:border-sky-500 w-48 md:w-64">
                            <span id="stok-count" class="text-xs bg-emerald-100 text-emerald-700 font-extrabold px-3 py-1 rounded-full whitespace-nowrap">0 Data</span>
                            <button type="button" onclick="exportStock('xls')" title="Export stok ke Excel" class="px-3.5 py-2 bg-emerald-600 hover:bg-emerald-500 text-white rounded-xl text-xs font-bold flex items-center gap-1.5 whitespace-nowrap"><i class="fa-solid fa-file-excel"></i> Excel</button>
                            <button type="button" onclick="exportStock('csv')" title="Export stok ke CSV" class="px-3.5 py-2 bg-slate-700 hover:bg-slate-600 text-white rounded-xl text-xs font-bold flex items-center gap-1.5 whitespace-nowrap"><i class="fa-solid fa-file-csv"></i> CSV</button>
                            <button type="button" onclick="document.getElementById('stok-import-file').click()" title="Import stok dari CSV / JSON / SQL" class="px-3.5 py-2 bg-sky-600 hover:bg-sky-500 text-white rounded-xl text-xs font-bold flex items-center gap-1.5 whitespace-nowrap"><i class="fa-solid fa-file-import"></i> Import</button>
                            <input type="file" id="stok-import-file" accept=".csv,.json,.sql,.txt,text/csv,application/json" class="hidden" onchange="stockImportPick(this)">
                        </div>
                    </div>
                    <div class="overflow-x-auto max-h-[420px] overflow-y-auto">
                        <table class="w-full text-sm text-left">
                            <thead class="bg-slate-50 text-[11px] uppercase tracking-wider text-slate-500 sticky top-0">
                                <tr>
                                    <th class="px-5 py-3 font-bold">SN</th>
                                    <th class="px-5 py-3 font-bold">Grup</th>
                                    <th class="px-5 py-3 font-bold text-right">Jumlah</th>
                                    <th class="px-5 py-3 font-bold">Satuan</th>
                                    <th class="px-5 py-3 font-bold">Brand</th>
                                    <th class="px-5 py-3 font-bold text-right">Aksi</th>
                                </tr>
                            </thead>
                            <tbody id="stok-body" class="divide-y divide-slate-100 font-medium text-slate-700"></tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- TAB 3: LIVE CHAT -->
            <div id="admin-tab-live-chat" class="space-y-6 hidden">
                <div class="bg-white rounded-3xl border border-slate-200/80 overflow-hidden shadow-sm flex flex-col md:flex-row h-[70vh] md:h-[calc(100vh-10rem)] md:h-[calc(100dvh-10rem)] md:min-h-[480px]">
                    <div class="w-full md:w-1/3 bg-white border-b md:border-b-0 md:border-r border-slate-200 flex flex-col max-h-[30vh] md:max-h-full">
                        <div class="p-4 bg-slate-900 text-white font-bold flex justify-between items-center sticky top-0">
                            <span class="text-sm">Sesi Chat</span>
                            <div class="flex items-center gap-1.5">
                                <span class="text-[10px] bg-sky-600 px-2 py-0.5 rounded-full">Live AI</span>
                                <button onclick="exportTab('livechat')" title="Export data Live Chat" class="text-[10px] bg-emerald-600 text-white px-2 py-1 rounded-lg font-bold"><i class="fa-solid fa-download"></i></button>
                                <label title="Import data Live Chat" class="text-[10px] bg-amber-600 text-white px-2 py-1 rounded-lg font-bold cursor-pointer"><i class="fa-solid fa-upload"></i><input type="file" accept=".json" onchange="importTab(this,'livechat')" class="hidden"></label>
                            </div>
                        </div>
                        <div id="customer-chat-list" class="flex-1 overflow-y-auto divide-y divide-slate-100"></div>
                    </div>
                    <div class="w-full md:w-2/3 flex flex-col justify-between bg-white h-[40vh] md:h-full">
                        <div class="p-4 bg-slate-50 font-bold flex justify-between items-center border-b border-slate-200">
                            <div class="flex items-center gap-2 md:gap-3">
                                <span id="header-active-chat" class="text-xs md:text-sm text-slate-800 line-clamp-1">Pilih sesi pelanggan</span>
                                <button onclick="loadChatHistory(activeAdminChatCustomer)" title="Refresh Chat" class="text-[10px] md:text-xs bg-white border border-slate-200 px-2 py-1 rounded-lg text-slate-600 hover:bg-slate-50"><i class="fa-solid fa-rotate"></i></button>
                            </div>
                            <button onclick="returnToBotSession()" class="bg-emerald-600 hover:bg-emerald-500 text-white px-3 py-1.5 text-[10px] md:text-xs font-bold rounded-xl shadow-sm hidden transition-all whitespace-nowrap" id="btn-return-bot">Kembali ke Bot</button>
                        </div>
                        <div id="admin-chat-messages" class="flex-1 p-4 md:p-5 overflow-y-auto space-y-3 bg-slate-50/50 text-xs md:text-sm" style="white-space: pre-wrap;">
                            <div class="text-center text-slate-400 my-auto h-full flex items-center justify-center">Pilih salah satu sesi pelanggan.</div>
                        </div>
                        <div class="p-3 bg-white border-t border-slate-200 flex gap-2 items-center">
                            <input id="admin-chat-input" type="text" placeholder="Ketik balasan..." class="flex-1 border border-slate-200 p-3 rounded-2xl text-xs md:text-sm focus:outline-none focus:border-sky-500 bg-slate-50 font-medium" onkeydown="if(event.key === 'Enter') sendAdminChatMessage()">
                            <button onclick="sendAdminChatMessage()" class="bg-slate-900 hover:bg-slate-800 text-white px-5 py-3 rounded-2xl text-xs md:text-sm font-bold shadow-md transition-all"><i class="fa-solid fa-paper-plane md:hidden"></i><span class="hidden md:inline">Kirim</span></button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- TAB 5: AKSES MEMBER PELANGGAN -->
            <div id="admin-tab-akses-member" class="space-y-6 hidden">
                <div class="bg-white p-5 md:p-8 rounded-3xl border border-slate-200/80 shadow-sm">
                    <div class="flex items-center justify-between mb-6 flex-wrap gap-3">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 rounded-2xl bg-sky-50 text-sky-600 flex items-center justify-center shadow-sm"><i class="fa-solid fa-id-card text-lg"></i></div>
                            <div>
                                <h4 id="customer-form-title" class="text-base font-black text-slate-900">Kelola Akses Member Pelanggan</h4>
                                <p class="text-xs text-slate-500">Atur ID Pelanggan, Username, Password, serta Serial Number (DVR, CCTV, Harddisk).</p>
                            </div>
                        </div>
                        <div class="flex gap-2 flex-wrap">
                            <button onclick="exportTab('akses')" title="Export hanya data Akses Member" class="tk-tool tk-tool-export"><i class="fa-solid fa-download"></i> Export</button>
                            <label title="Import data Akses Member" class="tk-tool tk-tool-import cursor-pointer"><i class="fa-solid fa-upload"></i> Import <input type="file" accept=".json" onchange="importTab(this,'akses')" class="hidden"></label>
                        </div>
                    </div>
                    <input type="hidden" id="edit-customer-db-id">
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                        <div>
                            <label class="block text-[12.5px] font-semibold text-slate-600 mb-1.5">ID Pelanggan</label>
                            <input type="text" id="cust-acc-id" placeholder="Cth: CUST-1001" class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-2xl text-sm font-medium focus:outline-none focus:border-sky-500">
                            <p class="text-[10px] text-slate-400 mt-1">Saat mengedit, ID bisa diganti dan otomatis ikut berubah di user &amp; teknisi.</p>
                        </div>
                        <div>
                            <label class="block text-[12.5px] font-semibold text-slate-600 mb-1.5">Nama Lengkap</label>
                            <input type="text" id="cust-acc-name" placeholder="Cth: Budi Santoso" class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-2xl text-sm font-medium focus:outline-none focus:border-sky-500">
                        </div>
                        <div>
                            <label class="block text-[12.5px] font-semibold text-slate-600 mb-1.5">Nomor HP / WhatsApp</label>
                            <input type="text" id="cust-acc-phone" placeholder="Cth: 08123456789" class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-2xl text-sm font-medium focus:outline-none focus:border-sky-500">
                        </div>
                        <div>
                            <label class="block text-[12.5px] font-semibold text-slate-600 mb-1.5">Username Login</label>
                            <input type="text" id="cust-acc-username" placeholder="Cth: budi_cctv" class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-2xl text-sm font-medium focus:outline-none focus:border-sky-500">
                        </div>
                        <div>
                            <label class="block text-[12.5px] font-semibold text-slate-600 mb-1.5">Password</label>
                            <input type="text" id="cust-acc-password" placeholder="Password Pelanggan" class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-2xl text-sm font-medium focus:outline-none focus:border-sky-500">
                        </div>
                        <div>
                            <label class="block text-[12.5px] font-semibold text-slate-600 mb-1.5">Tanggal Aktif Pemasangan</label>
                            <input type="date" id="cust-acc-active-date" class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-2xl text-sm font-medium focus:outline-none focus:border-sky-500">
                        </div>
                        <div>
                            <label class="block text-[12.5px] font-semibold text-slate-600 mb-1.5">Serial Number (SN) DVR</label>
                            <input type="text" id="cust-acc-dvr" placeholder="Cth: DVR-983472" class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-2xl text-sm font-medium focus:outline-none focus:border-sky-500">
                        </div>
                        <div>
                            <label class="block text-[12.5px] font-semibold text-slate-600 mb-1.5">Serial Number (SN) CCTV</label>
                            <input type="text" id="cust-acc-cctv" placeholder="Cth: CAM-552140" class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-2xl text-sm font-medium focus:outline-none focus:border-sky-500">
                        </div>
                        <div>
                            <label class="block text-[12.5px] font-semibold text-slate-600 mb-1.5">Serial Number (SN) Harddisk</label>
                            <input type="text" id="cust-acc-hdd" placeholder="Cth: WDC-TB1-8832" class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-2xl text-sm font-medium focus:outline-none focus:border-sky-500">
                        </div>
                        <div>
                            <label class="block text-[12.5px] font-semibold text-slate-600 mb-1.5">Serial Number (SN) Perangkat Lain</label>
                            <input type="text" id="cust-acc-other" placeholder="Cth: PSU-12V-2231" class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-2xl text-sm font-medium focus:outline-none focus:border-sky-500">
                        </div>
                    </div>
                    <div class="flex justify-end gap-3 mt-6 pt-5 border-t border-slate-100">
                        <button id="cust-cancel-edit" onclick="resetCustomerForm()" class="hidden px-6 py-3 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-2xl text-xs font-bold">Batal</button>
                        <button onclick="saveCustomerAccount()" class="px-7 py-3 bg-sky-600 hover:bg-sky-500 text-white rounded-2xl text-xs font-bold shadow-lg shadow-sky-600/30 flex items-center gap-2">
                            <i class="fa-solid fa-floppy-disk"></i> Simpan Akses Pelanggan
                        </button>
                    </div>
                </div>

                <div class="bg-white rounded-3xl border border-slate-200/80 overflow-hidden shadow-sm">
                    <div class="px-5 py-4 border-b border-slate-100 flex justify-between items-center bg-slate-50/50">
                        <h4 class="font-bold text-slate-900 text-sm">Daftar Akun Member Pelanggan</h4>
                        <span id="admin-customer-acc-count" class="text-xs bg-sky-100 text-sky-700 font-extrabold px-3 py-1 rounded-full">0 Akun</span>
                    </div>
                    <div id="admin-customer-acc-table" class="divide-y divide-slate-100 bg-white font-medium text-slate-700 text-sm"></div>
                </div>

            <!-- SUB: DATABASE PELANGGAN (digabung ke Akses Member) -->
            <div id="admin-section-data-pelanggan" class="space-y-6 mt-2 scroll-mt-4">
                <div class="bg-white p-5 md:p-6 rounded-3xl border border-slate-200/80 shadow-sm flex flex-col md:flex-row md:items-center justify-between gap-4">
                    <div>
                        <h4 class="font-black text-slate-900 text-base">Database Rekapitulasi Pelanggan</h4>
                        <p class="text-xs text-slate-500">Pelanggan otomatis terdata dari pesanan.</p>
                    </div>
                    <div class="flex items-center gap-3 flex-wrap">
                        <span id="data-pelanggan-count" class="text-xs bg-sky-100 text-sky-700 font-extrabold px-3.5 py-2 rounded-full w-fit">0 Pelanggan</span>
                        <div class="flex gap-2 flex-wrap">
                            <button onclick="exportTab('pelanggan')" title="Export hanya data Database Pelanggan" class="tk-tool tk-tool-export"><i class="fa-solid fa-download"></i> Export</button>
                            <button onclick="clearInactiveChats()" class="tk-tool tk-tool-danger"><i class="fa-solid fa-broom"></i> Bersihkan Chat</button>
                        </div>
                    </div>
                </div>
                <div class="bg-white rounded-3xl border border-slate-200/80 overflow-hidden shadow-sm">
                    <div id="admin-data-pelanggan-table" class="divide-y divide-slate-100 bg-white font-medium text-slate-700 text-sm"></div>
                </div>
            </div>
            </div>

            <!-- TAB 6: TAMBAH ANGGOTA (STAFF ADMIN) -->
            <div id="admin-tab-tambah-anggota" class="space-y-6 hidden">
                <div class="bg-white p-5 md:p-8 rounded-3xl border border-slate-200/80 shadow-sm">
                    <div class="flex items-center justify-between mb-6 flex-wrap gap-3">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 rounded-2xl bg-indigo-50 text-indigo-600 flex items-center justify-center shadow-sm"><i class="fa-solid fa-user-plus text-lg"></i></div>
                            <div>
                                <h4 class="text-base font-black text-slate-900">Akun Anggota Baru</h4>
                                <p class="text-xs text-slate-500">Tentukan izin akses per grup tabel.</p>
                            </div>
                        </div>
                        <div class="flex gap-2 flex-wrap">
                            <button onclick="exportTab('anggota')" title="Export hanya data Akun Anggota" class="tk-tool tk-tool-export"><i class="fa-solid fa-download"></i> Export</button>
                            <label title="Import data Akun Anggota" class="tk-tool tk-tool-import cursor-pointer"><i class="fa-solid fa-upload"></i> Import <input type="file" accept=".json" onchange="importTab(this,'anggota')" class="hidden"></label>
                            <button onclick="clearInactiveChats()" class="tk-tool tk-tool-danger"><i class="fa-solid fa-broom"></i> Bersihkan Chat</button>
                        </div>
                    </div>
                    <div class="space-y-5">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-[12.5px] font-semibold text-slate-600 mb-1.5">Username</label>
                                <input type="text" id="new-member-username" placeholder="Cth: staff_cctv" class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-2xl text-sm font-medium focus:outline-none focus:border-indigo-500">
                            </div>
                            <div>
                                <label class="block text-[12.5px] font-semibold text-slate-600 mb-1.5">Password</label>
                                <input type="password" id="new-member-password" placeholder="Password" class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-2xl text-sm font-medium focus:outline-none focus:border-indigo-500">
                            </div>
                        </div>
                        <div class="bg-slate-50 p-4 md:p-5 rounded-2xl border border-slate-200">
                            <h5 class="text-xs font-black uppercase text-slate-900 mb-4 tracking-wider"><i class="fa-solid fa-shield-halved text-indigo-600 mr-2"></i> Hak Akses</h5>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                <div class="bg-white p-3 rounded-xl border flex justify-between items-center">
                                    <span class="font-bold text-xs text-slate-700">Katalog Produk</span>
                                    <div class="flex gap-3 text-[10px] font-bold">
                                        <label><input type="checkbox" id="perm-prod-read" checked class="accent-indigo-600"> Read</label>
                                        <label><input type="checkbox" id="perm-prod-write" checked class="accent-indigo-600"> Write</label>
                                    </div>
                                </div>
                                <div class="bg-white p-3 rounded-xl border flex justify-between items-center">
                                    <span class="font-bold text-xs text-slate-700">Live Chat</span>
                                    <div class="flex gap-3 text-[10px] font-bold">
                                        <label><input type="checkbox" id="perm-chat-read" checked class="accent-indigo-600"> Read</label>
                                        <label><input type="checkbox" id="perm-chat-write" checked class="accent-indigo-600"> Write</label>
                                    </div>
                                </div>
                                <div class="bg-white p-3 rounded-xl border flex justify-between items-center">
                                    <span class="font-bold text-xs text-slate-700">Data Pelanggan</span>
                                    <div class="flex gap-3 text-[10px] font-bold">
                                        <label><input type="checkbox" id="perm-cust-read" checked class="accent-indigo-600"> Read</label>
                                        <label><input type="checkbox" id="perm-cust-write" checked class="accent-indigo-600"> Write</label>
                                    </div>
                                </div>
                                <div class="bg-white p-3 rounded-xl border flex justify-between items-center">
                                    <span class="font-bold text-xs text-slate-700">Pesanan PO</span>
                                    <div class="flex gap-3 text-[10px] font-bold">
                                        <label><input type="checkbox" id="perm-order-read" checked class="accent-indigo-600"> Read</label>
                                        <label><input type="checkbox" id="perm-order-write" checked class="accent-indigo-600"> Write</label>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="flex justify-end pt-2">
                            <button onclick="addNewMemberAccount()" class="w-full md:w-auto px-7 py-3 bg-indigo-600 hover:bg-indigo-500 text-white rounded-2xl text-xs font-bold shadow-lg shadow-indigo-600/30 flex justify-center items-center gap-2 transition-all">
                                <i class="fa-solid fa-user-check"></i> Simpan Akun
                            </button>
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-3xl border border-slate-200/80 overflow-hidden shadow-sm">
                    <div class="px-5 py-4 border-b border-slate-100 flex justify-between items-center bg-slate-50/50">
                        <h4 class="font-bold text-slate-900 text-sm">Daftar Akun Staff</h4>
                        <span id="admin-member-count" class="text-xs bg-indigo-100 text-indigo-700 font-extrabold px-3 py-1 rounded-full">0 Anggota</span>
                    </div>
                    <div id="admin-member-table" class="divide-y divide-slate-100 bg-white font-medium text-slate-700 text-sm"></div>
                </div>

                <!-- SUB: AKSES TEKNISI -->
                <div id="admin-section-akses-teknisi" class="bg-white p-5 md:p-8 rounded-3xl border border-slate-200/80 shadow-sm mt-2 scroll-mt-4">
                    <div class="flex items-center gap-3 mb-6">
                        <div class="w-10 h-10 rounded-2xl bg-rose-50 text-rose-600 flex items-center justify-center shadow-sm"><i class="fa-solid fa-screwdriver-wrench text-lg"></i></div>
                        <div>
                            <h4 class="text-base font-black text-slate-900">Akses Teknisi</h4>
                            <p class="text-xs text-slate-500">Hanya akun di bawah ini yang boleh login ke <b>teknisi.php</b>. Tanpa data = notif "Data teknisi tidak ditemukan".</p>
                        </div>
                    </div>
                    <input type="hidden" id="edit-tech-acc-id">
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                        <div>
                            <label class="block text-[12.5px] font-semibold text-slate-600 mb-1.5">Nama Teknisi</label>
                            <input type="text" id="tech-acc-name" placeholder="Cth: Budi Teknisi" class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-2xl text-sm font-medium focus:outline-none focus:border-rose-500">
                        </div>
                        <div>
                            <label class="block text-[12.5px] font-semibold text-slate-600 mb-1.5">Username Login</label>
                            <input type="text" id="tech-acc-username" placeholder="Cth: budi_tek" class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-2xl text-sm font-medium focus:outline-none focus:border-rose-500">
                        </div>
                        <div>
                            <label class="block text-[12.5px] font-semibold text-slate-600 mb-1.5">Password</label>
                            <input type="text" id="tech-acc-password" placeholder="Password" class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-2xl text-sm font-medium focus:outline-none focus:border-rose-500">
                        </div>
                        <div>
                            <label class="block text-[12.5px] font-semibold text-slate-600 mb-1.5">No. HP</label>
                            <input type="text" id="tech-acc-phone" placeholder="08..." class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-2xl text-sm font-medium focus:outline-none focus:border-rose-500">
                        </div>
                    </div>
                    <div class="flex justify-end gap-3 mt-5 pt-4 border-t border-slate-100">
                        <button id="tech-cancel-edit" onclick="resetTechAccForm()" class="hidden px-6 py-3 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-2xl text-xs font-bold">Batal</button>
                        <button onclick="saveTechnicianAccount()" class="px-7 py-3 bg-rose-600 hover:bg-rose-500 text-white rounded-2xl text-xs font-bold shadow-lg shadow-rose-600/30 flex items-center gap-2">
                            <i class="fa-solid fa-floppy-disk"></i> Simpan Akses Teknisi
                        </button>
                    </div>
                </div>
                <div class="bg-white rounded-3xl border border-slate-200/80 overflow-hidden shadow-sm">
                    <div class="px-5 py-4 border-b border-slate-100 flex justify-between items-center bg-slate-50/50">
                        <h4 class="font-bold text-slate-900 text-sm">Daftar Akun Teknisi</h4>
                        <span id="admin-tech-acc-count" class="text-xs bg-rose-100 text-rose-700 font-extrabold px-3 py-1 rounded-full">0 Akun</span>
                    </div>
                    <div id="admin-tech-acc-table" class="divide-y divide-slate-100 bg-white font-medium text-slate-700 text-sm"></div>
                </div>
            </div>

            </div>
            </div>
        </div>
    </div>

    <!-- Modal Preview Gambar Bukti Pembayaran -->
    <div id="image-modal" class="fixed inset-0 z-[110] hidden items-center justify-center p-4 bg-slate-950/80 backdrop-blur-md" onclick="closeImageModal()">
        <div class="relative bg-white p-3 rounded-3xl max-w-lg w-full shadow-2xl animate-pop-in" onclick="event.stopPropagation()">
            <div class="flex justify-between items-center mb-3 px-2">
                <h4 class="font-bold text-slate-900 text-xs uppercase"><i class="fa-solid fa-receipt text-sky-600 mr-2"></i> Bukti Pembayaran / Foto</h4>
                <button onclick="closeImageModal()" class="text-slate-400 hover:text-rose-600 w-8 h-8 rounded-full flex items-center justify-center bg-slate-100"><i class="fa-solid fa-xmark"></i></button>
            </div>
            <img id="modal-preview-img" src="" alt="Bukti" class="w-full max-h-[75vh] object-contain rounded-2xl bg-slate-100 border border-slate-200">
        </div>
    </div>

    <!-- Modal Import Stok Barang -->
    <div id="stok-import-modal" class="fixed inset-0 z-[110] hidden items-center justify-center p-4 bg-slate-950/80 backdrop-blur-md" onclick="stockImportClose()">
        <div class="relative bg-white p-5 rounded-3xl max-w-lg w-full shadow-2xl animate-pop-in" onclick="event.stopPropagation()">
            <div class="flex justify-between items-center mb-3">
                <h4 class="font-bold text-slate-900 text-xs uppercase"><i class="fa-solid fa-file-import text-sky-600 mr-2"></i> Import Stok Barang</h4>
                <button type="button" onclick="stockImportClose()" class="text-slate-400 hover:text-rose-600 w-8 h-8 rounded-full flex items-center justify-center bg-slate-100"><i class="fa-solid fa-xmark"></i></button>
            </div>
            <p id="stok-import-info" class="text-xs text-slate-600 mb-3"></p>
            <div class="overflow-x-auto max-h-48 overflow-y-auto border border-slate-200 rounded-2xl mb-3">
                <table class="w-full text-xs text-left">
                    <thead class="bg-slate-50 text-[10px] uppercase text-slate-500 sticky top-0"><tr><th class="px-3 py-2">SN</th><th class="px-3 py-2">Grup</th><th class="px-3 py-2 text-right">Jumlah</th><th class="px-3 py-2">Satuan</th><th class="px-3 py-2">Brand</th></tr></thead>
                    <tbody id="stok-import-preview" class="divide-y divide-slate-100"></tbody>
                </table>
            </div>
            <div class="space-y-2 mb-4 text-xs">
                <label class="flex items-start gap-2 p-3 border border-slate-200 rounded-2xl cursor-pointer"><input type="radio" name="stok-import-mode" value="add" checked class="mt-0.5"><span><b>Tambahkan ke stok</b><br><span class="text-slate-500">Jumlah di file ditambah ke stok yang sudah ada (dicatat sebagai barang MASUK).</span></span></label>
                <label class="flex items-start gap-2 p-3 border border-slate-200 rounded-2xl cursor-pointer"><input type="radio" name="stok-import-mode" value="replace" class="mt-0.5"><span><b>Samakan dengan file (timpa)</b><br><span class="text-slate-500">Stok SN yang ada diubah menjadi jumlah di file; selisihnya dicatat MASUK/KELUAR. SN yang tidak ada di file tidak diubah.</span></span></label>
            </div>
            <div class="flex justify-end gap-2">
                <button type="button" onclick="stockImportClose()" class="px-5 py-2.5 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-2xl text-xs font-bold">Batal</button>
                <button type="button" id="stok-import-go" onclick="stockImportRun()" class="px-6 py-2.5 bg-sky-600 hover:bg-sky-500 text-white rounded-2xl text-xs font-bold flex items-center gap-2"><i class="fa-solid fa-check"></i> Import Sekarang</button>
            </div>
        </div>
    </div>

    <div id="toast-container" class="fixed bottom-6 right-6 md:bottom-8 md:right-8 z-[100] flex flex-col gap-3 pointer-events-none w-[calc(100%-48px)] md:w-auto"></div>

    <script>
        let products = []; let orders = []; let sessions = []; let members = []; let customerAccounts = []; let tickets = []; let technicianOrders = []; let technicianAccounts = [];
        let activeAdminChatCustomer = null;
        let activeTicketId = null;
        let loggedInAdminName = "Admin";
        let lastKnownTicketCount = 0;
        let knownPendingPayIds = null;
        let knownMemberOrderIds = null;

        if(sessionStorage.getItem('tekunin_admin_logged') === 'true') {
            loggedInAdminName = sessionStorage.getItem('tekunin_admin_user') || "Admin";
            document.getElementById('login-section').classList.add('hidden');
            document.getElementById('admin-section').classList.remove('hidden');
            document.getElementById('admin-section').classList.add('flex');
            loadData();
            setInterval(loadData, 5000);
        }

        function handleLogin(e) {
            e.preventDefault();
            const u = document.getElementById('login-user').value.trim();
            const p = document.getElementById('login-pass').value.trim();
            if ((u === 'admin' && p === 'P4$$w0rd')) {
                loggedInAdminName = u;
                sessionStorage.setItem('tekunin_admin_logged', 'true');
                sessionStorage.setItem('tekunin_admin_user', u);
                document.getElementById('login-section').classList.add('hidden');
                document.getElementById('admin-section').classList.remove('hidden');
                document.getElementById('admin-section').classList.add('flex');
                loadData();
                setInterval(loadData, 5000);
                showToast('Login Berhasil');
            } else {
                document.getElementById('login-error').classList.remove('hidden');
            }
        }

        function logoutAdmin() {
            sessionStorage.removeItem('tekunin_admin_logged');
            sessionStorage.removeItem('tekunin_admin_user');
            document.getElementById('admin-section').classList.add('hidden');
            document.getElementById('admin-section').classList.remove('flex');
            document.getElementById('login-section').classList.remove('hidden');
        }

        const TK_TAB_META = {
            'pelanggan':       { group: 'Operasional', title: 'Pesanan Masuk' },
            'teknisi-reports': { group: 'Operasional', title: 'Laporan Teknisi & W.O.' },
            'laporan':         { group: 'Operasional', title: 'Laporan Kendala' },
            'live-chat':       { group: 'Operasional', title: 'Live Chat CS' },
            'katalog':         { group: 'Data', title: 'Katalog Produk' },
            'stok':            { group: 'Data', title: 'Stok Barang' },
            'akses-member':    { group: 'Data', title: 'Akses Member' },
            'tambah-anggota':  { group: 'Pengaturan', title: 'Akun Anggota' }
        };

        function switchAdminTab(tab) {
            ['katalog', 'live-chat', 'pelanggan', 'akses-member', 'tambah-anggota', 'laporan', 'teknisi-reports', 'stok'].forEach(t => {
                let el = document.getElementById('admin-tab-'+t);
                let btn = document.getElementById('tab-btn-'+t);
                let grp = document.getElementById('navgrp-'+t);
                if(el) el.classList.add('hidden');
                if(btn) { btn.classList.remove('tk-active'); btn.removeAttribute('aria-current'); }
                if(grp) grp.classList.remove('tk-open');
            });
            let targetEl = document.getElementById('admin-tab-'+tab);
            let targetBtn = document.getElementById('tab-btn-'+tab);
            let targetGrp = document.getElementById('navgrp-'+tab);
            if(targetEl) targetEl.classList.remove('hidden');
            if(targetBtn) { targetBtn.classList.add('tk-active'); targetBtn.setAttribute('aria-current', 'page'); }
            if(targetGrp) targetGrp.classList.add('tk-open');

            const meta = TK_TAB_META[tab];
            if(meta) {
                const cg = document.getElementById('tk-crumb-group');
                const ct = document.getElementById('tk-crumb-title');
                if(cg) cg.textContent = meta.group;
                if(ct) ct.textContent = meta.title;
            }
            const sc = document.getElementById('admin-scroll');
            if(sc) sc.scrollTop = 0;
            const adm = document.getElementById('admin-section');
            if(adm) adm.classList.remove('tk-drawer');

            if(tab === 'live-chat') loadChatList();
            if(tab === 'laporan') renderTicketsList();
            if(tab === 'teknisi-reports') loadAdminTechReports();
            if(tab === 'stok') loadStock();
        }

        function switchTechSubTab(subTab) {
            let btnInstalasi = document.getElementById('sub-tab-btn-instalasi');
            let btnMaintenance = document.getElementById('sub-tab-btn-maintenance');
            let panelInstalasi = document.getElementById('tech-sub-panel-instalasi');
            let panelMaintenance = document.getElementById('tech-sub-panel-maintenance');

            if (subTab === 'instalasi') {
                btnInstalasi.className = "px-5 py-2.5 rounded-xl bg-sky-600 text-white text-xs font-black transition-all flex items-center gap-2";
                btnMaintenance.className = "px-5 py-2.5 rounded-xl text-slate-600 hover:bg-slate-100 text-xs font-black transition-all flex items-center gap-2";
                panelInstalasi.classList.remove('hidden');
                panelMaintenance.classList.add('hidden');
            } else {
                btnMaintenance.className = "px-5 py-2.5 rounded-xl bg-rose-600 text-white text-xs font-black transition-all flex items-center gap-2";
                btnInstalasi.className = "px-5 py-2.5 rounded-xl text-slate-600 hover:bg-slate-100 text-xs font-black transition-all flex items-center gap-2";
                panelMaintenance.classList.remove('hidden');
                panelInstalasi.classList.add('hidden');
            }
        }

        async function loadAdminTechReports() {
            try {
                let res = await fetch('admin.php?api=get_technician_orders');
                let json = await res.json();
                let instContainer = document.getElementById('admin-tech-instalasi-table');
                let maintContainer = document.getElementById('admin-tech-maintenance-table');
                if(!instContainer || !maintContainer) return;

                if(json.success && json.data.length > 0) {
                    let maintenanceList = json.data.filter(to => {
                        let type = (to.wo_type || '').toUpperCase();
                        return type.includes('MAINTENANCE') || (to.id && to.id.toString().startsWith('TICK-') || to.id && to.id.toString().startsWith('TC-'));
                    });
                    
                    let instalasiList = json.data.filter(to => {
                        let type = (to.wo_type || '').toUpperCase();
                        let idStr = (to.id || '').toUpperCase();
                        return type === 'INSTALASI' && !idStr.startsWith('TICK') && !idStr.startsWith('TC-');
                    });

                    if(instalasiList.length > 0) {
                        instContainer.innerHTML = instalasiList.map(to => renderTechReportCard(to, false)).join('');
                    } else {
                        instContainer.innerHTML = '<div class="p-8 text-center text-slate-400 font-bold">Belum ada Work Order Instalasi.</div>';
                    }

                    if(maintenanceList.length > 0) {
                        maintContainer.innerHTML = maintenanceList.map(to => renderTechReportCard(to, true)).join('');
                    } else {
                        maintContainer.innerHTML = '<div class="p-8 text-center text-slate-400 font-bold">Belum ada Work Order Maintenance / Tiket Kendala.</div>';
                    }
                } else {
                    instContainer.innerHTML = '<div class="p-8 text-center text-slate-400 font-bold">Belum ada Work Order Instalasi.</div>';
                    maintContainer.innerHTML = '<div class="p-8 text-center text-slate-400 font-bold">Belum ada Work Order Maintenance / Tiket Kendala.</div>';
                }
            } catch(e) {
                console.error("Gagal memuat laporan teknisi", e);
            }
        }

        // ===== HELPER FOTO & SN TEKNISI (mendukung 1 atau banyak SN + foto) =====
        function techEsc(t) {
            return String(t == null ? '' : t).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
        }

        function techPickSrc(x) {
            if (!x) return '';
            if (typeof x === 'string') return x.trim();
            if (typeof x === 'object') return String(x.photo || x.image || x.foto || x.url || x.src || x.data || x.proof || '').trim();
            return '';
        }

        // Mengubah nilai foto (string tunggal / JSON array / JSON object / dipisah | atau baris baru / banyak data-URI) menjadi array src
        function parseTechPhotos(val) {
            if (!val) return [];
            // Array JSON dari teknisi: posisi kosong dipertahankan agar urutan foto tetap sejajar dengan urutan SN
            if (Array.isArray(val)) return val.map(techPickSrc);
            if (typeof val === 'object') return Object.values(val).map(techPickSrc);
            let str = String(val).trim();
            if (!str) return [];
            if (str[0] === '[' || str[0] === '{') {
                try {
                    let j = JSON.parse(str);
                    if (Array.isArray(j)) return j.map(techPickSrc);
                    if (j && typeof j === 'object') return Object.values(j).map(techPickSrc);
                } catch (e) { /* bukan JSON, lanjut */ }
            }
            let dataUris = str.match(/data:image\/[a-zA-Z0-9+.\-]+;base64,[A-Za-z0-9+\/=]+/g);
            if (dataUris && dataUris.length) return dataUris;
            return str.split(/\s*[\n|]\s*|,\s*(?=https?:\/\/|\/|\.\/|uploads\/)/).map(x => x.trim()).filter(Boolean);
        }

        // Mengubah nilai SN (teks dipisah koma / baris baru, atau JSON array) menjadi array {sn, photo}
        function parseTechSnList(val) {
            if (val === null || val === undefined || String(val).trim() === '') return [];
            let str = String(val).trim();
            if (str[0] === '[') {
                try {
                    let arr = JSON.parse(str);
                    if (Array.isArray(arr)) {
                        return arr.map(it => (typeof it === 'object' && it !== null)
                            ? { sn: String(it.sn || it.serial || it.nama || it.id || '-'), photo: techPickSrc({ photo: it.photo, image: it.image, foto: it.foto, url: it.url }) }
                            : { sn: String(it), photo: '' });
                    }
                } catch (e) { /* bukan JSON, lanjut */ }
            }
            return str.split(/[,\n;|]+/).map(x => x.trim()).filter(Boolean).map(x => ({ sn: x, photo: '' }));
        }

        // Catatan/deskripsi per unit dari teknisi (notes_json): {dvr:[{sn,desc,p}],cctv:[],hdd:[],other:[],kabel:''}
        function parseTechNotes(raw) {
            let base = { dvr: [], cctv: [], hdd: [], other: [], kabel: '' };
            if (!raw) return base;
            try {
                let j = (typeof raw === 'string') ? JSON.parse(raw) : raw;
                if (j && typeof j === 'object') {
                    ['dvr', 'cctv', 'hdd', 'other'].forEach(k => {
                        if (Array.isArray(j[k])) base[k] = j[k].filter(u => u && typeof u === 'object');
                    });
                    base.kabel = String(j.kabel || '');
                }
            } catch (e) { /* abaikan */ }
            return base;
        }

        // Render satu kolom: kotak SN + foto + deskripsi di bawahnya
        function renderSnPhotoColumn(snRaw, photoRaw, labelPrefix, fallbackPhoto, units) {
            let unitList = Array.isArray(units) ? units : [];
            // Jika ada data unit (format baru) pakai itu agar SN, foto & deskripsi selalu sejajar
            let sns = unitList.length ? unitList.map(u => ({ sn: (String(u.sn || '') === '-' ? '' : String(u.sn || '')), photo: '' })) : parseTechSnList(snRaw);
            let descs = unitList.map(u => String(u.desc || ''));
            let photos = parseTechPhotos(photoRaw);
            if (!photos.some(Boolean) && fallbackPhoto) photos = parseTechPhotos(fallbackPhoto);

            let thumb = (src, label) => `
                <div class="mt-2">
                    <span class="text-[9px] font-bold text-sky-600 block mb-1 uppercase">${techEsc(label)}</span>
                    <img src="${techEsc(src)}" onclick="openImageModal(this.src)" class="w-20 h-20 object-cover rounded-xl border border-slate-300 cursor-pointer shadow-sm hover:opacity-90">
                </div>`;

            let html = '';
            let total = Math.max(sns.length, photos.length);
            if (total === 0 || (sns.length === 0 && !photos.some(Boolean))) {
                return `<span class="font-mono text-sky-600 block bg-white p-1.5 rounded border border-slate-100 mt-0.5">-</span>`;
            }
            for (let i = 0; i < total; i++) {
                let item = sns[i];
                let snText = item ? item.sn : '';
                let desc = (descs[i] || '').trim();
                let src = (item && item.photo) ? item.photo : (photos[i] || '');
                // Jika hanya ada 1 foto untuk banyak SN, foto hanya dipasangkan ke SN pertama
                let label = snText ? `FOTO SN ${snText}` : `FOTO ${labelPrefix} ${i + 1}`;
                if (sns.length <= 1 && photos.length <= 1) label = `FOTO SN ${labelPrefix}`;
                html += `
                    <div class="${total > 1 ? 'mb-3 border-b border-slate-100/50 pb-2 last:border-0 last:pb-0' : ''}">
                        ${snText ? `<span class="font-mono text-sky-600 block bg-white p-1.5 rounded border border-slate-100 mt-0.5">${techEsc(snText)}</span>` : ''}
                        ${src ? thumb(src, label) : ''}
                        ${desc ? `<div class="mt-1.5 text-[10px] leading-relaxed text-slate-600 bg-amber-50 border border-amber-100 rounded-lg px-2 py-1.5 whitespace-pre-line"><b class="text-amber-700">Deskripsi:</b> ${techEsc(desc)}</div>` : ''}
                    </div>`;
            }
            return html;
        }

        function renderTechReportCard(to, isMaint) {
            let badgeTypeClass = isMaint ? 'bg-rose-100 text-rose-800 border border-rose-200' : 'bg-sky-100 text-sky-800 border border-sky-200';
            let badgeIcon = isMaint ? 'fa-screwdriver-wrench' : 'fa-house-chimney';
            let typeLabel = isMaint ? 'W.O. MAINTENANCE (TIKET KENDALA)' : 'W.O. INSTALASI';
            let st = (to.status || '').toUpperCase();

            // Badge status TERPISAH — hanya 2 status jelas untuk INSTALASI
            let statusBadge = '';
            if (!isMaint) {
                if (st === 'MENUNGGU KONFIRMASI') {
                    statusBadge = `<span class="text-[10px] font-extrabold px-2.5 py-0.5 rounded-full bg-amber-100 text-amber-700 border border-amber-200"><i class="fa-solid fa-hourglass-half mr-1"></i>MENUNGGU APPROVE</span>`;
                } else if (st === 'DATA APPROVE') {
                    statusBadge = `<span class="text-[10px] font-extrabold px-2.5 py-0.5 rounded-full bg-emerald-100 text-emerald-700 border border-emerald-200"><i class="fa-solid fa-circle-check mr-1"></i>DATA APPROVE</span>`;
                }
                // status lain (SELESAI, dll) tidak ditampilkan sebagai DATA APPROVE
            }

            // Tombol APPROVE terpisah — hanya muncul jika belum di-approve
            let approveBtn = '';
            if (!isMaint && st === 'MENUNGGU KONFIRMASI') {
                approveBtn = `
                    <button onclick="approveTechnicianOrder('${to.id}')" class="px-4 py-2.5 bg-emerald-600 hover:bg-emerald-500 text-white rounded-xl text-xs font-bold transition-all flex items-center gap-1.5 shadow-md shadow-emerald-600/20" title="Approve data teknisi">
                        <i class="fa-solid fa-check-double"></i> APPROVE DATA
                    </button>`;
            }

            let notes = parseTechNotes(to.notes_json);
            let dvrHtml = renderSnPhotoColumn(to.dvr_sn, to.proof_image_dvr, 'DVR/NVR', isMaint ? '' : to.proof_image, notes.dvr);
            let cctvHtml = renderSnPhotoColumn(to.cctv_sn, to.proof_image_cctv, 'CCTV/CAM', '', notes.cctv);
            let hddHtml = renderSnPhotoColumn(to.hdd_sn, to.proof_image_hdd, 'HDD', '', notes.hdd);
            let otherHtml = renderSnPhotoColumn(to.other_sn, to.proof_image_other, 'Perangkat Lain', '', notes.other);

            return `
                <div class="p-5 border-b border-slate-100 flex flex-col md:flex-row justify-between items-start gap-4 hover:bg-slate-50 transition-colors">
                    <div class="flex-1 w-full">
                        <div class="flex items-center gap-2 mb-1.5 flex-wrap">
                            <span class="text-xs font-black bg-slate-900 text-white px-2.5 py-0.5 rounded-lg">${to.id}</span>
                            <span class="text-[10px] font-extrabold px-2.5 py-0.5 rounded-full flex items-center gap-1 ${badgeTypeClass}"><i class="fa-solid ${badgeIcon}"></i> ${typeLabel}</span>
                            ${statusBadge}
                            <span class="font-black text-slate-900 text-base">${to.nama}</span>
                            <span class="text-[10px] bg-sky-100 text-sky-700 font-bold px-2 py-0.5 rounded-full">ID: ${techEsc(to.customer_id)}</span>
                            <button type="button" data-cid="${techEsc(to.customer_id)}" onclick="editCustomerIdPrompt(this.dataset.cid)" class="text-[10px] bg-amber-100 hover:bg-amber-200 text-amber-800 px-2 py-0.5 rounded-full font-bold flex items-center gap-1 transition-all" title="Ubah ID Pelanggan"><i class="fa-solid fa-pen"></i> Ubah ID</button>
                            <button onclick='createMemberFromTechReport(${JSON.stringify(to)})' class="text-[10px] bg-indigo-600 hover:bg-indigo-500 text-white px-2.5 py-1 rounded-lg shadow-sm font-bold flex items-center gap-1 transition-all ml-2" title="Buat Akses Member Pelanggan dengan SN Otomatis">
                                <i class="fa-solid fa-user-plus"></i> BUAT MEMBER
                            </button>
                        </div>
                        
                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 text-xs text-slate-600 bg-slate-50 p-4 rounded-2xl border border-slate-200/60 mt-3">
                            <div class="flex flex-col">
                                <b class="mb-1">DVR/NVR SN:</b> 
                                ${dvrHtml}
                            </div>
                            <div class="flex flex-col sm:border-l sm:border-slate-200/60 sm:pl-3">
                                <b class="mb-1">CCTV SN:</b> 
                                ${cctvHtml}
                            </div>
                            <div class="flex flex-col sm:border-l sm:border-slate-200/60 sm:pl-3">
                                <b class="mb-1">HDD SN:</b> 
                                ${hddHtml}
                            </div>
                            <div class="flex flex-col sm:border-l sm:border-slate-200/60 sm:pl-3">
                                <b class="mb-1">SN PERANGKAT LAIN:</b>
                                ${otherHtml}
                            </div>
                        </div>
                        
                        <div class="text-[11px] font-bold text-slate-700 mt-3 bg-white p-2 border border-slate-100 rounded-lg"><b>Keterangan / Hasil Perbaikan:</b> ${to.catatan_maintenance || (to.kabel_qty ? to.kabel_qty + ' Meter Kabel' : 'Perbaikan Kendala Selesai')}</div>
                        ${(!isMaint && (parseInt(to.kabel_qty) > 0 || notes.kabel)) ? `<div class="text-[11px] font-bold text-slate-700 mt-2 bg-white p-2 border border-slate-100 rounded-lg whitespace-pre-line"><i class="fa-solid fa-ruler-horizontal mr-1 text-slate-400"></i><b>Kabel:</b> ${techEsc(parseInt(to.kabel_qty) || 0)} Meter${notes.kabel ? ` <span class="font-medium text-slate-600">- ${techEsc(notes.kabel)}</span>` : ''}</div>` : ''}
                        <div class="text-[10px] text-slate-400 mt-2"><i class="fa-regular fa-clock mr-1"></i> Waktu Pengerjaan: ${to.created_at}</div>
                    </div>
                    
                    <div class="flex flex-col items-stretch md:items-end gap-2 mt-3 md:mt-0 shrink-0">
                        ${approveBtn}
                        <button onclick="deleteTechnicianOrder('${to.id}')" class="px-4 py-2.5 bg-rose-50 hover:bg-rose-100 text-rose-600 rounded-xl text-xs font-bold transition-all flex items-center gap-1.5 shadow-sm" title="Hapus Laporan/WO ini">
                            <i class="fa-solid fa-trash"></i> Hapus W.O.
                        </button>
                    </div>
                </div>
            `;
        }

        async function editCustomerIdPrompt(oldId) {
            let newId = prompt('Ubah ID Pelanggan "' + oldId + '" menjadi:\n(huruf, angka, titik, strip, underscore; maks 50 karakter)', oldId);
            if (newId === null) return;
            newId = newId.trim();
            if (!newId || newId === oldId) return;
            try {
                let res = await fetch('admin.php?api=rename_customer_id', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({ old_id: oldId, new_id: newId })
                });
                let json = await res.json();
                if (json.success) {
                    showToast('ID Pelanggan diubah menjadi ' + newId);
                    loadAdminTechReports();
                    loadData();
                } else {
                    alert(json.msg || 'Gagal mengubah ID');
                }
            } catch (e) {
                alert('Gagal menghubungi server');
            }
        }

        async function approveTechnicianOrder(id) {
            if (!confirm('APPROVE data Work Order ini?\n\nStatus akan berubah menjadi DATA APPROVE.')) return;
            try {
                let res = await fetch('admin.php?api=confirm_technician_order', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({ id: id })
                });
                let json = await res.json();
                if (json.success) {
                    showToast(json.msg || 'Data berhasil di-APPROVE');
                    loadAdminTechReports();
                } else {
                    alert(json.msg || 'Gagal approve data');
                }
            } catch (e) {
                alert('Gagal menghubungi server');
            }
        }

        async function deleteTechnicianOrder(id) {
            if(confirm("Apakah Anda yakin ingin menghapus data Laporan Teknisi / Work Order ini? Data akan terhapus secara realtime dari web teknisi.")) {
                let res = await fetch(`admin.php?api=delete_technician_order&id=${id}`);
                let json = await res.json();
                if(json.success) {
                    showToast("Work Order berhasil dihapus");
                    loadAdminTechReports();
                } else {
                    alert("Gagal menghapus data");
                }
            }
        }

        function createMemberFromTechReport(to) {
            switchAdminTab('akses-member');
            let date = new Date();
            let yyyy = date.getFullYear();
            let mm = String(date.getMonth() + 1).padStart(2, '0');
            let dd = String(date.getDate()).padStart(2, '0');
            let newCustId = to.customer_id && to.customer_id.startsWith('CUST-') ? to.customer_id : `${yyyy}${mm}${dd}-${Math.floor(100 + Math.random() * 900)}`;
            let baseUsername = to.nama ? to.nama.toLowerCase().replace(/[^a-z0-9]/g, '').substring(0, 10) : 'user';
            let newUsername = baseUsername + '_' + Math.floor(Math.random() * 100);
            
            document.getElementById('edit-customer-db-id').value = '';
            document.getElementById('cust-acc-id').value = newCustId;
            document.getElementById('cust-acc-id').disabled = false;
            document.getElementById('cust-acc-name').value = to.nama || '';
            document.getElementById('cust-acc-phone').value = '';
            document.getElementById('cust-acc-username').value = newUsername;
            document.getElementById('cust-acc-password').value = 'CCTV123';
            document.getElementById('cust-acc-active-date').value = `${yyyy}-${mm}-${dd}`;
            
            document.getElementById('cust-acc-dvr').value = to.dvr_sn || '';
            
            try {
                let cctvs = JSON.parse(to.cctv_sn);
                if(Array.isArray(cctvs)) {
                    document.getElementById('cust-acc-cctv').value = cctvs.map(x => x.sn || x).join(', ');
                } else {
                    document.getElementById('cust-acc-cctv').value = to.cctv_sn || '';
                }
            } catch(e) {
                document.getElementById('cust-acc-cctv').value = to.cctv_sn || '';
            }
            
            document.getElementById('cust-acc-hdd').value = to.hdd_sn || '';
            document.getElementById('cust-acc-other').value = to.other_sn || '';

            document.getElementById('customer-form-title').innerText = "Buat Akses Member dari Laporan Teknisi";
            document.getElementById('cust-cancel-edit').classList.remove('hidden');
            window.scrollTo({ top: 0, behavior: 'smooth' });
            showToast('Form Akses Member disiapkan dengan Serial Number otomatis dari Teknisi');
        }

        // ==========================================
        // EXPORT / IMPORT PER TAB
        // ==========================================
        const BACKUP_TAB_LABELS = {
            pesanan: 'Pesanan Masuk',
            teknisi: 'Laporan Teknisi',
            laporan: 'Laporan Kendala',
            katalog: 'Katalog Produk',
            livechat: 'Live Chat',
            pelanggan: 'Database Pelanggan',
            akses: 'Akses Member',
            anggota: 'Akun Anggota'
        };

        function exportTab(tab) {
            window.location.href = 'admin.php?api=export_tab&tab=' + encodeURIComponent(tab);
            showToast('Mengunduh backup ' + (BACKUP_TAB_LABELS[tab] || tab) + '...');
        }

        async function importTab(input, tab) {
            const file = input.files && input.files[0];
            if (!file) return;
            const label = BACKUP_TAB_LABELS[tab] || tab;
            try {
                const text = await file.text();
                let parsed;
                try { parsed = JSON.parse(text); } catch (e) { alert('File backup JSON tidak valid atau rusak.'); return; }

                // Cegah salah tab: file backup baru membawa penanda tab
                if (parsed && parsed.tab && parsed.tab !== tab) {
                    const from = BACKUP_TAB_LABELS[parsed.tab] || parsed.tab;
                    alert('File ini adalah backup tab "' + from + '", bukan "' + label + '". Import dibatalkan.');
                    return;
                }
                if (!confirm('Import backup ke tab "' + label + '"?\n\nData dengan ID yang sama akan diperbarui. Data lain di tab ini tidak dihapus.')) return;

                const res = await fetch('admin.php?api=import_tab&tab=' + encodeURIComponent(tab), {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: text
                });
                const result = await res.json();
                if (result.success) {
                    showToast(result.msg);
                    loadData();
                } else {
                    alert('Gagal import: ' + result.msg);
                }
            } catch (err) {
                alert('Gagal import: ' + err.message);
            } finally {
                input.value = '';
            }
        }

        async function clearInactiveChats() {
            if(confirm("Apakah Anda yakin ingin menghapus/membersihkan semua riwayat livechat yang sudah nonaktif?")) {
                try {
                    let res = await fetch('admin.php?api=clear_inactive_chats');
                    let json = await res.json();
                    if(json.success) {
                        showToast('Riwayat chat nonaktif berhasil dibersihkan');
                        activeAdminChatCustomer = null;
                        document.getElementById('header-active-chat').innerText = "Pilih sesi pelanggan";
                        document.getElementById('btn-return-bot').classList.add('hidden');
                        document.getElementById('admin-chat-messages').innerHTML = '<div class="text-center text-slate-400 my-auto h-full flex items-center justify-center">Pilih salah satu sesi pelanggan.</div>';
                        loadData();
                    } else {
                        alert('Gagal membersihkan chat.');
                    }
                } catch(e) {
                    alert('Terjadi kesalahan jaringan.');
                }
            }
        }

        async function loadData() {
            try {
                let res = await fetch('admin.php?api=get_data', { cache: 'no-store' });
                if (!res.ok) throw new Error('HTTP ' + res.status);
                let json = await res.json();

                if(json.success && json.data) {
                    products = json.data.products || [];
                    orders = json.data.orders || [];
                    // Notifikasi realtime: member baru mengirim bukti TF
                    (function() {
                        const pendingNow = orders.filter(x => (x.payStatus || '').toUpperCase() === 'MENUNGGU').map(x => x.id);
                        if (knownPendingPayIds !== null) {
                            const baru = pendingNow.filter(id => !knownPendingPayIds.includes(id));
                            if (baru.length > 0) {
                                try { playNotificationSound(); } catch (e) {}
                                showToast('💳 Bukti TF baru masuk dari member (' + baru.join(', ') + ')');
                            }
                        }
                        knownPendingPayIds = pendingNow;
                    })();
                    // Notifikasi realtime: member memesan produk dari dashboard
                    (function() {
                        const memberNow = orders.filter(x => x.orderSource === 'MEMBER').map(x => x.id);
                        if (knownMemberOrderIds !== null) {
                            const baru = memberNow.filter(id => !knownMemberOrderIds.includes(id));
                            if (baru.length > 0) {
                                try { playNotificationSound(); } catch (e) {}
                                showToast('🛒 Pesanan produk baru dari member (' + baru.join(', ') + ')');
                            }
                        }
                        knownMemberOrderIds = memberNow;
                    })();
                    sessions = json.data.sessions || [];
                    members = json.data.members || [];
                    customerAccounts = json.data.customer_accounts || [];
                    tickets = json.data.tickets || [];
                    technicianOrders = json.data.technician_orders || [];
                    technicianAccounts = json.data.technician_accounts || [];
                    
                    if (tickets.length > lastKnownTicketCount && lastKnownTicketCount !== 0) {
                        playNotificationSound();
                        showToast('🔔 Ada Laporan Kendala Baru Masuk!');
                    }
                    lastKnownTicketCount = tickets.length;

                    renderAdminProducts();
                    renderOrders();
                    renderDataPelanggan();
                    renderCustomerAccounts();
                    renderTechnicianAccounts();
                    renderMembers();
                    renderTicketsList();

                    const badgeEl = document.getElementById('badge-ticket-count');
                    const subBadge = document.getElementById('ticket-badge-sub');
                    const totalCountEl = document.getElementById('admin-ticket-total-count');
                    if(tickets.length > 0) {
                        if(badgeEl) { badgeEl.innerText = tickets.length; badgeEl.classList.remove('hidden'); }
                        if(subBadge) subBadge.innerText = `${tickets.length} Tiket`;
                        if(totalCountEl) totalCountEl.innerText = `${tickets.length} Laporan`;
                    } else {
                        if(badgeEl) badgeEl.classList.add('hidden');
                        if(subBadge) subBadge.innerText = '0 Aktif';
                        if(totalCountEl) totalCountEl.innerText = '0 Laporan';
                    }

                    if(activeAdminChatCustomer) loadChatHistory(activeAdminChatCustomer);
                    if(document.getElementById('admin-tab-live-chat') && !document.getElementById('admin-tab-live-chat').classList.contains('hidden')) {
                        loadChatList();
                    }
                    if(document.getElementById('admin-tab-teknisi-reports') && !document.getElementById('admin-tab-teknisi-reports').classList.contains('hidden')) {
                        loadAdminTechReports();
                    }
                    if(activeTicketId) {
                        let tObj = tickets.find(x => x.id === activeTicketId);
                        if(tObj) {
                            loadTicketChatHistory(tObj);
                        } else {
                            activeTicketId = null;
                            document.getElementById('tc-title').innerText = "Pilih tiket laporan";
                            document.getElementById('tc-subtitle').innerText = "Detail kendala akan muncul di sini";
                            document.getElementById('tc-status-container').classList.add('hidden');
                            document.getElementById('ticket-chat-messages').innerHTML = '<div class="text-center text-slate-400 my-auto h-full flex items-center justify-center font-medium">Laporan telah selesai dan dihapus otomatis.</div>';
                            document.getElementById('ticket-chat-input').disabled = true;
                            document.getElementById('ticket-send-btn').disabled = true;
                        }
                    }
                }
            } catch(e) { console.error("Gagal loadData:", e); }
        }

        function playNotificationSound() {
            try {
                let audioCtx = new (window.AudioContext || window.webkitAudioContext)();
                let osc = audioCtx.createOscillator();
                let gain = audioCtx.createGain();
                osc.type = 'sine';
                osc.frequency.setValueAtTime(587.33, audioCtx.currentTime);
                gain.gain.setValueAtTime(0.2, audioCtx.currentTime);
                osc.connect(gain);
                gain.connect(audioCtx.destination);
                osc.start();
                osc.stop(audioCtx.currentTime + 0.3);
            } catch(e) {}
        }

        function renderTicketsList() {
            const listEl = document.getElementById('admin-ticket-list');
            if(!listEl) return;
            if(!tickets.length) {
                listEl.innerHTML = '<div class="p-8 text-center text-slate-400 font-bold text-xs">Belum ada laporan kendala dari user</div>';
                return;
            }
            listEl.innerHTML = tickets.map(t => {
                let stColor = t.status === 'MENUNGGU' ? 'bg-amber-100 text-amber-700' : (t.status === 'PROSES' ? 'bg-sky-100 text-sky-700' : 'bg-emerald-100 text-emerald-700');
                let tingkatColor = t.tingkat_masalah === 'TINGGI' ? 'text-rose-600' : (t.tingkat_masalah === 'SEDANG' ? 'text-orange-600' : 'text-amber-600');
                return `
                    <div onclick="selectTicket('${t.id}')" class="p-4 cursor-pointer hover:bg-slate-50 border-b transition-colors ${activeTicketId === t.id ? 'bg-sky-50/70 border-l-4 border-l-sky-500' : 'border-l-4 border-l-transparent'}">
                        <div class="flex justify-between items-start mb-1.5">
                            <span class="font-black text-xs text-slate-900">${t.judul}</span>
                            <span class="px-2 py-0.5 rounded text-[9px] font-black uppercase ${stColor}">${t.status}</span>
                        </div>
                        <div class="text-[11px] text-slate-600 font-medium mb-1 flex items-center justify-between">
                            <span><i class="fa-solid fa-user mr-1 text-slate-400"></i> ${t.nama} (${t.customer_id})</span>
                            <span class="font-bold ${tingkatColor}">${t.tingkat_masalah}</span>
                        </div>
                        <div class="text-[10px] text-slate-400">${t.created_at}</div>
                    </div>
                `;
            }).join('');
        }

        async function selectTicket(tid) {
            activeTicketId = tid;
            renderTicketsList();
            let t = tickets.find(x => x.id === tid);
            if(!t) return;

            document.getElementById('tc-title').innerText = `${t.judul} (${t.id})`;
            document.getElementById('tc-subtitle').innerHTML = `Pelanggan: <b>${t.nama}</b> (ID: ${t.customer_id}) • Tingkat: <b class="text-rose-600">${t.tingkat_masalah}</b>`;
            document.getElementById('tc-status-container').classList.remove('hidden');

            document.getElementById('tc-status-select').value = t.status;
            document.getElementById('ticket-chat-input').disabled = false;
            document.getElementById('ticket-send-btn').disabled = false;

            loadTicketChatHistory(t);
        }

        async function loadTicketChatHistory(t) {
            let res = await fetch('admin.php?api=chat_history&channel=ticket&session_id=' + encodeURIComponent(t.id));
            let json = await res.json();
            const box = document.getElementById('ticket-chat-messages');
            if(!box) return;

            let imageProofHtml = '';
            if (t.proof_image) {
                imageProofHtml = `
                    <div class="mt-3 pt-3 border-t border-sky-200">
                        <span class="block font-bold text-[10px] uppercase text-sky-700 mb-2"><i class="fa-solid fa-image mr-1"></i> Foto Bukti Lampiran Member:</span>
                        <div class="flex items-center gap-3">
                            <img src="${t.proof_image}" onclick="openImageModal('${t.proof_image}')" class="w-24 h-24 object-cover rounded-xl border border-sky-300 cursor-pointer hover:opacity-90 shadow-sm" title="Klik untuk memperbesar">
                            <div class="flex flex-col gap-2">
                                <button onclick="openImageModal('${t.proof_image}')" class="px-3 py-1.5 bg-sky-600 hover:bg-sky-500 text-white rounded-lg text-xs font-bold shadow-sm transition-all"><i class="fa-solid fa-magnifying-glass-plus mr-1"></i> Perbesar Foto</button>
                                <a href="${t.proof_image}" download="Bukti_Kendala_${t.id}.png" class="px-3 py-1.5 bg-emerald-600 hover:bg-emerald-500 text-white rounded-lg text-xs font-bold shadow-sm text-center transition-all"><i class="fa-solid fa-download mr-1"></i> Download Foto</a>
                            </div>
                        </div>
                    </div>
                `;
            }

            let descHtml = `<div class="p-3.5 bg-sky-50 border border-sky-100 rounded-2xl text-xs text-sky-900 mb-3">
                <b class="block font-bold text-[10px] uppercase text-sky-700 mb-1">Deskripsi Kendala Awal:</b>
                ${t.deskripsi}
                ${imageProofHtml}
            </div>`;

            if(!json.messages || json.messages.length === 0) {
                box.innerHTML = descHtml + `<div class="text-center text-slate-400 my-4 text-xs font-medium">Belum ada riwayat percakapan balasan untuk tiket ini.</div>`;
                return;
            }

            box.innerHTML = descHtml + json.messages.map(m => {
                if(m.sender == 'system') return `<div class="text-center text-[10px] text-slate-400 font-bold my-2 flex items-center justify-center gap-2"><span class="h-px w-8 bg-slate-200"></span> ${m.text} <span class="h-px w-8 bg-slate-200"></span></div>`;
                let style = m.sender == 'customer' ? 'bg-white border border-slate-200 text-slate-800' : 'bg-sky-600 text-white ml-auto text-right shadow-md';
                let align = m.sender == 'customer' ? 'items-start' : 'items-end';
                let senderName = m.sender == 'customer' ? t.nama : 'Admin';
                return `<div class="flex flex-col ${align} w-full"><div class="p-3 rounded-2xl ${style} max-w-[85%] relative" style="white-space: pre-wrap;"><b class="block font-bold text-[9px] opacity-70 mb-1 uppercase tracking-wider">${senderName}</b>${m.text}</div></div>`;
            }).join('');
            box.scrollTop = box.scrollHeight;
        }

        async function updateTicketStatusFromSelect(newStatus) {
            if(!activeTicketId) return;
            let res = await fetch('admin.php?api=update_ticket_status', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({ticket_id: activeTicketId, status: newStatus})
            });
            let json = await res.json();
            if(json.success) {
                if(newStatus === 'SELESAI') {
                    showToast('Laporan telah selesai dan dihapus otomatis.');
                    activeTicketId = null;
                    document.getElementById('tc-title').innerText = "Pilih tiket laporan";
                    document.getElementById('tc-subtitle').innerText = "Detail kendala akan muncul di sini";
                    document.getElementById('tc-status-container').classList.add('hidden');
                    document.getElementById('ticket-chat-messages').innerHTML = '<div class="text-center text-slate-400 my-auto h-full flex items-center justify-center font-medium">Laporan telah selesai dan dihapus otomatis.</div>';
                    document.getElementById('ticket-chat-input').disabled = true;
                    document.getElementById('ticket-send-btn').disabled = true;
                } else {
                    showToast(`Status laporan diubah ke: ${newStatus}`);
                }
                loadData();
            }
        }

        async function sendTicketReply() {
            const inp = document.getElementById('ticket-chat-input');
            if(!inp.value.trim() || !activeTicketId) return;
            let text = inp.value.trim();
            inp.value = '';

            let adminSender = loggedInAdminName ? loggedInAdminName : 'Admin';

            await fetch('admin.php?api=send_chat', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({session_id: activeTicketId, message: text, sender: adminSender, channel: 'ticket'})
            });

            let t = tickets.find(x => x.id === activeTicketId);
            if(t) loadTicketChatHistory(t);
        }

        async function saveCustomerAccount() {
            // Saat edit, ID field di-disable — pakai hidden edit-customer-db-id sebagai cadangan
            let cidField = document.getElementById('cust-acc-id');
            let cidHidden = document.getElementById('edit-customer-db-id');
            let customerId = (cidField.value || '').trim() || (cidHidden ? cidHidden.value.trim() : '') || ('CUST-' + Date.now());

            // Jika ID diganti saat edit: ubah dulu di seluruh data terkait (user, teknisi, tiket)
            let oldCustomerId = cidHidden ? cidHidden.value.trim() : '';
            if (oldCustomerId && customerId !== oldCustomerId) {
                if (!confirm(`Ubah ID Pelanggan dari "${oldCustomerId}" menjadi "${customerId}"?\n\nData di user (member), laporan teknisi, dan tiket akan ikut terhubung ke ID baru.`)) return;
                try {
                    let rr = await fetch('admin.php?api=rename_customer_id', {
                        method: 'POST',
                        headers: {'Content-Type': 'application/json'},
                        body: JSON.stringify({ old_id: oldCustomerId, new_id: customerId })
                    });
                    let rj = await rr.json();
                    if (!rj.success) return alert(rj.msg || 'Gagal mengubah ID Pelanggan');
                    cidHidden.value = customerId;
                } catch (e) {
                    return alert('Gagal menghubungi server saat mengubah ID Pelanggan');
                }
            }

            let ca = {
                customer_id: customerId,
                name: document.getElementById('cust-acc-name').value.trim(),
                username: document.getElementById('cust-acc-username').value.trim(),
                password: document.getElementById('cust-acc-password').value.trim(),
                phone: document.getElementById('cust-acc-phone').value.trim(),
                active_date: document.getElementById('cust-acc-active-date').value.trim(),
                dvr_sn: document.getElementById('cust-acc-dvr').value.trim(),
                cctv_sn: document.getElementById('cust-acc-cctv').value.trim(),
                hdd_sn: document.getElementById('cust-acc-hdd').value.trim(),
                other_sn: document.getElementById('cust-acc-other').value.trim(),
                from_admin: true
            };

            if(!ca.customer_id || !ca.username || !ca.password) {
                return alert('ID Pelanggan, Username, dan Password wajib diisi.');
            }

            let res = await fetch('admin.php?api=save_customer_account', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify(ca)
            });
            let json = await res.json();
            if(json.success) {
                showToast('Akses Pelanggan Berhasil Disimpan');
                resetCustomerForm();
                loadData();
            } else {
                alert('Gagal menyimpan: ' + (json.msg || 'Terjadi kesalahan'));
            }
        }

        function renderCustomerAccounts() {
            const container = document.getElementById('admin-customer-acc-table');
            if(!container) return;
            document.getElementById('admin-customer-acc-count').innerText = `${customerAccounts.length} Akun`;
            if(!customerAccounts.length) {
                container.innerHTML = '<div class="p-8 text-center text-slate-400 font-bold">Belum ada akun member pelanggan terdaftar</div>';
                return;
            }
            container.innerHTML = customerAccounts.map(c => `
                <div class="p-4 md:p-5 border-b border-slate-100 flex flex-col md:flex-row justify-between items-start md:items-center gap-4 hover:bg-slate-50 transition-colors">
                    <div>
                        <div class="font-black text-slate-900 text-base mb-1 flex items-center gap-2">
                            <span class="bg-sky-100 text-sky-700 px-2.5 py-0.5 rounded text-xs">${c.customer_id}</span>
                            ${c.name || 'Tanpa Nama'}
                        </div>
                        <div class="text-xs text-slate-600 font-medium space-x-3 mb-1">
                            <span><i class="fa-solid fa-user mr-1 text-slate-400"></i> User: <b>${c.username}</b></span>
                            <span><i class="fa-solid fa-key mr-1 text-slate-400"></i> Pass: <b>${c.password}</b></span>
                            <span><i class="fa-solid fa-phone mr-1 text-slate-400"></i> HP: <b>${c.phone || '-'}</b></span>
                        </div>
                        <div class="text-[11px] text-slate-500 font-medium space-y-1 bg-slate-50 p-2.5 rounded-xl border border-slate-100">
                            <div>📅 Aktif: <b>${c.active_date || '-'}</b></div>
                            <div>📼 DVR SN: <b>${c.dvr_sn || '-'}</b></div>
                            <div>📷 CCTV SN: <b>${c.cctv_sn || '-'}</b></div>
                            <div>💾 HDD SN: <b>${c.hdd_sn || '-'}</b></div>
                            <div><i class="fa-solid fa-plug mr-1"></i> Perangkat Lain SN: <b>${c.other_sn || '-'}</b></div>
                        </div>
                    </div>
                    <div class="flex gap-2 w-full md:w-auto">
                        <button onclick='editCustomerAccount(${JSON.stringify(c)})' class="flex-1 md:flex-none px-4 py-2 bg-sky-50 hover:bg-sky-100 text-sky-600 rounded-xl font-bold text-xs"><i class="fa-solid fa-pen mr-1"></i> Edit</button>
                        <button onclick="deleteCustomerAccount('${c.customer_id}')" class="flex-1 md:flex-none px-4 py-2 bg-rose-50 hover:bg-rose-100 text-rose-600 rounded-xl font-bold text-xs"><i class="fa-solid fa-trash mr-1"></i> Hapus</button>
                    </div>
                </div>
            `).join('');
        }

        function editCustomerAccount(c) {
            document.getElementById('edit-customer-db-id').value = c.customer_id;
            document.getElementById('cust-acc-id').value = c.customer_id;
            document.getElementById('cust-acc-id').disabled = false;
            document.getElementById('cust-acc-name').value = c.name || '';
            document.getElementById('cust-acc-username').value = c.username || '';
            document.getElementById('cust-acc-password').value = c.password || '';
            document.getElementById('cust-acc-phone').value = c.phone || '';
            document.getElementById('cust-acc-active-date').value = c.active_date || '';
            document.getElementById('cust-acc-dvr').value = c.dvr_sn || '';
            document.getElementById('cust-acc-cctv').value = c.cctv_sn || '';
            document.getElementById('cust-acc-hdd').value = c.hdd_sn || '';
            document.getElementById('cust-acc-other').value = c.other_sn || '';
            document.getElementById('customer-form-title').innerText = "Edit Akses Pelanggan";
            document.getElementById('cust-cancel-edit').classList.remove('hidden');
        }

        function resetCustomerForm() {
            document.getElementById('edit-customer-db-id').value = '';
            document.getElementById('cust-acc-id').value = '';
            document.getElementById('cust-acc-id').disabled = false;
            document.getElementById('cust-acc-name').value = '';
            document.getElementById('cust-acc-username').value = '';
            document.getElementById('cust-acc-password').value = '';
            document.getElementById('cust-acc-phone').value = '';
            document.getElementById('cust-acc-active-date').value = '';
            document.getElementById('cust-acc-dvr').value = '';
            document.getElementById('cust-acc-cctv').value = '';
            document.getElementById('cust-acc-hdd').value = '';
            document.getElementById('cust-acc-other').value = '';
            document.getElementById('customer-form-title').innerText = "Kelola Akses Member Pelanggan";
            document.getElementById('cust-cancel-edit').classList.add('hidden');
        }

        async function deleteCustomerAccount(id) {
            if(confirm('Hapus akses akun member pelanggan ini?')) {
                await fetch('admin.php?api=delete_customer_account&id=' + id);
                showToast('Akun Pelanggan Dihapus');
                loadData();
            }
        }

        function renderTechnicianAccounts() {
            const container = document.getElementById('admin-tech-acc-table');
            if (!container) return;
            const countEl = document.getElementById('admin-tech-acc-count');
            if (countEl) countEl.innerText = `${technicianAccounts.length} Akun`;
            if (!technicianAccounts.length) {
                container.innerHTML = '<div class="p-8 text-center text-slate-400 font-bold text-xs">Belum ada akun teknisi. Buat di form di atas agar teknisi bisa login.</div>';
                return;
            }
            container.innerHTML = technicianAccounts.map(t => `
                <div class="p-4 md:p-5 border-b border-slate-100 flex flex-col md:flex-row justify-between items-start md:items-center gap-4 hover:bg-slate-50">
                    <div>
                        <div class="font-black text-slate-900 text-sm mb-1 flex items-center gap-2">
                            <span class="bg-rose-100 text-rose-700 px-2.5 py-0.5 rounded text-xs">${t.id}</span>
                            ${t.name || t.username}
                        </div>
                        <div class="text-xs text-slate-600 font-medium space-x-3">
                            <span><i class="fa-solid fa-user mr-1 text-slate-400"></i> User: <b>${t.username}</b></span>
                            <span><i class="fa-solid fa-key mr-1 text-slate-400"></i> Pass: <b>${t.password}</b></span>
                            <span><i class="fa-solid fa-phone mr-1 text-slate-400"></i> ${t.phone || '-'}</span>
                            <span class="${Number(t.is_active)===1?'text-emerald-600':'text-rose-600'} font-bold">${Number(t.is_active)===1?'AKTIF':'NONAKTIF'}</span>
                        </div>
                    </div>
                    <div class="flex gap-2 w-full md:w-auto">
                        <button onclick='editTechnicianAccount(${JSON.stringify(t)})' class="flex-1 md:flex-none px-4 py-2 bg-sky-50 hover:bg-sky-100 text-sky-600 rounded-xl font-bold text-xs"><i class="fa-solid fa-pen mr-1"></i> Edit</button>
                        <button onclick="deleteTechnicianAccount('${t.id}')" class="flex-1 md:flex-none px-4 py-2 bg-rose-50 hover:bg-rose-100 text-rose-600 rounded-xl font-bold text-xs"><i class="fa-solid fa-trash mr-1"></i> Hapus</button>
                    </div>
                </div>
            `).join('');
        }

        async function saveTechnicianAccount() {
            let id = document.getElementById('edit-tech-acc-id').value.trim() || ('TECH-' + Date.now());
            let payload = {
                id: id,
                name: document.getElementById('tech-acc-name').value.trim(),
                username: document.getElementById('tech-acc-username').value.trim(),
                password: document.getElementById('tech-acc-password').value.trim(),
                phone: document.getElementById('tech-acc-phone').value.trim(),
                is_active: 1
            };
            if (!payload.username || !payload.password) {
                return alert('Username dan Password teknisi wajib diisi.');
            }
            let res = await fetch('admin.php?api=save_technician_account', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify(payload)
            });
            let json = await res.json();
            if (json.success) {
                showToast('Akses Teknisi Berhasil Disimpan');
                resetTechAccForm();
                loadData();
            } else {
                alert(json.msg || 'Gagal menyimpan');
            }
        }

        function editTechnicianAccount(t) {
            document.getElementById('edit-tech-acc-id').value = t.id;
            document.getElementById('tech-acc-name').value = t.name || '';
            document.getElementById('tech-acc-username').value = t.username || '';
            document.getElementById('tech-acc-password').value = t.password || '';
            document.getElementById('tech-acc-phone').value = t.phone || '';
            document.getElementById('tech-cancel-edit').classList.remove('hidden');
        }

        function resetTechAccForm() {
            document.getElementById('edit-tech-acc-id').value = '';
            document.getElementById('tech-acc-name').value = '';
            document.getElementById('tech-acc-username').value = '';
            document.getElementById('tech-acc-password').value = '';
            document.getElementById('tech-acc-phone').value = '';
            document.getElementById('tech-cancel-edit').classList.add('hidden');
        }

        async function deleteTechnicianAccount(id) {
            if (confirm('Hapus akun teknisi ini? Yang bersangkutan tidak bisa login lagi.')) {
                await fetch('admin.php?api=delete_technician_account&id=' + encodeURIComponent(id));
                showToast('Akun Teknisi Dihapus');
                loadData();
            }
        }

        function renderAdminProducts() {
            const container = document.getElementById('admin-product-table');
            if(!container) return;
            document.getElementById('admin-product-count').innerText = `${products.length} Data`;
            if(!products.length) {
                container.innerHTML = '<div class="p-8 text-center text-slate-400 font-bold">Belum ada produk terdaftar</div>';
                return;
            }
            container.innerHTML = products.map(p => `
                <div class="flex flex-col md:flex-row items-start md:items-center justify-between p-4 md:p-5 border-b border-slate-100 hover:bg-slate-50 gap-4 transition-colors">
                    <div class="flex items-center gap-4 w-full md:w-auto">
                        <img src="${p.image}" class="w-16 h-16 object-cover rounded-2xl bg-slate-100 shrink-0 border border-slate-200">
                        <div class="flex-1">
                            <div class="font-bold text-slate-900 mb-1 leading-tight"><span class="bg-sky-100 text-sky-700 px-2 py-0.5 rounded text-[10px] mr-2 align-middle">${p.group_name}</span>${p.name}</div>
                            <div class="text-emerald-600 font-black text-sm">Rp ${Number(p.price).toLocaleString('id-ID')}</div>
                        </div>
                    </div>
                    <div class="flex gap-2 w-full md:w-auto mt-2 md:mt-0">
                        <button onclick='editProduct(${JSON.stringify(p)})' class="flex-1 md:flex-none px-4 py-2.5 bg-sky-50 hover:bg-sky-100 text-sky-600 rounded-xl font-bold text-xs transition-colors"><i class="fa-solid fa-pen mr-1"></i> Edit</button>
                        <button onclick="deleteProduct('${p.id}')" class="flex-1 md:flex-none px-4 py-2.5 bg-rose-50 hover:bg-rose-100 text-rose-600 rounded-xl font-bold text-xs transition-colors"><i class="fa-solid fa-trash mr-1"></i> Hapus</button>
                    </div>
                </div>
            `).join('');
        }

        // Grup produk = filter katalog di beranda tekunin.net. Ubah daftar ini jika grup di beranda berubah.
        const TK_PRODUCT_GROUPS = ['CCTV', 'NVR/DVR', 'Access Control', 'Alarm', 'Video Doorbell', 'Network', 'Tools'];

        function tkFillGroupSelect() {
            const sel = document.getElementById('admin-group');
            if(!sel) return;
            sel.innerHTML = '';
            const ph = document.createElement('option');
            ph.value = ''; ph.textContent = 'Tanpa grup (Lainnya)';
            sel.appendChild(ph);
            TK_PRODUCT_GROUPS.forEach(g => {
                const o = document.createElement('option');
                o.value = g; o.textContent = g;
                sel.appendChild(o);
            });
        }

        function tkClearLegacyGroup() {
            const sel = document.getElementById('admin-group');
            if(!sel) return;
            Array.from(sel.querySelectorAll('option[data-legacy]')).forEach(o => o.remove());
        }

        // Produk lama bisa punya grup di luar daftar (mis. nama merek). Tampilkan apa adanya agar tidak hilang saat diedit.
        function tkEnsureGroupOption(val) {
            const sel = document.getElementById('admin-group');
            if(!sel) return;
            tkClearLegacyGroup();
            if(!val) return;
            if(Array.from(sel.options).some(o => o.value === val)) return;
            const o = document.createElement('option');
            o.value = val; o.textContent = val + ' (di luar daftar beranda)';
            o.setAttribute('data-legacy', '1');
            sel.appendChild(o);
        }
        tkFillGroupSelect();

        async function saveAdminProduct() {
            let p = {
                id: document.getElementById('edit-product-id').value || 'PROD-' + Date.now(),
                name: document.getElementById('admin-name').value,
                group_name: document.getElementById('admin-group').value || 'Lainnya',
                badge: document.getElementById('admin-badge').value || 'Tersedia',
                price: parseFloat(document.getElementById('admin-price').value),
                image: document.getElementById('admin-image').value || 'https://images.unsplash.com/photo-1557324232-b8917d3c3dcb?q=80&w=800&auto=format&fit=crop',
                description: document.getElementById('admin-desc').value
            };
            if(!p.name || !p.price) return alert('Nama dan Harga wajib diisi');
            let res = await fetch('admin.php?api=save_product', { method: 'POST', body: JSON.stringify(p) });
            let json = await res.json();
            if(json.success) { showToast('Produk Berhasil Disimpan'); resetAdminForm(); loadData(); }
        }

        async function importProductsJson(input) {
            const file = input.files && input.files[0];
            if(!file) return;
            try {
                const list = JSON.parse(await file.text());
                if(!Array.isArray(list) || !list.length) throw new Error('Format harus berupa array produk');
                if(!confirm('Import ' + list.length + ' produk ke Katalog? Produk dengan ID sama akan diperbarui.')) { input.value=''; return; }
                let ok = 0;
                for(const x of list) {
                    if(!x.id || !x.name || !(Number(x.price) > 0)) continue;
                    const p = { id: String(x.id), name: String(x.name), group_name: String(x.group_name || 'Lainnya'), badge: String(x.badge || 'Tersedia'), price: Number(x.price), image: String(x.image || ''), description: String(x.description || '') };
                    const res = await fetch('admin.php?api=save_product', { method: 'POST', body: JSON.stringify(p) });
                    const json = await res.json();
                    if(json.success) ok++;
                }
                showToast(ok + ' produk berhasil diimport');
                loadData();
            } catch(e) { alert('Gagal import: ' + e.message); }
            input.value = '';
        }

        function editProduct(p) {
            document.getElementById('edit-product-id').value = p.id;
            document.getElementById('admin-name').value = p.name;
            tkEnsureGroupOption(p.group_name);
            document.getElementById('admin-group').value = p.group_name || '';
            document.getElementById('admin-badge').value = p.badge;
            document.getElementById('admin-price').value = p.price;
            document.getElementById('admin-image').value = p.image;
            document.getElementById('admin-desc').value = p.description;
            document.getElementById('admin-form-title').innerText = "Edit Produk";
            document.getElementById('admin-cancel-edit').classList.remove('hidden');
        }

        function resetAdminForm() {
            tkClearLegacyGroup();
            document.getElementById('edit-product-id').value = '';
            ['admin-name','admin-group','admin-badge','admin-price','admin-image','admin-desc'].forEach(id => document.getElementById(id).value = '');
            document.getElementById('admin-form-title').innerText = "Tambah Produk Baru";
            document.getElementById('admin-cancel-edit').classList.add('hidden');
        }

        async function deleteProduct(id) {
            if(confirm('Hapus produk?')) { await fetch('admin.php?api=delete_product&id=' + id); showToast('Dihapus'); loadData(); }
        }

        function renderOrders() {
            const container = document.getElementById('admin-customer-table');
            if(!container) return;
            document.getElementById('admin-customer-count').innerText = `${orders.length} Pesanan`;
            if(!orders.length) {
                container.innerHTML = '<div class="p-8 text-center text-slate-400 font-bold">Belum ada pesanan masuk</div>';
                return;
            }
            container.innerHTML = orders.map((o, index) => `
                <div class="p-5 md:p-6 border-b border-slate-100 hover:bg-slate-50 flex flex-col lg:flex-row gap-5 lg:gap-8 transition-colors">
                    <div class="flex-1">
                        <div class="flex justify-between items-start mb-3">
                            <div>
                                <b class="text-sky-700 block text-sm">${o.id}</b>
                                <span class="text-[10px] text-slate-500 font-bold uppercase">${o.date}</span>
                            </div>
                            <div class="flex gap-2">
                                <span class="bg-slate-900 text-white px-3 py-1 rounded-lg text-[10px] font-black tracking-wider shadow-sm">${o.status}</span>
                                <span class="bg-sky-100 text-sky-700 px-3 py-1 rounded-lg text-[10px] font-black tracking-wider border border-sky-200">${o.trackingStatus || 'PROSES'}</span>
                            </div>
                        </div>
                        <div class="mb-4">
                            <b class="block text-slate-900 text-base mb-1">${o.name}</b>
                            ${o.orderSource === 'MEMBER' ? `<div class="mb-2"><span class="inline-flex items-center gap-1 bg-violet-100 text-violet-700 border border-violet-200 px-2 py-0.5 rounded-lg text-[10px] font-black uppercase"><i class="fa-solid fa-cart-shopping"></i> Order Member${o.customerId ? ' &middot; ' + o.customerId : ''}</span>${o.orderNote ? `<div class="mt-1.5 text-[11px] text-slate-600 bg-amber-50 border border-amber-100 rounded-lg px-2 py-1 whitespace-pre-line"><b class="text-amber-700">Catatan:</b> ${String(o.orderNote).replace(/</g,'&lt;')}</div>` : ''}</div>` : ''}
                            <div class="text-xs text-slate-600 font-bold mb-1"><i class="fa-solid fa-phone mr-1.5 text-slate-400"></i>${o.phone}</div>
                            <div class="text-xs text-slate-500 line-clamp-2"><i class="fa-solid fa-location-dot mr-1.5 text-slate-400"></i>${o.address}</div>
                        </div>
                        <div class="text-xs bg-white p-3.5 rounded-xl border border-slate-200">
                            <div class="font-extrabold text-slate-800 mb-2 border-b border-slate-100 pb-1">Daftar Barang:</div>
                            <div class="space-y-1 text-slate-600 font-medium">${(o.items||[]).map(x=>`<div class="flex justify-between"><span>${x.name}</span> <span class="font-bold text-slate-900">x${x.qty}</span></div>`).join('')}</div>
                        </div>
                    </div>
                    
                    <div class="flex-1 flex flex-col gap-4 justify-center">
                        <div class="flex justify-between items-center bg-emerald-50 p-4 rounded-2xl border border-emerald-100">
                            <span class="text-xs font-extrabold text-emerald-800 uppercase">Total Belanja</span>
                            <b class="text-emerald-600 text-lg">Rp ${Number(o.total).toLocaleString('id-ID')}</b>
                        </div>
                        <div class="grid grid-cols-2 gap-3">
                            <div class="bg-slate-50 p-3 rounded-2xl border border-slate-100">
                                <label class="text-[10px] font-bold text-slate-500 uppercase block mb-1.5">Pembayaran</label>
                                <select onchange="updateOrderStatus('${o.id}', this.value)" class="text-xs p-2.5 border border-slate-200 rounded-xl w-full font-bold bg-white focus:outline-none mb-2 focus:border-sky-500">
                                    <option value="BELUM LUNAS" ${o.status=='BELUM LUNAS'?'selected':''}>BELUM LUNAS</option>
                                    <option value="DP" ${o.status=='DP'?'selected':''}>DP DIBAYAR</option>
                                    <option value="LUNAS" ${o.status=='LUNAS'?'selected':''}>LUNAS</option>
                                </select>
                                ${o.status=='DP' ? `<input type="number" value="${o.dpAmount||0}" onchange="updateDpAmount('${o.id}', this.value)" placeholder="Nominal DP" class="text-xs p-2.5 border border-emerald-200 rounded-xl w-full mt-1 mb-2 text-emerald-600 font-bold bg-emerald-50 focus:outline-none">` : ''}

                                <div class="flex justify-between items-center mt-3 pt-3 border-t border-slate-200 mb-1.5">
                                    <label class="text-[10px] font-bold text-slate-500 uppercase">Progress Tracking</label>
                                </div>
                                <select onchange="updateTrackingStatus('${o.id}', this.value)" class="text-xs p-2.5 border border-sky-200 rounded-xl w-full font-bold bg-sky-50 text-sky-700 focus:outline-none focus:border-sky-500">
                                    <option value="PROSES" ${(!o.trackingStatus || o.trackingStatus=='PROSES')?'selected':''}>01 - PROSES</option>
                                    <option value="KONFIRMASI" ${o.trackingStatus=='KONFIRMASI'?'selected':''}>02 - KONFIRMASI USER</option>
                                    <option value="MENUJU LOKASI" ${o.trackingStatus=='MENUJU LOKASI'?'selected':''}>03 - MENUJU LOKASI</option>
                                    <option value="SELESAI RAMPUNG" ${o.trackingStatus=='SELESAI RAMPUNG'?'selected':''}>04 - INSTALASI SELESAI</option>
                                </select>
                            </div>
                            <div class="bg-slate-50 p-3 rounded-2xl border border-slate-100">
                                <label class="text-[10px] font-bold text-slate-500 uppercase block mb-1.5">Garansi</label>
                                <select onchange="updateOrderWarranty('${o.id}', this.value)" class="text-xs p-2.5 border border-sky-200 rounded-xl bg-sky-50 text-sky-700 w-full font-bold focus:outline-none focus:border-sky-500">
                                    <option value="" ${!o.warrantyType?'selected':''}>Belum Diatur</option>
                                    <option value="MAINTENANCE" ${o.warrantyType=='MAINTENANCE'?'selected':''}>Maintenance 3 Bln</option>
                                    <option value="PRODUK" ${o.warrantyType=='PRODUK'?'selected':''}>Produk 1 Tahun</option>
                                    <option value="FULL" ${o.warrantyType=='FULL'?'selected':''}>FULL GARANSI</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="flex-1 flex flex-col justify-end gap-3 lg:border-l lg:pl-6 lg:border-slate-200">
                        <div class="bg-slate-50 p-4 rounded-2xl border border-slate-100">
                            <span class="text-[10px] font-extrabold text-slate-800 uppercase block mb-2"><i class="fa-solid fa-building-columns mr-1 text-slate-400"></i> Pembayaran: ${o.bank}</span>
                            ${adminPayBlock(o)}
                            ${adminSettleBlock(o)}
                            ${(o.hasProof || o.proofImage || o.proof_image) ? `<button onclick="openImageModalByOrderId('${o.id}')" class="text-xs bg-sky-600 text-white hover:bg-sky-500 px-4 py-2 rounded-xl font-bold w-full mb-3 shadow-md shadow-sky-600/20 transition-all"><i class="fa-solid fa-receipt mr-1"></i> Cek Bukti Transfer</button>` : '<span class="text-[10px] text-slate-500 block mb-3 italic font-medium bg-white p-2 rounded-lg border border-slate-200 text-center">Belum ada bukti TF diunggah</span>'}
                            <div class="relative">
                                <input type="file" accept="image/*" onchange="uploadProofAdmin('${o.id}', this)" class="absolute inset-0 w-full h-full opacity-0 cursor-pointer">
                                <div class="text-[10px] text-center w-full py-2 rounded-xl border-2 border-dashed border-slate-300 font-bold text-slate-500 bg-white group-hover:border-sky-300 transition-colors">
                                    Upload / Timpa Bukti TF
                                </div>
                            </div>
                        </div>
                        <div class="flex gap-2">
                            <button onclick='sendWhatsAppPO(${JSON.stringify(o)})' class="flex-1 py-3 bg-emerald-600 hover:bg-emerald-500 text-white rounded-xl text-xs font-bold flex items-center justify-center gap-1.5 shadow-lg shadow-emerald-600/20 transition-all"><i class="fa-brands fa-whatsapp text-sm"></i> Kirim Struk</button>
                            <button onclick="deleteOrder('${o.id}')" class="w-12 bg-rose-50 hover:bg-rose-100 text-rose-600 rounded-xl flex items-center justify-center transition-colors" title="Hapus PO"><i class="fa-solid fa-trash text-xs"></i></button>
                        </div>
                    </div>
                </div>
            `).join('');
        }

        // ===== PEMBAYARAN MEMBER (BUKTI TF + APPROVED) =====
        function adminPayBlock(o) {
            const st = (o.payStatus || '').toUpperCase();
            if (!st) return '';
            const nominal = Number(o.payAmount || 0).toLocaleString('id-ID');
            const info = `<div class="text-[10px] text-slate-600 font-bold leading-relaxed mt-1">
                    <div><i class="fa-solid fa-building-columns mr-1 text-slate-400"></i>${o.payBank || '-'}</div>
                    <div><i class="fa-solid fa-money-bill-wave mr-1 text-slate-400"></i>Rp ${nominal}${o.payStage ? ' (' + (o.payStage == 'SISA' ? 'Sisa DP' : o.payStage) + ')' : ''}</div>
                    ${o.payAt ? `<div><i class="fa-regular fa-clock mr-1 text-slate-400"></i>Dikirim: ${o.payAt}</div>` : ''}
                </div>`;
            if (st === 'MENUNGGU') {
                return `<div class="bg-amber-50 border border-amber-200 rounded-xl p-3 mb-3">
                    <div class="text-[10px] font-black uppercase text-amber-700"><i class="fa-solid fa-hourglass-half mr-1"></i> Menunggu Konfirmasi Admin</div>
                    ${info}
                    <div class="flex gap-2 mt-3">
                        <button onclick="reviewOrderPayment('${o.id}','DITERIMA')" class="flex-1 text-xs bg-emerald-600 hover:bg-emerald-500 text-white py-2 rounded-xl font-black shadow-md shadow-emerald-600/20"><i class="fa-solid fa-check mr-1"></i> APPROVED</button>
                        <button onclick="reviewOrderPayment('${o.id}','DITOLAK')" class="text-xs bg-rose-50 hover:bg-rose-100 text-rose-600 px-3 py-2 rounded-xl font-black border border-rose-200" title="Tolak bukti TF">TOLAK</button>
                    </div>
                </div>`;
            }
            if (st === 'DITERIMA') {
                return `<div class="bg-emerald-50 border border-emerald-200 rounded-xl p-3 mb-3">
                    <div class="text-[10px] font-black uppercase text-emerald-700"><i class="fa-solid fa-circle-check mr-1"></i> Pembayaran Diterima</div>
                    ${info}
                </div>`;
            }
            if (st === 'DITOLAK') {
                return `<div class="bg-rose-50 border border-rose-200 rounded-xl p-3 mb-3">
                    <div class="text-[10px] font-black uppercase text-rose-700"><i class="fa-solid fa-circle-xmark mr-1"></i> Bukti Ditolak (member diminta upload ulang)</div>
                    ${info}
                </div>`;
            }
            return '';
        }

        // ===== KIRIM SISA DP (setelah INSTALASI SELESAI) =====
        function adminSettleBlock(o) {
            const sisa = Math.max(0, Number(o.total || 0) - Number(o.dpAmount || 0));
            if ((o.status || '') !== 'DP' || (o.trackingStatus || '') !== 'SELESAI RAMPUNG' || sisa <= 0.5) return '';
            const sisaTxt = 'Rp ' + sisa.toLocaleString('id-ID');
            if (Number(o.settleSent) === 1) {
                return `<div class="bg-sky-50 border border-sky-200 rounded-xl p-3 mb-3 text-[10px] font-bold text-sky-700">
                    <i class="fa-solid fa-paper-plane mr-1"></i> Tagihan sisa DP ${sisaTxt} sudah dikirim ke member${o.settleSentAt ? ' (' + o.settleSentAt + ')' : ''}. Menunggu pembayaran member; APPROVED bukti TF untuk otomatis menjadikan LUNAS.
                </div>`;
            }
            const dpOk = (o.payStatus || '').toUpperCase() === 'DITERIMA' && o.payStage === 'DP';
            if (!dpOk) {
                return `<div class="bg-slate-100 border border-slate-200 rounded-xl p-3 mb-3 text-[10px] font-bold text-slate-500">
                    <i class="fa-solid fa-circle-info mr-1"></i> Sisa DP ${sisaTxt}. Tombol KIRIM SISA DP muncul setelah bukti TF DP member di-APPROVED.
                </div>`;
            }
            return `<div class="bg-violet-50 border border-violet-200 rounded-xl p-3 mb-3">
                <div class="text-[10px] font-black uppercase text-violet-700"><i class="fa-solid fa-screwdriver-wrench mr-1"></i> Instalasi selesai &mdash; ada kekurangan bayar</div>
                <div class="text-sm font-black text-violet-800 mt-1">Sisa DP: ${sisaTxt}</div>
                <button onclick="sendRemainingDp('${o.id}')" class="w-full mt-2 text-xs bg-violet-600 hover:bg-violet-500 text-white py-2 rounded-xl font-black shadow-md shadow-violet-600/20"><i class="fa-solid fa-paper-plane mr-1"></i> KIRIM SISA DP</button>
            </div>`;
        }

        async function sendRemainingDp(id) {
            if (!confirm('Kirim tagihan sisa DP ke riwayat invoice member?')) return;
            try {
                const res = await fetch('admin.php?api=send_remaining_dp', { method: 'POST', body: JSON.stringify({ id: id }) });
                const json = await res.json();
                showToast(json.success ? 'Sisa DP terkirim ke invoice member' : (json.msg || 'Gagal mengirim sisa DP'));
            } catch (e) {
                showToast('Kesalahan jaringan saat mengirim sisa DP');
            }
            loadData();
        }

        async function reviewOrderPayment(id, decision) {
            const label = decision === 'DITERIMA' ? 'APPROVE pembayaran ini?' : 'TOLAK bukti pembayaran ini? Member akan diminta upload ulang.';
            if (!confirm(label)) return;
            try {
                const res = await fetch('admin.php?api=review_order_payment', { method: 'POST', body: JSON.stringify({ id: id, decision: decision }) });
                const json = await res.json();
                if (json.success) {
                    showToast(decision === 'DITERIMA' ? (json.lunas ? 'Pembayaran disetujui — invoice otomatis LUNAS' : 'Pembayaran disetujui — status member berubah hijau') : 'Bukti pembayaran ditolak');
                } else {
                    showToast(json.msg || 'Gagal memproses pembayaran');
                }
            } catch (e) {
                showToast('Kesalahan jaringan saat memproses pembayaran');
            }
            loadData();
        }

        function compressImageFile(file, maxSize, quality) {
            return new Promise((resolve, reject) => {
                const reader = new FileReader();
                reader.onerror = () => reject(new Error('Gagal membaca file'));
                reader.onload = () => {
                    const img = new Image();
                    img.onerror = () => reject(new Error('File bukan gambar yang valid'));
                    img.onload = () => {
                        let w = img.width, h = img.height;
                        if (w > h) { if (w > maxSize) { h = Math.round(h * maxSize / w); w = maxSize; } }
                        else { if (h > maxSize) { w = Math.round(w * maxSize / h); h = maxSize; } }
                        const canvas = document.createElement('canvas');
                        canvas.width = w; canvas.height = h;
                        const ctx = canvas.getContext('2d');
                        ctx.fillStyle = '#ffffff'; ctx.fillRect(0, 0, w, h);
                        ctx.drawImage(img, 0, 0, w, h);
                        resolve(canvas.toDataURL('image/jpeg', quality));
                    };
                    img.src = reader.result;
                };
                reader.readAsDataURL(file);
            });
        }

        async function uploadProofAdmin(id, inputElement) {
            if (!(inputElement.files && inputElement.files[0])) return;
            const file = inputElement.files[0];
            try {
                showToast('Mengunggah bukti transfer...');
                const dataUrl = await compressImageFile(file, 1280, 0.75);
                const res = await fetch('admin.php?api=upload_order_proof', { method: 'POST', body: JSON.stringify({ id: id, proofImage: dataUrl }) });
                let json = null;
                try { json = await res.json(); } catch (e) { json = null; }
                if (json && json.success) {
                    const o = orders.find(x => x.id === id);
                    if (o) o.hasProof = true;
                    showToast('Bukti Transfer Berhasil Diunggah');
                } else {
                    showToast('Gagal upload bukti: ' + ((json && json.msg) ? json.msg : 'respon server tidak valid / file terlalu besar'));
                }
            } catch (e) {
                showToast('Gagal upload bukti: ' + (e && e.message ? e.message : 'kesalahan jaringan'));
            }
            inputElement.value = '';
            loadData();
        }

        async function updateOrderStatus(id, statusVal) {
            let o = orders.find(x => x.id === id); if(!o) return;
            o.status = statusVal;
            if(statusVal === 'DP' && !o.dpAmount) o.dpAmount = o.total / 2;
            if(statusVal === 'DP') { o.remainingAmount = o.total - (o.dpAmount || 0); } else { o.remainingAmount = 0; }
            await fetch('admin.php?api=save_order', { method: 'POST', body: JSON.stringify(o) });
            showToast('Status Pembayaran Diperbarui'); loadData();
        }

        async function updateTrackingStatus(id, trackingVal) {
            let o = orders.find(x => x.id === id); if(!o) return;
            o.trackingStatus = trackingVal;
            await fetch('admin.php?api=save_order', { method: 'POST', body: JSON.stringify(o) });
            showToast('Progress Tracking Diperbarui'); loadData();
        }

        async function updateDpAmount(id, val) {
            let o = orders.find(x => x.id === id); if(!o) return;
            o.dpAmount = parseFloat(val) || 0;
            o.remainingAmount = Math.max(0, o.total - o.dpAmount);
            await fetch('admin.php?api=save_order', { method: 'POST', body: JSON.stringify(o) });
            showToast('Nominal DP Diperbarui'); loadData();
        }

        async function updateOrderWarranty(id, wType) {
            let o = orders.find(x => x.id === id); if(!o) return;
            o.warrantyType = wType;
            await fetch('admin.php?api=save_order', { method: 'POST', body: JSON.stringify(o) });
            showToast('Status Garansi Diperbarui'); loadData();
        }

        function sendWhatsAppPO(o) {
            let warrantyInfo = 'Garansi: Belum diatur';
            if (o.warrantyType === 'MAINTENANCE') {
                let baseDate = new Date();
                baseDate.setMonth(baseDate.getMonth() + 3);
                let dueDate = baseDate.toLocaleDateString('id-ID', {day: 'numeric', month: 'long', year: 'numeric'});
                warrantyInfo = `Jenis Garansi: *Maintenance 3 Bulan*\n  - Jatuh Tempo: *${dueDate}*`;
            } else if (o.warrantyType === 'PRODUK') {
                let baseDate = new Date();
                baseDate.setFullYear(baseDate.getFullYear() + 1);
                let dueDate = baseDate.toLocaleDateString('id-ID', {day: 'numeric', month: 'long', year: 'numeric'});
                warrantyInfo = `Jenis Garansi: *Produk 1 Tahun*\n  - Jatuh Tempo: *${dueDate}*`;
            } else if (o.warrantyType === 'FULL') {
                let dateMaint = new Date();
                dateMaint.setMonth(dateMaint.getMonth() + 3);
                let dueMaint = dateMaint.toLocaleDateString('id-ID', {day: 'numeric', month: 'long', year: 'numeric'});

                let dateProd = new Date();
                dateProd.setFullYear(dateProd.getFullYear() + 1);
                let dueProd = dateProd.toLocaleDateString('id-ID', {day: 'numeric', month: 'long', year: 'numeric'});

                warrantyInfo = `Jenis Garansi: *FULL GARANSI*\n  - Produk 1 Tahun (Jatuh Tempo: *${dueProd}*)\n  - Maintenance 3 Bulan (Jatuh Tempo: *${dueMaint}*)`;
            }

            let itemList = (o.items || []).map((item, idx) => `  ${idx+1}. *${item.name}* (x${item.qty}) - Rp ${(item.price * item.qty).toLocaleString('id-ID')}`).join('\n');
            let statusInfo = `Status Pembayaran: *${o.status}*\n  - Progress Instalasi: *${o.trackingStatus || 'PROSES'}*`;
            
            if(o.status === 'DP') {
                let sisaTagihan = o.remainingAmount !== undefined ? o.remainingAmount : (o.total - (o.dpAmount || 0));
                statusInfo += `\n  - Nominal DP: Rp ${Number(o.dpAmount || 0).toLocaleString('id-ID')}\n  - Sisa Tagihan: Rp ${Number(sisaTagihan).toLocaleString('id-ID')}`;
            }

            let message = `📦 *STRUK PEMESANAN - TEKUNIN CCTV* 📦\n` +
                          `==============================\n\n` +
                          `🆔 *DETAIL PESANAN*\n` +
                          `  - No Invoice : *${o.id}*\n` +
                          `  - Tanggal    : ${o.date}\n\n` +
                          `👤 *INFORMASI PELANGGAN*\n` +
                          `  - Nama   : *${o.name}*\n` +
                          `  - No HP  : ${o.phone}\n` +
                          `  - Alamat : ${o.address}\n\n` +
                          `🛒 *DAFTAR PRODUK*:\n` +
                          `${itemList}\n\n` +
                          `💳 *PEMBAYARAN & STATUS*\n` +
                          `  - Total Belanja : *Rp ${Number(o.total).toLocaleString('id-ID')}*\n` +
                          `  - Metode Bank   : ${o.bank}\n` +
                          `  - ${statusInfo}\n\n` +
                          `🛡️ *GARANSI*\n` +
                          `  - ${warrantyInfo}\n\n` +
                          `==============================\n` +
                          `_Terima kasih telah mempercayakan sistem keamanan Anda kepada Tekunin CCTV_`;

            window.open(`https://wa.me/6285872024756?text=${encodeURIComponent(message)}`, '_blank');
        }

        async function deleteOrder(id) {
            if(confirm('Hapus pesanan ini secara permanen?')) { 
                await fetch('admin.php?api=delete_order&id=' + id); 
                showToast('Pesanan Dihapus'); 
                loadData(); 
            }
        }

        function renderDataPelanggan() {
            const container = document.getElementById('admin-data-pelanggan-table');
            if(!container) return;
            let map = {};
            orders.forEach(o => {
                let k = o.phone || o.name;
                if(!map[k]) map[k] = { name: o.name, phone: o.phone, address: o.address, count: 0, total: 0, date: o.date };
                map[k].count++; map[k].total += o.total;
            });
            let list = Object.values(map);
            document.getElementById('data-pelanggan-count').innerText = `${list.length} Pelanggan`;
            if(!list.length) {
                container.innerHTML = '<div class="p-8 text-center text-slate-400 font-bold">Belum ada pelanggan terdata</div>';
                return;
            }
            container.innerHTML = list.map(c => `
                <div class="p-4 md:p-5 border-b border-slate-100 flex flex-col md:flex-row justify-between items-start md:items-center gap-4 hover:bg-slate-50 transition-colors">
                    <div class="w-full md:w-1/2">
                        <div class="font-black text-slate-900 text-base mb-0.5">${c.name}</div>
                        <div class="text-sky-600 text-xs font-bold mb-1.5"><i class="fa-brands fa-whatsapp mr-1"></i> ${c.phone}</div>
                        <div class="text-xs text-slate-500 line-clamp-1 bg-white p-2 rounded border border-slate-100"><i class="fa-solid fa-location-dot mr-1"></i> ${c.address}</div>
                    </div>
                    <div class="flex items-center justify-between md:justify-end gap-4 md:gap-8 w-full md:w-auto bg-slate-50 md:bg-transparent p-3 md:p-0 rounded-xl border border-slate-100 md:border-0">
                        <div class="text-center md:text-right">
                            <div class="text-[10px] font-extrabold text-slate-400 uppercase tracking-wider mb-1">Transaksi</div>
                            <div class="text-sm font-black text-sky-700 bg-sky-100 px-3 py-1 rounded-lg inline-block">${c.count}x Order</div>
                        </div>
                        <div class="text-right">
                            <div class="text-[10px] font-extrabold text-slate-400 uppercase tracking-wider mb-1">Total Belanja</div>
                            <div class="text-base font-black text-emerald-600">Rp ${Number(c.total).toLocaleString('id-ID')}</div>
                        </div>
                    </div>
                </div>
            `).join('');
        }

        function renderMembers() {
            const container = document.getElementById('admin-member-table');
            if(!container) return;
            document.getElementById('admin-member-count').innerText = `${members.length} Anggota`;
            if(!members.length) {
                container.innerHTML = '<div class="p-8 text-center text-slate-400 font-bold">Belum ada anggota</div>';
                return;
            }
            container.innerHTML = members.map(m => {
                let p = m.perms || {};
                let permStr = Object.entries(p).map(([k,v]) => `<span class="bg-indigo-50 border border-indigo-100 text-indigo-700 px-2.5 py-1 rounded-lg text-[10px] font-bold"><b>${k}</b> (R:${v.read?'✓':'-'} W:${v.write?'✓':'-'})</span>`).join('');
                return `
                    <div class="p-4 md:p-5 border-b border-slate-100 flex flex-col md:flex-row justify-between items-start md:items-center gap-4 hover:bg-slate-50 transition-colors">
                        <div>
                            <div class="font-black text-slate-900 text-base flex items-center gap-2"><div class="w-8 h-8 rounded-full bg-slate-200 flex items-center justify-center text-slate-500 text-xs"><i class="fa-solid fa-user"></i></div> ${m.username}</div>
                            <div class="flex flex-wrap gap-2 mt-3">${permStr || '<span class="text-xs font-bold text-slate-500 bg-slate-100 px-3 py-1 rounded-lg">Akses Penuh / Admin Utama</span>'}</div>
                        </div>
                        <button onclick="deleteMember('${m.id}')" class="px-5 py-2.5 bg-rose-50 hover:bg-rose-100 text-rose-600 rounded-xl text-xs font-bold w-full md:w-auto transition-colors"><i class="fa-solid fa-trash mr-1"></i> Hapus Akun</button>
                    </div>
                `;
            }).join('');
        }

        async function addNewMemberAccount() {
            let u = document.getElementById('new-member-username').value.trim();
            let p = document.getElementById('new-member-password').value.trim();
            if(!u || !p) return alert('Isi username dan password');

            let perms = {
                katalog: { read: document.getElementById('perm-prod-read').checked, write: document.getElementById('perm-prod-write').checked },
                livechat: { read: document.getElementById('perm-chat-read').checked, write: document.getElementById('perm-chat-write').checked },
                datapelanggan: { read: document.getElementById('perm-cust-read').checked, write: document.getElementById('perm-cust-write').checked },
                pesananpo: { read: document.getElementById('perm-order-read').checked, write: document.getElementById('perm-order-write').checked }
            };

            let mem = { id: 'MEM-' + Date.now(), username: u, password: p, perms: perms };
            await fetch('admin.php?api=save_member', { method: 'POST', body: JSON.stringify(mem) });
            showToast('Anggota Berhasil Ditambahkan');
            document.getElementById('new-member-username').value = ''; document.getElementById('new-member-password').value = ''; loadData();
        }

        async function deleteMember(id) {
            if(confirm('Hapus anggota ini secara permanen?')) { await fetch('admin.php?api=delete_member&id=' + id); showToast('Akun Dihapus'); loadData(); }
        }

        function loadChatList() {
            const list = document.getElementById('customer-chat-list');
            if(!list) return;
            if(!sessions.length) {
                list.innerHTML = '<div class="p-6 text-center text-xs text-slate-400 font-bold">Belum ada sesi chat aktif</div>';
                return;
            }
            list.innerHTML = sessions.map(s => `
                <div onclick="selectChat('${s.session_id}')" class="p-4 cursor-pointer hover:bg-slate-50 border-b transition-colors ${activeAdminChatCustomer==s.session_id?'bg-sky-50/70 border-l-4 border-l-sky-500':'border-l-4 border-l-transparent'}">
                    <div class="flex justify-between font-bold text-xs mb-1.5 items-center">
                        <span class="text-sky-800 font-black"><i class="fa-solid fa-user-circle mr-1 text-slate-400"></i> ${s.session_id}</span>
                        <span class="px-2 py-0.5 rounded-full text-[9px] uppercase tracking-wider ${s.is_bot_active==1?'bg-emerald-100 text-emerald-700':'bg-orange-100 text-orange-700'}">${s.is_bot_active==1?'Bot':'Admin'}</span>
                    </div>
                    <div class="text-[11px] text-slate-500 truncate font-medium bg-white p-1.5 rounded border border-slate-100">${s.last_message || '...'}</div>
                </div>
            `).join('');
        }

        async function selectChat(id) {
            activeAdminChatCustomer = id;
            document.getElementById('header-active-chat').innerHTML = `<b class="text-sky-600 bg-sky-50 px-3 py-1 rounded-lg border border-sky-100">${id}</b>`;
            document.getElementById('btn-return-bot').classList.remove('hidden');
            await fetch('admin.php?api=takeover', { method: 'POST', body: JSON.stringify({session_id: id, admin_name: loggedInAdminName}) });
            loadData(); loadChatHistory(id);
        }

        async function returnToBotSession() {
            if(!activeAdminChatCustomer) return;
            await fetch('admin.php?api=return_bot', { method: 'POST', body: JSON.stringify({session_id: activeAdminChatCustomer}) });
            activeAdminChatCustomer = null;
            document.getElementById('header-active-chat').innerText = "Pilih sesi pelanggan";
            document.getElementById('btn-return-bot').classList.add('hidden');
            document.getElementById('admin-chat-messages').innerHTML = '<div class="text-center text-slate-400 my-auto h-full flex items-center justify-center">Pilih salah satu sesi pelanggan.</div>';
            loadData(); showToast('Dikembalikan ke Bot AI');
        }

        async function loadChatHistory(id) {
            if(!id) return;
            let res = await fetch('admin.php?api=chat_history&channel=live&session_id=' + encodeURIComponent(id));
            let json = await res.json();
            const box = document.getElementById('admin-chat-messages');
            if(!box) return;
            box.innerHTML = json.messages.map(m => {
                if(m.sender == 'system') return `<div class="text-center text-[10px] text-slate-400 font-bold my-2 flex items-center justify-center gap-2"><span class="h-px w-8 bg-slate-200"></span> ${m.text} <span class="h-px w-8 bg-slate-200"></span></div>`;
                let style = m.sender == 'customer' ? 'bg-white border border-slate-200 text-slate-800' : (m.sender == 'bot' ? 'bg-indigo-50 border border-indigo-100 text-indigo-900 ml-auto' : 'bg-sky-500 text-white ml-auto text-right shadow-md');
                let align = m.sender == 'customer' ? 'items-start' : 'items-end';
                return `<div class="flex flex-col ${align} w-full"><div class="p-3 rounded-2xl ${style} max-w-[85%] relative" style="white-space: pre-wrap;"><b class="block font-bold text-[9px] opacity-70 mb-1 uppercase tracking-wider">${m.sender}</b>${m.text}</div></div>`;
            }).join('');
            box.scrollTop = box.scrollHeight;
        }

        async function sendAdminChatMessage() {
            const input = document.getElementById('admin-chat-input');
            if(!input.value.trim() || !activeAdminChatCustomer) return;
            let text = input.value.trim(); input.value = '';
            await fetch('admin.php?api=send_chat', { method: 'POST', body: JSON.stringify({ session_id: activeAdminChatCustomer, message: text, sender: loggedInAdminName, channel: 'live' }) });
            loadChatHistory(activeAdminChatCustomer);
        }

        function openImageModal(imgSrc) {
            // Support both direct base64/URL and order-id lookup
            if (!imgSrc) return;
            // Jika yang dikirim adalah order ID (bukan data:image)
            if (typeof imgSrc === 'string' && !imgSrc.startsWith('data:') && !imgSrc.startsWith('http') && !imgSrc.startsWith('blob:')) {
                const order = (typeof orders !== 'undefined') ? orders.find(o => o.id === imgSrc) : null;
                if (order && order.proofImage) {
                    imgSrc = order.proofImage;
                } else {
                    showToast('Bukti transfer tidak ditemukan');
                    return;
                }
            }
            const img = document.getElementById('modal-preview-img');
            const modal = document.getElementById('image-modal');
            if (img) img.src = imgSrc;
            if (modal) modal.style.display = 'flex';
        }

        async function openImageModalByOrderId(orderId) {
            const order = (typeof orders !== 'undefined') ? orders.find(o => o.id === orderId) : null;
            let img = order ? (order.proofImage || order.proof_image) : null;
            if (!img) {
                try {
                    const res = await fetch('admin.php?api=get_order_proof&id=' + encodeURIComponent(orderId), { cache: 'no-store' });
                    const json = await res.json();
                    if (json && json.success && json.image) img = json.image;
                } catch (e) {}
            }
            if (!img) {
                showToast('Bukti transfer tidak ditemukan');
                return;
            }
            openImageModal(img);
        }

        function closeImageModal() {
            const modal = document.getElementById('image-modal');
            const img = document.getElementById('modal-preview-img');
            if (modal) modal.style.display = 'none';
            if (img) img.src = '';
        }
        
        function showToast(message) {
            const container = document.getElementById('toast-container');
            if(!container) return;
            const toast = document.createElement('div');
            toast.className = `glass-dark text-white px-5 py-3.5 rounded-2xl shadow-2xl flex items-center gap-4 animate-slide-in-right border border-white/10 w-full md:w-auto pointer-events-auto`;
            toast.innerHTML = `<div class="text-xs font-bold">${message}</div>`;
            container.appendChild(toast);
            setTimeout(() => toast.remove(), 3000);
        }


        /* ===== STOK BARANG ===== */
        let stockItems = []; let stockHistory = []; let stockRev = '';

        function tkStockEsc(v) {
            return String(v == null ? '' : v).replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
        }
        function tkStockNum(n) {
            return (Number(n) || 0).toLocaleString('id-ID', { maximumFractionDigits: 2 });
        }

        async function loadStock(silent) {
            try {
                const res = await fetch('admin.php?api=stock_list&rev=' + encodeURIComponent(silent ? stockRev : ''), { cache: 'no-store' });
                if (!res.ok) throw new Error('HTTP ' + res.status);
                const json = await res.json();
                if (!json.success) throw new Error(json.msg || 'Gagal memuat stok');
                if (json.unchanged) return;
                stockRev = json.rev || '';
                stockItems = json.stock || [];
                stockHistory = json.history || [];
                renderStockTable();
                renderStockHistory();
                refreshStockSuggestions();
            } catch (e) {
                if (!silent) showToast('❌ Gagal memuat stok: ' + e.message);
            }
        }

        // Realtime: selama tab Stok Barang terbuka, data teknisi otomatis masuk tiap 3 detik
        setInterval(() => {
            const tab = document.getElementById('admin-tab-stok');
            if (tab && !tab.classList.contains('hidden') && !document.hidden) loadStock(true);
        }, 3000);

        function refreshStockSuggestions() {
            const uniq = (arr) => [...new Set(arr.map(x => (x || '').trim()).filter(Boolean))].sort();
            const fill = (id, vals) => {
                const dl = document.getElementById(id);
                if (dl) dl.innerHTML = vals.map(v => `<option value="${tkStockEsc(v)}">`).join('');
            };
            fill('stok-grup-list', uniq(stockItems.map(x => x.grup)));
            fill('stok-brand-list', uniq(stockItems.map(x => x.brand)));
        }

        function renderStockTable() {
            const body = document.getElementById('stok-body');
            if (!body) return;
            const q = (document.getElementById('stok-search').value || '').toLowerCase().trim();
            const rows = stockItems.filter(x => !q || [x.sn, x.grup, x.brand, x.satuan].join(' ').toLowerCase().includes(q));
            document.getElementById('stok-count').innerText = rows.length + ' Data';
            if (rows.length === 0) {
                body.innerHTML = '<tr><td colspan="6" class="px-5 py-8 text-center text-xs text-slate-400">Belum ada data stok.</td></tr>';
                return;
            }
            body.innerHTML = rows.map(x => `
                <tr class="hover:bg-slate-50/70">
                    <td class="px-5 py-3 font-bold text-slate-900 whitespace-nowrap">${tkStockEsc(x.sn)}</td>
                    <td class="px-5 py-3">${tkStockEsc(x.grup) || '-'}</td>
                    <td class="px-5 py-3 text-right font-extrabold ${x.jumlah <= 0 ? 'text-rose-600' : 'text-slate-900'}">${tkStockNum(x.jumlah)}</td>
                    <td class="px-5 py-3">${tkStockEsc(x.satuan) || '-'}</td>
                    <td class="px-5 py-3">${tkStockEsc(x.brand) || '-'}</td>
                    <td class="px-5 py-3 text-right whitespace-nowrap">
                        <button type="button" data-sn="${tkStockEsc(x.sn)}" onclick="stockPrefill(this.dataset.sn)" title="Isi form dengan data ini" class="px-3 py-1.5 bg-sky-50 hover:bg-sky-100 text-sky-700 rounded-lg text-[11px] font-bold"><i class="fa-solid fa-pen"></i></button>
                        <button type="button" data-sn="${tkStockEsc(x.sn)}" onclick="deleteStock(this.dataset.sn)" title="Hapus dari stok" class="px-3 py-1.5 bg-rose-50 hover:bg-rose-100 text-rose-600 rounded-lg text-[11px] font-bold"><i class="fa-solid fa-trash"></i></button>
                    </td>
                </tr>`).join('');
        }

        function renderStockHistory() {
            const body = document.getElementById('stok-hist-body');
            if (!body) return;
            const q = (document.getElementById('stok-hist-search').value || '').toLowerCase().trim();
            const f = (document.getElementById('stok-hist-filter') || {}).value || '';
            const kindOf = o => { o = String(o || 'Admin'); return o.indexOf('INST-') === 0 ? 'INST' : (o.indexOf('MT-') === 0 ? 'MT' : 'MANUAL'); };
            const rows = stockHistory.filter(x => (!f || kindOf(x.oleh) === f) && (!q || [x.sn, x.grup, x.brand, x.satuan, x.tipe, x.oleh].join(' ').toLowerCase().includes(q)));
            document.getElementById('stok-hist-count').innerText = rows.length + ' Data';
            if (rows.length === 0) {
                body.innerHTML = '<tr><td colspan="8" class="px-5 py-8 text-center text-xs text-slate-400">Belum ada riwayat transaksi.</td></tr>';
                return;
            }
            body.innerHTML = rows.map(x => {
                const masuk = x.tipe === 'MASUK';
                return `
                <tr class="hover:bg-slate-50/70">
                    <td class="px-5 py-3 text-xs text-slate-500 whitespace-nowrap">${tkStockEsc(x.created_at)}</td>
                    <td class="px-5 py-3"><span class="text-[10px] font-extrabold px-2.5 py-1 rounded-full ${masuk ? 'bg-emerald-100 text-emerald-700' : 'bg-rose-100 text-rose-700'}">${masuk ? 'MASUK' : 'KELUAR'}</span></td>
                    <td class="px-5 py-3 font-bold text-slate-900 whitespace-nowrap">${tkStockEsc(x.sn)}</td>
                    <td class="px-5 py-3">${tkStockEsc(x.grup) || '-'}</td>
                    <td class="px-5 py-3 text-right font-extrabold ${masuk ? 'text-emerald-600' : 'text-rose-600'}">${masuk ? '+' : '-'}${tkStockNum(x.jumlah)}</td>
                    <td class="px-5 py-3">${tkStockEsc(x.satuan) || '-'}</td>
                    <td class="px-5 py-3">${tkStockEsc(x.brand) || '-'}</td>
                    <td class="px-5 py-3 text-xs font-bold whitespace-nowrap">${(() => { const o = x.oleh || 'Admin', k = kindOf(o); return k === 'MANUAL' ? `<span class="${o === 'Admin' ? 'text-slate-500' : 'text-sky-700'}">${tkStockEsc(o)}</span>` : `<span class="text-[10px] font-extrabold px-2.5 py-1 rounded-full ${k === 'INST' ? 'bg-sky-100 text-sky-700' : 'bg-rose-100 text-rose-700'}">${tkStockEsc(o)}</span>`; })()}</td>
                </tr>`;
            }).join('');
        }

        function stockPrefill(sn) {
            const it = stockItems.find(x => x.sn === sn);
            if (!it) return;
            document.getElementById('stok-sn').value = it.sn;
            document.getElementById('stok-grup').value = it.grup || '';
            document.getElementById('stok-satuan').value = it.satuan || '';
            document.getElementById('stok-brand').value = it.brand || '';
            document.getElementById('stok-jumlah').value = '';
            document.getElementById('stok-jumlah').focus();
            const sc = document.getElementById('admin-scroll'); if (sc) sc.scrollTo({ top: 0, behavior: 'smooth' });
        }

        function resetStockForm() {
            ['stok-sn', 'stok-grup', 'stok-jumlah', 'stok-satuan', 'stok-brand'].forEach(id => { document.getElementById(id).value = ''; });
            document.getElementById('stok-tipe').value = 'MASUK';
        }

        async function saveStock() {
            const payload = {
                sn: document.getElementById('stok-sn').value.trim(),
                grup: document.getElementById('stok-grup').value.trim(),
                jumlah: parseFloat(document.getElementById('stok-jumlah').value),
                satuan: document.getElementById('stok-satuan').value.trim(),
                brand: document.getElementById('stok-brand').value.trim(),
                tipe: document.getElementById('stok-tipe').value
            };
            if (!payload.sn) { showToast('SN wajib diisi'); return; }
            if (!(payload.jumlah > 0)) { showToast('Jumlah harus lebih dari 0'); return; }
            const btn = document.getElementById('stok-save-btn');
            btn.disabled = true;
            try {
                const res = await fetch('admin.php?api=stock_save', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload)
                });
                const json = await res.json();
                if (json.success) {
                    showToast('✅ Transaksi stok tersimpan');
                    resetStockForm();
                    await loadStock();
                } else {
                    showToast('❌ ' + (json.msg || 'Gagal menyimpan'));
                }
            } catch (e) {
                showToast('❌ Gagal menyimpan: ' + e.message);
            } finally {
                btn.disabled = false;
            }
        }

        async function deleteStock(sn) {
            if (!confirm('Hapus SN "' + sn + '" dari stok?\nSisa stok akan dicatat sebagai transaksi KELUAR di riwayat.')) return;
            try {
                const res = await fetch('admin.php?api=stock_delete', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ sn: sn })
                });
                const json = await res.json();
                if (json.success) { showToast('🗑️ Stok dihapus'); await loadStock(); }
                else showToast('❌ ' + (json.msg || 'Gagal menghapus'));
            } catch (e) {
                showToast('❌ Gagal menghapus: ' + e.message);
            }
        }

        /* ----- Export Stok Barang (Excel / CSV) ----- */
        function exportStock(fmt) {
            // Mengikuti kotak pencarian: kosong = semua data, terisi = hanya data yang tampil
            const q = (document.getElementById('stok-search').value || '').toLowerCase().trim();
            const rows = stockItems.filter(x => !q || [x.sn, x.grup, x.brand, x.satuan].join(' ').toLowerCase().includes(q));
            if (rows.length === 0) { showToast('⚠️ Tidak ada data stok untuk di-export'); return; }
            const head = ['SN', 'Grup', 'Jumlah', 'Satuan', 'Brand'];
            const d = new Date(), pad = n => String(n).padStart(2, '0');
            const stamp = d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-' + pad(d.getDate()) + '_' + pad(d.getHours()) + pad(d.getMinutes());
            let blob, name;
            if (fmt === 'csv') {
                const cell = v => '"' + String(v == null ? '' : v).replace(/"/g, '""') + '"';
                const lines = [head.map(cell).join(',')].concat(rows.map(x => [x.sn, x.grup, x.jumlah, x.satuan, x.brand].map(cell).join(',')));
                blob = new Blob(['\uFEFF' + lines.join('\r\n')], { type: 'text/csv;charset=utf-8;' });
                name = 'stok-barang_' + stamp + '.csv';
            } else {
                const txt = v => '<td style="mso-number-format:\'\\@\'">' + tkStockEsc(v == null ? '' : v) + '</td>';
                const html = '<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel"><head><meta charset="UTF-8"></head><body><table border="1">'
                    + '<thead><tr>' + head.map(h => '<th style="background:#e2e8f0">' + h + '</th>').join('') + '</tr></thead><tbody>'
                    + rows.map(x => '<tr>' + txt(x.sn) + txt(x.grup) + '<td>' + x.jumlah + '</td>' + txt(x.satuan) + txt(x.brand) + '</tr>').join('')
                    + '</tbody></table></body></html>';
                blob = new Blob(['\uFEFF' + html], { type: 'application/vnd.ms-excel;charset=utf-8;' });
                name = 'stok-barang_' + stamp + '.xls';
            }
            const a = document.createElement('a');
            a.href = URL.createObjectURL(blob);
            a.download = name;
            document.body.appendChild(a); a.click(); a.remove();
            setTimeout(() => URL.revokeObjectURL(a.href), 1000);
            showToast('✅ Export ' + rows.length + ' data stok berhasil');
        }

        /* ----- Import Stok Barang (CSV / JSON / SQL) ----- */
        let stockImportRows = [];

        function tkParseCSV(text) {
            text = text.replace(/^\uFEFF/, '');
            // Deteksi pemisah (koma / titik-koma / tab) dari baris pertama
            const first = text.split(/\r?\n/)[0] || '';
            const cnt = c => (first.match(new RegExp(c === '\t' ? '\t' : '\\' + c, 'g')) || []).length;
            const delim = [',', ';', '\t'].sort((a, b) => cnt(b) - cnt(a))[0];
            const rows = []; let row = [], cur = '', q = false;
            for (let i = 0; i < text.length; i++) {
                const ch = text[i];
                if (q) {
                    if (ch === '"') { if (text[i + 1] === '"') { cur += '"'; i++; } else q = false; }
                    else cur += ch;
                } else if (ch === '"') q = true;
                else if (ch === delim) { row.push(cur); cur = ''; }
                else if (ch === '\n' || ch === '\r') {
                    if (ch === '\r' && text[i + 1] === '\n') i++;
                    row.push(cur); cur = '';
                    if (row.some(c => c.trim() !== '')) rows.push(row);
                    row = [];
                } else cur += ch;
            }
            row.push(cur);
            if (row.some(c => c.trim() !== '')) rows.push(row);
            return rows;
        }

        // Ubah matriks (baris pertama = header) menjadi objek stok; header dikenali longgar
        function tkRowsToStock(matrix) {
            if (!matrix.length) return [];
            const norm = h => String(h || '').toLowerCase().replace(/[^a-z]/g, '');
            const alias = { sn: 'sn', serialnumber: 'sn', serial: 'sn', noseri: 'sn', kode: 'sn', grup: 'grup', group: 'grup', kategori: 'grup', jumlah: 'jumlah', qty: 'jumlah', stok: 'jumlah', stock: 'jumlah', quantity: 'jumlah', satuan: 'satuan', unit: 'satuan', brand: 'brand', merk: 'brand', merek: 'brand' };
            const heads = matrix[0].map(h => alias[norm(h)] || '');
            let body = matrix.slice(1), map = heads;
            if (!heads.includes('sn')) { map = ['sn', 'grup', 'jumlah', 'satuan', 'brand']; body = matrix; } // tanpa header: urutan SN, Grup, Jumlah, Satuan, Brand
            return body.map(r => { const o = {}; map.forEach((k, i) => { if (k && o[k] === undefined) o[k] = r[i] == null ? '' : String(r[i]).trim(); }); return o; });
        }

        function tkParseJSONStock(text) {
            let data = JSON.parse(text.replace(/^\uFEFF/, ''));
            if (!Array.isArray(data)) {
                // dukung {stock:[...]} / {data:[...]} / hasil export phpMyAdmin [{type:'table', data:[...]}]
                data = data.stock || data.data || data.rows || data.items || [];
            }
            if (data.length && data[0] && data[0].type === 'table' && Array.isArray(data[0].data)) {
                const t = data.find(x => x.type === 'table' && /tk_stock$/.test(x.name || '')) || data.find(x => x.type === 'table');
                data = t ? t.data : [];
            }
            const keys = ['sn', 'grup', 'jumlah', 'satuan', 'brand'];
            return data.filter(x => x && typeof x === 'object').map(x => {
                const low = {}; Object.keys(x).forEach(k => low[k.toLowerCase()] = x[k]);
                const o = {}; keys.forEach(k => { o[k] = low[k] == null ? '' : String(low[k]).trim(); });
                return o;
            });
        }

        // Parser SQL: hanya membaca pernyataan INSERT ... VALUES (...) ke tabel stok. SQL tidak pernah dijalankan di server.
        function tkParseSQLStock(text) {
            text = text.replace(/^\uFEFF/, '').replace(/\/\*[\s\S]*?\*\//g, '').replace(/^\s*(--|#).*$/gm, '');
            const out = []; const re = /INSERT\s+(?:IGNORE\s+)?INTO\s+((?:`?[\w$]+`?\.)?`?[\w$]+`?)\s*(?:\(([^)]*)\))?\s*VALUES\s*/gi;
            let m;
            while ((m = re.exec(text)) !== null) {
                const table = m[1].replace(/`/g, '').split('.').pop();
                const cols = m[2] ? m[2].split(',').map(c => c.replace(/[`'"\s]/g, '').toLowerCase()) : ['sn', 'grup', 'jumlah', 'satuan', 'brand'];
                if (!(table.toLowerCase() === 'tk_stock' || (cols.includes('sn') && !/history|log/i.test(table)))) { continue; }
                let i = re.lastIndex;
                for (;;) {
                    while (i < text.length && /\s|,/.test(text[i])) i++;
                    if (text[i] !== '(') break;
                    i++;
                    const vals = []; let cur = '', inStr = false, quote = '', wasStr = false;
                    for (; i < text.length; i++) {
                        const ch = text[i];
                        if (inStr) {
                            if (ch === '\\' && i + 1 < text.length) { const n = text[++i]; cur += ({ n: '\n', r: '\r', t: '\t', '0': '\0' })[n] ?? n; }
                            else if (ch === quote) { if (text[i + 1] === quote) { cur += quote; i++; } else inStr = false; }
                            else cur += ch;
                        } else if (ch === "'" || ch === '"') { inStr = true; quote = ch; wasStr = true; }
                        else if (ch === ',') { vals.push(wasStr || cur.trim().toUpperCase() !== 'NULL' ? cur.trim() : ''); cur = ''; wasStr = false; }
                        else if (ch === ')') { vals.push(wasStr || cur.trim().toUpperCase() !== 'NULL' ? cur.trim() : ''); i++; break; }
                        else cur += ch;
                    }
                    const o = {}; cols.forEach((c, k) => { if (['sn', 'grup', 'jumlah', 'satuan', 'brand'].includes(c)) o[c] = vals[k] == null ? '' : vals[k]; });
                    out.push(o);
                }
                re.lastIndex = i;
            }
            return out;
        }

        function stockImportPick(inp) {
            const f = inp.files && inp.files[0];
            inp.value = '';
            if (!f) return;
            if (f.size > 8 * 1024 * 1024) { showToast('⚠️ File terlalu besar (maks. 8 MB)'); return; }
            const rd = new FileReader();
            rd.onload = () => {
                try {
                    const text = String(rd.result || '');
                    const ext = (f.name.split('.').pop() || '').toLowerCase();
                    let rows;
                    if (ext === 'json') rows = tkParseJSONStock(text);
                    else if (ext === 'sql') rows = tkParseSQLStock(text);
                    else if (ext === 'csv' || ext === 'txt') rows = tkRowsToStock(tkParseCSV(text));
                    else { showToast('⚠️ Format harus CSV, JSON, atau SQL'); return; }
                    rows = rows.filter(r => (r.sn || '').trim() !== '');
                    if (!rows.length) { showToast('⚠️ Tidak ada data stok yang terbaca dari file ini'); return; }
                    stockImportRows = rows;
                    document.getElementById('stok-import-info').innerHTML = '<b>' + tkStockEsc(f.name) + '</b> — ' + rows.length + ' baris terbaca' + (rows.length > 50 ? ' (pratinjau 50 baris pertama)' : '') + '.';
                    document.getElementById('stok-import-preview').innerHTML = rows.slice(0, 50).map(r =>
                        '<tr><td class="px-3 py-1.5 font-bold">' + tkStockEsc(r.sn) + '</td><td class="px-3 py-1.5">' + tkStockEsc(r.grup || '') + '</td><td class="px-3 py-1.5 text-right">' + tkStockEsc(r.jumlah || '0') + '</td><td class="px-3 py-1.5">' + tkStockEsc(r.satuan || '') + '</td><td class="px-3 py-1.5">' + tkStockEsc(r.brand || '') + '</td></tr>').join('');
                    const m = document.getElementById('stok-import-modal');
                    m.classList.remove('hidden'); m.classList.add('flex');
                } catch (e) { showToast('❌ Gagal membaca file: ' + e.message); }
            };
            rd.onerror = () => showToast('❌ Gagal membuka file');
            rd.readAsText(f, 'UTF-8');
        }

        function stockImportClose() {
            const m = document.getElementById('stok-import-modal');
            m.classList.add('hidden'); m.classList.remove('flex');
            stockImportRows = [];
        }

        async function stockImportRun() {
            if (!stockImportRows.length) return;
            const mode = (document.querySelector('input[name="stok-import-mode"]:checked') || {}).value || 'add';
            if (mode === 'replace' && !confirm('Mode TIMPA akan mengubah jumlah stok SN yang ada menjadi sesuai file. Lanjutkan?')) return;
            const btn = document.getElementById('stok-import-go');
            const old = btn.innerHTML; btn.disabled = true; btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Mengimpor...';
            try {
                const res = await fetch('admin.php?api=stock_import', {
                    method: 'POST', headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ mode, rows: stockImportRows })
                });
                const json = await res.json();
                if (json.success) {
                    showToast('✅ Import selesai: ' + json.baru + ' baru, ' + json.diperbarui + ' diperbarui' + (json.sama ? ', ' + json.sama + ' tidak berubah' : '') + (json.dilewati ? ', ' + json.dilewati + ' dilewati' : ''));
                    if (json.errors && json.errors.length) console.warn('Baris dilewati:', json.errors);
                    stockImportClose();
                    await loadStock();
                } else { showToast('❌ ' + (json.msg || 'Import gagal')); }
            } catch (e) { showToast('❌ Gagal import: ' + e.message); }
            btn.disabled = false; btn.innerHTML = old;
        }

        /* ----- Hapus Riwayat Transaksi ----- */
        async function clearStockHistory() {
            if (stockHistory.length === 0) { showToast('ℹ️ Riwayat transaksi sudah kosong'); return; }
            if (!confirm('Hapus SELURUH riwayat transaksi stok?\nJumlah stok barang TIDAK berubah, tetapi riwayat yang dihapus tidak bisa dikembalikan.')) return;
            const btn = document.getElementById('stok-hist-clear-btn');
            if (btn) btn.disabled = true;
            try {
                const res = await fetch('admin.php?api=stock_history_clear', { method: 'POST' });
                const json = await res.json();
                if (json.success) { showToast('🗑️ ' + (json.deleted || 0) + ' riwayat dihapus'); await loadStock(); }
                else showToast('❌ ' + (json.msg || 'Gagal menghapus riwayat'));
            } catch (e) {
                showToast('❌ Gagal menghapus riwayat: ' + e.message);
            } finally {
                if (btn) btn.disabled = false;
            }
        }

        loadData();
    </script>
    <script>
        /* Perilaku shell sidebar (tidak mengubah logika data / API) */
        (function () {
            var admin = document.getElementById('admin-section');
            var isDesktop = function () { return window.matchMedia('(min-width: 1024px)').matches; };

            window.tkToggleSidebar = function () {
                if (isDesktop()) {
                    var c = admin.classList.toggle('tk-collapsed');
                    try { localStorage.setItem('tk_sb_collapsed', c ? '1' : '0'); } catch (e) {}
                } else {
                    admin.classList.toggle('tk-drawer');
                }
            };
            window.tkCloseDrawer = function () { admin.classList.remove('tk-drawer'); };
            window.tkGoSection = function (tab, id) {
                switchAdminTab(tab);
                setTimeout(function () {
                    var el = document.getElementById(id);
                    if (el) el.scrollIntoView({ behavior: 'smooth', block: 'start' });
                }, 60);
            };
            window.tkTogglePass = function (btn) {
                var i = document.getElementById('login-pass');
                var show = i.type === 'password';
                i.type = show ? 'text' : 'password';
                btn.setAttribute('aria-label', show ? 'Sembunyikan password' : 'Tampilkan password');
                btn.firstElementChild.className = show ? 'fa-solid fa-eye-slash' : 'fa-solid fa-eye';
            };

            try { if (localStorage.getItem('tk_sb_collapsed') === '1') admin.classList.add('tk-collapsed'); } catch (e) {}
            document.addEventListener('keydown', function (e) { if (e.key === 'Escape') admin.classList.remove('tk-drawer'); });

            function syncUser() {
                var n = 'Admin';
                try { n = sessionStorage.getItem('tekunin_admin_user') || 'Admin'; } catch (e) {}
                var nameEl = document.getElementById('tk-user-name');
                var avEl = document.getElementById('tk-avatar');
                if (nameEl) nameEl.textContent = n;
                if (avEl) avEl.textContent = (n.trim().charAt(0) || 'A').toUpperCase();
            }
            new MutationObserver(syncUser).observe(admin, { attributes: true, attributeFilter: ['class'] });
            syncUser();

            function pad(n) { return n < 10 ? '0' + n : '' + n; }
            function tick() {
                var d = new Date();
                var t = pad(d.getHours()) + ':' + pad(d.getMinutes()) + ':' + pad(d.getSeconds());
                var te = document.getElementById('tk-clock-time');
                var de = document.getElementById('tk-clock-date');
                if (te) te.textContent = t;
                if (de) { try { de.textContent = d.toLocaleDateString('id-ID', { weekday: 'long', day: 'numeric', month: 'short', year: 'numeric' }); } catch (e) {} }
                var tcs = document.querySelectorAll('.tk-tc');
                for (var i = 0; i < tcs.length; i++) tcs[i].textContent = t;
            }
            tick();
            setInterval(tick, 1000);
        })();
    </script>
</body>
</html>