<?php
require_once __DIR__ . '/../config/database.php';
checkAuth('pengunjung');

$userId = $_SESSION['user_id'];
$msg = '';
$msgType = '';

// Ambil Data Pengguna Terkini dari Database
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$userId]);
$user = $stmt->fetch();

if (!$user) {
    header("Location: " . BASE_URL . "login.php");
    exit;
}

// 1. Handle Upload & Update Foto Profil
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['action_update_photo'])) {
    if (isset($_FILES['foto_profil']) && $_FILES['foto_profil']['error'] === UPLOAD_ERR_OK) {
        $fileTmp = $_FILES['foto_profil']['tmp_name'];
        $fileName = $_FILES['foto_profil']['name'];
        $fileSize = $_FILES['foto_profil']['size'];
        $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        $allowed = ['jpg', 'jpeg', 'png', 'webp'];

        if (!in_array($ext, $allowed)) {
            $msg = "Format berkas tidak didukung. Silakan gunakan format JPG, JPEG, PNG, atau WEBP.";
            $msgType = "danger";
        } elseif ($fileSize > 5 * 1024 * 1024) { // Max 5MB
            $msg = "Ukuran berkas terlalu besar. Maksimal 5MB.";
            $msgType = "danger";
        } else {
            $uploadDir = __DIR__ . '/../assets/uploads/users/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }

            // Hapus foto lama jika file lokal dan bukan default
            $oldFoto = $user['foto'] ?? '';
            if (!empty($oldFoto) && $oldFoto !== 'default_avatar.png' && strpos($oldFoto, 'http') !== 0 && file_exists($uploadDir . $oldFoto)) {
                @unlink($uploadDir . $oldFoto);
            }

            $newFileName = 'user_' . $userId . '_' . time() . '.' . $ext;
            if (move_uploaded_file($fileTmp, $uploadDir . $newFileName)) {
                $upd = $pdo->prepare("UPDATE users SET foto = ? WHERE id = ?");
                $upd->execute([$newFileName, $userId]);
                $_SESSION['user_foto'] = $newFileName;
                setFlash('success', 'Foto profil eksklusif Anda berhasil diperbarui!');
                header("Location: " . BASE_URL . "pengunjung/profil.php");
                exit;
            } else {
                $msg = "Gagal mengunggah foto profil. Silakan coba kembali.";
                $msgType = "danger";
            }
        }
    } elseif (isset($_POST['remove_photo'])) {
        // Hapus foto profil kembali ke default
        $uploadDir = __DIR__ . '/../assets/uploads/users/';
        $oldFoto = $user['foto'] ?? '';
        if (!empty($oldFoto) && $oldFoto !== 'default_avatar.png' && strpos($oldFoto, 'http') !== 0 && file_exists($uploadDir . $oldFoto)) {
            @unlink($uploadDir . $oldFoto);
        }
        $upd = $pdo->prepare("UPDATE users SET foto = 'default_avatar.png' WHERE id = ?");
        $upd->execute([$userId]);
        $_SESSION['user_foto'] = 'default_avatar.png';
        setFlash('success', 'Foto profil berhasil direset ke avatar bawaan.');
        header("Location: " . BASE_URL . "pengunjung/profil.php");
        exit;
    } else {
        $msg = "Silakan pilih berkas foto terlebih dahulu.";
        $msgType = "warning";
    }
}

// 2. Handle Update Biodata Profil
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['action_update_profile'])) {
    $nama = trim($_POST['nama'] ?? '');
    $email = strtolower(trim($_POST['email'] ?? ''));
    $noTelp = trim($_POST['no_telp'] ?? '');

    if (empty($nama) || empty($email)) {
        $msg = "Nama lengkap dan alamat email tidak boleh kosong.";
        $msgType = "danger";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $msg = "Format alamat email tidak valid.";
        $msgType = "danger";
    } else {
        // Cek duplikasi email pada user lain
        $cek = $pdo->prepare("SELECT id FROM users WHERE LOWER(email) = ? AND id != ? LIMIT 1");
        $cek->execute([$email, $userId]);
        if ($cek->fetch()) {
            $msg = "Alamat email ini sudah terdaftar oleh akun lain.";
            $msgType = "danger";
        } else {
            $upd = $pdo->prepare("UPDATE users SET nama = ?, email = ?, no_telp = ? WHERE id = ?");
            if ($upd->execute([$nama, $email, $noTelp, $userId])) {
                $_SESSION['user_nama'] = $nama;
                $_SESSION['user_email'] = $email;
                $_SESSION['user_telp'] = $noTelp;
                setFlash('success', 'Data identitas profil Anda berhasil diperbarui!');
                header("Location: " . BASE_URL . "pengunjung/profil.php");
                exit;
            } else {
                $msg = "Gagal memperbarui data profil akun.";
                $msgType = "danger";
            }
        }
    }
}

// 3. Handle Ganti Kata Sandi
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['action_update_password'])) {
    $oldPass = $_POST['password_lama'] ?? '';
    $newPass = $_POST['password_baru'] ?? '';
    $confirmPass = $_POST['konfirmasi_password'] ?? '';

    if (empty($oldPass) || empty($newPass) || empty($confirmPass)) {
        $msg = "Semua kolom kata sandi wajib diisi.";
        $msgType = "danger";
    } elseif (!password_verify($oldPass, $user['password'])) {
        $msg = "Kata sandi saat ini yang Anda masukkan salah.";
        $msgType = "danger";
    } elseif (strlen($newPass) < 6) {
        $msg = "Kata sandi baru minimal harus 6 karakter.";
        $msgType = "danger";
    } elseif ($newPass !== $confirmPass) {
        $msg = "Konfirmasi kata sandi baru tidak cocok.";
        $msgType = "danger";
    } else {
        $newHash = password_hash($newPass, PASSWORD_DEFAULT);
        $upd = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
        if ($upd->execute([$newHash, $userId])) {
            setFlash('success', 'Kata sandi akun Anda berhasil diperbarui dengan aman!');
            header("Location: " . BASE_URL . "pengunjung/profil.php");
            exit;
        } else {
            $msg = "Gagal memperbarui kata sandi.";
            $msgType = "danger";
        }
    }
}

// 4. Statistik Member & Keuangan
$totalTiket = (int)$pdo->query("SELECT COUNT(*) FROM pemesanan WHERE user_id = $userId")->fetchColumn();
$totalLunas = (int)$pdo->query("SELECT COUNT(*) FROM pemesanan WHERE user_id = $userId AND status_bayar = 'lunas'")->fetchColumn();
$totalUlasan = (int)$pdo->query("SELECT COUNT(*) FROM ulasan WHERE user_id = $userId")->fetchColumn();
$totalNominal = (float)$pdo->query("SELECT COALESCE(SUM(total_bayar), 0) FROM pemesanan WHERE user_id = $userId AND status_bayar = 'lunas'")->fetchColumn();

// Handle Foto URL (remote Unsplash atau upload lokal)
$userFoto = trim($user['foto'] ?? '');
$hasCustomFoto = false;
$fotoUrl = '';

if (!empty($userFoto) && $userFoto !== 'default_avatar.png') {
    if (strpos($userFoto, 'http://') === 0 || strpos($userFoto, 'https://') === 0) {
        $hasCustomFoto = true;
        $fotoUrl = $userFoto;
    } elseif (file_exists(__DIR__ . '/../assets/uploads/users/' . $userFoto)) {
        $hasCustomFoto = true;
        $fotoUrl = BASE_URL . 'assets/uploads/users/' . htmlspecialchars($userFoto);
    }
}

$initial = strtoupper(substr($user['nama'], 0, 1));
$joinDate = !empty($user['created_at']) ? date('d M Y', strtotime($user['created_at'])) : date('d M Y');

$pageTitle = "Pengaturan Profil & Keamanan Wisatawan";
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';
$flash = getFlash();
?>

<style>
  /* =========================================================
     LUXURY STYLING FOR BERKELAS PROFILE SETTINGS
     ========================================================= */
  .profile-page-wrap {
    padding: 0.25rem 0.1rem 2.5rem 0.1rem;
  }

  /* Executive Member Card */
  .executive-card-vip {
    background: #ffffff;
    border-radius: 1rem;
    border: 1px solid #e2e8f0;
    box-shadow: 0 4px 20px -2px rgba(0, 0, 0, 0.05);
    overflow: hidden;
    position: sticky;
    top: 1rem;
  }

  .executive-header-banner {
    background: linear-gradient(135deg, #0b1329 0%, #0f172a 50%, #0f766e 100%);
    padding: 2.25rem 1.5rem 1.5rem 1.5rem;
    text-align: center;
    position: relative;
  }

  .executive-header-banner::after {
    content: '';
    position: absolute;
    bottom: 0;
    left: 0;
    right: 0;
    height: 16px;
    background: #ffffff;
    border-top-left-radius: 1rem;
    border-top-right-radius: 1rem;
  }

  /* Avatar with Luxury Ring */
  .avatar-ring-container {
    position: relative;
    width: 110px;
    height: 110px;
    margin: 0 auto;
    z-index: 2;
  }

  .avatar-circle-img {
    width: 110px;
    height: 110px;
    border-radius: 50%;
    object-fit: cover;
    border: 3.5px solid #ffffff;
    box-shadow: 0 8px 24px rgba(15, 118, 110, 0.45);
    background: #0f172a;
    display: block;
  }

  .avatar-circle-initial {
    width: 110px;
    height: 110px;
    border-radius: 50%;
    background: linear-gradient(135deg, #0d9488 0%, #0284c7 100%);
    color: #ffffff;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 2.8rem;
    font-weight: 900;
    border: 3.5px solid #ffffff;
    box-shadow: 0 8px 24px rgba(15, 118, 110, 0.45);
    font-family: 'Outfit', sans-serif;
  }

  .avatar-online-dot {
    position: absolute;
    bottom: 4px;
    right: 4px;
    width: 22px;
    height: 22px;
    border-radius: 50%;
    background: #10b981;
    border: 3px solid #ffffff;
    box-shadow: 0 2px 8px rgba(0,0,0,0.25);
  }

  /* Member Badge */
  .badge-vip-pill {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    background: #fef3c7;
    color: #92400e;
    border: 1px solid #fde68a;
    font-size: 0.72rem;
    font-weight: 800;
    padding: 0.25rem 0.8rem;
    border-radius: 9999px;
    letter-spacing: 0.02em;
  }

  /* Travel Stats Mini Grid */
  .stats-matrix-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 0.65rem;
    padding: 1.15rem 1.25rem 1.35rem 1.25rem;
    border-top: 1px solid #f1f5f9;
  }

  .stat-matrix-box {
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 0.65rem;
    padding: 0.65rem 0.5rem;
    text-align: center;
    transition: transform 0.2s, border-color 0.2s;
  }

  .stat-matrix-box:hover {
    transform: translateY(-2px);
    border-color: #cbd5e1;
  }

  .stat-matrix-box span {
    font-size: 0.65rem;
    color: #64748b;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    display: block;
    margin-bottom: 0.15rem;
  }

  .stat-matrix-box strong {
    font-size: 1.15rem;
    font-weight: 900;
    font-family: 'Outfit', sans-serif;
    line-height: 1.1;
    display: block;
  }

  /* Form Luxury Cards */
  .card-form-luxury {
    background: #ffffff;
    border-radius: 1rem;
    border: 1px solid #e2e8f0;
    box-shadow: 0 4px 20px -2px rgba(0, 0, 0, 0.04);
    padding: 1.45rem 1.55rem;
    margin-bottom: 1.25rem;
  }

  .form-header-bar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding-bottom: 0.85rem;
    margin-bottom: 1.25rem;
    border-bottom: 1px solid #f1f5f9;
  }

  .form-header-icon {
    width: 38px;
    height: 38px;
    border-radius: 0.65rem;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.15rem;
    flex-shrink: 0;
  }

  /* Input Styling */
  .label-berkelas {
    display: block;
    font-size: 0.74rem;
    font-weight: 800;
    color: #334155;
    margin-bottom: 0.4rem;
    text-transform: uppercase;
    letter-spacing: 0.04em;
  }

  .input-berkelas-wrap {
    position: relative;
    display: flex;
    align-items: center;
  }

  .input-berkelas-wrap i.prefix-icon {
    position: absolute;
    left: 1rem;
    color: #94a3b8;
    font-size: 0.95rem;
    pointer-events: none;
  }

  .input-control-berkelas {
    width: 100%;
    height: 42px;
    padding: 0.45rem 1rem 0.45rem 2.65rem;
    border-radius: 0.55rem;
    border: 1px solid #cbd5e1;
    background: #ffffff;
    font-size: 0.88rem;
    color: #0f172a;
    font-family: inherit;
    outline: none;
    transition: all 0.2s;
  }

  .input-control-berkelas:focus {
    border-color: #0f766e;
    box-shadow: 0 0 0 3px rgba(15, 118, 110, 0.15);
  }

  .btn-toggle-eye {
    position: absolute;
    right: 0.75rem;
    background: none;
    border: none;
    color: #94a3b8;
    cursor: pointer;
    font-size: 0.9rem;
    padding: 0.35rem;
  }

  .btn-toggle-eye:hover {
    color: #0f766e;
  }

  /* Buttons */
  .btn-submit-teal {
    background: #0f766e;
    color: #ffffff;
    border: none;
    border-radius: 0.55rem;
    height: 40px;
    padding: 0 1.5rem;
    font-weight: 800;
    font-size: 0.84rem;
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    cursor: pointer;
    box-shadow: 0 3px 12px rgba(15, 118, 110, 0.3);
    transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
  }

  .btn-submit-teal:hover {
    background: #115e59;
    transform: translateY(-1px);
    box-shadow: 0 6px 16px rgba(15, 118, 110, 0.4);
    color: #ffffff;
  }

  .btn-submit-amber {
    background: #d97706;
    color: #ffffff;
    border: none;
    border-radius: 0.55rem;
    height: 40px;
    padding: 0 1.5rem;
    font-weight: 800;
    font-size: 0.84rem;
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    cursor: pointer;
    box-shadow: 0 3px 12px rgba(217, 119, 6, 0.3);
    transition: all 0.2s;
  }

  .btn-submit-amber:hover {
    background: #b45309;
    transform: translateY(-1px);
    box-shadow: 0 6px 16px rgba(217, 119, 6, 0.4);
    color: #ffffff;
  }

  /* Password Strength Meter */
  .pwd-strength-bar {
    height: 4px;
    border-radius: 2px;
    background: #e2e8f0;
    margin-top: 0.45rem;
    overflow: hidden;
  }

  .pwd-strength-fill {
    height: 100%;
    width: 0%;
    transition: width 0.3s ease, background-color 0.3s ease;
  }

  /* VIP Perks Banner */
  .vip-perks-box {
    background: linear-gradient(135deg, #0b1329 0%, #1e293b 100%);
    border-radius: 1rem;
    padding: 1.25rem 1.45rem;
    color: #ffffff;
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.12);
  }

  .vip-perk-item {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 0.4rem 0;
    font-size: 0.82rem;
    color: #cbd5e1;
  }

  .vip-perk-item i {
    width: 24px;
    height: 24px;
    border-radius: 50%;
    background: rgba(15, 118, 110, 0.4);
    color: #2dd4bf;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.75rem;
    flex-shrink: 0;
  }
</style>

<main class="admin-main">

  <!-- Topbar Luxury -->
  <div class="admin-topbar-luxury">
    <div>
      <div style="display: flex; align-items: center; gap: 0.45rem; margin-bottom: 0.15rem;">
        <span class="badge-vip-pill">
          <i class="fa-solid fa-crown text-amber-500"></i> VIP Member Portal
        </span>
        <span style="font-size: 0.75rem; color: #64748b;">
          ID Akun: #MEM-<?= str_pad($userId, 4, '0', STR_PAD_LEFT) ?>
        </span>
      </div>
      <h1 style="font-size: 1.35rem; font-weight: 800; color: #0f172a; margin: 0; letter-spacing: -0.02em; font-family: 'Outfit', sans-serif;">
        Pengaturan Profil & Keamanan Akun
      </h1>
      <p style="font-size: 0.78rem; color: #64748b; margin: 0.15rem 0 0 0;">
        Kelola identitas diri resmi, foto profil berkelas, dan proteksi kata sandi untuk kenyamanan reservasi wisata.
      </p>
    </div>

    <div>
      <a href="<?= BASE_URL ?>pengunjung/index.php" class="btn btn-secondary btn-xs" style="background: #ffffff; border: 1px solid #cbd5e1; font-weight: 700; height: 32px; padding: 0 0.85rem; border-radius: 0.5rem; display: inline-flex; align-items: center; gap: 0.4rem;">
        <i class="fa-solid fa-house-chimney-window text-teal-600"></i> Dashboard Saya
      </a>
    </div>
  </div>

  <!-- Flash Messages -->
  <?php if ($flash): ?>
    <div class="alert alert-<?= $flash['type'] ?> mb-3" style="border-radius: 0.65rem; padding: 0.75rem 1rem; display: flex; align-items: center; gap: 0.75rem; box-shadow: 0 2px 8px rgba(0,0,0,0.04); font-size: 0.85rem;">
      <i class="fa-solid <?= $flash['type'] === 'success' ? 'fa-circle-check text-emerald-600' : 'fa-circle-exclamation text-rose-600' ?>" style="font-size: 1.25rem;"></i>
      <div style="line-height: 1.4;"><?= $flash['message'] ?></div>
    </div>
  <?php endif; ?>

  <?php if ($msg): ?>
    <div class="alert alert-<?= $msgType ?> mb-3" style="border-radius: 0.65rem; padding: 0.75rem 1rem; display: flex; align-items: center; gap: 0.75rem; box-shadow: 0 2px 8px rgba(0,0,0,0.04); font-size: 0.85rem;">
      <i class="fa-solid <?= $msgType === 'success' ? 'fa-circle-check text-emerald-600' : 'fa-circle-exclamation text-rose-600' ?>" style="font-size: 1.25rem;"></i>
      <div style="line-height: 1.4;"><?= $msg ?></div>
    </div>
  <?php endif; ?>

  <div class="profile-page-wrap">
    <div class="grid grid-cols-12 gap-4">

      <!-- LEFT COLUMN: EXECUTIVE VIP CARD & STATS (4 Cols) -->
      <div class="col-span-12 lg:col-span-4" style="grid-column: span 4 / span 4;">
        
        <div class="executive-card-vip">
          
          <!-- Banner Header & Avatar Ring -->
          <div class="executive-header-banner">
            <div class="avatar-ring-container">
              <?php if ($hasCustomFoto): ?>
                <img src="<?= htmlspecialchars($fotoUrl) ?>" alt="<?= htmlspecialchars($user['nama']) ?>" id="previewAvatarImg" class="avatar-circle-img" onerror="this.style.display='none'; document.getElementById('initialAvatarBox').style.display='flex';">
                <div id="initialAvatarBox" class="avatar-circle-initial" style="display: none;">
                  <?= $initial ?>
                </div>
              <?php else: ?>
                <div id="initialAvatarBox" class="avatar-circle-initial">
                  <?= $initial ?>
                </div>
                <img src="" alt="Preview" id="previewAvatarImg" class="avatar-circle-img" style="display: none;">
              <?php endif; ?>
              <span class="avatar-online-dot" title="Status Akun Aktif"></span>
            </div>
          </div>

          <!-- User Identification Info -->
          <div style="padding: 0.5rem 1.25rem 1.25rem 1.25rem; text-align: center;">
            <h2 style="font-size: 1.2rem; font-weight: 800; color: #0f172a; margin: 0 0 0.25rem 0; font-family: 'Outfit', sans-serif;">
              <?= htmlspecialchars($user['nama']) ?>
            </h2>
            
            <span style="font-size: 0.8rem; color: #64748b; display: block; margin-bottom: 0.65rem;">
              <i class="fa-solid fa-envelope text-primary" style="margin-right: 0.25rem;"></i>
              <?= htmlspecialchars($user['email']) ?>
            </span>

            <div style="display: flex; justify-content: center; gap: 0.35rem; flex-wrap: wrap; margin-bottom: 0.85rem;">
              <span class="badge-vip-pill">
                <i class="fa-solid fa-gem text-amber-500"></i> Wisatawan VIP Prioritas
              </span>
            </div>

            <span style="display: block; font-size: 0.72rem; color: #94a3b8; margin-bottom: 1rem;">
              <i class="fa-solid fa-calendar-check" style="margin-right: 0.25rem;"></i> Member Terdaftar: <?= $joinDate ?>
            </span>

            <!-- Photo Upload Action Controls -->
            <form action="<?= BASE_URL ?>pengunjung/profil.php" method="POST" enctype="multipart/form-data" id="photoUploadForm" style="border-top: 1px dashed #cbd5e1; padding-top: 0.95rem;">
              <input type="hidden" name="action_update_photo" value="1">

              <label for="fotoProfilInput" class="btn-submit-teal" style="cursor: pointer; width: 100%; justify-content: center; height: 36px; border-radius: 0.5rem; font-size: 0.8rem; margin-bottom: 0.5rem;">
                <i class="fa-solid fa-camera"></i> Ganti Foto Profil
              </label>
              <input type="file" name="foto_profil" id="fotoProfilInput" accept="image/jpeg,image/png,image/webp,image/jpg" style="display: none;" onchange="handlePreviewPhoto(this)">

              <div id="uploadActionsContainer" style="display: none; gap: 0.4rem; margin-bottom: 0.5rem;">
                <button type="submit" class="btn-submit-teal" style="flex: 1; height: 36px; font-size: 0.8rem; justify-content: center;">
                  <i class="fa-solid fa-cloud-arrow-up"></i> Simpan Foto
                </button>
                <button type="button" class="btn btn-secondary btn-sm" onclick="cancelPreviewPhoto()" style="height: 36px; border-radius: 0.5rem; padding: 0 0.85rem;" title="Batal">
                  <i class="fa-solid fa-xmark"></i>
                </button>
              </div>

              <?php if ($hasCustomFoto): ?>
                <button type="submit" name="remove_photo" value="1" class="btn btn-sm btn-light btn-block" style="font-size: 0.75rem; border-radius: 0.5rem; color: #e11d48; width: 100%; height: 32px; border: 1px solid #fecdd3;" onclick="return confirm('Apakah Anda yakin ingin menghapus foto profil kustom?')">
                  <i class="fa-solid fa-trash-can" style="margin-right: 0.3rem;"></i> Reset ke Avatar Bawaan
                </button>
              <?php endif; ?>

              <small style="display: block; font-size: 0.68rem; color: #94a3b8; margin-top: 0.5rem;">
                Format JPG, PNG, WEBP (Maks. 5MB)
              </small>
            </form>

          </div>

          <!-- Travel Matrix Stats (4 Indicators) -->
          <div class="stats-matrix-grid">
            <div class="stat-matrix-box">
              <span>Total Pesanan</span>
              <strong style="color: #0284c7;"><?= $totalTiket ?></strong>
            </div>
            <div class="stat-matrix-box">
              <span>Tiket Lunas</span>
              <strong style="color: #0d9488;"><?= $totalLunas ?></strong>
            </div>
            <div class="stat-matrix-box">
              <span>Ulasan Dibuat</span>
              <strong style="color: #f59e0b;"><?= $totalUlasan ?></strong>
            </div>
            <div class="stat-matrix-box">
              <span>Transaksi Lunas</span>
              <strong style="color: #10b981; font-size: 0.88rem;">Rp <?= number_format($totalNominal, 0, ',', '.') ?></strong>
            </div>
          </div>

        </div>

      </div>

      <!-- RIGHT COLUMN: BIODATA & KEAMANAN CONFIGURATION (8 Cols) -->
      <div class="col-span-12 lg:col-span-8" style="grid-column: span 8 / span 8;">

        <!-- Card 1: Biodata Identitas -->
        <div class="card-form-luxury">
          <div class="form-header-bar">
            <div style="display: flex; align-items: center; gap: 0.65rem;">
              <div class="form-header-icon" style="background: #ccfbf1; color: #0f766e;">
                <i class="fa-solid fa-address-card"></i>
              </div>
              <div>
                <h3 style="font-size: 1.05rem; font-weight: 800; color: #0f172a; margin: 0; font-family: 'Outfit', sans-serif;">
                  Biodata Identitas Wisatawan
                </h3>
                <span style="font-size: 0.72rem; color: #64748b;">
                  Data ini disinkronkan otomatis pada lembar E-Ticket & faktur reservasi Anda.
                </span>
              </div>
            </div>
          </div>

          <form action="<?= BASE_URL ?>pengunjung/profil.php" method="POST">
            <input type="hidden" name="action_update_profile" value="1">

            <div class="grid grid-cols-2 gap-3 mb-3">
              <div>
                <label class="label-berkelas">
                  Nama Lengkap Sesuai KTP/Paspor <span style="color: #ef4444;">*</span>
                </label>
                <div class="input-berkelas-wrap">
                  <i class="fa-solid fa-user prefix-icon"></i>
                  <input type="text" name="nama" value="<?= htmlspecialchars($user['nama']) ?>" class="input-control-berkelas" placeholder="Nama Lengkap" required>
                </div>
              </div>

              <div>
                <label class="label-berkelas">
                  Alamat Email Akun <span style="color: #ef4444;">*</span>
                </label>
                <div class="input-berkelas-wrap">
                  <i class="fa-solid fa-envelope prefix-icon"></i>
                  <input type="email" name="email" value="<?= htmlspecialchars($user['email']) ?>" class="input-control-berkelas" placeholder="nama@email.com" required>
                </div>
              </div>
            </div>

            <div class="grid grid-cols-2 gap-3 mb-4">
              <div>
                <label class="label-berkelas">
                  Nomor WhatsApp Aktif <span style="color: #ef4444;">*</span>
                </label>
                <div class="input-berkelas-wrap">
                  <i class="fa-brands fa-whatsapp prefix-icon" style="color: #16a34a;"></i>
                  <input type="text" name="no_telp" value="<?= htmlspecialchars($user['no_telp'] ?? '') ?>" placeholder="081234567890" class="input-control-berkelas" required>
                </div>
                <small style="display: block; font-size: 0.68rem; color: #94a3b8; margin-top: 0.35rem;">
                  Notifikasi konfirmasi pembayaran & QR Code akan dikirim ke nomor ini.
                </small>
              </div>

              <div>
                <label class="label-berkelas">Role Hak Akses Sistem</label>
                <div class="input-berkelas-wrap">
                  <i class="fa-solid fa-user-shield prefix-icon"></i>
                  <input type="text" value="Wisatawan VIP (Pengunjung Portal)" class="input-control-berkelas" style="background: #f8fafc; color: #64748b; font-weight: 700; cursor: not-allowed;" readonly>
                </div>
              </div>
            </div>

            <div style="display: flex; justify-content: flex-end;">
              <button type="submit" class="btn-submit-teal">
                <i class="fa-solid fa-floppy-disk"></i> Simpan Perubahan Biodata
              </button>
            </div>
          </form>
        </div>

        <!-- Card 2: Keamanan & Kata Sandi -->
        <div class="card-form-luxury">
          <div class="form-header-bar">
            <div style="display: flex; align-items: center; gap: 0.65rem;">
              <div class="form-header-icon" style="background: #fef3c7; color: #d97706;">
                <i class="fa-solid fa-shield-halved"></i>
              </div>
              <div>
                <h3 style="font-size: 1.05rem; font-weight: 800; color: #0f172a; margin: 0; font-family: 'Outfit', sans-serif;">
                  Keamanan & Kata Sandi Akun
                </h3>
                <span style="font-size: 0.72rem; color: #64748b;">
                  Gunakan kombinasi sandi yang kuat dan ubah secara berkala untuk menjaga akun Anda.
                </span>
              </div>
            </div>
          </div>

          <form action="<?= BASE_URL ?>pengunjung/profil.php" method="POST" id="formPassword">
            <input type="hidden" name="action_update_password" value="1">

            <!-- Password Lama -->
            <div class="form-group mb-3">
              <label class="label-berkelas">
                Kata Sandi Saat Ini <span style="color: #ef4444;">*</span>
              </label>
              <div class="input-berkelas-wrap">
                <i class="fa-solid fa-key prefix-icon"></i>
                <input type="password" name="password_lama" id="pwdOld" class="input-control-berkelas" placeholder="Ketik kata sandi saat ini" required>
                <button type="button" class="btn-toggle-eye" onclick="togglePwdVisibility('pwdOld', this)">
                  <i class="fa-solid fa-eye"></i>
                </button>
              </div>
            </div>

            <!-- Password Baru & Konfirmasi -->
            <div class="grid grid-cols-2 gap-3 mb-2">
              <div>
                <label class="label-berkelas">
                  Kata Sandi Baru <span style="color: #ef4444;">*</span>
                </label>
                <div class="input-berkelas-wrap">
                  <i class="fa-solid fa-lock prefix-icon"></i>
                  <input type="password" name="password_baru" id="pwdNew" class="input-control-berkelas" placeholder="Minimal 6 karakter" minlength="6" required oninput="evaluatePwdStrength(this.value)">
                  <button type="button" class="btn-toggle-eye" onclick="togglePwdVisibility('pwdNew', this)">
                    <i class="fa-solid fa-eye"></i>
                  </button>
                </div>
                <!-- Strength Indicator -->
                <div class="pwd-strength-bar">
                  <div id="pwdStrengthFill" class="pwd-strength-fill"></div>
                </div>
                <div style="display: flex; justify-content: space-between; font-size: 0.68rem; color: #94a3b8; margin-top: 0.25rem;">
                  <span>Kekuatan sandi:</span>
                  <strong id="pwdStrengthLabel" style="color: #64748b;">-</strong>
                </div>
              </div>

              <div>
                <label class="label-berkelas">
                  Ulangi Kata Sandi Baru <span style="color: #ef4444;">*</span>
                </label>
                <div class="input-berkelas-wrap">
                  <i class="fa-solid fa-circle-check prefix-icon"></i>
                  <input type="password" name="konfirmasi_password" id="pwdConfirm" class="input-control-berkelas" placeholder="Ketik ulang kata sandi baru" minlength="6" required oninput="checkMatchPwd()">
                  <button type="button" class="btn-toggle-eye" onclick="togglePwdVisibility('pwdConfirm', this)">
                    <i class="fa-solid fa-eye"></i>
                  </button>
                </div>
                <small id="pwdMatchMsg" style="display: block; font-size: 0.68rem; color: #94a3b8; margin-top: 0.35rem;">
                  Pastikan kata sandi konfirmasi cocok.
                </small>
              </div>
            </div>

            <div style="display: flex; justify-content: flex-end; margin-top: 1rem;">
              <button type="submit" class="btn-submit-amber">
                <i class="fa-solid fa-shield-check"></i> Perbarui Kata Sandi
              </button>
            </div>
          </form>
        </div>

        <!-- Card 3: VIP Perks & Informasi Fasilitas Reservasi -->
        <div class="vip-perks-box">
          <div style="display: flex; align-items: center; gap: 0.65rem; margin-bottom: 0.85rem;">
            <div style="width: 32px; height: 32px; border-radius: 0.5rem; background: rgba(45, 212, 191, 0.2); color: #2dd4bf; display: flex; align-items: center; justify-content: center; font-size: 1rem;">
              <i class="fa-solid fa-star"></i>
            </div>
            <div>
              <h4 style="font-size: 0.95rem; font-weight: 800; margin: 0; font-family: 'Outfit', sans-serif;">
                Keuntungan & Hak Istimewa Akun VIP
              </h4>
              <span style="font-size: 0.72rem; color: #94a3b8;">Nikmati kemudahan eksklusif selama berlibur di seluruh destinasi Pesona Nusantara</span>
            </div>
          </div>

          <div class="grid grid-cols-2 gap-2" style="border-top: 1px solid rgba(255,255,255,0.1); padding-top: 0.65rem;">
            <div class="vip-perk-item">
              <i class="fa-solid fa-qrcode"></i>
              <span>Akses Fast-Track Check-In QR Code di pos gerbang loket resmi</span>
            </div>
            <div class="vip-perk-item">
              <i class="fa-solid fa-percent"></i>
              <span>Diskon rombongan otomatis hingga 20% langsung dihitung</span>
            </div>
            <div class="vip-perk-item">
              <i class="fa-solid fa-shield-halved"></i>
              <span>Keamanan enkripsi transaksi standar perbankan & BCRYPT</span>
            </div>
            <div class="vip-perk-item">
              <i class="fa-solid fa-file-invoice"></i>
              <span>E-Ticket & riwayat transaksi dapat diunduh dan dicetak kapan saja</span>
            </div>
          </div>
        </div>

      </div>

    </div>
  </div>

</main>

<script>
// 1. Preview Foto Profil
function handlePreviewPhoto(input) {
  if (input.files && input.files[0]) {
    const file = input.files[0];
    if (file.size > 5 * 1024 * 1024) {
      alert('Ukuran berkas foto terlalu besar. Maksimal 5MB.');
      input.value = '';
      return;
    }

    const reader = new FileReader();
    reader.onload = function(e) {
      const previewImg = document.getElementById('previewAvatarImg');
      const initialBox = document.getElementById('initialAvatarBox');
      const actionsContainer = document.getElementById('uploadActionsContainer');

      if (initialBox) initialBox.style.display = 'none';
      if (previewImg) {
        previewImg.src = e.target.result;
        previewImg.style.display = 'block';
      }
      if (actionsContainer) {
        actionsContainer.style.display = 'flex';
      }
    };
    reader.readAsDataURL(file);
  }
}

function cancelPreviewPhoto() {
  const input = document.getElementById('fotoProfilInput');
  const previewImg = document.getElementById('previewAvatarImg');
  const initialBox = document.getElementById('initialAvatarBox');
  const actionsContainer = document.getElementById('uploadActionsContainer');

  if (input) input.value = '';
  if (actionsContainer) actionsContainer.style.display = 'none';

  <?php if ($hasCustomFoto): ?>
    if (previewImg) {
      previewImg.src = '<?= htmlspecialchars($fotoUrl) ?>';
      previewImg.style.display = 'block';
    }
    if (initialBox) initialBox.style.display = 'none';
  <?php else: ?>
    if (previewImg) previewImg.style.display = 'none';
    if (initialBox) initialBox.style.display = 'flex';
  <?php endif; ?>
}

// 2. Toggle Password Visibility
function togglePwdVisibility(inputId, btn) {
  const input = document.getElementById(inputId);
  if (!input) return;
  const icon = btn.querySelector('i');
  if (input.type === 'password') {
    input.type = 'text';
    icon.classList.remove('fa-eye');
    icon.classList.add('fa-eye-slash');
  } else {
    input.type = 'password';
    icon.classList.remove('fa-eye-slash');
    icon.classList.add('fa-eye');
  }
}

// 3. Password Strength Evaluation
function evaluatePwdStrength(val) {
  const fill = document.getElementById('pwdStrengthFill');
  const label = document.getElementById('pwdStrengthLabel');
  if (!val) {
    fill.style.width = '0%';
    label.innerText = '-';
    label.style.color = '#64748b';
    return;
  }

  let score = 0;
  if (val.length >= 6) score += 25;
  if (val.length >= 10) score += 25;
  if (/[A-Z]/.test(val) && /[a-z]/.test(val)) score += 25;
  if (/[0-9]/.test(val) && /[^A-Za-z0-9]/.test(val)) score += 25;

  fill.style.width = score + '%';

  if (score <= 25) {
    fill.style.backgroundColor = '#ef4444';
    label.innerText = 'Lemah';
    label.style.color = '#ef4444';
  } else if (score <= 50) {
    fill.style.backgroundColor = '#f59e0b';
    label.innerText = 'Cukup';
    label.style.color = '#f59e0b';
  } else if (score <= 75) {
    fill.style.backgroundColor = '#0284c7';
    label.innerText = 'Kuat';
    label.style.color = '#0284c7';
  } else {
    fill.style.backgroundColor = '#10b981';
    label.innerText = 'Sangat Kuat';
    label.style.color = '#10b981';
  }

  checkMatchPwd();
}

// 4. Check Match Password
function checkMatchPwd() {
  const p1 = document.getElementById('pwdNew').value;
  const p2 = document.getElementById('pwdConfirm').value;
  const msg = document.getElementById('pwdMatchMsg');

  if (!p2) {
    msg.innerText = 'Pastikan kata sandi konfirmasi cocok.';
    msg.style.color = '#94a3b8';
    return;
  }

  if (p1 === p2) {
    msg.innerHTML = '<i class="fa-solid fa-circle-check text-emerald-600"></i> Kata sandi cocok!';
    msg.style.color = '#10b981';
  } else {
    msg.innerHTML = '<i class="fa-solid fa-circle-xmark text-rose-600"></i> Kata sandi belum cocok.';
    msg.style.color = '#ef4444';
  }
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
