<?php
require_once __DIR__ . '/config/database.php';
checkAuth();

$id = (int)($_GET['id'] ?? 0);
$kode = trim($_GET['kode'] ?? '');

if ($id <= 0 && empty($kode)) {
    header("Location: " . BASE_URL . "riwayat.php");
    exit;
}

// Ambil data pemesanan
if ($id > 0) {
    $stmt = $pdo->prepare("SELECT p.*, d.nama_destinasi, d.lokasi, d.foto_utama 
                           FROM pemesanan p 
                           JOIN destinasi d ON p.destinasi_id = d.id 
                           WHERE p.id = ? AND p.user_id = ? LIMIT 1");
    $stmt->execute([$id, $_SESSION['user_id']]);
} else {
    $stmt = $pdo->prepare("SELECT p.*, d.nama_destinasi, d.lokasi, d.foto_utama 
                           FROM pemesanan p 
                           JOIN destinasi d ON p.destinasi_id = d.id 
                           WHERE p.kode_booking = ? AND p.user_id = ? LIMIT 1");
    $stmt->execute([$kode, $_SESSION['user_id']]);
}
$pesanan = $stmt->fetch();
if ($pesanan) {
    $id = (int)$pesanan['id'];
}

if (!$pesanan) {
    header("Location: " . BASE_URL . "riwayat.php");
    exit;
}

// Jika sudah lunas, langsung arahkan ke tiket
if ($pesanan['status_bayar'] === 'lunas') {
    header("Location: " . BASE_URL . "tiket.php?kode=" . $pesanan['kode_booking']);
    exit;
}

$msg = '';
$msgType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $metode = $_POST['metode_pembayaran'] ?? 'QRIS Instant';
    $action = $_POST['action_type'] ?? 'simulasi';

    if ($action === 'instan') {
        // Simulasi Pembayaran Instan
        $update = $pdo->prepare("UPDATE pemesanan SET metode_pembayaran = ?, status_bayar = 'lunas' WHERE id = ?");
        if ($update->execute([$metode, $id])) {
            // Catat ke transaksi_log
            $log = $pdo->prepare("INSERT INTO transaksi_log (pemesanan_id, nominal, metode, status, waktu_bayar, catatan) VALUES (?, ?, ?, 'LUNAS', NOW(), 'Pembayaran Instan Demo Berhasil')");
            $log->execute([$id, $pesanan['total_bayar'], $metode]);

            setFlash('success', 'Pembayaran berhasil dikonfirmasi! E-Ticket Anda telah aktif.');
            header("Location: " . BASE_URL . "tiket.php?kode=" . $pesanan['kode_booking']);
            exit;
        }
    } else {
        // Upload Bukti Pembayaran Manual
        $buktiFile = '';
        if (isset($_FILES['bukti_transfer']) && $_FILES['bukti_transfer']['error'] === UPLOAD_ERR_OK) {
            $tmp = $_FILES['bukti_transfer']['tmp_name'];
            $ext = strtolower(pathinfo($_FILES['bukti_transfer']['name'], PATHINFO_EXTENSION));
            $allowed = ['jpg', 'jpeg', 'png', 'webp', 'pdf'];

            if (in_array($ext, $allowed)) {
                $buktiFile = 'bukti_' . $pesanan['kode_booking'] . '_' . time() . '.' . $ext;
                $uploadDir = __DIR__ . '/assets/uploads/bukti/';
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0777, true);
                }
                move_uploaded_file($tmp, $uploadDir . $buktiFile);
            }
        }

        $update = $pdo->prepare("UPDATE pemesanan SET metode_pembayaran = ?, bukti_bayar = COALESCE(NULLIF(?, ''), bukti_bayar), status_bayar = 'lunas' WHERE id = ?");
        if ($update->execute([$metode, $buktiFile, $id])) {
            // Catat ke transaksi_log
            $log = $pdo->prepare("INSERT INTO transaksi_log (pemesanan_id, nominal, metode, status, waktu_bayar, catatan) VALUES (?, ?, ?, 'LUNAS', NOW(), 'Konfirmasi Pembayaran Pengunjung')");
            $log->execute([$id, $pesanan['total_bayar'], $metode]);

            setFlash('success', 'Bukti pembayaran telah diterima dan tiket otomatis lunas!');
            header("Location: " . BASE_URL . "tiket.php?kode=" . $pesanan['kode_booking']);
            exit;
        } else {
            $msg = "Gagal memproses konfirmasi pembayaran.";
            $msgType = "danger";
        }
    }
}

$pageTitle = "Pembayaran Tiket - " . $pesanan['kode_booking'];
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/navbar.php';
?>

<div class="container" style="padding-top: 2.5rem; padding-bottom: 4rem;">
  <div class="max-w-3xl mx-auto">
    
    <div class="text-center mb-8">
      <span class="badge badge-primary mb-2">Instruksi Pembayaran</span>
      <h1 class="text-3xl font-extrabold text-dark">Selesaikan Pembayaran Tiket</h1>
      <p class="text-muted">Kode Booking: <strong class="text-primary"><?= htmlspecialchars($pesanan['kode_booking']) ?></strong></p>
    </div>

    <?php if ($msg): ?>
      <div class="alert alert-<?= $msgType ?> mb-6">
        <i class="fa-solid fa-circle-info"></i> <?= htmlspecialchars($msg) ?>
      </div>
    <?php endif; ?>

    <div class="grid grid-cols-3 gap-8 items-start">
      
      <!-- Kolom Kiri: Pilihan Metode & Konfirmasi -->
      <div class="card p-6 shadow-md" style="grid-column: span 2; border-radius: var(--radius-xl);">
        
        <form action="<?= BASE_URL ?>pembayaran.php?id=<?= $pesanan['id'] ?>" method="POST" enctype="multipart/form-data">
          
          <h3 class="font-bold text-dark mb-4 text-lg border-b pb-2">
            <i class="fa-solid fa-credit-card text-primary"></i> 1. Pilih Metode Pembayaran
          </h3>

          <div class="flex flex-col gap-3 mb-6">
            <!-- QRIS -->
            <label class="p-4 border rounded cursor-pointer flex items-center justify-between hover:border-primary transition" style="background: var(--slate-50);">
              <div class="flex items-center gap-3">
                <input type="radio" name="metode_pembayaran" value="QRIS Instant" checked>
                <div>
                  <strong class="text-dark">QRIS (GoPay, OVO, Dana, ShopeePay, BCA Mobile)</strong>
                  <div class="text-xs text-muted">Scan QR instan dari semua aplikasi bank dan dompet digital</div>
                </div>
              </div>
              <span class="badge badge-success">Instan</span>
            </label>

            <!-- Transfer Bank BCA -->
            <label class="p-4 border rounded cursor-pointer flex items-center justify-between hover:border-primary transition" style="background: var(--slate-50);">
              <div class="flex items-center gap-3">
                <input type="radio" name="metode_pembayaran" value="Transfer BCA">
                <div>
                  <strong class="text-dark">Transfer Bank BCA</strong>
                  <div class="text-xs text-muted">No. Rek: <span class="font-bold text-dark">8271-9920-112</span> a/n PT Pesona Nusantara</div>
                </div>
              </div>
              <span class="badge badge-info">Otomatis</span>
            </label>

            <!-- Transfer Bank Mandiri -->
            <label class="p-4 border rounded cursor-pointer flex items-center justify-between hover:border-primary transition" style="background: var(--slate-50);">
              <div class="flex items-center gap-3">
                <input type="radio" name="metode_pembayaran" value="Transfer Mandiri">
                <div>
                  <strong class="text-dark">Transfer Bank Mandiri / BRI</strong>
                  <div class="text-xs text-muted">No. Rek: <span class="font-bold text-dark">0192-0100-2938-501</span> a/n PT Pesona Nusantara</div>
                </div>
              </div>
              <span class="badge badge-info">Otomatis</span>
            </label>

            <!-- Bayar Tunai di Loket -->
            <label class="p-4 border rounded cursor-pointer flex items-center justify-between hover:border-primary transition" style="background: var(--slate-50);">
              <div class="flex items-center gap-3">
                <input type="radio" name="metode_pembayaran" value="Bayar di Loket">
                <div>
                  <strong class="text-dark">Bayar Tunai di Loket / Lapangan</strong>
                  <div class="text-xs text-muted">Bayar langsung saat tiba di pintu masuk dengan menunjukkan kode booking</div>
                </div>
              </div>
              <span class="badge badge-accent">Di Tempat</span>
            </label>
          </div>

          <h3 class="font-bold text-dark mb-4 text-lg border-b pb-2">
            <i class="fa-solid fa-cloud-arrow-up text-primary"></i> 2. Bukti Transfer (Opsional)
          </h3>

          <div class="form-group mb-6">
            <label class="form-label text-xs font-bold text-muted uppercase">Upload Struk / Bukti Transfer</label>
            <input type="file" name="bukti_transfer" class="form-control" accept="image/*,.pdf">
            <span class="text-xs text-muted mt-1 block">Format: JPG, PNG, WEBP, PDF (Maks. 2MB).</span>
          </div>

          <div class="grid grid-cols-2 gap-4">
            <button type="submit" name="action_type" value="upload" class="btn btn-primary btn-block">
              <i class="fa-solid fa-check-circle"></i> Konfirmasi Pembayaran
            </button>
            <button type="submit" name="action_type" value="instan" class="btn btn-success btn-block" title="Simulasi pembayaran langsung lunas untuk demo">
              <i class="fa-solid fa-bolt"></i> Bayar Sekarang (Instan)
            </button>
          </div>

        </form>

      </div>

      <!-- Kolom Kanan: Rincian Tagihan -->
      <div class="card p-6 shadow-md bg-light" style="border-radius: var(--radius-xl); border: 1px solid var(--slate-200);">
        <h3 class="font-bold text-dark mb-4 text-lg border-b pb-2">
          <i class="fa-solid fa-file-invoice-dollar text-primary"></i> Total Tagihan
        </h3>

        <div class="mb-4">
          <h4 class="font-bold text-dark text-base"><?= htmlspecialchars($pesanan['nama_destinasi']) ?></h4>
          <p class="text-xs text-muted"><i class="fa-solid fa-calendar-day"></i> Kunjungan: <?= formatTanggalIndo($pesanan['tanggal_kunjungan']) ?></p>
        </div>

        <div class="flex flex-col gap-3 text-sm text-slate-600 mb-6">
          <div class="flex items-center justify-between">
            <span>Jumlah Tiket:</span>
            <strong><?= $pesanan['jumlah_tiket'] ?> Tiket (<?= ucfirst($pesanan['tipe_rombongan']) ?>)</strong>
          </div>
          <div class="flex items-center justify-between">
            <span>Harga Satuan:</span>
            <span><?= formatRupiah($pesanan['harga_satuan']) ?></span>
          </div>
          <div class="flex items-center justify-between">
            <span>Subtotal:</span>
            <span><?= formatRupiah($pesanan['jumlah_tiket'] * $pesanan['harga_satuan']) ?></span>
          </div>
          <?php if ($pesanan['diskon_didapat'] > 0): ?>
            <div class="flex items-center justify-between text-success">
              <span>Diskon Rombongan:</span>
              <span>- <?= formatRupiah($pesanan['diskon_didapat']) ?></span>
            </div>
          <?php endif; ?>
          <hr style="border-top: 1px dashed var(--slate-300); margin: 0.5rem 0;">
          <div class="flex items-baseline justify-between text-base">
            <strong class="text-dark">Total Bayar:</strong>
            <strong class="text-xl text-primary font-extrabold"><?= formatRupiah($pesanan['total_bayar']) ?></strong>
          </div>
        </div>

        <div class="p-3 bg-white rounded border text-xs text-muted">
          <i class="fa-solid fa-shield-halved text-success"></i> Setelah pembayaran, E-Ticket ber-QR Code resmi akan langsung aktif.
        </div>
      </div>

    </div>

  </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
