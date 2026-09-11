<?php
require_once __DIR__ . '/config/database.php';
checkAuth();

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    header("Location: " . BASE_URL . "destinasi.php");
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM destinasi WHERE id = ? AND status = 'buka' LIMIT 1");
$stmt->execute([$id]);
$wisata = $stmt->fetch();

if (!$wisata) {
    header("Location: " . BASE_URL . "destinasi.php");
    exit;
}

$initialQty = max(1, (int)($_GET['qty'] ?? ($wisata['diskon_rombongan'] > 0 ? $wisata['min_rombongan'] : 1)));
$initialTipe = $_GET['tipe'] ?? ($initialQty >= $wisata['min_rombongan'] ? 'rombongan' : 'sendiri');
$error = '';

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $tgl_kunjungan = $_POST['tanggal_kunjungan'] ?? '';
    $jumlah_tiket = (int)($_POST['jumlah_tiket'] ?? 1);
    $tipe_rombongan = $_POST['tipe_rombongan'] ?? 'sendiri';
    $nama_pemesan = trim($_POST['nama_pemesan'] ?? '');
    $no_telp = trim($_POST['no_telp'] ?? '');
    $catatan = trim($_POST['catatan'] ?? '');

    if (empty($tgl_kunjungan)) {
        $error = "Tanggal kunjungan wajib dipilih.";
    } elseif ($jumlah_tiket < 1) {
        $error = "Jumlah tiket minimal 1 lembar.";
    } elseif (empty($nama_pemesan) || empty($no_telp)) {
        $error = "Nama dan nomor kontak wajib diisi.";
    } else {
        // Cek diskon rombongan
        $hargaSatuan = (float)$wisata['harga_tiket'];
        $subtotal = $jumlah_tiket * $hargaSatuan;
        $diskon = 0;

        if ($tipe_rombongan === 'rombongan' || $jumlah_tiket >= $wisata['min_rombongan']) {
            $diskon = round(($subtotal * (float)$wisata['diskon_rombongan']) / 100);
            $tipe_rombongan = 'rombongan';
        } else {
            $tipe_rombongan = 'sendiri';
        }

        $totalBayar = $subtotal - $diskon;
        $kodeBooking = 'BK-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -4));
        $qrCode = 'QR-' . $kodeBooking;
        $userId = $_SESSION['user_id'];

        // Simpan ke tabel pemesanan
        $insertStmt = $pdo->prepare("INSERT INTO pemesanan 
          (kode_booking, user_id, destinasi_id, tanggal_kunjungan, tipe_rombongan, jumlah_tiket, harga_satuan, diskon_didapat, total_bayar, status_bayar, qr_code, status_kunjungan, nama_pemesan, no_telp, catatan, created_at) 
          VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', ?, 'belum_digunakan', ?, ?, ?, NOW())");
        
        $success = $insertStmt->execute([
            $kodeBooking,
            $userId,
            $wisata['id'],
            $tgl_kunjungan,
            $tipe_rombongan,
            $jumlah_tiket,
            $hargaSatuan,
            $diskon,
            $totalBayar,
            $qrCode,
            $nama_pemesan,
            $no_telp,
            $catatan
        ]);

        if ($success) {
            $pemesananId = $pdo->lastInsertId();
            header("Location: " . BASE_URL . "pembayaran.php?id=" . $pemesananId);
            exit;
        } else {
            $error = "Terjadi kesalahan saat memproses pemesanan. Coba lagi.";
        }
    }
}

$pageTitle = "Pesan Tiket - " . $wisata['nama_destinasi'];
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/navbar.php';
?>

<div class="container" style="padding-top: 2.5rem; padding-bottom: 4rem;">
  <div class="max-w-3xl mx-auto">
    
    <!-- Title -->
    <div class="text-center mb-8">
      <span class="badge badge-primary mb-2">Formulir Pemesanan Tiket</span>
      <h1 class="text-3xl font-extrabold text-dark"><?= htmlspecialchars($wisata['nama_destinasi']) ?></h1>
      <p class="text-muted"><i class="fa-solid fa-location-dot text-primary"></i> <?= htmlspecialchars($wisata['lokasi']) ?></p>
    </div>

    <?php if ($error): ?>
      <div class="alert alert-danger mb-6">
        <i class="fa-solid fa-circle-exclamation"></i> <?= htmlspecialchars($error) ?>
      </div>
    <?php endif; ?>

    <div class="grid grid-cols-3 gap-8 items-start">
      <!-- Left (Form) -->
      <div class="card p-6 shadow-md" style="grid-column: span 2; border-radius: var(--radius-xl);">
        <form action="<?= BASE_URL ?>booking.php?id=<?= $wisata['id'] ?>" method="POST" id="bookingForm">
          
          <h3 class="font-bold text-dark mb-4 text-lg border-b pb-2">
            <i class="fa-solid fa-calendar-day text-primary"></i> 1. Rencana Kunjungan
          </h3>

          <div class="grid grid-cols-2 gap-4 mb-4">
            <div class="form-group">
              <label class="form-label font-bold text-xs uppercase text-muted">Tanggal Kunjungan <span class="text-danger">*</span></label>
              <input type="date" name="tanggal_kunjungan" id="tanggal_kunjungan" class="form-control" min="<?= date('Y-m-d') ?>" value="<?= date('Y-m-d') ?>" required>
            </div>

            <div class="form-group">
              <label class="form-label font-bold text-xs uppercase text-muted">Tipe Tiket <span class="text-danger">*</span></label>
              <select name="tipe_rombongan" id="tipe_rombongan" class="form-control">
                <option value="sendiri" <?= ($initialTipe === 'sendiri') ? 'selected' : '' ?>>Perorangan / Reguler</option>
                <option value="rombongan" <?= ($initialTipe === 'rombongan') ? 'selected' : '' ?>>
                  Rombongan (Diskon <?= $wisata['diskon_rombongan'] ?>% Min. <?= $wisata['min_rombongan'] ?> Tiket)
                </option>
              </select>
            </div>
          </div>

          <div class="form-group mb-6">
            <label class="form-label font-bold text-xs uppercase text-muted">Jumlah Tiket / Orang <span class="text-danger">*</span></label>
            <div class="flex items-center gap-3">
              <input type="number" name="jumlah_tiket" id="jumlah_tiket" class="form-control font-bold text-lg" 
                     value="<?= $initialQty ?>" min="1" max="500" 
                     data-harga="<?= $wisata['harga_tiket'] ?>" 
                     data-diskon="<?= $wisata['diskon_rombongan'] ?>" 
                     data-min-rombongan="<?= $wisata['min_rombongan'] ?>" required>
              <span class="text-muted text-sm whitespace-nowrap">Orang / Tiket</span>
            </div>
          </div>

          <h3 class="font-bold text-dark mb-4 text-lg border-b pb-2">
            <i class="fa-solid fa-address-card text-primary"></i> 2. Informasi Kontak Pemesan
          </h3>

          <div class="form-group mb-4">
            <label class="form-label font-bold text-xs uppercase text-muted">Nama Lengkap Pemesan <span class="text-danger">*</span></label>
            <input type="text" name="nama_pemesan" class="form-control" value="<?= htmlspecialchars($_SESSION['user_nama'] ?? '') ?>" required>
          </div>

          <div class="form-group mb-4">
            <label class="form-label font-bold text-xs uppercase text-muted">Nomor WhatsApp / HP Aktif <span class="text-danger">*</span></label>
            <input type="tel" name="no_telp" class="form-control" placeholder="08123456789" required>
          </div>

          <div class="form-group mb-6">
            <label class="form-label font-bold text-xs uppercase text-muted">Catatan Khusus (Opsional)</label>
            <textarea name="catatan" class="form-control" rows="2" placeholder="Cth: Membawa rombongan bus 2 unit, jam kedatangan pukul 10:00..."></textarea>
          </div>

          <button type="submit" class="btn btn-primary btn-block btn-lg shadow-glow">
            <i class="fa-solid fa-arrow-right"></i> Lanjut ke Pembayaran
          </button>
        </form>
      </div>

      <!-- Right (Live Summary) -->
      <div class="card p-6 shadow-md bg-light" style="border-radius: var(--radius-xl); border: 1px solid var(--slate-200);">
        <h3 class="font-bold text-dark mb-4 text-lg border-b pb-2">
          <i class="fa-solid fa-receipt text-primary"></i> Ringkasan Biaya
        </h3>

        <div class="flex flex-col gap-3 text-sm text-slate-600 mb-6">
          <div class="flex items-center justify-between">
            <span>Harga Satuan:</span>
            <strong><?= formatRupiah($wisata['harga_tiket']) ?></strong>
          </div>
          <div class="flex items-center justify-between">
            <span>Subtotal:</span>
            <strong id="displaySubtotal" class="text-dark"><?= formatRupiah($wisata['harga_tiket']) ?></strong>
          </div>
          <div class="flex items-center justify-between text-success" id="diskonRow" style="display: none;">
            <span>Potongan Rombongan:</span>
            <strong id="displayDiskon">- Rp 0</strong>
          </div>
          
          <hr style="border-top: 1px dashed var(--slate-300); margin: 0.5rem 0;">

          <div class="flex items-baseline justify-between text-base">
            <span class="font-bold text-dark">Total Pembayaran:</span>
            <span id="displayTotal" class="font-extrabold text-xl text-primary"><?= formatRupiah($wisata['harga_tiket']) ?></span>
          </div>
        </div>

        <div class="p-3 bg-white rounded text-xs text-muted border">
          <i class="fa-solid fa-info-circle text-primary"></i> Tiket dapat langsung digunakan pada tanggal kunjungan yang dipilih setelah pembayaran terverifikasi.
        </div>
      </div>
    </div>

  </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
