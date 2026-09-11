<?php
require_once __DIR__ . '/../config/database.php';
checkAuth('admin');

$tglMulai = $_GET['tgl_mulai'] ?? date('Y-m-01');
$tglSelesai = $_GET['tgl_selesai'] ?? date('Y-m-d');
$tipeLaporan = $_GET['tipe'] ?? 'semua';
$isPrint = isset($_GET['print']);

// 1. Data Pemesanan Tiket (Keuangan)
$stmtPemesanan = $pdo->prepare("SELECT p.*, d.nama_destinasi 
                                FROM pemesanan p 
                                JOIN destinasi d ON p.destinasi_id = d.id 
                                WHERE DATE(p.created_at) BETWEEN ? AND ? 
                                ORDER BY p.id DESC");
$stmtPemesanan->execute([$tglMulai, $tglSelesai]);
$laporanPemesanan = $stmtPemesanan->fetchAll();

$totalOmsetLunas = 0;
$totalTiketTerjual = 0;
foreach ($laporanPemesanan as $p) {
    if ($p['status_bayar'] === 'lunas') {
        $totalOmsetLunas += (float)$p['total_bayar'];
        $totalTiketTerjual += (int)$p['jumlah_tiket'];
    }
}

// 2. Data Kunjungan Lapangan
$stmtKunjungan = $pdo->prepare("SELECT k.*, d.nama_destinasi, COALESCE(u.nama, 'Petugas Loket') as nama_petugas 
                                FROM presensi_kunjungan k 
                                JOIN destinasi d ON k.destinasi_id = d.id 
                                LEFT JOIN users u ON k.petugas_id = u.id 
                                WHERE k.tanggal_kunjungan BETWEEN ? AND ? 
                                ORDER BY k.tanggal_kunjungan DESC");
$stmtKunjungan->execute([$tglMulai, $tglSelesai]);
$laporanKunjungan = $stmtKunjungan->fetchAll();

$totalPengunjungLapangan = 0;
foreach ($laporanKunjungan as $k) {
    $totalPengunjungLapangan += (int)$k['jumlah_pengunjung'];
}

$pageTitle = "Laporan & Rekapitulasi Pariwisata";
if (!$isPrint) {
    require_once __DIR__ . '/includes/header.php';
    require_once __DIR__ . '/includes/sidebar.php';
} else {
    ?>
    <!DOCTYPE html>
    <html lang="id">
    <head>
      <meta charset="UTF-8">
      <title>Cetak Laporan - <?= htmlspecialchars($settings['nama_sistem']) ?></title>
      <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/style.css">
      <style>
        body { background: white !important; color: black !important; padding: 20px; }
        .no-print { display: none !important; }
        .table th, .table td { border-color: #cbd5e1 !important; color: black !important; }
      </style>
    </head>
    <body onload="window.print()">
    <?php
}
?>

<main class="<?= $isPrint ? 'p-4' : 'admin-main' ?>">
  
  <div class="admin-topbar-luxury no-print">
    <div>
      <h1 style="font-size: 1.5rem; font-weight: 800; color: #0f172a; margin: 0;">Laporan Eksekutif & Rekapitulasi</h1>
      <p style="font-size: 0.85rem; color: #64748b; margin: 0.2rem 0 0 0;">Laporan audit pendapatan tiket dan rekapitulasi jumlah kunjungan per periode</p>
    </div>
    <div class="flex gap-2">
      <a href="<?= BASE_URL ?>admin/laporan.php?tgl_mulai=<?= urlencode($tglMulai) ?>&tgl_selesai=<?= urlencode($tglSelesai) ?>&tipe=<?= urlencode($tipeLaporan) ?>&print=1" target="_blank" class="btn btn-primary btn-sm" style="box-shadow: 0 4px 12px rgba(2, 132, 199, 0.35);">
        <i class="fa-solid fa-print"></i> Cetak Dokumen Resmi
      </a>
    </div>
  </div>

  <!-- Header Cetak Khusus Print -->
  <div class="print-header mb-8 text-center" style="<?= $isPrint ? '' : 'display: none;' ?>">
    <h2 style="font-size: 1.5rem; font-weight: 800; margin: 0;"><?= strtoupper(htmlspecialchars($settings['nama_sistem'])) ?></h2>
    <p style="margin: 0; font-size: 0.9rem; color: #475569;"><?= htmlspecialchars($settings['alamat']) ?> | Telp: <?= htmlspecialchars($settings['kontak_telp']) ?></p>
    <hr style="border: 2px solid #000; margin: 15px 0;">
    <h3 style="font-size: 1.2rem; font-weight: 700; margin-bottom: 5px;">LAPORAN REKAPITULASI PENDAPATAN & KUNJUNGAN WISATA</h3>
    <p style="font-size: 0.85rem; color: #475569;">Periode: <strong><?= formatTanggalIndo($tglMulai) ?></strong> s/d <strong><?= formatTanggalIndo($tglSelesai) ?></strong></p>
  </div>

  <!-- Filter Card -->
  <div class="card p-4 shadow-sm mb-6 bg-white no-print" style="border-radius: var(--radius-lg);">
    <form action="<?= BASE_URL ?>admin/laporan.php" method="GET" class="flex items-center gap-4" style="flex-wrap: wrap;">
      <div class="flex items-center gap-2">
        <label class="text-xs font-bold text-muted uppercase">Mulai:</label>
        <input type="date" name="tgl_mulai" class="form-control text-sm" value="<?= htmlspecialchars($tglMulai) ?>" required>
      </div>

      <div class="flex items-center gap-2">
        <label class="text-xs font-bold text-muted uppercase">Sampai:</label>
        <input type="date" name="tgl_selesai" class="form-control text-sm" value="<?= htmlspecialchars($tglSelesai) ?>" required>
      </div>

      <div class="flex items-center gap-2">
        <label class="text-xs font-bold text-muted uppercase">Kategori:</label>
        <select name="tipe" class="form-control text-sm">
          <option value="semua" <?= $tipeLaporan === 'semua' ? 'selected' : '' ?>>Semua Data</option>
          <option value="pemesanan" <?= $tipeLaporan === 'pemesanan' ? 'selected' : '' ?>>Hanya Pemesanan (Keuangan)</option>
          <option value="kunjungan" <?= $tipeLaporan === 'kunjungan' ? 'selected' : '' ?>>Hanya Kunjungan Lapangan</option>
        </select>
      </div>

      <button type="submit" class="btn btn-primary btn-sm">
        <i class="fa-solid fa-filter"></i> Tampilkan
      </button>
    </form>
  </div>

  <!-- Summary KPI Cards -->
  <div class="grid grid-cols-3 gap-6 mb-8">
    <div class="card p-6 shadow-sm border" style="border-radius: var(--radius-lg);">
      <span class="text-xs font-bold text-muted uppercase">Total Omset Tiket Lunas</span>
      <h2 class="text-2xl font-black text-primary mt-1"><?= formatRupiah($totalOmsetLunas) ?></h2>
      <span class="text-xs text-muted"><?= count($laporanPemesanan) ?> Transaksi Terdata</span>
    </div>

    <div class="card p-6 shadow-sm border" style="border-radius: var(--radius-lg);">
      <span class="text-xs font-bold text-muted uppercase">Total Tiket Online Terjual</span>
      <h2 class="text-2xl font-black text-dark mt-1"><?= number_format($totalTiketTerjual) ?> Tiket</h2>
      <span class="text-xs text-muted">Tiket terverifikasi</span>
    </div>

    <div class="card p-6 shadow-sm border" style="border-radius: var(--radius-lg);">
      <span class="text-xs font-bold text-muted uppercase">Total Pengunjung Lapangan</span>
      <h2 class="text-2xl font-black text-secondary mt-1"><?= number_format($totalPengunjungLapangan) ?> Orang</h2>
      <span class="text-xs text-muted">Input loket & check-in</span>
    </div>
  </div>

  <!-- Tabel 1: Pemesanan Tiket Online -->
  <?php if ($tipeLaporan === 'semua' || $tipeLaporan === 'pemesanan'): ?>
    <div class="card p-6 shadow-sm bg-white mb-8" style="border-radius: var(--radius-xl);">
      <h3 class="font-bold text-dark text-lg mb-4">1. Rincian Pemesanan Tiket Online</h3>
      <div class="overflow-x-auto">
        <table class="table">
          <thead>
            <tr>
              <th>Kode Booking</th>
              <th>Nama Pemesan</th>
              <th>Destinasi</th>
              <th>Tgl Kunjungan</th>
              <th>Jumlah Tiket</th>
              <th>Diskon</th>
              <th>Total Bayar</th>
              <th>Status</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($laporanPemesanan)): ?>
              <tr><td colspan="8" class="text-center py-4 text-muted">Tidak ada transaksi pada periode ini.</td></tr>
            <?php else: ?>
              <?php foreach ($laporanPemesanan as $p): ?>
                <tr>
                  <td class="font-mono font-bold text-xs"><?= htmlspecialchars($p['kode_booking']) ?></td>
                  <td><?= htmlspecialchars($p['nama_pemesan'] ?? '-') ?></td>
                  <td><?= htmlspecialchars($p['nama_destinasi']) ?></td>
                  <td><?= formatTanggalIndo($p['tanggal_kunjungan']) ?></td>
                  <td><?= $p['jumlah_tiket'] ?> (<?= ucfirst($p['tipe_rombongan']) ?>)</td>
                  <td class="text-xs text-muted"><?= formatRupiah($p['diskon_didapat']) ?></td>
                  <td class="font-bold"><?= formatRupiah($p['total_bayar']) ?></td>
                  <td>
                    <span class="badge badge-<?= $p['status_bayar'] === 'lunas' ? 'success' : 'warning' ?>">
                      <?= ucfirst($p['status_bayar']) ?>
                    </span>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  <?php endif; ?>

  <!-- Tabel 2: Kunjungan Lapangan -->
  <?php if ($tipeLaporan === 'semua' || $tipeLaporan === 'kunjungan'): ?>
    <div class="card p-6 shadow-sm bg-white mb-8" style="border-radius: var(--radius-xl);">
      <h3 class="font-bold text-dark text-lg mb-4">2. Rekapitulasi Kunjungan Lapangan & Loket</h3>
      <div class="overflow-x-auto">
        <table class="table">
          <thead>
            <tr>
              <th>Tanggal</th>
              <th>Hari</th>
              <th>Destinasi</th>
              <th>Jumlah Pengunjung</th>
              <th>Tipe</th>
              <th>Petugas Input</th>
              <th>Keterangan</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($laporanKunjungan)): ?>
              <tr><td colspan="7" class="text-center py-4 text-muted">Tidak ada data kunjungan pada periode ini.</td></tr>
            <?php else: ?>
              <?php foreach ($laporanKunjungan as $k): ?>
                <tr>
                  <td><?= formatTanggalIndo($k['tanggal_kunjungan']) ?></td>
                  <td><?= htmlspecialchars($k['hari']) ?></td>
                  <td><strong><?= htmlspecialchars($k['nama_destinasi']) ?></strong></td>
                  <td class="font-bold text-primary"><?= number_format($k['jumlah_pengunjung']) ?> Orang</td>
                  <td><?= ucfirst($k['jenis_kunjungan']) ?></td>
                  <td><?= htmlspecialchars($k['nama_petugas']) ?></td>
                  <td class="text-xs text-muted"><?= htmlspecialchars($k['keterangan'] ?? '-') ?></td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  <?php endif; ?>

  <!-- Footer Tanda Tangan Cetak -->
  <div class="print-signature" style="<?= $isPrint ? 'margin-top: 50px; display: flex; justify-content: flex-end;' : 'display: none;' ?>">
    <div style="text-align: center; width: 250px;">
      <p style="margin-bottom: 60px;">Dicetak pada: <?= formatTanggalIndo(date('Y-m-d')) ?><br><strong>Administrator Sistem</strong></p>
      <p style="font-weight: bold; text-decoration: underline;"><?= htmlspecialchars($_SESSION['user_nama']) ?></p>
    </div>
  </div>

</main>

<?php 
if (!$isPrint) {
    require_once __DIR__ . '/includes/footer.php';
} else {
    echo "</body></html>";
}
?>
