<?php
require_once __DIR__ . '/config/database.php';
checkAuth();

$userId = $_SESSION['user_id'];

// Ambil riwayat pemesanan pengguna
$stmt = $pdo->prepare("SELECT p.*, d.nama_destinasi, d.lokasi, d.foto_utama 
                       FROM pemesanan p 
                       JOIN destinasi d ON p.destinasi_id = d.id 
                       WHERE p.user_id = ? 
                       ORDER BY p.id DESC");
$stmt->execute([$userId]);
$pesananList = $stmt->fetchAll();

$pageTitle = "Riwayat Pemesanan Tiket";
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/navbar.php';
?>

<div class="container" style="padding-top: 2.5rem; padding-bottom: 4rem;">
  
  <div class="flex items-center justify-between mb-8" style="flex-wrap: wrap; gap: 1rem;">
    <div>
      <span class="badge badge-primary mb-2">Portal Wisatawan</span>
      <h1 class="text-3xl font-extrabold text-dark">Riwayat Tiket Saya</h1>
      <p class="text-muted">Daftar semua pemesanan tiket wisata yang telah Anda buat.</p>
    </div>
    <a href="<?= BASE_URL ?>destinasi.php" class="btn btn-primary">
      <i class="fa-solid fa-plus"></i> Pesan Tiket Baru
    </a>
  </div>

  <?php if (empty($pesananList)): ?>
    <div class="card p-12 text-center" style="border-radius: var(--radius-xl);">
      <i class="fa-solid fa-ticket-simple fa-4x text-muted mb-4" style="opacity: 0.4;"></i>
      <h3 class="text-xl font-bold text-dark">Belum Ada Tiket yang Dipesan</h3>
      <p class="text-muted max-w-md mx-auto mb-6">Anda belum pernah melakukan pemesanan tiket. Ayo jelajahi berbagai destinasi wisata menarik sekarang!</p>
      <a href="<?= BASE_URL ?>destinasi.php" class="btn btn-primary">
        <i class="fa-solid fa-compass"></i> Jelajahi Destinasi Wisata
      </a>
    </div>
  <?php else: ?>
    <div class="flex flex-col gap-4">
      <?php foreach ($pesananList as $item): 
        $foto = !empty($item['foto_utama']) ? (strpos($item['foto_utama'], 'http') === 0 ? $item['foto_utama'] : BASE_URL . 'assets/uploads/destinasi/' . $item['foto_utama']) : 'https://images.unsplash.com/photo-1507525428034-b723cf961d3e?auto=format&fit=crop&w=400&q=80';
      ?>
        <div class="card p-6 shadow-sm flex items-center justify-between" style="border-radius: var(--radius-lg); flex-wrap: wrap; gap: 1.5rem;">
          
          <div class="flex items-center gap-4">
            <img src="<?= htmlspecialchars($foto) ?>" alt="<?= htmlspecialchars($item['nama_destinasi']) ?>" style="width: 100px; height: 80px; object-fit: cover; border-radius: var(--radius-md);">
            <div>
              <div class="flex items-center gap-2 mb-1">
                <span class="text-xs font-mono font-bold text-muted"><?= htmlspecialchars($item['kode_booking']) ?></span>
                <?php if ($item['status_bayar'] === 'lunas'): ?>
                  <span class="badge badge-success"><i class="fa-solid fa-circle-check"></i> Lunas</span>
                <?php elseif ($item['status_bayar'] === 'pending'): ?>
                  <span class="badge badge-warning"><i class="fa-solid fa-clock"></i> Menunggu Bayar</span>
                <?php else: ?>
                  <span class="badge badge-danger"><?= ucfirst($item['status_bayar']) ?></span>
                <?php endif; ?>
                <span class="badge badge-light text-xs"><?= ucfirst($item['tipe_rombongan']) ?></span>
              </div>
              <h3 class="font-bold text-dark text-lg" style="margin-bottom: 0.25rem;"><?= htmlspecialchars($item['nama_destinasi']) ?></h3>
              <div class="flex items-center gap-4 text-xs text-muted">
                <span><i class="fa-solid fa-calendar-day text-primary"></i> Kunjungan: <strong><?= formatTanggalIndo($item['tanggal_kunjungan']) ?></strong></span>
                <span><i class="fa-solid fa-users text-primary"></i> <?= $item['jumlah_tiket'] ?> Tiket</span>
              </div>
            </div>
          </div>

          <div class="flex items-center gap-6" style="flex-wrap: wrap;">
            <div class="text-right">
              <span class="text-xs text-muted block">Total Biaya:</span>
              <strong class="text-lg text-primary"><?= formatRupiah($item['total_bayar']) ?></strong>
            </div>

            <div class="flex items-center gap-2">
              <?php if ($item['status_bayar'] === 'lunas'): ?>
                <a href="<?= BASE_URL ?>tiket.php?kode=<?= $item['kode_booking'] ?>" class="btn btn-primary btn-sm">
                  <i class="fa-solid fa-qrcode"></i> Lihat E-Ticket
                </a>
              <?php elseif ($item['status_bayar'] === 'pending'): ?>
                <a href="<?= BASE_URL ?>pembayaran.php?id=<?= $item['id'] ?>" class="btn btn-warning btn-sm">
                  <i class="fa-solid fa-credit-card"></i> Bayar Sekarang
                </a>
              <?php else: ?>
                <a href="<?= BASE_URL ?>tiket.php?kode=<?= $item['kode_booking'] ?>" class="btn btn-secondary btn-sm">
                  <i class="fa-solid fa-file-invoice"></i> Detail
                </a>
              <?php endif; ?>
            </div>
          </div>

        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
