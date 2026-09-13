<?php
require_once __DIR__ . '/../config/database.php';
checkAuth('petugas');

$searchKode = strtoupper(trim($_GET['kode'] ?? ''));
$tiketData = null;
$msg = '';
$msgType = '';
$petugasId = $_SESSION['user_id'];
$today = date('Y-m-d');

// Handle Check-in Action (Konfirmasi Validasi Masuk)
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

    setFlash('success', '✅ <strong>VALIDASI BERHASIL!</strong> E-Ticket <strong>' . htmlspecialchars($kodeBookingVal) . '</strong> telah diverifikasi. Rombongan sebanyak <strong>' . $jumlahOrang . ' orang</strong> dipersilakan masuk gerbang wisata.');
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
          <i class="fa-solid fa-shield-halved"></i> Gate Verification System
        </span>
        <span style="font-size: 0.8rem; color: #64748b;">
          Hari Ini: <strong style="color: #059669;"><?= $todayCountValidated ?> Tiket</strong> (<?= number_format($todayTotalVisitorsCheckedIn) ?> Wisatawan Masuk)
        </span>
      </div>
      <h1 style="font-size: 1.45rem; font-weight: 800; color: #0f172a; margin: 0; letter-spacing: -0.02em;">
        Scan & Validasi E-Tiket Gerbang
      </h1>
      <p style="font-size: 0.82rem; color: #64748b; margin: 0.15rem 0 0 0;">
        Pindai QR Barcode smartphone wisatawan via kamera atau masukkan kode booking untuk membuka akses gerbang.
      </p>
    </div>

    <div style="display: flex; gap: 0.5rem;">
      <a href="<?= BASE_URL ?>petugas/validasi_tiket.php" class="btn btn-secondary btn-sm" style="background: #ffffff; border: 1px solid #cbd5e1; font-weight: 700; border-radius: 0.65rem;" title="Reset / Scan Baru">
        <i class="fa-solid fa-rotate-left"></i> Reset / Scan Baru
      </a>
    </div>
  </div>

  <!-- Flash Notification -->
  <?php if ($flash): ?>
    <div class="alert alert-<?= $flash['type'] ?> mb-4" style="border-radius: 0.85rem; padding: 1rem 1.25rem; display: flex; align-items: center; gap: 0.75rem; box-shadow: 0 4px 12px rgba(0,0,0,0.04);">
      <i class="fa-solid <?= $flash['type'] === 'success' ? 'fa-circle-check' : 'fa-circle-exclamation' ?>" style="font-size: 1.4rem;"></i>
      <div style="font-size: 0.9rem; line-height: 1.4;"><?= $flash['message'] ?></div>
    </div>
  <?php endif; ?>

  <?php if ($msg): ?>
    <div class="alert alert-<?= $msgType ?> mb-4" style="border-radius: 0.85rem; padding: 1rem 1.25rem; display: flex; align-items: center; gap: 0.75rem; box-shadow: 0 4px 12px rgba(0,0,0,0.04);">
      <i class="fa-solid <?= $msgType === 'success' ? 'fa-circle-check' : 'fa-circle-exclamation' ?>" style="font-size: 1.4rem;"></i>
      <div style="font-size: 0.9rem; line-height: 1.4;"><?= $msg ?></div>
    </div>
  <?php endif; ?>

  <!-- Grid Scanner & Input Barcode -->
  <div class="grid grid-cols-12 gap-5 mb-5">
    
    <!-- Left: Real Web Camera Scanner UI Card (6 cols) -->
    <div class="col-span-6" style="grid-column: span 6 / span 6;">
      <div class="card p-5 bg-white shadow-sm" style="border-radius: 1.15rem; border: 1.5px solid #ccfbf1; height: 100%; display: flex; flex-direction: column; justify-content: space-between;">
        
        <div>
          <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 1rem;">
            <h3 style="font-size: 1.1rem; font-weight: 800; color: #0f172a; margin: 0; display: flex; align-items: center; gap: 0.5rem;">
              <i class="fa-solid fa-camera text-teal-600"></i> Scanner Kamera QR Optik
            </h3>
            <span class="badge-luxury badge-luxury-success" id="scannerStatusBadge">
              <span style="width:6px;height:6px;border-radius:50%;background:#22c55e;display:inline-block;"></span> Siap Pindai
            </span>
          </div>

          <!-- Real Camera Video Box / Scanner Frame -->
          <div style="background: #090d16; border-radius: 1rem; padding: 1.25rem; text-align: center; color: white; position: relative; overflow: hidden; margin-bottom: 1rem; min-height: 240px; display: flex; flex-direction: column; align-items: center; justify-content: center; border: 1px solid rgba(255,255,255,0.1);">
            
            <div id="qrReaderContainer" style="width: 100%; max-width: 320px; border-radius: 0.75rem; overflow: hidden; display: none;"></div>

            <div id="qrIdleViewfinder" style="width: 150px; height: 150px; border: 2px dashed rgba(45, 212, 191, 0.75); border-radius: 1.25rem; display: flex; align-items: center; justify-content: center; position: relative;">
              <i class="fa-solid fa-qrcode" style="font-size: 4.5rem; color: rgba(255, 255, 255, 0.3);"></i>
              <!-- Scanning Laser Line -->
              <div style="position: absolute; left: 0; right: 0; height: 3px; background: linear-gradient(90deg, transparent, #2dd4bf, transparent); box-shadow: 0 0 12px #2dd4bf; animation: scanLaser 2s infinite alternate;"></div>
            </div>

            <p id="qrHelpText" style="font-size: 0.8rem; color: #94a3b8; margin: 1rem 0 0 0;">
              Klik tombol di bawah untuk mengaktifkan kamera scanner perangkat Anda.
            </p>
          </div>

          <!-- Camera Controls -->
          <div style="display: flex; gap: 0.5rem; justify-content: center; margin-bottom: 0.75rem; flex-wrap: wrap;">
            <button type="button" id="btnToggleCamera" class="btn btn-primary btn-sm btn-luxury-pulse" onclick="toggleCameraScanner()" style="font-weight: 800; border-radius: 0.65rem; padding: 0.55rem 1.15rem;">
              <i class="fa-solid fa-video" id="cameraIcon"></i> <span id="cameraBtnText">Nyalakan Kamera Scanner</span>
            </button>
          </div>
        </div>

        <!-- Quick Test Code Shortcuts -->
        <div style="padding-top: 0.75rem; border-top: 1px solid #f1f5f9; display: flex; align-items: center; gap: 0.4rem; flex-wrap: wrap; font-size: 0.75rem;">
          <span style="color: #64748b; font-weight: 700;">Contoh Kode Demo:</span>
          <a href="<?= BASE_URL ?>petugas/validasi_tiket.php?kode=BK-20260901-001" class="btn btn-secondary btn-xs" style="padding: 0.2rem 0.5rem; border-radius: 0.4rem; font-family: monospace; font-weight: 700;">
            BK-20260901-001
          </a>
          <a href="<?= BASE_URL ?>petugas/validasi_tiket.php?kode=BK-20260902-002" class="btn btn-secondary btn-xs" style="padding: 0.2rem 0.5rem; border-radius: 0.4rem; font-family: monospace; font-weight: 700;">
            BK-20260902-002
          </a>
        </div>

      </div>
    </div>

    <!-- Right: Manual Search & Barcode Lookup (6 cols) -->
    <div class="col-span-6" style="grid-column: span 6 / span 6;">
      <div class="card p-5 bg-white shadow-sm" style="border-radius: 1.15rem; border: 1.5px solid #e2e8f0; height: 100%; display: flex; flex-direction: column; justify-content: space-between;">
        
        <div>
          <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 1rem;">
            <h3 style="font-size: 1.1rem; font-weight: 800; color: #0f172a; margin: 0; display: flex; align-items: center; gap: 0.5rem;">
              <i class="fa-solid fa-barcode text-teal-600"></i> Input Barcode / Kode Booking
            </h3>
            <span class="badge badge-light text-xs font-bold" style="background: #f1f5f9; padding: 0.2rem 0.5rem; border-radius: 4px;">Manual / Gun Scanner</span>
          </div>

          <p style="font-size: 0.82rem; color: #64748b; line-height: 1.5; margin-bottom: 1.25rem;">
            Gunakan barcode scanner handheld USB/Bluetooth atau ketik kode pemesanan secara manual di bawah ini.
          </p>

          <form action="<?= BASE_URL ?>petugas/validasi_tiket.php" method="GET" id="barcodeForm">
            <div class="form-group mb-4">
              <label style="display: block; font-size: 0.75rem; font-weight: 800; color: #334155; margin-bottom: 0.4rem; text-transform: uppercase; letter-spacing: 0.05em;">
                Nomor Kode Booking / E-Tiket <span style="color: #ef4444;">*</span>
              </label>
              <div style="position: relative;">
                <i class="fa-solid fa-keyboard" style="position: absolute; left: 1rem; top: 50%; transform: translateY(-50%); color: #0d9488; font-size: 1.15rem;"></i>
                <input type="text" name="kode" id="kodeInput" class="form-control" style="padding-left: 2.85rem; font-family: monospace; font-size: 1.15rem; font-weight: 800; text-transform: uppercase; border-radius: 0.75rem; border: 2px solid #cbd5e1; height: 48px;" placeholder="Cth: BK-20260901-001" value="<?= htmlspecialchars($searchKode) ?>" required autofocus autocomplete="off">
              </div>
            </div>

            <button type="submit" class="btn btn-primary btn-block btn-lg btn-luxury-pulse" style="font-weight: 800; border-radius: 0.75rem; padding: 0.85rem; box-shadow: 0 4px 14px rgba(13, 148, 136, 0.35); width: 100%; font-size: 1rem;">
              <i class="fa-solid fa-magnifying-glass"></i> Periksa Keabsahan Tiket Sekarang
            </button>
          </form>
        </div>

        <div style="padding-top: 0.75rem; border-top: 1px solid #f1f5f9; margin-top: 1rem; font-size: 0.78rem; color: #64748b; display: flex; align-items: center; gap: 0.4rem;">
          <i class="fa-solid fa-circle-info text-teal-600"></i> Tekan <kbd style="background: #f1f5f9; border: 1px solid #cbd5e1; padding: 0.1rem 0.4rem; border-radius: 4px; font-weight: 700;">Enter</kbd> untuk memproses barcode scanner otomatis.
        </div>

      </div>
    </div>

  </div>

  <!-- HASIL VALIDASI TIKET (LUXURY BOARDING PASS CARD) -->
  <?php if ($tiketData): 
    $isValid = ($tiketData['status_bayar'] === 'lunas' && $tiketData['status_kunjungan'] !== 'sudah_digunakan');
    $isAlreadyUsed = ($tiketData['status_kunjungan'] === 'sudah_digunakan');
    $isUnpaid = ($tiketData['status_bayar'] !== 'lunas');
  ?>
    <div class="ticket-pass mb-5" style="border-color: <?= $isValid ? '#059669' : ($isAlreadyUsed ? '#d97706' : '#dc2626') ?>;">
      
      <!-- Boarding Pass Header -->
      <div style="background: <?= $isValid ? 'linear-gradient(135deg, #064e3b 0%, #047857 100%)' : ($isAlreadyUsed ? 'linear-gradient(135deg, #78350f 0%, #b45309 100%)' : 'linear-gradient(135deg, #7f1d1d 0%, #dc2626 100%)') ?>; padding: 1.25rem 1.75rem; color: #ffffff; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 1rem;">
        <div>
          <span style="font-size: 0.72rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.08em; opacity: 0.9; display: block;">
            HASIL PEMERIKSAAN GERBANG LOKET
          </span>
          <h2 style="font-family: monospace; font-size: 1.65rem; font-weight: 900; margin: 0.15rem 0 0 0; letter-spacing: 0.05em;">
            <?= htmlspecialchars($tiketData['kode_booking']) ?>
          </h2>
        </div>

        <div>
          <?php if ($isAlreadyUsed): ?>
            <span class="badge-luxury badge-luxury-warning" style="font-size: 0.85rem; padding: 0.45rem 1rem; background: #ffffff; color: #92400e; font-weight: 800;">
              <i class="fa-solid fa-clock-rotate-left"></i> SUDAH PERNAH DIGUNAKAN
            </span>
          <?php elseif ($isValid): ?>
            <span class="badge-luxury badge-luxury-success" style="font-size: 0.85rem; padding: 0.45rem 1rem; background: #ffffff; color: #065f46; font-weight: 800;">
              <i class="fa-solid fa-circle-check"></i> E-TIKET RESMI VALID & LUNAS
            </span>
          <?php else: ?>
            <span class="badge-luxury badge-luxury-danger" style="font-size: 0.85rem; padding: 0.45rem 1rem; background: #ffffff; color: #991b1b; font-weight: 800;">
              <i class="fa-solid fa-circle-exclamation"></i> TIKET BELUM LUNAS (<?= strtoupper($tiketData['status_bayar']) ?>)
            </span>
          <?php endif; ?>
        </div>
      </div>

      <!-- Boarding Pass Content -->
      <div style="padding: 1.75rem;">
        <div class="grid grid-cols-4 gap-5 mb-5">
          
          <!-- Destinasi -->
          <div>
            <span style="font-size: 0.7rem; color: #64748b; font-weight: 800; text-transform: uppercase; letter-spacing: 0.05em; display: block;">
              Destinasi Wisata
            </span>
            <strong style="font-size: 1.1rem; color: #0f172a; display: block; margin-top: 0.2rem;">
              <?= htmlspecialchars($tiketData['nama_destinasi']) ?>
            </strong>
            <span style="font-size: 0.75rem; color: #64748b;">
              <i class="fa-solid fa-location-dot text-teal-600"></i> <?= htmlspecialchars($tiketData['lokasi']) ?>
            </span>
          </div>

          <!-- Tanggal Kunjungan -->
          <div>
            <span style="font-size: 0.7rem; color: #64748b; font-weight: 800; text-transform: uppercase; letter-spacing: 0.05em; display: block;">
              Jadwal Kunjungan
            </span>
            <strong style="font-size: 1.1rem; color: #0f172a; display: block; margin-top: 0.2rem;">
              <?= formatTanggalIndo($tiketData['tanggal_kunjungan']) ?>
            </strong>
            <span style="font-size: 0.75rem; color: #64748b;">
              Hari <?= getNamaHariIndo($tiketData['tanggal_kunjungan']) ?>
            </span>
          </div>

          <!-- Pemesan -->
          <div>
            <span style="font-size: 0.7rem; color: #64748b; font-weight: 800; text-transform: uppercase; letter-spacing: 0.05em; display: block;">
              Nama Pemesan
            </span>
            <strong style="font-size: 1.05rem; color: #0f172a; display: block; margin-top: 0.2rem;">
              <?= htmlspecialchars($tiketData['nama_pemesan'] ?? $tiketData['nama_user']) ?>
            </strong>
            <span style="font-size: 0.75rem; color: #16a34a; font-weight: 600;">
              <i class="fa-brands fa-whatsapp"></i> <?= htmlspecialchars($tiketData['no_telp'] ?? $tiketData['user_telp'] ?? '-') ?>
            </span>
          </div>

          <!-- Kuota Tiket Masuk -->
          <div>
            <span style="font-size: 0.7rem; color: #64748b; font-weight: 800; text-transform: uppercase; letter-spacing: 0.05em; display: block;">
              Kapasitas Tiket (Masuk)
            </span>
            <strong style="font-size: 1.65rem; color: #0d9488; font-weight: 900; font-family: 'Outfit', sans-serif; display: block; line-height: 1.1; margin-top: 0.2rem;">
              <?= $tiketData['jumlah_tiket'] ?> Orang
            </strong>
            <span class="badge badge-primary text-xs font-bold" style="margin-top: 0.2rem; background: #ccfbf1; color: #0f766e; padding: 0.15rem 0.5rem; border-radius: 4px; display: inline-block;">
              Tipe <?= ucfirst($tiketData['tipe_rombongan']) ?>
            </span>
          </div>

        </div>

        <!-- Action / Warning Section -->
        <?php if ($isAlreadyUsed): ?>
          <div class="alert alert-warning" style="border-radius: 0.85rem; padding: 1.15rem 1.25rem; margin-bottom: 0; display: flex; align-items: center; gap: 0.75rem; background: #fef3c7; border: 1px solid #fde68a;">
            <i class="fa-solid fa-triangle-exclamation" style="font-size: 1.5rem; color: #d97706;"></i>
            <div>
              <strong style="font-size: 0.95rem; color: #92400e;">PERINGATAN: E-Tiket Ini Sudah Pernah Digunakan Check-In!</strong>
              <div style="font-size: 0.82rem; margin-top: 0.2rem; color: #78350f;">
                Tiket telah divalidasi masuk pada <strong><?= $tiketData['waktu_checkin'] ? date('d/m/Y H:i', strtotime($tiketData['waktu_checkin'])) . ' WIB' : '-' ?></strong>. Sesuai protokol gerbang, 1 E-Tiket hanya berlaku 1 kali scan masuk.
              </div>
            </div>
          </div>
        <?php elseif ($isUnpaid): ?>
          <div class="alert alert-danger" style="border-radius: 0.85rem; padding: 1.15rem 1.25rem; margin-bottom: 0; display: flex; align-items: center; gap: 0.75rem; background: #fee2e2; border: 1px solid #fecaca;">
            <i class="fa-solid fa-circle-xmark" style="font-size: 1.5rem; color: #dc2626;"></i>
            <div>
              <strong style="font-size: 0.95rem; color: #991b1b;">PERHATIAN: Status Pembayaran Masih <?= strtoupper($tiketData['status_bayar']) ?></strong>
              <div style="font-size: 0.82rem; margin-top: 0.2rem; color: #7f1d1d;">
                Arahkan pengunjung untuk menyelesaikan pelunasan tiket di loket kasir terlebih dahulu sebelum gerbang dibuka.
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

            <button type="submit" name="action_checkin" class="btn btn-primary btn-block btn-lg btn-luxury-pulse" style="background: linear-gradient(135deg, #059669 0%, #047857 100%); border: none; font-size: 1.15rem; font-weight: 800; padding: 1.1rem; border-radius: 0.85rem; box-shadow: 0 10px 25px -5px rgba(5, 150, 105, 0.45); cursor: pointer; width: 100%;">
              <i class="fa-solid fa-door-open"></i> BUKA GERBANG & IZINKAN MASUK (<?= $tiketData['jumlah_tiket'] ?> WISATAWAN)
            </button>
          </form>
        <?php endif; ?>

      </div>

    </div>
  <?php endif; ?>

  <!-- Riwayat Validasi Hari Ini (100% Full Width Table) -->
  <div class="card-table-luxury">
    <div style="padding: 1.15rem 1.5rem; border-bottom: 1px solid #e2e8f0; display: flex; align-items: center; justify-content: space-between; background: #ffffff; flex-wrap: wrap; gap: 0.5rem;">
      <div>
        <h3 style="font-size: 1.05rem; font-weight: 800; color: #0f172a; margin: 0; display: flex; align-items: center; gap: 0.4rem;">
          <i class="fa-solid fa-clock-rotate-left text-teal-600"></i> Riwayat Check-In Gate Hari Ini (Terbaru)
        </h3>
        <p style="font-size: 0.78rem; color: #64748b; margin: 0.15rem 0 0 0;">
          Daftar tiket yang telah berhasil dipindai dan diverifikasi masuk gerbang wisata.
        </p>
      </div>
      <span class="badge-luxury badge-luxury-success" style="font-size: 0.72rem;">
        <span style="width:5px;height:5px;border-radius:50%;background:#22c55e;display:inline-block;"></span> Live Verified
      </span>
    </div>

    <div style="overflow-x: auto; width: 100%;">
      <table class="table-luxury" style="width: 100%;">
        <thead>
          <tr>
            <th style="width: 15%;">Waktu Check-In</th>
            <th style="width: 18%;">Kode Booking</th>
            <th style="width: 25%;">Nama Pemesan</th>
            <th style="width: 24%;">Destinasi Objek Wisata</th>
            <th style="width: 10%; text-align: center;">Jumlah Masuk</th>
            <th style="width: 8%; text-align: right;">Status</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($recentValidated)): ?>
            <tr>
              <td colspan="6" style="text-align: center; padding: 2.5rem 1rem; color: #94a3b8;">
                <p style="font-size: 0.88rem; font-weight: 600; color: #64748b; margin: 0;">Belum ada e-tiket yang divalidasi hari ini.</p>
              </td>
            </tr>
          <?php else: ?>
            <?php foreach ($recentValidated as $rv): ?>
              <tr>
                <td>
                  <div style="font-size: 0.82rem; font-weight: 700; color: #0f172a;">
                    <?= $rv['waktu_checkin'] ? date('H:i:s', strtotime($rv['waktu_checkin'])) : '-' ?> WIB
                  </div>
                  <span style="font-size: 0.7rem; color: #94a3b8;">
                    <?= $rv['waktu_checkin'] ? date('d/m/Y', strtotime($rv['waktu_checkin'])) : '-' ?>
                  </span>
                </td>
                <td>
                  <code style="font-size: 0.82rem; font-weight: 800; color: #0f766e; background: #ccfbf1; padding: 0.15rem 0.45rem; border-radius: 4px; font-family: monospace;">
                    <?= htmlspecialchars($rv['kode_booking']) ?>
                  </code>
                </td>
                <td>
                  <strong style="color: #0f172a; font-size: 0.88rem;"><?= htmlspecialchars($rv['nama_pemesan'] ?? $rv['nama_user']) ?></strong>
                </td>
                <td>
                  <span style="color: #334155; font-size: 0.85rem; font-weight: 600;"><?= htmlspecialchars($rv['nama_destinasi']) ?></span>
                </td>
                <td style="text-align: center;">
                  <span style="background: #e0f2fe; color: #0284c7; font-weight: 800; font-size: 0.85rem; padding: 0.2rem 0.6rem; border-radius: 9999px;">
                    <?= $rv['jumlah_tiket'] ?> Org
                  </span>
                </td>
                <td style="text-align: right;">
                  <span class="badge-luxury badge-luxury-success" style="font-size: 0.7rem;">
                    <i class="fa-solid fa-circle-check"></i> Masuk
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
// Web Audio API Synthesizer for Audio Feedback
function playAudioBeep(type) {
  try {
    const ctx = new (window.AudioContext || window.webkitAudioContext)();
    if (type === 'success') {
      // High-tone double chime
      const osc1 = ctx.createOscillator();
      const osc2 = ctx.createOscillator();
      const gain = ctx.createGain();
      
      osc1.frequency.setValueAtTime(880, ctx.currentTime); // A5
      osc2.frequency.setValueAtTime(1320, ctx.currentTime + 0.1); // E6
      
      gain.gain.setValueAtTime(0.2, ctx.currentTime);
      gain.gain.exponentialRampToValueAtTime(0.01, ctx.currentTime + 0.3);
      
      osc1.connect(gain);
      osc2.connect(gain);
      gain.connect(ctx.destination);
      
      osc1.start(ctx.currentTime);
      osc1.stop(ctx.currentTime + 0.1);
      osc2.start(ctx.currentTime + 0.1);
      osc2.stop(ctx.currentTime + 0.3);
    } else if (type === 'error') {
      // Low buzz error
      const osc = ctx.createOscillator();
      const gain = ctx.createGain();
      osc.type = 'sawtooth';
      osc.frequency.setValueAtTime(220, ctx.currentTime);
      gain.gain.setValueAtTime(0.3, ctx.currentTime);
      gain.gain.exponentialRampToValueAtTime(0.01, ctx.currentTime + 0.4);
      osc.connect(gain);
      gain.connect(ctx.destination);
      osc.start(ctx.currentTime);
      osc.stop(ctx.currentTime + 0.4);
    }
  } catch (e) {
    console.log('Audio feedback not supported:', e);
  }
}

// Auto play audio feedback if result present
<?php if ($tiketData): ?>
  <?php if ($isValid): ?>
    playAudioBeep('success');
  <?php else: ?>
    playAudioBeep('error');
  <?php endif; ?>
<?php endif; ?>

// HTML5 QR Scanner Logic
let html5QrCode = null;
let isScannerRunning = false;

function toggleCameraScanner() {
  const container = document.getElementById('qrReaderContainer');
  const idleView = document.getElementById('qrIdleViewfinder');
  const helpText = document.getElementById('qrHelpText');
  const btnText = document.getElementById('cameraBtnText');
  const btnIcon = document.getElementById('cameraIcon');
  const statusBadge = document.getElementById('scannerStatusBadge');

  if (isScannerRunning) {
    // Matikan Kamera
    if (html5QrCode) {
      html5QrCode.stop().then(() => {
        html5QrCode.clear();
        isScannerRunning = false;
        container.style.display = 'none';
        idleView.style.display = 'flex';
        helpText.innerText = 'Klik tombol di bawah untuk mengaktifkan kamera scanner.';
        btnText.innerText = 'Nyalakan Kamera Scanner';
        btnIcon.className = 'fa-solid fa-video';
        statusBadge.className = 'badge-luxury badge-luxury-success';
        statusBadge.innerHTML = '<span style="width:6px;height:6px;border-radius:50%;background:#22c55e;display:inline-block;"></span> Siap Pindai';
      }).catch(err => {
        console.error("Gagal mematikan kamera:", err);
      });
    }
  } else {
    // Hidupkan Kamera
    container.style.display = 'block';
    idleView.style.display = 'none';
    helpText.innerText = 'Arahkan lensa kamera ke QR Code Tiket Wisatawan...';
    btnText.innerText = 'Matikan Kamera';
    btnIcon.className = 'fa-solid fa-video-slash';
    statusBadge.className = 'badge-luxury badge-luxury-primary';
    statusBadge.innerHTML = '<span style="width:6px;height:6px;border-radius:50%;background:#0d9488;display:inline-block;animation:pulse 1s infinite;"></span> Kamera Aktif';

    html5QrCode = new Html5Qrcode("qrReaderContainer");
    const config = { fps: 10, qrbox: { width: 250, height: 250 } };

    html5QrCode.start(
      { facingMode: "environment" },
      config,
      (decodedText, decodedResult) => {
        // QR Code berhasil terbaca
        playAudioBeep('success');
        html5QrCode.stop().then(() => {
          window.location.href = "<?= BASE_URL ?>petugas/validasi_tiket.php?kode=" + encodeURIComponent(decodedText);
        });
      },
      (errorMessage) => {
        // Parsing error diabaikan
      }
    ).then(() => {
      isScannerRunning = true;
    }).catch(err => {
      console.error("Error mengakses kamera:", err);
      helpText.innerText = 'Gagal mengakses kamera. Pastikan izin kamera telah diberikan di browser.';
      isScannerRunning = false;
    });
  }
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
