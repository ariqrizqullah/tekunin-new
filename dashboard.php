<?php
ob_start();
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

mysqli_report(MYSQLI_REPORT_OFF); 

$db_host = "127.0.0.1";
$db_user = "tekh7457_admin";       
$db_pass = "Tekunin2026";   
$db_name = "tekh7457_chat";       

$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
if ($conn->connect_error) die("Koneksi Database Gagal: " . $conn->connect_error);

// 1. BUAT ATAU PERBAIKI STRUKTUR TABEL OTOMATIS
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

$conn->query("CREATE TABLE IF NOT EXISTS tk_chats (
    id INT AUTO_INCREMENT PRIMARY KEY,
    session_id VARCHAR(50),
    sender VARCHAR(50),
    message LONGTEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

$conn->query("CREATE TABLE IF NOT EXISTS tk_chat_sessions (
    session_id VARCHAR(50) PRIMARY KEY,
    is_bot_active TINYINT DEFAULT 0,
    last_update TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
)");

$conn->query("ALTER TABLE tk_chats MODIFY COLUMN message LONGTEXT");

$columns_check = [
    "nama" => "VARCHAR(100)",
    "tingkat_masalah" => "VARCHAR(20)",
    "judul" => "VARCHAR(150)",
    "deskripsi" => "TEXT",
    "status" => "VARCHAR(50) DEFAULT 'MENUNGGU'",
    "proof_image" => "LONGTEXT"
];
foreach ($columns_check as $col => $definition) {
    $chk = $conn->query("SHOW COLUMNS FROM tk_tickets LIKE '$col'");
    if ($chk && $chk->num_rows == 0) {
        $conn->query("ALTER TABLE tk_tickets ADD COLUMN $col $definition");
    }
}

// Pastikan kolom SN Perangkat Lain / deskripsi tersedia (hanya menambah jika belum ada)
foreach ([
    ['tk_customer_accounts', 'other_sn', 'TEXT'],
    ['tk_technician_orders', 'other_sn', 'TEXT'],
    ['tk_technician_orders', 'proof_image_other', 'LONGTEXT'],
    ['tk_technician_orders', 'notes_json', 'LONGTEXT'],
] as $cc) {
    $chk_col = $conn->query("SHOW COLUMNS FROM {$cc[0]} LIKE '{$cc[1]}'");
    if ($chk_col && $chk_col->num_rows == 0) {
        @$conn->query("ALTER TABLE {$cc[0]} ADD COLUMN {$cc[1]} {$cc[2]}");
    }
}

// ===== HELPER: SN + DESKRIPSI + FOTO PERANGKAT YANG DIINPUT TEKNISI (untuk tampilan member) =====
function tk_split_sn($v) {
    $out = [];
    foreach (explode(',', (string)$v) as $p) {
        $p = trim($p);
        if ($p !== '') $out[] = $p;
    }
    return $out;
}
function tk_pure_sn($s) {
    return strtolower(trim(preg_replace('/\s*\(.*\)$/', '', (string)$s)));
}
function tk_member_condition_params($member) {
    return [(string)($member['customer_id'] ?? ''), (string)($member['name'] ?? '')];
}
function tk_build_devices($conn, $cid) {
    $labels = [
        'dvr'   => ['DVR / NVR', 'fa-server'],
        'cctv'  => ['Kamera CCTV', 'fa-video'],
        'hdd'   => ['Harddisk', 'fa-hard-drive'],
        'other' => ['SN Perangkat Lain', 'fa-plug'],
    ];
    $result = ['groups' => [], 'cable' => null];

    $member = null;
    $stmt = $conn->prepare("SELECT * FROM tk_customer_accounts WHERE customer_id = ?");
    if ($stmt) {
        $stmt->bind_param("s", $cid);
        $stmt->execute();
        $r = $stmt->get_result();
        $member = $r ? $r->fetch_assoc() : null;
        $stmt->close();
    }
    if (!$member) return $result;

    // Data teknisi untuk pelanggan ini (tanpa kolom foto agar ringan; foto dimuat terpisah saat dibutuhkan)
    $orders = [];
    list($m_cid, $m_name) = tk_member_condition_params($member);
    $stmt = $conn->prepare("SELECT id, wo_type, status, dvr_sn, cctv_sn, hdd_sn, other_sn, notes_json, kabel_qty, created_at FROM tk_technician_orders WHERE customer_id = ? OR (nama = ? AND nama <> '') ORDER BY created_at DESC LIMIT 60");
    if ($stmt) {
        $stmt->bind_param("ss", $m_cid, $m_name);
        $stmt->execute();
        $r = $stmt->get_result();
        if ($r) while ($row = $r->fetch_assoc()) $orders[] = $row;
        $stmt->close();
    }

    $lookup = ['dvr' => [], 'cctv' => [], 'hdd' => [], 'other' => []];
    foreach ($orders as $o) {
        $notes = json_decode((string)($o['notes_json'] ?? ''), true);
        if (!is_array($notes)) $notes = [];
        foreach (array_keys($lookup) as $g) {
            $units = (isset($notes[$g]) && is_array($notes[$g])) ? $notes[$g] : null;
            if ($units === null) {
                $units = [];
                foreach (tk_split_sn($o[$g . '_sn'] ?? '') as $sn_l) $units[] = ['sn' => $sn_l, 'desc' => '', 'p' => 0];
            }
            foreach ($units as $i => $u) {
                if (!is_array($u)) continue;
                $sn_u = trim((string)($u['sn'] ?? ''));
                if ($sn_u === '' || $sn_u === '-') continue;
                $key = tk_pure_sn($sn_u);
                if (isset($lookup[$g][$key])) continue; // data terbaru yang dipakai
                $lookup[$g][$key] = [
                    'desc'  => trim((string)($u['desc'] ?? '')),
                    'photo' => !empty($u['p']) ? ('?api=device_photo&wo=' . rawurlencode($o['id']) . '&field=' . $g . '&index=' . intval($i)) : '',
                    'date'  => $o['created_at'],
                ];
            }
        }
        if ($result['cable'] === null && strtoupper((string)($o['wo_type'] ?? '')) !== 'MAINTENANCE') {
            $kd = isset($notes['kabel']) ? trim((string)$notes['kabel']) : '';
            $km = intval($o['kabel_qty'] ?? 0);
            if ($km > 0 || $kd !== '') {
                $result['cable'] = ['meter' => $km, 'desc' => $kd, 'date' => $o['created_at']];
            }
        }
    }

    foreach ($labels as $g => $meta) {
        $items = [];
        foreach (tk_split_sn($member[$g . '_sn'] ?? '') as $sn) {
            $info = $lookup[$g][tk_pure_sn($sn)] ?? null;
            $items[] = [
                'sn'    => $sn,
                'desc'  => $info ? $info['desc'] : '',
                'photo' => $info ? $info['photo'] : '',
                'date'  => $info ? $info['date'] : '',
            ];
        }
        $result['groups'][] = ['key' => $g, 'label' => $meta[0], 'icon' => $meta[1], 'items' => $items];
    }
    return $result;
}
// Alias ID pelanggan (jika admin mengubah ID, sesi/cookie user lama tetap terhubung ke ID baru)
$conn->query("CREATE TABLE IF NOT EXISTS tk_customer_id_aliases (old_id VARCHAR(50) PRIMARY KEY, new_id VARCHAR(50), changed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP)");
function tk_resolve_member_id($conn, $cid) {
    $cid = (string)$cid;
    if ($cid === '') return $cid;
    $st = $conn->prepare("SELECT 1 FROM tk_customer_accounts WHERE customer_id = ? LIMIT 1");
    if ($st) {
        $st->bind_param("s", $cid);
        $st->execute();
        $r = $st->get_result();
        $exists = $r && $r->num_rows > 0;
        $st->close();
        if ($exists) return $cid;
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
function tk_json_flags() {
    return (defined('JSON_INVALID_UTF8_SUBSTITUTE') ? JSON_INVALID_UTF8_SUBSTITUTE : 0) | JSON_PARTIAL_OUTPUT_ON_ERROR;
}

// ===== HELPER: INVOICE & PEMBAYARAN (member <-> admin, realtime) =====
function tk_pay_banks() {
    return [
        'BCA'     => ['key' => 'BCA',     'label' => 'BCA (4730647951 A/N Sterlyght A. Moorrees)',     'name' => 'BCA',     'number' => '4730647951',    'holder' => 'Sterlyght A. Moorrees', 'cash' => false],
        'MANDIRI' => ['key' => 'MANDIRI', 'label' => 'Mandiri (1570010288620 A/N Sterlyght A. Moorrees)', 'name' => 'Mandiri', 'number' => '1570010288620', 'holder' => 'Sterlyght A. Moorrees', 'cash' => false],
        'TUNAI'   => ['key' => 'TUNAI',   'label' => 'TUNAI / BAYAR DI TEMPAT',                          'name' => 'TUNAI',   'number' => '',              'holder' => '',                      'cash' => true],
    ];
}
// Status pesanan (diatur admin) yang membuat member bisa menekan "Bayar Sekarang"
function tk_payable_statuses() { return ['DP']; }

function tk_ensure_order_columns($conn) {
    $cols = [
        'trackingStatus'  => "VARCHAR(50) DEFAULT 'PROSES'",
        'dpAmount'        => "DOUBLE DEFAULT 0",
        'remainingAmount' => "DOUBLE DEFAULT 0",
        'warrantyType'    => "VARCHAR(50) DEFAULT ''",
        'warrantyDueDate' => "VARCHAR(50) DEFAULT ''",
        'proofImage'      => "LONGTEXT",
        'bank'            => "VARCHAR(50) DEFAULT ''",
        'payStatus'       => "VARCHAR(20) DEFAULT ''",
        'payBank'         => "VARCHAR(150) DEFAULT ''",
        'payStage'        => "VARCHAR(30) DEFAULT ''",
        'payAmount'       => "DOUBLE DEFAULT 0",
        'payAt'           => "DATETIME NULL DEFAULT NULL",
        'payReviewedAt'   => "DATETIME NULL DEFAULT NULL",
        'settleSent'      => "TINYINT DEFAULT 0",
        'settleSentAt'    => "DATETIME NULL DEFAULT NULL",
        'orderSource'     => "VARCHAR(20) DEFAULT ''",
        'customerId'      => "VARCHAR(50) DEFAULT ''",
        'orderNote'       => "TEXT",
    ];
    foreach ($cols as $c => $def) {
        $chk = $conn->query("SHOW COLUMNS FROM tk_orders LIKE '$c'");
        if ($chk && $chk->num_rows == 0) { @$conn->query("ALTER TABLE tk_orders ADD COLUMN $c $def"); }
    }
}

// Ambil pesanan milik member (TANPA kolom foto agar ringan; hanya flag hasProof)
function tk_member_orders($conn, $member, $only_id = null) {
    $phone = (string)($member['phone'] ?? '');
    $name  = (string)($member['name'] ?? '');
    $out = [];
    if ($phone === '' && $name === '') return $out;
    $sql = "SELECT id, `date`, rawDate, name, phone, address, total, bank, status, trackingStatus, dpAmount, remainingAmount, warrantyType, warrantyDueDate, items, payStatus, payBank, payStage, payAmount, payAt, payReviewedAt, settleSent, settleSentAt, orderSource, orderNote, (CASE WHEN proofImage IS NOT NULL AND proofImage <> '' THEN 1 ELSE 0 END) AS hasProof FROM tk_orders WHERE ((phone = ? AND ? <> '') OR (name = ? AND ? <> ''))";
    if ($only_id !== null) $sql .= " AND id = ?";
    $sql .= " ORDER BY rawDate DESC";
    $st = $conn->prepare($sql);
    if (!$st) return $out;
    if ($only_id !== null) { $st->bind_param("sssss", $phone, $phone, $name, $name, $only_id); }
    else { $st->bind_param("ssss", $phone, $phone, $name, $name); }
    $st->execute();
    $r = $st->get_result();
    if ($r) {
        while ($row = $r->fetch_assoc()) {
            $row['items'] = json_decode((string)($row['items'] ?? ''), true) ?: [];
            $out[] = $row;
        }
    }
    $st->close();
    return $out;
}
// ===== HELPER: KATALOG PRODUK (terintegrasi dengan Katalog Produk admin) =====
function tk_product_available($p) {
    $b = strtolower((string)($p['badge'] ?? ''));
    foreach (['habis', 'kosong', 'sold', 'nonaktif'] as $w) { if ($b !== '' && strpos($b, $w) !== false) return false; }
    return true;
}
function tk_products_public($conn) {
    $out = [];
    $r = $conn->query("SELECT id, name, group_name, badge, price, image, description FROM tk_products ORDER BY group_name ASC, name ASC");
    if ($r) {
        while ($p = $r->fetch_assoc()) {
            $out[] = [
                'id'          => (string)$p['id'],
                'name'        => (string)$p['name'],
                'group'       => (string)($p['group_name'] ?? '') !== '' ? (string)$p['group_name'] : 'Lainnya',
                'badge'       => (string)($p['badge'] ?? ''),
                'price'       => (float)$p['price'],
                'image'       => (string)($p['image'] ?? ''),
                'description' => (string)($p['description'] ?? ''),
                'available'   => tk_product_available($p),
            ];
        }
    }
    return $out;
}
function tk_id_months() { return ['Jan','Feb','Mar','Apr','Mei','Jun','Jul','Agu','Sep','Okt','Nov','Des']; }

// Bagian DP dari total (nominal DP yang ditetapkan admin)
function tk_order_dp_part($o) {
    $total = (float)($o['total'] ?? 0);
    $dp = (float)($o['dpAmount'] ?? 0);
    return $dp > 0 ? min($dp, $total) : ($total / 2);
}
// Tahap tagihan saat ini: 'DP' (tagihan DP) atau 'SISA' (pelunasan sisa DP setelah instalasi selesai)
function tk_order_stage($o) {
    $status = strtoupper(trim((string)($o['status'] ?? '')));
    if ($status === 'DP' && intval($o['settleSent'] ?? 0) === 1) return 'SISA';
    return $status;
}
function tk_order_due($o) {
    $total = (float)($o['total'] ?? 0);
    if (strtoupper(trim((string)($o['status'] ?? ''))) === 'DP') {
        if (tk_order_stage($o) === 'SISA') return max(0, $total - tk_order_dp_part($o));
        return tk_order_dp_part($o);
    }
    return $total;
}
function tk_order_payable($o) {
    $status = strtoupper(trim((string)($o['status'] ?? '')));
    if (!in_array($status, tk_payable_statuses(), true)) return false;
    $stage = tk_order_stage($o);
    $ps = strtoupper(trim((string)($o['payStatus'] ?? '')));
    if ($ps === 'MENUNGGU') return false;
    if ($ps === 'DITERIMA' && strtoupper(trim((string)($o['payStage'] ?? ''))) === $stage) return false;
    return true;
}
function tk_order_public($o) {
    $status = strtoupper(trim((string)($o['status'] ?? '')));
    $total  = (float)($o['total'] ?? 0);
    $due    = tk_order_due($o);
    return [
        'id'          => (string)($o['id'] ?? ''),
        'date'        => (string)($o['date'] ?? ''),
        'rawDate'     => (string)($o['rawDate'] ?? ''),
        'name'        => (string)($o['name'] ?? ''),
        'phone'       => (string)($o['phone'] ?? ''),
        'address'     => (string)($o['address'] ?? ''),
        'items'       => is_array($o['items'] ?? null) ? $o['items'] : [],
        'total'       => $total,
        'status'      => $status,
        'statusLabel' => $status === 'DP' ? 'DP DIBAYAR' : ((string)($o['status'] ?? '') !== '' ? (string)$o['status'] : '-'),
        'tracking'    => (string)($o['trackingStatus'] ?? ''),
        'dpAmount'    => (float)($o['dpAmount'] ?? 0),
        'due'         => $due,
        'remaining'   => $status === 'DP' ? max(0, $total - tk_order_dp_part($o)) : ($status === 'LUNAS' ? 0 : $total),
        'dpPart'      => tk_order_dp_part($o),
        'stage'       => tk_order_stage($o),
        'settleSent'  => intval($o['settleSent'] ?? 0) === 1,
        'source'      => (string)($o['orderSource'] ?? ''),
        'note'        => (string)($o['orderNote'] ?? ''),
        'payStatus'   => strtoupper(trim((string)($o['payStatus'] ?? ''))),
        'payBank'     => (string)($o['payBank'] ?? ''),
        'payStage'    => (string)($o['payStage'] ?? ''),
        'payAmount'   => (float)($o['payAmount'] ?? 0),
        'payAt'       => (string)($o['payAt'] ?? ''),
        'payReviewedAt' => (string)($o['payReviewedAt'] ?? ''),
        'hasProof'    => intval($o['hasProof'] ?? 0) === 1,
        'payable'     => tk_order_payable($o),
    ];
}

if (!isset($_SESSION['member_logged_in']) && isset($_COOKIE['tk_member_id'])) {
    $cid = $conn->real_escape_string(tk_resolve_member_id($conn, $_COOKIE['tk_member_id']));
    if ($cid !== $_COOKIE['tk_member_id']) setcookie("tk_member_id", $cid, time() + 86400 * 30, "/");
    $res = $conn->query("SELECT * FROM tk_customer_accounts WHERE customer_id = '$cid'");
    if ($res && $res->num_rows > 0) {
        $_SESSION['member_logged_in'] = true;
        $_SESSION['member_data'] = $res->fetch_assoc();
    }
}

if (!isset($_SESSION['member_logged_in']) || $_SESSION['member_logged_in'] !== true) {
    header("Location: login.php?err=session_lost");
    exit;
}

if (isset($_GET['action']) && $_GET['action'] == 'logout') {
    session_destroy();
    setcookie("tk_member_id", "", time() - 3600, "/");
    header("Location: login.php");
    exit;
}

if (empty($_SESSION['tk_order_cols_v3'])) {
    tk_ensure_order_columns($conn);
    $_SESSION['tk_order_cols_v3'] = 1;
}

if (isset($_GET['api'])) {
    header('Content-Type: application/json');

    // ===== KATALOG PRODUK REALTIME (data dari Katalog Produk admin) =====
    if ($_GET['api'] == 'get_products') {
        echo json_encode(["success" => true, "data" => tk_products_public($conn)], tk_json_flags());
        exit;
    }

    // ===== MEMBER MEMESAN PRODUK -> masuk ke daftar pesanan admin =====
    if ($_GET['api'] == 'place_order') {
        $d_ord = json_decode(file_get_contents('php://input'), true);
        $cid_o = tk_resolve_member_id($conn, $_SESSION['member_data']['customer_id'] ?? '');
        $mem_o = null;
        $stm = $conn->prepare("SELECT * FROM tk_customer_accounts WHERE customer_id = ? LIMIT 1");
        if ($stm) { $stm->bind_param("s", $cid_o); $stm->execute(); $rm = $stm->get_result(); $mem_o = $rm ? $rm->fetch_assoc() : null; $stm->close(); }
        if (!$mem_o) { echo json_encode(["success" => false, "error" => "Akun member tidak ditemukan."]); exit; }

        $address_o = trim((string)($d_ord['address'] ?? ''));
        $note_o    = mb_substr(trim((string)($d_ord['note'] ?? '')), 0, 300);
        $bank_k    = strtoupper(trim((string)($d_ord['bank'] ?? '')));
        $banks_o   = tk_pay_banks();
        if (mb_strlen($address_o) < 5) { echo json_encode(["success" => false, "error" => "Alamat pemasangan/pengiriman wajib diisi."]); exit; }
        if (mb_strlen($address_o) > 500) $address_o = mb_substr($address_o, 0, 500);
        if (!isset($banks_o[$bank_k])) { echo json_encode(["success" => false, "error" => "Pilih metode pembayaran."]); exit; }

        $lines = [];
        foreach ((array)($d_ord['items'] ?? []) as $ln) {
            $pid = (string)($ln['id'] ?? '');
            $q = intval($ln['qty'] ?? 0);
            if ($pid === '' || $q < 1) continue;
            $lines[$pid] = min(99, ($lines[$pid] ?? 0) + $q);
        }
        if (!$lines || count($lines) > 30) { echo json_encode(["success" => false, "error" => "Keranjang kosong atau terlalu banyak jenis produk."]); exit; }

        // Harga SELALU diambil dari katalog server (bukan dari browser)
        $items_o = []; $total_o = 0.0;
        foreach ($lines as $pid => $q) {
            $stp = $conn->prepare("SELECT id, name, group_name, badge, price FROM tk_products WHERE id = ? LIMIT 1");
            if (!$stp) continue;
            $stp->bind_param("s", $pid); $stp->execute();
            $rp = $stp->get_result(); $prod = $rp ? $rp->fetch_assoc() : null; $stp->close();
            if (!$prod) { echo json_encode(["success" => false, "error" => "Ada produk yang sudah tidak tersedia di katalog. Muat ulang halaman."]); exit; }
            if (!tk_product_available($prod)) { echo json_encode(["success" => false, "error" => "Produk \"" . $prod['name'] . "\" sedang tidak tersedia."]); exit; }
            $price = (float)$prod['price'];
            $items_o[] = ['id' => (string)$prod['id'], 'name' => (string)$prod['name'], 'qty' => $q, 'price' => $price, 'group_name' => (string)($prod['group_name'] ?? '')];
            $total_o += $price * $q;
        }

        // Cegah kirim ganda (klik dua kali / refresh)
        $sig = md5(json_encode($lines) . '|' . $address_o . '|' . $bank_k);
        if (!empty($_SESSION['tk_last_order_sig']) && $_SESSION['tk_last_order_sig'] === $sig && (time() - intval($_SESSION['tk_last_order_time'] ?? 0)) < 20) {
            echo json_encode(["success" => false, "error" => "Pesanan yang sama baru saja dikirim. Cek Riwayat Invoice."]);
            exit;
        }

        $tz = new DateTimeZone('Asia/Jakarta');
        $now = new DateTime('now', $tz);
        $mon = tk_id_months();
        $date_txt = $now->format('j') . ' ' . $mon[intval($now->format('n')) - 1] . ' ' . $now->format('Y') . ', ' . $now->format('H.i');
        $raw_date = gmdate('Y-m-d\TH:i:s') . '.000Z';
        $oid_new = '';
        for ($try = 0; $try < 8; $try++) {
            $cand = 'INV-' . $now->format('ymd') . '-' . strtoupper(substr(md5(uniqid('', true)), 0, 5));
            $chk = $conn->prepare("SELECT 1 FROM tk_orders WHERE id = ? LIMIT 1");
            if (!$chk) break;
            $chk->bind_param("s", $cand); $chk->execute(); $rc = $chk->get_result(); $dup = $rc && $rc->num_rows > 0; $chk->close();
            if (!$dup) { $oid_new = $cand; break; }
        }
        if ($oid_new === '') { echo json_encode(["success" => false, "error" => "Gagal membuat nomor invoice, coba lagi."]); exit; }

        $name_o = (string)($mem_o['name'] ?? ''); $phone_o = (string)($mem_o['phone'] ?? '');
        $bank_label = $banks_o[$bank_k]['label'];
        $items_json = json_encode($items_o, JSON_UNESCAPED_UNICODE);
        $st_o = 'BELUM LUNAS'; $tr_o = 'PROSES'; $zero = 0.0; $empty = ''; $src = 'MEMBER';
        $ins = $conn->prepare("INSERT INTO tk_orders (id, `date`, rawDate, name, phone, address, total, bank, status, trackingStatus, dpAmount, remainingAmount, warrantyType, warrantyDueDate, items, proofImage, orderSource, customerId, orderNote) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
        if (!$ins) { echo json_encode(["success" => false, "error" => "Sistem gagal menyiapkan pesanan: " . $conn->error]); exit; }
        $ins->bind_param("ssssssdsssddsssssss", $oid_new, $date_txt, $raw_date, $name_o, $phone_o, $address_o, $total_o, $bank_label, $st_o, $tr_o, $zero, $zero, $empty, $empty, $items_json, $empty, $src, $cid_o, $note_o);
        $ok_i = $ins->execute(); $err_i = $ins->error; $ins->close();
        if (!$ok_i) { echo json_encode(["success" => false, "error" => "Gagal menyimpan pesanan: " . $err_i]); exit; }

        $_SESSION['tk_last_order_sig'] = $sig;
        $_SESSION['tk_last_order_time'] = time();
        $fresh_o = tk_member_orders($conn, $_SESSION['member_data'] ?? [], $oid_new);
        echo json_encode(["success" => true, "id" => $oid_new, "data" => $fresh_o ? tk_order_public($fresh_o[0]) : null], tk_json_flags());
        exit;
    }

    // ===== INVOICE REALTIME (status pembayaran, label konfirmasi admin) =====
    if ($_GET['api'] == 'get_invoices') {
        $list_inv = [];
        foreach (tk_member_orders($conn, $_SESSION['member_data'] ?? []) as $o_inv) $list_inv[] = tk_order_public($o_inv);
        echo json_encode(["success" => true, "data" => $list_inv], tk_json_flags());
        exit;
    }

    // ===== GAMBAR BUKTI TF (hanya pemilik invoice) =====
    if ($_GET['api'] == 'order_proof') {
        $oid_p = (string)($_GET['id'] ?? '');
        $src = '';
        if ($oid_p !== '' && count(tk_member_orders($conn, $_SESSION['member_data'] ?? [], $oid_p)) > 0) {
            $stp = $conn->prepare("SELECT proofImage FROM tk_orders WHERE id = ? LIMIT 1");
            if ($stp) {
                $stp->bind_param("s", $oid_p);
                $stp->execute();
                $rp = $stp->get_result();
                $rowp = $rp ? $rp->fetch_assoc() : null;
                $stp->close();
                $src = (string)($rowp['proofImage'] ?? '');
            }
        }
        if (!preg_match('#^data:(image/(?:jpeg|png|webp|gif));base64,(.+)$#s', $src, $pm)) {
            while (ob_get_level()) ob_end_clean();
            http_response_code(404);
            exit;
        }
        $bin = base64_decode($pm[2], true);
        if ($bin === false) {
            while (ob_get_level()) ob_end_clean();
            http_response_code(404);
            exit;
        }
        while (ob_get_level()) ob_end_clean();
        header('Content-Type: ' . $pm[1]);
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: private, max-age=60');
        echo $bin;
        exit;
    }

    // ===== MEMBER KIRIM BUKTI TF -> status "Menunggu konfirmasi admin" =====
    if ($_GET['api'] == 'submit_payment') {
        $d_pay = json_decode(file_get_contents('php://input'), true);
        $oid_s  = (string)($d_pay['id'] ?? '');
        $bank_k = strtoupper(trim((string)($d_pay['bank'] ?? '')));
        $proof  = (string)($d_pay['proof'] ?? '');
        $banks  = tk_pay_banks();
        if ($oid_s === '' || !isset($banks[$bank_k])) {
            echo json_encode(["success" => false, "error" => "Pilih bank/metode pembayaran terlebih dahulu."]);
            exit;
        }
        $rows_s = tk_member_orders($conn, $_SESSION['member_data'] ?? [], $oid_s);
        if (!$rows_s) {
            echo json_encode(["success" => false, "error" => "Invoice tidak ditemukan."]);
            exit;
        }
        $ord_s = $rows_s[0];
        if (!tk_order_payable($ord_s)) {
            echo json_encode(["success" => false, "error" => "Invoice ini sedang menunggu konfirmasi admin atau pembayarannya sudah diterima."]);
            exit;
        }
        $is_cash = !empty($banks[$bank_k]['cash']);
        $valid_proof = (bool)preg_match('#^data:image/(?:jpeg|png|webp|gif);base64,#', $proof);
        if (!$is_cash && !$valid_proof) {
            echo json_encode(["success" => false, "error" => "Bukti transfer wajib dilampirkan (format gambar)."]);
            exit;
        }
        if ($valid_proof && strlen($proof) > 8000000) {
            echo json_encode(["success" => false, "error" => "Ukuran gambar terlalu besar. Gunakan foto yang lebih kecil."]);
            exit;
        }
        $stage_s  = tk_order_stage($ord_s);
        $amount_s = tk_order_due($ord_s);
        $label_s  = $banks[$bank_k]['label'];

        if ($valid_proof) {
            $stu = $conn->prepare("UPDATE tk_orders SET proofImage = ?, payStatus = 'MENUNGGU', payBank = ?, payStage = ?, payAmount = ?, payAt = NOW(), payReviewedAt = NULL WHERE id = ? AND (payStatus IS NULL OR payStatus <> 'MENUNGGU')");
            if ($stu) $stu->bind_param("sssds", $proof, $label_s, $stage_s, $amount_s, $oid_s);
        } else {
            $stu = $conn->prepare("UPDATE tk_orders SET payStatus = 'MENUNGGU', payBank = ?, payStage = ?, payAmount = ?, payAt = NOW(), payReviewedAt = NULL WHERE id = ? AND (payStatus IS NULL OR payStatus <> 'MENUNGGU')");
            if ($stu) $stu->bind_param("ssds", $label_s, $stage_s, $amount_s, $oid_s);
        }
        if (!$stu) {
            echo json_encode(["success" => false, "error" => "Sistem gagal menyiapkan penyimpanan: " . $conn->error]);
            exit;
        }
        $ok_u  = $stu->execute();
        $err_u = $stu->error;
        $aff_u = $stu->affected_rows;
        $stu->close();
        if (!$ok_u) {
            echo json_encode(["success" => false, "error" => "Gagal menyimpan bukti: " . $err_u]);
            exit;
        }
        if ($aff_u < 1) {
            echo json_encode(["success" => false, "error" => "Pembayaran untuk invoice ini sudah dikirim sebelumnya."]);
            exit;
        }
        $fresh = tk_member_orders($conn, $_SESSION['member_data'] ?? [], $oid_s);
        echo json_encode(["success" => true, "data" => $fresh ? tk_order_public($fresh[0]) : null], tk_json_flags());
        exit;
    }
    
    // Data perangkat realtime (SN + deskripsi + foto) milik member yang sedang login
    if ($_GET['api'] == 'get_devices') {
        $cid_dev = $_SESSION['member_data']['customer_id'] ?? '';
        echo json_encode(["success" => true, "data" => tk_build_devices($conn, $cid_dev)], tk_json_flags());
        exit;
    }

    // Foto SN satu unit (dibatasi hanya untuk pemilik data)
    if ($_GET['api'] == 'device_photo') {
        $cid_dev = $_SESSION['member_data']['customer_id'] ?? '';
        $name_dev = $_SESSION['member_data']['name'] ?? '';
        $wo_id = (string)($_GET['wo'] ?? '');
        $field = (string)($_GET['field'] ?? '');
        $idx = intval($_GET['index'] ?? 0);
        $photo_cols = ['dvr' => 'proof_image_dvr', 'cctv' => 'proof_image_cctv', 'hdd' => 'proof_image_hdd', 'other' => 'proof_image_other'];
        $src = '';
        if ($wo_id !== '' && isset($photo_cols[$field]) && $idx >= 0 && $idx <= 50) {
            $col = $photo_cols[$field];
            $stmt_p = $conn->prepare("SELECT $col AS val FROM tk_technician_orders WHERE id = ? AND (customer_id = ? OR (nama = ? AND nama <> '')) LIMIT 1");
            if ($stmt_p) {
                $stmt_p->bind_param("sss", $wo_id, $cid_dev, $name_dev);
                $stmt_p->execute();
                $rp = $stmt_p->get_result();
                $row_p = $rp ? $rp->fetch_assoc() : null;
                $stmt_p->close();
                $val = (string)($row_p['val'] ?? '');
                $arr = json_decode($val, true);
                if (!is_array($arr)) $arr = (strpos($val, 'data:image/') === 0) ? [$val] : [];
                $arr = array_values($arr);
                $src = (string)($arr[$idx] ?? '');
            }
        }
        if (!preg_match('#^data:(image/(?:jpeg|png|webp|gif));base64,(.+)$#s', $src, $pm)) {
            while (ob_get_level()) ob_end_clean();
            http_response_code(404);
            exit;
        }
        $bin = base64_decode($pm[2], true);
        if ($bin === false) {
            while (ob_get_level()) ob_end_clean();
            http_response_code(404);
            exit;
        }
        while (ob_get_level()) ob_end_clean();
        header('Content-Type: ' . $pm[1]);
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: private, max-age=300');
        echo $bin;
        exit;
    }

    if ($_GET['api'] == 'get_chat') {
        $session = $conn->real_escape_string($_GET['ticket_id']);
        $res = $conn->query("SELECT sender, message, created_at FROM tk_chats WHERE session_id = '$session' ORDER BY created_at ASC");
        $msgs = [];
        if($res) while($row = $res->fetch_assoc()) $msgs[] = $row;
        echo json_encode(["success" => true, "data" => $msgs]);
        exit;
    }
    
    if ($_GET['api'] == 'send_chat') {
        $d = json_decode(file_get_contents('php://input'), true);
        $session = $conn->real_escape_string($d['ticket_id'] ?? '');
        $msg = $conn->real_escape_string($d['message'] ?? '');
        
        if (!empty($session) && !empty($msg)) {
            $conn->query("INSERT IGNORE INTO tk_chat_sessions (session_id, is_bot_active) VALUES ('$session', 0)");
            $conn->query("UPDATE tk_chat_sessions SET last_update = CURRENT_TIMESTAMP, is_bot_active = 0 WHERE session_id = '$session'");
            $conn->query("INSERT INTO tk_chats (session_id, sender, message) VALUES ('$session', 'customer', '$msg')");
            echo json_encode(["success" => true]);
            exit;
        }
        echo json_encode(["success" => false, "error" => "Pesan kosong."]);
        exit;
    }
    
    if ($_GET['api'] == 'upload_chat_image') {
        $session = $conn->real_escape_string($_POST['ticket_id'] ?? '');
        $base64_raw = $_POST['image_base64'] ?? '';

        if (!empty($session) && !empty($base64_raw)) {
            $full_base64 = 'data:image/jpeg;base64,' . $base64_raw;
            $html_img = '<a href="' . $full_base64 . '" target="_blank"><img src="' . $full_base64 . '" class="max-w-xs rounded-xl shadow-md border my-1 cursor-pointer hover:opacity-95 transition" /></a>';
            
            $conn->query("INSERT IGNORE INTO tk_chat_sessions (session_id, is_bot_active) VALUES ('$session', 0)");
            $conn->query("UPDATE tk_chat_sessions SET last_update = CURRENT_TIMESTAMP, is_bot_active = 0 WHERE session_id = '$session'");
            
            $stmt_img = $conn->prepare("INSERT INTO tk_chats (session_id, sender, message) VALUES (?, 'customer', ?)");
            if ($stmt_img) {
                $stmt_img->bind_param("ss", $session, $html_img);
                if ($stmt_img->execute()) {
                    $stmt_img->close();
                    echo json_encode(["success" => true]);
                    exit;
                } else {
                    $db_error = $stmt_img->error;
                    $stmt_img->close();
                    echo json_encode(["success" => false, "error" => "Gagal simpan DB: " . $db_error]);
                    exit;
                }
            }
        }
        echo json_encode(["success" => false, "error" => "Sesi atau data gambar kosong."]);
        exit;
    }

    if ($_GET['api'] == 'get_tickets') {
        $cid_api = $conn->real_escape_string(tk_resolve_member_id($conn, $_GET['customer_id'] ?? ''));
        $tickets_api = [];
        $q_t = $conn->query("SELECT * FROM tk_tickets WHERE customer_id = '$cid_api' AND status != 'SELESAI' ORDER BY created_at DESC");
        if ($q_t) while ($row = $q_t->fetch_assoc()) $tickets_api[] = $row;
        echo json_encode(["success" => true, "data" => $tickets_api]);
        exit;
    }
}

$error_msg = ""; $success_msg = "";

if (isset($_GET['status']) && $_GET['status'] == 'sukses') {
    $success_msg = "Laporan berhasil dikirim ke Admin Dashboard!";
}

$member = $_SESSION['member_data']; 
$cid = $member['customer_id'];
// Jika admin mengubah ID Pelanggan, sesi lama otomatis diarahkan ke ID terbaru
$cid_resolved = tk_resolve_member_id($conn, $cid);
if ($cid_resolved !== $cid) {
    $cid = $conn->real_escape_string($cid_resolved);
    setcookie("tk_member_id", $cid, time() + 86400 * 30, "/");
}

$q_fresh = $conn->query("SELECT * FROM tk_customer_accounts WHERE customer_id = '$cid'");
if ($q_fresh && $q_fresh->num_rows > 0) { 
    $_SESSION['member_data'] = $q_fresh->fetch_assoc(); 
    $member = $_SESSION['member_data']; 
} else { 
    session_destroy();
    setcookie("tk_member_id", "", time() - 3600, "/");
    header("Location: login.php?err=account_lost");
    exit;
}

$display_name = preg_replace('/\s*\(\d+\)/', '', $member['name'] ?? '');
$display_name = trim(preg_replace('/\s+/', ' ', $display_name));
if ($display_name === '') {
    $display_name = $member['name'] ?? 'Member';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_ticket'])) {
    try {
        $nama = $conn->real_escape_string(trim($member['name'])); 
        $tingkat = $conn->real_escape_string(trim($_POST['tingkat_masalah']));
        $judul = $conn->real_escape_string(trim($_POST['judul']));
        $deskripsi = $conn->real_escape_string(trim($_POST['deskripsi']));
        $ticket_id = 'TKT-' . time();
        
        $stmt = $conn->prepare("INSERT INTO tk_tickets (id, customer_id, nama, tingkat_masalah, judul, deskripsi, status, proof_image) VALUES (?, ?, ?, ?, ?, ?, 'MENUNGGU', '')");
        
        if ($stmt) {
            $stmt->bind_param("ssssss", $ticket_id, $cid, $nama, $tingkat, $judul, $deskripsi);
            if ($stmt->execute()) {
                $conn->query("INSERT IGNORE INTO tk_chat_sessions (session_id, is_bot_active) VALUES ('$ticket_id', 0)");
                $welcome_sys = "Tiket dibuat. Anda dapat mengirimkan foto kendala atau chat langsung dengan admin di sini.";
                $conn->query("INSERT INTO tk_chats (session_id, sender, message) VALUES ('$ticket_id', 'system', '$welcome_sys')");
                
                $current_page = basename($_SERVER['PHP_SELF']);
                header("Location: {$current_page}?tab=tiket&status=sukses");
                exit;
            } else {
                $error_msg = "Gagal menyimpan laporan ke database: " . $stmt->error;
            }
            $stmt->close();
        } else {
            $error_msg = "Sistem gagal menyiapkan laporan: " . $conn->error;
        }
    } catch (Exception $e) {
        $error_msg = "Terjadi kesalahan sistem: " . $e->getMessage();
    }
}

$tickets = [];
$q_tickets = $conn->query("SELECT * FROM tk_tickets WHERE customer_id = '$cid' AND status != 'SELESAI' ORDER BY created_at DESC");
if ($q_tickets) while ($row = $q_tickets->fetch_assoc()) $tickets[] = $row;

$purchased_orders = [];
$member_phone = $conn->real_escape_string($member['phone'] ?? '');
$member_name = $conn->real_escape_string($member['name'] ?? '');
if (!empty($member_phone) || !empty($member_name)) {
    // Tanpa kolom foto bukti (ringan); gambar dimuat terpisah lewat ?api=order_proof
    $purchased_orders = tk_member_orders($conn, $member);
}
$invoices_public = array_map('tk_order_public', $purchased_orders);
$default_address = '';
foreach ($purchased_orders as $po_a) { if (trim((string)($po_a['address'] ?? '')) !== '') { $default_address = (string)$po_a['address']; break; } }
$products_public = (($_GET['tab'] ?? '') === 'produk') ? tk_products_public($conn) : [];

$active_tab = $_GET['tab'] ?? 'beranda';
$active_date = $member['active_date'] ?: date('Y-m-d');

$warranty_type = '';
$latest_invoice = null;
foreach ($purchased_orders as $ord) {
    if (!$latest_invoice) $latest_invoice = $ord;
    if (!empty($ord['warrantyType'])) {
        $warranty_type = strtoupper($ord['warrantyType']);
        break;
    }
}
if ($warranty_type === '' && !empty($member['warranty_type'])) {
    $warranty_type = strtoupper($member['warranty_type']);
}

$warranty_produk_end = null;
$warranty_maint_end = null;
if ($warranty_type === 'PRODUK' || $warranty_type === 'FULL') {
    $warranty_produk_end = strtotime($active_date . ' + 1 year');
}
if ($warranty_type === 'MAINTENANCE' || $warranty_type === 'FULL') {
    $warranty_maint_end = strtotime($active_date . ' + 3 months');
}
if ($warranty_type === '') {
    $warranty_type = 'PRODUK';
    $warranty_produk_end = strtotime($active_date . ' + 1 year');
}

// LOG AKTIVITAS
$activity_logs = [];

$q_all_tickets = $conn->query("SELECT id, judul, status, created_at FROM tk_tickets WHERE customer_id = '$cid' ORDER BY created_at DESC LIMIT 50");
if ($q_all_tickets) {
    while ($t = $q_all_tickets->fetch_assoc()) {
        $status_label = $t['status'];
        if ($status_label === 'MENUNGGU') $status_label = 'Menunggu';
        elseif (in_array(strtoupper($status_label), ['PROSES', 'PROSES ADMIN', 'TEKNISI VISIT'])) $status_label = 'Masih Proses';
        elseif (in_array(strtoupper($status_label), ['SELESAI', 'SELESAI RAMPUNG'])) $status_label = 'Selesai';

        $activity_logs[] = [
            'tanggal'    => $t['created_at'],
            'group'      => 'tickets',
            'data_id'    => $t['id'],
            'deskripsi'  => 'Membuat tiket: ' . $t['judul'] . ' (Status: ' . $status_label . ')',
        ];

        $tid = $conn->real_escape_string($t['id']);
        $q_ch = $conn->query("SELECT sender, message, created_at FROM tk_chats WHERE session_id = '$tid' AND sender != 'system' ORDER BY created_at DESC LIMIT 5");
        if ($q_ch) {
            while ($c = $q_ch->fetch_assoc()) {
                $preview = mb_substr(strip_tags($c['message']), 0, 80);
                $who = $c['sender'] === 'customer' ? 'Anda membalas tiket' : 'Admin membalas tiket';
                $activity_logs[] = [
                    'tanggal'   => $c['created_at'],
                    'group'     => 'tickets',
                    'data_id'   => $t['id'],
                    'deskripsi' => $who . ': ' . $preview,
                ];
            }
        }
    }
}

$q_tech = $conn->query("SELECT * FROM tk_technician_orders WHERE customer_id = '$cid' ORDER BY created_at DESC LIMIT 40");
if ($q_tech) {
    while ($to = $q_tech->fetch_assoc()) {
        $wo_type = strtoupper($to['wo_type'] ?? 'INSTALASI');
        $status_raw = strtoupper(trim($to['status'] ?? ''));

        if (in_array($status_raw, ['MENUNGGU KONFIRMASI', 'MENUNGGU'])) {
            $status_label = 'Menunggu';
        } elseif (in_array($status_raw, ['PROSES', 'PROSES ADMIN', 'TEKNISI VISIT'])) {
            $status_label = 'Masih Proses';
        } elseif (in_array($status_raw, ['DATA APPROVE', 'SELESAI', 'SELESAI RAMPUNG', 'APPROVED'])) {
            $status_label = 'Selesai';
        } else {
            $status_label = $to['status'] ?: 'Menunggu';
        }

        $sn_parts = [];
        if (!empty($to['dvr_sn']))  $sn_parts[] = 'DVR: ' . $to['dvr_sn'];
        if (!empty($to['cctv_sn'])) $sn_parts[] = 'CCTV: ' . $to['cctv_sn'];
        if (!empty($to['hdd_sn']))  $sn_parts[] = 'HDD: ' . $to['hdd_sn'];
        if (!empty($to['other_sn'])) $sn_parts[] = 'Lainnya: ' . $to['other_sn'];
        $sn_text = !empty($sn_parts) ? implode(' | ', $sn_parts) : 'Tidak ada SN tercatat';

        $catatan = trim($to['catatan_maintenance'] ?? '');
        if ($catatan === '') {
            $catatan = ($wo_type === 'MAINTENANCE') ? 'Laporan Maintenance' : 'Instalasi perangkat';
        }

        $jenis = ($wo_type === 'MAINTENANCE') ? 'Maintenance' : 'Instalasi';

        $activity_logs[] = [
            'tanggal'   => $to['created_at'],
            'group'     => strtolower($wo_type) === 'maintenance' ? 'maintenance' : 'instalasi',
            'data_id'   => $to['id'],
            'deskripsi' => $jenis . ' \97 ' . $catatan . ' | ' . $sn_text . ' (Status: ' . $status_label . ')',
        ];
    }
}

foreach ($purchased_orders as $ord) {
    $activity_logs[] = [
        'tanggal'   => $ord['rawDate'] ?: $ord['date'],
        'group'     => 'orders',
        'data_id'   => $ord['id'],
        'deskripsi' => 'Pesanan ' . $ord['id'] . ' \97 Status: ' . $ord['status'] . ' (Rp ' . number_format($ord['total'], 0, ',', '.') . ')',
    ];
}

usort($activity_logs, function($a, $b) {
    return strcmp($b['tanggal'], $a['tanggal']);
});
$activity_logs = array_slice($activity_logs, 0, 50);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MEMBER TEKUNIN | Portal Pelanggan</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; }
        .sidebar-link.active { background: rgba(255,255,255,0.12); color: #fff; border-left: 3px solid #38bdf8; }
        .sidebar-link:hover { background: rgba(255,255,255,0.08); }
    </style>
</head>
<body class="bg-slate-100 text-slate-800 min-h-screen flex">

    <aside id="sidebar" class="fixed md:static inset-y-0 left-0 z-40 w-64 bg-slate-900 text-slate-300 flex flex-col shrink-0 -translate-x-full md:translate-x-0 transition-transform duration-300">
        <div class="h-16 flex items-center gap-3 px-5 border-b border-white/10">
            <div class="w-9 h-9 rounded-xl bg-sky-500 text-white flex items-center justify-center font-black text-sm shadow">M</div>
            <div>
                <div class="text-white font-black text-sm tracking-wide">MEMBER TEKUNIN</div>
                <div class="text-[10px] text-slate-400 font-medium">Portal Pelanggan</div>
            </div>
        </div>
        <div class="px-4 py-4 border-b border-white/10">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-full bg-slate-700 flex items-center justify-center text-sky-300"><i class="fa-solid fa-user"></i></div>
                <div class="min-w-0">
                    <div class="text-white text-xs font-bold truncate"><?= htmlspecialchars($display_name) ?></div>
                    <div class="text-[10px] text-emerald-400 font-semibold flex items-center gap-1"><span class="w-1.5 h-1.5 rounded-full bg-emerald-400"></span> Online</div>
                </div>
            </div>
        </div>
        <nav class="flex-1 overflow-y-auto py-4 px-3 space-y-1 text-sm font-semibold">
            <div class="text-[10px] uppercase tracking-wider text-slate-500 px-3 mb-2">Navigasi</div>
            <a href="?tab=beranda" class="sidebar-link flex items-center gap-3 px-3 py-2.5 rounded-lg transition <?= $active_tab=='beranda'?'active bg-white/10 text-white border-l-[3px] border-sky-400':'' ?>">
                <i class="fa-solid fa-house w-5 text-center"></i> Beranda
            </a>
            <a href="?tab=produk" class="sidebar-link flex items-center gap-3 px-3 py-2.5 rounded-lg transition relative <?= $active_tab=='produk'?'active bg-white/10 text-white border-l-[3px] border-sky-400':'' ?>">
                <i class="fa-solid fa-bag-shopping w-5 text-center"></i> Produk
                <span id="cart-badge" class="ml-auto bg-sky-500 text-white text-[10px] font-black px-1.5 py-0.5 rounded-full hidden">0</span>
            </a>
            <a href="?tab=tiket" class="sidebar-link flex items-center gap-3 px-3 py-2.5 rounded-lg transition relative <?= $active_tab=='tiket'?'active bg-white/10 text-white border-l-[3px] border-sky-400':'' ?>">
                <i class="fa-solid fa-ticket w-5 text-center"></i> Tiket Laporan
                <?php if (count($tickets) > 0): ?>
                <span class="ml-auto bg-rose-500 text-white text-[10px] font-black px-1.5 py-0.5 rounded-full"><?= count($tickets) ?></span>
                <?php endif; ?>
            </a>
            <a href="?tab=log" class="sidebar-link flex items-center gap-3 px-3 py-2.5 rounded-lg transition <?= $active_tab=='log'?'active bg-white/10 text-white border-l-[3px] border-sky-400':'' ?>">
                <i class="fa-solid fa-clock-rotate-left w-5 text-center"></i> Log Aktivitas
            </a>
        </nav>
        <div class="p-4 border-t border-white/10">
            <a href="?action=logout" class="flex items-center justify-center gap-2 w-full py-2.5 rounded-xl bg-rose-500/15 text-rose-300 hover:bg-rose-500/25 text-xs font-bold transition">
                <i class="fa-solid fa-right-from-bracket"></i> Keluar
            </a>
        </div>
    </aside>
    <div id="sidebar-overlay" onclick="document.getElementById('sidebar').classList.add('-translate-x-full');this.classList.add('hidden')" class="fixed inset-0 bg-black/50 z-30 hidden md:hidden"></div>

    <div class="flex-1 flex flex-col min-w-0 min-h-screen">
        <header class="h-14 bg-white border-b border-slate-200 flex items-center justify-between px-4 md:px-6 sticky top-0 z-20">
            <div class="flex items-center gap-3">
                <button onclick="document.getElementById('sidebar').classList.toggle('-translate-x-full');document.getElementById('sidebar-overlay').classList.toggle('hidden')" class="md:hidden p-2 rounded-lg hover:bg-slate-100 text-slate-600">
                    <i class="fa-solid fa-bars"></i>
                </button>
                <h1 class="text-sm font-black text-slate-800">
                    <?php
                    if ($active_tab=='produk') echo 'Produk';
                    elseif ($active_tab=='tiket') echo 'Tiket Laporan';
                    elseif ($active_tab=='log') echo 'Log Aktivitas';
                    else echo 'Beranda';
                    ?>
                </h1>
            </div>
            <div class="text-xs font-bold text-slate-500 hidden sm:block">ID: <span class="text-slate-800"><?= htmlspecialchars($member['customer_id']) ?></span></div>
        </header>

        <main class="flex-1 p-4 md:p-6 space-y-5">
            <?php if ($success_msg): ?>
            <div class="bg-emerald-50 text-emerald-700 border border-emerald-200 font-bold p-3 rounded-xl text-sm flex items-center gap-2">
                <i class="fa-solid fa-check-circle"></i> <?= $success_msg ?>
            </div>
            <?php endif; ?>
            <?php if ($error_msg): ?>
            <div class="bg-rose-50 text-rose-700 border border-rose-200 font-bold p-3 rounded-xl text-sm flex items-center gap-2">
                <i class="fa-solid fa-triangle-exclamation"></i> <?= $error_msg ?>
            </div>
            <?php endif; ?>

            <?php if ($active_tab == 'beranda'): ?>
            <div class="bg-sky-50 border border-sky-100 rounded-2xl p-4 md:p-5">
                <div class="flex items-start gap-3">
                    <div class="w-8 h-8 rounded-lg bg-sky-500 text-white flex items-center justify-center shrink-0"><i class="fa-solid fa-circle-info"></i></div>
                    <div>
                        <h2 class="font-black text-slate-800 text-sm">Selamat Datang, <?= htmlspecialchars($display_name) ?></h2>
                        <p class="text-xs text-slate-600 mt-1">Portal Member TEKUNIN \97 cek invoice, status garansi, dan kirim tiket laporan kendala.</p>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-3">
                <?php
                $inv_label = 'Belum ada';
                $inv_sub = 'Invoice';
                $inv_color = 'bg-sky-500';
                if ($latest_invoice) {
                    $lp_card = tk_order_public($latest_invoice);
                    $inv_label = htmlspecialchars($lp_card['id']);
                    $st = $lp_card['status'];
                    $inv_sub = ($st === 'LUNAS') ? 'Invoice (LUNAS)' : 'Invoice (' . htmlspecialchars($lp_card['statusLabel']) . ')';
                    $inv_color = ($st === 'LUNAS') ? 'bg-sky-500' : 'bg-amber-500';
                }
                ?>
                <div id="inv-card" class="<?= $inv_color ?> text-white rounded-2xl p-4 shadow-sm transition-colors">
                    <div class="text-[10px] font-bold uppercase opacity-80 mb-1"><i class="fa-solid fa-file-invoice mr-1"></i> Invoice</div>
                    <div id="inv-card-label" class="font-black text-sm truncate"><?= $inv_label ?></div>
                    <div id="inv-card-sub" class="text-[11px] mt-1 opacity-90"><?= $inv_sub ?></div>
                </div>

                <?php if ($warranty_produk_end): ?>
                <div class="bg-emerald-500 text-white rounded-2xl p-4 shadow-sm">
                    <div class="text-[10px] font-bold uppercase opacity-80 mb-1"><i class="fa-solid fa-shield-halved mr-1"></i> Garansi Produk</div>
                    <div id="cd-produk" class="font-black text-sm">Menghitung...</div>
                    <div class="text-[11px] mt-1 opacity-90">1 Tahun \B7 mulai <?= htmlspecialchars($active_date) ?></div>
                </div>
                <?php endif; ?>

                <?php if ($warranty_maint_end): ?>
                <div class="bg-violet-500 text-white rounded-2xl p-4 shadow-sm">
                    <div class="text-[10px] font-bold uppercase opacity-80 mb-1"><i class="fa-solid fa-screwdriver-wrench mr-1"></i> Garansi Maintenance</div>
                    <div id="cd-maint" class="font-black text-sm">Menghitung...</div>
                    <div class="text-[11px] mt-1 opacity-90">3 Bulan \B7 mulai <?= htmlspecialchars($active_date) ?></div>
                </div>
                <?php endif; ?>

                <div class="bg-slate-700 text-white rounded-2xl p-4 shadow-sm">
                    <div class="text-[10px] font-bold uppercase opacity-80 mb-1"><i class="fa-solid fa-id-card mr-1"></i> ID Pelanggan</div>
                    <div class="font-black text-sm"><?= htmlspecialchars($member['customer_id']) ?></div>
                    <div class="text-[11px] mt-1 opacity-90">Member resmi</div>
                </div>
            </div>

            <div class="bg-white rounded-2xl border border-slate-200 p-5 shadow-sm">
                <h3 class="font-black text-slate-800 text-sm mb-4"><i class="fa-solid fa-microchip text-sky-600 mr-2"></i>Perangkat Terdaftar (S/N)<span id="sn-live-badge" class="ml-2 text-[9px] font-extrabold uppercase px-2 py-0.5 rounded-full bg-emerald-100 text-emerald-700 align-middle"><i class="fa-solid fa-circle text-[6px] mr-1 align-middle"></i>Live</span></h3>
                <div id="sn-devices-grid" class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-3 text-xs"></div>
                <div id="sn-cable-info"></div>
                <script>window.__INIT_DEVICES = <?= json_encode(tk_build_devices($conn, $cid), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | tk_json_flags()) ?>;</script>
            </div>

            <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
                <div class="px-5 py-4 border-b border-slate-100 flex justify-between items-center">
                    <h3 class="font-black text-slate-800 text-sm"><i class="fa-solid fa-receipt text-sky-600 mr-2"></i>Riwayat Invoice<span class="ml-2 text-[9px] font-extrabold uppercase px-2 py-0.5 rounded-full bg-emerald-100 text-emerald-700 align-middle"><i class="fa-solid fa-circle text-[6px] mr-1 align-middle"></i>Live</span></h3>
                    <span id="invoice-count-badge" class="text-[10px] font-bold bg-slate-100 text-slate-600 px-2 py-1 rounded-lg"><?= count($purchased_orders) ?> transaksi</span>
                </div>
                <div id="invoice-table-wrap"></div>
            </div>

            <?php elseif ($active_tab == 'produk'): ?>
            <div class="bg-sky-50 border border-sky-100 rounded-2xl p-4 md:p-5 flex items-start gap-3">
                <div class="w-8 h-8 rounded-lg bg-sky-500 text-white flex items-center justify-center shrink-0"><i class="fa-solid fa-bag-shopping"></i></div>
                <div>
                    <h2 class="font-black text-slate-800 text-sm">Beli Produk Lagi</h2>
                    <p class="text-xs text-slate-600 mt-1">Pilih produk dari katalog TEKUNIN. Pesanan langsung diterima Admin beserta data Anda, lalu tagihan (DP/pelunasan) dapat dibayar dari <b>Riwayat Invoice</b>.</p>
                </div>
            </div>

            <div id="rebuy-box" class="hidden bg-white rounded-2xl border border-slate-200 shadow-sm p-4">
                <h3 class="font-black text-slate-800 text-xs uppercase mb-3"><i class="fa-solid fa-rotate-right text-violet-600 mr-2"></i>Beli Lagi &mdash; produk yang pernah Anda pesan</h3>
                <div id="rebuy-list" class="flex flex-wrap gap-2"></div>
            </div>

            <div class="grid grid-cols-1 xl:grid-cols-3 gap-5">
                <div class="xl:col-span-2 space-y-3">
                    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-3 space-y-3">
                        <div class="relative">
                            <i class="fa-solid fa-magnifying-glass absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400 text-xs"></i>
                            <input id="prod-search" type="text" oninput="PROD_Q=this.value;renderProducts()" placeholder="Cari produk..." class="w-full pl-9 pr-3 py-2.5 bg-slate-50 border rounded-xl text-sm font-bold focus:outline-none focus:border-sky-500">
                        </div>
                        <div id="prod-groups" class="flex flex-wrap gap-1.5"></div>
                    </div>
                    <div id="prod-grid" class="grid grid-cols-1 sm:grid-cols-2 gap-3"></div>
                </div>

                <div id="cart-panel" class="bg-white rounded-2xl border border-slate-200 p-5 shadow-sm h-fit xl:sticky xl:top-20 space-y-3">
                    <h3 class="text-xs font-black uppercase border-b pb-3 flex justify-between"><span><i class="fa-solid fa-cart-shopping text-sky-600 mr-1"></i> Keranjang</span><span id="cart-count-label" class="bg-sky-100 text-sky-700 px-2 py-0.5 rounded text-[10px]">0 item</span></h3>
                    <div id="cart-list" class="space-y-2"></div>
                    <div id="cart-total-box" class="hidden flex justify-between items-center bg-emerald-50 border border-emerald-100 rounded-xl px-3 py-2.5"><span class="text-[11px] font-extrabold text-emerald-800 uppercase">Total</span><b id="cart-total" class="text-emerald-600 text-base">Rp 0</b></div>

                    <div id="checkout-form" class="hidden space-y-3 pt-1">
                        <div class="text-[11px] bg-slate-50 border border-slate-100 rounded-xl p-3 text-slate-600 font-bold">
                            <div class="text-[10px] text-slate-400 uppercase">Data Pemesan</div>
                            <div class="text-slate-900"><?= htmlspecialchars($member['name'] ?? '') ?></div>
                            <div><?= htmlspecialchars($member['phone'] ?? '') ?> &middot; ID <?= htmlspecialchars($member['customer_id'] ?? '') ?></div>
                        </div>
                        <div>
                            <label class="block text-[11px] font-bold text-slate-600 uppercase mb-1">Alamat Pemasangan / Pengiriman</label>
                            <textarea id="co-address" rows="3" placeholder="Alamat lengkap..." class="w-full px-3 py-2.5 bg-slate-50 border rounded-xl text-sm font-bold resize-none focus:outline-none focus:border-sky-500"></textarea>
                        </div>
                        <div>
                            <label class="block text-[11px] font-bold text-slate-600 uppercase mb-1">Catatan (opsional)</label>
                            <input id="co-note" type="text" maxlength="300" placeholder="Cth: tambah kamera di halaman belakang" class="w-full px-3 py-2.5 bg-slate-50 border rounded-xl text-sm font-bold focus:outline-none focus:border-sky-500">
                        </div>
                        <div>
                            <label class="block text-[11px] font-bold text-sky-800 uppercase mb-1"><i class="fa-solid fa-building-columns mr-1"></i> Metode Pembayaran (Bank)</label>
                            <select id="co-bank" class="w-full px-3 py-2.5 bg-white border-2 border-sky-200 rounded-xl text-sm font-bold focus:outline-none focus:border-sky-500"></select>
                        </div>
                        <div class="p-3 bg-sky-50 text-sky-800 rounded-xl text-[11px] border border-sky-200"><i class="fa-solid fa-circle-info mr-1"></i> Setelah pesanan dikirim, Admin menetapkan DP. Tombol <b>Bayar Sekarang</b> akan muncul di Riwayat Invoice.</div>
                        <div id="co-msg" class="hidden text-xs font-bold rounded-xl p-2.5"></div>
                        <button id="co-submit" onclick="submitOrder()" class="w-full bg-slate-900 text-white font-extrabold py-3 rounded-xl text-sm hover:bg-slate-800"><i class="fa-solid fa-paper-plane mr-1"></i> Kirim Pesanan ke Admin</button>
                    </div>
                </div>
            </div>

            <?php elseif ($active_tab == 'tiket'): ?>
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-5">
                <div class="bg-white rounded-2xl border border-slate-200 p-5 shadow-sm">
                    <h3 class="text-xs font-black uppercase mb-4 border-b pb-3"><i class="fa-solid fa-headset text-rose-600 mr-1"></i> Buat Laporan Kendala</h3>
                    <form method="POST" action="?tab=tiket" class="space-y-4">
                        <div>
                            <label class="block text-[11px] font-bold text-slate-600 uppercase mb-1">Tingkat Masalah</label>
                            <select name="tingkat_masalah" required class="w-full px-4 py-2.5 bg-slate-50 border rounded-xl text-sm font-bold focus:outline-none focus:border-sky-500">
                                <option value="RENDAH">RENDAH (Pertanyaan Umum)</option>
                                <option value="SEDANG">SEDANG (Kamera Mati Sebagian)</option>
                                <option value="TINGGI">TINGGI (Sistem Mati Total)</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-[11px] font-bold text-slate-600 uppercase mb-1">Judul Masalah</label>
                            <input type="text" name="judul" required placeholder="Cth: Kamera belakang tidak merekam" class="w-full px-4 py-2.5 bg-slate-50 border rounded-xl text-sm font-bold focus:outline-none focus:border-sky-500">
                        </div>
                        <div>
                            <label class="block text-[11px] font-bold text-slate-600 uppercase mb-1">Deskripsi Detail</label>
                            <textarea name="deskripsi" rows="3" required placeholder="Jelaskan kendala..." class="w-full px-4 py-2.5 bg-slate-50 border rounded-xl text-sm font-bold resize-none focus:outline-none focus:border-sky-500"></textarea>
                        </div>
                        <div class="p-3 bg-sky-50 text-sky-800 rounded-xl text-xs border border-sky-200">
                            <i class="fa-solid fa-circle-info mr-1"></i> Foto bukti dapat dikirim via <b>Live Chat</b> setelah tiket dibuat.
                        </div>
                        <button type="submit" name="submit_ticket" class="w-full bg-slate-900 text-white font-extrabold py-3 rounded-xl text-sm hover:bg-slate-800">Kirim Laporan</button>
                    </form>
                </div>
                <div class="bg-white rounded-2xl border border-slate-200 p-5 shadow-sm">
                    <h3 class="text-xs font-black uppercase mb-4 border-b pb-3 flex justify-between">
                        <span><i class="fa-solid fa-clipboard-list text-sky-600 mr-1"></i> Status Tiket</span>
                        <span id="ticket-count-badge" class="bg-sky-100 text-sky-700 px-2 py-0.5 rounded text-[10px]"><?= count($tickets) ?> Tiket</span>
                    </h3>
                    <div id="tickets-container" class="space-y-3 max-h-[420px] overflow-y-auto pr-1">
                        <?php if (empty($tickets)): ?>
                        <div class="text-center p-8 text-slate-400 font-bold bg-slate-50 rounded-2xl border border-dashed text-xs">Belum ada tiket aktif.</div>
                        <?php else: foreach ($tickets as $t):
                            $s_col = 'bg-slate-100 text-slate-700';
                            if ($t['status']=='MENUNGGU') $s_col='bg-amber-100 text-amber-700';
                            elseif ($t['status']=='Proses Admin') $s_col='bg-sky-100 text-sky-700';
                            elseif ($t['status']=='TEKNISI VISIT') $s_col='bg-purple-100 text-purple-700 font-black';
                        ?>
                        <div class="p-4 bg-slate-50 border rounded-xl">
                            <div class="flex justify-between items-start gap-2">
                                <div>
                                    <b class="text-sm text-slate-900"><?= htmlspecialchars($t['judul']) ?></b>
                                    <div class="text-[10px] text-slate-500">ID: <?= $t['id'] ?> \B7 <?= $t['created_at'] ?></div>
                                </div>
                                <span class="px-2 py-1 rounded-full text-[9px] font-black uppercase <?= $s_col ?>"><?= htmlspecialchars($t['status']) ?></span>
                            </div>
                            <button onclick="openChat('<?= $t['id'] ?>', '<?= htmlspecialchars($t['judul'], ENT_QUOTES) ?>')" class="mt-3 w-full text-xs bg-sky-600 text-white px-4 py-2.5 rounded-xl font-bold hover:bg-sky-500">
                                <i class="fa-solid fa-comments mr-1"></i> Buka Live Chat & Kirim Foto
                            </button>
                        </div>
                        <?php endforeach; endif; ?>
                    </div>
                </div>
            </div>

            <?php else: ?>
            <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
                <div class="px-5 py-4 border-b border-slate-100">
                    <h3 class="font-black text-slate-800 text-sm"><i class="fa-solid fa-clock-rotate-left text-sky-600 mr-2"></i>Log Aktivitas</h3>
                    <p class="text-[11px] text-slate-500 mt-0.5">Riwayat tiket, hasil maintenance/instalasi, dan pesanan Anda.</p>
                </div>
                <?php if (empty($activity_logs)): ?>
                <div class="p-10 text-center text-slate-400 text-xs font-bold">Belum ada aktivitas tercatat.</div>
                <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="w-full text-xs">
                        <thead class="bg-slate-50 text-slate-500 uppercase text-[10px] font-bold">
                            <tr>
                                <th class="text-left px-4 py-3">Tanggal</th>
                                <th class="text-left px-4 py-3">Jenis</th>
                                <th class="text-left px-4 py-3">ID</th>
                                <th class="text-left px-4 py-3">Detail</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <?php foreach ($activity_logs as $log): 
                                $group_label = $log['group'];
                                if ($group_label === 'maintenance') $group_label = 'Maintenance';
                                elseif ($group_label === 'instalasi') $group_label = 'Instalasi';
                                elseif ($group_label === 'tickets') $group_label = 'Tiket';
                                elseif ($group_label === 'orders') $group_label = 'Pesanan';
                            ?>
                            <tr class="hover:bg-slate-50">
                                <td class="px-4 py-3 text-slate-600 whitespace-nowrap"><?= htmlspecialchars($log['tanggal']) ?></td>
                                <td class="px-4 py-3">
                                    <span class="bg-slate-100 text-slate-600 px-2 py-0.5 rounded font-bold"><?= htmlspecialchars($group_label) ?></span>
                                </td>
                                <td class="px-4 py-3 font-mono text-sky-700"><?= htmlspecialchars($log['data_id']) ?></td>
                                <td class="px-4 py-3 text-slate-700"><?= htmlspecialchars($log['deskripsi']) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </main>

        <footer class="py-3 px-6 text-[11px] text-slate-400 border-t border-slate-200 bg-white text-center">
            MEMBER TEKUNIN \B7 Portal Pelanggan
        </footer>
    </div>

    <div id="chat-modal" class="fixed inset-0 z-50 bg-slate-900/60 hidden flex-col items-center justify-end md:justify-center p-4">
        <div class="bg-white w-full max-w-lg rounded-t-3xl md:rounded-3xl shadow-2xl flex flex-col h-[75vh] md:h-[620px] border overflow-hidden">
            <div class="bg-slate-900 text-white p-4 flex justify-between items-center">
                <h3 class="font-black text-sm" id="chat-title">Live Chat</h3>
                <button onclick="closeChat()" class="bg-white/20 w-8 h-8 rounded-full hover:bg-white/30 flex items-center justify-center"><i class="fa-solid fa-xmark"></i></button>
            </div>
            <div id="chat-messages" class="flex-1 p-5 overflow-y-auto bg-slate-50 space-y-3"></div>
            <div class="bg-white p-3 border-t flex items-center gap-2">
                <input type="hidden" id="active-ticket-id">
                <label class="cursor-pointer bg-slate-100 text-slate-700 hover:bg-sky-50 hover:text-sky-600 p-3 rounded-xl border shrink-0" title="Unggah Foto">
                    <i class="fa-solid fa-image"></i>
                    <input type="file" accept="image/*" class="hidden" onchange="handleImageSelection(this)">
                </label>
                <label class="cursor-pointer bg-slate-100 text-slate-700 hover:bg-sky-50 hover:text-sky-600 p-3 rounded-xl border shrink-0" title="Kamera">
                    <i class="fa-solid fa-camera"></i>
                    <input type="file" accept="image/*" capture="environment" class="hidden" onchange="handleImageSelection(this)">
                </label>
                <input type="text" id="chat-input" placeholder="Tuliskan pesan..." class="flex-1 border rounded-xl px-4 py-2.5 text-xs focus:outline-none focus:border-sky-500" onkeydown="if(event.key==='Enter')sendChat()">
                <button onclick="sendChat()" class="bg-sky-600 text-white px-5 py-3 rounded-xl hover:bg-sky-500 shrink-0"><i class="fa-solid fa-paper-plane"></i></button>
            </div>
        </div>
    </div>
    <div id="invoice-modal" class="fixed inset-0 z-50 bg-slate-900/60 hidden items-end md:items-center justify-center p-0 md:p-4" onclick="if(event.target===this)closeInvoice()">
        <div class="bg-white w-full max-w-lg rounded-t-3xl md:rounded-3xl shadow-2xl max-h-[92vh] flex flex-col border overflow-hidden">
            <div class="bg-slate-900 text-white p-4 flex justify-between items-center shrink-0">
                <h3 class="font-black text-sm"><i class="fa-solid fa-file-invoice mr-2 text-sky-300"></i><span id="inv-modal-title">Invoice</span></h3>
                <button onclick="closeInvoice()" class="bg-white/20 w-8 h-8 rounded-full hover:bg-white/30 flex items-center justify-center"><i class="fa-solid fa-xmark"></i></button>
            </div>
            <div id="inv-scroll" class="flex-1 overflow-y-auto p-5 space-y-4 bg-slate-50">
                <div id="inv-detail"></div>
                <div id="inv-status"></div>

                <div id="inv-pay-form" class="hidden rounded-2xl border border-sky-100 bg-sky-50 p-4 space-y-3">
                    <label class="block text-[11px] font-extrabold text-sky-800 uppercase"><i class="fa-solid fa-building-columns mr-1"></i> Metode Pembayaran (Bank)</label>
                    <select id="pay-bank" onchange="onPayBankChange()" class="w-full px-4 py-3 bg-white border-2 border-sky-200 rounded-xl text-sm font-bold text-slate-900 focus:outline-none focus:border-sky-500"></select>
                    <div id="pay-bank-info"></div>
                    <div id="pay-proof-box">
                        <label id="pay-proof-label" class="block text-[11px] font-extrabold text-sky-800 uppercase mb-1"><i class="fa-solid fa-paperclip mr-1"></i> Bukti TF</label>
                        <input type="file" id="pay-proof-file" accept="image/*" onchange="onPayFileChange(this)" class="w-full text-xs bg-white border border-sky-200 rounded-xl p-2 file:mr-3 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:bg-sky-600 file:text-white file:font-bold file:text-xs">
                        <img id="pay-proof-preview" class="hidden mt-2 max-h-40 rounded-xl border border-slate-200" alt="Preview bukti">
                    </div>
                    <div id="pay-msg" class="hidden text-xs font-bold rounded-xl p-2.5"></div>
                    <button id="pay-submit-btn" onclick="submitPayment()" class="w-full bg-emerald-600 hover:bg-emerald-500 text-white font-extrabold py-3 rounded-xl text-sm shadow-md shadow-emerald-600/20"><i class="fa-solid fa-paper-plane mr-1"></i> Kirim Bukti TF</button>
                </div>
            </div>
        </div>
    </div>
    <div id="user-toast" class="fixed top-4 left-1/2 -translate-x-1/2 z-[130] hidden"></div>
    <div id="loading-overlay" class="fixed inset-0 z-[60] bg-slate-900/50 hidden flex-col items-center justify-center">
        <div class="bg-white p-4 rounded-xl shadow-lg flex items-center gap-3">
            <i class="fa-solid fa-circle-notch fa-spin text-sky-600 text-2xl"></i>
            <span class="font-bold text-sm text-slate-700">Meresize & Mengunggah Foto...</span>
        </div>
    </div>

    <script>
        const memberCustomerId = "<?= $cid ?>";
        const currentEndpoint = window.location.pathname.split("/").pop() || "dashboard.php";
        if (window.history.replaceState && window.location.search.includes('status=sukses')) {
            const cleanUrl = window.location.protocol + "//" + window.location.host + window.location.pathname + "?tab=tiket";
            window.history.replaceState({path: cleanUrl}, '', cleanUrl);
        }

        const produkEnd = <?= $warranty_produk_end ? ($warranty_produk_end * 1000) : 'null' ?>;
        const maintEnd = <?= $warranty_maint_end ? ($warranty_maint_end * 1000) : 'null' ?>;

        function fmtCountdown(endTs, elId) {
            const el = document.getElementById(elId);
            if (!el || !endTs) return;
            const dist = endTs - Date.now();
            if (dist < 0) { el.innerHTML = '<span class="opacity-90">Masa Habis</span>'; return; }
            const d = Math.floor(dist / 86400000);
            const h = Math.floor((dist % 86400000) / 3600000);
            const m = Math.floor((dist % 3600000) / 60000);
            el.innerHTML = d + 'h ' + h + 'j ' + m + 'm';
        }
        function tickWarranty() {
            fmtCountdown(produkEnd, 'cd-produk');
            fmtCountdown(maintEnd, 'cd-maint');
        }
        tickWarranty();
        setInterval(tickWarranty, 60000);

        function escapeHtml(text) {
            return String(text).replace(/&/g,"&amp;").replace(/</g,"&lt;").replace(/>/g,"&gt;").replace(/"/g,"&quot;").replace(/'/g,"&#039;");
        }

        async function fetchMemberTickets() {
            try {
                let res = await fetch(`${currentEndpoint}?api=get_tickets&customer_id=${memberCustomerId}`);
                let json = await res.json();
                if (!json.success) return;
                const tickets = json.data;
                const container = document.getElementById('tickets-container');
                const badge = document.getElementById('ticket-count-badge');
                if (badge) badge.innerText = `${tickets.length} Tiket`;
                if (!container) return;
                if (!tickets.length) {
                    container.innerHTML = '<div class="text-center p-8 text-slate-400 font-bold bg-slate-50 rounded-2xl border border-dashed text-xs">Belum ada tiket aktif.</div>';
                    return;
                }
                container.innerHTML = tickets.map(t => {
                    let s_col = 'bg-slate-100 text-slate-700';
                    if (t.status == 'MENUNGGU') s_col = 'bg-amber-100 text-amber-700';
                    else if (t.status == 'Proses Admin') s_col = 'bg-sky-100 text-sky-700';
                    else if (t.status == 'TEKNISI VISIT') s_col = 'bg-purple-100 text-purple-700 font-black';
                    return `<div class="p-4 bg-slate-50 border rounded-xl">
                        <div class="flex justify-between items-start gap-2">
                            <div><b class="text-sm text-slate-900">${escapeHtml(t.judul)}</b><div class="text-[10px] text-slate-500">ID: ${t.id} \B7 ${t.created_at}</div></div>
                            <span class="px-2 py-1 rounded-full text-[9px] font-black uppercase ${s_col}">${escapeHtml(t.status)}</span>
                        </div>
                        <button onclick="openChat('${t.id}', '${escapeHtml(t.judul).replace(/'/g,"\\\\'")}')" class="mt-3 w-full text-xs bg-sky-600 text-white px-4 py-2.5 rounded-xl font-bold hover:bg-sky-500">
                            <i class="fa-solid fa-comments mr-1"></i> Buka Live Chat & Kirim Foto
                        </button>
                    </div>`;
                }).join('');
            } catch(e) {}
        }
        setInterval(fetchMemberTickets, 3000);

        // ===== PERANGKAT TERDAFTAR (SN + DESKRIPSI + FOTO) REALTIME DARI TEKNISI/ADMIN =====
        function openDevPhoto(src) {
            if (!src) return;
            const ov = document.createElement('div');
            ov.className = 'fixed inset-0 z-[120] bg-slate-950/85 flex items-center justify-center p-4';
            ov.onclick = () => ov.remove();
            const im = document.createElement('img');
            im.src = src;
            im.className = 'max-w-full max-h-full rounded-2xl shadow-2xl';
            ov.appendChild(im);
            document.body.appendChild(ov);
        }

        function renderDevices(data) {
            const grid = document.getElementById('sn-devices-grid');
            if (!grid || !data || !Array.isArray(data.groups)) return;
            const html = data.groups.map(g => {
                const items = (g.items || []).map(it => `
                    <div class="bg-white border border-slate-100 rounded-lg p-2">
                        <div class="font-mono font-bold text-slate-800 break-all">&bull; ${escapeHtml(it.sn)}</div>
                        ${it.photo ? `<img src="${currentEndpoint}${it.photo}" loading="lazy" onclick="openDevPhoto(this.src)" onerror="this.remove()" class="mt-1.5 w-20 h-20 object-cover rounded-lg border border-slate-200 cursor-pointer hover:opacity-90">` : ''}
                        ${it.desc ? `<div class="mt-1.5 text-[11px] leading-relaxed text-slate-600 bg-amber-50 border border-amber-100 rounded-md px-2 py-1 whitespace-pre-line"><b class="text-amber-700">Deskripsi:</b> ${escapeHtml(it.desc)}</div>` : ''}
                    </div>`).join('');
                return `<div class="bg-slate-50 border border-slate-100 rounded-xl p-3">
                    <div class="font-bold text-slate-500 uppercase text-[10px] mb-2"><i class="fa-solid ${escapeHtml(g.icon)} text-sky-500 mr-1"></i>${escapeHtml(g.label)}</div>
                    <div class="space-y-1.5">${items || '<span class="text-slate-400">Belum diatur</span>'}</div>
                </div>`;
            }).join('');
            // render ulang hanya jika data berubah (hindari kedip & muat ulang foto)
            if (grid.dataset.sig !== html) { grid.innerHTML = html; grid.dataset.sig = html; }

            const cableBox = document.getElementById('sn-cable-info');
            if (cableBox) {
                const c = data.cable;
                const ch = c ? `<div class="mt-3 text-xs bg-slate-50 border border-slate-100 rounded-xl p-3 text-slate-700 whitespace-pre-line"><i class="fa-solid fa-ruler-horizontal text-sky-500 mr-1"></i><b>Kabel terpasang:</b> ${escapeHtml(c.meter || 0)} Meter${c.desc ? ' &mdash; ' + escapeHtml(c.desc) : ''}</div>` : '';
                if (cableBox.dataset.sig !== ch) { cableBox.innerHTML = ch; cableBox.dataset.sig = ch; }
            }
        }

        async function fetchDevices() {
            if (document.hidden || !document.getElementById('sn-devices-grid')) return;
            try {
                const res = await fetch(`${currentEndpoint}?api=get_devices`, { cache: 'no-store' });
                const json = await res.json();
                if (json && json.success) renderDevices(json.data);
            } catch (e) {}
        }
        if (window.__INIT_DEVICES) renderDevices(window.__INIT_DEVICES);
        setInterval(fetchDevices, 3000);

        // ===== INVOICE, PEMBAYARAN & BUKTI TF (REALTIME DENGAN ADMIN) =====
        const PAY_BANKS = <?= json_encode(array_values(tk_pay_banks()), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | tk_json_flags()) ?>;
        let INVOICES = <?= json_encode($invoices_public, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | tk_json_flags()) ?>;
        let activeInvoiceId = null;
        const prevPayState = {};
        INVOICES.forEach(i => { prevPayState[i.id] = i.payStatus; });
        const prevStage = {};
        INVOICES.forEach(i => { prevStage[i.id] = i.stage; });

        function rupiah(n) { return 'Rp ' + Number(n || 0).toLocaleString('id-ID'); }

        function userToast(msg, type) {
            const box = document.getElementById('user-toast');
            if (!box) return;
            const cls = type === 'ok' ? 'bg-emerald-600' : (type === 'err' ? 'bg-rose-600' : 'bg-slate-900');
            box.innerHTML = `<div class="${cls} text-white text-xs font-bold px-5 py-3 rounded-2xl shadow-2xl max-w-[90vw]">${escapeHtml(msg)}</div>`;
            box.classList.remove('hidden');
            clearTimeout(box._t);
            box._t = setTimeout(() => box.classList.add('hidden'), 4500);
        }

        function payBadgeHtml(inv) {
            if (inv.payStatus === 'MENUNGGU') return `<span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-black bg-amber-100 text-amber-700 border border-amber-200"><i class="fa-solid fa-hourglass-half"></i> Menunggu konfirmasi admin</span>`;
            if (inv.payStatus === 'DITERIMA') return `<span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-black bg-emerald-100 text-emerald-700 border border-emerald-200"><i class="fa-solid fa-circle-check"></i> Pembayaran diterima${inv.payStage === 'DP' ? ' (DP)' : ''}</span>`;
            if (inv.payStatus === 'DITOLAK') return `<span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-black bg-rose-100 text-rose-700 border border-rose-200"><i class="fa-solid fa-circle-xmark"></i> Ditolak, upload ulang</span>`;
            return '';
        }
        function statusBadgeHtml(inv) {
            const cls = inv.status === 'LUNAS' ? 'bg-emerald-100 text-emerald-700' : 'bg-amber-100 text-amber-700';
            return `<span class="px-2 py-0.5 rounded-full text-[10px] font-black ${cls}">${escapeHtml(inv.statusLabel)}</span>`;
        }
        function proofUrl(inv) {
            return `${currentEndpoint}?api=order_proof&id=${encodeURIComponent(inv.id)}&v=${encodeURIComponent((inv.payAt || '') + '|' + (inv.payStatus || ''))}`;
        }

        function renderInvoiceTable() {
            const wrap = document.getElementById('invoice-table-wrap');
            const badge = document.getElementById('invoice-count-badge');
            if (badge) badge.innerText = INVOICES.length + ' transaksi';
            if (!wrap) return;
            let html;
            if (!INVOICES.length) {
                html = '<div class="p-8 text-center text-slate-400 text-xs font-bold">Belum ada invoice / pesanan tercatat.</div>';
            } else {
                const rows = INVOICES.map(inv => {
                    const proofCell = inv.hasProof
                        ? `<button onclick="openDevPhoto('${proofUrl(inv)}')" class="text-[10px] font-black bg-sky-50 text-sky-700 border border-sky-200 hover:bg-sky-100 px-2.5 py-1 rounded-lg"><i class="fa-solid fa-image mr-1"></i> Lihat Bukti</button>`
                        : '<span class="text-[10px] text-slate-400 font-bold">Belum ada bukti</span>';
                    const payBtn = inv.payable
                        ? `<button onclick="openInvoice('${escapeHtml(inv.id)}', true)" class="text-[10px] font-black bg-emerald-600 hover:bg-emerald-500 text-white px-3 py-1.5 rounded-lg shadow-sm"><i class="fa-solid fa-wallet mr-1"></i> BAYAR SEKARANG</button>`
                        : '';
                    return `<tr class="hover:bg-slate-50">
                        <td class="px-4 py-3 font-mono font-bold text-slate-800">${escapeHtml(inv.id)}</td>
                        <td class="px-4 py-3 text-slate-600">${escapeHtml(inv.date)}</td>
                        <td class="px-4 py-3">${statusBadgeHtml(inv)}</td>
                        <td class="px-4 py-3 text-right font-bold text-slate-800">${rupiah(inv.total)}</td>
                        <td class="px-4 py-3"><div class="space-y-1.5">${proofCell}<div>${payBadgeHtml(inv)}</div></div></td>
                        <td class="px-4 py-3"><div class="flex flex-wrap gap-1.5 justify-end">
                            <button onclick="openInvoice('${escapeHtml(inv.id)}', false)" class="text-[10px] font-black bg-slate-100 hover:bg-slate-200 text-slate-700 px-3 py-1.5 rounded-lg"><i class="fa-solid fa-file-lines mr-1"></i> Detail</button>
                            ${payBtn}
                        </div></td>
                    </tr>`;
                }).join('');
                html = `<div class="overflow-x-auto"><table class="w-full text-xs">
                    <thead class="bg-slate-50 text-slate-500 uppercase text-[10px] font-bold"><tr>
                        <th class="text-left px-4 py-3">Invoice</th>
                        <th class="text-left px-4 py-3">Tanggal</th>
                        <th class="text-left px-4 py-3">Status</th>
                        <th class="text-right px-4 py-3">Total</th>
                        <th class="text-left px-4 py-3">Bukti Pembayaran</th>
                        <th class="text-right px-4 py-3">Aksi</th>
                    </tr></thead>
                    <tbody class="divide-y divide-slate-100">${rows}</tbody></table></div>`;
            }
            if (wrap.dataset.sig !== html) { wrap.innerHTML = html; wrap.dataset.sig = html; }
        }

        function renderInvoiceCard() {
            const card = document.getElementById('inv-card');
            if (!card) return;
            const inv = INVOICES[0];
            const lab = document.getElementById('inv-card-label');
            const sub = document.getElementById('inv-card-sub');
            if (!inv) { lab.innerText = 'Belum ada'; sub.innerText = 'Invoice'; card.className = 'bg-sky-500 text-white rounded-2xl p-4 shadow-sm transition-colors'; return; }
            lab.innerText = inv.id;
            let color = inv.status === 'LUNAS' ? 'bg-sky-500' : 'bg-amber-500';
            let txt = inv.status === 'LUNAS' ? 'Invoice (LUNAS)' : 'Invoice (' + inv.statusLabel + ')';
            if (inv.payStatus === 'MENUNGGU') { txt = 'Menunggu konfirmasi admin'; color = 'bg-amber-500'; }
            else if (inv.payStatus === 'DITERIMA') { txt = (inv.status === 'LUNAS' ? 'Invoice (LUNAS)' : 'Pembayaran diterima'); color = 'bg-emerald-500'; }
            else if (inv.payStatus === 'DITOLAK') { txt = 'Bukti ditolak, upload ulang'; color = 'bg-rose-500'; }
            sub.innerText = txt;
            card.className = color + ' text-white rounded-2xl p-4 shadow-sm transition-colors';
        }

        function invoiceDetailHtml(inv) {
            const items = (inv.items || []).map(x => `<div class="flex justify-between gap-3 py-1.5 border-b border-slate-100 last:border-0">
                    <span class="text-slate-700 font-medium">${escapeHtml(x.name || '-')} <span class="text-slate-400">x${escapeHtml(x.qty || 1)}</span></span>
                    <span class="font-bold text-slate-900 whitespace-nowrap">${rupiah((x.price || 0) * (x.qty || 1))}</span>
                </div>`).join('') || '<div class="text-slate-400 py-1.5">Tidak ada rincian barang.</div>';
            let money = `<div class="flex justify-between"><span class="text-slate-500 font-bold">Total Belanja</span><b class="text-slate-900">${rupiah(inv.total)}</b></div>`;
            if (inv.status === 'DP' && inv.stage === 'SISA') {
                money += `<div class="flex justify-between mt-1.5"><span class="text-slate-500 font-bold">DP (sudah dibayar)</span><b class="text-slate-900">${rupiah(inv.dpPart)}</b></div>
                          <div class="flex justify-between mt-1.5"><span class="text-emerald-700 font-bold">Sisa pelunasan yang harus dibayar</span><b class="text-emerald-700">${rupiah(inv.due)}</b></div>`;
            } else if (inv.status === 'DP') {
                money += `<div class="flex justify-between mt-1.5"><span class="text-emerald-700 font-bold">DP yang harus dibayar</span><b class="text-emerald-700">${rupiah(inv.due)}</b></div>
                          <div class="flex justify-between mt-1.5"><span class="text-slate-500 font-bold">Sisa tagihan setelah DP</span><b class="text-slate-900">${rupiah(inv.remaining)}</b></div>`;
            }
            return `<div class="bg-white border border-slate-200 rounded-2xl p-4 text-xs">
                    <div class="flex justify-between items-start gap-2 mb-3">
                        <div><div class="text-[10px] text-slate-400 font-bold uppercase">No. Invoice</div><div class="font-mono font-black text-sky-700 text-sm">${escapeHtml(inv.id)}</div></div>
                        <div class="text-right"><div class="text-[10px] text-slate-400 font-bold uppercase">Tanggal</div><div class="font-bold text-slate-700">${escapeHtml(inv.date)}</div><div class="mt-1">${statusBadgeHtml(inv)}</div></div>
                    </div>
                    <div class="mb-3 text-slate-600"><b class="text-slate-900 block">${escapeHtml(inv.name)}</b>${escapeHtml(inv.phone)}<br>${escapeHtml(inv.address)}${inv.note ? `<div class="mt-1.5 bg-amber-50 border border-amber-100 rounded-lg px-2 py-1"><b class="text-amber-700">Catatan:</b> ${escapeHtml(inv.note)}</div>` : ''}</div>
                    <div class="font-extrabold text-slate-800 mb-1 text-[11px] uppercase">Daftar Barang</div>
                    <div class="mb-3">${items}</div>
                    <div class="bg-emerald-50 border border-emerald-100 rounded-xl p-3">${money}</div>
                </div>`;
        }

        function invoiceStatusHtml(inv) {
            let out = '';
            const badge = payBadgeHtml(inv);
            if (badge || inv.hasProof) {
                let lines = '';
                if (inv.payBank) lines += `<div><i class="fa-solid fa-building-columns mr-1 text-slate-400"></i>${escapeHtml(inv.payBank)}</div>`;
                if (inv.payAmount) lines += `<div><i class="fa-solid fa-money-bill-wave mr-1 text-slate-400"></i>${rupiah(inv.payAmount)}</div>`;
                if (inv.payAt) lines += `<div><i class="fa-regular fa-clock mr-1 text-slate-400"></i>Dikirim: ${escapeHtml(inv.payAt)}</div>`;
                if (inv.payReviewedAt && inv.payStatus !== 'MENUNGGU') lines += `<div><i class="fa-solid fa-user-check mr-1 text-slate-400"></i>Dikonfirmasi: ${escapeHtml(inv.payReviewedAt)}</div>`;
                const proof = inv.hasProof ? `<img src="${proofUrl(inv)}" onclick="openDevPhoto(this.src)" onerror="this.remove()" loading="lazy" class="mt-2 max-h-40 rounded-xl border border-slate-200 cursor-pointer hover:opacity-90" alt="Bukti TF">` : '';
                const wrapCls = inv.payStatus === 'DITERIMA' ? 'bg-emerald-50 border-emerald-200' : (inv.payStatus === 'MENUNGGU' ? 'bg-amber-50 border-amber-200' : (inv.payStatus === 'DITOLAK' ? 'bg-rose-50 border-rose-200' : 'bg-white border-slate-200'));
                out = `<div class="border ${wrapCls} rounded-2xl p-4 text-xs">
                        <div class="font-extrabold text-slate-800 uppercase text-[11px] mb-2">Status Pembayaran</div>
                        ${badge || '<span class="text-slate-500 font-bold">Bukti tersimpan</span>'}
                        <div class="mt-2 text-slate-600 font-bold space-y-0.5">${lines}</div>
                        ${proof}
                        ${inv.payStatus === 'DITOLAK' ? '<div class="mt-2 text-rose-700 font-bold">Bukti pembayaran ditolak admin. Silakan kirim ulang bukti TF yang jelas.</div>' : ''}
                    </div>`;
            } else if (inv.status === 'LUNAS') {
                out = `<div class="bg-emerald-50 border border-emerald-200 rounded-2xl p-4 text-xs font-bold text-emerald-700"><i class="fa-solid fa-circle-check mr-1"></i> Invoice sudah LUNAS.</div>`;
            } else if (!inv.payable) {
                out = `<div class="bg-white border border-slate-200 rounded-2xl p-4 text-xs font-bold text-slate-500"><i class="fa-solid fa-circle-info mr-1"></i> Belum ada tagihan pembayaran. Tombol <b>Bayar Sekarang</b> akan muncul setelah admin menetapkan DP.</div>`;
            }
            if (inv.status === 'DP' && inv.payStatus === 'DITERIMA' && inv.payStage === 'DP' && !inv.settleSent) {
                out += `<div class="bg-violet-50 border border-violet-200 rounded-2xl p-3 text-xs font-bold text-violet-700"><i class="fa-solid fa-circle-info mr-1"></i> DP sudah diterima. Sisa tagihan sebesar ${rupiah(inv.remaining)} akan dikirim admin setelah instalasi selesai.</div>`;
            }
            if (inv.status === 'DP' && inv.stage === 'SISA' && inv.payable) {
                out += `<div class="bg-violet-50 border border-violet-200 rounded-2xl p-3 text-xs font-bold text-violet-700"><i class="fa-solid fa-bell mr-1"></i> Instalasi selesai. Silakan bayar sisa pelunasan ${rupiah(inv.due)}.</div>`;
            }
            return out;
        }

        function refreshInvoiceModal(fresh) {
            const inv = INVOICES.find(x => x.id === activeInvoiceId);
            if (!inv) { closeInvoice(); return; }
            document.getElementById('inv-modal-title').innerText = 'Invoice ' + inv.id;
            const det = document.getElementById('inv-detail');
            const dh = invoiceDetailHtml(inv);
            if (det.dataset.sig !== dh) { det.innerHTML = dh; det.dataset.sig = dh; }
            const stBox = document.getElementById('inv-status');
            const sh = invoiceStatusHtml(inv);
            if (stBox.dataset.sig !== sh) { stBox.innerHTML = sh; stBox.dataset.sig = sh; }
            const form = document.getElementById('inv-pay-form');
            if (inv.payable) {
                if (form.classList.contains('hidden')) { form.classList.remove('hidden'); initPayForm(inv); }
                else updatePayBankInfo(inv);
            } else {
                form.classList.add('hidden');
            }
        }

        function initPayForm(inv) {
            const sel = document.getElementById('pay-bank');
            sel.innerHTML = PAY_BANKS.map(b => `<option value="${escapeHtml(b.key)}">${escapeHtml(b.label)}</option>`).join('');
            document.getElementById('pay-proof-file').value = '';
            const pv = document.getElementById('pay-proof-preview'); pv.classList.add('hidden'); pv.removeAttribute('src');
            setPayMsg('', '');
            onPayBankChange();
        }
        function currentPayBank() {
            const key = document.getElementById('pay-bank').value;
            return PAY_BANKS.find(b => b.key === key) || PAY_BANKS[0];
        }
        function onPayBankChange() {
            const inv = INVOICES.find(x => x.id === activeInvoiceId);
            if (inv) updatePayBankInfo(inv);
            const b = currentPayBank();
            const lbl = document.getElementById('pay-proof-label');
            lbl.innerHTML = '<i class="fa-solid fa-paperclip mr-1"></i> Bukti TF' + (b.cash ? ' (opsional untuk pembayaran tunai)' : ' (wajib)');
            document.getElementById('pay-submit-btn').innerHTML = b.cash
                ? '<i class="fa-solid fa-paper-plane mr-1"></i> Konfirmasi Bayar Tunai'
                : '<i class="fa-solid fa-paper-plane mr-1"></i> Kirim Bukti TF';
        }
        function updatePayBankInfo(inv) {
            const b = currentPayBank();
            const box = document.getElementById('pay-bank-info');
            if (!box) return;
            const nominal = `<div class="flex justify-between items-center bg-white border border-emerald-200 rounded-xl px-3 py-2"><span class="text-[11px] font-bold text-emerald-700 uppercase">${inv.stage === 'SISA' ? 'Sisa Pelunasan' : (inv.status === 'DP' ? 'Nominal DP' : 'Nominal Bayar')}</span><b class="text-emerald-700 text-sm">${rupiah(inv.due)}</b></div>`;
            let html;
            if (b.cash) {
                html = `<div class="space-y-2">${nominal}<div class="bg-white border border-sky-200 rounded-xl px-3 py-2 text-xs font-bold text-slate-600"><i class="fa-solid fa-hand-holding-dollar mr-1 text-sky-500"></i> Pembayaran tunai dilakukan langsung di tempat. Setelah dikonfirmasi, admin akan menyetujui pembayaran Anda.</div></div>`;
            } else {
                html = `<div class="space-y-2">${nominal}
                    <div class="bg-white border border-sky-200 rounded-xl px-3 py-2.5 text-xs">
                        <div class="text-[10px] font-bold text-slate-400 uppercase">Transfer ke ${escapeHtml(b.name)}</div>
                        <div class="flex items-center justify-between gap-2 mt-0.5">
                            <b class="font-mono text-base text-slate-900 tracking-wide">${escapeHtml(b.number)}</b>
                            <button type="button" onclick="copyText('${escapeHtml(b.number)}')" class="text-[10px] font-black bg-sky-600 text-white px-2.5 py-1 rounded-lg hover:bg-sky-500"><i class="fa-regular fa-copy mr-1"></i> Salin</button>
                        </div>
                        <div class="text-slate-600 font-bold mt-0.5">a/n ${escapeHtml(b.holder)}</div>
                    </div></div>`;
            }
            if (box.dataset.sig !== html) { box.innerHTML = html; box.dataset.sig = html; }
            document.getElementById('pay-proof-box').classList.toggle('hidden', false);
        }
        function copyText(t) {
            try { navigator.clipboard.writeText(t); userToast('Nomor rekening disalin: ' + t, 'ok'); }
            catch (e) { userToast('Nomor rekening: ' + t); }
        }
        function setPayMsg(msg, type) {
            const el = document.getElementById('pay-msg');
            if (!msg) { el.classList.add('hidden'); el.innerText = ''; return; }
            el.className = 'text-xs font-bold rounded-xl p-2.5 ' + (type === 'ok' ? 'bg-emerald-100 text-emerald-700' : 'bg-rose-100 text-rose-700');
            el.innerText = msg;
        }
        function onPayFileChange(input) {
            const pv = document.getElementById('pay-proof-preview');
            const f = input.files && input.files[0];
            if (!f) { pv.classList.add('hidden'); pv.removeAttribute('src'); return; }
            const r = new FileReader();
            r.onload = e => { pv.src = e.target.result; pv.classList.remove('hidden'); };
            r.readAsDataURL(f);
        }

        function openInvoice(id, payMode) {
            activeInvoiceId = id;
            const m = document.getElementById('invoice-modal');
            m.classList.remove('hidden'); m.classList.add('flex');
            ['inv-detail', 'inv-status'].forEach(k => { const e = document.getElementById(k); if (e) delete e.dataset.sig; });
            document.getElementById('inv-pay-form').classList.add('hidden');
            const nb = document.getElementById('pay-bank-info'); if (nb) delete nb.dataset.sig;
            refreshInvoiceModal(true);
            if (payMode) {
                const f = document.getElementById('inv-pay-form');
                if (!f.classList.contains('hidden')) setTimeout(() => f.scrollIntoView({ behavior: 'smooth', block: 'center' }), 150);
            } else {
                document.getElementById('inv-scroll').scrollTop = 0;
            }
        }
        function closeInvoice() {
            activeInvoiceId = null;
            const m = document.getElementById('invoice-modal');
            m.classList.add('hidden'); m.classList.remove('flex');
        }

        function compressImageToDataUrl(file, maxSize, quality) {
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

        async function submitPayment() {
            const inv = INVOICES.find(x => x.id === activeInvoiceId);
            if (!inv) return;
            const bank = currentPayBank();
            const fileInput = document.getElementById('pay-proof-file');
            const file = fileInput.files && fileInput.files[0];
            if (!bank.cash && !file) { setPayMsg('Lampirkan foto bukti transfer terlebih dahulu.', 'err'); return; }
            const btn = document.getElementById('pay-submit-btn');
            const oldHtml = btn.innerHTML;
            btn.disabled = true; btn.classList.add('opacity-60');
            btn.innerHTML = '<i class="fa-solid fa-circle-notch fa-spin mr-1"></i> Mengirim...';
            setPayMsg('', '');
            try {
                let proof = '';
                if (file) proof = await compressImageToDataUrl(file, 1280, 0.75);
                const res = await fetch(`${currentEndpoint}?api=submit_payment`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ id: inv.id, bank: bank.key, proof: proof })
                });
                let json = null;
                try { json = await res.json(); } catch (e) { json = null; }
                if (json && json.success) {
                    fileInput.value = '';
                    const pv = document.getElementById('pay-proof-preview'); pv.classList.add('hidden'); pv.removeAttribute('src');
                    if (json.data) {
                        const idx = INVOICES.findIndex(x => x.id === json.data.id);
                        if (idx >= 0) INVOICES[idx] = json.data;
                        prevPayState[json.data.id] = json.data.payStatus;
                        prevStage[json.data.id] = json.data.stage;
                    }
                    renderInvoiceTable(); renderInvoiceCard(); refreshInvoiceModal(false);
                    userToast('Bukti terkirim. Menunggu konfirmasi admin.', 'ok');
                    fetchInvoices(true);
                } else {
                    setPayMsg((json && json.error) ? json.error : 'Gagal mengirim. File terlalu besar atau koneksi terputus, coba lagi.', 'err');
                }
            } catch (e) {
                setPayMsg(e && e.message ? e.message : 'Kesalahan jaringan, coba lagi.', 'err');
            } finally {
                btn.disabled = false; btn.classList.remove('opacity-60');
                if (!document.getElementById('inv-pay-form').classList.contains('hidden')) btn.innerHTML = oldHtml;
            }
        }

        function applyInvoices(list) {
            list.forEach(i => {
                const prev = prevPayState[i.id];
                if (prev === 'MENUNGGU' && i.payStatus === 'DITERIMA') userToast('Pembayaran invoice ' + i.id + ' telah diterima admin ✔', 'ok');
                else if (prev === 'MENUNGGU' && i.payStatus === 'DITOLAK') userToast('Bukti pembayaran invoice ' + i.id + ' ditolak. Silakan upload ulang.', 'err');
                prevPayState[i.id] = i.payStatus;
                if (prevStage[i.id] && prevStage[i.id] !== 'SISA' && i.stage === 'SISA') userToast('Admin mengirim tagihan sisa DP untuk invoice ' + i.id + ' — silakan bayar sekarang.', 'ok');
                if (prevStage[i.id] === 'DP' && i.status === 'LUNAS') userToast('Invoice ' + i.id + ' sudah LUNAS. Terima kasih!', 'ok');
                prevStage[i.id] = i.stage;
            });
            INVOICES = list;
            renderInvoiceTable();
            renderInvoiceCard();
            if (typeof renderRebuy === 'function') renderRebuy();
            if (activeInvoiceId) refreshInvoiceModal(false);
        }
        async function fetchInvoices(force) {
            if (!force && document.hidden) return;
            try {
                const res = await fetch(`${currentEndpoint}?api=get_invoices`, { cache: 'no-store' });
                const json = await res.json();
                if (json && json.success) applyInvoices(json.data || []);
            } catch (e) {}
        }
        renderInvoiceTable();
        renderInvoiceCard();
        setInterval(fetchInvoices, 3000);

        // ===== PRODUK: KATALOG (dari admin) + KERANJANG + PESAN KE ADMIN =====
        let PRODUCTS = <?= json_encode($products_public, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | tk_json_flags()) ?>;
        const DEFAULT_ADDRESS = <?= json_encode($default_address, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | tk_json_flags()) ?>;
        const CART_KEY = 'tk_cart_' + memberCustomerId;
        let CART = {};
        let PROD_Q = '', PROD_G = '';
        try { CART = JSON.parse(localStorage.getItem(CART_KEY) || '{}') || {}; } catch (e) { CART = {}; }

        function saveCart() { try { localStorage.setItem(CART_KEY, JSON.stringify(CART)); } catch (e) {} }
        function cartCount() { return Object.values(CART).reduce((a, b) => a + (parseInt(b) || 0), 0); }
        function prodById(id) { return PRODUCTS.find(p => p.id === id); }
        function safeImg(u) { u = String(u || ''); return /^(https?:\/\/|data:image\/|\/)/i.test(u) ? u : ''; }

        function updateCartBadge() {
            const b = document.getElementById('cart-badge');
            if (!b) return;
            const n = cartCount();
            b.innerText = n; b.classList.toggle('hidden', n === 0);
        }

        function cartAdd(id, n) {
            const p = prodById(id);
            if (!p || !p.available) { userToast('Produk tidak tersedia saat ini.', 'err'); return; }
            CART[id] = Math.min(99, (parseInt(CART[id]) || 0) + (n || 1));
            saveCart(); renderCart(); updateCardCounts(); updateCartBadge();
            userToast(p.name + ' ditambahkan ke keranjang', 'ok');
        }
        function cartSet(id, q) {
            q = parseInt(q) || 0;
            if (q < 1) delete CART[id]; else CART[id] = Math.min(99, q);
            saveCart(); renderCart(); updateCardCounts(); updateCartBadge();
        }

        function setProdGroup(g) { PROD_G = g || ''; renderProducts(); }
        function renderGroups() {
            const box = document.getElementById('prod-groups');
            if (!box) return;
            const groups = [...new Set(PRODUCTS.map(p => p.group))];
            const chip = (g, label) => `<button data-g="${escapeHtml(g)}" onclick="setProdGroup(this.dataset.g)" class="px-3 py-1.5 rounded-full text-[11px] font-black border ${PROD_G === g ? 'bg-slate-900 text-white border-slate-900' : 'bg-white text-slate-600 border-slate-200 hover:bg-slate-50'}">${escapeHtml(label)}</button>`;
            const html = chip('', 'Semua') + groups.map(g => chip(g, g)).join('');
            if (box.dataset.sig !== html) { box.innerHTML = html; box.dataset.sig = html; }
        }

        function renderProducts() {
            const grid = document.getElementById('prod-grid');
            if (!grid) return;
            renderGroups();
            if (PROD_G && !PRODUCTS.some(p => p.group === PROD_G)) PROD_G = '';
            const q = PROD_Q.trim().toLowerCase();
            const list = PRODUCTS.filter(p => (!PROD_G || p.group === PROD_G) && (!q || (p.name + ' ' + p.description + ' ' + p.group).toLowerCase().includes(q)));
            let html;
            if (!PRODUCTS.length) html = '<div class="sm:col-span-2 text-center p-8 text-slate-400 font-bold bg-white rounded-2xl border border-dashed text-xs">Belum ada produk di katalog.</div>';
            else if (!list.length) html = '<div class="sm:col-span-2 text-center p-8 text-slate-400 font-bold bg-white rounded-2xl border border-dashed text-xs">Produk tidak ditemukan.</div>';
            else html = list.map(p => {
                const img = safeImg(p.image);
                return `<div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden flex flex-col ${p.available ? '' : 'opacity-60'}">
                    <div class="h-36 bg-slate-100 flex items-center justify-center overflow-hidden">${img ? `<img src="${escapeHtml(img)}" loading="lazy" onerror="this.style.display='none'" class="w-full h-full object-cover" alt="">` : '<i class="fa-solid fa-image text-slate-300 text-3xl"></i>'}</div>
                    <div class="p-4 flex-1 flex flex-col">
                        <div class="flex items-center gap-1.5 mb-1.5"><span class="bg-sky-100 text-sky-700 px-2 py-0.5 rounded text-[10px] font-bold">${escapeHtml(p.group)}</span>${p.badge ? `<span class="bg-amber-100 text-amber-700 px-2 py-0.5 rounded text-[10px] font-bold">${escapeHtml(p.badge)}</span>` : ''}</div>
                        <div class="font-bold text-slate-900 text-sm leading-tight">${escapeHtml(p.name)}</div>
                        ${p.description ? `<div class="text-[11px] text-slate-500 mt-1 line-clamp-2">${escapeHtml(p.description)}</div>` : ''}
                        <div class="mt-auto pt-3 flex items-center justify-between gap-2">
                            <b class="text-emerald-600 text-sm">${rupiah(p.price)}</b>
                            ${p.available
                                ? `<button onclick="cartAdd('${escapeHtml(p.id)}')" class="text-[11px] font-black bg-sky-600 hover:bg-sky-500 text-white px-3 py-2 rounded-xl"><i class="fa-solid fa-cart-plus mr-1"></i> Tambah <span data-cnt="${escapeHtml(p.id)}" class="ml-1 bg-white/25 px-1.5 rounded hidden"></span></button>`
                                : '<span class="text-[11px] font-black text-slate-400">Tidak tersedia</span>'}
                        </div>
                    </div></div>`;
            }).join('');
            if (grid.dataset.sig !== html) { grid.innerHTML = html; grid.dataset.sig = html; }
            updateCardCounts();
            renderRebuy();
        }
        function updateCardCounts() {
            document.querySelectorAll('[data-cnt]').forEach(el => {
                const n = parseInt(CART[el.getAttribute('data-cnt')]) || 0;
                el.innerText = n; el.classList.toggle('hidden', n === 0);
            });
        }

        function renderCart() {
            const list = document.getElementById('cart-list');
            if (!list) return;
            // buang produk yang sudah dihapus / tidak tersedia di katalog admin
            if (PRODUCTS.length) {
                let removed = [];
                Object.keys(CART).forEach(id => { const p = prodById(id); if (!p || !p.available) { removed.push(id); delete CART[id]; } });
                if (removed.length) { saveCart(); userToast('Sebagian produk di keranjang sudah tidak tersedia dan dihapus.', 'err'); }
            }
            const ids = Object.keys(CART);
            let total = 0;
            const html = ids.length ? ids.map(id => {
                const p = prodById(id); if (!p) return '';
                const q = parseInt(CART[id]) || 0; total += p.price * q;
                return `<div class="flex items-center gap-2 bg-slate-50 border border-slate-100 rounded-xl p-2.5">
                    <div class="flex-1 min-w-0"><div class="text-xs font-bold text-slate-800 truncate">${escapeHtml(p.name)}</div><div class="text-[11px] text-emerald-600 font-bold">${rupiah(p.price)}</div></div>
                    <div class="flex items-center gap-1 shrink-0">
                        <button onclick="cartSet('${escapeHtml(id)}', ${q - 1})" class="w-7 h-7 rounded-lg bg-white border text-slate-600 font-black hover:bg-slate-100">&minus;</button>
                        <span class="w-7 text-center text-xs font-black">${q}</span>
                        <button onclick="cartSet('${escapeHtml(id)}', ${q + 1})" class="w-7 h-7 rounded-lg bg-white border text-slate-600 font-black hover:bg-slate-100">+</button>
                    </div></div>`;
            }).join('') : '<div class="text-center p-6 text-slate-400 font-bold bg-slate-50 rounded-xl border border-dashed text-xs">Keranjang masih kosong.</div>';
            if (list.dataset.sig !== html) { list.innerHTML = html; list.dataset.sig = html; }
            document.getElementById('cart-count-label').innerText = cartCount() + ' item';
            document.getElementById('cart-total').innerText = rupiah(total);
            document.getElementById('cart-total-box').classList.toggle('hidden', !ids.length);
            document.getElementById('checkout-form').classList.toggle('hidden', !ids.length);
        }

        function renderRebuy() {
            const box = document.getElementById('rebuy-box');
            if (!box) return;
            const seen = new Set(), picks = [];
            (INVOICES || []).forEach(inv => (inv.items || []).forEach(it => {
                const p = prodById(String(it.id || '')) || PRODUCTS.find(x => x.name.toLowerCase() === String(it.name || '').toLowerCase());
                if (p && p.available && !seen.has(p.id)) { seen.add(p.id); picks.push(p); }
            }));
            const top = picks.slice(0, 8);
            box.classList.toggle('hidden', !top.length);
            const html = top.map(p => `<button onclick="cartAdd('${escapeHtml(p.id)}')" class="text-[11px] font-bold bg-violet-50 hover:bg-violet-100 text-violet-700 border border-violet-200 px-3 py-1.5 rounded-xl"><i class="fa-solid fa-cart-plus mr-1"></i>${escapeHtml(p.name)} &middot; ${rupiah(p.price)}</button>`).join('');
            const list = document.getElementById('rebuy-list');
            if (list && list.dataset.sig !== html) { list.innerHTML = html; list.dataset.sig = html; }
        }

        function setCoMsg(msg, type) {
            const el = document.getElementById('co-msg');
            if (!el) return;
            if (!msg) { el.classList.add('hidden'); el.innerText = ''; return; }
            el.className = 'text-xs font-bold rounded-xl p-2.5 ' + (type === 'ok' ? 'bg-emerald-100 text-emerald-700' : 'bg-rose-100 text-rose-700');
            el.innerText = msg;
        }

        async function submitOrder() {
            const ids = Object.keys(CART);
            if (!ids.length) return;
            const address = document.getElementById('co-address').value.trim();
            if (address.length < 5) { setCoMsg('Alamat pemasangan/pengiriman wajib diisi.', 'err'); return; }
            const btn = document.getElementById('co-submit');
            const old = btn.innerHTML;
            btn.disabled = true; btn.classList.add('opacity-60');
            btn.innerHTML = '<i class="fa-solid fa-circle-notch fa-spin mr-1"></i> Mengirim...';
            setCoMsg('', '');
            try {
                const res = await fetch(`${currentEndpoint}?api=place_order`, {
                    method: 'POST', headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        items: ids.map(id => ({ id: id, qty: CART[id] })),
                        address: address,
                        note: document.getElementById('co-note').value.trim(),
                        bank: document.getElementById('co-bank').value
                    })
                });
                let json = null;
                try { json = await res.json(); } catch (e) { json = null; }
                if (json && json.success) {
                    CART = {}; saveCart(); renderCart(); updateCardCounts(); updateCartBadge();
                    document.getElementById('co-note').value = '';
                    if (json.data) { INVOICES = [json.data].concat(INVOICES.filter(x => x.id !== json.data.id)); prevPayState[json.data.id] = json.data.payStatus; prevStage[json.data.id] = json.data.stage; }
                    userToast('Pesanan ' + json.id + ' terkirim ke Admin ✔', 'ok');
                    setCoMsg('Pesanan ' + json.id + ' berhasil dikirim. Pantau statusnya di Beranda > Riwayat Invoice.', 'ok');
                    fetchInvoices(true);
                } else {
                    setCoMsg((json && json.error) ? json.error : 'Gagal mengirim pesanan. Periksa koneksi lalu coba lagi.', 'err');
                }
            } catch (e) {
                setCoMsg('Kesalahan jaringan, coba lagi.', 'err');
            } finally {
                btn.disabled = false; btn.classList.remove('opacity-60'); btn.innerHTML = old;
            }
        }

        async function fetchProducts() {
            if (document.hidden || !document.getElementById('prod-grid')) return;
            try {
                const res = await fetch(`${currentEndpoint}?api=get_products`, { cache: 'no-store' });
                const json = await res.json();
                if (json && json.success) { PRODUCTS = json.data || []; renderProducts(); renderCart(); }
            } catch (e) {}
        }
        (function initProduk() {
            updateCartBadge();
            if (!document.getElementById('prod-grid')) return;
            const bankSel = document.getElementById('co-bank');
            if (bankSel) bankSel.innerHTML = PAY_BANKS.map(b => `<option value="${escapeHtml(b.key)}">${escapeHtml(b.label)}</option>`).join('');
            const addr = document.getElementById('co-address');
            if (addr && !addr.value) addr.value = DEFAULT_ADDRESS || '';
            renderProducts(); renderCart();
            setInterval(fetchProducts, 5000);
        })();

        let chatInterval;
        async function loadChat() {
            let tid = localStorage.getItem('tk_active_ticket') || document.getElementById('active-ticket-id').value;
            if (!tid) return;
            try {
                let res = await fetch(`${currentEndpoint}?api=get_chat&ticket_id=${tid}`);
                let json = await res.json();
                const box = document.getElementById('chat-messages');
                if (!box) return;
                box.innerHTML = json.data.map(m => `
                    <div class="p-3 rounded-xl text-xs font-medium w-fit max-w-[85%] ${m.sender=='customer'?'bg-sky-500 text-white ml-auto rounded-br-none':'bg-white border rounded-bl-none'} ${m.sender=='system'?'bg-slate-200 text-slate-500 mx-auto text-[10px]':''}">
                        ${m.sender!='system'?`<b class="block text-[9px] mb-1 uppercase opacity-70">${m.sender=='customer'?'Anda':'ADMIN'}</b>`:''}
                        <div>${m.message}</div>
                    </div>`).join('');
                box.scrollTop = box.scrollHeight;
            } catch(e) {}
        }
        async function sendChat() {
            let tid = localStorage.getItem('tk_active_ticket') || document.getElementById('active-ticket-id').value;
            let inp = document.getElementById('chat-input');
            if (!inp.value.trim() || !tid) return;
            await fetch(`${currentEndpoint}?api=send_chat`, { method:'POST', body: JSON.stringify({ticket_id: tid, message: inp.value}) });
            inp.value = ''; loadChat();
        }
        function showLoading(){ document.getElementById('loading-overlay').classList.remove('hidden'); document.getElementById('loading-overlay').classList.add('flex'); }
        function hideLoading(){ document.getElementById('loading-overlay').classList.add('hidden'); document.getElementById('loading-overlay').classList.remove('flex'); }
        function handleImageSelection(inputElement) {
            let file = inputElement.files[0]; if (!file) return;
            showLoading();
            let reader = new FileReader();
            reader.onload = function(e) {
                let img = new Image();
                img.onload = function() {
                    let canvas = document.createElement('canvas');
                    let ctx = canvas.getContext('2d');
                    let max_size = 800, w = img.width, h = img.height;
                    if (w > h) { if (w > max_size) { h *= max_size/w; w = max_size; } }
                    else { if (h > max_size) { w *= max_size/h; h = max_size; } }
                    canvas.width = w; canvas.height = h;
                    ctx.drawImage(img, 0, 0, w, h);
                    let rawBase64Data = canvas.toDataURL('image/jpeg', 0.6).split(',')[1];
                    let tid = localStorage.getItem('tk_active_ticket') || document.getElementById('active-ticket-id').value;
                    if (!tid || !rawBase64Data) { alert('Sesi tidak valid'); hideLoading(); return; }
                    let formData = new FormData();
                    formData.append('ticket_id', tid);
                    formData.append('image_base64', rawBase64Data);
                    fetch(`${currentEndpoint}?api=upload_chat_image`, { method:'POST', body: formData })
                        .then(r => r.json()).then(json => { hideLoading(); if (json.success) loadChat(); else alert(json.error||'Gagal upload'); })
                        .catch(() => { hideLoading(); alert('Kesalahan jaringan'); });
                    inputElement.value = '';
                };
                img.onerror = function(){ hideLoading(); alert('Gagal proses gambar'); inputElement.value=''; };
                img.src = e.target.result;
            };
            reader.readAsDataURL(file);
        }
        function openChat(id, title) {
            localStorage.setItem('tk_active_ticket', id);
            document.getElementById('active-ticket-id').value = id;
            document.getElementById('chat-title').innerText = 'Chat Kendala: ' + title;
            document.getElementById('chat-modal').classList.remove('hidden');
            document.getElementById('chat-modal').classList.add('flex');
            loadChat(); chatInterval = setInterval(loadChat, 3000);
        }
        function closeChat() {
            localStorage.removeItem('tk_active_ticket');
            document.getElementById('chat-modal').classList.add('hidden');
            document.getElementById('chat-modal').classList.remove('flex');
            clearInterval(chatInterval);
        }
    </script>
</body>
</html>