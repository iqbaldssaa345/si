<?php
require_once __DIR__ . '/../config/database.php';
checkAuth('petugas');

$searchKode = strtoupper(trim($_GET['kode'] ?? ''));
$tiketData = null;
$msg = '';
$msgType = '';
$petugasId = $_SESSION['user_id'];
$today = date('Y-m-d');

// Handle Check-in Action (Konfirmasi Validasi)
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['action_checkin'])) {
    $pemesananId = (int)$_POST['pemesanan_id'];
    $destId = (int)$_POST['destinasi_id'];
    $jumlahOrang = (int)$_POST['jumlah_tiket'];
    $tipe = $_POST['tipe_rombongan'] ?? 'individu';
    $tglKunjungan = date('Y-m-d');
    $hari = getNamaHariIndo($tglKunjungan);
    $kodeBookingVal = trim($_POST['kode_booking_val'] ?? $searchKode);

    // Update status pemesanan ke lunas & sudah digunakan
    $update = $pdo->prepare("UPDATE pemesanan SET status_bayar = 'lunas', status_kunjungan = 'sudah_digunakan', waktu_checkin = NOW() WHERE id = ?");
    $update->execute([$pemesananId]);

    // Masukkan ke tabel presensi_kunjungan
    $ins = $pdo->prepare("INSERT INTO presensi_kunjungan (petugas_id, destinasi_id, tanggal_kunjungan, hari, jumlah_pengunjung, jenis_kunjungan, keterangan, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())");
    $ins->execute([$petugasId, $destId, $tglKunjungan, $hari, $jumlahOrang, $tipe, 'Validasi Gate QR E-Ticket: ' . $kodeBookingVal]);

    setFlash('success', '✅ <strong>VALIDASI BERHASIL!</strong> E-Ticket <strong>' . htmlspecialchars($kodeBookingVal) . '</strong> telah divalidasi. Rombongan sebanyak <strong>' . $jumlahOrang . ' orang</strong> dipersilakan masuk gerbang wisata.');
    header("Location: " . BASE_URL . "petugas/validasi_tiket.php?kode=" . urlencode($kodeBookingVal) . "&success_validated=1");
    exit;
}

// Cari Tiket jika ada kode booking
if (!empty($searchKode)) {
    // Bersihkan prefix QR- jika discan dari format QR
    $cleanKode = str_replace('QR-', '', $searchKode);

    $stmt = $pdo->prepare("SELECT p.*, d.nama_destinasi, d.lokasi, d.harga_tiket as harga_asli, u.nama as nama_user, u.email as email_user, u.no_telp as user_telp 
                           FROM pemesanan p 
                           JOIN destinasi d ON p.destinasi_id = d.id 
                           JOIN users u ON p.user_id = u.id 
                           WHERE p.kode_booking = ? OR p.qr_code = ? LIMIT 1");
    $stmt->execute([$cleanKode, $searchKode]);
    $tiketData = $stmt->fetch();

    if (!$tiketData && empty($msg)) {
        $msg = "Kode Tiket / Barcode <strong>'$searchKode'</strong> tidak ditemukan dalam basis data sistem. Pastikan kode benar.";
        $msgType = "danger";
    }
}

// Counter Validasi Hari Ini
$todayCountValidated = (int)$pdo->query("SELECT COUNT(*) FROM pemesanan WHERE status_kunjungan = 'sudah_digunakan' AND DATE(waktu_checkin) = CURDATE()")->fetchColumn();
$todayTotalVisitorsCheckedIn = (int)$pdo->query("SELECT COALESCE(SUM(jumlah_tiket), 0) FROM pemesanan WHERE status_kunjungan = 'sudah_digunakan' AND DATE(waktu_checkin) = CURDATE()")->fetchColumn();

// Ambil 8 Tiket yang Terakhir Divalidasi Hari Ini
$recentValidated = $pdo->query("SELECT p.*, d.nama_destinasi, u.nama as nama_user 
                                FROM pemesanan p 
                                JOIN destinasi d ON p.destinasi_id = d.id 
                                JOIN users u ON p.user_id = u.id 
                                WHERE p.status_kunjungan = 'sudah_digunakan' 
                                ORDER BY p.waktu_checkin DESC LIMIT 8")->fetchAll();

$pageTitle = "Scan & Validasi E-Tiket Gate";
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';
$flash = getFlash();
?>

<main class="admin-main">
  
  <!-- Topbar Luxury -->
  <div class="admin-topbar-luxury">
    <div>
      <div style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.25rem;">
        <span class="badge-luxury badge-luxury-success">
          <i class="fa-solid fa-qrcode"></i> Gate Verification System
        </span>
        <span style="font-size: 0.8rem; color: #64748b;">
          Hari Ini: <strong style="color: #059669;"><?= $todayCountValidated ?> Tiket</strong> (<?= number_format($todayTotalVisitorsCheckedIn) ?> Wisatawan Masuk)
        </span>
      </div>
      <h1 style="font-size: 1.6rem; font-weight: 800; color: #0f172a; margin: 0; letter-spacing: -0.02em;">
        Scan & Validasi E-Tiket Gate
      </h1>
      <p style="font-size: 0.85rem; color: #64748b; margin: 0.2rem 0 0 0;">
        Pindai QR Barcode smartphone wisatawan via kamera atau masukkan kode booking untuk membuka akses gerbang.
      </p>
    </div>

    <div style="display: flex; gap: 0.5rem;">
      <a href="<?= BASE_URL ?>petugas/validasi_tiket.php" class="btn btn-secondary btn-sm" style="background: #ffffff; border: 1px solid #cbd5e1; font-weight: 700;" title="Reset / Scan Baru">
        <i class="fa-solid fa-rotate-left"></i> Scan Tiket Baru
      </a>
    </div>
  </div>

  <!-- Flash Notification -->
  <?php if ($flash): ?>
    <div class="alert alert-<?= $flash['type'] ?> mb-6" style="border-radius: 0.85rem; padding: 1rem 1.25rem; display: flex; align-items: center; gap: 0.75rem; box-shadow: 0 4px 12px rgba(0,0,0,0.04);">
      <i class="fa-solid <?= $flash['type'] === 'success' ? 'fa-circle-check' : 'fa-circle-exclamation' ?>" style="font-size: 1.4rem;"></i>
      <div style="font-size: 0.95rem; line-height: 1.4;"><?= $flash['message'] ?></div>
    </div>
  <?php endif; ?>

  <?php if ($msg): ?>
    <div class="alert alert-<?= $msgType ?> mb-6" style="border-radius: 0.85rem; padding: 1rem 1.25rem; display: flex; align-items: center; gap: 0.75rem; box-shadow: 0 4px 12px rgba(0,0,0,0.04);">
      <i class="fa-solid <?= $msgType === 'success' ? 'fa-circle-check' : 'fa-circle-exclamation' ?>" style="font-size: 1.4rem;"></i>
      <div style="font-size: 0.95rem; line-height: 1.4;"><?= $msg ?></div>
    </div>
  <?php endif; ?>

  <!-- Grid Scanner & Input Barcode -->
  <div class="grid grid-cols-12 gap-6 mb-7">
    
    <!-- Left: Real Web Camera Scanner UI Card (6 cols) -->
    <div class="col-span-6" style="grid-column: span 6 / span 6;">
      <div class="card p-6 bg-white shadow-sm" style="border-radius: 1.25rem; border: 1.5px solid #ccfbf1; height: 100%; display: flex; flex-direction: column; justify-content: space-between;">
        
        <div>
          <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 1rem;">
            <h3 style="font-size: 1.1rem; font-weight: 800; color: #0f172a; margin: 0; display: flex; align-items: center; gap: 0.5rem;">
              <i class="fa-solid fa-camera text-primary"></i> Scanner Kamera QR Optik
            </h3>
            <span class="badge-luxury badge-luxury-success" id="scannerStatusBadge">
              <span style="width:6px;height:6px;border-radius:50%;background:#22c55e;display:inline-block;"></span> Siap Pindai
            </span>
          </div>

          <!-- Real Camera Video Box / Scanner Frame -->
          <div style="background: #0f172a; border-radius: 1rem; padding: 1rem; text-align: center; color: white; position: relative; overflow: hidden; margin-bottom: 1rem; min-height: 240px; display: flex; flex-direction: column; align-items: center; justify-content: center;">
            
            <div id="qrReaderContainer" style="width: 100%; max-width: 320px; border-radius: 0.75rem; overflow: hidden; display: none;"></div>

            <div id="qrIdleViewfinder" style="width: 140px; height: 140px; border: 2px dashed rgba(45, 212, 191, 0.75); border-radius: 1rem; display: flex; align-items: center; justify-content: center; position: relative;">
              <i class="fa-solid fa-qrcode" style="font-size: 4.5rem; color: rgba(255, 255, 255, 0.35);"></i>
              <!-- Scanning Laser Line -->
              <div style="position: absolute; left: 0; right: 0; height: 3px; background: linear-gradient(90deg, transparent, #2dd4bf, transparent); box-shadow: 0 0 10px #2dd4bf; animation: scanLaser 2s infinite alternate;"></div>
            </div>

            <p id="qrHelpText" style="font-size: 0.8rem; color: #94a3b8; margin: 1rem 0 0 0;">
              Klik tombol di bawah untuk mengaktifkan kamera perangkat Anda.
            </p>
          </div>

          <!-- Camera Controls -->
          <div style="display: flex; gap: 0.5rem; justify-content: center; margin-bottom: 1rem;">
            <button type="button" id="btnToggleCamera" class="btn btn-primary btn-sm" onclick="toggleCameraScanner()" style="font-weight: 700; border-radius: 0.5rem; padding: 0.5rem 1rem;">
              <i class="fa-solid fa-video" id="cameraIcon"></i> <span id="cameraBtnText">Nyalakan Kamera Scanner</span>
            </button>
          </div>
        </div>

        <!-- Quick Test Code Shortcuts -->
        <div style="padding-top: 0.75rem; border-top: 1px solid #f1f5f9; display: flex; align-items: center; gap: 0.4rem; flex-wrap: wrap; font-size: 0.75rem;">
          <span style="color: #64748b; font-weight: 700;">Contoh Tiket Demo:</span>
          <a href="<?= BASE_URL ?>petugas/validasi_tiket.php?kode=BK-20260901-001" class="btn btn-secondary btn-xs" style="padding: 0.2rem 0.5rem; border-radius: 0.4rem; font-family: monospace;">
            BK-20260901-001
          </a>
          <a href="<?= BASE_URL ?>petugas/validasi_tiket.php?kode=BK-20260902-002" class="btn btn-secondary btn-xs" style="padding: 0.2rem 0.5rem; border-radius: 0.4rem; font-family: monospace;">
            BK-20260902-002
          </a>
        </div>

      </div>
    </div>

    <!-- Right: Manual Search & Barcode Lookup (6 cols) -->
    <div class="col-span-6" style="grid-column: span 6 / span 6;">
      <div class="card p-6 bg-white shadow-sm" style="border-radius: 1.25rem; border: 1.5px solid #e2e8f0; height: 100%; display: flex; flex-direction: column; justify-content: space-between;">
        
        <div>
          <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 1rem;">
            <h3 style="font-size: 1.1rem; font-weight: 800; color: #0f172a; margin: 0; display: flex; align-items: center; gap: 0.5rem;">
              <i class="fa-solid fa-barcode text-primary"></i> Input Kode Booking / Barcode
            </h3>
            <span class="badge badge-light text-xs font-bold">Manual / USB Gun</span>
          </div>

          <p style="font-size: 0.85rem; color: #64748b; line-height: 1.6; margin-bottom: 1.5rem;">
            Gunakan barcode scanner handheld atau ketik kode pemesanan secara manual di bawah ini.
          </p>

          <form action="<?= BASE_URL ?>petugas/validasi_tiket.php" method="GET" id="barcodeForm">
            <div class="form-group mb-4">
              <label style="display: block; font-size: 0.75rem; font-weight: 800; color: #334155; margin-bottom: 0.4rem; text-transform: uppercase; letter-spacing: 0.05em;">
                Kode Booking / Nomor E-Tiket <span style="color: #ef4444;">*</span>
              </label>
              <div style="position: relative;">
                <i class="fa-solid fa-keyboard" style="position: absolute; left: 1rem; top: 50%; transform: translateY(-50%); color: #0d9488; font-size: 1.15rem;"></i>
                <input type="text" name="kode" id="kodeInput" class="form-control" style="padding-left: 2.85rem; font-family: monospace; font-size: 1.15rem; font-weight: 800; text-transform: uppercase; border-radius: 0.75rem; border: 2px solid #cbd5e1;" placeholder="Cth: BK-20260901-001" value="<?= htmlspecialchars($searchKode) ?>" required autofocus autocomplete="off">
              </div>
            </div>

            <button type="submit" class="btn btn-primary btn-block btn-lg btn-luxury-pulse" style="font-weight: 800; border-radius: 0.75rem; padding: 0.9rem; box-shadow: 0 4px 14px rgba(13, 148, 136, 0.35);">
              <i class="fa-solid fa-magnifying-glass"></i> Periksa Keabsahan Tiket Sekarang
            </button>
          </form>
        </div>

        <div style="padding-top: 1rem; border-top: 1px solid #f1f5f9; margin-top: 1.25rem; font-size: 0.78rem; color: #64748b; display: flex; align-items: center; gap: 0.4rem;">
          <i class="fa-solid fa-circle-info text-primary"></i> Tekan <kbd style="background: #f1f5f9; border: 1px solid #cbd5e1; padding: 0.1rem 0.4rem; border-radius: 4px; font-weight: 700;">Enter</kbd> pada keyboard untuk memproses hasil scan barcode otomatis.
        </div>

      </div>
    </div>

  </div>

  <!-- HASIL VALIDASI TIKET (LUXURY BOARDING PASS PASS CARD) -->
  <?php if ($tiketData): 
    $isValid = ($tiketData['status_bayar'] === 'lunas' && $tiketData['status_kunjungan'] !== 'sudah_digunakan');
    $isAlreadyUsed = ($tiketData['status_kunjungan'] === 'sudah_digunakan');
    $isUnpaid = ($tiketData['status_bayar'] !== 'lunas');
  ?>
    <div class="card shadow-xl bg-white mb-7" style="border-radius: 1.5rem; overflow: hidden; border: 2.5px solid <?= $isValid ? '#059669' : ($isAlreadyUsed ? '#d97706' : '#dc2626') ?>; box-shadow: 0 15px 35px rgba(0,0,0,0.08);">
      
      <!-- Boarding Pass Header -->
      <div style="background: <?= $isValid ? 'linear-gradient(135deg, #064e3b 0%, #047857 100%)' : ($isAlreadyUsed ? 'linear-gradient(135deg, #78350f 0%, #b45309 100%)' : 'linear-gradient(135deg, #7f1d1d 0%, #dc2626 100%)') ?>; padding: 1.5rem 2rem; color: #ffffff; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 1rem;">
        <div>
          <span style="font-size: 0.75rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.08em; opacity: 0.9; display: block;">
            HASIL PEMERIKSAAN GERBANG LOKET
          </span>
          <h2 style="font-family: monospace; font-size: 1.75rem; font-weight: 900; margin: 0.2rem 0 0 0; letter-spacing: 0.05em;">
            <?= htmlspecialchars($tiketData['kode_booking']) ?>
          </h2>
        </div>

        <div>
          <?php if ($isAlreadyUsed): ?>
            <span class="badge-luxury badge-luxury-warning" style="font-size: 0.88rem; padding: 0.5rem 1.15rem; background: #ffffff; color: #92400e; font-weight: 800;">
              <i class="fa-solid fa-clock-rotate-left"></i> SUDAH PERNAH DIGUNAKAN CHECK-IN
            </span>
          <?php elseif ($isValid): ?>
            <span class="badge-luxury badge-luxury-success" style="font-size: 0.88rem; padding: 0.5rem 1.15rem; background: #ffffff; color: #065f46; font-weight: 800;">
              <i class="fa-solid fa-circle-check"></i> E-TIKET RESMI VALID & LUNAS
            </span>
          <?php else: ?>
            <span class="badge-luxury badge-luxury-danger" style="font-size: 0.88rem; padding: 0.5rem 1.15rem; background: #ffffff; color: #991b1b; font-weight: 800;">
              <i class="fa-solid fa-circle-exclamation"></i> TIKET BELUM LUNAS (<?= strtoupper($tiketData['status_bayar']) ?>)
            </span>
          <?php endif; ?>
        </div>
      </div>

      <!-- Boarding Pass Content -->
      <div style="padding: 2rem;">
        <div class="grid grid-cols-4 gap-6 mb-6">
          
          <!-- Destinasi -->
          <div>
            <span style="font-size: 0.72rem; color: #64748b; font-weight: 800; text-transform: uppercase; letter-spacing: 0.05em; display: block;">
              Destinasi Wisata
            </span>
            <strong style="font-size: 1.15rem; color: #0f172a; display: block; margin-top: 0.25rem;">
              <?= htmlspecialchars($tiketData['nama_destinasi']) ?>
            </strong>
            <span style="font-size: 0.78rem; color: #64748b;">
              <i class="fa-solid fa-location-dot text-primary"></i> <?= htmlspecialchars($tiketData['lokasi']) ?>
            </span>
          </div>

          <!-- Tanggal Kunjungan -->
          <div>
            <span style="font-size: 0.72rem; color: #64748b; font-weight: 800; text-transform: uppercase; letter-spacing: 0.05em; display: block;">
              Jadwal Kunjungan
            </span>
            <strong style="font-size: 1.15rem; color: #0f172a; display: block; margin-top: 0.25rem;">
              <?= formatTanggalIndo($tiketData['tanggal_kunjungan']) ?>
            </strong>
            <span style="font-size: 0.78rem; color: #64748b;">
              Hari <?= getNamaHariIndo($tiketData['tanggal_kunjungan']) ?>
            </span>
          </div>

          <!-- Pemesan -->
          <div>
            <span style="font-size: 0.72rem; color: #64748b; font-weight: 800; text-transform: uppercase; letter-spacing: 0.05em; display: block;">
              Nama Pemesan
            </span>
            <strong style="font-size: 1.1rem; color: #0f172a; display: block; margin-top: 0.25rem;">
              <?= htmlspecialchars($tiketData['nama_pemesan'] ?? $tiketData['nama_user']) ?>
            </strong>
            <span style="font-size: 0.78rem; color: #16a34a; font-weight: 600;">
              <i class="fa-brands fa-whatsapp"></i> <?= htmlspecialchars($tiketData['no_telp'] ?? $tiketData['user_telp'] ?? '-') ?>
            </span>
          </div>

          <!-- Kuota Tiket Masuk -->
          <div>
            <span style="font-size: 0.72rem; color: #64748b; font-weight: 800; text-transform: uppercase; letter-spacing: 0.05em; display: block;">
              Kapasitas Tiket (Masuk)
            </span>
            <strong style="font-size: 1.75rem; color: #0d9488; font-weight: 900; font-family: 'Outfit', sans-serif; display: block; line-height: 1.1; margin-top: 0.25rem;">
              <?= $tiketData['jumlah_tiket'] ?> Orang
            </strong>
            <span class="badge badge-primary text-xs font-bold" style="margin-top: 0.25rem;">
              Tipe <?= ucfirst($tiketData['tipe_rombongan']) ?>
            </span>
          </div>

        </div>

        <!-- Action / Warning Section -->
        <?php if ($isAlreadyUsed): ?>
          <div class="alert alert-warning" style="border-radius: 0.85rem; padding: 1.15rem 1.25rem; margin-bottom: 0; display: flex; align-items: center; gap: 0.75rem;">
            <i class="fa-solid fa-triangle-exclamation" style="font-size: 1.5rem; color: #d97706;"></i>
            <div>
              <strong style="font-size: 0.95rem;">PERINGATAN: E-Tiket Ini Sudah Pernah Digunakan Check-In!</strong>
              <div style="font-size: 0.85rem; margin-top: 0.2rem;">
                Tiket telah digunakan pada <strong><?= $tiketData['waktu_checkin'] ? date('d/m/Y H:i', strtotime($tiketData['waktu_checkin'])) . ' WIB' : '-' ?></strong>. Sesuai prosedur keamanan, 1 E-Tiket hanya berlaku 1 kali scan masuk.
              </div>
            </div>
          </div>
        <?php elseif ($isUnpaid): ?>
          <div class="alert alert-danger" style="border-radius: 0.85rem; padding: 1.15rem 1.25rem; margin-bottom: 0; display: flex; align-items: center; gap: 0.75rem;">
            <i class="fa-solid fa-circle-xmark" style="font-size: 1.5rem; color: #dc2626;"></i>
            <div>
              <strong style="font-size: 0.95rem;">PERHATIAN: Status Pembayaran Masih <?= strtoupper($tiketData['status_bayar']) ?></strong>
              <div style="font-size: 0.85rem; margin-top: 0.2rem;">
                Harap arahkan pengunjung untuk menyelesaikan pelunasan tiket terlebih dahulu di loket kasir sebelum pintu gerbang dapat dibuka.
              </div>
            </div>
          </div>
        <?php else: ?>
          <form action="<?= BASE_URL ?>petugas/validasi_tiket.php?kode=<?= urlencode($tiketData['kode_booking']) ?>" method="POST" id="formCheckinSubmit">
            <input type="hidden" name="pemesanan_id" value="<?= $tiketData['id'] ?>">
            <input type="hidden" name="destinasi_id" value="<?= $tiketData['destinasi_id'] ?>">
            <input type="hidden" name="jumlah_tiket" value="<?= $tiketData['jumlah_tiket'] ?>">
            <input type="hidden" name="tipe_rombongan" value="<?= $tiketData['tipe_rombongan'] ?>">
            <input type="hidden" name="kode_booking_val" value="<?= htmlspecialchars($tiketData['kode_booking']) ?>">

            <button type="submit" name="action_checkin" class="btn btn-primary btn-block btn-lg btn-luxury-pulse" style="background: linear-gradient(135deg, #059669 0%, #047857 100%); border: none; font-size: 1.2rem; font-weight: 800; padding: 1.2rem; border-radius: 0.85rem; box-shadow: 0 10px 25px -5px rgba(5, 150, 105, 0.45); cursor: pointer;">
              <i class="fa-solid fa-door-open"></i> BUKA GERBANG & IZINKAN MASUK (<?= $tiketData['jumlah_tiket'] ?> WISATAWAN)
            </button>
          </form>
        <?php endif; ?>

      </div>

    </div>
  <?php endif; ?>

  <!-- Riwayat Validasi Hari Ini (100% Full Width Table) -->
  <div class="card-table-luxury" style="width: 100%;">
    <div style="padding: 1.25rem 1.5rem; border-bottom: 1px solid #e2e8f0; display: flex; align-items: center; justify-content: space-between; background: #ffffff; flex-wrap: wrap; gap: 0.5rem;">
      <div>
        <h3 style="font-size: 1.05rem; font-weight: 800; color: #0f172a; margin: 0;">
          Riwayat Check-In Gate Hari Ini (Terbaru)
        </h3>
        <p style="font-size: 0.78rem; color: #64748b; margin: 0.15rem 0 0 0;">
          Daftar tiket yang telah berhasil dipindai dan diverifikasi masuk.
        </p>
      </div>
      <span class="badge-luxury badge-luxury-success" style="font-size: 0.75rem;">
        <i class="fa-solid fa-shield-check"></i> Live Gate Check-in
      </span>
    </div>

    <div style="overflow-x: auto; width: 100%;">
      <table class="table-luxury" style="width: 100%;">
        <thead>
          <tr>
            <th style="width: 18%;">Kode Booking</th>
            <th style="width: 25%;">Destinasi Wisata</th>
            <th style="width: 20%;">Nama Pemesan</th>
            <th style="width: 15%; text-align: center;">Jumlah Masuk</th>
            <th style="width: 22%; text-align: right;">Waktu Check-In</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($recentValidated)): ?>
            <tr>
              <td colspan="5" style="text-align: center; padding: 3rem 1rem; color: #94a3b8;">
                <p style="font-size: 0.95rem; font-weight: 600; color: #475569; margin: 0;">Belum ada riwayat tiket yang divalidasi check-in hari ini.</p>
              </td>
            </tr>
          <?php else: ?>
            <?php foreach ($recentValidated as $rv): ?>
              <tr>
                <td>
                  <code style="font-size: 0.85rem; font-weight: 800; background: #f0fdfa; color: #0f766e; padding: 0.2rem 0.5rem; border-radius: 4px; border: 1px solid #ccfbf1;">
                    <?= htmlspecialchars($rv['kode_booking']) ?>
                  </code>
                </td>
                <td><strong class="text-dark"><?= htmlspecialchars($rv['nama_destinasi']) ?></strong></td>
                <td><?= htmlspecialchars($rv['nama_pemesan'] ?? $rv['nama_user']) ?></td>
                <td style="text-align: center;">
                  <div style="display: inline-block; background: #ccfbf1; padding: 0.2rem 0.6rem; border-radius: 9999px;">
                    <strong style="color: #0f766e; font-size: 0.88rem;"><?= $rv['jumlah_tiket'] ?> Org</strong>
                  </div>
                </td>
                <td style="text-align: right;">
                  <span style="font-size: 0.82rem; color: #16a34a; font-weight: 700;">
                    <i class="fa-solid fa-circle-check"></i> <?= $rv['waktu_checkin'] ? date('H:i:s', strtotime($rv['waktu_checkin'])) . ' WIB' : '-' ?>
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

<style>
@keyframes scanLaser {
  0% { top: 10%; }
  100% { top: 90%; }
}
</style>

<script>
// Web Audio API Synthesizer Beep Sound
function playScanBeep(success = true) {
  try {
    const ctx = new (window.AudioContext || window.webkitAudioContext)();
    const osc = ctx.createOscillator();
    const gain = ctx.createGain();
    osc.connect(gain);
    gain.connect(ctx.destination);
    
    if (success) {
      osc.frequency.setValueAtTime(880, ctx.currentTime); // High pitch A5
      gain.gain.setValueAtTime(0.3, ctx.currentTime);
      gain.gain.exponentialRampToValueAtTime(0.01, ctx.currentTime + 0.15);
      osc.start();
      osc.stop(ctx.currentTime + 0.15);
    } else {
      osc.frequency.setValueAtTime(220, ctx.currentTime); // Low pitch
      gain.gain.setValueAtTime(0.3, ctx.currentTime);
      gain.gain.exponentialRampToValueAtTime(0.01, ctx.currentTime + 0.3);
      osc.start();
      osc.stop(ctx.currentTime + 0.3);
    }
  } catch(e) {}
}

let html5QrCode = null;
let isScanning = false;

function toggleCameraScanner() {
  const container = document.getElementById('qrReaderContainer');
  const idleView = document.getElementById('qrIdleViewfinder');
  const helpText = document.getElementById('qrHelpText');
  const btnText = document.getElementById('cameraBtnText');
  const icon = document.getElementById('cameraIcon');
  const badge = document.getElementById('scannerStatusBadge');

  if (isScanning) {
    // Stop camera
    if (html5QrCode) {
      html5QrCode.stop().then(() => {
        container.style.display = 'none';
        idleView.style.display = 'flex';
        btnText.innerText = 'Nyalakan Kamera Scanner';
        icon.className = 'fa-solid fa-video';
        helpText.innerText = 'Kamera dinonaktifkan.';
        badge.innerHTML = '<span style="width:6px;height:6px;border-radius:50%;background:#94a3b8;display:inline-block;"></span> Nonaktif';
        isScanning = false;
      }).catch(err => {
        console.error("Failed to stop scanner", err);
      });
    }
  } else {
    // Start camera
    container.style.display = 'block';
    idleView.style.display = 'none';
    helpText.innerText = 'Arahkan QR Code ke dalam kotak kamera...';
    btnText.innerText = 'Matikan Kamera';
    icon.className = 'fa-solid fa-video-slash';
    badge.innerHTML = '<span style="width:6px;height:6px;border-radius:50%;background:#22c55e;display:inline-block;animation:pulse 1s infinite;"></span> Scanning...';

    if (!html5QrCode) {
      html5QrCode = new Html5Qrcode("qrReaderContainer");
    }

    const config = { fps: 10, qrbox: { width: 220, height: 220 } };

    html5QrCode.start(
      { facingMode: "environment" }, 
      config, 
      (decodedText, decodedResult) => {
        playScanBeep(true);
        // Clean and redirect
        let cleaned = decodedText.trim();
        window.location.href = "<?= BASE_URL ?>petugas/validasi_tiket.php?kode=" + encodeURIComponent(cleaned);
      }, 
      (errorMessage) => {
        // scan error / waiting for code, ignore
      }
    ).then(() => {
      isScanning = true;
    }).catch(err => {
      alert("Tidak dapat mengakses kamera perangkat: " + err);
      container.style.display = 'none';
      idleView.style.display = 'flex';
      btnText.innerText = 'Nyalakan Kamera Scanner';
      icon.className = 'fa-solid fa-video';
      isScanning = false;
    });
  }
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
