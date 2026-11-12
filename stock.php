<?php
// ==========================================
// PENGATURAN DATABASE & API ADMIN
// ==========================================
$db_host = "127.0.0.1";
$db_user = "tekh7457_admin";
$db_pass = "Tekunin2026";
$db_name = "tekh7457_chat";
$api_key = "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";
// llama-3.3-70b-versatile = Enterprise-only; pakai model publik yang support tools
$ai_model = "openai/gpt-oss-20b";
$batas_menipis = 5;

date_default_timezone_set('Asia/Jakarta');

try {
    $pdo = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8mb4", $db_user, $db_pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    $pdo->exec("SET time_zone = '+07:00'");
} catch (Exception $e) {
    http_response_code(500);
    die("Koneksi database gagal. Periksa pengaturan database.");
}

// Group = kata saja (CCTV, NVR). Nomor unik hanya di SN (CCTV-001).
$pdo->exec("CREATE TABLE IF NOT EXISTS stok_barang (
    id INT AUTO_INCREMENT PRIMARY KEY,
    kode VARCHAR(40) NOT NULL UNIQUE,
    nama VARCHAR(120) NOT NULL,
    satuan VARCHAR(20) NOT NULL DEFAULT 'pcs',
    dibuat_pada DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB");

$pdo->exec("CREATE TABLE IF NOT EXISTS stok_sn (
    id INT AUTO_INCREMENT PRIMARY KEY,
    barang_id INT NOT NULL,
    sn VARCHAR(60) NOT NULL UNIQUE,
    status ENUM('tersedia','terpakai') NOT NULL DEFAULT 'tersedia',
    keterangan VARCHAR(255) DEFAULT '',
    sumber VARCHAR(20) NOT NULL DEFAULT 'manual',
    masuk_pada DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    keluar_pada DATETIME NULL,
    keluar_ket VARCHAR(255) DEFAULT '',
    FOREIGN KEY (barang_id) REFERENCES stok_barang(id) ON DELETE CASCADE,
    INDEX idx_barang_status (barang_id, status)
) ENGINE=InnoDB");

$pdo->exec("CREATE TABLE IF NOT EXISTS stok_riwayat (
    id INT AUTO_INCREMENT PRIMARY KEY,
    barang_id INT NOT NULL,
    jenis ENUM('masuk','keluar') NOT NULL,
    jumlah INT NOT NULL,
    sn_list TEXT NOT NULL DEFAULT '',
    stok_sesudah INT NOT NULL,
    keterangan VARCHAR(255) DEFAULT '',
    sumber VARCHAR(20) NOT NULL DEFAULT 'manual',
    waktu DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (barang_id) REFERENCES stok_barang(id) ON DELETE CASCADE
) ENGINE=InnoDB");

try {
    $pdo->exec("ALTER TABLE stok_riwayat ADD COLUMN sn_list TEXT NOT NULL DEFAULT '' AFTER jumlah");
} catch (Exception $e) { /* sudah ada */ }

function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

function normalisasi_kode_group(string $kode): string {
    $kode = strtoupper(trim($kode));
    $kode = preg_replace('/[^A-Z\s\-]/', '', $kode);
    $kode = preg_replace('/[\s\-]+/', '', $kode);
    return $kode;
}

function hitung_stok(PDO $pdo, int $barang_id): int {
    $st = $pdo->prepare("SELECT COUNT(*) FROM stok_sn WHERE barang_id = ? AND status = 'tersedia'");
    $st->execute([$barang_id]);
    return (int)$st->fetchColumn();
}

function hitung_terpakai(PDO $pdo, int $barang_id): int {
    $st = $pdo->prepare("SELECT COUNT(*) FROM stok_sn WHERE barang_id = ? AND status = 'terpakai'");
    $st->execute([$barang_id]);
    return (int)$st->fetchColumn();
}

function cari_barang(PDO $pdo, string $q): array {
    $q = trim($q);
    if ($q === '') return [];
    $kode = normalisasi_kode_group($q);
    $st = $pdo->prepare("SELECT * FROM stok_barang WHERE kode = ? OR nama = ? OR kode = ?");
    $st->execute([$q, $q, $kode]);
    $tepat = $st->fetchAll();
    if (count($tepat) === 1) return $tepat;
    $st = $pdo->prepare("SELECT * FROM stok_barang WHERE nama LIKE ? OR kode LIKE ? ORDER BY nama LIMIT 8");
    $st->execute(["%$q%", "%$q%"]);
    return $st->fetchAll();
}

function sn_masuk(PDO $pdo, int $barang_id, array $sns, string $ket, string $sumber): array {
    // 1) Normalisasi
    $raw = array_map(fn($s) => strtoupper(trim((string)$s)), $sns);
    $raw = array_values(array_filter($raw, fn($s) => $s !== ''));
    if (!$raw) return ['ok' => false, 'error' => 'Daftar SN kosong.'];

    // 2) Tolak jika ada SN yang sama di dalam input (batch utuh ditolak)
    $counts = array_count_values($raw);
    $dup_input = array_keys(array_filter($counts, fn($c) => $c > 1));
    if ($dup_input) {
        return [
            'ok' => false,
            'error' => 'Ditolak: SN duplikat pada input (' . implode(', ', $dup_input) . '). Seluruh batch tidak disimpan.',
        ];
    }
    $sns = $raw;

    $pdo->beginTransaction();
    try {
        $st = $pdo->prepare("SELECT * FROM stok_barang WHERE id = ? FOR UPDATE");
        $st->execute([$barang_id]);
        $b = $st->fetch();
        if (!$b) {
            $pdo->rollBack();
            return ['ok' => false, 'error' => 'Group barang tidak ditemukan.'];
        }

        // 3) Kunci & cek SN yang sudah ada di DB — jika ada SATU saja yang bentrok, tolak SELURUH batch
        $placeholders = implode(',', array_fill(0, count($sns), '?'));
        $cek = $pdo->prepare("SELECT sn FROM stok_sn WHERE sn IN ($placeholders) FOR UPDATE");
        $cek->execute($sns);
        $sudah = $cek->fetchAll(PDO::FETCH_COLUMN);
        if ($sudah) {
            $pdo->rollBack();
            return [
                'ok' => false,
                'error' => 'Ditolak: SN sudah terdaftar (' . implode(', ', $sudah) . '). Seluruh batch tidak disimpan.',
            ];
        }

        // 4) Insert semua SN (hanya jalan jika lolos cek di atas)
        $ins = $pdo->prepare("INSERT INTO stok_sn (barang_id, sn, status, keterangan, sumber, masuk_pada) VALUES (?,?, 'tersedia', ?, ?, NOW())");
        foreach ($sns as $sn) {
            $ins->execute([$barang_id, $sn, $ket, $sumber]);
        }

        $stok_sekarang = hitung_stok($pdo, $barang_id);
        $pdo->prepare("INSERT INTO stok_riwayat (barang_id, jenis, jumlah, sn_list, stok_sesudah, keterangan, sumber, waktu)
            VALUES (?,?,?,?,?,?,?,NOW())")
            ->execute([$barang_id, 'masuk', count($sns), implode(', ', $sns), $stok_sekarang, $ket, $sumber]);

        $waktu = $pdo->query("SELECT DATE_FORMAT(NOW(), '%d-%m-%Y %H:%i:%s')")->fetchColumn();
        $pdo->commit();
        return [
            'ok' => true,
            'barang' => $b['nama'],
            'kode' => $b['kode'],
            'jenis' => 'masuk',
            'jumlah' => count($sns),
            'sn' => $sns,
            'sn_gagal' => [],
            'stok_sekarang' => $stok_sekarang,
            'satuan' => $b['satuan'],
            'waktu' => $waktu . ' WIB',
        ];
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        // Unique constraint = race condition: perlakukan sebagai duplikat batch
        return [
            'ok' => false,
            'error' => 'Ditolak: SN bentrok/duplikat. Seluruh batch tidak disimpan.',
        ];
    }
}

/** Cari atau buat group dari nama/kode + satuan */
function pastikan_group(PDO $pdo, string $group_input, string $satuan = 'pcs'): array {
    $nama = trim($group_input);
    $kode = normalisasi_kode_group($nama);
    if ($kode === '') return ['ok' => false, 'error' => 'Group tidak valid (harus kata, tanpa angka).'];
    $satuan = trim($satuan) ?: 'pcs';
    $kanonik = kunci_group_kanonik($nama, $kode);

    // Cari by kode / nama exact
    $st = $pdo->prepare("SELECT * FROM stok_barang WHERE kode = ? OR kode = ? OR UPPER(TRIM(nama)) = UPPER(?) LIMIT 1");
    $st->execute([$kode, $kanonik, $nama]);
    $row = $st->fetch();
    if ($row) return ['ok' => true, 'barang' => $row];

    // Cari by kunci kanonik (CCTV Analog → CCTV)
    foreach ($pdo->query("SELECT * FROM stok_barang")->fetchAll() as $r) {
        if (kunci_group_kanonik($r['nama'], $r['kode']) === $kanonik) {
            return ['ok' => true, 'barang' => $r];
        }
    }

    // Simpan dengan nama/kode kanonik agar tidak pecah lagi
    $namaSimpan = ($kanonik === 'CCTV') ? 'CCTV' : $nama;
    $kodeSimpan = $kanonik ?: $kode;
    try {
        $pdo->prepare("INSERT INTO stok_barang (kode, nama, satuan) VALUES (?,?,?)")->execute([$kodeSimpan, $namaSimpan, $satuan]);
        $id = (int)$pdo->lastInsertId();
        $row = $pdo->query("SELECT * FROM stok_barang WHERE id = $id")->fetch();
        return ['ok' => true, 'barang' => $row, 'baru' => true];
    } catch (Exception $e) {
        $st->execute([$kodeSimpan, $kodeSimpan, $namaSimpan]);
        $row = $st->fetch();
        if ($row) return ['ok' => true, 'barang' => $row];
        return ['ok' => false, 'error' => 'Gagal membuat group.'];
    }
}

/**
 * Gabungkan group duplikat (nama/kode mirip → satu group).
 * SN & riwayat dipindah ke group utama, group ekstra dihapus.
 */
/** Kunci kanonik group: "CCTV Analog", "cctv" → CCTV */
function kunci_group_kanonik(string $nama, string $kode = ''): string {
    $n = normalisasi_kode_group($nama !== '' ? $nama : $kode);
    if ($n === '') return '';
    $alias = [
        'CCTVANALOG' => 'CCTV',
        'CCTVANALOGUE' => 'CCTV',
        'CCTVCAMERA' => 'CCTV',
        'CCTVIP' => 'CCTV',
        'IPCCTV' => 'CCTV',
        'CCTVHD' => 'CCTV',
        'CCTVWIRELESS' => 'CCTV',
        'IPCAM' => 'IPCAMERA',
        'IPCAMERAS' => 'IPCAMERA',
        'WIRELESSCAMERA' => 'WIRELESS',
        'WIRELESSCAM' => 'WIRELESS',
        'KABELCOAXIAL' => 'COAXIAL',
        'COAX' => 'COAXIAL',
        'KABELRG59' => 'RG59',
        'KABELRJ45' => 'RJ45',
        'RJ45CABLE' => 'RJ45',
        'HARDDISKDRIVE' => 'HARDDISK',
        'HDD' => 'HARDDISK',
    ];
    if (isset($alias[$n])) return $alias[$n];
    // CCTV IP, CCTV Analog, IP CCTV, dll.
    if (str_contains($n, 'CCTV')) return 'CCTV';
    $raw = strtoupper($nama . ' ' . $kode);
    if (preg_match('/\bCCTV\b/', $raw)) return 'CCTV';
    return $n;
}

/** Pindahkan semua SN+riwayat dari group $fromId ke $toId, lalu hapus $fromId */
function pindah_dan_hapus_group(PDO $pdo, int $fromId, int $toId): bool {
    if ($fromId === $toId || $fromId <= 0 || $toId <= 0) return false;
    try {
        $pdo->beginTransaction();
        $sns = $pdo->prepare("SELECT id, sn FROM stok_sn WHERE barang_id = ?");
        $sns->execute([$fromId]);
        foreach ($sns->fetchAll() as $s) {
            try {
                $pdo->prepare("UPDATE stok_sn SET barang_id = ? WHERE id = ?")->execute([$toId, $s['id']]);
            } catch (Exception $e) {
                $pdo->prepare("DELETE FROM stok_sn WHERE id = ?")->execute([$s['id']]);
            }
        }
        $pdo->prepare("UPDATE stok_riwayat SET barang_id = ? WHERE barang_id = ?")->execute([$toId, $fromId]);
        $pdo->prepare("DELETE FROM stok_sn WHERE barang_id = ?")->execute([$fromId]);
        $pdo->prepare("DELETE FROM stok_riwayat WHERE barang_id = ?")->execute([$fromId]);
        $pdo->prepare("DELETE FROM stok_barang WHERE id = ?")->execute([$fromId]);
        $pdo->commit();
        return true;
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        return false;
    }
}

function gabung_group_duplikat(PDO $pdo): void {
    // --- Khusus: paksa semua variasi CCTV (Analog, IP, HD, dll.) → CCTV ---
    $semua = $pdo->query("SELECT * FROM stok_barang ORDER BY id ASC")->fetchAll();
    $cctvIds = [];
    $analogIds = [];
    foreach ($semua as $r) {
        $namaU = strtoupper(trim($r['nama']));
        $kodeU = strtoupper(trim($r['kode']));
        $kan = kunci_group_kanonik($r['nama'], $r['kode']);
        $isCctv = ($kan === 'CCTV')
            || str_contains($namaU, 'CCTV')
            || str_contains($kodeU, 'CCTV');
        if ($isCctv) {
            if ($namaU === 'CCTV' || $kodeU === 'CCTV') {
                $cctvIds[] = (int)$r['id'];
            } else {
                // CCTV Analog, CCTV IP, CCTV HD, dll.
                $analogIds[] = (int)$r['id'];
            }
        }
    }
    // Pastikan ada group utama bernama CCTV
    $idUtama = $cctvIds[0] ?? 0;
    if (!$idUtama && $analogIds) {
        // Rename group analog pertama menjadi CCTV
        $idUtama = $analogIds[0];
        try {
            $pdo->prepare("UPDATE stok_barang SET nama = 'CCTV', kode = 'CCTV' WHERE id = ?")->execute([$idUtama]);
        } catch (Exception $e) {
            $pdo->prepare("UPDATE stok_barang SET nama = 'CCTV' WHERE id = ?")->execute([$idUtama]);
        }
        array_shift($analogIds);
    } elseif ($idUtama) {
        try {
            $pdo->prepare("UPDATE stok_barang SET nama = 'CCTV', kode = 'CCTV' WHERE id = ?")->execute([$idUtama]);
        } catch (Exception $e) { /* ok */ }
    }
    // Gabungkan sisa CCTV ke utama
    foreach (array_merge(array_slice($cctvIds, 1), $analogIds) as $did) {
        if ($did !== $idUtama) pindah_dan_hapus_group($pdo, $did, $idUtama);
    }

    // --- Umum: gabungkan group dengan kunci kanonik sama ---
    $rows = $pdo->query("SELECT * FROM stok_barang ORDER BY id ASC")->fetchAll();
    if (count($rows) < 2) return;

    $buckets = [];
    foreach ($rows as $r) {
        $key = kunci_group_kanonik($r['nama'] ?? '', $r['kode'] ?? '');
        if ($key === '') $key = 'ID' . $r['id'];
        $buckets[$key][] = $r;
    }

    foreach ($buckets as $key => $list) {
        if (count($list) < 2) continue;
        usort($list, function ($a, $b) use ($pdo, $key) {
            $exactA = (normalisasi_kode_group($a['nama']) === $key || strtoupper(trim($a['nama'])) === $key) ? 0 : 1;
            $exactB = (normalisasi_kode_group($b['nama']) === $key || strtoupper(trim($b['nama'])) === $key) ? 0 : 1;
            if ($exactA !== $exactB) return $exactA <=> $exactB;
            $ca = hitung_stok($pdo, (int)$a['id']) + hitung_terpakai($pdo, (int)$a['id']);
            $cb = hitung_stok($pdo, (int)$b['id']) + hitung_terpakai($pdo, (int)$b['id']);
            if ($ca !== $cb) return $cb <=> $ca;
            return (int)$a['id'] <=> (int)$b['id'];
        });
        $uid = (int)$list[0]['id'];
        $namaIdeal = ($key === 'CCTV') ? 'CCTV' : $list[0]['nama'];
        try {
            $pdo->prepare("UPDATE stok_barang SET nama = ?, kode = ? WHERE id = ?")->execute([$namaIdeal, $key, $uid]);
        } catch (Exception $e) {
            try { $pdo->prepare("UPDATE stok_barang SET nama = ? WHERE id = ?")->execute([$namaIdeal, $uid]); } catch (Exception $e2) {}
        }
        for ($i = 1; $i < count($list); $i++) {
            pindah_dan_hapus_group($pdo, (int)$list[$i]['id'], $uid);
        }
    }
}

function sn_keluar(PDO $pdo, array $sns, string $ket, string $sumber): array {
    $sns = array_values(array_unique(array_filter(array_map(function ($s) {
        return strtoupper(trim($s));
    }, $sns))));
    if (!$sns) return ['ok' => false, 'error' => 'Daftar SN kosong.'];

    $pdo->beginTransaction();
    try {
        $detail = [];
        $by_barang = [];
        foreach ($sns as $sn) {
            $st = $pdo->prepare("SELECT s.*, b.nama, b.kode, b.satuan FROM stok_sn s JOIN stok_barang b ON b.id = s.barang_id WHERE s.sn = ? FOR UPDATE");
            $st->execute([$sn]);
            $row = $st->fetch();
            if (!$row) {
                $pdo->rollBack();
                return ['ok' => false, 'error' => "SN $sn tidak ditemukan."];
            }
            if ($row['status'] !== 'tersedia') {
                $pdo->rollBack();
                return ['ok' => false, 'error' => "SN $sn sudah terpakai (tidak tersedia)."];
            }
            $pdo->prepare("UPDATE stok_sn SET status = 'terpakai', keluar_pada = NOW(), keluar_ket = ? WHERE id = ?")
                ->execute([$ket, $row['id']]);
            $detail[] = ['sn' => $sn, 'barang' => $row['nama'], 'kode' => $row['kode'], 'barang_id' => (int)$row['barang_id'], 'satuan' => $row['satuan']];
            $by_barang[(int)$row['barang_id']][] = $sn;
        }

        $waktu = $pdo->query("SELECT DATE_FORMAT(NOW(), '%d-%m-%Y %H:%i:%s')")->fetchColumn();
        $hasil_group = [];
        foreach ($by_barang as $bid => $list) {
            $stok_sekarang = hitung_stok($pdo, $bid);
            $pdo->prepare("INSERT INTO stok_riwayat (barang_id, jenis, jumlah, sn_list, stok_sesudah, keterangan, sumber, waktu)
                VALUES (?,?,?,?,?,?,?,NOW())")
                ->execute([$bid, 'keluar', count($list), implode(', ', $list), $stok_sekarang, $ket, $sumber]);
            $nama = '';
            $sat = 'pcs';
            foreach ($detail as $d) {
                if ($d['barang_id'] === $bid) { $nama = $d['barang']; $sat = $d['satuan']; break; }
            }
            $hasil_group[] = [
                'barang' => $nama,
                'jumlah' => count($list),
                'sn' => $list,
                'stok_sekarang' => $stok_sekarang,
                'satuan' => $sat,
            ];
        }
        $pdo->commit();
        return [
            'ok' => true,
            'jenis' => 'keluar',
            'jumlah' => count($detail),
            'detail' => $detail,
            'per_group' => $hasil_group,
            'keterangan' => $ket,
            'waktu' => $waktu . ' WIB',
        ];
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        return ['ok' => false, 'error' => 'Gagal mencatat SN keluar.'];
    }
}

function generate_sn(PDO $pdo, string $group_kode, int $jumlah): array {
    $prefix = normalisasi_kode_group($group_kode);
    if ($prefix === '') $prefix = 'ITEM';
    // Ambil semua SN existing untuk prefix ini agar tidak bentrok (termasuk nomor loncat)
    $st = $pdo->prepare("SELECT sn FROM stok_sn WHERE sn LIKE ?");
    $st->execute([$prefix . '-%']);
    $existing = array_flip($st->fetchAll(PDO::FETCH_COLUMN));
    $n = 0;
    foreach (array_keys($existing) as $sn) {
        if (preg_match('/-(\d+)$/', $sn, $m)) $n = max($n, (int)$m[1]);
    }
    $out = [];
    $i = 1;
    while (count($out) < $jumlah && $i < 10000) {
        $candidate = sprintf('%s-%03d', $prefix, $n + $i);
        $i++;
        if (isset($existing[$candidate])) continue; // skip jika sudah ada
        $out[] = $candidate;
    }
    return $out;
}

function html_stok(PDO $pdo, int $batas): string {
    $rows = $pdo->query("
        SELECT b.id, b.kode, b.nama, b.satuan,
            COALESCE(SUM(s.status = 'tersedia'), 0) AS tersedia,
            COALESCE(SUM(s.status = 'terpakai'), 0) AS terpakai,
            COUNT(s.id) AS total
        FROM stok_barang b
        LEFT JOIN stok_sn s ON s.barang_id = b.id
        GROUP BY b.id
        ORDER BY b.nama
    ")->fetchAll();
    if (!$rows) return '<tr><td colspan="6" class="kosong">Belum ada group barang. Tambahkan lewat formulir di bawah.</td></tr>';
    $o = '';
    foreach ($rows as $r) {
        $tersedia = (int)$r['tersedia'];
        $terpakai = (int)$r['terpakai'];
        $total = (int)$r['total'];
        $sisa = $tersedia;
        $sat = h($r['satuan']);
        $id = (int)$r['id'];
        $cls = $sisa == 0 ? 'habis' : ($sisa <= $batas ? 'tipis' : 'aman');
        if ($sisa == 0) {
            $tag = '<span class="tag merah">Habis</span>';
        } elseif ($sisa <= $batas) {
            $tag = '<span class="tag kuning">Menipis</span>';
        } else {
            $tag = '<span class="tag hijau">Aman</span>';
        }
        $payload = htmlspecialchars(json_encode([
            'id' => $id,
            'nama' => $r['nama'],
            'kode' => $r['kode'],
            'satuan' => $r['satuan'],
        ], JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8');
        $o .= '<tr class="' . $cls . '">'
            . '<td class="col-group"><b>' . h($r['nama']) . '</b></td>'
            . '<td class="col-status">' . $tag . '</td>'
            . '<td class="num">' . $total . ' <span class="sat">' . $sat . '</span></td>'
            . '<td class="num">' . $terpakai . ' <span class="sat">' . $sat . '</span></td>'
            . '<td class="num col-sisa"><b>' . $sisa . '</b> <span class="sat">' . $sat . '</span></td>'
            . '<td class="aksi">'
            . '<button type="button" class="btn-edit" onclick="openEditGroup(' . $payload . ')">Edit</button> '
            . '<form method="post" style="display:inline" onsubmit="return confirm(\'Hapus group ' . h($r['nama']) . '? Semua SN & riwayat group ini ikut terhapus.\')">'
            . '<input type="hidden" name="aksi" value="hapus_group">'
            . '<input type="hidden" name="group_id" value="' . $id . '">'
            . '<button type="submit" class="btn-hapus">Hapus</button>'
            . '</form>'
            . '</td></tr>';
    }
    return $o;
}

function html_sn_tersedia(PDO $pdo, ?int $barang_id = null): string {
    $sql = "SELECT s.id, s.sn, s.keterangan, s.status, DATE_FORMAT(s.masuk_pada,'%d-%m-%Y') m, b.nama, b.id AS barang_id
            FROM stok_sn s JOIN stok_barang b ON b.id = s.barang_id
            WHERE s.status = 'tersedia'";
    $params = [];
    if ($barang_id) {
        $sql .= " AND s.barang_id = ?";
        $params[] = $barang_id;
    }
    $sql .= " ORDER BY b.nama, s.sn LIMIT 120";
    $st = $pdo->prepare($sql);
    $st->execute($params);
    $rows = $st->fetchAll();
    if (!$rows) return '<tr><td colspan="4" class="kosong">Tidak ada SN tersedia.</td></tr>';
    $o = '';
    foreach ($rows as $r) {
        $id = (int)$r['id'];
        $payload = htmlspecialchars(json_encode([
            'id' => $id,
            'sn' => $r['sn'],
            'keterangan' => $r['keterangan'] ?? '',
            'nama' => $r['nama'],
        ], JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8');
        $o .= '<tr>'
            . '<td class="mono">' . h($r['sn']) . '</td>'
            . '<td>' . h($r['nama']) . '</td>'
            . '<td>' . h($r['m']) . '</td>'
            . '<td class="aksi">'
            . '<button type="button" class="btn-edit" onclick="openEditSn(' . $payload . ')">Edit</button> '
            . '<form method="post" style="display:inline" onsubmit="return confirm(\'Hapus SN ' . h($r['sn']) . '?\')">'
            . '<input type="hidden" name="aksi" value="hapus_sn">'
            . '<input type="hidden" name="sn_id" value="' . $id . '">'
            . '<button type="submit" class="btn-hapus">Hapus</button>'
            . '</form>'
            . '</td></tr>';
    }
    return $o;
}

function edit_group(PDO $pdo, int $id, string $nama, string $satuan): array {
    $nama = trim($nama);
    $satuan = trim($satuan) ?: 'pcs';
    if ($nama === '') return ['ok' => false, 'error' => 'Nama group wajib diisi.'];
    $st = $pdo->prepare("SELECT * FROM stok_barang WHERE id = ?");
    $st->execute([$id]);
    if (!$st->fetch()) return ['ok' => false, 'error' => 'Group tidak ditemukan.'];
    // Cek nama bentrok dengan group lain
    $cek = $pdo->prepare("SELECT id FROM stok_barang WHERE UPPER(TRIM(nama)) = UPPER(?) AND id <> ? LIMIT 1");
    $cek->execute([$nama, $id]);
    if ($cek->fetch()) return ['ok' => false, 'error' => "Nama group \"$nama\" sudah dipakai group lain."];
    $kode = normalisasi_kode_group($nama);
    if ($kode === '') {
        $pdo->prepare("UPDATE stok_barang SET nama = ?, satuan = ? WHERE id = ?")->execute([$nama, $satuan, $id]);
    } else {
        try {
            $pdo->prepare("UPDATE stok_barang SET nama = ?, satuan = ?, kode = ? WHERE id = ?")->execute([$nama, $satuan, $kode, $id]);
        } catch (Exception $e) {
            $pdo->prepare("UPDATE stok_barang SET nama = ?, satuan = ? WHERE id = ?")->execute([$nama, $satuan, $id]);
        }
    }
    return ['ok' => true, 'msg' => "Group diperbarui: $nama ($satuan)."];
}

function hapus_group(PDO $pdo, int $id): array {
    $st = $pdo->prepare("SELECT * FROM stok_barang WHERE id = ?");
    $st->execute([$id]);
    $g = $st->fetch();
    if (!$g) return ['ok' => false, 'error' => 'Group tidak ditemukan.'];
    try {
        $pdo->beginTransaction();
        // Hapus anak dulu (aman meski FK cascade tidak aktif)
        $pdo->prepare("DELETE FROM stok_sn WHERE barang_id = ?")->execute([$id]);
        $pdo->prepare("DELETE FROM stok_riwayat WHERE barang_id = ?")->execute([$id]);
        $pdo->prepare("DELETE FROM stok_barang WHERE id = ?")->execute([$id]);
        $pdo->commit();
        return ['ok' => true, 'msg' => "Group {$g['nama']} beserta SN & riwayatnya dihapus."];
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        return ['ok' => false, 'error' => 'Gagal menghapus group: ' . $e->getMessage()];
    }
}

function edit_sn(PDO $pdo, int $id, string $sn_baru, string $keterangan): array {
    $sn_baru = strtoupper(trim($sn_baru));
    if ($sn_baru === '') return ['ok' => false, 'error' => 'SN tidak boleh kosong.'];
    $st = $pdo->prepare("SELECT * FROM stok_sn WHERE id = ?");
    $st->execute([$id]);
    $row = $st->fetch();
    if (!$row) return ['ok' => false, 'error' => 'SN tidak ditemukan.'];
    if ($row['status'] !== 'tersedia') return ['ok' => false, 'error' => 'Hanya SN berstatus tersedia yang bisa diedit.'];
    if ($sn_baru !== $row['sn']) {
        $cek = $pdo->prepare("SELECT id FROM stok_sn WHERE sn = ? AND id <> ?");
        $cek->execute([$sn_baru, $id]);
        if ($cek->fetch()) return ['ok' => false, 'error' => "SN $sn_baru sudah terdaftar."];
    }
    $pdo->prepare("UPDATE stok_sn SET sn = ?, keterangan = ? WHERE id = ?")->execute([$sn_baru, trim($keterangan), $id]);
    return ['ok' => true, 'msg' => "SN diperbarui: $sn_baru."];
}

function hapus_sn(PDO $pdo, int $id): array {
    $st = $pdo->prepare("SELECT * FROM stok_sn WHERE id = ?");
    $st->execute([$id]);
    $row = $st->fetch();
    if (!$row) return ['ok' => false, 'error' => 'SN tidak ditemukan.'];
    if ($row['status'] !== 'tersedia') return ['ok' => false, 'error' => 'SN sudah terpakai. Batalkan transaksi keluar di riwayat dulu.'];
    $pdo->prepare("DELETE FROM stok_sn WHERE id = ?")->execute([$id]);
    return ['ok' => true, 'msg' => "SN {$row['sn']} dihapus."];
}

function html_riwayat(PDO $pdo): string {
    $rows = $pdo->query("SELECT r.*, b.nama, b.satuan, DATE_FORMAT(r.waktu,'%d-%m-%Y %H:%i:%s') w
        FROM stok_riwayat r JOIN stok_barang b ON b.id = r.barang_id
        ORDER BY r.id DESC LIMIT 50")->fetchAll();
    if (!$rows) return '<tr><td colspan="8" class="kosong">Belum ada transaksi.</td></tr>';
    $o = '';
    foreach ($rows as $r) {
        $m = $r['jenis'] === 'masuk';
        $id = (int)$r['id'];
        $ket = trim((string)($r['keterangan'] ?? ''));
        $o .= '<tr data-rid="' . $id . '">'
            . '<td class="mono">' . h($r['w']) . '</td>'
            . '<td>' . h($r['nama']) . '</td>'
            . '<td><span class="tag ' . ($m ? 'hijau' : 'merah') . '">' . ($m ? 'Masuk' : 'Keluar') . '</span></td>'
            . '<td class="num">' . ($m ? '+' : '−') . (int)$r['jumlah'] . ' ' . h($r['satuan']) . '</td>'
            . '<td class="mono" style="font-size:12px;max-width:180px;word-break:break-all">' . h($r['sn_list'] ?? '') . '</td>'
            . '<td style="font-size:13px;max-width:200px">' . ($ket !== '' ? h($ket) : '<span class="hint">—</span>') . '</td>'
            . '<td class="num">' . (int)$r['stok_sesudah'] . ' ' . h($r['satuan']) . '</td>'
            . '<td class="aksi">'
            . '<button type="button" class="btn-edit" onclick="openEditRiwayat(' . $id . ','
            . htmlspecialchars(json_encode([
                'id' => $id,
                'jenis' => $r['jenis'],
                'sn_list' => $r['sn_list'] ?? '',
                'keterangan' => $r['keterangan'] ?? '',
                'nama' => $r['nama'],
            ], JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8')
            . ')">Edit</button> '
            . '<form method="post" style="display:inline" onsubmit="return confirm(\'Hapus transaksi ini? Efek stok/SN akan dibatalkan.\')">'
            . '<input type="hidden" name="aksi" value="hapus_riwayat">'
            . '<input type="hidden" name="riwayat_id" value="' . $id . '">'
            . '<button type="submit" class="btn-hapus">Hapus</button>'
            . '</form>'
            . '</td></tr>';
    }
    return $o;
}

/** Hapus riwayat + batalkan efek ke stok_sn */
function hapus_riwayat(PDO $pdo, int $id): array {
    $pdo->beginTransaction();
    try {
        $st = $pdo->prepare("SELECT * FROM stok_riwayat WHERE id = ? FOR UPDATE");
        $st->execute([$id]);
        $r = $st->fetch();
        if (!$r) {
            $pdo->rollBack();
            return ['ok' => false, 'error' => 'Riwayat tidak ditemukan.'];
        }
        $sns = array_values(array_filter(array_map('trim', preg_split('/\s*,\s*/', (string)($r['sn_list'] ?? '')))));
        if ($r['jenis'] === 'masuk') {
            // Batalkan masuk: hapus SN yang masih tersedia; tolak jika ada yang sudah terpakai
            foreach ($sns as $sn) {
                $s = $pdo->prepare("SELECT * FROM stok_sn WHERE sn = ? FOR UPDATE");
                $s->execute([strtoupper($sn)]);
                $row = $s->fetch();
                if (!$row) continue; // sudah tidak ada
                if ($row['status'] === 'terpakai') {
                    $pdo->rollBack();
                    return ['ok' => false, 'error' => "Tidak bisa hapus: SN $sn sudah terpakai. Batalkan keluarannya dulu."];
                }
                $pdo->prepare("DELETE FROM stok_sn WHERE id = ?")->execute([$row['id']]);
            }
        } else {
            // Batalkan keluar: kembalikan SN ke tersedia
            foreach ($sns as $sn) {
                $s = $pdo->prepare("SELECT * FROM stok_sn WHERE sn = ? FOR UPDATE");
                $s->execute([strtoupper($sn)]);
                $row = $s->fetch();
                if (!$row) continue;
                $pdo->prepare("UPDATE stok_sn SET status = 'tersedia', keluar_pada = NULL, keluar_ket = '' WHERE id = ?")
                    ->execute([$row['id']]);
            }
        }
        $pdo->prepare("DELETE FROM stok_riwayat WHERE id = ?")->execute([$id]);
        $pdo->commit();
        return ['ok' => true, 'msg' => 'Riwayat dihapus dan stok/SN dibatalkan.'];
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        return ['ok' => false, 'error' => 'Gagal menghapus riwayat.'];
    }
}

/** Edit keterangan riwayat (SN tidak diubah lewat edit untuk hindari inkonsistensi) */
function edit_riwayat(PDO $pdo, int $id, string $keterangan): array {
    $st = $pdo->prepare("SELECT id FROM stok_riwayat WHERE id = ?");
    $st->execute([$id]);
    if (!$st->fetch()) return ['ok' => false, 'error' => 'Riwayat tidak ditemukan.'];
    $pdo->prepare("UPDATE stok_riwayat SET keterangan = ? WHERE id = ?")->execute([trim($keterangan), $id]);
    return ['ok' => true, 'msg' => 'Keterangan riwayat diperbarui.'];
}

$seed_group = [
    ['CCTV',       'CCTV',              'pcs'],
    ['IPCAMERA',   'IP Camera',         'pcs'],
    ['WIRELESS',   'Wireless Camera',   'pcs'],
    ['NVR',        'NVR',               'pcs'],
    ['DVR',        'DVR',               'pcs'],
    ['HARDDISK',   'Harddisk',          'pcs'],
    ['COAXIAL',    'Kabel Coaxial',     'roll'],
    ['RG59',       'Kabel RG59',        'roll'],
    ['RJ45',       'Kabel RJ45',        'pcs'],
    ['DCMALE',     'DC Male',           'pcs'],
    ['DCFEMALE',   'DC Female',         'pcs'],
];
// Seed HANYA saat database group masih kosong (instalasi pertama).
// Jangan isi ulang group yang sengaja dihapus user.
$jumlah_group = (int)$pdo->query("SELECT COUNT(*) FROM stok_barang")->fetchColumn();
if ($jumlah_group === 0) {
    $insG = $pdo->prepare("INSERT IGNORE INTO stok_barang (kode, nama, satuan) VALUES (?,?,?)");
    foreach ($seed_group as $g) $insG->execute($g);

    $map = [];
    foreach ($pdo->query("SELECT id, kode FROM stok_barang")->fetchAll() as $r) $map[$r['kode']] = (int)$r['id'];
    $samples = [
        'CCTV'     => ['CCTV-001','CCTV-002','CCTV-003','CCTV-004','CCTV-005'],
        'IPCAMERA' => ['IPCAMERA-001','IPCAMERA-002','IPCAMERA-003'],
        'WIRELESS' => ['WIRELESS-001','WIRELESS-002'],
        'NVR'      => ['NVR-001','NVR-002'],
        'DVR'      => ['DVR-001','DVR-002'],
        'HARDDISK' => ['HARDDISK-001','HARDDISK-002','HARDDISK-003'],
        'COAXIAL'  => ['COAXIAL-001','COAXIAL-002'],
        'RG59'     => ['RG59-001','RG59-002'],
        'RJ45'     => ['RJ45-001','RJ45-002','RJ45-003','RJ45-004','RJ45-005'],
        'DCMALE'   => ['DCMALE-001','DCMALE-002','DCMALE-003'],
        'DCFEMALE' => ['DCFEMALE-001','DCFEMALE-002','DCFEMALE-003'],
    ];
    $insS = $pdo->prepare("INSERT IGNORE INTO stok_sn (barang_id, sn, status, sumber) VALUES (?,?,'tersedia','seed')");
    foreach ($samples as $kode => $list) {
        if (empty($map[$kode])) continue;
        foreach ($list as $sn) $insS->execute([$map[$kode], $sn]);
    }
}

// Bersihkan group duplikat dari versi lama
gabung_group_duplikat($pdo);

/** Export Excel (.xlsx) — 3 sheet: Group, SN, Riwayat */
function excel_xml_escape(string $s): string {
    return htmlspecialchars($s, ENT_XML1 | ENT_QUOTES, 'UTF-8');
}

function excel_sheet_xml(string $name, array $headers, array $rows): string {
    $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
        . '<sheetData>';
    // header row
    $xml .= '<row r="1">';
    $c = 0;
    foreach ($headers as $h) {
        $col = '';
        $n = $c++;
        do { $col = chr(65 + ($n % 26)) . $col; $n = intdiv($n, 26) - 1; } while ($n >= 0);
        $xml .= '<c r="' . $col . '1" t="inlineStr"><is><t>' . excel_xml_escape((string)$h) . '</t></is></c>';
    }
    $xml .= '</row>';
    $rnum = 2;
    foreach ($rows as $row) {
        $xml .= '<row r="' . $rnum . '">';
        $c = 0;
        foreach ($row as $val) {
            $col = '';
            $n = $c++;
            do { $col = chr(65 + ($n % 26)) . $col; $n = intdiv($n, 26) - 1; } while ($n >= 0);
            $isNum = is_int($val) || is_float($val) || (is_string($val) && $val !== '' && ctype_digit(ltrim($val, '-')));
            if ($isNum && $val !== '' && $val !== null) {
                $xml .= '<c r="' . $col . $rnum . '"><v>' . excel_xml_escape((string)$val) . '</v></c>';
            } else {
                $xml .= '<c r="' . $col . $rnum . '" t="inlineStr"><is><t>' . excel_xml_escape((string)$val) . '</t></is></c>';
            }
        }
        $xml .= '</row>';
        $rnum++;
    }
    $xml .= '</sheetData></worksheet>';
    return $xml;
}

function export_stok_excel(PDO $pdo): void {
    $groups = $pdo->query("
        SELECT b.nama, b.kode, b.satuan,
            COUNT(s.id) AS total,
            COALESCE(SUM(s.status = 'terpakai'), 0) AS terpakai,
            COALESCE(SUM(s.status = 'tersedia'), 0) AS sisa
        FROM stok_barang b
        LEFT JOIN stok_sn s ON s.barang_id = b.id
        GROUP BY b.id
        ORDER BY b.nama
    ")->fetchAll();

    $sns = $pdo->query("
        SELECT s.sn, b.nama, s.status, s.keterangan,
            DATE_FORMAT(s.masuk_pada,'%d-%m-%Y %H:%i') masuk,
            DATE_FORMAT(s.keluar_pada,'%d-%m-%Y %H:%i') keluar,
            s.keluar_ket
        FROM stok_sn s JOIN stok_barang b ON b.id = s.barang_id
        ORDER BY b.nama, s.sn
    ")->fetchAll();

    $riw = $pdo->query("
        SELECT DATE_FORMAT(r.waktu,'%d-%m-%Y %H:%i:%s') w, b.nama, r.jenis, r.jumlah, r.sn_list, r.stok_sesudah, r.keterangan, r.sumber
        FROM stok_riwayat r JOIN stok_barang b ON b.id = r.barang_id
        ORDER BY r.id DESC
        LIMIT 500
    ")->fetchAll();

    $cell = function ($v, bool $num = false): string {
        if ($num) return '<Cell><Data ss:Type="Number">' . htmlspecialchars((string)$v, ENT_XML1) . '</Data></Cell>';
        return '<Cell><Data ss:Type="String">' . htmlspecialchars((string)$v, ENT_XML1) . '</Data></Cell>';
    };
    $rowXml = function (array $cells) use ($cell): string {
        $o = '<Row>';
        foreach ($cells as $c) {
            if (is_array($c)) $o .= $cell($c[0], !empty($c[1]));
            else $o .= $cell($c, false);
        }
        return $o . '</Row>';
    };

    $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n"
        . '<?mso-application progid="Excel.Sheet"?>' . "\n"
        . '<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet"'
        . ' xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet">'
        . '<Styles>'
        . '<Style ss:ID="hdr"><Font ss:Bold="1"/><Interior ss:Color="#F2B705" ss:Pattern="Solid"/></Style>'
        . '</Styles>';

    // Sheet 1: Stok Group
    $xml .= '<Worksheet ss:Name="Stok Group"><Table>';
    $xml .= '<Row>'
        . '<Cell ss:StyleID="hdr"><Data ss:Type="String">Group</Data></Cell>'
        . '<Cell ss:StyleID="hdr"><Data ss:Type="String">Kode</Data></Cell>'
        . '<Cell ss:StyleID="hdr"><Data ss:Type="String">Total</Data></Cell>'
        . '<Cell ss:StyleID="hdr"><Data ss:Type="String">Terpakai</Data></Cell>'
        . '<Cell ss:StyleID="hdr"><Data ss:Type="String">Sisa Stock</Data></Cell>'
        . '<Cell ss:StyleID="hdr"><Data ss:Type="String">Satuan</Data></Cell>'
        . '</Row>';
    foreach ($groups as $g) {
        $xml .= $rowXml([$g['nama'], $g['kode'], [(int)$g['total'], true], [(int)$g['terpakai'], true], [(int)$g['sisa'], true], $g['satuan']]);
    }
    $xml .= '</Table></Worksheet>';

    // Sheet 2: SN Unit
    $xml .= '<Worksheet ss:Name="SN Unit"><Table>';
    $xml .= '<Row>'
        . '<Cell ss:StyleID="hdr"><Data ss:Type="String">SN</Data></Cell>'
        . '<Cell ss:StyleID="hdr"><Data ss:Type="String">Group</Data></Cell>'
        . '<Cell ss:StyleID="hdr"><Data ss:Type="String">Status</Data></Cell>'
        . '<Cell ss:StyleID="hdr"><Data ss:Type="String">Keterangan</Data></Cell>'
        . '<Cell ss:StyleID="hdr"><Data ss:Type="String">Masuk</Data></Cell>'
        . '<Cell ss:StyleID="hdr"><Data ss:Type="String">Keluar</Data></Cell>'
        . '<Cell ss:StyleID="hdr"><Data ss:Type="String">Ket. Keluar</Data></Cell>'
        . '</Row>';
    foreach ($sns as $s) {
        $xml .= $rowXml([$s['sn'], $s['nama'], $s['status'], $s['keterangan'] ?? '', $s['masuk'] ?? '', $s['keluar'] ?? '', $s['keluar_ket'] ?? '']);
    }
    $xml .= '</Table></Worksheet>';

    // Sheet 3: Riwayat
    $xml .= '<Worksheet ss:Name="Riwayat"><Table>';
    $xml .= '<Row>'
        . '<Cell ss:StyleID="hdr"><Data ss:Type="String">Waktu</Data></Cell>'
        . '<Cell ss:StyleID="hdr"><Data ss:Type="String">Group</Data></Cell>'
        . '<Cell ss:StyleID="hdr"><Data ss:Type="String">Jenis</Data></Cell>'
        . '<Cell ss:StyleID="hdr"><Data ss:Type="String">Jumlah</Data></Cell>'
        . '<Cell ss:StyleID="hdr"><Data ss:Type="String">SN</Data></Cell>'
        . '<Cell ss:StyleID="hdr"><Data ss:Type="String">Sisa Setelah</Data></Cell>'
        . '<Cell ss:StyleID="hdr"><Data ss:Type="String">Keterangan</Data></Cell>'
        . '<Cell ss:StyleID="hdr"><Data ss:Type="String">Sumber</Data></Cell>'
        . '</Row>';
    foreach ($riw as $r) {
        $xml .= $rowXml([$r['w'], $r['nama'], $r['jenis'], [(int)$r['jumlah'], true], $r['sn_list'] ?? '', [(int)$r['stok_sesudah'], true], $r['keterangan'] ?? '', $r['sumber']]);
    }
    $xml .= '</Table></Worksheet>';

    $xml .= '</Workbook>';

    $fname = 'stok_gudang_' . date('Ymd_His') . '.xls';
    header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $fname . '"');
    header('Cache-Control: max-age=0');
    echo $xml;
    exit;
}

if (($_GET['api'] ?? '') === 'export') {
    export_stok_excel($pdo);
}

if (($_GET['api'] ?? '') === 'chat' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json; charset=utf-8');
    $in = json_decode(file_get_contents('php://input'), true) ?: [];
    $pesan = trim($in['message'] ?? '');
    if ($pesan === '') { echo json_encode(['reply' => 'Pesan kosong.']); exit; }

    $hari = ['Minggu','Senin','Selasa','Rabu','Kamis','Jumat','Sabtu'][date('w')];
    $waktu_sekarang = date('d-m-Y H:i:s');
    $tanggal_hari_ini = date('Y-m-d');

    // Snapshot ringkas untuk konteks (AI tetap wajib verifikasi lewat tool)
    $snap_group = (int)$pdo->query("SELECT COUNT(*) FROM stok_barang")->fetchColumn();
    $snap_tersedia = (int)$pdo->query("SELECT COUNT(*) FROM stok_sn WHERE status='tersedia'")->fetchColumn();
    $snap_terpakai = (int)$pdo->query("SELECT COUNT(*) FROM stok_sn WHERE status='terpakai'")->fetchColumn();
    $snap_keluar_hari = (int)$pdo->query("SELECT COALESCE(SUM(jumlah),0) FROM stok_riwayat WHERE jenis='keluar' AND DATE(waktu)=CURDATE()")->fetchColumn();
    $snap_masuk_hari = (int)$pdo->query("SELECT COALESCE(SUM(jumlah),0) FROM stok_riwayat WHERE jenis='masuk' AND DATE(waktu)=CURDATE()")->fetchColumn();

    $sistem = <<<SYS
Kamu adalah Asisten Gudang TEKUNIN. Kamu punya akses penuh ke data dashboard stok lewat tool.
Waktu server: $hari, $waktu_sekarang WIB (tanggal $tanggal_hari_ini).

SNAPSHOT (verifikasi ulang dengan tool sebelum menjawab angka):
• Group: $snap_group
• SN tersedia: $snap_tersedia | SN terpakai: $snap_terpakai
• Hari ini masuk: $snap_masuk_hari unit | Hari ini keluar: $snap_keluar_hari unit

ATURAN DATA:
1. SELALU panggil tool untuk pertanyaan stok, riwayat, hari ini, atau statistik. Jangan mengarang angka.
2. Jangan bilang "saya tidak punya data". Jika hasil tool kosong, jawab angka 0 dengan jelas.
3. Hari ini / ringkasan / berapa keluar-masuk → tool ringkasan_dashboard atau cek_riwayat.
4. Stok group atau SN → tool cek_stok / list_sn_tersedia.
5. Catat keluar hanya dengan SN konkret.

GAYA BAHASA:
• Bahasa Indonesia formal-ramah, singkat, jelas.
• Jangan pakai markdown (**bold**, ##, backticks).
• Jangan bertele-tele. Langsung ke tabel.
• WAJIB pakai tabel teks rapi dengan garis +---+ dan spasi rata (monospace).
• Setiap kolom lebar tetap. Angka rata kanan jika memungkinkan.
• Jangan buat tabel miring atau kolom tidak sejajar.

CARA MEMBUAT TABEL (contoh pola wajib):
+--------------+----------------+
| Keterangan   | Jumlah         |
+--------------+----------------+
| Total keluar | 3 unit (3 trx) |
| Total masuk  | 0 unit (0 trx) |
+--------------+----------------+

STRUKTUR JAWABAN:

A) Keluar/masuk hari ini:
📦 Ringkasan transaksi [dd-mm-yyyy]

+--------------+----------------+
| Keterangan   | Jumlah         |
+--------------+----------------+
| Total keluar | X unit (Y trx) |
| Total masuk  | X unit (Y trx) |
+--------------+----------------+

Detail keluar:
+----+--------+------------------+----------+----------------------+
| No | Jam    | Group            | SN       | Keterangan           |
+----+--------+------------------+----------+----------------------+
| 1  | 02:00  | DVR              | DVR-002  | Ganti unit           |
| 2  | 01:52  | Wireless Camera  | WLC-001  | Proyek A             |
+----+--------+------------------+----------+----------------------+

Jika kosong: "Tidak ada transaksi pada tanggal ini."

B) Ringkasan / cek semua stok:
📊 Ringkasan stok gudang

+------------+-------+
| Metrik     | Nilai |
+------------+-------+
| Group      |    11 |
| Total SN   |    24 |
| Tersedia   |    23 |
| Terpakai   |     1 |
+------------+-------+

Stok per group:
+------------------+-------+----------+------+--------+
| Group            | Total | Terpakai | Sisa | Satuan |
+------------------+-------+----------+------+--------+
| CCTV             |     5 |        0 |    5 | pcs    |
| Harddisk         |     3 |        1 |    2 | pcs    |
| NVR              |     2 |        0 |    2 | pcs    |
+------------------+-------+----------+------+--------+

C) Cek satu group:
📦 [Nama group]

+------------+-------+
| Metrik     | Nilai |
+------------+-------+
| Total      |     5 |
| Terpakai   |     1 |
| Sisa       |     4 |
+------------+-------+
SN tersedia: CCTV-001, CCTV-002, CCTV-003

D) Transaksi berhasil:
✅ Transaksi berhasil

+-------------+------------------+
| Field       | Nilai            |
+-------------+------------------+
| Jenis       | Keluar           |
| Group       | CCTV             |
| SN          | CCTV-001         |
| Jumlah      | 1                |
| Sisa stok   | 4 pcs            |
| Keterangan  | Proyek A         |
| Waktu       | 12-11-2026 02:00 |
+-------------+------------------+

E) Error:
⚠️ [penjelasan singkat]
Saran: [langkah berikutnya]
SYS;

    $tools = [
        ['type' => 'function', 'function' => [
            'name' => 'ringkasan_dashboard',
            'description' => 'Ringkasan lengkap dashboard: total group, SN tersedia/terpakai, transaksi masuk & keluar hari ini (jumlah unit + detail list), dan opsional filter tanggal. WAJIB dipanggil untuk pertanyaan hari ini, berapa keluar, statistik, ringkasan gudang.',
            'parameters' => ['type' => 'object', 'properties' => [
                'tanggal' => ['type' => 'string', 'description' => 'YYYY-MM-DD. Kosongkan = hari ini.'],
            ]]]],
        ['type' => 'function', 'function' => [
            'name' => 'cek_riwayat',
            'description' => 'Ambil riwayat transaksi masuk/keluar. Filter opsional: tanggal, jenis (masuk/keluar), nama group.',
            'parameters' => ['type' => 'object', 'properties' => [
                'tanggal' => ['type' => 'string', 'description' => 'YYYY-MM-DD atau kosong untuk semua / hari ini'],
                'jenis' => ['type' => 'string', 'description' => 'masuk | keluar | semua'],
                'nama_barang' => ['type' => 'string', 'description' => 'Filter group opsional'],
                'limit' => ['type' => 'integer', 'description' => 'Default 20, max 50'],
            ]]]],
        ['type' => 'function', 'function' => [
            'name' => 'cek_stok',
            'description' => 'Cek stok group (CCTV) atau SN (CCTV-001). Kosongkan nama untuk semua group (total, terpakai, sisa).',
            'parameters' => ['type' => 'object', 'properties' => [
                'nama_barang' => ['type' => 'string'],
            ]]]],
        ['type' => 'function', 'function' => [
            'name' => 'list_sn_tersedia',
            'description' => 'List SN berstatus tersedia untuk suatu group.',
            'parameters' => ['type' => 'object', 'properties' => [
                'nama_barang' => ['type' => 'string'],
            ], 'required' => ['nama_barang']]]],
        ['type' => 'function', 'function' => [
            'name' => 'catat_barang_masuk',
            'description' => 'Tambah unit masuk. Group + sn_list atau jumlah untuk generate SN.',
            'parameters' => ['type' => 'object', 'properties' => [
                'nama_barang' => ['type' => 'string'],
                'sn_list' => ['type' => 'string'],
                'jumlah' => ['type' => 'integer'],
                'keterangan' => ['type' => 'string'],
            ], 'required' => ['nama_barang']]]],
        ['type' => 'function', 'function' => [
            'name' => 'catat_barang_keluar',
            'description' => 'Catat keluar berdasarkan SN (contoh CCTV-001).',
            'parameters' => ['type' => 'object', 'properties' => [
                'sn_list' => ['type' => 'string'],
                'keterangan' => ['type' => 'string'],
            ], 'required' => ['sn_list']]]],
    ];

    $pesan_ai = [['role' => 'system', 'content' => $sistem]];
    foreach (array_slice($in['history'] ?? [], -10) as $m) {
        if (in_array($m['role'] ?? '', ['user', 'assistant'], true) && is_string($m['content'] ?? null))
            $pesan_ai[] = ['role' => $m['role'], 'content' => $m['content']];
    }
    $pesan_ai[] = ['role' => 'user', 'content' => $pesan];

    $berubah = false;
    $balasan = 'Maaf, terjadi kendala. Coba lagi.';

    for ($i = 0; $i < 5; $i++) {
        $payload = [
            'model' => $ai_model,
            'messages' => $pesan_ai,
            'tools' => $tools,
            'tool_choice' => 'auto',
            'temperature' => 0.15,
            'max_tokens' => 1400,
        ];
        $ch = curl_init('https://api.groq.com/openai/v1/chat/completions');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_TIMEOUT => 45,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                "Authorization: Bearer $api_key",
            ],
            CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE),
        ]);
        $raw = curl_exec($ch);
        $cerr = curl_error($ch);
        $http = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($raw === false || $raw === '') {
            $balasan = 'Gagal menghubungi API AI: ' . ($cerr ?: 'koneksi kosong');
            break;
        }
        $res = json_decode($raw, true);
        if (!is_array($res)) {
            $balasan = 'Respon AI tidak valid (HTTP ' . $http . ').';
            break;
        }
        if (!empty($res['error']['message'])) {
            $balasan = 'AI error: ' . $res['error']['message'];
            break;
        }

        $msg = $res['choices'][0]['message'] ?? null;
        if (!$msg) {
            $balasan = 'AI tidak merespons. Periksa API key / model.';
            break;
        }
        if (isset($msg['reasoning'])) unset($msg['reasoning']);

        if (empty($msg['tool_calls'])) {
            $balasan = trim((string)($msg['content'] ?? ''));
            if ($balasan === '') $balasan = 'Tidak ada balasan dari AI.';
            break;
        }

        $pesan_ai[] = $msg;
        foreach ($msg['tool_calls'] as $tc) {
            $a = json_decode($tc['function']['arguments'] ?? '{}', true) ?: [];
            $nama = $tc['function']['name'] ?? '';
            $hasil = ['ok' => false, 'error' => 'Tool tidak dikenal.'];

            if ($nama === 'ringkasan_dashboard') {
                $tgl = trim((string)($a['tanggal'] ?? ''));
                if ($tgl === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $tgl)) {
                    $tgl = date('Y-m-d');
                }
                $total_group = (int)$pdo->query("SELECT COUNT(*) FROM stok_barang")->fetchColumn();
                $total_tersedia = (int)$pdo->query("SELECT COUNT(*) FROM stok_sn WHERE status='tersedia'")->fetchColumn();
                $total_terpakai = (int)$pdo->query("SELECT COUNT(*) FROM stok_sn WHERE status='terpakai'")->fetchColumn();
                $total_sn = $total_tersedia + $total_terpakai;

                $stM = $pdo->prepare("SELECT COALESCE(SUM(jumlah),0) FROM stok_riwayat WHERE jenis='masuk' AND DATE(waktu)=?");
                $stM->execute([$tgl]);
                $unit_masuk = (int)$stM->fetchColumn();
                $stK = $pdo->prepare("SELECT COALESCE(SUM(jumlah),0) FROM stok_riwayat WHERE jenis='keluar' AND DATE(waktu)=?");
                $stK->execute([$tgl]);
                $unit_keluar = (int)$stK->fetchColumn();

                $stList = $pdo->prepare("
                    SELECT r.jenis, r.jumlah, r.sn_list, r.keterangan, r.stok_sesudah,
                        DATE_FORMAT(r.waktu,'%H:%i:%s') jam, b.nama, b.satuan
                    FROM stok_riwayat r JOIN stok_barang b ON b.id=r.barang_id
                    WHERE DATE(r.waktu)=?
                    ORDER BY r.id DESC LIMIT 40
                ");
                $stList->execute([$tgl]);
                $detail = [];
                $trx_masuk = 0;
                $trx_keluar = 0;
                foreach ($stList->fetchAll() as $row) {
                    if ($row['jenis'] === 'masuk') $trx_masuk++; else $trx_keluar++;
                    $detail[] = [
                        'jam' => $row['jam'],
                        'jenis' => $row['jenis'],
                        'group' => $row['nama'],
                        'jumlah' => (int)$row['jumlah'],
                        'satuan' => $row['satuan'],
                        'sn' => $row['sn_list'],
                        'keterangan' => $row['keterangan'] ?? '',
                        'sisa_setelah' => (int)$row['stok_sesudah'],
                    ];
                }

                // per group ringkas
                $per_group = [];
                foreach ($pdo->query("
                    SELECT b.nama, b.satuan,
                        COUNT(s.id) total,
                        COALESCE(SUM(s.status='tersedia'),0) tersedia,
                        COALESCE(SUM(s.status='terpakai'),0) terpakai
                    FROM stok_barang b LEFT JOIN stok_sn s ON s.barang_id=b.id
                    GROUP BY b.id ORDER BY b.nama
                ") as $g) {
                    $per_group[] = [
                        'group' => $g['nama'],
                        'total' => (int)$g['total'],
                        'terpakai' => (int)$g['terpakai'],
                        'sisa' => (int)$g['tersedia'],
                        'satuan' => $g['satuan'],
                    ];
                }

                $hasil = [
                    'ok' => true,
                    'tanggal' => $tgl,
                    'waktu_cek' => date('d-m-Y H:i:s') . ' WIB',
                    'dashboard' => [
                        'jumlah_group' => $total_group,
                        'total_sn' => $total_sn,
                        'tersedia' => $total_tersedia,
                        'terpakai' => $total_terpakai,
                    ],
                    'transaksi_tanggal' => [
                        'unit_masuk' => $unit_masuk,
                        'unit_keluar' => $unit_keluar,
                        'jumlah_trx_masuk' => $trx_masuk,
                        'jumlah_trx_keluar' => $trx_keluar,
                    ],
                    'detail_transaksi' => $detail,
                    'stok_per_group' => $per_group,
                ];
            } elseif ($nama === 'cek_riwayat') {
                $tgl = trim((string)($a['tanggal'] ?? ''));
                $jenis = strtolower(trim((string)($a['jenis'] ?? 'semua')));
                $namaB = trim((string)($a['nama_barang'] ?? ''));
                $limit = max(1, min(50, (int)($a['limit'] ?? 20)));

                $sql = "SELECT r.*, b.nama, b.satuan, DATE_FORMAT(r.waktu,'%d-%m-%Y %H:%i:%s') w
                    FROM stok_riwayat r JOIN stok_barang b ON b.id=r.barang_id WHERE 1=1";
                $params = [];
                if ($tgl !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $tgl)) {
                    $sql .= " AND DATE(r.waktu)=?";
                    $params[] = $tgl;
                } elseif ($tgl === '' && stripos(json_encode($a), 'hari') !== false) {
                    $sql .= " AND DATE(r.waktu)=CURDATE()";
                }
                // default: if user asked without tanggal, still allow all; AI should pass tanggal for "hari ini"
                if (in_array($jenis, ['masuk', 'keluar'], true)) {
                    $sql .= " AND r.jenis=?";
                    $params[] = $jenis;
                }
                if ($namaB !== '') {
                    $sql .= " AND (b.nama LIKE ? OR b.kode LIKE ?)";
                    $params[] = "%$namaB%";
                    $params[] = "%$namaB%";
                }
                $sql .= " ORDER BY r.id DESC LIMIT $limit";
                $st = $pdo->prepare($sql);
                $st->execute($params);
                $rows = [];
                $sum_masuk = 0;
                $sum_keluar = 0;
                foreach ($st->fetchAll() as $row) {
                    if ($row['jenis'] === 'masuk') $sum_masuk += (int)$row['jumlah'];
                    else $sum_keluar += (int)$row['jumlah'];
                    $rows[] = [
                        'waktu' => $row['w'],
                        'group' => $row['nama'],
                        'jenis' => $row['jenis'],
                        'jumlah' => (int)$row['jumlah'],
                        'satuan' => $row['satuan'],
                        'sn' => $row['sn_list'] ?? '',
                        'keterangan' => $row['keterangan'] ?? '',
                        'sisa_setelah' => (int)$row['stok_sesudah'],
                        'sumber' => $row['sumber'],
                    ];
                }
                $hasil = [
                    'ok' => true,
                    'filter' => ['tanggal' => $tgl ?: 'semua', 'jenis' => $jenis, 'group' => $namaB ?: 'semua'],
                    'total_unit_masuk' => $sum_masuk,
                    'total_unit_keluar' => $sum_keluar,
                    'jumlah_baris' => count($rows),
                    'riwayat' => $rows,
                    'waktu_cek' => date('d-m-Y H:i:s') . ' WIB',
                ];
            } elseif ($nama === 'cek_stok') {
                $q = trim($a['nama_barang'] ?? '');
                if ($q !== '' && preg_match('/^.+-\d+$/', strtoupper($q))) {
                    $st = $pdo->prepare("SELECT s.*, b.nama, b.kode, b.satuan FROM stok_sn s JOIN stok_barang b ON b.id=s.barang_id WHERE s.sn = ?");
                    $st->execute([strtoupper($q)]);
                    $row = $st->fetch();
                    if ($row) {
                        $hasil = ['ok' => true, 'tipe' => 'sn', 'sn' => $row['sn'], 'status' => $row['status'],
                            'barang' => $row['nama'], 'kode' => $row['kode'], 'satuan' => $row['satuan'],
                            'waktu' => date('d-m-Y H:i:s') . ' WIB'];
                    } else {
                        $hasil = ['ok' => false, 'error' => "SN $q tidak ditemukan."];
                    }
                } else {
                    $groups = $q === '' ? $pdo->query("SELECT * FROM stok_barang ORDER BY nama")->fetchAll() : cari_barang($pdo, $q);
                    $list = [];
                    foreach ($groups as $g) {
                        $tersedia = hitung_stok($pdo, (int)$g['id']);
                        $terpakai = hitung_terpakai($pdo, (int)$g['id']);
                        $st3 = $pdo->prepare("SELECT sn FROM stok_sn WHERE barang_id=? AND status='tersedia' ORDER BY sn LIMIT 10");
                        $st3->execute([$g['id']]);
                        $list[] = [
                            'kode' => $g['kode'], 'nama' => $g['nama'], 'satuan' => $g['satuan'],
                            'tersedia' => $tersedia, 'terpakai' => $terpakai, 'sisa' => $tersedia,
                            'status' => $tersedia === 0 ? 'Habis' : ($tersedia <= $batas_menipis ? 'Menipis' : 'Tersedia'),
                            'sn_tersedia_sample' => $st3->fetchAll(PDO::FETCH_COLUMN),
                        ];
                    }
                    $hasil = ['ok' => true, 'tipe' => 'group', 'waktu' => date('d-m-Y H:i:s') . ' WIB', 'barang' => $list];
                }
            } elseif ($nama === 'list_sn_tersedia') {
                $cocok = cari_barang($pdo, (string)($a['nama_barang'] ?? ''));
                if (!$cocok) $hasil = ['ok' => false, 'error' => 'Group tidak ditemukan.'];
                elseif (count($cocok) > 1) $hasil = ['ok' => false, 'error' => 'Nama ambigu.', 'kandidat' => array_column($cocok, 'nama')];
                else {
                    $st = $pdo->prepare("SELECT sn FROM stok_sn WHERE barang_id=? AND status='tersedia' ORDER BY sn");
                    $st->execute([$cocok[0]['id']]);
                    $sns = $st->fetchAll(PDO::FETCH_COLUMN);
                    $hasil = ['ok' => true, 'barang' => $cocok[0]['nama'], 'kode' => $cocok[0]['kode'], 'jumlah' => count($sns), 'sn' => $sns, 'satuan' => $cocok[0]['satuan']];
                }
            } elseif ($nama === 'catat_barang_masuk') {
                $g = pastikan_group($pdo, (string)($a['nama_barang'] ?? ''), 'pcs');
                if (!$g['ok']) {
                    $hasil = ['ok' => false, 'error' => $g['error']];
                } else {
                    $barang = $g['barang'];
                    $sn_raw = trim((string)($a['sn_list'] ?? ''));
                    if ($sn_raw !== '') {
                        $sns = preg_split('/[\s,;]+/', $sn_raw, -1, PREG_SPLIT_NO_EMPTY);
                    } else {
                        $sns = generate_sn($pdo, $barang['kode'], max(1, (int)($a['jumlah'] ?? 1)));
                    }
                    $hasil = sn_masuk($pdo, (int)$barang['id'], $sns, (string)($a['keterangan'] ?? ''), 'ai');
                    if ($hasil['ok']) $berubah = true;
                }
            } elseif ($nama === 'catat_barang_keluar') {
                $sns = preg_split('/[\s,;]+/', trim((string)($a['sn_list'] ?? '')), -1, PREG_SPLIT_NO_EMPTY);
                $hasil = sn_keluar($pdo, $sns, (string)($a['keterangan'] ?? ''), 'ai');
                if ($hasil['ok']) $berubah = true;
            }

            $pesan_ai[] = [
                'role' => 'tool',
                'tool_call_id' => $tc['id'],
                'content' => json_encode($hasil, JSON_UNESCAPED_UNICODE),
            ];
        }
    }

    $out = ['reply' => $balasan, 'changed' => $berubah];
    if ($berubah) {
        $out['stok'] = html_stok($pdo, $batas_menipis);
        $out['riwayat'] = html_riwayat($pdo);
        $out['sn'] = html_sn_tersedia($pdo);
        $out['stat'] = true;
    }
    echo json_encode($out, JSON_UNESCAPED_UNICODE);
    exit;
}

$flash = $_GET['m'] ?? '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $aksi = $_POST['aksi'] ?? '';
    $msg = '';
    if ($aksi === 'masuk_unit') {
        // Satu form: SN Perangkat | Group | Jumlah | Satuan
        $sn_raw = trim($_POST['sn_perangkat'] ?? '');
        $group_in = trim($_POST['group'] ?? '');
        $jumlah = max(1, min(100, (int)($_POST['jumlah'] ?? 1)));
        $satuan = trim($_POST['satuan'] ?? 'pcs') ?: 'pcs';
        $ket = trim($_POST['keterangan'] ?? '');

        $g = pastikan_group($pdo, $group_in, $satuan);
        if (!$g['ok']) {
            $msg = $g['error'];
        } else {
            $barang = $g['barang'];
            // Update satuan group jika beda
            if (($barang['satuan'] ?? '') !== $satuan) {
                $pdo->prepare("UPDATE stok_barang SET satuan = ? WHERE id = ?")->execute([$satuan, $barang['id']]);
                $barang['satuan'] = $satuan;
            }
            if ($sn_raw !== '') {
                $sns = preg_split('/[\s,;]+/', $sn_raw, -1, PREG_SPLIT_NO_EMPTY);
                // Jika user isi SN + jumlah, jumlah harus cocok
                if (count($sns) !== $jumlah && $jumlah > 1 && count($sns) === 1) {
                    // satu SN prefix? tidak — pakai daftar SN apa adanya
                }
                if (count($sns) === 0) {
                    $msg = 'SN Perangkat tidak valid.';
                } else {
                    // Jika jumlah > jumlah SN, tolak agar jelas
                    if ($jumlah !== count($sns)) {
                        $msg = 'Jumlah (' . $jumlah . ') harus sama dengan jumlah SN yang diisi (' . count($sns) . ').';
                    } else {
                        $r = sn_masuk($pdo, (int)$barang['id'], $sns, $ket, 'manual');
                        $msg = $r['ok']
                            ? "Masuk {$r['jumlah']} {$r['satuan']} · Group {$r['barang']} · SN: " . implode(', ', $r['sn']) . " · Sisa {$r['stok_sekarang']} {$r['satuan']} ({$r['waktu']})"
                            : $r['error'];
                    }
                }
            } else {
                // SN kosong → generate otomatis sesuai Group + Jumlah
                $sns = generate_sn($pdo, $barang['kode'], $jumlah);
                $r = sn_masuk($pdo, (int)$barang['id'], $sns, $ket, 'manual');
                $msg = $r['ok']
                    ? "Masuk {$r['jumlah']} {$r['satuan']} · Group {$r['barang']} · SN: " . implode(', ', $r['sn']) . " · Sisa {$r['stok_sekarang']} {$r['satuan']} ({$r['waktu']})"
                    : $r['error'];
            }
        }
    } elseif ($aksi === 'keluar_sn') {
        // Support: sn_list string, sn single, or sn[] array from multi-select
        $sns = [];
        if (!empty($_POST['sn']) && is_array($_POST['sn'])) {
            $sns = $_POST['sn'];
        } elseif (!empty($_POST['sn'])) {
            $sns = [$_POST['sn']];
        } elseif (!empty($_POST['sn_list'])) {
            $sns = preg_split('/[\s,;]+/', trim((string)$_POST['sn_list']), -1, PREG_SPLIT_NO_EMPTY);
        }
        $ket = trim($_POST['keterangan'] ?? '');
        $r = sn_keluar($pdo, $sns, $ket, 'manual');
        if ($r['ok']) {
            $parts = [];
            foreach ($r['per_group'] as $g) {
                $parts[] = "{$g['barang']}: " . implode(', ', $g['sn']) . " (sisa {$g['stok_sekarang']} {$g['satuan']})";
            }
            $msg = "Keluar {$r['jumlah']} unit. " . implode(' | ', $parts)
                . ($ket !== '' ? " · Ket: $ket" : '')
                . ". ({$r['waktu']})";
        } else {
            $msg = $r['error'];
        }
    } elseif ($aksi === 'hapus_riwayat') {
        $r = hapus_riwayat($pdo, (int)($_POST['riwayat_id'] ?? 0));
        $msg = $r['ok'] ? $r['msg'] : $r['error'];
    } elseif ($aksi === 'edit_riwayat') {
        $r = edit_riwayat($pdo, (int)($_POST['riwayat_id'] ?? 0), (string)($_POST['keterangan'] ?? ''));
        $msg = $r['ok'] ? $r['msg'] : $r['error'];
    } elseif ($aksi === 'edit_group') {
        $r = edit_group($pdo, (int)($_POST['group_id'] ?? 0), (string)($_POST['nama'] ?? ''), (string)($_POST['satuan'] ?? 'pcs'));
        $msg = $r['ok'] ? $r['msg'] : $r['error'];
    } elseif ($aksi === 'hapus_group') {
        $r = hapus_group($pdo, (int)($_POST['group_id'] ?? 0));
        $msg = $r['ok'] ? $r['msg'] : $r['error'];
    } elseif ($aksi === 'edit_sn') {
        $r = edit_sn($pdo, (int)($_POST['sn_id'] ?? 0), (string)($_POST['sn'] ?? ''), (string)($_POST['keterangan'] ?? ''));
        $msg = $r['ok'] ? $r['msg'] : $r['error'];
    } elseif ($aksi === 'hapus_sn') {
        $r = hapus_sn($pdo, (int)($_POST['sn_id'] ?? 0));
        $msg = $r['ok'] ? $r['msg'] : $r['error'];
    }
    header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?') . '?m=' . urlencode($msg));
    exit;
}

$daftar = $pdo->query("
    SELECT b.id, b.kode, b.nama, b.satuan,
        COALESCE(SUM(s.status='tersedia'),0) AS stok
    FROM stok_barang b
    LEFT JOIN stok_sn s ON s.barang_id = b.id
    GROUP BY b.id
    ORDER BY b.nama
")->fetchAll();
$total_jenis = count($daftar);
$total_unit = array_sum(array_column($daftar, 'stok'));
$menipis = count(array_filter($daftar, fn($r) => (int)$r['stok'] <= $batas_menipis));
$total_terpakai = (int)$pdo->query("SELECT COUNT(*) FROM stok_sn WHERE status='terpakai'")->fetchColumn();

// Peta SN tersedia per group untuk form keluar (auto-fill)
$sn_by_group = [];
$stSnMap = $pdo->query("
    SELECT s.barang_id, s.sn
    FROM stok_sn s
    WHERE s.status = 'tersedia'
    ORDER BY s.sn
");
foreach ($stSnMap->fetchAll() as $row) {
    $sn_by_group[(int)$row['barang_id']][] = $row['sn'];
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Stok Gudang — SN Tracking</title>
<link href="https://fonts.googleapis.com/css2?family=Archivo:wdth,wght@75..125,400..800&family=JetBrains+Mono:wght@400;600&display=swap" rel="stylesheet">
<style>
:root{--bg:#eceff3;--panel:#fff;--ink:#14213d;--muted:#5b6678;--line:#d5dbe4;--kuning:#f2b705;--merah:#c8372d;--hijau:#1f7a4d;--bubble:#e6ebf2}
@media(prefers-color-scheme:dark){:root{--bg:#0e1522;--panel:#162031;--ink:#e8edf5;--muted:#93a0b5;--line:#27344b;--bubble:#1e2b42}}
*{box-sizing:border-box}
body{margin:0;background:var(--bg);color:var(--ink);font-family:'Archivo',system-ui,sans-serif;font-size:15px;line-height:1.5}
.mono{font-family:'JetBrains Mono',monospace;font-size:13px}
header{background:#14213d;color:#fff;padding:14px 24px;display:flex;justify-content:space-between;align-items:center;gap:16px;flex-wrap:wrap;border-bottom:4px solid var(--kuning)}
header h1{margin:0;font-size:22px;font-weight:800;font-stretch:80%}
#jam{font-family:'JetBrains Mono',monospace;font-size:15px;text-align:right;line-height:1.3}
#jam small{display:block;color:#b9c3d6;font-size:12px}
main{max-width:1200px;margin:0 auto;padding:20px;display:grid;grid-template-columns:1.35fr 1fr;gap:20px}
@media(max-width:900px){main{grid-template-columns:1fr;padding:14px}}
.panel{background:var(--panel);border:1px solid var(--line);border-radius:6px;padding:18px;min-width:0}
.panel h2{margin:0 0 12px;font-size:17px;font-weight:700}
.stat{display:flex;gap:12px;margin-bottom:16px;flex-wrap:wrap}
.stat div{font-size:12px;color:var(--muted);background:var(--bubble);border:1px solid var(--line);border-radius:8px;padding:10px 16px;min-width:88px}
.stat b{display:block;font-size:24px;color:var(--ink);font-weight:800;font-stretch:85%;line-height:1.15;margin-bottom:2px}
.scroll{overflow-x:auto;border:1px solid var(--line);border-radius:10px}
table{width:100%;border-collapse:collapse;font-size:14px}
thead th{text-align:left;font-size:12px;color:var(--muted);font-weight:700;text-transform:uppercase;letter-spacing:.03em;padding:12px 14px;background:var(--bubble);border-bottom:1px solid var(--line);white-space:nowrap}
td{padding:12px 14px;border-bottom:1px solid var(--line);vertical-align:middle}
tbody tr:last-child td{border-bottom:0}
tbody tr:hover td{background:rgba(242,183,5,.06)}
td.num,th.num{text-align:right;font-variant-numeric:tabular-nums;font-family:'JetBrains Mono',ui-monospace,monospace;font-size:13px}
td.col-group{font-size:14px}
td.col-status{width:1%;white-space:nowrap}
td.col-sisa b{color:var(--ink)}
td .sat{color:var(--muted);font-weight:500;font-size:12px}
tr.habis td{background:rgba(200,55,45,.07)}
tr.tipis td{background:rgba(242,183,5,.08)}
tr.aman td{background:transparent}
.kosong{color:var(--muted);text-align:center;padding:28px}
.tag{display:inline-block;font-size:11px;font-weight:700;padding:3px 9px;border-radius:999px;color:#fff;letter-spacing:.02em}
.merah{background:var(--merah)}.hijau{background:var(--hijau)}.kuning{background:var(--kuning);color:#14213d}
form.grid{display:grid;grid-template-columns:repeat(2,1fr);gap:10px}
form.grid .full{grid-column:1/-1}
label{font-size:13px;color:var(--muted);display:block;margin-bottom:3px}
input,select,textarea{width:100%;padding:8px 10px;border:1px solid var(--line);border-radius:4px;background:var(--panel);color:var(--ink);font:inherit}
input:focus,select:focus,textarea:focus{outline:2px solid var(--kuning);outline-offset:1px}
button{font:inherit;font-weight:700;padding:9px 16px;border:0;border-radius:4px;background:var(--kuning);color:#14213d;cursor:pointer}
button:hover{filter:brightness(.95)}
.flash{grid-column:1/-1;background:#14213d;color:#fff;padding:10px 14px;border-left:4px solid var(--kuning);border-radius:4px}
#chat{display:flex;flex-direction:column;height:520px}
#log{flex:1;overflow-y:auto;display:flex;flex-direction:column;gap:8px;padding:2px 2px 10px}
.b{max-width:92%;padding:8px 12px;border-radius:8px;white-space:pre-wrap;word-wrap:break-word}
.b.u{align-self:flex-end;background:#14213d;color:#fff}
.b.a{align-self:flex-start;background:var(--bubble);font-family:'JetBrains Mono',ui-monospace,monospace;font-size:12.5px;line-height:1.45;overflow-x:auto}
.b.err{border-left:3px solid var(--merah)}
#kirim{display:flex;gap:8px;margin-top:8px}
.contoh{display:flex;flex-wrap:wrap;gap:6px;margin-bottom:8px}
.contoh button{font-size:12px;font-weight:600;padding:4px 10px;background:var(--bubble);color:var(--ink)}
.lebar{grid-column:1/-1}
.hint{font-size:12px;color:var(--muted);margin-top:4px}
.tabs{display:flex;gap:6px;margin-bottom:10px}
.tabs button{background:var(--bubble);font-size:13px}
.tabs button.on{background:var(--kuning);color:#14213d}
td.aksi{white-space:nowrap;text-align:right}
.btn-edit,.btn-hapus,.btn-batal{font:inherit;font-size:12px;font-weight:700;padding:5px 10px;border:0;border-radius:4px;cursor:pointer}
.btn-edit{background:#e8eef8;color:#14213d}
.btn-hapus{background:#fde8e6;color:#c8372d}
.btn-batal{background:var(--bubble);color:var(--ink)}
.btn-export{display:inline-block;font:inherit;font-size:13px;font-weight:700;padding:8px 14px;border-radius:4px;background:#1f7a4d;color:#fff;text-decoration:none}
.btn-export:hover{filter:brightness(1.08)}
.btn-edit:hover,.btn-hapus:hover{filter:brightness(.95)}
.modal{position:fixed;inset:0;background:rgba(15,23,42,.55);display:flex;align-items:center;justify-content:center;z-index:100;padding:16px}
.modal[hidden]{display:none!important}
.modal-card{background:var(--panel);border:1px solid var(--line);border-radius:10px;padding:20px;width:min(420px,100%);box-shadow:0 20px 40px rgba(0,0,0,.25)}
.modal-card h3{margin:0 0 8px;font-size:17px}
.modal-card label{margin-top:10px}
.modal-card input{margin-top:4px}
.modal-actions{display:flex;gap:8px;justify-content:flex-end;margin-top:16px}
</style>
</head>
<body>
<header>
  <h1>Stok Gudang · SN Tracking</h1>
  <div id="jam"><span id="jam-t">--:--:--</span><small id="jam-d"></small></div>
</header>
<main>
<?php if ($flash !== ''): ?><div class="flash" role="status"><?= h($flash) ?></div><?php endif; ?>

<section class="panel">
  <div style="display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;margin-bottom:12px">
    <h2 style="margin:0">Stok per Group</h2>
    <a href="?api=export" class="btn-export" download>⬇ Download Excel</a>
  </div>
  <div class="stat" id="stat">
    <div><b><?= $total_jenis ?></b>group</div>
    <div><b><?= (int)$total_unit ?></b>tersedia</div>
    <div><b><?= (int)$total_terpakai ?></b>terpakai</div>
    <div><b><?= $menipis ?></b>menipis / habis</div>
  </div>
  <div class="scroll">
    <table class="tbl-stok">
      <thead>
        <tr>
          <th>Group</th>
          <th>Status</th>
          <th class="num">Total</th>
          <th class="num">Terpakai</th>
          <th class="num">Sisa Stock</th>
          <th style="text-align:right">Aksi</th>
        </tr>
      </thead>
      <tbody id="tb-stok"><?= html_stok($pdo, $batas_menipis) ?></tbody>
    </table>
  </div>
</section>

<section class="panel" id="chat-panel">
  <h2>Asisten Gudang (AI)</h2>
  <div id="chat">
    <div class="contoh">
      <button type="button" data-q="Ringkasan dashboard hari ini">Ringkasan hari ini</button>
      <button type="button" data-q="Ada berapa barang keluar hari ini?">Keluar hari ini</button>
      <button type="button" data-q="Cek semua stok">Cek semua stok</button>
      <button type="button" data-q="List SN tersedia CCTV">SN CCTV</button>
    </div>
    <div id="log"><div class="b a">Halo. Group = kata (CCTV). SN = nomor unik (CCTV-001).
Contoh:
• "Masuk 3 unit CCTV" → generate CCTV-006...
• "Keluar CCTV-001 untuk proyek A"
• "Cek NVR-001"</div></div>
    <form id="kirim" autocomplete="off">
      <input id="msg" placeholder="Tulis pesan…" required>
      <button type="submit" id="btn">Kirim</button>
    </form>
  </div>
</section>

<section class="panel">
  <h2>Barang Masuk</h2>
  <p class="hint">Satu form: <b>SN Perangkat</b> · <b>Group</b> · <b>Jumlah</b> · <b>Satuan</b>. Kosongkan SN untuk generate otomatis (CCTV-001, CCTV-002, …). Group baru dibuat otomatis jika belum ada. SN duplikat ditolak.</p>
  <form method="post" class="grid">
    <input type="hidden" name="aksi" value="masuk_unit">
    <div class="full">
      <label for="sn_perangkat">SN Perangkat</label>
      <input id="sn_perangkat" name="sn_perangkat" placeholder="Kosongkan = otomatis, atau isi: CCTV-010, CCTV-011">
    </div>
    <div>
      <label for="group">Group</label>
      <input id="group" name="group" required list="group-list" placeholder="mis. CCTV">
      <datalist id="group-list">
        <?php foreach ($daftar as $d): ?>
          <option value="<?= h($d['nama']) ?>"><?= h($d['kode']) ?></option>
        <?php endforeach; ?>
      </datalist>
    </div>
    <div>
      <label for="jumlah">Jumlah</label>
      <input id="jumlah" type="number" name="jumlah" min="1" max="100" value="1" required>
    </div>
    <div>
      <label for="satuan">Satuan</label>
      <input id="satuan" name="satuan" value="pcs" required>
    </div>
    <div class="full">
      <label for="ket_in">Keterangan (opsional)</label>
      <input id="ket_in" name="keterangan" placeholder="Supplier / PO">
    </div>
    <div class="full"><button type="submit">Simpan Masuk</button></div>
  </form>
</section>

<section class="panel">
  <h2>Barang Keluar</h2>
  <p class="hint">Pilih <b>Group</b> → SN tersedia group itu muncul otomatis (hanya yang belum terpakai). Lalu isi keterangan.</p>
  <form method="post" class="grid" id="form-keluar">
    <input type="hidden" name="aksi" value="keluar_sn">
    <div class="full">
      <label for="keluar_group">Group</label>
      <select id="keluar_group" required>
        <option value="">— Pilih group —</option>
        <?php foreach ($daftar as $d): ?>
          <?php if ((int)$d['stok'] <= 0) continue; ?>
          <option value="<?= (int)$d['id'] ?>"><?= h($d['nama']) ?> (<?= (int)$d['stok'] ?> tersedia)</option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="full">
      <label for="keluar_sn">SN Perangkat</label>
      <select id="keluar_sn" name="sn" required>
        <option value="">— Pilih group dulu —</option>
      </select>
      <p class="hint">Pilih satu SN unit yang akan keluar (hanya SN tersedia dari group terpilih).</p>
    </div>
    <div class="full">
      <label for="ket_out">Keterangan</label>
      <input id="ket_out" name="keterangan" required placeholder="Proyek A / teknisi / tujuan">
    </div>
    <div class="full"><button type="submit">Catat Keluar</button></div>
  </form>
</section>

<section class="panel lebar">
  <h2>SN Tersedia</h2>
  <div class="scroll"><table>
    <thead><tr><th>SN</th><th>Group</th><th>Tanggal masuk</th><th>Aksi</th></tr></thead>
    <tbody id="tb-sn"><?= html_sn_tersedia($pdo) ?></tbody>
  </table></div>
</section>

<section class="panel lebar">
  <h2>Riwayat transaksi</h2>
  <div class="scroll"><table>
    <thead><tr><th>Waktu (WIB)</th><th>Group</th><th>Jenis</th><th class="num">Qty</th><th>SN</th><th>Keterangan</th><th class="num">Sisa</th><th>Aksi</th></tr></thead>
    <tbody id="tb-riwayat"><?= html_riwayat($pdo) ?></tbody>
  </table></div>
</section>
</main>

<!-- Modal Edit Riwayat -->
<div id="modal-edit" class="modal" hidden>
  <div class="modal-card">
    <h3>Edit Riwayat</h3>
    <p id="edit-info" class="hint"></p>
    <form method="post">
      <input type="hidden" name="aksi" value="edit_riwayat">
      <input type="hidden" name="riwayat_id" id="edit-id" value="">
      <label for="edit-ket">Keterangan</label>
      <input id="edit-ket" name="keterangan" placeholder="Keterangan transaksi">
      <p class="hint">SN dan jenis tidak diubah di sini. Gunakan Hapus lalu catat ulang bila perlu ubah SN.</p>
      <div class="modal-actions">
        <button type="button" class="btn-batal" onclick="closeModal('modal-edit')">Batal</button>
        <button type="submit">Simpan</button>
      </div>
    </form>
  </div>
</div>

<!-- Modal Edit Group -->
<div id="modal-group" class="modal" hidden>
  <div class="modal-card">
    <h3>Edit Group</h3>
    <form method="post">
      <input type="hidden" name="aksi" value="edit_group">
      <input type="hidden" name="group_id" id="g-id" value="">
      <label for="g-nama">Nama Group</label>
      <input id="g-nama" name="nama" required placeholder="mis. CCTV">
      <label for="g-satuan">Satuan</label>
      <input id="g-satuan" name="satuan" required placeholder="pcs">
      <div class="modal-actions">
        <button type="button" class="btn-batal" onclick="closeModal('modal-group')">Batal</button>
        <button type="submit">Simpan</button>
      </div>
    </form>
  </div>
</div>

<!-- Modal Edit SN -->
<div id="modal-sn" class="modal" hidden>
  <div class="modal-card">
    <h3>Edit SN</h3>
    <p id="sn-info" class="hint"></p>
    <form method="post">
      <input type="hidden" name="aksi" value="edit_sn">
      <input type="hidden" name="sn_id" id="s-id" value="">
      <label for="s-sn">SN Perangkat</label>
      <input id="s-sn" name="sn" required placeholder="CCTV-001">
      <label for="s-ket">Keterangan</label>
      <input id="s-ket" name="keterangan" placeholder="Opsional">
      <div class="modal-actions">
        <button type="button" class="btn-batal" onclick="closeModal('modal-sn')">Batal</button>
        <button type="submit">Simpan</button>
      </div>
    </form>
  </div>
</div>

<script>
function closeModal(id){ document.getElementById(id).hidden = true; }
function openEditRiwayat(id, data){
  document.getElementById('edit-id').value = id;
  document.getElementById('edit-ket').value = data.keterangan || '';
  document.getElementById('edit-info').textContent = (data.jenis||'') + ' · ' + (data.nama||'') + ' · SN: ' + (data.sn_list||'-');
  document.getElementById('modal-edit').hidden = false;
}
function openEditGroup(data){
  document.getElementById('g-id').value = data.id;
  document.getElementById('g-nama').value = data.nama || '';
  document.getElementById('g-satuan').value = data.satuan || 'pcs';
  document.getElementById('modal-group').hidden = false;
}
function openEditSn(data){
  document.getElementById('s-id').value = data.id;
  document.getElementById('s-sn').value = data.sn || '';
  document.getElementById('s-ket').value = data.keterangan || '';
  document.getElementById('sn-info').textContent = 'Group: ' + (data.nama || '-');
  document.getElementById('modal-sn').hidden = false;
}
['modal-edit','modal-group','modal-sn'].forEach(function(id){
  var el = document.getElementById(id);
  if (el) el.addEventListener('click', function(e){ if(e.target===el) closeModal(id); });
});

// Form keluar: Group → filter SN tersedia
const snByGroup = <?= json_encode($sn_by_group, JSON_UNESCAPED_UNICODE) ?>;
const selGroup = document.getElementById('keluar_group');
const selSn = document.getElementById('keluar_sn');
function refreshSnOptions(){
  if (!selGroup || !selSn) return;
  const gid = selGroup.value;
  const list = (gid && snByGroup[gid]) ? snByGroup[gid] : [];
  selSn.innerHTML = '';
  const ph = document.createElement('option');
  ph.value = '';
  if (!gid) {
    ph.textContent = '— Pilih group dulu —';
    selSn.appendChild(ph);
    return;
  }
  if (!list.length) {
    ph.textContent = '— Tidak ada SN tersedia —';
    selSn.appendChild(ph);
    return;
  }
  ph.textContent = '— Pilih SN —';
  selSn.appendChild(ph);
  list.forEach(function(sn){
    const o = document.createElement('option');
    o.value = sn;
    o.textContent = sn;
    selSn.appendChild(o);
  });
}
if (selGroup) selGroup.addEventListener('change', refreshSnOptions);
</script>
<script>
const hari=new Intl.DateTimeFormat('id-ID',{timeZone:'Asia/Jakarta',weekday:'long',day:'numeric',month:'long',year:'numeric'});
const jam=new Intl.DateTimeFormat('id-ID',{timeZone:'Asia/Jakarta',hour:'2-digit',minute:'2-digit',second:'2-digit',hour12:false});
function tick(){const n=new Date();document.getElementById('jam-t').textContent=jam.format(n).replace(/\./g,':')+' WIB';document.getElementById('jam-d').textContent=hari.format(n)}
tick();setInterval(tick,1000);

const log=document.getElementById('log'),msg=document.getElementById('msg'),btn=document.getElementById('btn');
let history=[];
function bubble(t,cls){const d=document.createElement('div');d.className='b '+cls;d.textContent=t;log.appendChild(d);log.scrollTop=log.scrollHeight;return d}
async function kirim(teks){
  bubble(teks,'u');btn.disabled=true;const tunggu=bubble('Memproses…','a');
  try{
    const r=await fetch('?api=chat',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({message:teks,history})});
    const j=await r.json();
    tunggu.textContent=j.reply||'(tidak ada balasan)';
    history.push({role:'user',content:teks},{role:'assistant',content:j.reply||''});
    if(j.changed){
      if(j.stok) document.getElementById('tb-stok').innerHTML=j.stok;
      if(j.riwayat) document.getElementById('tb-riwayat').innerHTML=j.riwayat;
      if(j.sn) document.getElementById('tb-sn').innerHTML=j.sn;
      setTimeout(()=>{const u=new URL(location);u.search='';fetch(u).then(x=>x.text()).then(t=>{
        const d=new DOMParser().parseFromString(t,'text/html');
        ['stat'].forEach(id=>{const a=document.getElementById(id),b=d.getElementById(id);if(a&&b)a.innerHTML=b.innerHTML});
        const dl=document.getElementById('group-list'), dl2=d.getElementById('group-list');
        if(dl&&dl2) dl.innerHTML=dl2.innerHTML;
      })},200);
    }
  }catch(e){tunggu.textContent='Tidak bisa menghubungi server.';tunggu.classList.add('err')}
  btn.disabled=false;log.scrollTop=log.scrollHeight;msg.focus();
}
document.getElementById('kirim').addEventListener('submit',e=>{e.preventDefault();const t=msg.value.trim();if(t){msg.value='';kirim(t)}});
document.querySelectorAll('.contoh button').forEach(b=>b.addEventListener('click',()=>kirim(b.dataset.q)));
</script>
</body>
</html>
