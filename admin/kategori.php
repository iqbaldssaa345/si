<?php
require_once __DIR__ . '/../config/database.php';
checkAuth('admin');

$action = $_GET['action'] ?? 'list';
$id = (int)($_GET['id'] ?? 0);
$filterStatus = $_GET['status'] ?? 'all'; // all, with_dest, empty_dest
$search = trim($_GET['q'] ?? '');
$msg = '';
$msgType = '';
$showForm = ($id > 0 || isset($_GET['show_form']));

// ==========================================
// 1. ACTION: HAPUS KATEGORI
// ==========================================
if ($action === 'delete' && $id > 0) {
    // Cek nama kategori
    $stmtCek = $pdo->prepare("SELECT nama_kategori FROM kategori_wisata WHERE id = ?");
    $stmtCek->execute([$id]);
    $katInfo = $stmtCek->fetch();
    $katName = $katInfo ? $katInfo['nama_kategori'] : 'Kategori';

    // Cek apakah ada destinasi terkait
    $cek = $pdo->prepare("SELECT COUNT(*) FROM destinasi WHERE kategori_id = ?");
    $cek->execute([$id]);
    $countDest = (int)$cek->fetchColumn();

    if ($countDest > 0) {
        setFlash('danger', 'Kategori <strong>' . htmlspecialchars($katName) . '</strong> tidak dapat dihapus karena masih digunakan oleh ' . $countDest . ' destinasi wisata aktif. Pindahkan atau hapus destinasi tersebut terlebih dahulu.');
    } else {
        $del = $pdo->prepare("DELETE FROM kategori_wisata WHERE id = ?");
        if ($del->execute([$id])) {
            setFlash('success', 'Kategori <strong>' . htmlspecialchars($katName) . '</strong> berhasil dihapus.');
        } else {
            setFlash('danger', 'Gagal menghapus kategori wisata.');
        }
    }
    header("Location: " . BASE_URL . "admin/kategori.php");
    exit;
}

// ==========================================
// 2. ACTION: TAMBAH / EDIT KATEGORI (POST)
// ==========================================
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $nama_kategori = trim($_POST['nama_kategori'] ?? '');
    $slug = trim($_POST['slug'] ?? '');
    if (empty($slug)) {
        $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $nama_kategori), '-'));
    } else {
        $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $slug), '-'));
    }
    $icon = trim($_POST['icon'] ?? 'fa-tree');
    $deskripsi = trim($_POST['deskripsi'] ?? '');

    if (empty($nama_kategori)) {
        $msg = "Nama kategori wajib diisi.";
        $msgType = "danger";
        $showForm = true;
    } else {
        // Cek duplikasi slug
        if ($id > 0) {
            $cekSlug = $pdo->prepare("SELECT id FROM kategori_wisata WHERE slug = ? AND id != ? LIMIT 1");
            $cekSlug->execute([$slug, $id]);
        } else {
            $cekSlug = $pdo->prepare("SELECT id FROM kategori_wisata WHERE slug = ? LIMIT 1");
            $cekSlug->execute([$slug]);
        }

        if ($cekSlug->fetch()) {
            $slug = $slug . '-' . time();
        }

        if ($id > 0) {
            $upd = $pdo->prepare("UPDATE kategori_wisata SET nama_kategori = ?, slug = ?, icon = ?, deskripsi = ? WHERE id = ?");
            $upd->execute([$nama_kategori, $slug, $icon, $deskripsi, $id]);
            setFlash('success', 'Kategori <strong>' . htmlspecialchars($nama_kategori) . '</strong> berhasil diperbarui!');
        } else {
            $ins = $pdo->prepare("INSERT INTO kategori_wisata (nama_kategori, slug, icon, deskripsi) VALUES (?, ?, ?, ?)");
            $ins->execute([$nama_kategori, $slug, $icon, $deskripsi]);
            setFlash('success', 'Kategori baru <strong>' . htmlspecialchars($nama_kategori) . '</strong> berhasil ditambahkan!');
        }
        header("Location: " . BASE_URL . "admin/kategori.php");
        exit;
    }
}

// ==========================================
// 3. AMBIL DATA UNTUK EDIT
// ==========================================
$kategoriData = null;
if ($id > 0) {
    $stmt = $pdo->prepare("SELECT * FROM kategori_wisata WHERE id = ?");
    $stmt->execute([$id]);
    $kategoriData = $stmt->fetch();
    if (!$kategoriData) {
        setFlash('danger', 'Data kategori tidak ditemukan.');
        header("Location: " . BASE_URL . "admin/kategori.php");
        exit;
    }
}

// ==========================================
// 4. HITUNG STATISTIK & KPI
// ==========================================
$totalKategori = (int)$pdo->query("SELECT COUNT(*) FROM kategori_wisata")->fetchColumn();
$totalDestinasi = (int)$pdo->query("SELECT COUNT(*) FROM destinasi")->fetchColumn();

// Kategori Terpopuler (paling banyak destinasi)
$topKategori = $pdo->query("SELECT k.nama_kategori, COUNT(d.id) as cnt FROM kategori_wisata k LEFT JOIN destinasi d ON k.id = d.kategori_id GROUP BY k.id ORDER BY cnt DESC, k.nama_kategori ASC LIMIT 1")->fetch();

// Kategori tanpa destinasi
$emptyKategoriCount = (int)$pdo->query("SELECT COUNT(*) FROM kategori_wisata k LEFT JOIN destinasi d ON k.id = d.kategori_id WHERE d.id IS NULL")->fetchColumn();

// ==========================================
// 5. QUERY DAFTAR KATEGORI
// ==========================================
$sql = "SELECT k.*, COUNT(d.id) as total_destinasi, 
        GROUP_CONCAT(d.nama_destinasi SEPARATOR '||') as daftar_wisata 
        FROM kategori_wisata k 
        LEFT JOIN destinasi d ON k.id = d.kategori_id 
        WHERE 1=1";
$params = [];

if (!empty($search)) {
    $sql .= " AND (k.nama_kategori LIKE ? OR k.slug LIKE ? OR k.deskripsi LIKE ?)";
    $sw = "%" . $search . "%";
    $params[] = $sw;
    $params[] = $sw;
    $params[] = $sw;
}

$sql .= " GROUP BY k.id";

if ($filterStatus === 'with_dest') {
    $sql .= " HAVING total_destinasi > 0";
} elseif ($filterStatus === 'empty_dest') {
    $sql .= " HAVING total_destinasi = 0";
}

$sql .= " ORDER BY k.nama_kategori ASC";

$stmtKat = $pdo->prepare($sql);
$stmtKat->execute($params);
$kategoriList = $stmtKat->fetchAll();

$pageTitle = "Kelola Kategori Wisata";
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';
$flash = getFlash();

// Preset Icons & Emote untuk Wisata
$iconPresets = [
    ['icon' => '🎡', 'label' => '🎡 Wahana & Hiburan'],
    ['icon' => '🎢', 'label' => '🎢 Roller Coaster'],
    ['icon' => '🏔️', 'label' => '🏔️ Pegunungan & Alam'],
    ['icon' => '🏖️', 'label' => '🏖️ Pantai & Bahari'],
    ['icon' => '🏛️', 'label' => '🏛️ Sejarah & Candi'],
    ['icon' => '🦁', 'label' => '🦁 Satwa & Safari'],
    ['icon' => '🌿', 'label' => '🌿 Edukasi & Konservasi'],
    ['icon' => '🌊', 'label' => '🌊 Air Terjun & Danau'],
    ['icon' => '🏕️', 'label' => '🏕️ Camping Ground'],
    ['icon' => '🎭', 'label' => '🎭 Seni & Budaya'],
    ['icon' => 'fa-mountain-sun', 'label' => 'FA Gunung & Alam'],
    ['icon' => 'fa-umbrella-beach', 'label' => 'FA Pantai & Bahari'],
    ['icon' => 'fa-landmark', 'label' => 'FA Candi & Sejarah'],
    ['icon' => 'fa-archway', 'label' => 'FA Taman Hiburan'],
    ['icon' => 'fa-tree', 'label' => 'FA Hutan Lindung'],
    ['icon' => 'fa-masks-theater', 'label' => 'FA Seni & Teater']
];
?>

<main class="admin-main">
  
  <!-- Topbar Luxury -->
  <div class="admin-topbar-luxury">
    <div>
      <div style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.25rem;">
        <span class="badge-luxury badge-luxury-primary">
          <i class="fa-solid fa-shapes"></i> Master Kategori Wisata
        </span>
        <span style="font-size: 0.8rem; color: #64748b;">
          Total <?= $totalKategori ?> Kategori Terdaftar
        </span>
      </div>
      <h1 style="font-size: 1.6rem; font-weight: 800; color: #0f172a; margin: 0; letter-spacing: -0.02em;">
        Kategori Destinasi Wisata
      </h1>
      <p style="font-size: 0.85rem; color: #64748b; margin: 0.2rem 0 0 0;">
        Pengelompokan jenis objek wisata, visual icon selector, dan integrasi katalog destinasi.
      </p>
    </div>

    <div style="display: flex; align-items: center; gap: 0.6rem; flex-wrap: wrap;">
      <button type="button" onclick="toggleKatForm()" class="btn btn-primary btn-sm" style="font-weight: 700; box-shadow: 0 4px 12px rgba(13, 148, 136, 0.35); padding: 0.55rem 1.15rem;">
        <i class="fa-solid <?= $showForm ? 'fa-xmark' : 'fa-plus' ?>" id="btnKatFormIcon"></i>
        <span id="btnKatFormText"><?= $showForm ? 'Tutup Formulir' : 'Tambah Kategori Baru' ?></span>
      </button>
      <a href="<?= BASE_URL ?>admin/kategori.php" class="btn btn-secondary btn-sm" style="background: #ffffff; border: 1px solid #cbd5e1; padding: 0.55rem 0.85rem;" title="Refresh Data">
        <i class="fa-solid fa-rotate"></i>
      </a>
    </div>
  </div>

  <!-- Flash Notification -->
  <?php if ($flash): ?>
    <div class="alert alert-<?= $flash['type'] ?> mb-6" style="border-radius: 0.85rem; padding: 0.9rem 1.25rem; display: flex; align-items: center; gap: 0.75rem; box-shadow: 0 4px 12px rgba(0,0,0,0.03);">
      <i class="fa-solid <?= $flash['type'] === 'success' ? 'fa-circle-check' : 'fa-circle-exclamation' ?>" style="font-size: 1.25rem;"></i>
      <div style="font-size: 0.9rem; line-height: 1.4;"><?= $flash['message'] ?></div>
    </div>
  <?php endif; ?>

  <?php if ($msg): ?>
    <div class="alert alert-<?= $msgType ?> mb-6" style="border-radius: 0.85rem; padding: 0.9rem 1.25rem; display: flex; align-items: center; gap: 0.75rem;">
      <i class="fa-solid fa-circle-exclamation" style="font-size: 1.25rem;"></i>
      <div style="font-size: 0.9rem; line-height: 1.4;"><?= $msg ?></div>
    </div>
  <?php endif; ?>

  <!-- 4 KPI Stat Widgets -->
  <div class="grid grid-cols-4 gap-4 mb-4">
    
    <!-- Total Kategori -->
    <a href="<?= BASE_URL ?>admin/kategori.php" style="text-decoration: none; color: inherit;">
      <div class="kpi-card-luxury kpi-primary" style="cursor: pointer; border: <?= $filterStatus === 'all' ? '2px solid #0d9488' : '1px solid #e2e8f0' ?>;">
        <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 0.4rem;">
          <div class="kpi-icon-wrap kpi-icon-primary">
            <i class="fa-solid fa-layer-group"></i>
          </div>
          <span class="badge-luxury badge-luxury-primary" style="font-size: 0.68rem;">Semua Data</span>
        </div>
        <span style="font-size: 0.68rem; font-weight: 700; color: #64748b; text-transform: uppercase; letter-spacing: 0.05em; display: block;">
          Total Kategori
        </span>
        <h3 style="font-size: 1.35rem; font-weight: 900; color: #0f172a; margin: 0.15rem 0 0 0; font-family: 'Outfit', sans-serif;">
          <?= $totalKategori ?> <span style="font-size: 0.78rem; font-weight: 600; color: #64748b;">Kategori</span>
        </h3>
      </div>
    </a>

    <!-- Total Destinasi Terhubung -->
    <a href="<?= BASE_URL ?>admin/destinasi.php" style="text-decoration: none; color: inherit;">
      <div class="kpi-card-luxury kpi-info" style="cursor: pointer; border: 1px solid #e2e8f0;">
        <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 0.4rem;">
          <div class="kpi-icon-wrap kpi-icon-info">
            <i class="fa-solid fa-map-location-dot"></i>
          </div>
          <span class="badge-luxury badge-luxury-info" style="font-size: 0.68rem;">Objek Wisata</span>
        </div>
        <span style="font-size: 0.68rem; font-weight: 700; color: #64748b; text-transform: uppercase; letter-spacing: 0.05em; display: block;">
          Destinasi Terdaftar
        </span>
        <h3 style="font-size: 1.35rem; font-weight: 900; color: #0f172a; margin: 0.15rem 0 0 0; font-family: 'Outfit', sans-serif;">
          <?= $totalDestinasi ?> <span style="font-size: 0.78rem; font-weight: 600; color: #64748b;">Wisata</span>
        </h3>
      </div>
    </a>

    <!-- Kategori Populer / Terbanyak -->
    <a href="<?= BASE_URL ?>admin/kategori.php?status=with_dest" style="text-decoration: none; color: inherit;">
      <div class="kpi-card-luxury kpi-success" style="cursor: pointer; border: <?= $filterStatus === 'with_dest' ? '2px solid #059669' : '1px solid #e2e8f0' ?>;">
        <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 0.4rem;">
          <div class="kpi-icon-wrap kpi-icon-success">
            <i class="fa-solid fa-trophy"></i>
          </div>
          <span class="badge-luxury badge-luxury-success" style="font-size: 0.68rem;">Terpadat</span>
        </div>
        <span style="font-size: 0.68rem; font-weight: 700; color: #64748b; text-transform: uppercase; letter-spacing: 0.05em; display: block;">
          Kategori Terbanyak
        </span>
        <h3 style="font-size: 1.15rem; font-weight: 800; color: #0f172a; margin: 0.15rem 0 0 0; font-family: 'Outfit', sans-serif; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;" title="<?= htmlspecialchars($topKategori['nama_kategori'] ?? '-') ?>">
          <?= htmlspecialchars($topKategori['nama_kategori'] ?? '-') ?> <span style="font-size: 0.78rem; font-weight: 600; color: #059669;">(<?= $topKategori['cnt'] ?? 0 ?>)</span>
        </h3>
      </div>
    </a>

    <!-- Kategori Belum Ada Konten -->
    <a href="<?= BASE_URL ?>admin/kategori.php?status=empty_dest" style="text-decoration: none; color: inherit;">
      <div class="kpi-card-luxury kpi-warning" style="cursor: pointer; border: <?= $filterStatus === 'empty_dest' ? '2px solid #d97706' : '1px solid #e2e8f0' ?>;">
        <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 0.4rem;">
          <div class="kpi-icon-wrap kpi-icon-warning">
            <i class="fa-solid fa-folder-open"></i>
          </div>
          <span class="badge-luxury badge-luxury-warning" style="font-size: 0.68rem;">Perlu Konten</span>
        </div>
        <span style="font-size: 0.68rem; font-weight: 700; color: #64748b; text-transform: uppercase; letter-spacing: 0.05em; display: block;">
          Kategori Kosong
        </span>
        <h3 style="font-size: 1.35rem; font-weight: 900; color: #0f172a; margin: 0.15rem 0 0 0; font-family: 'Outfit', sans-serif;">
          <?= $emptyKategoriCount ?> <span style="font-size: 0.78rem; font-weight: 600; color: #64748b;">Kategori</span>
        </h3>
      </div>
    </a>

  </div>

  <!-- Form Tambah / Edit Kategori (Luxury Card with Live Preview) -->
  <div id="katFormCard" class="card p-6 shadow-sm bg-white mb-6" style="border-radius: 1.25rem; border: 2px solid <?= $id > 0 ? '#0d9488' : '#cbd5e1' ?>; display: <?= $showForm ? 'block' : 'none' ?>;">
    <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 1.5rem; padding-bottom: 0.85rem; border-bottom: 1px solid #e2e8f0;">
      <div>
        <span class="badge-luxury badge-luxury-primary" style="font-size: 0.72rem; margin-bottom: 0.25rem;">
          <i class="fa-solid <?= $id > 0 ? 'fa-pen-to-square' : 'fa-plus' ?>"></i> Form Master Data
        </span>
        <h3 class="font-bold text-dark text-lg" style="margin: 0;">
          <?= $id > 0 ? 'Edit Data Kategori Wisata: <span style="color: #0d9488;">' . htmlspecialchars($kategoriData['nama_kategori']) . '</span>' : 'Tambah Kategori Wisata Baru' ?>
        </h3>
      </div>
      <a href="<?= BASE_URL ?>admin/kategori.php" class="btn btn-secondary btn-sm" style="border-radius: 0.5rem;">
        <i class="fa-solid fa-xmark"></i> Batal
      </a>
    </div>

    <form action="<?= BASE_URL ?>admin/kategori.php<?= $id > 0 ? '?id=' . $id : '' ?>" method="POST" id="kategoriMainForm">
      <div class="grid grid-cols-12 gap-6 mb-5">
        
        <!-- Left Column: Form Inputs (8 cols) -->
        <div class="col-span-8" style="grid-column: span 8 / span 8;">
          
          <div class="grid grid-cols-2 gap-4 mb-4">
            <div class="form-group">
              <label class="form-label font-bold text-xs uppercase text-muted" style="display: flex; justify-content: space-between;">
                <span>Nama Kategori <span class="text-danger">*</span></span>
                <span style="font-weight: normal; font-size: 0.7rem; color: #94a3b8;">Cth: Wisata Bahari & Pantai</span>
              </label>
              <input type="text" name="nama_kategori" id="inputNamaKategori" class="form-control" value="<?= htmlspecialchars($kategoriData['nama_kategori'] ?? '') ?>" placeholder="Masukkan nama kategori..." required oninput="syncPreviewAndSlug()">
            </div>

            <div class="form-group">
              <label class="form-label font-bold text-xs uppercase text-muted" style="display: flex; justify-content: space-between;">
                <span>Slug URL <span style="font-weight: normal; color: #94a3b8;">(Auto-generated)</span></span>
                <a href="javascript:void(0)" onclick="regenerateSlug()" style="font-size: 0.7rem; color: #0d9488; text-decoration: none; font-weight: 600;">
                  <i class="fa-solid fa-arrows-rotate"></i> Sync Slug
                </a>
              </label>
              <input type="text" name="slug" id="inputSlug" class="form-control" value="<?= htmlspecialchars($kategoriData['slug'] ?? '') ?>" placeholder="wisata-bahari-pantai" style="font-family: monospace; font-size: 0.85rem;" oninput="updatePreview()">
            </div>
          </div>

          <div class="form-group mb-4">
            <label class="form-label font-bold text-xs uppercase text-muted" style="display: flex; justify-content: space-between; align-items: center;">
              <span>Pilih Icon FontAwesome</span>
              <span style="font-size: 0.72rem; color: #64748b;">Klik salah satu icon preset atau ketik manual</span>
            </label>
            
            <div class="grid grid-cols-4 gap-2 mb-3" style="max-height: 140px; overflow-y: auto; padding: 0.5rem; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 0.75rem;">
              <?php foreach ($iconPresets as $preset): ?>
                <button type="button" class="btn-preset-icon <?= (($kategoriData['icon'] ?? 'fa-mountain-sun') === $preset['icon']) ? 'active' : '' ?>" onclick="selectPresetIcon('<?= $preset['icon'] ?>')" style="display: flex; align-items: center; gap: 0.45rem; padding: 0.4rem 0.55rem; border: 1px solid #e2e8f0; border-radius: 0.5rem; background: #ffffff; cursor: pointer; text-align: left; transition: all 0.2s; font-size: 0.75rem;">
                  <span style="font-size: 1.05rem; width: 22px; text-align: center; display: inline-flex; align-items: center; justify-content: center;"><?= renderKategoriIcon($preset['icon'], 'fa-shapes') ?></span>
                  <span style="white-space: nowrap; overflow: hidden; text-overflow: ellipsis; flex: 1; font-weight: 600;"><?= $preset['label'] ?></span>
                </button>
              <?php endforeach; ?>
            </div>

            <div style="display: flex; align-items: center; gap: 0.5rem; flex-wrap: wrap;">
              <span style="font-size: 0.8rem; font-weight: 600; color: #64748b;">Icon Class / Emote:</span>
              <input type="text" name="icon" id="inputIconClass" class="form-control text-sm" value="<?= htmlspecialchars($kategoriData['icon'] ?? 'fa-mountain-sun') ?>" placeholder="Cth: 🎡, 🎢, 🏔️ atau fa-mountain-sun" style="max-width: 320px;" oninput="updatePreview()">
            </div>
          </div>

          <div class="form-group mb-4">
            <label class="form-label font-bold text-xs uppercase text-muted">Deskripsi Kategori</label>
            <textarea name="deskripsi" id="inputDeskripsi" rows="3" class="form-control" placeholder="Tuliskan gambaran singkat mengenai destinasi dalam kategori ini..." oninput="updatePreview()"><?= htmlspecialchars($kategoriData['deskripsi'] ?? '') ?></textarea>
          </div>

        </div>

        <!-- Right Column: Live Interactive Preview Card (4 cols) -->
        <div class="col-span-4" style="grid-column: span 4 / span 4;">
          <div style="background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%); padding: 1.25rem; border-radius: 1rem; color: #ffffff; height: 100%; display: flex; flex-direction: column; justify-content: space-between; box-shadow: 0 10px 25px rgba(15, 23, 42, 0.15);">
            
            <div>
              <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 1rem;">
                <span style="font-size: 0.7rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.08em; color: #38bdf8;">
                  <i class="fa-solid fa-eye"></i> Live Card Preview
                </span>
                <span class="badge-luxury badge-luxury-success" style="font-size: 0.65rem;">
                  Tampilan Pengunjung
                </span>
              </div>

              <!-- Visitor Card Mockup -->
              <div style="background: rgba(255,255,255,0.06); border: 1px solid rgba(255,255,255,0.12); border-radius: 0.85rem; padding: 1.25rem; text-align: center; backdrop-filter: blur(8px);">
                <div id="previewIconWrap" style="width: 56px; height: 56px; border-radius: 1.15rem; background: linear-gradient(135deg, #0d9488, #059669); color: #ffffff; display: inline-flex; align-items: center; justify-content: center; font-size: 1.6rem; margin-bottom: 0.75rem; box-shadow: 0 8px 16px rgba(13, 148, 136, 0.35);">
                  <?= renderKategoriIcon($kategoriData['icon'] ?? 'fa-mountain-sun', 'fa-mountain-sun', 'preview-inner-icon') ?>
                </div>
                
                <h4 id="previewTitle" style="font-size: 1.05rem; font-weight: 800; color: #ffffff; margin: 0 0 0.35rem 0; font-family: 'Outfit', sans-serif;">
                  <?= !empty($kategoriData['nama_kategori']) ? htmlspecialchars($kategoriData['nama_kategori']) : 'Nama Kategori Wisata' ?>
                </h4>

                <p id="previewDesc" style="font-size: 0.78rem; color: #94a3b8; margin: 0 0 0.75rem 0; line-height: 1.4; min-height: 38px;">
                  <?= !empty($kategoriData['deskripsi']) ? htmlspecialchars($kategoriData['deskripsi']) : 'Deskripsi singkat mengenai kategori objek wisata ini akan tampil di katalog pengunjung.' ?>
                </p>

                <div style="display: flex; align-items: center; justify-content: center; gap: 0.5rem;">
                  <span style="font-size: 0.7rem; background: rgba(56, 189, 248, 0.15); color: #38bdf8; padding: 0.2rem 0.6rem; border-radius: 9999px; font-family: monospace;">
                    <i class="fa-solid fa-link"></i> /kategori/<span id="previewSlug"><?= !empty($kategoriData['slug']) ? htmlspecialchars($kategoriData['slug']) : 'slug-kategori' ?></span>
                  </span>
                </div>
              </div>
            </div>

            <div style="margin-top: 1rem; padding-top: 0.75rem; border-top: 1px solid rgba(255,255,255,0.08); font-size: 0.72rem; color: #64748b; text-align: center;">
              Icon dan warna akan disesuaikan otomatis di halaman beranda.
            </div>

          </div>
        </div>

      </div>

      <div class="flex justify-between items-center" style="border-top: 1px solid #e2e8f0; padding-top: 1.25rem;">
        <span class="text-xs text-muted">
          Pastikan icon FontAwesome yang dipilih valid dan relevan dengan kategori.
        </span>
        <div class="flex gap-2">
          <a href="<?= BASE_URL ?>admin/kategori.php" class="btn btn-secondary">
            Batal
          </a>
          <button type="submit" class="btn btn-primary" style="font-weight: 700; padding: 0.6rem 1.5rem; box-shadow: 0 4px 14px rgba(13, 148, 136, 0.4);">
            <i class="fa-solid fa-floppy-disk"></i> <?= $id > 0 ? 'Simpan Perubahan Kategori' : 'Simpan Kategori Baru' ?>
          </button>
        </div>
      </div>
    </form>
  </div>

  <!-- Filter, Search & Export Toolbar -->
  <div class="card p-3 shadow-sm mb-4 bg-white" style="border-radius: 0.75rem; border: 1px solid #e2e8f0;">
    <div style="display: flex; justify-content: space-between; align-items: center; gap: 0.75rem; flex-wrap: wrap;">
      
      <!-- Filter Tabs / Pills -->
      <div style="display: flex; gap: 0.3rem; flex-wrap: wrap;">
        <a href="<?= BASE_URL ?>admin/kategori.php?status=all<?= !empty($search) ? '&q=' . urlencode($search) : '' ?>" class="btn btn-sm <?= $filterStatus === 'all' ? 'btn-primary' : 'btn-secondary' ?>">
          Semua (<?= $totalKategori ?>)
        </a>
        <a href="<?= BASE_URL ?>admin/kategori.php?status=with_dest<?= !empty($search) ? '&q=' . urlencode($search) : '' ?>" class="btn btn-sm <?= $filterStatus === 'with_dest' ? 'btn-primary' : 'btn-secondary' ?>">
          <i class="fa-solid fa-circle-check text-emerald"></i> Ada Wisata (<?= $totalKategori - $emptyKategoriCount ?>)
        </a>
        <a href="<?= BASE_URL ?>admin/kategori.php?status=empty_dest<?= !empty($search) ? '&q=' . urlencode($search) : '' ?>" class="btn btn-sm <?= $filterStatus === 'empty_dest' ? 'btn-primary' : 'btn-secondary' ?>">
          <i class="fa-solid fa-circle-exclamation text-amber"></i> Kosong (<?= $emptyKategoriCount ?>)
        </a>
      </div>

      <!-- Live Search & Actions -->
      <div style="display: flex; align-items: center; gap: 0.4rem; flex: 1; max-width: 400px; justify-content: flex-end;">
        <form action="<?= BASE_URL ?>admin/kategori.php" method="GET" style="display: flex; width: 100%; gap: 0.35rem;">
          <?php if (!empty($filterStatus) && $filterStatus !== 'all'): ?>
            <input type="hidden" name="status" value="<?= htmlspecialchars($filterStatus) ?>">
          <?php endif; ?>
          <input type="text" name="q" id="tableSearchInput" class="form-control" value="<?= htmlspecialchars($search) ?>" placeholder="Cari nama kategori..." style="padding: 0.35rem 0.65rem; font-size: 0.78rem;" onkeyup="clientFilterTable()">
          <button type="submit" class="btn btn-primary btn-sm">Cari</button>
          <?php if (!empty($search)): ?>
            <a href="<?= BASE_URL ?>admin/kategori.php<?= $filterStatus !== 'all' ? '?status=' . urlencode($filterStatus) : '' ?>" class="btn btn-secondary btn-sm" title="Reset">
              <i class="fa-solid fa-xmark"></i>
            </a>
          <?php endif; ?>
        </form>
      </div>

    </div>
  </div>

  <!-- Luxury Data Table (100% Full Width) -->
  <div class="card-table-luxury">
    <div class="card-table-header">
      <div>
        <h3 style="font-size: 0.95rem; font-weight: 800; color: #0f172a; margin: 0; display: flex; align-items: center; gap: 0.4rem;">
          Daftar Master Kategori Wisata 
          <span class="badge-luxury badge-luxury-primary" style="font-size: 0.68rem;">
            <?= count($kategoriList) ?> Kategori
          </span>
        </h3>
      </div>

      <div style="display: flex; gap: 0.35rem;">
        <button type="button" onclick="window.print()" class="btn btn-secondary btn-sm">
          <i class="fa-solid fa-print"></i> Cetak
        </button>
      </div>
    </div>
    
    <div style="overflow-x: auto; width: 100%;">
      <table class="table-luxury" id="kategoriDataTable" style="width: 100%;">
        <thead>
          <tr>
            <th style="width: 7%; text-align: center;">No</th>
            <th style="width: 10%; text-align: center;">Visual Icon</th>
            <th style="width: 30%;">Nama & Deskripsi Kategori</th>
            <th style="width: 20%;">Slug URL</th>
            <th style="width: 18%;">Destinasi Terhubung</th>
            <th style="width: 15%; text-align: right;">Aksi</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($kategoriList)): ?>
            <tr>
              <td colspan="6" style="text-align: center; padding: 3rem 1rem;">
                <div style="max-width: 320px; margin: 0 auto;">
                  <div style="width: 60px; height: 60px; border-radius: 50%; background: #f1f5f9; color: #94a3b8; display: inline-flex; align-items: center; justify-content: center; font-size: 1.5rem; margin-bottom: 1rem;">
                    <i class="fa-solid fa-shapes"></i>
                  </div>
                  <h4 style="font-size: 1.05rem; font-weight: 700; color: #334155; margin: 0 0 0.25rem 0;">Tidak Ada Kategori</h4>
                  <p style="font-size: 0.8rem; color: #64748b; margin: 0 0 1rem 0;">Tidak ada kategori yang sesuai dengan filter atau kata kunci pencarian.</p>
                  <button type="button" onclick="toggleKatForm()" class="btn btn-primary btn-sm">
                    <i class="fa-solid fa-plus"></i> Buat Kategori Baru
                  </button>
                </div>
              </td>
            </tr>
          <?php else: ?>
            <?php $no = 1; foreach ($kategoriList as $k): ?>
              <?php 
                $daftarWisataArr = !empty($k['daftar_wisata']) ? explode('||', $k['daftar_wisata']) : [];
                $encodedList = htmlspecialchars(json_encode($daftarWisataArr), ENT_QUOTES, 'UTF-8');
              ?>
              <tr class="kategori-row">
                <td style="text-align: center; font-weight: 700; color: #94a3b8; font-size: 0.85rem;">
                  <?= $no++ ?>
                </td>
                <td style="text-align: center;">
                  <div class="kategori-avatar-icon" style="width: 44px; height: 44px; border-radius: 0.85rem; margin: 0 auto; display: inline-flex; align-items: center; justify-content: center; font-size: 1.35rem; background: linear-gradient(135deg, #ccfbf1 0%, #e0f2fe 100%); color: #0d9488; border: 1px solid #99f6e4; box-shadow: 0 2px 6px rgba(13, 148, 136, 0.12);">
                    <?= renderKategoriIcon($k['icon'] ?? 'fa-map-pin') ?>
                  </div>
                </td>
                <td>
                  <div style="font-weight: 800; font-size: 0.98rem; color: #0f172a; margin-bottom: 0.2rem;" class="kat-nama">
                    <?= htmlspecialchars($k['nama_kategori']) ?>
                  </div>
                  <?php if (!empty($k['deskripsi'])): ?>
                    <p class="text-xs text-muted kat-desc" style="margin: 0; line-height: 1.4; color: #64748b; max-width: 380px;">
                      <?= htmlspecialchars($k['deskripsi']) ?>
                    </p>
                  <?php else: ?>
                    <span class="text-xs" style="color: #cbd5e1; font-style: italic;">Tidak ada deskripsi</span>
                  <?php endif; ?>
                </td>
                <td>
                  <div style="display: inline-flex; align-items: center; gap: 0.35rem; background: #f8fafc; border: 1px solid #e2e8f0; padding: 0.25rem 0.6rem; border-radius: 0.5rem;">
                    <i class="fa-solid fa-link" style="color: #94a3b8; font-size: 0.72rem;"></i>
                    <code class="text-xs kat-slug" style="font-weight: 700; color: #0284c7;"><?= htmlspecialchars($k['slug']) ?></code>
                  </div>
                </td>
                <td>
                  <?php if ($k['total_destinasi'] > 0): ?>
                    <button type="button" class="badge-luxury badge-luxury-primary" onclick="showWisataModal('<?= htmlspecialchars($k['nama_kategori'], ENT_QUOTES) ?>', <?= $encodedList ?>)" style="cursor: pointer; border: none; font-size: 0.75rem; padding: 0.35rem 0.75rem; border-radius: 9999px; transition: transform 0.15s;" title="Klik untuk melihat daftar destinasi">
                      <i class="fa-solid fa-map-location-dot"></i> <?= $k['total_destinasi'] ?> Destinasi <i class="fa-solid fa-chevron-right" style="font-size: 0.65rem; margin-left: 0.2rem;"></i>
                    </button>
                  <?php else: ?>
                    <span class="badge-luxury badge-luxury-warning" style="font-size: 0.72rem; padding: 0.3rem 0.65rem;">
                      <i class="fa-solid fa-triangle-exclamation"></i> Belum Ada Wisata
                    </span>
                  <?php endif; ?>
                </td>
                <td style="text-align: right;">
                  <div class="flex gap-2 justify-end">
                    <a href="<?= BASE_URL ?>admin/kategori.php?id=<?= $k['id'] ?>&show_form=1" class="btn btn-secondary btn-sm" title="Edit Kategori" style="border-radius: 0.5rem; font-weight: 600;">
                      <i class="fa-solid fa-pen-to-square"></i> Edit
                    </a>
                    <a href="<?= BASE_URL ?>admin/kategori.php?action=delete&id=<?= $k['id'] ?>" class="btn btn-danger btn-sm" title="Hapus Kategori" style="border-radius: 0.5rem;" onclick="return confirm('Apakah Anda yakin ingin menghapus kategori \'<?= htmlspecialchars($k['nama_kategori'], ENT_QUOTES) ?>\'?')">
                      <i class="fa-solid fa-trash"></i>
                    </a>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- Modal Quick View Destinasi -->
  <div id="modalWisataList" style="display: none; position: fixed; inset: 0; background: rgba(15, 23, 42, 0.65); backdrop-filter: blur(4px); z-index: 9999; align-items: center; justify-content: center; padding: 1rem;">
    <div style="background: #ffffff; width: 100%; max-width: 500px; border-radius: 1.25rem; box-shadow: 0 20px 40px rgba(0,0,0,0.25); overflow: hidden; animation: modalPop 0.2s ease-out;">
      
      <div style="padding: 1.25rem 1.5rem; background: #f8fafc; border-bottom: 1px solid #e2e8f0; display: flex; align-items: center; justify-content: space-between;">
        <div style="display: flex; align-items: center; gap: 0.6rem;">
          <div style="width: 36px; height: 36px; border-radius: 0.65rem; background: #ccfbf1; color: #0d9488; display: flex; align-items: center; justify-content: center; font-size: 1rem;">
            <i class="fa-solid fa-mountain-sun"></i>
          </div>
          <div>
            <h4 style="font-size: 1rem; font-weight: 800; color: #0f172a; margin: 0;" id="modalKatTitle">Daftar Destinasi</h4>
            <span style="font-size: 0.72rem; color: #64748b;">Objek wisata terhubung dalam kategori ini</span>
          </div>
        </div>
        <button type="button" onclick="closeWisataModal()" style="border: none; background: transparent; font-size: 1.15rem; color: #94a3b8; cursor: pointer;">
          <i class="fa-solid fa-xmark"></i>
        </button>
      </div>

      <div style="padding: 1.25rem 1.5rem; max-height: 360px; overflow-y: auto;" id="modalWisataBody">
        <!-- Dynamic Wisata Items -->
      </div>

      <div style="padding: 1rem 1.5rem; background: #f8fafc; border-top: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center;">
        <a href="<?= BASE_URL ?>admin/destinasi.php" class="btn btn-secondary btn-sm" style="font-size: 0.78rem;">
          <i class="fa-solid fa-arrow-up-right-from-square"></i> Kelola di Destinasi
        </a>
        <button type="button" onclick="closeWisataModal()" class="btn btn-primary btn-sm" style="font-weight: 700;">
          Tutup
        </button>
      </div>

    </div>
  </div>

</main>

<style>
/* Preset Icon styling */
.btn-preset-icon:hover {
  background: #ccfbf1 !important;
  border-color: #0d9488 !important;
}
.btn-preset-icon.active {
  background: #0d9488 !important;
  color: #ffffff !important;
  border-color: #0d9488 !important;
}
.btn-preset-icon.active i {
  color: #ffffff !important;
}

/* Filter Pill Buttons */
.btn-filter-pill {
  display: inline-flex;
  align-items: center;
  gap: 0.4rem;
  padding: 0.45rem 0.9rem;
  border-radius: 9999px;
  font-size: 0.8rem;
  font-weight: 700;
  color: #64748b;
  background: #f1f5f9;
  border: 1px solid #e2e8f0;
  text-decoration: none;
  transition: all 0.2s;
}
.btn-filter-pill:hover {
  background: #e2e8f0;
  color: #0f172a;
}
.btn-filter-pill.active {
  background: #0d9488;
  color: #ffffff;
  border-color: #0d9488;
  box-shadow: 0 4px 10px rgba(13, 148, 136, 0.25);
}

@keyframes modalPop {
  from { opacity: 0; transform: scale(0.95); }
  to { opacity: 1; transform: scale(1); }
}
</style>

<script>
function toggleKatForm() {
  const card = document.getElementById('katFormCard');
  const icon = document.getElementById('btnKatFormIcon');
  const text = document.getElementById('btnKatFormText');
  if (card.style.display === 'none' || card.style.display === '') {
    card.style.display = 'block';
    if (icon) icon.className = 'fa-solid fa-xmark';
    if (text) text.innerText = 'Tutup Formulir';
    card.scrollIntoView({ behavior: 'smooth', block: 'start' });
  } else {
    card.style.display = 'none';
    if (icon) icon.className = 'fa-solid fa-plus';
    if (text) text.innerText = 'Tambah Kategori Baru';
  }
}

function selectPresetIcon(iconClass) {
  document.getElementById('inputIconClass').value = iconClass;
  
  // Highlight active preset button
  document.querySelectorAll('.btn-preset-icon').forEach(btn => {
    btn.classList.remove('active');
  });
  if (event && event.currentTarget) {
    event.currentTarget.classList.add('active');
  }
  
  updatePreview();
}

function generateSlug(str) {
  return str
    .toLowerCase()
    .trim()
    .replace(/[^\w\s-]/g, '')
    .replace(/[\s_-]+/g, '-')
    .replace(/^-+|-+$/g, '');
}

function syncPreviewAndSlug() {
  const nama = document.getElementById('inputNamaKategori').value;
  const slugInput = document.getElementById('inputSlug');
  
  // Only auto-update slug if user hasn't heavily customized or if creating new
  slugInput.value = generateSlug(nama);
  updatePreview();
}

function regenerateSlug() {
  const nama = document.getElementById('inputNamaKategori').value;
  document.getElementById('inputSlug').value = generateSlug(nama);
  updatePreview();
}

function updatePreview() {
  const nama = document.getElementById('inputNamaKategori').value || 'Nama Kategori Wisata';
  const slug = document.getElementById('inputSlug').value || 'slug-kategori';
  const icon = document.getElementById('inputIconClass').value.trim() || 'fa-mountain-sun';
  const desc = document.getElementById('inputDeskripsi').value || 'Deskripsi singkat mengenai kategori objek wisata ini akan tampil di katalog pengunjung.';

  document.getElementById('previewTitle').innerText = nama;
  document.getElementById('previewSlug').innerText = slug;
  document.getElementById('previewDesc').innerText = desc;
  
  const pWrap = document.getElementById('previewIconWrap');
  if (pWrap) {
    const isFa = (icon.indexOf('fa-') === 0 || icon.indexOf('fa ') === 0 || icon.indexOf('fa-solid ') === 0 || icon.indexOf('fas ') === 0);
    if (!isFa || icon.match(/[\u{1F300}-\u{1F9FF}\u{2600}-\u{26FF}\u{2700}-\u{27BF}]/u)) {
      pWrap.innerHTML = `<span class="kategori-emote-icon" style="font-size: 1.8rem; line-height: 1;">${icon}</span>`;
    } else {
      let cleanFa = icon.replace('fa-solid ', '').replace('fa ', '').trim();
      if (cleanFa.indexOf('fa-') !== 0) cleanFa = 'fa-' + cleanFa;
      pWrap.innerHTML = `<i class="fa-solid ${cleanFa}"></i>`;
    }
  }
}

// Client-side quick filter for real-time responsiveness
function clientFilterTable() {
  const filter = document.getElementById('tableSearchInput').value.toLowerCase();
  const rows = document.querySelectorAll('#kategoriDataTable tbody tr.kategori-row');
  
  rows.forEach(row => {
    const nama = row.querySelector('.kat-nama') ? row.querySelector('.kat-nama').innerText.toLowerCase() : '';
    const desc = row.querySelector('.kat-desc') ? row.querySelector('.kat-desc').innerText.toLowerCase() : '';
    const slug = row.querySelector('.kat-slug') ? row.querySelector('.kat-slug').innerText.toLowerCase() : '';

    if (nama.includes(filter) || desc.includes(filter) || slug.includes(filter)) {
      row.style.display = '';
    } else {
      row.style.display = 'none';
    }
  });
}

// Wisata Quick View Modal
function showWisataModal(katNama, wisataList) {
  document.getElementById('modalKatTitle').innerText = katNama;
  const body = document.getElementById('modalWisataBody');
  
  if (!wisataList || wisataList.length === 0) {
    body.innerHTML = '<div style="text-align: center; color: #94a3b8; padding: 1.5rem 0;">Belum ada destinasi wisata yang terhubung.</div>';
  } else {
    let html = '<ul style="list-style: none; padding: 0; margin: 0; display: flex; flex-direction: column; gap: 0.5rem;">';
    wisataList.forEach((w, idx) => {
      html += `
        <li style="display: flex; align-items: center; justify-content: space-between; padding: 0.65rem 0.85rem; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 0.65rem;">
          <div style="display: flex; align-items: center; gap: 0.6rem;">
            <span style="width: 24px; height: 24px; border-radius: 50%; background: #e0f2fe; color: #0284c7; font-size: 0.72rem; font-weight: 700; display: inline-flex; align-items: center; justify-content: center;">
              ${idx + 1}
            </span>
            <strong style="font-size: 0.88rem; color: #1e293b;">${w}</strong>
          </div>
          <span class="badge-luxury badge-luxury-success" style="font-size: 0.68rem;">Aktif</span>
        </li>
      `;
    });
    html += '</ul>';
    body.innerHTML = html;
  }

  const modal = document.getElementById('modalWisataList');
  modal.style.display = 'flex';
}

function closeWisataModal() {
  document.getElementById('modalWisataList').style.display = 'none';
}

// Close modal on click outside
window.onclick = function(event) {
  const modal = document.getElementById('modalWisataList');
  if (event.target === modal) {
    closeWisataModal();
  }
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
