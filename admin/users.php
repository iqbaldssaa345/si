<?php
require_once __DIR__ . '/../config/database.php';
checkAuth('admin');

$action = $_GET['action'] ?? 'list';
$id = (int)($_GET['id'] ?? 0);
$roleFilter = $_GET['role'] ?? '';
$search = trim($_GET['q'] ?? '');
$msg = '';
$msgType = '';
$showForm = ($id > 0 || isset($_GET['show_form']));

// ==========================================
// 1. ACTION: HAPUS PENGGUNA (DELETE)
// ==========================================
if ($action === 'delete' && $id > 0) {
    if ($id === (int)$_SESSION['user_id']) {
        setFlash('danger', 'Aksi dibatalkan: Anda tidak dapat menghapus akun Anda sendiri yang sedang aktif.');
    } else {
        $stmtCek = $pdo->prepare("SELECT nama, foto FROM users WHERE id = ?");
        $stmtCek->execute([$id]);
        $delUser = $stmtCek->fetch();
        $namaDel = $delUser ? $delUser['nama'] : 'Pengguna';

        // Hapus file foto jika ada
        if (!empty($delUser['foto']) && $delUser['foto'] !== 'default_avatar.png') {
            $pathFoto = __DIR__ . '/../assets/uploads/users/' . $delUser['foto'];
            if (file_exists($pathFoto)) {
                @unlink($pathFoto);
            }
        }

        $del = $pdo->prepare("DELETE FROM users WHERE id = ?");
        if ($del->execute([$id])) {
            setFlash('success', 'Akun <strong>' . htmlspecialchars($namaDel) . '</strong> berhasil dihapus dari sistem.');
        } else {
            setFlash('danger', 'Gagal menghapus akun pengguna.');
        }
    }
    header("Location: " . BASE_URL . "admin/users.php" . (!empty($roleFilter) ? '?role=' . urlencode($roleFilter) : ''));
    exit;
}

// ==========================================
// 2. ACTION: TAMBAH & EDIT PENGGUNA (POST)
// ==========================================
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $nama = trim($_POST['nama'] ?? '');
    $email = strtolower(trim($_POST['email'] ?? ''));
    $no_telp = trim($_POST['no_telp'] ?? '');
    $role = $_POST['role'] ?? 'pengunjung';
    $password = trim($_POST['password'] ?? '');
    $fotoLama = $_POST['foto_lama'] ?? 'default_avatar.png';
    $hapusFoto = isset($_POST['hapus_foto']);

    if (empty($nama) || empty($email)) {
        $msg = "Nama lengkap dan alamat email wajib diisi.";
        $msgType = "danger";
        $showForm = true;
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $msg = "Format alamat email tidak valid.";
        $msgType = "danger";
        $showForm = true;
    } else {
        // Handle Upload Foto
        $fotoName = $fotoLama;
        if ($hapusFoto) {
            if (!empty($fotoLama) && $fotoLama !== 'default_avatar.png') {
                $pathFoto = __DIR__ . '/../assets/uploads/users/' . $fotoLama;
                if (file_exists($pathFoto)) {
                    @unlink($pathFoto);
                }
            }
            $fotoName = 'default_avatar.png';
        }

        if (isset($_FILES['foto']) && $_FILES['foto']['error'] === UPLOAD_ERR_OK) {
            $tmp = $_FILES['foto']['tmp_name'];
            $ext = strtolower(pathinfo($_FILES['foto']['name'], PATHINFO_EXTENSION));
            $allowed = ['jpg', 'jpeg', 'png', 'webp'];

            if (in_array($ext, $allowed) && $_FILES['foto']['size'] <= 5 * 1024 * 1024) {
                $uploadDir = __DIR__ . '/../assets/uploads/users/';
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0777, true);
                }

                if (!empty($fotoLama) && $fotoLama !== 'default_avatar.png') {
                    $pathFoto = $uploadDir . $fotoLama;
                    if (file_exists($pathFoto)) {
                        @unlink($pathFoto);
                    }
                }

                $fotoName = 'user_' . time() . '_' . rand(100, 999) . '.' . $ext;
                move_uploaded_file($tmp, $uploadDir . $fotoName);
            }
        }

        if ($id > 0) {
            // EDIT / UPDATE USER
            $cek = $pdo->prepare("SELECT id FROM users WHERE LOWER(email) = ? AND id != ? LIMIT 1");
            $cek->execute([$email, $id]);
            if ($cek->fetch()) {
                $msg = "Email sudah digunakan oleh akun lain. Silakan gunakan email berbeda.";
                $msgType = "danger";
                $showForm = true;
            } else {
                if (!empty($password)) {
                    if (strlen($password) < 6) {
                        $msg = "Kata sandi baru minimal harus 6 karakter.";
                        $msgType = "danger";
                        $showForm = true;
                    } else {
                        $hash = password_hash($password, PASSWORD_DEFAULT);
                        $upd = $pdo->prepare("UPDATE users SET nama = ?, email = ?, no_telp = ?, role = ?, password = ?, foto = ? WHERE id = ?");
                        $upd->execute([$nama, $email, $no_telp, $role, $hash, $fotoName, $id]);
                        
                        if ($id === (int)$_SESSION['user_id']) {
                            $_SESSION['user_nama'] = $nama;
                            $_SESSION['user_foto'] = $fotoName;
                        }

                        setFlash('success', 'Data akun <strong>' . htmlspecialchars($nama) . '</strong> berhasil diperbarui!');
                        header("Location: " . BASE_URL . "admin/users.php");
                        exit;
                    }
                } else {
                    $upd = $pdo->prepare("UPDATE users SET nama = ?, email = ?, no_telp = ?, role = ?, foto = ? WHERE id = ?");
                    $upd->execute([$nama, $email, $no_telp, $role, $fotoName, $id]);

                    if ($id === (int)$_SESSION['user_id']) {
                        $_SESSION['user_nama'] = $nama;
                        $_SESSION['user_foto'] = $fotoName;
                    }

                    setFlash('success', 'Perubahan data akun <strong>' . htmlspecialchars($nama) . '</strong> berhasil disimpan!');
                    header("Location: " . BASE_URL . "admin/users.php");
                    exit;
                }
            }
        } else {
            // TAMBAH / CREATE USER
            if (empty($password)) {
                $msg = "Kata sandi wajib diisi untuk pengguna baru (minimal 6 karakter).";
                $msgType = "danger";
                $showForm = true;
            } elseif (strlen($password) < 6) {
                $msg = "Kata sandi minimal 6 karakter demi keamanan akun.";
                $msgType = "danger";
                $showForm = true;
            } else {
                $cek = $pdo->prepare("SELECT id FROM users WHERE LOWER(email) = ? LIMIT 1");
                $cek->execute([$email]);
                if ($cek->fetch()) {
                    $msg = "Alamat email tersebut sudah terdaftar di sistem. Silakan gunakan email lain.";
                    $msgType = "danger";
                    $showForm = true;
                } else {
                    $hash = password_hash($password, PASSWORD_DEFAULT);
                    $ins = $pdo->prepare("INSERT INTO users (nama, email, password, no_telp, role, foto, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())");
                    $ins->execute([$nama, $email, $hash, $no_telp, $role, $fotoName]);
                    setFlash('success', 'Akun pengguna baru <strong>' . htmlspecialchars($nama) . '</strong> dengan peran <strong>' . ucfirst($role) . '</strong> berhasil dibuat!');
                    header("Location: " . BASE_URL . "admin/users.php");
                    exit;
                }
            }
        }
    }
}

// ==========================================
// 3. AMBIL DATA USER UNTUK EDIT
// ==========================================
$userData = null;
if ($id > 0) {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$id]);
    $userData = $stmt->fetch();
    if (!$userData) {
        setFlash('danger', 'Akun pengguna tidak ditemukan.');
        header("Location: " . BASE_URL . "admin/users.php");
        exit;
    }
}

// ==========================================
// 4. HITUNG STATISTIK PENGGUNA
// ==========================================
$totalAllUsers = (int)$pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
$totalAdmin = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role = 'admin'")->fetchColumn();
$totalPetugas = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role = 'petugas'")->fetchColumn();
$totalPengunjung = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role = 'pengunjung'")->fetchColumn();

// ==========================================
// 5. QUERY DAFTAR PENGGUNA DENGAN FILTER
// ==========================================
$sql = "SELECT * FROM users WHERE 1=1";
$params = [];

if (!empty($roleFilter)) {
    $sql .= " AND role = ?";
    $params[] = $roleFilter;
}

if (!empty($search)) {
    $sql .= " AND (nama LIKE ? OR email LIKE ? OR no_telp LIKE ?)";
    $searchWildcard = "%" . $search . "%";
    $params[] = $searchWildcard;
    $params[] = $searchWildcard;
    $params[] = $searchWildcard;
}

$sql .= " ORDER BY CASE role WHEN 'admin' THEN 1 WHEN 'petugas' THEN 2 ELSE 3 END, id DESC";
$stmtUsers = $pdo->prepare($sql);
$stmtUsers->execute($params);
$usersList = $stmtUsers->fetchAll();

$pageTitle = "Manajemen Pengguna & Foto Profil";
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
          <i class="fa-solid fa-users-gear"></i> Modul Pengguna & Foto Profil
        </span>
        <span style="font-size: 0.8rem; color: #64748b;">
          Total <?= $totalAllUsers ?> Akun Terdaftar
        </span>
      </div>
      <h1 style="font-size: 1.6rem; font-weight: 800; color: #0f172a; margin: 0; letter-spacing: -0.02em;">
        Manajemen Pengguna & Foto Profil
      </h1>
      <p style="font-size: 0.85rem; color: #64748b; margin: 0.2rem 0 0 0;">
        Kontrol seluruh akun Administrator, Petugas Loket, dan Wisatawan lengkap dengan manajemen foto profil berkelas.
      </p>
    </div>

    <div style="display: flex; align-items: center; gap: 0.6rem; flex-wrap: wrap;">
      <button type="button" onclick="toggleUserForm()" class="btn btn-primary btn-sm" style="font-weight: 700; box-shadow: 0 4px 12px rgba(13, 148, 136, 0.35); padding: 0.55rem 1.15rem;">
        <i class="fa-solid <?= $showForm ? 'fa-xmark' : 'fa-user-plus' ?>" id="btnUserFormIcon"></i>
        <span id="btnUserFormText"><?= $showForm ? 'Tutup Formulir' : 'Tambah Pengguna Baru' ?></span>
      </button>
      <a href="<?= BASE_URL ?>admin/users.php" class="btn btn-secondary btn-sm" style="background: #ffffff; border: 1px solid #cbd5e1; padding: 0.55rem 0.85rem;" title="Refresh Data">
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
  <div class="grid grid-cols-4 gap-5 mb-6">
    
    <!-- Total Users -->
    <a href="<?= BASE_URL ?>admin/users.php" style="text-decoration: none; color: inherit;">
      <div class="kpi-card-luxury kpi-primary" style="cursor: pointer; padding: 1.25rem; border: <?= empty($roleFilter) ? '2px solid #0d9488' : '1px solid #e2e8f0' ?>;">
        <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 0.75rem;">
          <div class="kpi-icon-wrap" style="width: 44px; height: 44px; font-size: 1.15rem; background: #ccfbf1; color: #0d9488;">
            <i class="fa-solid fa-users"></i>
          </div>
          <span class="badge-luxury badge-luxury-primary" style="font-size: 0.7rem;">Semua Role</span>
        </div>
        <span style="font-size: 0.72rem; font-weight: 700; color: #64748b; text-transform: uppercase; letter-spacing: 0.05em; display: block;">
          Total Pengguna
        </span>
        <h3 style="font-size: 1.5rem; font-weight: 900; color: #0f172a; margin: 0.2rem 0 0 0; font-family: 'Outfit', sans-serif;">
          <?= $totalAllUsers ?> <span style="font-size: 0.85rem; font-weight: 600; color: #64748b;">Akun</span>
        </h3>
      </div>
    </a>

    <!-- Admin -->
    <a href="<?= BASE_URL ?>admin/users.php?role=admin" style="text-decoration: none; color: inherit;">
      <div class="kpi-card-luxury kpi-warning" style="cursor: pointer; padding: 1.25rem; border: <?= $roleFilter === 'admin' ? '2px solid #dc2626' : '1px solid #e2e8f0' ?>;">
        <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 0.75rem;">
          <div class="kpi-icon-wrap" style="width: 44px; height: 44px; font-size: 1.15rem; background: #fee2e2; color: #dc2626;">
            <i class="fa-solid fa-shield-halved"></i>
          </div>
          <span class="badge-luxury badge-luxury-danger" style="font-size: 0.7rem;">Super Admin</span>
        </div>
        <span style="font-size: 0.72rem; font-weight: 700; color: #64748b; text-transform: uppercase; letter-spacing: 0.05em; display: block;">
          Administrator
        </span>
        <h3 style="font-size: 1.5rem; font-weight: 900; color: #0f172a; margin: 0.2rem 0 0 0; font-family: 'Outfit', sans-serif;">
          <?= $totalAdmin ?> <span style="font-size: 0.85rem; font-weight: 600; color: #64748b;">Orang</span>
        </h3>
      </div>
    </a>

    <!-- Petugas -->
    <a href="<?= BASE_URL ?>admin/users.php?role=petugas" style="text-decoration: none; color: inherit;">
      <div class="kpi-card-luxury kpi-indigo" style="cursor: pointer; padding: 1.25rem; border: <?= $roleFilter === 'petugas' ? '2px solid #d97706' : '1px solid #e2e8f0' ?>;">
        <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 0.75rem;">
          <div class="kpi-icon-wrap" style="width: 44px; height: 44px; font-size: 1.15rem; background: #fef3c7; color: #d97706;">
            <i class="fa-solid fa-id-badge"></i>
          </div>
          <span class="badge-luxury badge-luxury-warning" style="font-size: 0.7rem;">Loket & Gate</span>
        </div>
        <span style="font-size: 0.72rem; font-weight: 700; color: #64748b; text-transform: uppercase; letter-spacing: 0.05em; display: block;">
          Petugas Loket
        </span>
        <h3 style="font-size: 1.5rem; font-weight: 900; color: #0f172a; margin: 0.2rem 0 0 0; font-family: 'Outfit', sans-serif;">
          <?= $totalPetugas ?> <span style="font-size: 0.85rem; font-weight: 600; color: #64748b;">Petugas</span>
        </h3>
      </div>
    </a>

    <!-- Pengunjung -->
    <a href="<?= BASE_URL ?>admin/users.php?role=pengunjung" style="text-decoration: none; color: inherit;">
      <div class="kpi-card-luxury kpi-success" style="cursor: pointer; padding: 1.25rem; border: <?= $roleFilter === 'pengunjung' ? '2px solid #16a34a' : '1px solid #e2e8f0' ?>;">
        <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 0.75rem;">
          <div class="kpi-icon-wrap kpi-icon-success" style="width: 44px; height: 44px; font-size: 1.15rem;">
            <i class="fa-solid fa-user"></i>
          </div>
          <span class="badge-luxury badge-luxury-success" style="font-size: 0.7rem;">Pengunjung</span>
        </div>
        <span style="font-size: 0.72rem; font-weight: 700; color: #64748b; text-transform: uppercase; letter-spacing: 0.05em; display: block;">
          Wisatawan Terdaftar
        </span>
        <h3 style="font-size: 1.5rem; font-weight: 900; color: #0f172a; margin: 0.2rem 0 0 0; font-family: 'Outfit', sans-serif;">
          <?= $totalPengunjung ?> <span style="font-size: 0.85rem; font-weight: 600; color: #64748b;">Member</span>
        </h3>
      </div>
    </a>

  </div>

  <!-- ==========================================
       COLLAPSIBLE LUXURY FORM WITH PHOTO UPLOADER
       ========================================== -->
  <div id="userFormCard" class="card p-6 shadow-md bg-white mb-6" style="border-radius: 1.25rem; border: 2px solid <?= $id > 0 ? '#0d9488' : '#cbd5e1' ?>; display: <?= $showForm ? 'block' : 'none' ?>; animation: fadeIn 0.3s ease;">
    
    <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 1.5rem; padding-bottom: 0.85rem; border-bottom: 1px solid #e2e8f0;">
      <div style="display: flex; align-items: center; gap: 0.75rem;">
        <div style="width: 42px; height: 42px; border-radius: 12px; background: <?= $id > 0 ? '#ccfbf1' : '#e0f2fe' ?>; color: <?= $id > 0 ? '#0d9488' : '#0284c7' ?>; display: flex; align-items: center; justify-content: center; font-size: 1.2rem;">
          <i class="fa-solid <?= $id > 0 ? 'fa-user-pen' : 'fa-user-plus' ?>"></i>
        </div>
        <div>
          <h3 style="font-size: 1.2rem; font-weight: 800; color: #0f172a; margin: 0;">
            <?= $id > 0 ? 'Edit Data & Foto Pengguna' : 'Formulir Pembuatan Akun Baru' ?>
          </h3>
          <p style="font-size: 0.8rem; color: #64748b; margin: 0;">
            <?= $id > 0 ? 'Sedang memperbarui profil akun #' . $id . ' (' . htmlspecialchars($userData['nama'] ?? '') . ')' : 'Lengkapi data identitas, unggah foto profil, dan tentukan hak akses' ?>
          </p>
        </div>
      </div>

      <div style="display: flex; gap: 0.5rem;">
        <a href="<?= BASE_URL ?>admin/users.php" class="btn btn-secondary btn-sm" style="border-radius: 0.5rem;">
          <i class="fa-solid fa-xmark"></i> Tutup Form
        </a>
      </div>
    </div>

    <form action="<?= BASE_URL ?>admin/users.php<?= $id > 0 ? '?id=' . $id : '' ?>" method="POST" enctype="multipart/form-data" autocomplete="off">
      <input type="hidden" name="foto_lama" value="<?= htmlspecialchars($userData['foto'] ?? 'default_avatar.png') ?>">
      
      <!-- Photo Uploader Section -->
      <div style="display: flex; align-items: center; gap: 1.5rem; margin-bottom: 1.5rem; padding: 1.25rem; background: #f8fafc; border-radius: 1rem; border: 1px solid #e2e8f0;">
        <?php 
          $userEditFoto = $userData['foto'] ?? 'default_avatar.png';
          $hasFotoEdit = (!empty($userEditFoto) && $userEditFoto !== 'default_avatar.png' && file_exists(__DIR__ . '/../assets/uploads/users/' . $userEditFoto));
          $editFotoUrl = $hasFotoEdit ? BASE_URL . 'assets/uploads/users/' . htmlspecialchars($userEditFoto) : '';
          $initEdit = strtoupper(substr($userData['nama'] ?? 'U', 0, 1));
        ?>
        <div style="position: relative; width: 72px; height: 72px; flex-shrink: 0;">
          <?php if ($hasFotoEdit): ?>
            <img src="<?= $editFotoUrl ?>" alt="Foto" id="formAvatarPreview" style="width: 72px; height: 72px; border-radius: 50%; object-fit: cover; border: 2.5px solid #0d9488; box-shadow: 0 4px 12px rgba(13, 148, 136, 0.25);">
          <?php else: ?>
            <div id="formInitialBox" style="width: 72px; height: 72px; border-radius: 50%; background: linear-gradient(135deg, #0d9488, #0284c7); color: #ffffff; display: flex; align-items: center; justify-content: center; font-size: 1.75rem; font-weight: 800; border: 2.5px solid #ffffff; box-shadow: 0 4px 12px rgba(13, 148, 136, 0.25);">
              <?= $initEdit ?>
            </div>
            <img src="" alt="Preview" id="formAvatarPreview" style="display: none; width: 72px; height: 72px; border-radius: 50%; object-fit: cover; border: 2.5px solid #0d9488;">
          <?php endif; ?>
        </div>

        <div style="flex: 1;">
          <label style="display: block; font-size: 0.78rem; font-weight: 800; color: #334155; margin-bottom: 0.25rem; text-transform: uppercase;">
            Foto Profil Pengguna (Opsional)
          </label>
          <div style="display: flex; align-items: center; gap: 0.75rem; flex-wrap: wrap;">
            <input type="file" name="foto" id="adminUserFotoInput" accept="image/jpeg,image/png,image/webp,image/jpg" class="form-control" style="font-size: 0.82rem; padding: 0.35rem 0.65rem; max-width: 320px;" onchange="previewAdminUserPhoto(this)">
            
            <?php if ($hasFotoEdit): ?>
              <label style="display: flex; align-items: center; gap: 0.35rem; font-size: 0.8rem; color: #ef4444; cursor: pointer; user-select: none;">
                <input type="checkbox" name="hapus_foto" value="1" style="accent-color: #ef4444;">
                <span>Hapus Foto Profil</span>
              </label>
            <?php endif; ?>
          </div>
          <small style="font-size: 0.72rem; color: #94a3b8; margin-top: 0.2rem; display: block;">Format JPG, PNG, WEBP (Maksimal 5MB).</small>
        </div>
      </div>

      <div class="grid grid-cols-3 gap-5 mb-4">
        
        <!-- Nama Lengkap -->
        <div class="form-group">
          <label style="display: block; font-size: 0.75rem; font-weight: 800; color: #334155; margin-bottom: 0.4rem; text-transform: uppercase; letter-spacing: 0.05em;">
            Nama Lengkap Pengguna <span style="color: #ef4444;">*</span>
          </label>
          <div style="position: relative;">
            <i class="fa-solid fa-user" style="position: absolute; left: 1rem; top: 50%; transform: translateY(-50%); color: #0d9488; font-size: 0.95rem;"></i>
            <input type="text" name="nama" class="form-control" style="padding-left: 2.6rem; border-radius: 0.75rem; font-weight: 600;" value="<?= htmlspecialchars($userData['nama'] ?? '') ?>" placeholder="Cth: Budi Santoso" required>
          </div>
        </div>

        <!-- Email -->
        <div class="form-group">
          <label style="display: block; font-size: 0.75rem; font-weight: 800; color: #334155; margin-bottom: 0.4rem; text-transform: uppercase; letter-spacing: 0.05em;">
            Alamat Email (Login) <span style="color: #ef4444;">*</span>
          </label>
          <div style="position: relative;">
            <i class="fa-solid fa-envelope" style="position: absolute; left: 1rem; top: 50%; transform: translateY(-50%); color: #0d9488; font-size: 0.95rem;"></i>
            <input type="email" name="email" class="form-control" style="padding-left: 2.6rem; border-radius: 0.75rem; font-weight: 600;" value="<?= htmlspecialchars($userData['email'] ?? '') ?>" placeholder="nama@email.com" required>
          </div>
        </div>

        <!-- No WhatsApp -->
        <div class="form-group">
          <label style="display: block; font-size: 0.75rem; font-weight: 800; color: #334155; margin-bottom: 0.4rem; text-transform: uppercase; letter-spacing: 0.05em;">
            Nomor WhatsApp / HP
          </label>
          <div style="position: relative;">
            <i class="fa-brands fa-whatsapp" style="position: absolute; left: 1rem; top: 50%; transform: translateY(-50%); color: #16a34a; font-size: 1.05rem;"></i>
            <input type="text" name="no_telp" class="form-control" style="padding-left: 2.6rem; border-radius: 0.75rem; font-weight: 600;" value="<?= htmlspecialchars($userData['no_telp'] ?? '') ?>" placeholder="081234567890">
          </div>
        </div>

      </div>

      <div class="grid grid-cols-2 gap-5 mb-5">
        
        <!-- Role Selector -->
        <div class="form-group">
          <label style="display: block; font-size: 0.75rem; font-weight: 800; color: #334155; margin-bottom: 0.4rem; text-transform: uppercase; letter-spacing: 0.05em;">
            Hak Akses Sistem (Role) <span style="color: #ef4444;">*</span>
          </label>
          <select name="role" class="form-control" style="border-radius: 0.75rem; font-weight: 700; height: 45px;" required>
            <option value="pengunjung" <?= ($userData['role'] ?? '') === 'pengunjung' ? 'selected' : '' ?>>
              👤 Pengunjung / Wisatawan (Pemesanan Tiket & Riwayat)
            </option>
            <option value="petugas" <?= ($userData['role'] ?? '') === 'petugas' ? 'selected' : '' ?>>
              🎫 Petugas Loket (Validasi QR E-Ticket & Input Presensi)
            </option>
            <option value="admin" <?= ($userData['role'] ?? '') === 'admin' ? 'selected' : '' ?>>
              🛡️ Administrator Utama (Super Admin - Akses Penuh Sistem)
            </option>
          </select>
        </div>

        <!-- Password -->
        <div class="form-group">
          <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.4rem;">
            <label style="font-size: 0.75rem; font-weight: 800; color: #334155; text-transform: uppercase; letter-spacing: 0.05em; margin: 0;">
              <?= $id > 0 ? 'Ganti Kata Sandi (Opsional)' : 'Kata Sandi Akun *' ?>
            </label>
            <a href="javascript:void(0)" onclick="generateRandomPwd()" style="font-size: 0.75rem; color: #0d9488; font-weight: 700; text-decoration: none;">
              <i class="fa-solid fa-key"></i> Generate Otomatis
            </a>
          </div>
          <div style="position: relative;">
            <i class="fa-solid fa-lock" style="position: absolute; left: 1rem; top: 50%; transform: translateY(-50%); color: #0d9488; font-size: 0.95rem;"></i>
            <input type="password" name="password" id="userFormPassword" class="form-control" style="padding-left: 2.6rem; padding-right: 2.6rem; border-radius: 0.75rem; font-weight: 600;" placeholder="<?= $id > 0 ? '•••••••• (Biarkan kosong jika tidak ganti)' : 'Min. 6 Karakter' ?>" <?= $id > 0 ? '' : 'required' ?>>
            <button type="button" onclick="toggleUserPwd()" style="position: absolute; right: 0.85rem; top: 50%; transform: translateY(-50%); background: none; border: none; color: #94a3b8; cursor: pointer;" title="Tampilkan/Sembunyikan Sandi">
              <i class="fa-regular fa-eye" id="userEyeIcon"></i>
            </button>
          </div>
        </div>

      </div>

      <!-- Submit & Cancel Buttons -->
      <div style="display: flex; align-items: center; gap: 0.75rem; justify-content: flex-end; border-top: 1px solid #f1f5f9; padding-top: 1.25rem;">
        <a href="<?= BASE_URL ?>admin/users.php" class="btn btn-secondary" style="border-radius: 0.75rem; font-weight: 700; padding: 0.65rem 1.25rem;">
          <i class="fa-solid fa-xmark"></i> Batal
        </a>
        <button type="submit" class="btn btn-primary shadow-glow" style="border-radius: 0.75rem; font-weight: 800; padding: 0.65rem 1.75rem;">
          <i class="fa-solid <?= $id > 0 ? 'fa-floppy-disk' : 'fa-user-check' ?>"></i>
          <span><?= $id > 0 ? 'Simpan Perubahan Akun' : 'Simpan & Buat Akun' ?></span>
        </button>
      </div>

    </form>
  </div>

  <!-- ==========================================
       TABEL DAFTAR PENGGUNA (FULL 100% WIDTH)
       ========================================== -->
  <div class="card-table-luxury" style="width: 100%; border-radius: 1.25rem; box-shadow: 0 4px 20px rgba(0, 0, 0, 0.03);">
    
    <!-- Filter, Search and Tab Header -->
    <div style="padding: 1.25rem 1.5rem; border-bottom: 1px solid #e2e8f0; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 1rem; background: #ffffff;">
      
      <!-- Role Filter Tabs -->
      <div style="display: flex; align-items: center; gap: 0.4rem; flex-wrap: wrap;">
        <a href="<?= BASE_URL ?>admin/users.php<?= !empty($search) ? '?q=' . urlencode($search) : '' ?>" class="btn btn-sm <?= empty($roleFilter) ? 'btn-primary' : 'btn-secondary' ?>" style="border-radius: 9999px; font-size: 0.8rem; font-weight: 700; padding: 0.4rem 0.95rem;">
          Semua Pengguna (<?= $totalAllUsers ?>)
        </a>
        <a href="<?= BASE_URL ?>admin/users.php?role=admin<?= !empty($search) ? '&q=' . urlencode($search) : '' ?>" class="btn btn-sm <?= $roleFilter === 'admin' ? 'btn-primary' : 'btn-secondary' ?>" style="border-radius: 9999px; font-size: 0.8rem; font-weight: 700; padding: 0.4rem 0.95rem;">
          🛡️ Admin (<?= $totalAdmin ?>)
        </a>
        <a href="<?= BASE_URL ?>admin/users.php?role=petugas<?= !empty($search) ? '&q=' . urlencode($search) : '' ?>" class="btn btn-sm <?= $roleFilter === 'petugas' ? 'btn-primary' : 'btn-secondary' ?>" style="border-radius: 9999px; font-size: 0.8rem; font-weight: 700; padding: 0.4rem 0.95rem;">
          🎫 Petugas Loket (<?= $totalPetugas ?>)
        </a>
        <a href="<?= BASE_URL ?>admin/users.php?role=pengunjung<?= !empty($search) ? '&q=' . urlencode($search) : '' ?>" class="btn btn-sm <?= $roleFilter === 'pengunjung' ? 'btn-primary' : 'btn-secondary' ?>" style="border-radius: 9999px; font-size: 0.8rem; font-weight: 700; padding: 0.4rem 0.95rem;">
          🎒 Wisatawan (<?= $totalPengunjung ?>)
        </a>
      </div>

      <!-- Search Box Form -->
      <form action="<?= BASE_URL ?>admin/users.php" method="GET" style="display: flex; align-items: center; gap: 0.5rem; min-width: 280px;">
        <?php if (!empty($roleFilter)): ?>
          <input type="hidden" name="role" value="<?= htmlspecialchars($roleFilter) ?>">
        <?php endif; ?>
        <div style="position: relative; flex: 1;">
          <i class="fa-solid fa-magnifying-glass" style="position: absolute; left: 0.85rem; top: 50%; transform: translateY(-50%); color: #0d9488; font-size: 0.85rem;"></i>
          <input type="text" name="q" class="form-control" style="padding: 0.5rem 0.85rem 0.5rem 2.4rem; font-size: 0.85rem; border-radius: 0.65rem;" placeholder="Cari nama, email, no HP..." value="<?= htmlspecialchars($search) ?>">
        </div>
        <button type="submit" class="btn btn-primary btn-sm" style="padding: 0.5rem 0.95rem; font-weight: 700; border-radius: 0.65rem;">
          Cari
        </button>
        <?php if (!empty($search)): ?>
          <a href="<?= BASE_URL ?>admin/users.php<?= !empty($roleFilter) ? '?role=' . urlencode($roleFilter) : '' ?>" class="btn btn-secondary btn-sm" style="border-radius: 0.65rem; padding: 0.5rem 0.75rem;" title="Reset Pencarian">
            <i class="fa-solid fa-xmark"></i>
          </a>
        <?php endif; ?>
      </form>

    </div>

    <!-- User List Table (Full Width 100% Fit) -->
    <div style="overflow-x: auto; width: 100%;">
      <table class="table-luxury" style="width: 100%; border-collapse: separate; border-spacing: 0;">
        <thead>
          <tr>
            <th style="width: 35%;">Foto Profil & Identitas</th>
            <th style="width: 20%;">Kontak WhatsApp</th>
            <th style="width: 18%;">Hak Akses (Role)</th>
            <th style="width: 15%;">Terdaftar</th>
            <th style="width: 12%; text-align: right;">Aksi Cepat</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($usersList)): ?>
            <tr>
              <td colspan="5" style="text-align: center; padding: 4rem 1rem; color: #94a3b8;">
                <i class="fa-solid fa-user-slash" style="font-size: 2.8rem; margin-bottom: 0.75rem; display: block; color: #cbd5e1;"></i>
                <p style="font-size: 1rem; font-weight: 700; color: #475569; margin: 0;">Tidak ada data pengguna yang sesuai dengan filter.</p>
                <p style="font-size: 0.82rem; color: #94a3b8; margin-top: 0.25rem;">Coba bersihkan kata kunci pencarian atau ubah tab kategori role di atas.</p>
              </td>
            </tr>
          <?php else: ?>
            <?php foreach ($usersList as $u): 
              $userInit = strtoupper(substr($u['nama'], 0, 1));
              $isSelf = ((int)$u['id'] === (int)$_SESSION['user_id']);
              $isBeingEdited = ($id === (int)$u['id']);
              $userFotoFile = $u['foto'] ?? 'default_avatar.png';
              $hasUserFoto = (!empty($userFotoFile) && $userFotoFile !== 'default_avatar.png' && file_exists(__DIR__ . '/../assets/uploads/users/' . $userFotoFile));
            ?>
              <tr style="<?= $isBeingEdited ? 'background-color: #f0fdfa;' : '' ?>">
                
                <!-- 1. User Profile Info with Photo -->
                <td>
                  <div style="display: flex; align-items: center; gap: 0.85rem;">
                    
                    <?php if ($hasUserFoto): ?>
                      <img src="<?= BASE_URL ?>assets/uploads/users/<?= htmlspecialchars($userFotoFile) ?>" alt="<?= htmlspecialchars($u['nama']) ?>" style="width: 46px; height: 46px; min-width: 46px; border-radius: 14px; object-fit: cover; border: 2px solid <?= $u['role'] === 'admin' ? '#dc2626' : ($u['role'] === 'petugas' ? '#d97706' : '#0d9488') ?>; box-shadow: 0 4px 10px rgba(0,0,0,0.12);">
                    <?php else: ?>
                      <div style="width: 46px; height: 46px; min-width: 46px; border-radius: 14px; display: flex; align-items: center; justify-content: center; font-weight: 800; font-size: 1.15rem; color: #ffffff; background: <?= $u['role'] === 'admin' ? 'linear-gradient(135deg, #dc2626, #991b1b)' : ($u['role'] === 'petugas' ? 'linear-gradient(135deg, #d97706, #b45309)' : 'linear-gradient(135deg, #0d9488, #0284c7)') ?>; box-shadow: 0 4px 10px rgba(0,0,0,0.12);">
                        <?= $userInit ?>
                      </div>
                    <?php endif; ?>

                    <div>
                      <div style="display: flex; align-items: center; gap: 0.45rem; flex-wrap: wrap;">
                        <strong style="color: #0f172a; font-size: 0.95rem; font-weight: 800;">
                          <?= htmlspecialchars($u['nama']) ?>
                        </strong>
                        <?php if ($isSelf): ?>
                          <span style="font-size: 0.68rem; font-weight: 800; background: #e0f2fe; color: #0369a1; padding: 0.15rem 0.5rem; border-radius: 0.35rem; border: 1px solid #bae6fd;">
                            <i class="fa-solid fa-crown text-amber"></i> Anda (Aktif)
                          </span>
                        <?php endif; ?>
                        <?php if ($isBeingEdited): ?>
                          <span style="font-size: 0.68rem; font-weight: 800; background: #ccfbf1; color: #0f766e; padding: 0.15rem 0.5rem; border-radius: 0.35rem; border: 1px solid #99f6e4;">
                            <i class="fa-solid fa-pen"></i> Sedang Diedit
                          </span>
                        <?php endif; ?>
                      </div>
                      <a href="mailto:<?= htmlspecialchars($u['email']) ?>" style="font-size: 0.82rem; color: #64748b; text-decoration: none; display: inline-flex; align-items: center; gap: 0.35rem; margin-top: 0.15rem;">
                        <i class="fa-regular fa-envelope" style="font-size: 0.75rem; color: #0d9488;"></i> <?= htmlspecialchars($u['email']) ?>
                      </a>
                    </div>
                  </div>
                </td>

                <!-- 2. Phone / WhatsApp -->
                <td>
                  <?php if (!empty($u['no_telp'])): 
                    $waClean = preg_replace('/[^0-9]/', '', $u['no_telp']);
                    if (substr($waClean, 0, 1) === '0') {
                        $waClean = '62' . substr($waClean, 1);
                    }
                  ?>
                    <a href="https://wa.me/<?= $waClean ?>" target="_blank" style="font-size: 0.84rem; font-weight: 700; color: #15803d; text-decoration: none; display: inline-flex; align-items: center; gap: 0.4rem; background: #f0fdf4; padding: 0.35rem 0.7rem; border-radius: 0.5rem; border: 1px solid #bbf7d0;" title="Kirim Chat WhatsApp">
                      <i class="fa-brands fa-whatsapp text-emerald" style="font-size: 1rem;"></i> <?= htmlspecialchars($u['no_telp']) ?>
                    </a>
                  <?php else: ?>
                    <span style="font-size: 0.8rem; color: #94a3b8; font-style: italic;">Belum diisi</span>
                  <?php endif; ?>
                </td>

                <!-- 3. Role Badge -->
                <td>
                  <?php if ($u['role'] === 'admin'): ?>
                    <span class="badge-luxury badge-luxury-danger" style="font-size: 0.78rem; padding: 0.35rem 0.75rem;">
                      <i class="fa-solid fa-shield-halved" style="font-size: 0.75rem;"></i> Administrator
                    </span>
                  <?php elseif ($u['role'] === 'petugas'): ?>
                    <span class="badge-luxury badge-luxury-warning" style="font-size: 0.78rem; padding: 0.35rem 0.75rem;">
                      <i class="fa-solid fa-id-badge" style="font-size: 0.75rem;"></i> Petugas Loket
                    </span>
                  <?php else: ?>
                    <span class="badge-luxury badge-luxury-primary" style="font-size: 0.78rem; padding: 0.35rem 0.75rem;">
                      <i class="fa-solid fa-user" style="font-size: 0.75rem;"></i> Wisatawan
                    </span>
                  <?php endif; ?>
                </td>

                <!-- 4. Joined Date -->
                <td>
                  <span style="font-size: 0.85rem; color: #334155; display: block; font-weight: 600;">
                    <?= formatTanggalIndo($u['created_at']) ?>
                  </span>
                  <small style="font-size: 0.72rem; color: #94a3b8;">
                    <?= date('H:i', strtotime($u['created_at'])) ?> WIB
                  </small>
                </td>

                <!-- 5. Actions (Edit & Delete) -->
                <td style="text-align: right;">
                  <div style="display: inline-flex; align-items: center; gap: 0.4rem; justify-content: flex-end;">
                    
                    <!-- Edit Button -->
                    <a href="<?= BASE_URL ?>admin/users.php?id=<?= $u['id'] ?>&show_form=1" class="btn btn-secondary btn-sm" style="padding: 0.4rem 0.75rem; font-size: 0.8rem; border-radius: 0.5rem; font-weight: 700;" title="Edit Data Pengguna">
                      <i class="fa-solid fa-pen-to-square"></i> Edit
                    </a>

                    <!-- Delete Button -->
                    <?php if (!$isSelf): ?>
                      <a href="<?= BASE_URL ?>admin/users.php?action=delete&id=<?= $u['id'] ?>" class="btn btn-danger btn-sm" style="padding: 0.4rem 0.7rem; font-size: 0.8rem; border-radius: 0.5rem;" title="Hapus Akun" onclick="return confirm('Apakah Anda yakin ingin menghapus akun pengguna [<?= htmlspecialchars(addslashes($u['nama'])) ?>]? Tindakan ini tidak dapat dibatalkan.')">
                        <i class="fa-solid fa-trash"></i>
                      </a>
                    <?php else: ?>
                      <button type="button" class="btn btn-secondary btn-sm" style="padding: 0.4rem 0.7rem; font-size: 0.8rem; opacity: 0.4; cursor: not-allowed; border-radius: 0.5rem;" title="Akun Anda aktif saat ini (tidak bisa dihapus)">
                        <i class="fa-solid fa-lock"></i>
                      </button>
                    <?php endif; ?>

                  </div>
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
function previewAdminUserPhoto(input) {
  if (input.files && input.files[0]) {
    const reader = new FileReader();
    reader.onload = function(e) {
      const previewImg = document.getElementById('formAvatarPreview');
      const initBox = document.getElementById('formInitialBox');
      if (initBox) initBox.style.display = 'none';
      if (previewImg) {
        previewImg.src = e.target.result;
        previewImg.style.display = 'block';
      }
    };
    reader.readAsDataURL(input.files[0]);
  }
}

function toggleUserForm() {
  const card = document.getElementById('userFormCard');
  const icon = document.getElementById('btnUserFormIcon');
  const text = document.getElementById('btnUserFormText');
  if (card.style.display === 'none' || card.style.display === '') {
    card.style.display = 'block';
    if (icon) { icon.className = 'fa-solid fa-xmark'; }
    if (text) { text.innerText = 'Tutup Formulir'; }
    card.scrollIntoView({ behavior: 'smooth', block: 'start' });
  } else {
    card.style.display = 'none';
    if (icon) { icon.className = 'fa-solid fa-user-plus'; }
    if (text) { text.innerText = 'Tambah Pengguna Baru'; }
  }
}

function toggleUserPwd() {
  const f = document.getElementById('userFormPassword');
  const ic = document.getElementById('userEyeIcon');
  if (f.type === 'password') {
    f.type = 'text';
    ic.classList.remove('fa-eye');
    ic.classList.add('fa-eye-slash');
  } else {
    f.type = 'password';
    ic.classList.remove('fa-eye-slash');
    ic.classList.add('fa-eye');
  }
}

function generateRandomPwd() {
  const chars = "abcdefghjkmnpqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789!@#$%";
  let pwd = "";
  for (let i = 0; i < 8; i++) {
    pwd += chars.charAt(Math.floor(Math.random() * chars.length));
  }
  const f = document.getElementById('userFormPassword');
  f.type = 'text';
  f.value = pwd;
  const ic = document.getElementById('userEyeIcon');
  if (ic) {
    ic.classList.remove('fa-eye');
    ic.classList.add('fa-eye-slash');
  }
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
