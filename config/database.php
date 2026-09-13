<?php
/**
 * Konfigurasi Database & Helper Sistem Informasi Wisata
 */

// Mulai sesi jika belum aktif
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Konfigurasi Database
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '12345678'); // Password MySQL AppServ
define('DB_NAME', 'wisata');

// Base URL detection (otomatis menyesuaikan path di localhost)
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443)) ? "https://" : "http://";
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? ''));
// Hapus subfolder admin / petugas / pengunjung jika sedang berada di dalamnya
$baseDir = preg_replace('/(\/admin|\/petugas|\/pengunjung)$/', '', $scriptDir);
$baseUrl = rtrim($protocol . $host . $baseDir, '/') . '/';

define('BASE_URL', $baseUrl);
define('BASE_PATH', str_replace('\\', '/', dirname(__DIR__)) . '/');

// Koneksi PDO dengan fallback password otomatis
$pdo = null;
$passwords_to_try = [DB_PASS, '', 'root', '123456', '1234', 'appserv', 'admin'];
$connected = false;

foreach ($passwords_to_try as $pwd) {
    try {
        $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4", DB_USER, $pwd, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
        $connected = true;
        break;
    } catch (PDOException $e) {
        continue;
    }
}

if (!$connected) {
    // Coba konek tanpa dbname jika database belum di-create
    foreach ($passwords_to_try as $pwd) {
        try {
            $pdo_init = new PDO("mysql:host=" . DB_HOST . ";charset=utf8mb4", DB_USER, $pwd);
            $pdo_init->exec("CREATE DATABASE IF NOT EXISTS `" . DB_NAME . "` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4", DB_USER, $pwd, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
            $connected = true;
            break;
        } catch (PDOException $ex) {
            continue;
        }
    }
}

/**
 * Helper: Ambil data pengaturan web
 */
function getSettings($pdo) {
    if (!$pdo) {
        return [
            'nama_sistem' => 'Pesona Nusantara',
            'tagline' => 'Jelajahi Pesona Keindahan Wisata Terbaik di Indonesia',
            'deskripsi' => 'Platform informasi dan pemesanan tiket wisata terintegrasi dan berkelas.',
            'kontak_email' => 'kontak@pesonanusantara.id',
            'kontak_telp' => '+62 821-9988-7766',
            'alamat' => 'Jl. Pariwisata No. 88, Indonesia',
            'logo' => '',
            'banner' => '',
            'sosmed_instagram' => 'pesonanusantara.id',
            'sosmed_facebook' => 'pesonanusantara.official',
            'sosmed_youtube' => 'PesonaNusantaraTV'
        ];
    }
    try {
        $stmt = $pdo->query("SELECT * FROM pengaturan_web LIMIT 1");
        $res = $stmt->fetch();
        return $res ?: [
            'nama_sistem' => 'Pesona Nusantara',
            'tagline' => 'Jelajahi Pesona Keindahan Wisata Terbaik di Indonesia',
            'deskripsi' => 'Platform informasi dan pemesanan tiket wisata terintegrasi dan berkelas.',
            'kontak_email' => 'kontak@pesonanusantara.id',
            'kontak_telp' => '+62 821-9988-7766',
            'alamat' => 'Jl. Pariwisata No. 88, Indonesia',
            'logo' => '',
            'banner' => '',
            'sosmed_instagram' => 'pesonanusantara.id',
            'sosmed_facebook' => 'pesonanusantara.official',
            'sosmed_youtube' => 'PesonaNusantaraTV'
        ];
    } catch (Exception $e) {
        return [
            'nama_sistem' => 'Pesona Nusantara',
            'tagline' => 'Jelajahi Pesona Keindahan Wisata Terbaik di Indonesia',
            'deskripsi' => 'Platform informasi dan pemesanan tiket wisata terintegrasi dan berkelas.',
            'kontak_email' => 'kontak@pesonanusantara.id',
            'kontak_telp' => '+62 821-9988-7766',
            'alamat' => 'Jl. Pariwisata No. 88, Indonesia',
            'logo' => '',
            'banner' => '',
            'sosmed_instagram' => 'pesonanusantara.id',
            'sosmed_facebook' => 'pesonanusantara.official',
            'sosmed_youtube' => 'PesonaNusantaraTV'
        ];
    }
}

/**
 * Helper: Cek hak akses user
 */
function checkAuth($roleRequired = null) {
    if (!isset($_SESSION['user_id'])) {
        header("Location: " . BASE_URL . "login.php?msg=login_required");
        exit;
    }
    if ($roleRequired !== null) {
        $userRole = $_SESSION['user_role'] ?? '';
        // Superuser admin selalu memiliki akses ke semua panel
        if ($userRole === 'admin') {
            return;
        }
        $allowed = is_array($roleRequired) ? in_array($userRole, $roleRequired) : ($userRole === $roleRequired);
        if (!$allowed) {
            if ($userRole === 'petugas') {
                header("Location: " . BASE_URL . "petugas/index.php");
            } elseif ($userRole === 'pengunjung') {
                header("Location: " . BASE_URL . "pengunjung/index.php");
            } else {
                header("Location: " . BASE_URL . "index.php?msg=unauthorized");
            }
            exit;
        }
    }
}

/**
 * Helper: Format Rupiah
 */
function formatRupiah($angka) {
    return 'Rp ' . number_format((float)$angka, 0, ',', '.');
}

/**
 * Helper: Format Tanggal Indonesia
 */
function formatTanggalIndo($tanggal) {
    if (!$tanggal || $tanggal == '0000-00-00') return '-';
    $bulan = [
        1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
        'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'
    ];
    $pecahkan = explode('-', date('Y-m-d', strtotime($tanggal)));
    return $pecahkan[2] . ' ' . $bulan[(int)$pecahkan[1]] . ' ' . $pecahkan[0];
}

/**
 * Helper: Dapatkan Nama Hari dari Tanggal
 */
function getNamaHariIndo($tanggal) {
    $hari = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
    $index = date('w', strtotime($tanggal));
    return $hari[$index];
}

/**
 * Helper: Flash Message
 */
function setFlash($type, $message) {
    $_SESSION['flash'] = [
        'type' => $type, // success, danger, warning, info
        'message' => $message
    ];
}

function getFlash() {
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}

/**
 * Helper: Render Ikon Kategori (Mendukung Emote / Emoji & Font Awesome)
 * Kompatibel dengan PHP 7.x & 8.x
 */
function renderKategoriIcon($icon, $default = 'fa-map-pin', $extraClass = '') {
    $icon = trim($icon ?? '');
    if (empty($icon)) {
        return '<i class="fa-solid ' . htmlspecialchars($default) . (!empty($extraClass) ? ' ' . htmlspecialchars($extraClass) : '') . '"></i>';
    }

    // Mapping ikon Font Awesome Pro ke emoji / icon free jika ada
    $proMap = [
        'fa-ferris-wheel' => '🎡',
        'fa-roller-coaster' => '🎢',
        'fa-water-slide' => 'fa-water',
    ];
    if (isset($proMap[$icon])) {
        $icon = $proMap[$icon];
    }

    // Cek apakah berupa Emote / Emoji (tidak diawali fa- atau mengandung karakter emoji)
    $hasFa = (strpos($icon, 'fa-') === 0 || strpos($icon, 'fa ') === 0 || strpos($icon, 'fa-solid ') === 0 || strpos($icon, 'fas ') === 0);

    if (!$hasFa || preg_match('/[\x{1F300}-\x{1F9FF}\x{2600}-\x{26FF}\x{2700}-\x{27BF}]/u', $icon)) {
        return '<span class="kategori-emote-icon' . (!empty($extraClass) ? ' ' . htmlspecialchars($extraClass) : '') . '" style="font-style: normal; line-height: 1; display: inline-flex; align-items: center; justify-content: center;">' . $icon . '</span>';
    }

    $cleanFa = str_replace(['fa-solid ', 'fa '], '', $icon);
    if (strpos($cleanFa, 'fa-') !== 0) {
        $cleanFa = 'fa-' . $cleanFa;
    }

    return '<i class="fa-solid ' . htmlspecialchars($cleanFa) . (!empty($extraClass) ? ' ' . htmlspecialchars($extraClass) : '') . '"></i>';
}

