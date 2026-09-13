<?php
require_once __DIR__ . '/../config/database.php';
checkAuth('petugas');

$today = date('Y-m-d');
$userId = $_SESSION['user_id'];

// Handle Quick Presensi Direct from Dashboard
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['action_quick_presensi'])) {
    $destId = (int)($_POST['destinasi_id'] ?? 0);
    $jml = (int)($_POST['jumlah_pengunjung'] ?? 1);
    $jenis = $_POST['jenis_kunjungan'] ?? 'individu';
    $hari = getNamaHariIndo($today);
    $ket = trim($_POST['keterangan'] ?? 'Quick Entry Loket');

    if ($destId > 0 && $jml > 0) {
        $ins = $pdo->prepare("INSERT INTO presensi_kunjungan (petugas_id, destinasi_id, tanggal_kunjungan, hari, jumlah_pengunjung, jenis_kunjungan, keterangan, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())");
        if ($ins->execute([$userId, $destId, $today, $hari, $jml, $jenis, $ket])) {
            setFlash('success', 'Presensi cepat <strong>' . number_format($jml) . ' wisatawan</strong> berhasil dicatat ke sistem!');
        } else {
            setFlash('danger', 'Gagal mencatat presensi cepat.');
        }
    } else {
        setFlash('danger', 'Mohon pilih destinasi dan masukkan jumlah pengunjung yang valid (min. 1).');
    }
    header("Location: " . BASE_URL . "petugas/index.php");
    exit;
}

// Statistik Petugas Hari Ini
$stmtHariIni = $pdo->prepare("SELECT COALESCE(SUM(jumlah_pengunjung), 0) as total_pengunjung, COUNT(id) as total_entri 
                              FROM presensi_kunjungan 
                              WHERE tanggal_kunjungan = ? AND petugas_id = ?");
$stmtHariIni->execute([$today, $userId]);
$statHariIni = $stmtHariIni->fetch();

// Total Tiket Yang Telah Divalidasi Hari Ini (Semua Petugas)
$stmtValidasi = (int)$pdo->query("SELECT COUNT(*) FROM pemesanan WHERE status_kunjungan = 'sudah_digunakan' AND DATE(waktu_checkin) = CURDATE()")->fetchColumn();
$stmtValidasiOrang = (int)$pdo->query("SELECT COALESCE(SUM(jumlah_tiket), 0) FROM pemesanan WHERE status_kunjungan = 'sudah_digunakan' AND DATE(waktu_checkin) = CURDATE()")->fetchColumn();

// Total Seluruh Kunjungan yang Dicatat Petugas Ini (All Time)
$stmtTotal = $pdo->prepare("SELECT COALESCE(SUM(jumlah_pengunjung), 0) as total_all 
                            FROM presensi_kunjungan 
                            WHERE petugas_id = ?");
$stmtTotal->execute([$userId]);
$totalAll = (int)$stmtTotal->fetchColumn();

// Destinasi Buka
$totalDestinasiBuka = (int)$pdo->query("SELECT COUNT(*) FROM destinasi WHERE status = 'buka'")->fetchColumn();

// Kunjungan Terbaru yang Diinput Petugas Ini (5 Terakhir)
$stmtTerbaru = $pdo->prepare("SELECT k.*, d.nama_destinasi, d.lokasi 
                              FROM presensi_kunjungan k 
                              JOIN destinasi d ON k.destinasi_id = d.id 
                              WHERE k.petugas_id = ? 
                              ORDER BY k.id DESC LIMIT 6");
$stmtTerbaru->execute([$userId]);
$kunjunganTerbaru = $stmtTerbaru->fetchAll();

// 5 Tiket Terakhir yang Check-in Masuk Hari Ini (Live Stream)
$stmtGateLive = $pdo->query("SELECT p.*, d.nama_destinasi, u.nama as nama_user 
                             FROM pemesanan p 
                             JOIN destinasi d ON p.destinasi_id = d.id 
                             JOIN users u ON p.user_id = u.id 
                             WHERE p.status_kunjungan = 'sudah_digunakan' 
                             ORDER BY p.waktu_checkin DESC LIMIT 5");
$gateLiveList = $stmtGateLive->fetchAll();

// List Destinasi untuk Quick Input
$destinasiList = $pdo->query("SELECT id, nama_destinasi, lokasi, harga_tiket FROM destinasi WHERE status = 'buka' ORDER BY nama_destinasi ASC")->fetchAll();

$pageTitle = "Dashboard Operasional Petugas Loket";
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';
$flash = getFlash();
?>

<main class="admin-main">
  
  <!-- Topbar Luxury -->
  <div class="admin-topbar-luxury">
    <div>
      <div style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.25rem;">
        <span class="badge-luxury badge-luxury-primary">
          <span style="font-size: 0.85rem;">📡</span> Terminal Gate Aktif
        </span>
        <span style="font-size: 0.8rem; color: #64748b;">
          <?= formatTanggalIndo($today) ?> • <span id="petugasClock" style="font-weight: 800; color: #0d9488; font-family: monospace;"><?= date('H:i:s') ?> WIB</span>
        </span>
      </div>
      <h1 style="font-size: 1.45rem; font-weight: 800; color: #0f172a; margin: 0; letter-spacing: -0.02em;">
        Dashboard Operasional Loket & Gate 🎟️
      </h1>
      <p style="font-size: 0.82rem; color: #64748b; margin: 0.15rem 0 0 0;">
        Selamat bertugas, <strong><?= htmlspecialchars($_SESSION['user_nama']) ?></strong>! Kelola presensi masuk dan validasi tiket wisatawan dengan cepat. ⚡
      </p>
    </div>

    <div style="display: flex; align-items: center; gap: 0.6rem; flex-wrap: wrap;">
      <a href="<?= BASE_URL ?>petugas/validasi_tiket.php" class="btn btn-primary btn-sm btn-luxury-pulse" style="font-weight: 800; box-shadow: 0 4px 14px rgba(13, 148, 136, 0.4); padding: 0.55rem 1.15rem; border-radius: 0.65rem;">
        <i class="fa-solid fa-qrcode"></i> Scan E-Tiket Gate
      </a>
      <a href="<?= BASE_URL ?>petugas/input_kunjungan.php" class="btn btn-secondary btn-sm" style="background: #ffffff; border: 1px solid #cbd5e1; font-weight: 700; padding: 0.55rem 1rem; border-radius: 0.65rem;">
        <i class="fa-solid fa-pen-to-square"></i> Input Presensi Lengkap
      </a>
    </div>
  </div>

  <!-- Flash Notification -->
  <?php if ($flash): ?>
    <div class="alert alert-<?= $flash['type'] ?> mb-4" style="border-radius: 0.85rem; padding: 0.9rem 1.25rem; display: flex; align-items: center; gap: 0.75rem; box-shadow: 0 4px 12px rgba(0,0,0,0.03);">
      <i class="fa-solid <?= $flash['type'] === 'success' ? 'fa-circle-check' : 'fa-circle-exclamation' ?>" style="font-size: 1.25rem;"></i>
      <div style="font-size: 0.88rem; line-height: 1.4;"><?= $flash['message'] ?></div>
    </div>
  <?php endif; ?>

  <!-- 4 Luxury KPI Stat Cards Grid -->
  <div class="grid grid-cols-4 gap-4 mb-5">
    
    <!-- Stat 1: Pengunjung Shift Hari Ini -->
    <div class="kpi-card-luxury">
      <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 0.65rem;">
        <div class="kpi-icon-wrap" style="background: #ccfbf1; color: #0d9488;">
          <i class="fa-solid fa-person-walking-luggage"></i>
        </div>
        <span class="badge-luxury badge-luxury-primary" style="font-size: 0.68rem;">Shift Anda</span>
      </div>
      <span style="font-size: 0.7rem; font-weight: 800; color: #64748b; text-transform: uppercase; letter-spacing: 0.05em; display: block;">
        Input Kunjungan Hari Ini
      </span>
      <h2 style="font-size: 1.55rem; font-weight: 900; color: #0f172a; margin: 0.15rem 0 0 0; font-family: 'Outfit', sans-serif;">
        <?= number_format($statHariIni['total_pengunjung']) ?> <span style="font-size: 0.85rem; font-weight: 600; color: #64748b;">Orang</span>
      </h2>
      <span style="font-size: 0.72rem; color: #0d9488; font-weight: 700; display: block; margin-top: 0.35rem;">
        <i class="fa-solid fa-circle-check"></i> Dari <?= $statHariIni['total_entri'] ?> entri loket
      </span>
    </div>

    <!-- Stat 2: Validasi Gate Masuk Hari Ini -->
    <a href="<?= BASE_URL ?>petugas/validasi_tiket.php" style="text-decoration: none; color: inherit;">
      <div class="kpi-card-luxury" style="cursor: pointer;">
        <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 0.65rem;">
          <div class="kpi-icon-wrap" style="background: #dcfce7; color: #15803d;">
            <i class="fa-solid fa-door-open"></i>
          </div>
          <span class="badge-luxury badge-luxury-success" style="font-size: 0.68rem;">Gate Masuk</span>
        </div>
        <span style="font-size: 0.7rem; font-weight: 800; color: #64748b; text-transform: uppercase; letter-spacing: 0.05em; display: block;">
          E-Tiket Check-in Hari Ini
        </span>
        <h2 style="font-size: 1.55rem; font-weight: 900; color: #0f172a; margin: 0.15rem 0 0 0; font-family: 'Outfit', sans-serif;">
          <?= number_format($stmtValidasi) ?> <span style="font-size: 0.85rem; font-weight: 600; color: #64748b;">Scan</span>
        </h2>
        <span style="font-size: 0.72rem; color: #15803d; font-weight: 700; display: block; margin-top: 0.35rem;">
          <i class="fa-solid fa-users"></i> <?= number_format($stmtValidasiOrang) ?> orang wisatawan
        </span>
      </div>
    </a>

    <!-- Stat 3: Total Akumulasi Kontribusi Anda -->
    <a href="<?= BASE_URL ?>petugas/riwayat_input.php" style="text-decoration: none; color: inherit;">
      <div class="kpi-card-luxury" style="cursor: pointer;">
        <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 0.65rem;">
          <div class="kpi-icon-wrap" style="background: #e0f2fe; color: #0284c7;">
            <i class="fa-solid fa-clipboard-check"></i>
          </div>
          <span class="badge-luxury badge-luxury-info" style="font-size: 0.68rem;">Semua Waktu</span>
        </div>
        <span style="font-size: 0.7rem; font-weight: 800; color: #64748b; text-transform: uppercase; letter-spacing: 0.05em; display: block;">
          Total Entri Kunjungan Anda
        </span>
        <h2 style="font-size: 1.55rem; font-weight: 900; color: #0f172a; margin: 0.15rem 0 0 0; font-family: 'Outfit', sans-serif;">
          <?= number_format($totalAll) ?> <span style="font-size: 0.85rem; font-weight: 600; color: #64748b;">Orang</span>
        </h2>
        <span style="font-size: 0.72rem; color: #0284c7; font-weight: 700; display: block; margin-top: 0.35rem;">
          <i class="fa-solid fa-arrow-up-right-from-square"></i> Lihat semua riwayat
        </span>
      </div>
    </a>

    <!-- Stat 4: Destinasi Buka Operasional -->
    <div class="kpi-card-luxury">
      <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 0.65rem;">
        <div class="kpi-icon-wrap" style="background: #fef3c7; color: #d97706;">
          <i class="fa-solid fa-mountain-sun"></i>
        </div>
        <span class="badge-luxury badge-luxury-warning" style="font-size: 0.68rem;">Operasional</span>
      </div>
      <span style="font-size: 0.7rem; font-weight: 800; color: #64748b; text-transform: uppercase; letter-spacing: 0.05em; display: block;">
        Destinasi Beroperasi
      </span>
      <h2 style="font-size: 1.55rem; font-weight: 900; color: #0f172a; margin: 0.15rem 0 0 0; font-family: 'Outfit', sans-serif;">
        <?= $totalDestinasiBuka ?> <span style="font-size: 0.85rem; font-weight: 600; color: #64748b;">Lokasi</span>
      </h2>
      <span style="font-size: 0.72rem; color: #d97706; font-weight: 700; display: block; margin-top: 0.35rem;">
        <i class="fa-solid fa-circle-check"></i> Siap melayani wisatawan
      </span>
    </div>

  </div>

  <!-- Main 2-Column Operational Grid -->
  <div class="grid grid-cols-12 gap-5 mb-5">
    
    <!-- Left Column: Fast Presensi Entry Widget (7 cols) -->
    <div class="col-span-7" style="grid-column: span 7 / span 7;">
      <div class="card p-5 bg-white" style="border-radius: 1.15rem; border: 1.5px solid #ccfbf1; box-shadow: 0 4px 15px -3px rgba(15, 23, 42, 0.04); height: 100%;">
        <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 1.15rem; padding-bottom: 0.75rem; border-bottom: 1px solid #f1f5f9;">
          <div>
            <span class="badge-luxury badge-luxury-primary" style="font-size: 0.68rem;">
              <i class="fa-solid fa-bolt text-amber"></i> Fast Entry Loket
            </span>
            <h3 style="font-size: 1.15rem; font-weight: 800; color: #0f172a; margin: 0.2rem 0 0 0;">
              Catat Presensi Wisatawan Cepat
            </h3>
          </div>
          <span style="font-size: 0.75rem; color: #64748b; font-weight: 600;">Shift <?= date('d M Y') ?></span>
        </div>

        <form action="<?= BASE_URL ?>petugas/index.php" method="POST">
          <input type="hidden" name="action_quick_presensi" value="1">

          <div class="form-group mb-3">
            <label class="form-label font-bold text-xs uppercase text-muted" style="display: block; margin-bottom: 0.3rem;">
              Pilih Destinasi Wisata <span class="text-danger">*</span>
            </label>
            <select name="destinasi_id" id="quickDestinasiSelect" class="form-control" style="font-weight: 700; border-radius: 0.65rem; height: 40px; font-size: 0.85rem;" required>
              <option value="">-- Pilih Lokasi Objek Wisata --</option>
              <?php foreach ($destinasiList as $d): ?>
                <option value="<?= $d['id'] ?>">
                  <?= htmlspecialchars($d['nama_destinasi']) ?> (<?= htmlspecialchars($d['lokasi']) ?>) - Rp <?= number_format($d['harga_tiket'], 0, ',', '.') ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="grid grid-cols-2 gap-3 mb-3">
            <div class="form-group">
              <label class="form-label font-bold text-xs uppercase text-muted" style="display: block; margin-bottom: 0.3rem;">
                Jumlah Wisatawan <span class="text-danger">*</span>
              </label>
              <div style="display: flex; align-items: center; gap: 0.35rem;">
                <button type="button" class="btn btn-secondary btn-sm" onclick="adjustQuickCount(-1)" style="padding: 0.4rem 0.75rem; font-weight: 800; border-radius: 0.5rem; height: 38px;">-</button>
                <input type="number" name="jumlah_pengunjung" id="quickJmlInput" class="form-control text-center" value="1" min="1" style="font-weight: 800; font-size: 1.15rem; font-family: 'Outfit', sans-serif; height: 38px; border-radius: 0.5rem;" required oninput="autoCheckJenis(this.value)">
                <button type="button" class="btn btn-secondary btn-sm" onclick="adjustQuickCount(1)" style="padding: 0.4rem 0.75rem; font-weight: 800; border-radius: 0.5rem; height: 38px;">+</button>
              </div>
              <!-- Quick Add Buttons -->
              <div style="display: flex; gap: 0.25rem; margin-top: 0.4rem;">
                <button type="button" class="btn btn-light btn-xs" onclick="setQuickCount(5)" style="font-size: 0.72rem; padding: 0.15rem 0.5rem; border: 1px solid #e2e8f0; border-radius: 0.35rem; font-weight: 700;">+5</button>
                <button type="button" class="btn btn-light btn-xs" onclick="setQuickCount(10)" style="font-size: 0.72rem; padding: 0.15rem 0.5rem; border: 1px solid #e2e8f0; border-radius: 0.35rem; font-weight: 700;">+10</button>
                <button type="button" class="btn btn-light btn-xs" onclick="setQuickCount(20)" style="font-size: 0.72rem; padding: 0.15rem 0.5rem; border: 1px solid #e2e8f0; border-radius: 0.35rem; font-weight: 700;">+20</button>
                <button type="button" class="btn btn-light btn-xs" onclick="setQuickCount(50)" style="font-size: 0.72rem; padding: 0.15rem 0.5rem; border: 1px solid #e2e8f0; border-radius: 0.35rem; font-weight: 700;">+50</button>
              </div>
            </div>

            <div class="form-group">
              <label class="form-label font-bold text-xs uppercase text-muted" style="display: block; margin-bottom: 0.3rem;">Jenis Kunjungan</label>
              <select name="jenis_kunjungan" id="quickJenisInput" class="form-control" style="border-radius: 0.65rem; font-weight: 700; height: 38px; font-size: 0.85rem;">
                <option value="individu">Individu / Perorangan</option>
                <option value="rombongan">Rombongan / Tour Bus</option>
              </select>
              <span style="font-size: 0.7rem; color: #64748b; display: block; margin-top: 0.35rem;">
                *Otomatis rombongan jika ≥ 10 org
              </span>
            </div>
          </div>

          <div class="form-group mb-4">
            <label class="form-label font-bold text-xs uppercase text-muted" style="display: block; margin-bottom: 0.3rem;">Catatan Loket (Opsional)</label>
            <input type="text" name="keterangan" class="form-control" placeholder="Cth: Pembelian langsung loket pintu barat..." style="border-radius: 0.65rem; font-size: 0.85rem; height: 38px;">
          </div>

          <button type="submit" class="btn btn-primary btn-block btn-luxury-pulse" style="font-weight: 800; border-radius: 0.75rem; padding: 0.75rem; box-shadow: 0 4px 14px rgba(13, 148, 136, 0.35); font-size: 0.95rem; width: 100%;">
            <i class="fa-solid fa-check-to-slot"></i> Simpan Catatan Masuk Loket
          </button>
        </form>
      </div>
    </div>

    <!-- Right Column: Live Gate Check-in Stream (5 cols) -->
    <div class="col-span-5" style="grid-column: span 5 / span 5;">
      <div class="card p-5 bg-white" style="border-radius: 1.15rem; border: 1.5px solid #e2e8f0; box-shadow: 0 4px 15px -3px rgba(15, 23, 42, 0.04); height: 100%; display: flex; flex-direction: column;">
        <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 1rem; padding-bottom: 0.75rem; border-bottom: 1px solid #f1f5f9;">
          <h3 style="font-size: 1.05rem; font-weight: 800; color: #0f172a; margin: 0; display: flex; align-items: center; gap: 0.4rem;">
            <i class="fa-solid fa-qrcode text-teal-600"></i> Live Gate Check-In
          </h3>
          <a href="<?= BASE_URL ?>petugas/validasi_tiket.php" class="text-xs font-bold" style="color: #0d9488; text-decoration: none;">
            Buka Scanner <i class="fa-solid fa-arrow-right"></i>
          </a>
        </div>

        <div style="flex: 1; overflow-y: auto; max-height: 290px;">
          <?php if (empty($gateLiveList)): ?>
            <div style="text-align: center; padding: 2.5rem 1rem; color: #94a3b8;">
              <i class="fa-solid fa-ticket" style="font-size: 2.2rem; color: #cbd5e1; margin-bottom: 0.5rem; display: block;"></i>
              <span style="font-size: 0.85rem; font-weight: 600;">Belum ada scan e-tiket hari ini</span>
              <p style="font-size: 0.75rem; color: #94a3b8; margin: 0.25rem 0 0 0;">Aktivitas scan gate akan muncul di sini secara real-time.</p>
            </div>
          <?php else: ?>
            <ul style="list-style: none; padding: 0; margin: 0; display: flex; flex-direction: column; gap: 0.5rem;">
              <?php foreach ($gateLiveList as $gl): ?>
                <li style="display: flex; align-items: center; justify-content: space-between; padding: 0.65rem 0.85rem; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 0.75rem; transition: background 0.2s ease;">
                  <div style="overflow: hidden; flex: 1; padding-right: 0.5rem;">
                    <div style="display: flex; align-items: center; gap: 0.4rem;">
                      <code style="font-size: 0.75rem; font-weight: 800; color: #0f766e; background: #ccfbf1; padding: 0.15rem 0.4rem; border-radius: 4px; font-family: monospace;">
                        <?= htmlspecialchars($gl['kode_booking']) ?>
                      </code>
                      <span style="font-size: 0.78rem; font-weight: 700; color: #1e293b; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                        <?= htmlspecialchars($gl['nama_pemesan'] ?? $gl['nama_user']) ?>
                      </span>
                    </div>
                    <span style="font-size: 0.72rem; color: #64748b; display: block; margin-top: 0.15rem;">
                      <?= htmlspecialchars($gl['nama_destinasi']) ?> • <strong style="color: #0d9488;"><?= $gl['jumlah_tiket'] ?> Wisatawan</strong>
                    </span>
                  </div>
                  <div style="text-align: right;">
                    <span style="font-size: 0.7rem; font-weight: 800; color: #15803d; background: #dcfce7; padding: 0.2rem 0.5rem; border-radius: 9999px; display: inline-block;">
                      <i class="fa-solid fa-circle-check"></i> <?= $gl['waktu_checkin'] ? date('H:i', strtotime($gl['waktu_checkin'])) : '-' ?>
                    </span>
                  </div>
                </li>
              <?php endforeach; ?>
            </ul>
          <?php endif; ?>
        </div>

        <div style="margin-top: 1rem; padding-top: 0.75rem; border-top: 1px solid #f1f5f9; text-align: center;">
          <a href="<?= BASE_URL ?>petugas/validasi_tiket.php" class="btn btn-light btn-sm btn-block" style="font-size: 0.8rem; font-weight: 700; color: #0d9488; border: 1px solid #ccfbf1; border-radius: 0.65rem; width: 100%; padding: 0.5rem;">
            <i class="fa-solid fa-camera"></i> Buka Kamera Scanner Gate
          </a>
        </div>
      </div>
    </div>

  </div>

  <!-- Riwayat Presensi Terakhir Oleh Petugas Ini (Full Width Table) -->
  <div class="card-table-luxury">
    <div style="padding: 1.15rem 1.5rem; border-bottom: 1px solid #e2e8f0; display: flex; align-items: center; justify-content: space-between; background: #ffffff; flex-wrap: wrap; gap: 0.75rem;">
      <div>
        <h3 style="font-size: 1.05rem; font-weight: 800; color: #0f172a; margin: 0; display: flex; align-items: center; gap: 0.4rem;">
          <i class="fa-solid fa-clock-rotate-left text-teal-600"></i> Catatan Input Presensi Terakhir Anda
        </h3>
        <p style="font-size: 0.78rem; color: #64748b; margin: 0.15rem 0 0 0;">
          Daftar entri wisatawan yang baru saja Anda catat ke sistem loket.
        </p>
      </div>

      <a href="<?= BASE_URL ?>petugas/riwayat_input.php" class="btn btn-secondary btn-sm" style="font-size: 0.78rem; border-radius: 0.65rem; font-weight: 700; background: #ffffff; border: 1px solid #cbd5e1;">
        <i class="fa-solid fa-list-check"></i> Buka Seluruh Riwayat
      </a>
    </div>

    <div style="overflow-x: auto; width: 100%;">
      <table class="table-luxury" style="width: 100%;">
        <thead>
          <tr>
            <th style="width: 14%;">Waktu Input</th>
            <th style="width: 28%;">Destinasi Objek Wisata</th>
            <th style="width: 18%;">Tanggal Kunjungan</th>
            <th style="width: 15%; text-align: center;">Jumlah Masuk</th>
            <th style="width: 13%;">Tipe Kunjungan</th>
            <th style="width: 12%; text-align: right;">Status</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($kunjunganTerbaru)): ?>
            <tr>
              <td colspan="6" style="text-align: center; padding: 2.5rem 1rem; color: #94a3b8;">
                <p style="font-size: 0.88rem; font-weight: 600; color: #64748b; margin: 0;">Belum ada catatan kunjungan yang Anda simpan hari ini.</p>
              </td>
            </tr>
          <?php else: ?>
            <?php foreach ($kunjunganTerbaru as $kt): ?>
              <tr>
                <td>
                  <div style="font-size: 0.82rem; font-weight: 700; color: #0f172a;">
                    <?= date('d/m/Y', strtotime($kt['created_at'])) ?>
                  </div>
                  <span style="font-size: 0.7rem; color: #94a3b8; font-weight: 600;">
                    <?= date('H:i', strtotime($kt['created_at'])) ?> WIB
                  </span>
                </td>
                <td>
                  <strong class="text-dark" style="font-size: 0.9rem; color: #0f172a;"><?= htmlspecialchars($kt['nama_destinasi']) ?></strong>
                  <span class="text-xs text-muted" style="display: block; margin-top: 0.1rem; color: #64748b;">
                    <i class="fa-solid fa-location-dot" style="color: #ef4444; font-size: 0.7rem;"></i> <?= htmlspecialchars($kt['lokasi']) ?>
                  </span>
                </td>
                <td>
                  <strong style="color: #1e293b; font-size: 0.85rem;"><?= formatTanggalIndo($kt['tanggal_kunjungan']) ?></strong>
                  <span class="badge badge-light text-xs" style="margin-left: 0.3rem; background: #f1f5f9; padding: 0.15rem 0.4rem; border-radius: 4px; font-weight: 700; color: #0284c7;"><?= htmlspecialchars($kt['hari']) ?></span>
                </td>
                <td style="text-align: center;">
                  <div style="display: inline-block; background: #ccfbf1; border: 1px solid #99f6e4; padding: 0.25rem 0.75rem; border-radius: 9999px;">
                    <strong style="color: #0f766e; font-size: 0.95rem; font-family: 'Outfit', sans-serif;">
                      <?= number_format($kt['jumlah_pengunjung']) ?>
                    </strong>
                    <span style="font-size: 0.72rem; color: #0f766e; font-weight: 600;"> Orang</span>
                  </div>
                </td>
                <td>
                  <?php if ($kt['jenis_kunjungan'] === 'rombongan'): ?>
                    <span class="badge-luxury badge-luxury-warning" style="font-size: 0.7rem;">
                      <i class="fa-solid fa-people-group"></i> Rombongan
                    </span>
                  <?php else: ?>
                    <span class="badge-luxury badge-luxury-primary" style="font-size: 0.7rem;">
                      <i class="fa-solid fa-user"></i> Individu
                    </span>
                  <?php endif; ?>
                </td>
                <td style="text-align: right;">
                  <span class="badge-luxury badge-luxury-success" style="font-size: 0.7rem;">
                    <i class="fa-solid fa-circle-check"></i> Tercatat
                  </span>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

</main>

<script>
// Digital Clock Live Synchronizer
setInterval(function() {
  const now = new Date();
  const hours = String(now.getHours()).padStart(2, '0');
  const minutes = String(now.getMinutes()).padStart(2, '0');
  const seconds = String(now.getSeconds()).padStart(2, '0');
  const clockElem = document.getElementById('petugasClock');
  if (clockElem) {
    clockElem.innerText = `${hours}:${minutes}:${seconds} WIB`;
  }
}, 1000);

function adjustQuickCount(delta) {
  const input = document.getElementById('quickJmlInput');
  let val = parseInt(input.value) || 1;
  val = Math.max(1, val + delta);
  input.value = val;
  autoCheckJenis(val);
}

function setQuickCount(amount) {
  const input = document.getElementById('quickJmlInput');
  let val = parseInt(input.value) || 0;
  val += amount;
  input.value = val;
  autoCheckJenis(val);
}

function autoCheckJenis(count) {
  const cnt = parseInt(count) || 1;
  const jenisSelect = document.getElementById('quickJenisInput');
  if (cnt >= 10 && jenisSelect.value === 'individu') {
    jenisSelect.value = 'rombongan';
  } else if (cnt < 10 && jenisSelect.value === 'rombongan') {
    jenisSelect.value = 'individu';
  }
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
