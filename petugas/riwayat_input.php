<?php
require_once __DIR__ . '/../config/database.php';
checkAuth('petugas');

$userId = $_SESSION['user_id'];
$filterTgl = $_GET['tgl'] ?? '';
$filterDestinasi = (int)($_GET['destinasi_id'] ?? 0);
$filterJenis = $_GET['jenis'] ?? '';
$filterSearch = trim($_GET['search'] ?? '');
$filterPreset = $_GET['preset'] ?? '';

// Tangani preset tanggal cepat
if ($filterPreset === 'today') {
    $filterTgl = date('Y-m-d');
} elseif ($filterPreset === 'yesterday') {
    $filterTgl = date('Y-m-d', strtotime('-1 day'));
}

// 1. ACTION: TAMBAH DATA PRESENSI BARU
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['action_add_presensi'])) {
    $destId = (int)($_POST['destinasi_id'] ?? 0);
    $tglKunjungan = $_POST['tanggal_kunjungan'] ?? date('Y-m-d');
    $jml = (int)($_POST['jumlah_pengunjung'] ?? 1);
    $jenis = ($_POST['jenis_kunjungan'] === 'rombongan') ? 'rombongan' : 'sendiri';
    $ket = trim($_POST['keterangan'] ?? 'Loket Fisik');
    $hari = getNamaHariIndo($tglKunjungan);

    if ($destId <= 0 || $jml < 1) {
        setFlash('danger', 'Gagal menambah: Mohon pilih destinasi dan masukkan jumlah pengunjung yang valid (minimal 1).');
    } else {
        $ins = $pdo->prepare("INSERT INTO presensi_kunjungan (petugas_id, destinasi_id, tanggal_kunjungan, hari, jumlah_pengunjung, jenis_kunjungan, keterangan, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())");
        if ($ins->execute([$userId, $destId, $tglKunjungan, $hari, $jml, $jenis, $ket])) {
            setFlash('success', "Presensi <strong>" . number_format($jml) . " wisatawan</strong> berhasil ditambahkan ke dalam rekapitulasi!");
        } else {
            setFlash('danger', 'Terjadi kesalahan sistem saat menyimpan data presensi.');
        }
    }
    header("Location: " . BASE_URL . "petugas/riwayat_input.php" . (!empty($_SERVER['QUERY_STRING']) ? '?' . $_SERVER['QUERY_STRING'] : ''));
    exit;
}

// 2. ACTION: EDIT DATA PRESENSI
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['action_edit_presensi'])) {
    $presensiId = (int)$_POST['presensi_id'];
    $destId = (int)($_POST['destinasi_id'] ?? 0);
    $tglKunjungan = $_POST['tanggal_kunjungan'] ?? date('Y-m-d');
    $jml = (int)($_POST['jumlah_pengunjung'] ?? 1);
    $jenis = ($_POST['jenis_kunjungan'] === 'rombongan') ? 'rombongan' : 'sendiri';
    $ket = trim($_POST['keterangan'] ?? '');
    $hari = getNamaHariIndo($tglKunjungan);

    // Cek kepemilikan data (petugas sendiri atau admin)
    $stmtCek = $pdo->prepare("SELECT id FROM presensi_kunjungan WHERE id = ? " . ($_SESSION['user_role'] === 'admin' ? "" : "AND petugas_id = ?"));
    $checkParams = ($_SESSION['user_role'] === 'admin') ? [$presensiId] : [$presensiId, $userId];
    $stmtCek->execute($checkParams);

    if ($stmtCek->fetch()) {
        if ($destId <= 0 || $jml < 1) {
            setFlash('danger', 'Gagal mengubah: Data destinasi dan jumlah pengunjung harus valid.');
        } else {
            $upd = $pdo->prepare("UPDATE presensi_kunjungan SET destinasi_id = ?, tanggal_kunjungan = ?, hari = ?, jumlah_pengunjung = ?, jenis_kunjungan = ?, keterangan = ? WHERE id = ?");
            if ($upd->execute([$destId, $tglKunjungan, $hari, $jml, $jenis, $ket, $presensiId])) {
                setFlash('success', "Data presensi ID #<strong>$presensiId</strong> berhasil diperbarui!");
            } else {
                setFlash('danger', 'Gagal memperbarui data presensi.');
            }
        }
    } else {
        setFlash('danger', 'Akses ditolak atau data presensi tidak ditemukan.');
    }
    header("Location: " . BASE_URL . "petugas/riwayat_input.php" . (!empty($_SERVER['QUERY_STRING']) ? '?' . $_SERVER['QUERY_STRING'] : ''));
    exit;
}

// 3. ACTION: HAPUS DATA PRESENSI
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['action_delete_presensi'])) {
    $presensiId = (int)$_POST['presensi_id'];
    
    // Cek kepemilikan
    $stmtCek = $pdo->prepare("SELECT id, jumlah_pengunjung FROM presensi_kunjungan WHERE id = ? " . ($_SESSION['user_role'] === 'admin' ? "" : "AND petugas_id = ?"));
    $checkParams = ($_SESSION['user_role'] === 'admin') ? [$presensiId] : [$presensiId, $userId];
    $stmtCek->execute($checkParams);
    $dataDel = $stmtCek->fetch();

    if ($dataDel) {
        $del = $pdo->prepare("DELETE FROM presensi_kunjungan WHERE id = ?");
        if ($del->execute([$presensiId])) {
            setFlash('success', "Data presensi (" . number_format($dataDel['jumlah_pengunjung']) . " pengunjung) berhasil dihapus dari rekapitulasi.");
        } else {
            setFlash('danger', 'Gagal menghapus entri presensi.');
        }
    } else {
        setFlash('danger', 'Data tidak ditemukan atau Anda tidak memiliki hak menghapus entri ini.');
    }
    header("Location: " . BASE_URL . "petugas/riwayat_input.php" . (!empty($_SERVER['QUERY_STRING']) ? '?' . $_SERVER['QUERY_STRING'] : ''));
    exit;
}

// 4. QUERY UTAMA DENGAN FILTER
$sql = "SELECT k.*, d.nama_destinasi, d.lokasi, d.harga_tiket 
        FROM presensi_kunjungan k 
        JOIN destinasi d ON k.destinasi_id = d.id 
        WHERE 1=1";
$params = [];

if ($_SESSION['user_role'] !== 'admin') {
    $sql .= " AND k.petugas_id = ?";
    $params[] = $userId;
}

if (!empty($filterTgl)) {
    $sql .= " AND k.tanggal_kunjungan = ?";
    $params[] = $filterTgl;
} elseif ($filterPreset === 'this_month') {
    $sql .= " AND MONTH(k.tanggal_kunjungan) = MONTH(CURRENT_DATE()) AND YEAR(k.tanggal_kunjungan) = YEAR(CURRENT_DATE())";
} elseif ($filterPreset === 'last_7_days') {
    $sql .= " AND k.tanggal_kunjungan >= DATE_SUB(CURRENT_DATE(), INTERVAL 7 DAY)";
}

if ($filterDestinasi > 0) {
    $sql .= " AND k.destinasi_id = ?";
    $params[] = $filterDestinasi;
}

if (!empty($filterJenis)) {
    $sql .= " AND k.jenis_kunjungan = ?";
    $params[] = $filterJenis;
}

if (!empty($filterSearch)) {
    $sql .= " AND (d.nama_destinasi LIKE ? OR k.keterangan LIKE ?)";
    $params[] = "%$filterSearch%";
    $params[] = "%$filterSearch%";
}

$sql .= " ORDER BY k.id DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$riwayat = $stmt->fetchAll();

// Hitung Statistik Berdasarkan Filter
$totalOrang = 0;
$totalRombongan = 0;
$totalIndividu = 0;
foreach ($riwayat as $r) {
    $jml = (int)$r['jumlah_pengunjung'];
    $totalOrang += $jml;
    if ($r['jenis_kunjungan'] === 'rombongan') {
        $totalRombongan += $jml;
    } else {
        $totalIndividu += $jml;
    }
}

// Data Destinasi Buka untuk Form Tambah & Edit
$destinasiList = $pdo->query("SELECT id, nama_destinasi, lokasi, harga_tiket FROM destinasi WHERE status = 'buka' ORDER BY nama_destinasi ASC")->fetchAll();

// Statistik Global Petugas Hari Ini & Total All
$whereUser = ($_SESSION['user_role'] === 'admin') ? "1=1" : "petugas_id = $userId";

$stmtTotalAll = $pdo->query("SELECT COALESCE(SUM(jumlah_pengunjung),0) FROM presensi_kunjungan WHERE $whereUser");
$grandTotalOrang = (int)$stmtTotalAll->fetchColumn();

$stmtStatHariIni = $pdo->query("SELECT COALESCE(SUM(jumlah_pengunjung),0) FROM presensi_kunjungan WHERE $whereUser AND tanggal_kunjungan = CURDATE()");
$todayTotalOrang = (int)$stmtStatHariIni->fetchColumn();

$pageTitle = "Riwayat & Kelola Presensi Masuk";
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';
$flash = getFlash();
?>

<main class="admin-main">
  
  <!-- Header Topbar Luxury (Ultra-Compact) -->
  <div class="admin-topbar-luxury" style="padding: 0.65rem 1.15rem; margin-bottom: 0.75rem; border-radius: 0.75rem;">
    <div style="display: flex; align-items: center; gap: 0.85rem; flex-wrap: wrap;">
      <h1 style="font-size: 1.25rem; font-weight: 800; color: #0f172a; margin: 0; display: flex; align-items: center; gap: 0.5rem;">
        <i class="fa-solid fa-clipboard-list text-primary" style="font-size: 1.1rem;"></i>
        Riwayat & Input Presensi Loket
      </h1>
      <span class="badge-luxury badge-luxury-warning" style="font-size: 0.7rem; padding: 0.15rem 0.5rem;">
        Akumulasi: <?= number_format($grandTotalOrang) ?> Wisatawan
      </span>
    </div>
    
    <div style="display: flex; gap: 0.4rem; align-items: center;">
      <button onclick="window.print()" class="btn btn-secondary btn-xs" style="background: #ffffff; border: 1px solid #cbd5e1; font-weight: 700; border-radius: 0.5rem; padding: 0.35rem 0.65rem; height: 32px;">
        <i class="fa-solid fa-print"></i> Cetak
      </button>
      <button type="button" onclick="openAddModal()" class="btn btn-primary btn-xs btn-luxury-pulse" style="font-weight: 800; box-shadow: 0 4px 12px rgba(13, 148, 136, 0.35); border-radius: 0.5rem; padding: 0.35rem 0.85rem; height: 32px; font-size: 0.8rem;">
        <i class="fa-solid fa-plus"></i> + Tambah Presensi
      </button>
    </div>
  </div>

  <!-- Flash Notification -->
  <?php if ($flash): ?>
    <div class="alert alert-<?= $flash['type'] ?> mb-3" style="border-radius: 0.65rem; padding: 0.65rem 1rem; display: flex; align-items: center; gap: 0.6rem; font-size: 0.85rem;">
      <i class="fa-solid <?= $flash['type'] === 'success' ? 'fa-circle-check' : 'fa-circle-exclamation' ?>" style="font-size: 1.1rem;"></i>
      <div><?= $flash['message'] ?></div>
    </div>
  <?php endif; ?>

  <!-- Summary 4 KPI Cards Grid (Ultra-Fitted Zoom 100%) -->
  <div class="grid grid-cols-4 gap-3 mb-3">
    
    <!-- KPI 1 -->
    <div class="kpi-card-luxury" style="padding: 0.65rem 0.85rem; border-radius: 0.75rem;">
      <div style="display: flex; align-items: center; justify-content: space-between;">
        <div>
          <span style="font-size: 0.68rem; font-weight: 800; text-transform: uppercase; color: #64748b; letter-spacing: 0.04em; display: block;">Total Terfilter</span>
          <div style="font-size: 1.35rem; font-weight: 900; color: #0284c7; line-height: 1.1; margin-top: 0.1rem; font-family: 'Outfit', sans-serif;">
            <?= number_format($totalOrang) ?> <span style="font-size: 0.75rem; font-weight: 600; color: #64748b;">Org</span>
          </div>
        </div>
        <div class="kpi-icon-wrap" style="width: 34px; height: 34px; font-size: 0.95rem; background: #e0f2fe; color: #0284c7; border-radius: 0.5rem;">
          <i class="fa-solid fa-users"></i>
        </div>
      </div>
    </div>

    <!-- KPI 2 -->
    <div class="kpi-card-luxury" style="padding: 0.65rem 0.85rem; border-radius: 0.75rem;">
      <div style="display: flex; align-items: center; justify-content: space-between;">
        <div>
          <span style="font-size: 0.68rem; font-weight: 800; text-transform: uppercase; color: #64748b; letter-spacing: 0.04em; display: block;">Shift Hari Ini</span>
          <div style="font-size: 1.35rem; font-weight: 900; color: #0d9488; line-height: 1.1; margin-top: 0.1rem; font-family: 'Outfit', sans-serif;">
            <?= number_format($todayTotalOrang) ?> <span style="font-size: 0.75rem; font-weight: 600; color: #64748b;">Org</span>
          </div>
        </div>
        <div class="kpi-icon-wrap" style="width: 34px; height: 34px; font-size: 0.95rem; background: #ccfbf1; color: #0d9488; border-radius: 0.5rem;">
          <i class="fa-solid fa-calendar-day"></i>
        </div>
      </div>
    </div>

    <!-- KPI 3 -->
    <div class="kpi-card-luxury" style="padding: 0.65rem 0.85rem; border-radius: 0.75rem;">
      <div style="display: flex; align-items: center; justify-content: space-between;">
        <div>
          <span style="font-size: 0.68rem; font-weight: 800; text-transform: uppercase; color: #64748b; letter-spacing: 0.04em; display: block;">Rombongan / Tour</span>
          <div style="font-size: 1.35rem; font-weight: 900; color: #d97706; line-height: 1.1; margin-top: 0.1rem; font-family: 'Outfit', sans-serif;">
            <?= number_format($totalRombongan) ?> <span style="font-size: 0.75rem; font-weight: 600; color: #64748b;">Org</span>
          </div>
        </div>
        <div class="kpi-icon-wrap" style="width: 34px; height: 34px; font-size: 0.95rem; background: #fef3c7; color: #d97706; border-radius: 0.5rem;">
          <i class="fa-solid fa-people-group"></i>
        </div>
      </div>
    </div>

    <!-- KPI 4 -->
    <div class="kpi-card-luxury" style="padding: 0.65rem 0.85rem; border-radius: 0.75rem;">
      <div style="display: flex; align-items: center; justify-content: space-between;">
        <div>
          <span style="font-size: 0.68rem; font-weight: 800; text-transform: uppercase; color: #64748b; letter-spacing: 0.04em; display: block;">Individu / Sendiri</span>
          <div style="font-size: 1.35rem; font-weight: 900; color: #334155; line-height: 1.1; margin-top: 0.1rem; font-family: 'Outfit', sans-serif;">
            <?= number_format($totalIndividu) ?> <span style="font-size: 0.75rem; font-weight: 600; color: #64748b;">Org</span>
          </div>
        </div>
        <div class="kpi-icon-wrap" style="width: 34px; height: 34px; font-size: 0.95rem; background: #f1f5f9; color: #475569; border-radius: 0.5rem;">
          <i class="fa-solid fa-user"></i>
        </div>
      </div>
    </div>

  </div>

  <!-- Filter & Preset Bar (Ultra-Compact Horizontal Row) -->
  <div class="card p-2 bg-white shadow-sm mb-3" style="border-radius: 0.75rem; border: 1px solid #e2e8f0;">
    <form action="<?= BASE_URL ?>petugas/riwayat_input.php" method="GET" style="display: flex; gap: 0.35rem; align-items: center; flex-wrap: wrap;">
      
      <!-- Preset Buttons -->
      <div style="display: flex; gap: 0.25rem;">
        <a href="<?= BASE_URL ?>petugas/riwayat_input.php" class="btn btn-xs <?= (empty($filterTgl) && empty($filterPreset) && $filterDestinasi == 0) ? 'btn-primary' : 'btn-secondary' ?>" style="font-weight: 700; border-radius: 0.4rem; padding: 0.25rem 0.55rem; font-size: 0.75rem;">
          Semua
        </a>
        <a href="<?= BASE_URL ?>petugas/riwayat_input.php?preset=today" class="btn btn-xs <?= ($filterPreset === 'today' || $filterTgl === date('Y-m-d')) ? 'btn-primary' : 'btn-secondary' ?>" style="font-weight: 700; border-radius: 0.4rem; padding: 0.25rem 0.55rem; font-size: 0.75rem;">
          Hari Ini
        </a>
        <a href="<?= BASE_URL ?>petugas/riwayat_input.php?preset=yesterday" class="btn btn-xs <?= ($filterPreset === 'yesterday') ? 'btn-primary' : 'btn-secondary' ?>" style="font-weight: 700; border-radius: 0.4rem; padding: 0.25rem 0.55rem; font-size: 0.75rem;">
          Kemarin
        </a>
        <a href="<?= BASE_URL ?>petugas/riwayat_input.php?preset=this_month" class="btn btn-xs <?= ($filterPreset === 'this_month') ? 'btn-primary' : 'btn-secondary' ?>" style="font-weight: 700; border-radius: 0.4rem; padding: 0.25rem 0.55rem; font-size: 0.75rem;">
          Bulan Ini
        </a>
      </div>

      <div style="height: 18px; width: 1px; background: #e2e8f0; margin: 0 0.15rem;"></div>

      <!-- Destinasi Dropdown Filter -->
      <select name="destinasi_id" class="form-control" style="width: auto; min-width: 150px; font-size: 0.78rem; padding: 0.25rem 0.5rem; border-radius: 0.4rem; height: 30px;">
        <option value="0">-- Semua Destinasi --</option>
        <?php foreach ($destinasiList as $d): ?>
          <option value="<?= $d['id'] ?>" <?= $filterDestinasi == $d['id'] ? 'selected' : '' ?>>
            <?= htmlspecialchars($d['nama_destinasi']) ?>
          </option>
        <?php endforeach; ?>
      </select>

      <!-- Tanggal Kustom Input -->
      <input type="date" name="tgl" value="<?= htmlspecialchars($filterTgl) ?>" class="form-control" style="width: auto; font-size: 0.78rem; padding: 0.25rem 0.5rem; border-radius: 0.4rem; height: 30px;" title="Filter Tanggal">

      <!-- Keyword Search -->
      <div style="position: relative; flex: 1; min-width: 140px;">
        <i class="fa-solid fa-magnifying-glass" style="position: absolute; left: 0.65rem; top: 50%; transform: translateY(-50%); color: #94a3b8; font-size: 0.72rem;"></i>
        <input type="text" name="search" value="<?= htmlspecialchars($filterSearch) ?>" placeholder="Cari keterangan..." class="form-control" style="padding-left: 1.75rem; font-size: 0.78rem; border-radius: 0.4rem; height: 30px;">
      </div>

      <button type="submit" class="btn btn-primary btn-xs" style="height: 30px; border-radius: 0.4rem; padding: 0 0.75rem; font-weight: 700;">
        <i class="fa-solid fa-filter"></i> Saring
      </button>

      <?php if (!empty($filterTgl) || $filterDestinasi > 0 || !empty($filterPreset) || !empty($filterSearch)): ?>
        <a href="<?= BASE_URL ?>petugas/riwayat_input.php" class="btn btn-secondary btn-xs" style="height: 30px; border-radius: 0.4rem; padding: 0 0.6rem; display: inline-flex; align-items: center;" title="Reset Filter">
          <i class="fa-solid fa-rotate-left"></i>
        </a>
      <?php endif; ?>

    </form>
  </div>

  <!-- Luxury Interactive Data Table (Fitted & Compact) -->
  <div class="card-table-luxury" style="border-radius: 0.75rem;">
    <div style="padding: 0.65rem 1rem; border-bottom: 1px solid #e2e8f0; display: flex; align-items: center; justify-content: space-between; background: #ffffff;">
      <h3 style="font-size: 0.9rem; font-weight: 800; color: #0f172a; margin: 0; display: flex; align-items: center; gap: 0.4rem;">
        <i class="fa-solid fa-list-check text-primary"></i> Data Presensi (<?= count($riwayat) ?> Entri)
      </h3>
      <span class="badge-luxury badge-luxury-success" style="font-size: 0.68rem; padding: 0.15rem 0.45rem;">
        <span style="width:5px;height:5px;border-radius:50%;background:#22c55e;display:inline-block;"></span> Data Terverifikasi
      </span>
    </div>

    <?php if (empty($riwayat)): ?>
      <div style="text-align: center; padding: 2.5rem 1.5rem; color: #94a3b8; background: #ffffff;">
        <i class="fa-solid fa-folder-open" style="font-size: 2rem; margin-bottom: 0.5rem; display: block; color: #cbd5e1;"></i>
        <h4 style="font-size: 0.95rem; font-weight: 800; color: #334155; margin: 0 0 0.25rem 0;">Tidak Ada Data Presensi</h4>
        <p style="font-size: 0.8rem; color: #64748b; margin: 0 0 0.75rem 0;">Belum ada entri presensi yang sesuai filter.</p>
        <button type="button" onclick="openAddModal()" class="btn btn-primary btn-xs btn-luxury-pulse" style="font-weight: 800;">
          <i class="fa-solid fa-plus"></i> Tambah Data Sekarang
        </button>
      </div>
    <?php else: ?>
      <div class="overflow-x-auto" style="background: #ffffff;">
        <table class="table-luxury" style="font-size: 0.82rem;">
          <thead>
            <tr style="background: #f8fafc;">
              <th style="width: 45px; padding: 0.6rem 0.85rem; font-size: 0.7rem;">#ID</th>
              <th style="padding: 0.6rem 0.85rem; font-size: 0.7rem;">Destinasi Objek Wisata</th>
              <th style="padding: 0.6rem 0.85rem; font-size: 0.7rem;">Jadwal Kunjungan</th>
              <th style="padding: 0.6rem 0.85rem; font-size: 0.7rem;">Wisatawan Masuk</th>
              <th style="padding: 0.6rem 0.85rem; font-size: 0.7rem;">Kategori</th>
              <th style="padding: 0.6rem 0.85rem; font-size: 0.7rem;">Catatan Loket</th>
              <th style="padding: 0.6rem 0.85rem; font-size: 0.7rem;">Waktu Input</th>
              <th style="text-align: right; width: 120px; padding: 0.6rem 0.85rem; font-size: 0.7rem;">Aksi</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($riwayat as $row): 
              $isRombongan = ($row['jenis_kunjungan'] === 'rombongan');
            ?>
              <tr>
                <td style="padding: 0.55rem 0.85rem;">
                  <span style="font-size: 0.72rem; font-weight: 800; color: #94a3b8; font-family: monospace;">
                    #<?= $row['id'] ?>
                  </span>
                </td>

                <td style="padding: 0.55rem 0.85rem;">
                  <strong class="text-dark" style="font-size: 0.85rem; display: block; color: #0f172a;">
                    <?= htmlspecialchars($row['nama_destinasi']) ?>
                  </strong>
                  <span style="font-size: 0.72rem; color: #64748b;">
                    <i class="fa-solid fa-location-dot text-rose-500"></i> <?= htmlspecialchars($row['lokasi']) ?>
                  </span>
                </td>

                <td style="padding: 0.55rem 0.85rem;">
                  <strong style="font-size: 0.82rem; color: #0f172a; display: block;">
                    <?= formatTanggalIndo($row['tanggal_kunjungan']) ?>
                  </strong>
                  <span style="font-size: 0.7rem; color: #0284c7; font-weight: 700;">
                    Hari <?= htmlspecialchars($row['hari']) ?>
                  </span>
                </td>

                <td style="padding: 0.55rem 0.85rem;">
                  <strong style="font-size: 1rem; color: #0d9488; font-weight: 900; font-family: 'Outfit', sans-serif;">
                    <?= number_format($row['jumlah_pengunjung']) ?>
                  </strong>
                  <span style="font-size: 0.72rem; font-weight: 600; color: #64748b;"> Orang</span>
                </td>

                <td style="padding: 0.55rem 0.85rem;">
                  <?php if ($isRombongan): ?>
                    <span class="badge" style="background: #fef3c7; color: #b45309; border: 1px solid #fde68a; font-size: 0.68rem; font-weight: 800; border-radius: 4px; padding: 0.15rem 0.45rem;">
                      <i class="fa-solid fa-people-group"></i> Rombongan
                    </span>
                  <?php else: ?>
                    <span class="badge" style="background: #f1f5f9; color: #475569; border: 1px solid #e2e8f0; font-size: 0.68rem; font-weight: 700; border-radius: 4px; padding: 0.15rem 0.45rem;">
                      <i class="fa-solid fa-user"></i> Sendiri
                    </span>
                  <?php endif; ?>
                </td>

                <td style="padding: 0.55rem 0.85rem;">
                  <span style="font-size: 0.78rem; color: #475569; display: block; max-width: 200px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;" title="<?= htmlspecialchars($row['keterangan'] ?? '-') ?>">
                    <?= htmlspecialchars($row['keterangan'] ?? '-') ?>
                  </span>
                </td>

                <td style="padding: 0.55rem 0.85rem;">
                  <span style="font-size: 0.72rem; color: #64748b; display: block;">
                    <?= date('d/m/Y', strtotime($row['created_at'])) ?>
                  </span>
                  <span style="font-size: 0.68rem; color: #94a3b8; font-weight: 600;">
                    <?= date('H:i', strtotime($row['created_at'])) ?> WIB
                  </span>
                </td>

                <td style="text-align: right; padding: 0.55rem 0.85rem;">
                  <div style="display: flex; gap: 0.25rem; justify-content: flex-end;">
                    
                    <!-- Tombol Edit Modal -->
                    <button type="button" 
                            class="btn btn-secondary btn-xs" 
                            style="padding: 0.2rem 0.45rem; font-size: 0.72rem; font-weight: 700; border-radius: 0.35rem; background: #ffffff; border: 1px solid #cbd5e1; color: #0284c7;"
                            onclick="openEditModal(<?= htmlspecialchars(json_encode($row)) ?>)"
                            title="Edit Data Presensi">
                      <i class="fa-solid fa-pen-to-square"></i> Edit
                    </button>

                    <!-- Form Hapus -->
                    <form action="<?= BASE_URL ?>petugas/riwayat_input.php<?= !empty($_SERVER['QUERY_STRING']) ? '?' . $_SERVER['QUERY_STRING'] : '' ?>" 
                          method="POST" 
                          onsubmit="return confirm('Hapus presensi #<?= $row['id'] ?> (<?= $row['jumlah_pengunjung'] ?> orang)?')" 
                          style="display: inline;">
                      <input type="hidden" name="action_delete_presensi" value="1">
                      <input type="hidden" name="presensi_id" value="<?= $row['id'] ?>">
                      <button type="submit" class="btn btn-danger btn-xs" style="padding: 0.2rem 0.45rem; font-size: 0.72rem; border-radius: 0.35rem;" title="Hapus Presensi">
                        <i class="fa-solid fa-trash"></i>
                      </button>
                    </form>

                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>

</main>

<!-- ======================================================== -->
<!-- 1. MODAL TAMBAH PRESENSI BARU (HIDDEN POPUP MODAL)       -->
<!-- ======================================================== -->
<div id="addModalBackdrop" class="luxury-modal-backdrop" style="display: none; position: fixed; inset: 0; background: rgba(15, 23, 42, 0.75); backdrop-filter: blur(6px); -webkit-backdrop-filter: blur(6px); z-index: 99999; align-items: center; justify-content: center; padding: 1.25rem;" onclick="closeAddModal(event)">
  <div class="luxury-modal-box" style="background: #ffffff; border-radius: 1.25rem; width: 100%; max-width: 520px; box-shadow: 0 25px 60px -15px rgba(0, 0, 0, 0.45); overflow: hidden; position: relative;" onclick="event.stopPropagation()">
    
    <!-- Modal Header -->
    <div style="background: linear-gradient(135deg, #0d9488 0%, #0284c7 100%); padding: 1rem 1.25rem; color: white; display: flex; align-items: center; justify-content: space-between;">
      <div style="display: flex; align-items: center; gap: 0.5rem;">
        <div style="background: rgba(255,255,255,0.2); width: 32px; height: 32px; border-radius: 8px; display: flex; align-items: center; justify-content: center;">
          <i class="fa-solid fa-plus text-white"></i>
        </div>
        <div>
          <h3 style="font-size: 1.05rem; font-weight: 800; margin: 0; color: white; font-family: 'Outfit', sans-serif;">Tambah Presensi Loket Baru</h3>
          <span style="font-size: 0.7rem; color: #ccfbf1;">Catat wisatawan yang masuk gerbang</span>
        </div>
      </div>
      <button type="button" onclick="closeAddModalDirect()" style="background: none; border: none; color: white; font-size: 1.25rem; cursor: pointer; padding: 0.25rem;">
        <i class="fa-solid fa-xmark"></i>
      </button>
    </div>

    <!-- Modal Form -->
    <form action="<?= BASE_URL ?>petugas/riwayat_input.php<?= !empty($_SERVER['QUERY_STRING']) ? '?' . $_SERVER['QUERY_STRING'] : '' ?>" method="POST" style="padding: 1.25rem;">
      <input type="hidden" name="action_add_presensi" value="1">

      <!-- Destinasi Wisata -->
      <div class="form-group mb-3">
        <label style="display: block; font-size: 0.72rem; font-weight: 700; color: #334155; margin-bottom: 0.3rem; text-transform: uppercase; letter-spacing: 0.04em;">
          Destinasi Objek Wisata <span style="color: #ef4444;">*</span>
        </label>
        <div style="position: relative;">
          <i class="fa-solid fa-mountain-sun" style="position: absolute; left: 0.85rem; top: 50%; transform: translateY(-50%); color: #0d9488;"></i>
          <select name="destinasi_id" class="form-control" style="padding-left: 2.3rem; border-radius: 0.5rem; font-weight: 700; font-size: 0.85rem; height: 36px;" required>
            <option value="">-- Pilih Destinasi Wisata --</option>
            <?php foreach ($destinasiList as $d): ?>
              <option value="<?= $d['id'] ?>">
                <?= htmlspecialchars($d['nama_destinasi']) ?> (<?= htmlspecialchars($d['lokasi']) ?>)
              </option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>

      <!-- Tanggal Kunjungan -->
      <div class="grid grid-cols-2 gap-3 mb-3">
        <div>
          <label style="display: block; font-size: 0.72rem; font-weight: 700; color: #334155; margin-bottom: 0.3rem; text-transform: uppercase; letter-spacing: 0.04em;">
            Tanggal Kunjungan <span style="color: #ef4444;">*</span>
          </label>
          <input type="date" name="tanggal_kunjungan" class="form-control" style="border-radius: 0.5rem; font-weight: 600; font-size: 0.85rem; height: 36px;" value="<?= date('Y-m-d') ?>" required>
        </div>

        <div>
          <label style="display: block; font-size: 0.72rem; font-weight: 700; color: #334155; margin-bottom: 0.3rem; text-transform: uppercase; letter-spacing: 0.04em;">
            Kategori Kunjungan <span style="color: #ef4444;">*</span>
          </label>
          <select name="jenis_kunjungan" class="form-control" style="border-radius: 0.5rem; font-weight: 600; font-size: 0.85rem; height: 36px;" required>
            <option value="sendiri">Sendiri / Individu</option>
            <option value="rombongan">Rombongan / Grup</option>
          </select>
        </div>
      </div>

      <!-- Jumlah Wisatawan Stepper -->
      <div class="form-group mb-3">
        <label style="display: block; font-size: 0.72rem; font-weight: 700; color: #334155; margin-bottom: 0.3rem; text-transform: uppercase; letter-spacing: 0.04em;">
          Jumlah Wisatawan Masuk <span style="color: #ef4444;">*</span>
        </label>
        <div style="display: flex; align-items: center; gap: 0.35rem;">
          <input type="number" name="jumlah_pengunjung" id="addJmlPengunjung" class="form-control" style="border-radius: 0.5rem; font-size: 1rem; font-weight: 800; color: #0d9488; height: 36px;" value="1" min="1" max="1000" required>
          <button type="button" onclick="setAddQty(1)" class="btn btn-secondary btn-xs" style="border-radius: 0.4rem; font-weight: 700; height: 36px; padding: 0 0.6rem;">+1</button>
          <button type="button" onclick="setAddQty(5)" class="btn btn-secondary btn-xs" style="border-radius: 0.4rem; font-weight: 700; height: 36px; padding: 0 0.6rem;">+5</button>
          <button type="button" onclick="setAddQty(10)" class="btn btn-secondary btn-xs" style="border-radius: 0.4rem; font-weight: 700; height: 36px; padding: 0 0.6rem;">+10</button>
          <button type="button" onclick="setAddQty(25)" class="btn btn-secondary btn-xs" style="border-radius: 0.4rem; font-weight: 700; height: 36px; padding: 0 0.6rem;">+25</button>
        </div>
      </div>

      <!-- Keterangan -->
      <div class="form-group mb-4">
        <label style="display: block; font-size: 0.72rem; font-weight: 700; color: #334155; margin-bottom: 0.3rem; text-transform: uppercase; letter-spacing: 0.04em;">
          Keterangan / Catatan Loket
        </label>
        <input type="text" name="keterangan" class="form-control" style="border-radius: 0.5rem; font-size: 0.85rem; height: 36px;" placeholder="Cth: Rombongan Bus Surabaya / Loket Barat">
      </div>

      <!-- Modal Footer Action -->
      <div style="display: flex; justify-content: flex-end; gap: 0.4rem; border-top: 1px solid #f1f5f9; padding-top: 0.85rem;">
        <button type="button" onclick="closeAddModalDirect()" class="btn btn-secondary btn-xs" style="font-weight: 700; border-radius: 0.4rem; padding: 0.4rem 0.85rem;">
          Batal
        </button>
        <button type="submit" class="btn btn-primary btn-xs btn-luxury-pulse" style="font-weight: 800; border-radius: 0.4rem; padding: 0.4rem 1.15rem;">
          <i class="fa-solid fa-floppy-disk"></i> Simpan Presensi
        </button>
      </div>

    </form>
  </div>
</div>

<!-- ======================================================== -->
<!-- 2. MODAL EDIT PRESENSI (HIDDEN POPUP MODAL)              -->
<!-- ======================================================== -->
<div id="editModalBackdrop" class="luxury-modal-backdrop" style="display: none; position: fixed; inset: 0; background: rgba(15, 23, 42, 0.75); backdrop-filter: blur(6px); -webkit-backdrop-filter: blur(6px); z-index: 99999; align-items: center; justify-content: center; padding: 1.25rem;" onclick="closeEditModal(event)">
  <div class="luxury-modal-box" style="background: #ffffff; border-radius: 1.25rem; width: 100%; max-width: 520px; box-shadow: 0 25px 60px -15px rgba(0, 0, 0, 0.45); overflow: hidden; position: relative;" onclick="event.stopPropagation()">
    
    <!-- Modal Header -->
    <div style="background: linear-gradient(135deg, #0284c7 0%, #0369a1 100%); padding: 1rem 1.25rem; color: white; display: flex; align-items: center; justify-content: space-between;">
      <div style="display: flex; align-items: center; gap: 0.5rem;">
        <div style="background: rgba(255,255,255,0.2); width: 32px; height: 32px; border-radius: 8px; display: flex; align-items: center; justify-content: center;">
          <i class="fa-solid fa-pen-to-square text-white"></i>
        </div>
        <div>
          <h3 style="font-size: 1.05rem; font-weight: 800; margin: 0; color: white; font-family: 'Outfit', sans-serif;">Edit Data Presensi Loket</h3>
          <span style="font-size: 0.7rem; color: #bae6fd;">ID Entri: #<strong id="editModalIdLabel">-</strong></span>
        </div>
      </div>
      <button type="button" onclick="closeEditModalDirect()" style="background: none; border: none; color: white; font-size: 1.25rem; cursor: pointer; padding: 0.25rem;">
        <i class="fa-solid fa-xmark"></i>
      </button>
    </div>

    <!-- Modal Form -->
    <form action="<?= BASE_URL ?>petugas/riwayat_input.php<?= !empty($_SERVER['QUERY_STRING']) ? '?' . $_SERVER['QUERY_STRING'] : '' ?>" method="POST" style="padding: 1.25rem;">
      <input type="hidden" name="action_edit_presensi" value="1">
      <input type="hidden" name="presensi_id" id="editPresensiId" value="">

      <!-- Destinasi Wisata -->
      <div class="form-group mb-3">
        <label style="display: block; font-size: 0.72rem; font-weight: 700; color: #334155; margin-bottom: 0.3rem; text-transform: uppercase; letter-spacing: 0.04em;">
          Destinasi Objek Wisata <span style="color: #ef4444;">*</span>
        </label>
        <div style="position: relative;">
          <i class="fa-solid fa-mountain-sun" style="position: absolute; left: 0.85rem; top: 50%; transform: translateY(-50%); color: #0284c7;"></i>
          <select name="destinasi_id" id="editDestinasiId" class="form-control" style="padding-left: 2.3rem; border-radius: 0.5rem; font-weight: 700; font-size: 0.85rem; height: 36px;" required>
            <?php foreach ($destinasiList as $d): ?>
              <option value="<?= $d['id'] ?>">
                <?= htmlspecialchars($d['nama_destinasi']) ?> (<?= htmlspecialchars($d['lokasi']) ?>)
              </option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>

      <!-- Tanggal Kunjungan -->
      <div class="grid grid-cols-2 gap-3 mb-3">
        <div>
          <label style="display: block; font-size: 0.72rem; font-weight: 700; color: #334155; margin-bottom: 0.3rem; text-transform: uppercase; letter-spacing: 0.04em;">
            Tanggal Kunjungan <span style="color: #ef4444;">*</span>
          </label>
          <input type="date" name="tanggal_kunjungan" id="editTanggal" class="form-control" style="border-radius: 0.5rem; font-weight: 600; font-size: 0.85rem; height: 36px;" required>
        </div>

        <div>
          <label style="display: block; font-size: 0.72rem; font-weight: 700; color: #334155; margin-bottom: 0.3rem; text-transform: uppercase; letter-spacing: 0.04em;">
            Kategori Kunjungan <span style="color: #ef4444;">*</span>
          </label>
          <select name="jenis_kunjungan" id="editJenis" class="form-control" style="border-radius: 0.5rem; font-weight: 600; font-size: 0.85rem; height: 36px;" required>
            <option value="sendiri">Sendiri / Individu</option>
            <option value="rombongan">Rombongan / Grup</option>
          </select>
        </div>
      </div>

      <!-- Jumlah Wisatawan -->
      <div class="form-group mb-3">
        <label style="display: block; font-size: 0.72rem; font-weight: 700; color: #334155; margin-bottom: 0.3rem; text-transform: uppercase; letter-spacing: 0.04em;">
          Jumlah Wisatawan Masuk <span style="color: #ef4444;">*</span>
        </label>
        <input type="number" name="jumlah_pengunjung" id="editJmlPengunjung" class="form-control" style="border-radius: 0.5rem; font-size: 1rem; font-weight: 800; color: #0284c7; height: 36px;" min="1" max="1000" required>
      </div>

      <!-- Keterangan -->
      <div class="form-group mb-4">
        <label style="display: block; font-size: 0.72rem; font-weight: 700; color: #334155; margin-bottom: 0.3rem; text-transform: uppercase; letter-spacing: 0.04em;">
          Keterangan / Catatan Loket
        </label>
        <input type="text" name="keterangan" id="editKeterangan" class="form-control" style="border-radius: 0.5rem; font-size: 0.85rem; height: 36px;">
      </div>

      <!-- Modal Footer Action -->
      <div style="display: flex; justify-content: flex-end; gap: 0.4rem; border-top: 1px solid #f1f5f9; padding-top: 0.85rem;">
        <button type="button" onclick="closeEditModalDirect()" class="btn btn-secondary btn-xs" style="font-weight: 700; border-radius: 0.4rem; padding: 0.4rem 0.85rem;">
          Batal
        </button>
        <button type="submit" class="btn btn-primary btn-xs btn-luxury-pulse" style="font-weight: 800; border-radius: 0.4rem; padding: 0.4rem 1.15rem;">
          <i class="fa-solid fa-check"></i> Simpan Perubahan
        </button>
      </div>

    </form>
  </div>
</div>

<script>
// --- Handler Modal Tambah ---
function openAddModal() {
  const modal = document.getElementById('addModalBackdrop');
  modal.style.display = 'flex';
  setTimeout(() => {
    modal.classList.add('active');
  }, 10);
  document.body.style.overflow = 'hidden';
}

function closeAddModalDirect() {
  const modal = document.getElementById('addModalBackdrop');
  modal.classList.remove('active');
  setTimeout(() => {
    modal.style.display = 'none';
  }, 200);
  document.body.style.overflow = '';
}

function closeAddModal(e) {
  if (e.target.id === 'addModalBackdrop') {
    closeAddModalDirect();
  }
}

function setAddQty(delta) {
  const input = document.getElementById('addJmlPengunjung');
  let val = parseInt(input.value) || 0;
  input.value = Math.max(1, val + delta);
}

// --- Handler Modal Edit ---
function openEditModal(row) {
  document.getElementById('editPresensiId').value = row.id;
  document.getElementById('editModalIdLabel').innerText = row.id;
  document.getElementById('editDestinasiId').value = row.destinasi_id;
  document.getElementById('editTanggal').value = row.tanggal_kunjungan;
  document.getElementById('editJenis').value = (row.jenis_kunjungan === 'rombongan') ? 'rombongan' : 'sendiri';
  document.getElementById('editJmlPengunjung').value = row.jumlah_pengunjung;
  document.getElementById('editKeterangan').value = row.keterangan || '';

  const modal = document.getElementById('editModalBackdrop');
  modal.style.display = 'flex';
  setTimeout(() => {
    modal.classList.add('active');
  }, 10);
  document.body.style.overflow = 'hidden';
}

function closeEditModalDirect() {
  const modal = document.getElementById('editModalBackdrop');
  modal.classList.remove('active');
  setTimeout(() => {
    modal.style.display = 'none';
  }, 200);
  document.body.style.overflow = '';
}

function closeEditModal(e) {
  if (e.target.id === 'editModalBackdrop') {
    closeEditModalDirect();
  }
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
