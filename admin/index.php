<?php
require_once __DIR__ . '/../config/database.php';
checkAuth('admin');

// 1. KPI Counts
$totalDestinasi = $pdo->query("SELECT COUNT(*) FROM destinasi")->fetchColumn();
$totalUsers = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
$totalKunjungan = $pdo->query("SELECT COALESCE(SUM(jumlah_pengunjung), 0) FROM presensi_kunjungan")->fetchColumn();
$totalOmset = $pdo->query("SELECT COALESCE(SUM(total_bayar), 0) FROM pemesanan WHERE status_bayar = 'lunas'")->fetchColumn();

// 2. Data Grafik Kunjungan 7 Hari Terakhir
$grafikDates = [];
$grafikCounts = [];
for ($i = 6; $i >= 0; $i--) {
    $d = date('Y-m-d', strtotime("-$i days"));
    $grafikDates[] = date('d M', strtotime($d));

    $stmtG = $pdo->prepare("SELECT COALESCE(SUM(jumlah_pengunjung), 0) FROM presensi_kunjungan WHERE tanggal_kunjungan = ?");
    $stmtG->execute([$d]);
    $grafikCounts[] = (int)$stmtG->fetchColumn();
}

// 3. Destinasi Terpopuler
$topDestinasi = $pdo->query("SELECT d.nama_destinasi, d.rating, d.foto_utama, k.nama_kategori,
                            (SELECT COALESCE(SUM(jumlah_pengunjung), 0) FROM presensi_kunjungan WHERE destinasi_id = d.id) as total_wisatawan
                            FROM destinasi d 
                            JOIN kategori_wisata k ON d.kategori_id = k.id 
                            ORDER BY total_wisatawan DESC, d.rating DESC LIMIT 5")->fetchAll();

// 4. Transaksi Pemesanan Tiket Terbaru
$transaksiTerbaru = $pdo->query("SELECT p.*, d.nama_destinasi, u.nama as nama_user 
                                 FROM pemesanan p 
                                 JOIN destinasi d ON p.destinasi_id = d.id 
                                 JOIN users u ON p.user_id = u.id 
                                 ORDER BY p.id DESC LIMIT 6")->fetchAll();

$pageTitle = "Executive Dashboard Administrator";
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';
?>

<main class="admin-main">
  
  <!-- Topbar Luxury -->
  <div class="admin-topbar-luxury">
    <div>
      <div style="display: flex; align-items: center; gap: 0.4rem; margin-bottom: 0.15rem;">
        <span class="badge-luxury badge-luxury-primary">
          <span style="font-size: 0.85rem;">⚡</span> Live Real-Time
        </span>
        <span style="font-size: 0.75rem; color: #64748b;">
          <?= formatTanggalIndo(date('Y-m-d')) ?> • <span id="liveTime"><?= date('H:i') ?> WIB</span>
        </span>
      </div>
      <h1 style="font-size: 1.35rem; font-weight: 800; color: #0f172a; margin: 0; letter-spacing: -0.02em;">
        Dashboard Eksekutif 🏛️
      </h1>
      <p style="font-size: 0.78rem; color: #64748b; margin: 0.1rem 0 0 0;">
        Selamat bertugas, <strong><?= htmlspecialchars($_SESSION['user_nama']) ?></strong>! Seluruh layanan sistem pariwisata beroperasi normal. ✨
      </p>
    </div>

    <div style="display: flex; align-items: center; gap: 0.5rem;">
      <a href="<?= BASE_URL ?>admin/destinasi.php?action=add" class="btn btn-primary btn-sm" style="box-shadow: 0 2px 8px rgba(2, 132, 199, 0.3);">
        <i class="fa-solid fa-plus"></i> Tambah Destinasi
      </a>
      <a href="<?= BASE_URL ?>admin/laporan.php" class="btn btn-secondary btn-sm">
        <i class="fa-solid fa-file-pdf"></i> Cetak Laporan
      </a>
    </div>
  </div>

  <!-- Luxury KPI Stat Cards -->
  <div class="grid grid-cols-4 gap-4 mb-4">
    
    <!-- KPI 1: Pendapatan -->
    <div class="kpi-card-luxury kpi-primary">
      <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 0.5rem;">
        <div class="kpi-icon-wrap kpi-icon-primary">
          <i class="fa-solid fa-wallet"></i>
        </div>
        <span class="kpi-badge-trend trend-up">
          <i class="fa-solid fa-arrow-trend-up"></i> +18.4%
        </span>
      </div>
      <div>
        <span style="font-size: 0.7rem; font-weight: 700; color: #64748b; text-transform: uppercase; letter-spacing: 0.05em; display: block;">
          Total Pendapatan Tiket
        </span>
        <h2 style="font-size: 1.4rem; font-weight: 900; color: #0f172a; margin: 0.2rem 0 0.15rem 0; font-family: 'Outfit', sans-serif;">
          <?= formatRupiah($totalOmset) ?>
        </h2>
        <span style="font-size: 0.7rem; color: #16a34a; font-weight: 600;">
          <i class="fa-solid fa-circle-check"></i> Transaksi lunas
        </span>
      </div>
    </div>

    <!-- KPI 2: Total Wisatawan -->
    <div class="kpi-card-luxury kpi-success">
      <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 0.5rem;">
        <div class="kpi-icon-wrap kpi-icon-success">
          <i class="fa-solid fa-users"></i>
        </div>
        <span class="kpi-badge-trend trend-up">
          <i class="fa-solid fa-arrow-trend-up"></i> +12.1%
        </span>
      </div>
      <div>
        <span style="font-size: 0.7rem; font-weight: 700; color: #64748b; text-transform: uppercase; letter-spacing: 0.05em; display: block;">
          Total Kunjungan Wisata
        </span>
        <h2 style="font-size: 1.4rem; font-weight: 900; color: #0f172a; margin: 0.2rem 0 0.15rem 0; font-family: 'Outfit', sans-serif;">
          <?= number_format($totalKunjungan) ?> <span style="font-size: 0.85rem; font-weight: 600; color: #64748b;">Orang</span>
        </h2>
        <span style="font-size: 0.7rem; color: #059669; font-weight: 600;">
          <i class="fa-solid fa-qrcode"></i> Validasi gerbang
        </span>
      </div>
    </div>

    <!-- KPI 3: Destinasi -->
    <div class="kpi-card-luxury kpi-warning">
      <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 0.5rem;">
        <div class="kpi-icon-wrap kpi-icon-warning">
          <i class="fa-solid fa-mountain-sun"></i>
        </div>
        <span class="kpi-badge-trend trend-info">
          <i class="fa-solid fa-location-dot"></i> Aktif
        </span>
      </div>
      <div>
        <span style="font-size: 0.7rem; font-weight: 700; color: #64748b; text-transform: uppercase; letter-spacing: 0.05em; display: block;">
          Destinasi Terdaftar
        </span>
        <h2 style="font-size: 1.4rem; font-weight: 900; color: #0f172a; margin: 0.2rem 0 0.15rem 0; font-family: 'Outfit', sans-serif;">
          <?= number_format($totalDestinasi) ?> <span style="font-size: 0.85rem; font-weight: 600; color: #64748b;">Objek</span>
        </h2>
        <span style="font-size: 0.7rem; color: #d97706; font-weight: 600;">
          <i class="fa-solid fa-layer-group"></i> 5 Kategori
        </span>
      </div>
    </div>

    <!-- KPI 4: Users -->
    <div class="kpi-card-luxury kpi-indigo">
      <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 0.5rem;">
        <div class="kpi-icon-wrap kpi-icon-indigo">
          <i class="fa-solid fa-user-shield"></i>
        </div>
        <span class="kpi-badge-trend trend-info">
          <i class="fa-solid fa-shield"></i> Verified
        </span>
      </div>
      <div>
        <span style="font-size: 0.7rem; font-weight: 700; color: #64748b; text-transform: uppercase; letter-spacing: 0.05em; display: block;">
          Total Pengguna Sistem
        </span>
        <h2 style="font-size: 1.4rem; font-weight: 900; color: #0f172a; margin: 0.2rem 0 0.15rem 0; font-family: 'Outfit', sans-serif;">
          <?= number_format($totalUsers) ?> <span style="font-size: 0.85rem; font-weight: 600; color: #64748b;">Akun</span>
        </h2>
        <span style="font-size: 0.7rem; color: #6366f1; font-weight: 600;">
          <i class="fa-solid fa-users"></i> Admin, Petugas, User
        </span>
      </div>
    </div>

  </div>

  <!-- Charts & Top Destination Section -->
  <div class="grid grid-cols-3 gap-4 mb-4">
    
    <!-- Chart Box (Span 2) -->
    <div class="card p-4 shadow-sm" style="grid-column: span 2; border-radius: 0.85rem; border: 1px solid #e2e8f0; background: #ffffff;">
      <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 0.75rem;">
        <div>
          <h3 style="font-size: 1rem; font-weight: 800; color: #0f172a; margin: 0;">
            Tren Kunjungan 7 Hari Terakhir
          </h3>
          <p style="font-size: 0.75rem; color: #64748b; margin: 0.1rem 0 0 0;">
            Frekuensi kedatangan wisatawan harian tercatat di gerbang
          </p>
        </div>
        <span class="badge-luxury badge-luxury-primary">
          <i class="fa-solid fa-chart-line"></i> Grafik Kunjungan
        </span>
      </div>
      <div style="position: relative; height: 210px; width: 100%;">
        <canvas id="kunjunganChart"></canvas>
      </div>
    </div>

    <!-- Top Destination Leaderboard (Span 1) -->
    <div class="card p-4 shadow-sm" style="border-radius: 0.85rem; border: 1px solid #e2e8f0; background: #ffffff; display: flex; flex-direction: column;">
      <div style="margin-bottom: 0.65rem;">
        <h3 style="font-size: 1rem; font-weight: 800; color: #0f172a; margin: 0;">
          Top Destinasi Wisata
        </h3>
        <p style="font-size: 0.75rem; color: #64748b; margin: 0.1rem 0 0 0;">
          Objek wisata terpopuler wisatawan
        </p>
      </div>

      <div style="display: flex; flex-direction: column; gap: 0.45rem; flex: 1; justify-content: space-around;">
        <?php foreach ($topDestinasi as $idx => $d): ?>
          <div style="display: flex; align-items: center; justify-content: space-between; padding: 0.45rem 0.65rem; border-radius: 0.55rem; background: #f8fafc; border: 1px solid #f1f5f9;">
            <div style="display: flex; align-items: center; gap: 0.55rem;">
              <span style="font-size: 0.75rem; font-weight: 800; width: 22px; height: 22px; border-radius: 50%; display: flex; align-items: center; justify-content: center; background: <?= $idx === 0 ? '#fef3c7' : ($idx === 1 ? '#f1f5f9' : '#ffffff') ?>; color: <?= $idx === 0 ? '#d97706' : ($idx === 1 ? '#64748b' : '#94a3b8') ?>; border: 1px solid #e2e8f0;">
                <?= $idx + 1 ?>
              </span>
              <div>
                <strong style="font-size: 0.8rem; color: #0f172a; display: block; line-height: 1.15;">
                  <?= htmlspecialchars($d['nama_destinasi']) ?>
                </strong>
                <span style="font-size: 0.68rem; color: #64748b;">
                  <?= htmlspecialchars($d['nama_kategori']) ?>
                </span>
              </div>
            </div>
            <div style="text-align: right;">
              <strong style="font-size: 0.8rem; color: #0284c7; display: block;">
                <?= number_format($d['total_wisatawan']) ?> <small style="font-size: 0.65rem; color: #64748b;">Org</small>
              </strong>
              <div style="font-size: 0.68rem; color: #f59e0b; font-weight: 700;">
                ★ <?= number_format($d['rating'], 1) ?>
              </div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>

  </div>

  <!-- Recent Transactions Table -->
  <div class="card-table-luxury">
    <div style="padding: 0.75rem 1.15rem; display: flex; align-items: center; justify-content: space-between; border-bottom: 1px solid #e2e8f0;">
      <div>
        <h3 style="font-size: 1rem; font-weight: 800; color: #0f172a; margin: 0;">
          Transaksi Pemesanan Tiket Terbaru
        </h3>
        <p style="font-size: 0.75rem; color: #64748b; margin: 0.1rem 0 0 0;">
          Daftar pemesanan online langsung dari pengunjung
        </p>
      </div>
      <a href="<?= BASE_URL ?>admin/pemesanan.php" class="btn btn-secondary btn-sm">
        Lihat Semua Transaksi &rarr;
      </a>
    </div>

    <div class="overflow-x-auto">
      <table class="table-luxury">
        <thead>
          <tr>
            <th>Kode Booking</th>
            <th>Pemesan</th>
            <th>Destinasi</th>
            <th>Tgl Kunjungan</th>
            <th>Jumlah Tiket</th>
            <th>Total Bayar</th>
            <th>Status Bayar</th>
            <th style="text-align: right;">Aksi</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($transaksiTerbaru as $t): ?>
            <tr>
              <td>
                <span style="font-family: monospace; font-size: 0.8rem; font-weight: 700; background: #f1f5f9; padding: 0.25rem 0.5rem; border-radius: 0.4rem; border: 1px solid #e2e8f0; color: #0f172a;">
                  <?= htmlspecialchars($t['kode_booking']) ?>
                </span>
              </td>
              <td>
                <strong style="color: #0f172a; font-size: 0.875rem; display: block;">
                  <?= htmlspecialchars($t['nama_pemesan'] ?? $t['nama_user']) ?>
                </strong>
                <span style="font-size: 0.75rem; color: #64748b;">
                  <?= htmlspecialchars($t['no_telp'] ?? '-') ?>
                </span>
              </td>
              <td>
                <strong style="font-size: 0.875rem; color: #334155;">
                  <?= htmlspecialchars($t['nama_destinasi']) ?>
                </strong>
              </td>
              <td>
                <span style="font-size: 0.8rem; color: #475569;">
                  <?= formatTanggalIndo($t['tanggal_kunjungan']) ?>
                </span>
              </td>
              <td>
                <span style="font-weight: 600; font-size: 0.85rem;">
                  <?= $t['jumlah_tiket'] ?> Orang
                </span>
                <span style="font-size: 0.72rem; color: #64748b; display: block; text-transform: capitalize;">
                  (<?= htmlspecialchars($t['tipe_rombongan']) ?>)
                </span>
              </td>
              <td>
                <strong style="color: #0284c7; font-size: 0.95rem; font-weight: 800;">
                  <?= formatRupiah($t['total_bayar']) ?>
                </strong>
              </td>
              <td>
                <?php if ($t['status_bayar'] === 'lunas'): ?>
                  <span class="badge-luxury badge-luxury-success">Lunas</span>
                <?php elseif ($t['status_bayar'] === 'pending'): ?>
                  <span class="badge-luxury badge-luxury-warning">Pending</span>
                <?php else: ?>
                  <span class="badge-luxury badge-luxury-danger"><?= ucfirst($t['status_bayar']) ?></span>
                <?php endif; ?>
              </td>
              <td style="text-align: right;">
                <a href="<?= BASE_URL ?>admin/pemesanan.php?action=view&id=<?= $t['id'] ?>" class="btn btn-secondary btn-sm" title="Kelola Pemesanan">
                  <i class="fa-solid fa-eye"></i> Detail
                </a>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

</main>

<script>
  const ctx = document.getElementById('kunjunganChart').getContext('2d');
  
  // Luxury Gradient Fill
  const gradient = ctx.createLinearGradient(0, 0, 0, 280);
  gradient.addColorStop(0, 'rgba(2, 132, 199, 0.35)');
  gradient.addColorStop(1, 'rgba(2, 132, 199, 0.0)');

  new Chart(ctx, {
    type: 'line',
    data: {
      labels: <?= json_encode($grafikDates) ?>,
      datasets: [{
        label: 'Wisatawan (Orang)',
        data: <?= json_encode($grafikCounts) ?>,
        borderColor: '#0284c7',
        borderWidth: 3,
        backgroundColor: gradient,
        fill: true,
        tension: 0.35,
        pointBackgroundColor: '#0284c7',
        pointBorderColor: '#ffffff',
        pointBorderWidth: 2,
        pointRadius: 5,
        pointHoverRadius: 8
      }]
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      plugins: {
        legend: {
          display: false
        },
        tooltip: {
          backgroundColor: '#0f172a',
          titleFont: { family: 'Plus Jakarta Sans', size: 13, weight: 'bold' },
          bodyFont: { family: 'Plus Jakarta Sans', size: 12 },
          padding: 12,
          cornerRadius: 8
        }
      },
      scales: {
        y: {
          beginAtZero: true,
          grid: {
            color: '#f1f5f9',
            drawBorder: false
          },
          ticks: {
            font: { family: 'Plus Jakarta Sans', size: 11 },
            color: '#64748b'
          }
        },
        x: {
          grid: {
            display: false
          },
          ticks: {
            font: { family: 'Plus Jakarta Sans', size: 11 },
            color: '#64748b'
          }
        }
      }
    }
  });

  // Update clock
  setInterval(() => {
    const now = new Date();
    const h = String(now.getHours()).padStart(2, '0');
    const m = String(now.getMinutes()).padStart(2, '0');
    const el = document.getElementById('liveTime');
    if (el) el.textContent = `${h}:${m} WIB`;
  }, 1000);
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
