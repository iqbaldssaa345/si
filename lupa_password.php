<?php
require_once __DIR__ . '/config/database.php';

// Pastikan kolom username tersedia di database (auto migration)
try {
    $pdo->exec("ALTER TABLE `users` ADD COLUMN `username` VARCHAR(100) NULL AFTER `nama`");
    $pdo->exec("UPDATE `users` SET `username` = SUBSTRING_INDEX(email, '@', 1) WHERE `username` IS NULL OR `username` = ''");
} catch (Exception $e) {}

// Jika sudah login, redirect
if (isset($_SESSION['user_id'])) {
    if ($_SESSION['user_role'] === 'admin') {
        header("Location: " . BASE_URL . "admin/index.php");
    } elseif ($_SESSION['user_role'] === 'petugas') {
        header("Location: " . BASE_URL . "petugas/index.php");
    } else {
        header("Location: " . BASE_URL . "index.php");
    }
    exit;
}

$step = 1; // Step 1: Input Nama & Username, Step 2: Input Password Baru, Step 3: Sukses
$error = '';
$success = '';
$matchedUser = null;

$namaInput = trim($_POST['nama'] ?? '');
$usernameInput = trim($_POST['username'] ?? '');
$userIdSession = $_SESSION['reset_user_id'] ?? 0;

// Handle Form Submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'identify';

    if ($action === 'identify') {
        if (empty($namaInput) || empty($usernameInput)) {
            $error = "Silakan masukkan Nama Lengkap dan Username akun Anda.";
        } else {
            // Cari kecocokan data pengguna berdasarkan Nama dan Username (atau Email prefix)
            $stmt = $pdo->prepare("SELECT * FROM users 
                                    WHERE LOWER(TRIM(nama)) = LOWER(TRIM(?)) 
                                    AND (
                                        LOWER(TRIM(username)) = LOWER(TRIM(?)) 
                                        OR LOWER(TRIM(email)) = LOWER(TRIM(?)) 
                                        OR LOWER(TRIM(SUBSTRING_INDEX(email, '@', 1))) = LOWER(TRIM(?))
                                    ) 
                                    LIMIT 1");
            $stmt->execute([$namaInput, $usernameInput, $usernameInput, $usernameInput]);
            $user = $stmt->fetch();

            if ($user) {
                $_SESSION['reset_user_id'] = $user['id'];
                $_SESSION['reset_user_nama'] = $user['nama'];
                $_SESSION['reset_user_username'] = !empty($user['username']) ? $user['username'] : explode('@', $user['email'])[0];
                $_SESSION['reset_user_email'] = $user['email'];
                $_SESSION['reset_user_role'] = $user['role'];
                $_SESSION['reset_user_foto'] = $user['foto'] ?? 'default_avatar.png';
                $step = 2;
                $matchedUser = $user;
            } else {
                $error = "Data tidak cocok! Tidak ditemukan akun dengan kombinasi Nama dan Username tersebut. Pastikan ejaan nama dan username sudah benar.";
            }
        }
    } elseif ($action === 'reset_password') {
        $newPassword = $_POST['new_password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';
        $resetId = (int)($_SESSION['reset_user_id'] ?? 0);

        if ($resetId <= 0) {
            $error = "Sesi reset kata sandi telah kedaluwarsa. Silakan ulangi identifikasi akun Anda.";
            $step = 1;
        } elseif (empty($newPassword) || empty($confirmPassword)) {
            $error = "Silakan isi kata sandi baru dan konfirmasi kata sandi.";
            $step = 2;
        } elseif (strlen($newPassword) < 6) {
            $error = "Kata sandi baru minimal harus terdiri dari 6 karakter demi keamanan akun Anda.";
            $step = 2;
        } elseif ($newPassword !== $confirmPassword) {
            $error = "Konfirmasi kata sandi tidak cocok. Pastikan kedua kolom kata sandi sama persis.";
            $step = 2;
        } else {
            // Update password pengguna dengan hash BCRYPT yang aman
            $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
            $upd = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
            if ($upd->execute([$hashedPassword, $resetId])) {
                $step = 3;
                $success = "Kata sandi akun Anda berhasil diperbarui! Anda sekarang dapat masuk menggunakan kata sandi baru.";
                // Bersihkan sesi reset
                unset($_SESSION['reset_user_id'], $_SESSION['reset_user_nama'], $_SESSION['reset_user_username'], $_SESSION['reset_user_email'], $_SESSION['reset_user_role'], $_SESSION['reset_user_foto']);
            } else {
                $error = "Gagal memperbarui kata sandi. Silakan coba beberapa saat lagi.";
                $step = 2;
            }
        }
    }
} elseif (isset($_GET['cancel'])) {
    unset($_SESSION['reset_user_id'], $_SESSION['reset_user_nama'], $_SESSION['reset_user_username'], $_SESSION['reset_user_email'], $_SESSION['reset_user_role'], $_SESSION['reset_user_foto']);
    header("Location: " . BASE_URL . "lupa_password.php");
    exit;
}

// Cek jika sudah berada di Step 2 dari session
if ($step === 1 && !empty($_SESSION['reset_user_id']) && empty($_POST)) {
    $step = 2;
}

$pageTitle = "Lupa Kata Sandi - Pemulihan Akun Resmi Pesona Nusantara";
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/navbar.php';
?>

<div class="auth-luxury-page">
  <div class="container auth-luxury-wrapper">
    
    <div class="auth-card-luxury">
      
      <!-- SISI KIRI: BRANDING & KEUNGGULAN PEMULIHAN AKUN -->
      <div class="auth-visual-side">
        <div class="auth-visual-content">
          <div class="auth-brand-badge">
            <i class="fa-solid fa-key text-amber"></i>
            <span>Pusat Pemulihan Akun Resmi</span>
          </div>

          <h1 class="auth-visual-title">
            Amankan & Pulihkan <br>
            <span class="text-gradient-gold">Akses Akun Wisata</span>
          </h1>

          <p class="auth-visual-desc">
            Fitur pemulihan kata sandi terverifikasi resmi. Cukup cocokkan <strong>Nama Lengkap</strong> dan <strong>Username</strong> akun terdaftar Anda untuk mengatur ulang kata sandi secara aman dan instan.
          </p>

          <!-- 3 Langkah Pemulihan Visual Card -->
          <div class="auth-steps-flow">
            
            <div class="auth-step-item <?= $step >= 1 ? 'active' : '' ?>">
              <div class="auth-step-circle">1</div>
              <div class="auth-step-text">
                <strong>Identifikasi Akun</strong>
                <span>Nama & Username</span>
              </div>
            </div>

            <div class="auth-step-item <?= $step >= 2 ? 'active' : '' ?>">
              <div class="auth-step-circle">2</div>
              <div class="auth-step-text">
                <strong>Kata Sandi Baru</strong>
                <span>Setel sandi baru aman</span>
              </div>
            </div>

            <div class="auth-step-item <?= $step >= 3 ? 'active' : '' ?>">
              <div class="auth-step-circle">3</div>
              <div class="auth-step-text">
                <strong>Akses Pulih</strong>
                <span>Langsung siap digunakan</span>
              </div>
            </div>

          </div>

          <div class="auth-testimonial-pill">
            <i class="fa-solid fa-shield-halved text-emerald"></i>
            <span>Verifikasi Akun Terproteksi Enkripsi SSL 256-Bit</span>
          </div>
        </div>
      </div>

      <!-- SISI KANAN: FORMULIR MULTI-STEP BERKELAS -->
      <div class="auth-form-side">
        
        <!-- Form Header -->
        <div class="auth-form-header">
          <div class="auth-logo-circle" style="background: linear-gradient(135deg, rgba(245, 158, 11, 0.2), rgba(13, 148, 136, 0.2)); border-color: rgba(245, 158, 11, 0.4); color: #f59e0b;">
            <i class="fa-solid <?= $step === 3 ? 'fa-circle-check text-emerald' : ($step === 2 ? 'fa-lock-open text-primary' : 'fa-user-shield') ?>"></i>
          </div>
          
          <h2 class="auth-form-title">
            <?php if ($step === 1): ?>
              Pemulihan Kata Sandi
            <?php elseif ($step === 2): ?>
              Atur Kata Sandi Baru
            <?php else: ?>
              Kata Sandi Diperbarui!
            <?php endif; ?>
          </h2>

          <p class="auth-form-subtitle">
            <?php if ($step === 1): ?>
              Masukkan Nama Lengkap dan Username akun Anda untuk verifikasi identitas
            <?php elseif ($step === 2): ?>
              Akun Anda telah teridentifikasi. Masukkan kata sandi baru Anda di bawah ini
            <?php else: ?>
              Akun Anda telah berhasil dipulihkan dan siap untuk digunakan kembali
            <?php endif; ?>
          </p>
        </div>

        <!-- Alert Pesan Error / Info -->
        <?php if ($error): ?>
          <div class="auth-alert auth-alert-danger">
            <i class="fa-solid fa-circle-exclamation auth-alert-icon"></i>
            <div><?= $error ?></div>
          </div>
        <?php endif; ?>

        <?php if ($step === 1): ?>
          <!-- ========================================================
               STEP 1: FORM IDENTIFIKASI (NAMA & USERNAME)
               ======================================================== -->
          <form method="POST" action="" autocomplete="on" id="forgotStep1Form">
            <input type="hidden" name="action" value="identify">
            
            <!-- Input Nama Lengkap -->
            <div class="auth-form-group">
              <label class="auth-label">Nama Lengkap Akun Terdaftar <span class="text-danger">*</span></label>
              <div class="auth-input-container">
                <i class="fa-solid fa-user-tag auth-input-icon"></i>
                <input type="text" name="nama" id="inputNama" class="auth-input-field" placeholder="Cth: Administrator atau Nama Lengkap Anda" required value="<?= htmlspecialchars($namaInput) ?>" autofocus>
              </div>
              <small class="auth-hint-text"><i class="fa-solid fa-circle-info"></i> Masukkan nama lengkap sesuai yang tertera saat pendaftaran akun.</small>
            </div>

            <!-- Input Username -->
            <div class="auth-form-group">
              <label class="auth-label">Username / ID Pengguna <span class="text-danger">*</span></label>
              <div class="auth-input-container">
                <i class="fa-solid fa-id-badge auth-input-icon"></i>
                <input type="text" name="username" id="inputUsername" class="auth-input-field" placeholder="Cth: admin, petugas, atau username / email" required value="<?= htmlspecialchars($usernameInput) ?>">
              </div>
              <small class="auth-hint-text"><i class="fa-solid fa-circle-info"></i> Dapat berupa username unik akun Anda atau alamat email terdaftar.</small>
            </div>

            <!-- Tombol Verifikasi Akun -->
            <button type="submit" class="auth-submit-btn" style="background: linear-gradient(135deg, #0d9488 0%, #0284c7 100%);">
              <span>Verifikasi Identitas Akun</span>
              <i class="fa-solid fa-magnifying-glass"></i>
            </button>

          </form>

        <?php elseif ($step === 2): ?>
          <!-- ========================================================
               STEP 2: FORM RESET KATA SANDI BARU
               ======================================================== -->
          
          <!-- Kartu Identitas Akun Terverifikasi -->
          <div class="verified-account-card">
            <div class="flex items-center gap-3">
              <div class="avatar" style="width: 44px; height: 44px; font-size: 1.15rem; background: linear-gradient(135deg, #0d9488, #0284c7); color: #fff; font-weight: 800; border-radius: 50%; display: flex; align-items: center; justify-content: center;">
                <?= strtoupper(substr($_SESSION['reset_user_nama'] ?? 'U', 0, 1)) ?>
              </div>
              <div style="flex: 1; overflow: hidden;">
                <div class="flex items-center gap-2">
                  <strong class="text-dark" style="font-size: 0.95rem; white-space: nowrap; text-overflow: ellipsis; overflow: hidden;">
                    <?= htmlspecialchars($_SESSION['reset_user_nama'] ?? '') ?>
                  </strong>
                  <span class="badge badge-success text-xs" style="padding: 0.15rem 0.5rem;">
                    <i class="fa-solid fa-check-double"></i> Terverifikasi
                  </span>
                </div>
                <div class="text-xs text-muted" style="margin-top: 0.15rem;">
                  Username: <span class="font-bold text-primary">@<?= htmlspecialchars($_SESSION['reset_user_username'] ?? '') ?></span> • 
                  Role: <span class="text-dark font-bold"><?= ucfirst($_SESSION['reset_user_role'] ?? 'Pengguna') ?></span>
                </div>
              </div>
            </div>
            <a href="<?= BASE_URL ?>lupa_password.php?cancel=1" class="btn-cancel-reset" title="Bukan akun Anda? Batalkan">
              <i class="fa-solid fa-xmark"></i>
            </a>
          </div>

          <form method="POST" action="" id="forgotStep2Form">
            <input type="hidden" name="action" value="reset_password">

            <!-- Kata Sandi Baru Input -->
            <div class="auth-form-group">
              <label class="auth-label">Kata Sandi Baru <span class="text-danger">*</span></label>
              <div class="auth-input-container">
                <i class="fa-solid fa-lock auth-input-icon"></i>
                <input type="password" name="new_password" id="newPasswordInput" class="auth-input-field" placeholder="Masukkan minimal 6 karakter kata sandi baru" required minlength="6" autofocus onkeyup="checkPasswordStrength(this.value)">
                <button type="button" class="auth-password-toggle" onclick="togglePasswordVisibility('newPasswordInput', 'eyeIconNew')" title="Tampilkan/Sembunyikan">
                  <i class="fa-regular fa-eye" id="eyeIconNew"></i>
                </button>
              </div>

              <!-- Real-Time Password Strength Meter -->
              <div class="password-meter-wrap" id="passwordMeterWrap">
                <div class="password-meter-bar">
                  <div class="password-meter-fill" id="passwordMeterFill"></div>
                </div>
                <span class="password-meter-text" id="passwordMeterText">Kekuatan Sandi: Belum diisi</span>
              </div>
            </div>

            <!-- Konfirmasi Kata Sandi Input -->
            <div class="auth-form-group">
              <label class="auth-label">Ulangi Kata Sandi Baru <span class="text-danger">*</span></label>
              <div class="auth-input-container">
                <i class="fa-solid fa-shield-check auth-input-icon"></i>
                <input type="password" name="confirm_password" id="confirmPasswordInput" class="auth-input-field" placeholder="Ketik ulang kata sandi baru Anda" required minlength="6" onkeyup="checkPasswordMatch()">
                <button type="button" class="auth-password-toggle" onclick="togglePasswordVisibility('confirmPasswordInput', 'eyeIconConfirm')" title="Tampilkan/Sembunyikan">
                  <i class="fa-regular fa-eye" id="eyeIconConfirm"></i>
                </button>
              </div>
              <div id="passwordMatchMessage" class="auth-hint-text"></div>
            </div>

            <!-- Submit Button -->
            <button type="submit" class="auth-submit-btn" style="background: linear-gradient(135deg, #10b981 0%, #059669 100%); box-shadow: 0 4px 15px rgba(16, 185, 129, 0.4);">
              <span>Simpan & Perbarui Kata Sandi</span>
              <i class="fa-solid fa-floppy-disk"></i>
            </button>

          </form>

        <?php elseif ($step === 3): ?>
          <!-- ========================================================
               STEP 3: SUKSES RESET PASSWORD
               ======================================================== -->
          <div class="reset-success-box text-center">
            
            <div class="success-icon-animation">
              <i class="fa-solid fa-circle-check"></i>
            </div>

            <h3 class="text-xl font-black text-dark mb-2">Pembaruan Kata Sandi Berhasil!</h3>
            <p class="text-sm text-slate-600 mb-6" style="line-height: 1.6;">
              Kata sandi akun Anda telah berhasil diubah dan dilindungi enkripsi terkini. Anda sekarang dapat masuk kembali ke sistem dengan kredensial baru Anda.
            </p>

            <a href="<?= BASE_URL ?>login.php" class="auth-submit-btn" style="background: linear-gradient(135deg, #0d9488 0%, #0284c7 100%); text-decoration: none; justify-content: center;">
              <span>Masuk ke Akun Sekarang</span>
              <i class="fa-solid fa-arrow-right-to-bracket"></i>
            </a>

          </div>

        <?php endif; ?>

        <!-- Footer Navigation Links -->
        <div class="auth-switch-box" style="margin-top: 2rem;">
          Sudah ingat kata sandi akun Anda? 
          <a href="<?= BASE_URL ?>login.php" class="auth-switch-link font-bold">Kembali ke Halaman Masuk</a>
        </div>

        <div class="auth-security-footnote">
          <i class="fa-solid fa-shield-halved text-emerald"></i>
          <span>Privasi & Keamanan Data Pengguna Pesona Nusantara Terjamin</span>
        </div>

      </div>

    </div>

  </div>
</div>

<style>
/* ==========================================================================
   ULTRA-LUXURY FORGOT PASSWORD STYLING
   ========================================================================== */
.auth-luxury-page {
  min-height: 86vh;
  display: flex;
  align-items: center;
  justify-content: center;
  background: radial-gradient(circle at 10% 20%, rgba(13, 148, 136, 0.08) 0%, transparent 45%),
              radial-gradient(circle at 90% 80%, rgba(245, 158, 11, 0.08) 0%, transparent 45%),
              #f8fafc;
  padding: 3rem 0;
}

.auth-card-luxury {
  background: #ffffff;
  border-radius: 1.75rem;
  border: 1px solid #e2e8f0;
  box-shadow: 0 20px 50px rgba(0, 0, 0, 0.06);
  display: grid;
  grid-template-columns: 1.15fr 1fr;
  overflow: hidden;
  max-width: 1040px;
  margin: 0 auto;
}

.auth-visual-side {
  background: radial-gradient(circle at 70% 30%, #042f2e 0%, #090d16 100%);
  padding: 3.5rem;
  color: #ffffff;
  position: relative;
  display: flex;
  flex-direction: column;
  justify-content: center;
}

.auth-brand-badge {
  display: inline-flex;
  align-items: center;
  gap: 0.5rem;
  padding: 0.4rem 1rem;
  background: rgba(255, 255, 255, 0.08);
  border: 1px solid rgba(255, 255, 255, 0.15);
  border-radius: 9999px;
  font-size: 0.8rem;
  font-weight: 700;
  color: #f1f5f9;
  margin-bottom: 1.5rem;
}

.auth-visual-title {
  font-family: 'Outfit', sans-serif;
  font-size: 2.35rem;
  font-weight: 900;
  line-height: 1.2;
  margin-bottom: 1.25rem;
  color: #ffffff;
}

.auth-visual-desc {
  font-size: 0.92rem;
  color: #94a3b8;
  line-height: 1.7;
  margin-bottom: 2rem;
}

.auth-steps-flow {
  display: flex;
  flex-direction: column;
  gap: 1rem;
  margin-bottom: 2rem;
}

.auth-step-item {
  display: flex;
  align-items: center;
  gap: 0.85rem;
  padding: 0.75rem 1rem;
  background: rgba(255, 255, 255, 0.04);
  border: 1px solid rgba(255, 255, 255, 0.08);
  border-radius: 0.85rem;
  transition: all 0.3s ease;
  opacity: 0.5;
}

.auth-step-item.active {
  opacity: 1;
  background: rgba(13, 148, 136, 0.15);
  border-color: rgba(45, 212, 191, 0.4);
}

.auth-step-circle {
  width: 28px;
  height: 28px;
  border-radius: 50%;
  background: rgba(255, 255, 255, 0.1);
  color: #ffffff;
  display: flex;
  align-items: center;
  justify-content: center;
  font-weight: 800;
  font-size: 0.8rem;
}

.auth-step-item.active .auth-step-circle {
  background: #0d9488;
  color: #ffffff;
}

.auth-step-text strong {
  display: block;
  font-size: 0.85rem;
  color: #f8fafc;
}

.auth-step-text span {
  font-size: 0.72rem;
  color: #94a3b8;
}

.auth-testimonial-pill {
  display: inline-flex;
  align-items: center;
  gap: 0.5rem;
  font-size: 0.78rem;
  color: #cbd5e1;
  background: rgba(255, 255, 255, 0.05);
  padding: 0.4rem 0.85rem;
  border-radius: 9999px;
}

/* Sisi Kanan: Form Styling */
.auth-form-side {
  padding: 3.5rem 3rem;
  display: flex;
  flex-direction: column;
  justify-content: center;
}

.auth-form-header {
  text-align: center;
  margin-bottom: 2rem;
}

.auth-logo-circle {
  width: 58px;
  height: 58px;
  border-radius: 16px;
  margin: 0 auto 1.25rem;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 1.5rem;
  border: 1.5px solid;
}

.auth-form-title {
  font-size: 1.65rem;
  font-weight: 800;
  color: #0f172a;
  margin-bottom: 0.4rem;
}

.auth-form-subtitle {
  font-size: 0.85rem;
  color: #64748b;
  line-height: 1.5;
}

.auth-alert {
  padding: 0.85rem 1.15rem;
  border-radius: 0.85rem;
  font-size: 0.85rem;
  display: flex;
  align-items: flex-start;
  gap: 0.75rem;
  margin-bottom: 1.5rem;
  line-height: 1.5;
}

.auth-alert-danger {
  background: #fee2e2;
  border: 1px solid #fecaca;
  color: #b91c1c;
}

.auth-alert-icon {
  font-size: 1.1rem;
  margin-top: 0.1rem;
  flex-shrink: 0;
}

.auth-form-group {
  margin-bottom: 1.25rem;
}

.auth-label {
  display: block;
  font-size: 0.78rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  color: #475569;
  margin-bottom: 0.4rem;
}

.auth-input-container {
  position: relative;
  display: flex;
  align-items: center;
}

.auth-input-icon {
  position: absolute;
  left: 1rem;
  color: #0d9488;
  font-size: 1rem;
  pointer-events: none;
}

.auth-input-field {
  width: 100%;
  padding: 0.85rem 2.8rem 0.85rem 2.75rem;
  background: #f8fafc;
  border: 1.5px solid #cbd5e1;
  border-radius: 0.85rem;
  font-size: 0.95rem;
  color: #0f172a;
  font-weight: 600;
  outline: none;
  transition: all 0.25s ease;
}

.auth-input-field:focus {
  background: #ffffff;
  border-color: #0d9488;
  box-shadow: 0 0 0 4px rgba(13, 148, 136, 0.15);
}

.auth-password-toggle {
  position: absolute;
  right: 1rem;
  background: transparent;
  border: none;
  color: #94a3b8;
  font-size: 1.1rem;
  cursor: pointer;
  padding: 0;
}

.auth-password-toggle:hover {
  color: #0f172a;
}

.auth-hint-text {
  font-size: 0.72rem;
  color: #64748b;
  margin-top: 0.35rem;
  display: block;
}

.auth-submit-btn {
  width: 100%;
  padding: 0.95rem 1.5rem;
  color: #ffffff;
  border: none;
  border-radius: 0.85rem;
  font-size: 0.95rem;
  font-weight: 800;
  display: flex;
  align-items: center;
  justify-content: space-between;
  cursor: pointer;
  transition: all 0.25s ease;
  margin-top: 0.5rem;
}

.auth-submit-btn:hover {
  transform: translateY(-2px);
  filter: brightness(1.05);
}

/* Verified Account Pill Card */
.verified-account-card {
  background: linear-gradient(135deg, #f0fdfa 0%, #e0f2fe 100%);
  border: 1.5px solid #99f6e4;
  border-radius: 1rem;
  padding: 1rem 1.25rem;
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 1.5rem;
}

.btn-cancel-reset {
  width: 28px;
  height: 28px;
  border-radius: 50%;
  background: #ffffff;
  border: 1px solid #cbd5e1;
  color: #64748b;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 0.85rem;
  text-decoration: none;
  transition: all 0.2s ease;
}

.btn-cancel-reset:hover {
  background: #fee2e2;
  color: #ef4444;
  border-color: #fca5a5;
}

/* Password Strength Meter */
.password-meter-wrap {
  margin-top: 0.5rem;
}

.password-meter-bar {
  width: 100%;
  height: 5px;
  background: #e2e8f0;
  border-radius: 9999px;
  overflow: hidden;
  margin-bottom: 0.25rem;
}

.password-meter-fill {
  height: 100%;
  width: 0%;
  transition: width 0.3s ease, background-color 0.3s ease;
}

.password-meter-text {
  font-size: 0.72rem;
  font-weight: 700;
  color: #64748b;
}

/* Success Card */
.reset-success-box {
  padding: 1.5rem 0;
}

.success-icon-animation {
  width: 72px;
  height: 72px;
  border-radius: 50%;
  background: #dcfce7;
  color: #16a34a;
  font-size: 2.5rem;
  display: flex;
  align-items: center;
  justify-content: center;
  margin: 0 auto 1.25rem;
  box-shadow: 0 0 25px rgba(22, 163, 74, 0.2);
  animation: bounce-in 0.6s cubic-bezier(0.175, 0.885, 0.32, 1.275);
}

@keyframes bounce-in {
  0% { transform: scale(0.3); opacity: 0; }
  60% { transform: scale(1.1); opacity: 1; }
  100% { transform: scale(1); }
}

.auth-switch-box {
  text-align: center;
  font-size: 0.85rem;
  color: #64748b;
  margin-top: 1.5rem;
}

.auth-switch-link {
  color: #0d9488;
  text-decoration: none;
}

.auth-switch-link:hover {
  text-decoration: underline;
}

.auth-security-footnote {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 0.4rem;
  font-size: 0.72rem;
  color: #94a3b8;
  margin-top: 1.5rem;
  text-align: center;
}

@media (max-width: 860px) {
  .auth-card-luxury { grid-template-columns: 1fr; }
  .auth-visual-side { display: none; }
  .auth-form-side { padding: 2.5rem 1.75rem; }
}
</style>

<script>
  // Toggle password eye visibility
  function togglePasswordVisibility(inputId, iconId) {
    const input = document.getElementById(inputId);
    const icon = document.getElementById(iconId);
    if (!input || !icon) return;

    if (input.type === 'password') {
      input.type = 'text';
      icon.className = 'fa-regular fa-eye-slash';
    } else {
      input.type = 'password';
      icon.className = 'fa-regular fa-eye';
    }
  }

  // Password strength checker
  function checkPasswordStrength(val) {
    const fill = document.getElementById('passwordMeterFill');
    const text = document.getElementById('passwordMeterText');
    if (!fill || !text) return;

    if (!val || val.length === 0) {
      fill.style.width = '0%';
      text.innerText = 'Kekuatan Sandi: Belum diisi';
      text.style.color = '#64748b';
      return;
    }

    let score = 0;
    if (val.length >= 6) score += 25;
    if (val.length >= 8) score += 25;
    if (/[A-Z]/.test(val) && /[a-z]/.test(val)) score += 25;
    if (/[0-9]/.test(val) || /[^A-Za-z0-9]/.test(val)) score += 25;

    fill.style.width = score + '%';

    if (score <= 25) {
      fill.style.backgroundColor = '#ef4444';
      text.innerText = 'Kekuatan Sandi: Sangat Lemah (min 6 karakter)';
      text.style.color = '#ef4444';
    } else if (score <= 50) {
      fill.style.backgroundColor = '#f59e0b';
      text.innerText = 'Kekuatan Sandi: Cukup (tambahkan angka/simbol)';
      text.style.color = '#d97706';
    } else if (score <= 75) {
      fill.style.backgroundColor = '#3b82f6';
      text.innerText = 'Kekuatan Sandi: Bagus & Aman';
      text.style.color = '#2563eb';
    } else {
      fill.style.backgroundColor = '#10b981';
      text.innerText = 'Kekuatan Sandi: Sangat Kuat & Optimal';
      text.style.color = '#059669';
    }
    checkPasswordMatch();
  }

  // Confirm password match check
  function checkPasswordMatch() {
    const p1 = document.getElementById('newPasswordInput');
    const p2 = document.getElementById('confirmPasswordInput');
    const msg = document.getElementById('passwordMatchMessage');
    if (!p1 || !p2 || !msg) return;

    if (!p2.value) {
      msg.innerHTML = '';
      return;
    }

    if (p1.value === p2.value) {
      msg.innerHTML = '<span class="text-success font-bold"><i class="fa-solid fa-check"></i> Konfirmasi kata sandi cocok!</span>';
    } else {
      msg.innerHTML = '<span class="text-danger font-bold"><i class="fa-solid fa-triangle-exclamation"></i> Kata sandi belum cocok.</span>';
    }
  }
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
