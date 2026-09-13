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

$pageTitle = "Riwayat & Rekapitulasi Presensi Loket";
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';
$flash = getFlash();
?>

<main class="admin-main">
  
  <!-- Header Topbar Luxury -->
  <div class="admin-topbar-luxury">
    <div style="display: flex; align-items: center; gap: 0.85rem; flex-wrap: wrap;">
      <div>
        <div style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.2rem;">
          <span class="badge-luxury badge-luxury-primary">
            <i class="fa-solid fa-clipboard-list"></i> Database Presensi
          </span>
          <span class="badge-luxury badge-luxury-warning" style="font-size: 0.72rem;">
            Total Akumulasi: <?= number_format($grandTotalOrang) ?> Wisatawan
          </span>
        </div>
        <h1 style="font-size: 1.45rem; font-weight: 800; color: #0f172a; margin: 0; letter-spacing: -0.02em;">
          Riwayat & Kelola Presensi Masuk
        </h1>
      </div>
    </div>
    
    <div style="display: flex; gap: 0.5rem; align-items: center;">
      <button onclick="window.print()" class="btn btn-secondary btn-sm" style="background: #ffffff; border: 1px solid #cbd5e1; font-weight: 700; border-radius: 0.65rem;">
        <i class="fa-solid fa-print"></i> Cetak Rekap Shift
      </button>
      <button type="button" onclick="openAddModal()" class="btn btn-primary btn-sm btn-luxury-pulse" style="font-weight: 800; box-shadow: 0 4px 12px rgba(13, 148, 136, 0.35); border-radius: 0.65rem; padding: 0.55rem 1.15rem;">
        <i class="fa-solid fa-plus"></i> + Tambah Presensi
      </button>
    </div>
  </div>

  <!-- Flash Notification -->
  <?php if ($flash): ?>
    <div class="alert alert-<?= $flash['type'] ?> mb-4" style="border-radius: 0.85rem; padding: 0.9rem 1.25rem; display: flex; align-items: center; gap: 0.75rem; font-size: 0.88rem; box-shadow: 0 4px 12px rgba(0,0,0,0.03);">
      <i class="fa-solid <?= $flash['type'] === 'success' ? 'fa-circle-check' : 'fa-circle-exclamation' ?>" style="font-size: 1.25rem;"></i>
      <div><?= $flash['message'] ?></div>
    </div>
  <?php endif; ?>

  <!-- Summary 4 KPI Cards Grid -->
  <div class="grid grid-cols-4 gap-4 mb-4">
    
    <!-- KPI 1 -->
    <div class="kpi-card-luxury">
      <div style="display: flex; align-items: center; justify-content: space-between;">
        <div>
          <span style="font-size: 0.7rem; font-weight: 800; text-transform: uppercase; color: #64748b; letter-spacing: 0.04em; display: block;">Total Terfilter</span>
          <div style="font-size: 1.55rem; font-weight: 900; color: #0284c7; line-height: 1.1; margin-top: 0.15rem; font-family: 'Outfit', sans-serif;">
            <?= number_format($totalOrang) ?> <span style="font-size: 0.85rem; font-weight: 600; color: #64748b;">Org</span>
          </div>
        </div>
        <div class="kpi-icon-wrap" style="background: #e0f2fe; color: #0284c7;">
          <i class="fa-solid fa-users"></i>
        </div>
      </div>
    </div>

    <!-- KPI 2 -->
    <div class="kpi-card-luxury">
      <div style="display: flex; align-items: center; justify-content: space-between;">
        <div>
          <span style="font-size: 0.7rem; font-weight: 800; text-transform: uppercase; color: #64748b; letter-spacing: 0.04em; display: block;">Shift Hari Ini</span>
          <div style="font-size: 1.55rem; font-weight: 900; color: #0d9488; line-height: 1.1; margin-top: 0.15rem; font-family: 'Outfit', sans-serif;">
            <?= number_format($todayTotalOrang) ?> <span style="font-size: 0.85rem; font-weight: 600; color: #64748b;">Org</span>
          </div>
        </div>
        <div class="kpi-icon-wrap" style="background: #ccfbf1; color: #0d9488;">
          <i class="fa-solid fa-calendar-day"></i>
        </div>
      </div>
    </div>

    <!-- KPI 3 -->
    <div class="kpi-card-luxury">
      <div style="display: flex; align-items: center; justify-content: space-between;">
        <div>
          <span style="font-size: 0.7rem; font-weight: 800; text-transform: uppercase; color: #64748b; letter-spacing: 0.04em; display: block;">Rombongan / Tour</span>
          <div style="font-size: 1.55rem; font-weight: 900; color: #d97706; line-height: 1.1; margin-top: 0.15rem; font-family: 'Outfit', sans-serif;">
            <?= number_format($totalRombongan) ?> <span style="font-size: 0.85rem; font-weight: 600; color: #64748b;">Org</span>
          </div>
        </div>
        <div class="kpi-icon-wrap" style="background: #fef3c7; color: #d97706;">
          <i class="fa-solid fa-people-group"></i>
        </div>
      </div>
    </div>

    <!-- KPI 4 -->
    <div class="kpi-card-luxury">
      <div style="display: flex; align-items: center; justify-content: space-between;">
        <div>
          <span style="font-size: 0.7rem; font-weight: 800; text-transform: uppercase; color: #64748b; letter-spacing: 0.04em; display: block;">Individu / Sendiri</span>
          <div style="font-size: 1.55rem; font-weight: 900; color: #334155; line-height: 1.1; margin-top: 0.15rem; font-family: 'Outfit', sans-serif;">
            <?= number_format($totalIndividu) ?> <span style="font-size: 0.85rem; font-weight: 600; color: #64748b;">Org</span>
          </div>
        </div>
        <div class="kpi-icon-wrap" style="background: #f1f5f9; color: #475569;">
          <i class="fa-solid fa-user"></i>
        </div>
      </div>
    </div>

  </div>

  <!-- Filter & Preset Bar -->
  <div class="card p-3 bg-white shadow-sm mb-4" style="border-radius: 0.95rem; border: 1px solid #e2e8f0;">
    <form action="<?= BASE_URL ?>petugas/riwayat_input.php" method="GET" style="display: flex; gap: 0.45rem; align-items: center; flex-wrap: wrap;">
      
      <!-- Preset Buttons -->
      <div style="display: flex; gap: 0.25rem;">
        <a href="<?= BASE_URL ?>petugas/riwayat_input.php" class="btn btn-xs <?= (empty($filterTgl) && empty($filterPreset) && $filterDestinasi == 0) ? 'btn-primary' : 'btn-secondary' ?>" style="font-weight: 700; border-radius: 0.45rem; padding: 0.35rem 0.65rem; font-size: 0.78rem;">
          Semua
        </a>
        <a href="<?= BASE_URL ?>petugas/riwayat_input.php?preset=today" class="btn btn-xs <?= ($filterPreset === 'today' || $filterTgl === date('Y-m-d')) ? 'btn-primary' : 'btn-secondary' ?>" style="font-weight: 700; border-radius: 0.45rem; padding: 0.35rem 0.65rem; font-size: 0.78rem;">
          Hari Ini
        </a>
        <a href="<?= BASE_URL ?>petugas/riwayat_input.php?preset=yesterday" class="btn btn-xs <?= ($filterPreset === 'yesterday') ? 'btn-primary' : 'btn-secondary' ?>" style="font-weight: 700; border-radius: 0.45rem; padding: 0.35rem 0.65rem; font-size: 0.78rem;">
          Kemarin
        </a>
        <a href="<?= BASE_URL ?>petugas/riwayat_input.php?preset=this_month" class="btn btn-xs <?= ($filterPreset === 'this_month') ? 'btn-primary' : 'btn-secondary' ?>" style="font-weight: 700; border-radius: 0.45rem; padding: 0.35rem 0.65rem; font-size: 0.78rem;">
          Bulan Ini
        </a>
      </div>

      <div style="height: 20px; width: 1px; background: #e2e8f0; margin: 0 0.25rem;"></div>

      <!-- Destinasi Dropdown Filter -->
      <select name="destinasi_id" class="form-control" style="width: auto; min-width: 170px; font-size: 0.8rem; padding: 0.3rem 0.6rem; border-radius: 0.5rem; height: 34px; font-weight: 600;">
        <option value="0">-- Semua Destinasi --</option>
        <?php foreach ($destinasiList as $d): ?>
          <option value="<?= $d['id'] ?>" <?= $filterDestinasi == $d['id'] ? 'selected' : '' ?>>
            <?= htmlspecialchars($d['nama_destinasi']) ?>
          </option>
        <?php endforeach; ?>
      </select>

      <!-- Tanggal Kustom Input -->
      <input type="date" name="tgl" value="<?= htmlspecialchars($filterTgl) ?>" class="form-control" style="width: auto; font-size: 0.8rem; padding: 0.3rem 0.6rem; border-radius: 0.5rem; height: 34px; font-weight: 600;" title="Filter Tanggal">

      <!-- Keyword Search -->
      <div style="position: relative; flex: 1; min-width: 160px;">
        <i class="fa-solid fa-magnifying-glass" style="position: absolute; left: 0.75rem; top: 50%; transform: translateY(-50%); color: #94a3b8; font-size: 0.78rem;"></i>
        <input type="text" name="search" value="<?= htmlspecialchars($filterSearch) ?>" placeholder="Cari keterangan..." class="form-control" style="padding-left: 2rem; font-size: 0.8rem; border-radius: 0.5rem; height: 34px;">
      </div>

      <button type="submit" class="btn btn-primary btn-sm" style="height: 34px; border-radius: 0.5rem; padding: 0 0.9rem; font-weight: 700; font-size: 0.8rem;">
        <i class="fa-solid fa-filter"></i> Saring
      </button>

      <?php if (!empty($filterTgl) || $filterDestinasi > 0 || !empty($filterPreset) || !empty($filterSearch)): ?>
        <a href="<?= BASE_URL ?>petugas/riwayat_input.php" class="btn btn-secondary btn-sm" style="height: 34px; border-radius: 0.5rem; padding: 0 0.75rem; display: inline-flex; align-items: center;" title="Reset Filter">
          <i class="fa-solid fa-rotate-left"></i>
        </a>
      <?php endif; ?>

    </form>
  </div>

  <!-- Luxury Interactive Data Table -->
  <div class="card-table-luxury">
    <div style="padding: 1.15rem 1.5rem; border-bottom: 1px solid #e2e8f0; display: flex; align-items: center; justify-content: space-between; background: #ffffff;">
      <h3 style="font-size: 1.05rem; font-weight: 800; color: #0f172a; margin: 0; display: flex; align-items: center; gap: 0.4rem;">
        <i class="fa-solid fa-list-check text-teal-600"></i> Rekapitulasi Presensi Masuk (<?= count($riwayat) ?> Entri)
      </h3>
      <span class="badge-luxury badge-luxury-success" style="font-size: 0.72rem;">
        <span style="width:5px;height:5px;border-radius:50%;background:#22c55e;display:inline-block;"></span> Data Terverifikasi
      </span>
    </div>

    <?php if (empty($riwayat)): ?>
      <div style="text-align: center; padding: 3rem 1.5rem; color: #94a3b8; background: #ffffff;">
        <i class="fa-solid fa-folder-open" style="font-size: 2.5rem; margin-bottom: 0.75rem; display: block; color: #cbd5e1;"></i>
        <h4 style="font-size: 1.05rem; font-weight: 800; color: #334155; margin: 0 0 0.25rem 0;">Tidak Ada Data Presensi</h4>
        <p style="font-size: 0.85rem; color: #64748b; margin: 0 0 1rem 0;">Belum ada entri presensi yang sesuai dengan parameter filter yang dipilih.</p>
        <button type="button" onclick="openAddModal()" class="btn btn-primary btn-sm btn-luxury-pulse" style="font-weight: 800;">
          <i class="fa-solid fa-plus"></i> Tambah Data Sekarang
        </button>
      </div>
    <?php else: ?>
      <div class="overflow-x-auto" style="background: #ffffff;">
        <table class="table-luxury">
          <thead>
            <tr>
              <th style="width: 50px;">#ID</th>
              <th>Destinasi Objek Wisata</th>
              <th>Jadwal Kunjungan</th>
              <th style="text-align: center;">Wisatawan Masuk</th>
              <th>Kategori</th>
              <th>Catatan Loket</th>
              <th>Waktu Input</th>
              <th style="text-align: right; width: 130px;">Aksi</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($riwayat as $row): 
              $isRombongan = ($row['jenis_kunjungan'] === 'rombongan');
            ?>
              <tr>
                <td>
                  <span style="font-size: 0.75rem; font-weight: 800; color: #94a3b8; font-family: monospace;">
                    #<?= $row['id'] ?>
                  </span>
                </td>

                <td>
                  <strong class="text-dark" style="font-size: 0.9rem; display: block; color: #0f172a;">
                    <?= htmlspecialchars($row['nama_destinasi']) ?>
                  </strong>
                  <span style="font-size: 0.75rem; color: #64748b;">
                    <i class="fa-solid fa-location-dot" style="color: #ef4444; font-size: 0.7rem;"></i> <?= htmlspecialchars($row['lokasi']) ?>
                  </span>
                </td>

                <td>
                  <strong style="font-size: 0.85rem; color: #0f172a; display: block;">
                    <?= formatTanggalIndo($row['tanggal_kunjungan']) ?>
                  </strong>
                  <span style="font-size: 0.72rem; color: #0284c7; font-weight: 700;">
                    Hari <?= htmlspecialchars($row['hari']) ?>
                  </span>
                </td>

                <td style="text-align: center;">
                  <strong style="font-size: 1.05rem; color: #0d9488; font-weight: 900; font-family: 'Outfit', sans-serif;">
                    <?= number_format($row['jumlah_pengunjung']) ?>
                  </strong>
                  <span style="font-size: 0.75rem; font-weight: 600; color: #64748b;"> Orang</span>
                </td>

                <td>
                  <?php if ($isRombongan): ?>
                    <span class="badge-luxury badge-luxury-warning" style="font-size: 0.7rem;">
                      <i class="fa-solid fa-people-group"></i> Rombongan
                    </span>
                  <?php else: ?>
                    <span class="badge-luxury badge-luxury-primary" style="font-size: 0.7rem;">
                      <i class="fa-solid fa-user"></i> Sendiri
                    </span>
                  <?php endif; ?>
                </td>

                <td>
                  <span style="font-size: 0.8rem; color: #475569; display: block; max-width: 220px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;" title="<?= htmlspecialchars($row['keterangan'] ?? '-') ?>">
                    <?= htmlspecialchars($row['keterangan'] ?? '-') ?>
                  </span>
                </td>

                <td>
                  <span style="font-size: 0.75rem; color: #64748b; display: block;">
                    <?= date('d/m/Y', strtotime($row['created_at'])) ?>
                  </span>
                  <span style="font-size: 0.7rem; color: #94a3b8; font-weight: 600;">
                    <?= date('H:i', strtotime($row['created_at'])) ?> WIB
                  </span>
                </td>

                <td style="text-align: right;">
                  <div style="display: flex; gap: 0.35rem; justify-content: flex-end;">
                    
                    <!-- Tombol Edit Modal -->
                    <button type="button" 
                            class="btn btn-secondary btn-xs" 
                            style="padding: 0.25rem 0.55rem; font-size: 0.75rem; font-weight: 700; border-radius: 0.45rem; background: #ffffff; border: 1px solid #cbd5e1; color: #0284c7;"
                            onclick="openEditModal(<?= htmlspecialchars(json_encode($row)) ?>)"
                            title="Edit Data Presensi">
                      <i class="fa-solid fa-pen-to-square"></i> Edit
                    </button>

                    <!-- Form Hapus -->
                    <form action="<?= BASE_URL ?>petugas/riwayat_input.php<?= !empty($_SERVER['QUERY_STRING']) ? '?' . $_SERVER['QUERY_STRING'] : '' ?>" 
                          method="POST" 
                          onsubmit="return confirm('Hapus entri presensi #<?= $row['id'] ?> (<?= $row['jumlah_pengunjung'] ?> orang)?')" 
                          style="display: inline;">
                      <input type="hidden" name="action_delete_presensi" value="1">
                      <input type="hidden" name="presensi_id" value="<?= $row['id'] ?>">
                      <button type="submit" class="btn btn-danger btn-xs" style="padding: 0.25rem 0.55rem; font-size: 0.75rem; border-radius: 0.45rem;" title="Hapus Presensi">
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
<div id="addModalBackdrop" class="luxury-modal-backdrop" onclick="closeAddModal(event)">
  <div class="luxury-modal-box" onclick="event.stopPropagation()">
    
    <!-- Modal Header -->
    <div style="background: linear-gradient(135deg, #0d9488 0%, #0284c7 100%); padding: 1.15rem 1.5rem; color: white; display: flex; align-items: center; justify-content: space-between;">
      <div style="display: flex; align-items: center; gap: 0.6rem;">
        <div style="background: rgba(255,255,255,0.2); width: 36px; height: 36px; border-radius: 0.65rem; display: flex; align-items: center; justify-content: center;">
          <i class="fa-solid fa-plus text-white"></i>
        </div>
        <div>
          <h3 style="font-size: 1.15rem; font-weight: 800; margin: 0; color: white; font-family: 'Outfit', sans-serif;">Tambah Presensi Loket Baru</h3>
          <span style="font-size: 0.72rem; color: #ccfbf1;">Catat wisatawan yang masuk gerbang</span>
        </div>
      </div>
      <button type="button" onclick="closeAddModalDirect()" style="background: none; border: none; color: white; font-size: 1.35rem; cursor: pointer; padding: 0.25rem;">
        <i class="fa-solid fa-xmark"></i>
      </button>
    </div>

    <!-- Modal Form -->
    <form action="<?= BASE_URL ?>petugas/riwayat_input.php<?= !empty($_SERVER['QUERY_STRING']) ? '?' . $_SERVER['QUERY_STRING'] : '' ?>" method="POST" style="padding: 1.5rem;">
      <input type="hidden" name="action_add_presensi" value="1">

      <!-- Destinasi Wisata -->
      <div class="form-group mb-4">
        <label style="display: block; font-size: 0.75rem; font-weight: 700; color: #334155; margin-bottom: 0.35rem; text-transform: uppercase; letter-spacing: 0.04em;">
          Destinasi Objek Wisata <span style="color: #ef4444;">*</span>
        </label>
        <div style="position: relative;">
          <i class="fa-solid fa-mountain-sun" style="position: absolute; left: 0.95rem; top: 50%; transform: translateY(-50%); color: #0d9488;"></i>
          <select name="destinasi_id" class="form-control" style="padding-left: 2.5rem; border-radius: 0.65rem; font-weight: 700; font-size: 0.88rem; height: 40px;" required>
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
      <div class="grid grid-cols-2 gap-3 mb-4">
        <div>
          <label style="display: block; font-size: 0.75rem; font-weight: 700; color: #334155; margin-bottom: 0.35rem; text-transform: uppercase; letter-spacing: 0.04em;">
            Tanggal Kunjungan <span style="color: #ef4444;">*</span>
          </label>
          <input type="date" name="tanggal_kunjungan" class="form-control" style="border-radius: 0.65rem; font-weight: 600; font-size: 0.88rem; height: 40px;" value="<?= date('Y-m-d') ?>" required>
        </div>

        <div>
          <label style="display: block; font-size: 0.75rem; font-weight: 700; color: #334155; margin-bottom: 0.35rem; text-transform: uppercase; letter-spacing: 0.04em;">
            Kategori Kunjungan <span style="color: #ef4444;">*</span>
          </label>
          <select name="jenis_kunjungan" class="form-control" style="border-radius: 0.65rem; font-weight: 600; font-size: 0.88rem; height: 40px;" required>
            <option value="sendiri">Sendiri / Individu</option>
            <option value="rombongan">Rombongan / Grup</option>
          </select>
        </div>
      </div>

      <!-- Jumlah Pengunjung -->
      <div class="form-group mb-4">
        <label style="display: block; font-size: 0.75rem; font-weight: 700; color: #334155; margin-bottom: 0.35rem; text-transform: uppercase; letter-spacing: 0.04em;">
          Jumlah Wisatawan Masuk <span style="color: #ef4444;">*</span>
        </label>
        <div style="position: relative;">
          <i class="fa-solid fa-users" style="position: absolute; left: 0.95rem; top: 50%; transform: translateY(-50%); color: #0d9488;"></i>
          <input type="number" name="jumlah_pengunjung" class="form-control" style="padding-left: 2.5rem; font-size: 1.15rem; font-weight: 800; font-family: 'Outfit', sans-serif; border-radius: 0.65rem; height: 44px;" value="1" min="1" required>
        </div>
      </div>

      <!-- Keterangan / Catatan -->
      <div class="form-group mb-5">
        <label style="display: block; font-size: 0.75rem; font-weight: 700; color: #334155; margin-bottom: 0.35rem; text-transform: uppercase; letter-spacing: 0.04em;">
          Catatan Loket (Opsional)
        </label>
        <input type="text" name="keterangan" class="form-control" placeholder="Cth: Pembelian tunai loket..." style="border-radius: 0.65rem; font-size: 0.85rem; height: 40px;">
      </div>

      <!-- Modal Action Buttons -->
      <div style="display: flex; gap: 0.5rem; justify-content: flex-end;">
        <button type="button" onclick="closeAddModalDirect()" class="btn btn-secondary" style="border-radius: 0.65rem; font-weight: 700; padding: 0.6rem 1.25rem;">
          Batal
        </button>
        <button type="submit" class="btn btn-primary btn-luxury-pulse" style="border-radius: 0.65rem; font-weight: 800; padding: 0.6rem 1.5rem;">
          <i class="fa-solid fa-check"></i> Simpan Presensi
        </button>
      </div>
    </form>

  </div>
</div>

<!-- ======================================================== -->
<!-- 2. MODAL EDIT PRESENSI (HIDDEN POPUP MODAL)              -->
<!-- ======================================================== -->
<div id="editModalBackdrop" class="luxury-modal-backdrop" onclick="closeEditModal(event)">
  <div class="luxury-modal-box" onclick="event.stopPropagation()">
    
    <!-- Modal Header -->
    <div style="background: linear-gradient(135deg, #0284c7 0%, #0369a1 100%); padding: 1.15rem 1.5rem; color: white; display: flex; align-items: center; justify-content: space-between;">
      <div style="display: flex; align-items: center; gap: 0.6rem;">
        <div style="background: rgba(255,255,255,0.2); width: 36px; height: 36px; border-radius: 0.65rem; display: flex; align-items: center; justify-content: center;">
          <i class="fa-solid fa-pen-to-square text-white"></i>
        </div>
        <div>
          <h3 style="font-size: 1.15rem; font-weight: 800; margin: 0; color: white; font-family: 'Outfit', sans-serif;">Edit Data Presensi Loket</h3>
          <span style="font-size: 0.72rem; color: #bae6fd;">Perbarui detail kunjungan wisata</span>
        </div>
      </div>
      <button type="button" onclick="closeEditModalDirect()" style="background: none; border: none; color: white; font-size: 1.35rem; cursor: pointer; padding: 0.25rem;">
        <i class="fa-solid fa-xmark"></i>
      </button>
    </div>

    <!-- Modal Form -->
    <form action="<?= BASE_URL ?>petugas/riwayat_input.php<?= !empty($_SERVER['QUERY_STRING']) ? '?' . $_SERVER['QUERY_STRING'] : '' ?>" method="POST" style="padding: 1.5rem;">
      <input type="hidden" name="action_edit_presensi" value="1">
      <input type="hidden" name="presensi_id" id="editPresensiId" value="">

      <!-- Destinasi Wisata -->
      <div class="form-group mb-4">
        <label style="display: block; font-size: 0.75rem; font-weight: 700; color: #334155; margin-bottom: 0.35rem; text-transform: uppercase; letter-spacing: 0.04em;">
          Destinasi Objek Wisata <span style="color: #ef4444;">*</span>
        </label>
        <div style="position: relative;">
          <i class="fa-solid fa-mountain-sun" style="position: absolute; left: 0.95rem; top: 50%; transform: translateY(-50%); color: #0284c7;"></i>
          <select name="destinasi_id" id="editDestinasiId" class="form-control" style="padding-left: 2.5rem; border-radius: 0.65rem; font-weight: 700; font-size: 0.88rem; height: 40px;" required>
            <?php foreach ($destinasiList as $d): ?>
              <option value="<?= $d['id'] ?>">
                <?= htmlspecialchars($d['nama_destinasi']) ?> (<?= htmlspecialchars($d['lokasi']) ?>)
              </option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>

      <!-- Tanggal Kunjungan -->
      <div class="grid grid-cols-2 gap-3 mb-4">
        <div>
          <label style="display: block; font-size: 0.75rem; font-weight: 700; color: #334155; margin-bottom: 0.35rem; text-transform: uppercase; letter-spacing: 0.04em;">
            Tanggal Kunjungan <span style="color: #ef4444;">*</span>
          </label>
          <input type="date" name="tanggal_kunjungan" id="editTanggal" class="form-control" style="border-radius: 0.65rem; font-weight: 600; font-size: 0.88rem; height: 40px;" required>
        </div>

        <div>
          <label style="display: block; font-size: 0.75rem; font-weight: 700; color: #334155; margin-bottom: 0.35rem; text-transform: uppercase; letter-spacing: 0.04em;">
            Kategori Kunjungan <span style="color: #ef4444;">*</span>
          </label>
          <select name="jenis_kunjungan" id="editJenis" class="form-control" style="border-radius: 0.65rem; font-weight: 600; font-size: 0.88rem; height: 40px;" required>
            <option value="sendiri">Sendiri / Individu</option>
            <option value="rombongan">Rombongan / Grup</option>
          </select>
        </div>
      </div>

      <!-- Jumlah Pengunjung -->
      <div class="form-group mb-4">
        <label style="display: block; font-size: 0.75rem; font-weight: 700; color: #334155; margin-bottom: 0.35rem; text-transform: uppercase; letter-spacing: 0.04em;">
          Jumlah Wisatawan Masuk <span style="color: #ef4444;">*</span>
        </label>
        <div style="position: relative;">
          <i class="fa-solid fa-users" style="position: absolute; left: 0.95rem; top: 50%; transform: translateY(-50%); color: #0284c7;"></i>
          <input type="number" name="jumlah_pengunjung" id="editJumlah" class="form-control" style="padding-left: 2.5rem; font-size: 1.15rem; font-weight: 800; font-family: 'Outfit', sans-serif; border-radius: 0.65rem; height: 44px;" min="1" required>
        </div>
      </div>

      <!-- Keterangan / Catatan -->
      <div class="form-group mb-5">
        <label style="display: block; font-size: 0.75rem; font-weight: 700; color: #334155; margin-bottom: 0.35rem; text-transform: uppercase; letter-spacing: 0.04em;">
          Catatan Loket (Opsional)
        </label>
        <input type="text" name="keterangan" id="editKeterangan" class="form-control" placeholder="Cth: Pembelian tunai loket..." style="border-radius: 0.65rem; font-size: 0.85rem; height: 40px;">
      </div>

      <!-- Modal Action Buttons -->
      <div style="display: flex; gap: 0.5rem; justify-content: flex-end;">
        <button type="button" onclick="closeEditModalDirect()" class="btn btn-secondary" style="border-radius: 0.65rem; font-weight: 700; padding: 0.6rem 1.25rem;">
          Batal
        </button>
        <button type="submit" class="btn btn-primary btn-luxury-pulse" style="background: linear-gradient(135deg, #0284c7 0%, #0369a1 100%); border-radius: 0.65rem; font-weight: 800; padding: 0.6rem 1.5rem;">
          <i class="fa-solid fa-check"></i> Perbarui Data
        </button>
      </div>
    </form>

  </div>
</div>

<script>
// Modal Functions
function openAddModal() {
  const backdrop = document.getElementById('addModalBackdrop');
  backdrop.classList.add('active');
}

function closeAddModal(e) {
  if (e.target.id === 'addModalBackdrop') {
    closeAddModalDirect();
  }
}

function closeAddModalDirect() {
  const backdrop = document.getElementById('addModalBackdrop');
  backdrop.classList.remove('active');
}

function openEditModal(data) {
  document.getElementById('editPresensiId').value = data.id;
  document.getElementById('editDestinasiId').value = data.destinasi_id;
  document.getElementById('editTanggal').value = data.tanggal_kunjungan;
  document.getElementById('editJenis').value = data.jenis_kunjungan === 'rombongan' ? 'rombongan' : 'sendiri';
  document.getElementById('editJumlah').value = data.jumlah_pengunjung;
  document.getElementById('editKeterangan').value = data.keterangan || '';

  const backdrop = document.getElementById('editModalBackdrop');
  backdrop.classList.add('active');
}

function closeEditModal(e) {
  if (e.target.id === 'editModalBackdrop') {
    closeEditModalDirect();
  }
}

function closeEditModalDirect() {
  const backdrop = document.getElementById('editModalBackdrop');
  backdrop.classList.remove('active');
}

// ESC Key listener
document.addEventListener('keydown', function(e) {
  if (e.key === 'Escape') {
    closeAddModalDirect();
    closeEditModalDirect();
  }
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
