<?php
require_once __DIR__ . '/../config/database.php';
checkAuth('admin');

$destFilter = (int)($_GET['destinasi'] ?? 0);
$tglFilter = $_GET['tgl'] ?? '';
$jenisFilter = $_GET['jenis'] ?? '';
$search = trim($_GET['q'] ?? '');
$action = $_GET['action'] ?? 'list';
$id = (int)($_GET['id'] ?? 0);

if ($action === 'delete' && $id > 0) {
    $del = $pdo->prepare("DELETE FROM presensi_kunjungan WHERE id = ?");
    if ($del->execute([$id])) {
        setFlash('success', 'Catatan kunjungan wisatawan berhasil dihapus.');
    } else {
        setFlash('danger', 'Gagal menghapus data kunjungan.');
    }
    header("Location: " . BASE_URL . "admin/kunjungan.php");
    exit;
}

// Ambil List Destinasi untuk Filter
$destList = $pdo->query("SELECT id, nama_destinasi FROM destinasi ORDER BY nama_destinasi ASC")->fetchAll();

// ==========================================
// HITUNG STATISTIK KUNJUNGAN
// ==========================================
$totalSemuaPengunjung = (int)$pdo->query("SELECT COALESCE(SUM(jumlah_pengunjung), 0) FROM presensi_kunjungan")->fetchColumn();
$totalIndividu = (int)$pdo->query("SELECT COALESCE(SUM(jumlah_pengunjung), 0) FROM presensi_kunjungan WHERE jenis_kunjungan = 'individu'")->fetchColumn();
$totalRombongan = (int)$pdo->query("SELECT COALESCE(SUM(jumlah_pengunjung), 0) FROM presensi_kunjungan WHERE jenis_kunjungan = 'rombongan'")->fetchColumn();

// Destinasi paling banyak dikunjungi
$topDestRow = $pdo->query("SELECT d.nama_destinasi, SUM(k.jumlah_pengunjung) as total 
                          FROM presensi_kunjungan k 
                          JOIN destinasi d ON k.destinasi_id = d.id 
                          GROUP BY d.id 
                          ORDER BY total DESC LIMIT 1")->fetch();

// ==========================================
// BUILD QUERY UTAMA
// ==========================================
$sql = "SELECT k.*, d.nama_destinasi, d.lokasi, COALESCE(u.nama, 'Loket Langsung') as nama_petugas 
        FROM presensi_kunjungan k 
        JOIN destinasi d ON k.destinasi_id = d.id 
        LEFT JOIN users u ON k.petugas_id = u.id 
        WHERE 1=1";
$params = [];

if ($destFilter > 0) {
    $sql .= " AND k.destinasi_id = ?";
    $params[] = $destFilter;
}

if (!empty($tglFilter)) {
    $sql .= " AND k.tanggal_kunjungan = ?";
    $params[] = $tglFilter;
}

if (!empty($jenisFilter)) {
    $sql .= " AND k.jenis_kunjungan = ?";
    $params[] = $jenisFilter;
}

if (!empty($search)) {
    $sql .= " AND (d.nama_destinasi LIKE ? OR u.nama LIKE ? OR k.keterangan LIKE ?)";
    $sw = "%" . $search . "%";
    $params[] = $sw;
    $params[] = $sw;
    $params[] = $sw;
}

$sql .= " ORDER BY k.tanggal_kunjungan DESC, k.id DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$kunjunganList = $stmt->fetchAll();

$filterTotalPengunjung = 0;
foreach ($kunjunganList as $row) {
    $filterTotalPengunjung += (int)$row['jumlah_pengunjung'];
}

$pageTitle = "Rekapitulasi Kunjungan Wisatawan";
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
          <i class="fa-solid fa-users-viewfinder"></i> Log Presensi Lapangan
        </span>
        <span style="font-size: 0.8rem; color: #64748b;">
          Total Terdata: <?= number_format($totalSemuaPengunjung) ?> Wisatawan
        </span>
      </div>
      <h1 style="font-size: 1.6rem; font-weight: 800; color: #0f172a; margin: 0; letter-spacing: -0.02em;">
        Rekapitulasi Kunjungan Wisata
      </h1>
      <p style="font-size: 0.85rem; color: #64748b; margin: 0.2rem 0 0 0;">
        Monitoring real-time data wisatawan masuk yang dicatat oleh petugas lapangan dan loket destinasi.
      </p>
    </div>

    <div style="display: flex; align-items: center; gap: 0.6rem; flex-wrap: wrap;">
      <a href="<?= BASE_URL ?>admin/laporan.php" class="btn btn-primary btn-sm" style="font-weight: 700; box-shadow: 0 4px 12px rgba(13, 148, 136, 0.35); padding: 0.55rem 1.15rem;">
        <i class="fa-solid fa-file-invoice-dollar"></i> Buka Laporan Keuangan
      </a>
      <button type="button" onclick="window.print()" class="btn btn-secondary btn-sm" style="background: #ffffff; border: 1px solid #cbd5e1; padding: 0.55rem 0.85rem;" title="Cetak Rekap">
        <i class="fa-solid fa-print"></i> Cetak
      </button>
    </div>
  </div>

  <!-- Flash Notification -->
  <?php if ($flash): ?>
    <div class="alert alert-<?= $flash['type'] ?> mb-6" style="border-radius: 0.85rem; padding: 0.9rem 1.25rem; display: flex; align-items: center; gap: 0.75rem; box-shadow: 0 4px 12px rgba(0,0,0,0.03);">
      <i class="fa-solid <?= $flash['type'] === 'success' ? 'fa-circle-check' : 'fa-circle-exclamation' ?>" style="font-size: 1.25rem;"></i>
      <div style="font-size: 0.9rem; line-height: 1.4;"><?= $flash['message'] ?></div>
    </div>
  <?php endif; ?>

  <!-- 4 KPI Stat Cards -->
  <div class="grid grid-cols-4 gap-5 mb-6">
    
    <!-- Total Wisatawan -->
    <div class="kpi-card-luxury kpi-primary" style="padding: 1.25rem; border: 1px solid #e2e8f0;">
      <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 0.75rem;">
        <div class="kpi-icon-wrap" style="width: 44px; height: 44px; font-size: 1.15rem; background: #ccfbf1; color: #0d9488;">
          <i class="fa-solid fa-people-group"></i>
        </div>
        <span class="badge-luxury badge-luxury-primary" style="font-size: 0.7rem;">Akumulasi</span>
      </div>
      <span style="font-size: 0.72rem; font-weight: 700; color: #64748b; text-transform: uppercase; letter-spacing: 0.05em; display: block;">
        Total Wisatawan
      </span>
      <h3 style="font-size: 1.5rem; font-weight: 900; color: #0f172a; margin: 0.2rem 0 0 0; font-family: 'Outfit', sans-serif;">
        <?= number_format($totalSemuaPengunjung) ?> <span style="font-size: 0.85rem; font-weight: 600; color: #64748b;">Orang</span>
      </h3>
    </div>

    <!-- Individu -->
    <a href="<?= BASE_URL ?>admin/kunjungan.php?jenis=individu" style="text-decoration: none; color: inherit;">
      <div class="kpi-card-luxury kpi-info" style="cursor: pointer; padding: 1.25rem; border: <?= $jenisFilter === 'individu' ? '2px solid #0284c7' : '1px solid #e2e8f0' ?>;">
        <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 0.75rem;">
          <div class="kpi-icon-wrap" style="width: 44px; height: 44px; font-size: 1.15rem; background: #e0f2fe; color: #0284c7;">
            <i class="fa-solid fa-user"></i>
          </div>
          <span class="badge-luxury badge-luxury-info" style="font-size: 0.7rem;">Perorangan</span>
        </div>
        <span style="font-size: 0.72rem; font-weight: 700; color: #64748b; text-transform: uppercase; letter-spacing: 0.05em; display: block;">
          Kunjungan Individu
        </span>
        <h3 style="font-size: 1.5rem; font-weight: 900; color: #0f172a; margin: 0.2rem 0 0 0; font-family: 'Outfit', sans-serif;">
          <?= number_format($totalIndividu) ?> <span style="font-size: 0.85rem; font-weight: 600; color: #64748b;">Orang</span>
        </h3>
      </div>
    </a>

    <!-- Rombongan -->
    <a href="<?= BASE_URL ?>admin/kunjungan.php?jenis=rombongan" style="text-decoration: none; color: inherit;">
      <div class="kpi-card-luxury kpi-warning" style="cursor: pointer; padding: 1.25rem; border: <?= $jenisFilter === 'rombongan' ? '2px solid #d97706' : '1px solid #e2e8f0' ?>;">
        <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 0.75rem;">
          <div class="kpi-icon-wrap" style="width: 44px; height: 44px; font-size: 1.15rem; background: #fef3c7; color: #d97706;">
            <i class="fa-solid fa-bus-simple"></i>
          </div>
          <span class="badge-luxury badge-luxury-warning" style="font-size: 0.7rem;">Grup / Tour</span>
        </div>
        <span style="font-size: 0.72rem; font-weight: 700; color: #64748b; text-transform: uppercase; letter-spacing: 0.05em; display: block;">
          Kunjungan Rombongan
        </span>
        <h3 style="font-size: 1.5rem; font-weight: 900; color: #0f172a; margin: 0.2rem 0 0 0; font-family: 'Outfit', sans-serif;">
          <?= number_format($totalRombongan) ?> <span style="font-size: 0.85rem; font-weight: 600; color: #64748b;">Orang</span>
        </h3>
      </div>
    </a>

    <!-- Destinasi Terpadat -->
    <div class="kpi-card-luxury kpi-success" style="padding: 1.25rem; border: 1px solid #e2e8f0;">
      <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 0.75rem;">
        <div class="kpi-icon-wrap" style="width: 44px; height: 44px; font-size: 1.15rem; background: #dcfce7; color: #059669;">
          <i class="fa-solid fa-trophy"></i>
        </div>
        <span class="badge-luxury badge-luxury-success" style="font-size: 0.7rem;">Terfavorit</span>
      </div>
      <span style="font-size: 0.72rem; font-weight: 700; color: #64748b; text-transform: uppercase; letter-spacing: 0.05em; display: block;">
        Destinasi Paling Ramai
      </span>
      <h3 style="font-size: 1.1rem; font-weight: 800; color: #0f172a; margin: 0.2rem 0 0 0; font-family: 'Outfit', sans-serif; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;" title="<?= htmlspecialchars($topDestRow['nama_destinasi'] ?? '-') ?>">
        <?= htmlspecialchars($topDestRow['nama_destinasi'] ?? '-') ?> <span style="font-size: 0.8rem; font-weight: 600; color: #059669;">(<?= number_format($topDestRow['total'] ?? 0) ?>)</span>
      </h3>
    </div>

  </div>

  <!-- Filter Toolbar -->
  <div class="card p-4 shadow-sm mb-6 bg-white" style="border-radius: 1rem; border: 1px solid #e2e8f0;">
    <form action="<?= BASE_URL ?>admin/kunjungan.php" method="GET" class="flex items-center gap-3" style="flex-wrap: wrap;">
      
      <!-- Destinasi Dropdown -->
      <div style="display: flex; align-items: center; gap: 0.4rem; min-width: 220px;">
        <i class="fa-solid fa-mountain-sun text-muted text-xs"></i>
        <select name="destinasi" class="form-control text-sm" style="border-radius: 0.65rem;">
          <option value="">Semua Destinasi</option>
          <?php foreach ($destList as $d): ?>
            <option value="<?= $d['id'] ?>" <?= $destFilter == $d['id'] ? 'selected' : '' ?>>
              <?= htmlspecialchars($d['nama_destinasi']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <!-- Tanggal Picker -->
      <div style="display: flex; align-items: center; gap: 0.4rem;">
        <i class="fa-regular fa-calendar text-muted text-xs"></i>
        <input type="date" name="tgl" class="form-control text-sm" value="<?= htmlspecialchars($tglFilter) ?>" style="border-radius: 0.65rem;">
      </div>

      <!-- Jenis Kunjungan Dropdown -->
      <div style="display: flex; align-items: center; gap: 0.4rem;">
        <select name="jenis" class="form-control text-sm" style="border-radius: 0.65rem;">
          <option value="">Semua Tipe</option>
          <option value="individu" <?= $jenisFilter === 'individu' ? 'selected' : '' ?>>Individu</option>
          <option value="rombongan" <?= $jenisFilter === 'rombongan' ? 'selected' : '' ?>>Rombongan</option>
        </select>
      </div>

      <!-- Search Keyword -->
      <div style="display: flex; align-items: center; gap: 0.4rem; flex: 1; min-width: 180px;">
        <input type="text" name="q" id="searchKunjunganInput" class="form-control text-sm" value="<?= htmlspecialchars($search) ?>" placeholder="Cari destinasi / petugas..." style="border-radius: 0.65rem;" onkeyup="clientFilterKunjungan()">
      </div>

      <button type="submit" class="btn btn-primary btn-sm" style="border-radius: 0.65rem; font-weight: 700; padding: 0.55rem 1rem;">
        <i class="fa-solid fa-filter"></i> Terapkan
      </button>

      <?php if ($destFilter > 0 || !empty($tglFilter) || !empty($jenisFilter) || !empty($search)): ?>
        <a href="<?= BASE_URL ?>admin/kunjungan.php" class="btn btn-secondary btn-sm" style="border-radius: 0.65rem;" title="Reset Filter">
          <i class="fa-solid fa-rotate-left"></i> Reset
        </a>
      <?php endif; ?>

      <div style="margin-left: auto; display: flex; align-items: center; gap: 0.5rem; background: #f8fafc; padding: 0.4rem 0.85rem; border-radius: 0.65rem; border: 1px solid #e2e8f0;">
        <span class="text-xs text-muted uppercase font-bold">Hasil Filter:</span>
        <strong style="font-size: 1.05rem; color: #0d9488; font-weight: 800;"><?= number_format($filterTotalPengunjung) ?></strong>
        <span style="font-size: 0.8rem; color: #64748b;">Wisatawan</span>
      </div>

    </form>
  </div>

  <!-- Luxury Data Table -->
  <div class="card-table-luxury" style="width: 100%;">
    <div style="padding: 1.25rem 1.5rem; border-bottom: 1px solid #e2e8f0; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 0.75rem;">
      <div>
        <h3 style="font-size: 1.1rem; font-weight: 800; color: #0f172a; margin: 0; display: flex; align-items: center; gap: 0.5rem;">
          Log Catatan Presensi Lapangan
          <span style="font-size: 0.8rem; font-weight: 700; color: #0d9488; background: #ccfbf1; padding: 0.15rem 0.6rem; border-radius: 9999px;">
            <?= count($kunjunganList) ?> Entri
          </span>
        </h3>
        <p style="font-size: 0.78rem; color: #64748b; margin: 0.15rem 0 0 0;">
          Rekaman presensi masuk wisatawan langsung per destinasi yang dientrikan petugas.
        </p>
      </div>

      <div style="display: flex; gap: 0.4rem;">
        <a href="<?= BASE_URL ?>petugas/input_kunjungan.php" target="_blank" class="btn btn-secondary btn-sm" style="background: #ffffff; border: 1px solid #cbd5e1; border-radius: 0.5rem; font-size: 0.78rem;">
          <i class="fa-solid fa-arrow-up-right-from-square"></i> Form Input Petugas
        </a>
      </div>
    </div>

    <div style="overflow-x: auto; width: 100%;">
      <table class="table-luxury" id="kunjunganDataTable" style="width: 100%;">
        <thead>
          <tr>
            <th style="width: 14%;">Waktu Input</th>
            <th style="width: 25%;">Destinasi Wisata</th>
            <th style="width: 16%;">Tanggal Kunjungan</th>
            <th style="width: 15%; text-align: center;">Jumlah Masuk</th>
            <th style="width: 12%;">Tipe</th>
            <th style="width: 18%;">Petugas Loket</th>
            <th style="width: 10%; text-align: right;">Aksi</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($kunjunganList)): ?>
            <tr>
              <td colspan="7" style="text-align: center; padding: 3rem 1rem;">
                <div style="max-width: 320px; margin: 0 auto;">
                  <div style="width: 60px; height: 60px; border-radius: 50%; background: #f1f5f9; color: #94a3b8; display: inline-flex; align-items: center; justify-content: center; font-size: 1.5rem; margin-bottom: 1rem;">
                    <i class="fa-solid fa-clipboard-list"></i>
                  </div>
                  <h4 style="font-size: 1.05rem; font-weight: 700; color: #334155; margin: 0 0 0.25rem 0;">Tidak Ada Log Kunjungan</h4>
                  <p style="font-size: 0.8rem; color: #64748b; margin: 0 0 1rem 0;">Belum ada data presensi wisatawan untuk kriteria filter yang dipilih.</p>
                </div>
              </td>
            </tr>
          <?php else: ?>
            <?php foreach ($kunjunganList as $k): ?>
              <tr class="kunjungan-row">
                <td>
                  <div style="font-size: 0.82rem; font-weight: 700; color: #0f172a;">
                    <?= date('d/m/Y', strtotime($k['created_at'])) ?>
                  </div>
                  <span style="font-size: 0.72rem; color: #94a3b8;">
                    <i class="fa-regular fa-clock"></i> <?= date('H:i', strtotime($k['created_at'])) ?> WIB
                  </span>
                </td>
                <td>
                  <strong class="text-dark dest-name" style="font-size: 0.92rem;"><?= htmlspecialchars($k['nama_destinasi']) ?></strong>
                  <p class="text-xs text-muted" style="margin: 0.15rem 0 0 0; display: flex; align-items: center; gap: 0.3rem;">
                    <i class="fa-solid fa-location-dot" style="color: #ef4444; font-size: 0.7rem;"></i> <?= htmlspecialchars($k['lokasi']) ?>
                  </p>
                </td>
                <td>
                  <div style="font-weight: 700; color: #1e293b; font-size: 0.88rem;">
                    <?= formatTanggalIndo($k['tanggal_kunjungan']) ?>
                  </div>
                  <span class="badge-luxury badge-luxury-info" style="font-size: 0.68rem; margin-top: 0.2rem;">
                    <?= htmlspecialchars($k['hari']) ?>
                  </span>
                </td>
                <td style="text-align: center;">
                  <div style="display: inline-block; background: #ccfbf1; border: 1px solid #99f6e4; padding: 0.35rem 0.85rem; border-radius: 9999px;">
                    <strong style="color: #0f766e; font-size: 0.95rem; font-family: 'Outfit', sans-serif;">
                      <?= number_format($k['jumlah_pengunjung']) ?>
                    </strong>
                    <span style="font-size: 0.72rem; color: #0f766e; font-weight: 600;"> Orang</span>
                  </div>
                </td>
                <td>
                  <?php if ($k['jenis_kunjungan'] === 'rombongan'): ?>
                    <span class="badge-luxury badge-luxury-warning" style="font-size: 0.72rem;">
                      <i class="fa-solid fa-bus-simple"></i> Rombongan
                    </span>
                  <?php else: ?>
                    <span class="badge-luxury badge-luxury-primary" style="font-size: 0.72rem;">
                      <i class="fa-solid fa-user"></i> Individu
                    </span>
                  <?php endif; ?>
                </td>
                <td>
                  <div style="display: flex; align-items: center; gap: 0.4rem;">
                    <div style="width: 28px; height: 28px; border-radius: 50%; background: #e0f2fe; color: #0284c7; display: flex; align-items: center; justify-content: center; font-size: 0.75rem;">
                      <i class="fa-solid fa-user-shield"></i>
                    </div>
                    <div>
                      <strong class="petugas-name" style="font-size: 0.82rem; color: #1e293b; display: block;"><?= htmlspecialchars($k['nama_petugas']) ?></strong>
                      <?php if (!empty($k['keterangan'])): ?>
                        <span class="text-xs text-muted info-desc" style="display: block; max-width: 160px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;" title="<?= htmlspecialchars($k['keterangan']) ?>">
                          <?= htmlspecialchars($k['keterangan']) ?>
                        </span>
                      <?php endif; ?>
                    </div>
                  </div>
                </td>
                <td style="text-align: right;">
                  <a href="<?= BASE_URL ?>admin/kunjungan.php?action=delete&id=<?= $k['id'] ?>" class="btn btn-danger btn-sm" title="Hapus Data" style="border-radius: 0.5rem;" onclick="return confirm('Apakah Anda yakin ingin menghapus data presensi kunjungan ini?')">
                    <i class="fa-solid fa-trash"></i>
                  </a>
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
function clientFilterKunjungan() {
  const filter = document.getElementById('searchKunjunganInput').value.toLowerCase();
  const rows = document.querySelectorAll('#kunjunganDataTable tbody tr.kunjungan-row');
  
  rows.forEach(row => {
    const dest = row.querySelector('.dest-name') ? row.querySelector('.dest-name').innerText.toLowerCase() : '';
    const petugas = row.querySelector('.petugas-name') ? row.querySelector('.petugas-name').innerText.toLowerCase() : '';
    const desc = row.querySelector('.info-desc') ? row.querySelector('.info-desc').innerText.toLowerCase() : '';

    if (dest.includes(filter) || petugas.includes(filter) || desc.includes(filter)) {
      row.style.display = '';
    } else {
      row.style.display = 'none';
    }
  });
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
