<?php
require_once __DIR__ . '/config/database.php';
checkAuth();

$kode = trim($_GET['kode'] ?? '');
if (empty($kode)) {
    header("Location: " . BASE_URL . "riwayat.php");
    exit;
}

$stmt = $pdo->prepare("SELECT p.*, d.nama_destinasi, d.lokasi, d.jam_buka, d.jam_tutup, u.nama as nama_user, u.email as email_user 
                       FROM pemesanan p 
                       JOIN destinasi d ON p.destinasi_id = d.id 
                       JOIN users u ON p.user_id = u.id 
                       WHERE p.kode_booking = ? LIMIT 1");
$stmt->execute([$kode]);
$tiket = $stmt->fetch();

if (!$tiket) {
    header("Location: " . BASE_URL . "riwayat.php");
    exit;
}

// Hak akses
if ($_SESSION['user_role'] === 'pengunjung' && $tiket['user_id'] != $_SESSION['user_id']) {
    header("Location: " . BASE_URL . "riwayat.php");
    exit;
}

$qrUrl = "https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=" . urlencode($tiket['kode_booking']);

$pageTitle = "E-Ticket: " . $tiket['kode_booking'];
require_once __DIR__ . '/includes/header.php';
if (!isset($_GET['print'])) {
    require_once __DIR__ . '/includes/navbar.php';
}
?>

<div class="container" style="padding-top: 2rem; padding-bottom: 4rem;">
  
  <!-- Print and Back Action Bar -->
  <div class="flex items-center justify-between mb-6 no-print">
    <a href="<?= BASE_URL ?>riwayat.php" class="btn btn-secondary btn-sm">
      <i class="fa-solid fa-arrow-left"></i> Kembali ke Riwayat
    </a>
    <button onclick="window.print()" class="btn btn-primary btn-sm">
      <i class="fa-solid fa-print"></i> Cetak E-Ticket / PDF
    </button>
  </div>

  <!-- E-Ticket Card Layout -->
  <div class="e-ticket-wrapper max-w-3xl mx-auto bg-white shadow-xl rounded-xl overflow-hidden" style="border: 2px solid var(--slate-200);">
    
    <!-- Ticket Header -->
    <div class="ticket-header p-6 bg-gradient text-white flex items-center justify-between" style="background: linear-gradient(135deg, #0d9488, #0369a1);">
      <div class="flex items-center gap-3">
        <div style="background: rgba(255,255,255,0.2); width: 44px; height: 44px; border-radius: 10px; display: flex; items-center; justify-content: center; font-size: 1.25rem;">
          <i class="fa-solid fa-ticket"></i>
        </div>
        <div>
          <h2 style="margin: 0; font-size: 1.35rem; font-weight: 800; color: #fff;">OFFICIAL E-TICKET</h2>
          <span style="font-size: 0.8rem; opacity: 0.9;"><?= htmlspecialchars($settings['nama_sistem']) ?></span>
        </div>
      </div>
      <div class="text-right">
        <div style="font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.05em; opacity: 0.85;">Kode Booking</div>
        <div style="font-family: monospace; font-size: 1.2rem; font-weight: 800; letter-spacing: 1px;"><?= htmlspecialchars($tiket['kode_booking']) ?></div>
      </div>
    </div>

    <!-- Ticket Body -->
    <div class="p-8">
      <div class="grid grid-cols-3 gap-6 items-center">
        
        <!-- Info Destinasi & Kunjungan (Span 2) -->
        <div style="grid-column: span 2;">
          <div class="mb-4">
            <span class="badge badge-primary text-xs uppercase"><?= ucfirst($tiket['tipe_rombongan']) ?> Pass</span>
            <h1 class="text-2xl font-black text-dark mt-1" style="margin-bottom: 0.25rem;"><?= htmlspecialchars($tiket['nama_destinasi']) ?></h1>
            <p class="text-sm text-muted"><i class="fa-solid fa-location-dot text-primary"></i> <?= htmlspecialchars($tiket['lokasi']) ?></p>
          </div>

          <div class="grid grid-cols-2 gap-4 py-4 my-2 border-t border-b border-slate-200">
            <div>
              <span class="text-xs font-bold text-muted uppercase block">Tanggal Kunjungan</span>
              <strong class="text-base text-dark"><?= formatTanggalIndo($tiket['tanggal_kunjungan']) ?></strong>
              <div class="text-xs text-muted"><?= getNamaHariIndo($tiket['tanggal_kunjungan']) ?></div>
            </div>
            <div>
              <span class="text-xs font-bold text-muted uppercase block">Jumlah Pengunjung</span>
              <strong class="text-base text-dark"><?= $tiket['jumlah_tiket'] ?> Orang</strong>
              <div class="text-xs text-muted">Tiket Masuk Terpadu</div>
            </div>
            <div>
              <span class="text-xs font-bold text-muted uppercase block">Nama Pemesan</span>
              <strong class="text-sm text-dark"><?= htmlspecialchars($tiket['nama_pemesan'] ?? $tiket['nama_user']) ?></strong>
              <div class="text-xs text-muted"><?= htmlspecialchars($tiket['no_telp'] ?? '-') ?></div>
            </div>
            <div>
              <span class="text-xs font-bold text-muted uppercase block">Status Pembayaran</span>
              <?php if ($tiket['status_bayar'] === 'lunas'): ?>
                <span class="badge badge-success"><i class="fa-solid fa-check"></i> LUNAS</span>
              <?php elseif ($tiket['status_bayar'] === 'pending'): ?>
                <span class="badge badge-warning">BELUM DIBAYAR</span>
              <?php else: ?>
                <span class="badge badge-danger"><?= strtoupper($tiket['status_bayar']) ?></span>
              <?php endif; ?>
            </div>
          </div>

          <div class="flex items-center justify-between text-xs text-muted mt-3">
            <span>Metode: <?= strtoupper(str_replace('_', ' ', $tiket['metode_pembayaran'] ?? 'QRIS')) ?></span>
            <span>Jam Operasional: <?= htmlspecialchars($tiket['jam_buka']) ?> - <?= htmlspecialchars($tiket['jam_tutup']) ?></span>
          </div>
        </div>

        <!-- QR Code Container (Span 1) -->
        <div class="text-center p-4 bg-slate-50 rounded-xl border flex flex-col items-center justify-center">
          <img src="<?= $qrUrl ?>" alt="QR Code <?= $tiket['kode_booking'] ?>" style="width: 140px; height: 140px; border-radius: 8px; border: 1px solid var(--slate-300); margin-bottom: 0.75rem;">
          <span class="text-xs font-bold text-dark tracking-wider block font-mono"><?= htmlspecialchars($tiket['kode_booking']) ?></span>
          <span class="text-xs text-muted mt-1 block">Tunjukkan QR ini ke Petugas di Pintu Masuk</span>
        </div>

      </div>
    </div>

    <!-- Ticket Footer / Instructions -->
    <div class="p-6 bg-slate-100 border-t border-slate-200 text-xs text-slate-600">
      <h4 class="font-bold text-dark mb-1">Syarat & Ketentuan Kunjungan:</h4>
      <ul style="margin: 0; padding-left: 1.25rem; line-height: 1.6;">
        <li>E-Ticket ini berlaku sebagai tiket masuk resmi untuk <?= $tiket['jumlah_tiket'] ?> orang sesuai tanggal kunjungan yang tertera.</li>
        <li>Wajib menunjukkan QR Code ini pada petugas gerbang / loket untuk divalidasi.</li>
        <li>Tiket yang telah divalidasi tidak dapat digunakan kembali.</li>
      </ul>
    </div>

  </div>
</div>

<?php 
if (!isset($_GET['print'])) {
    require_once __DIR__ . '/includes/footer.php';
}
?>
