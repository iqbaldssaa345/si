<?php
require_once __DIR__ . '/config/database.php';

if (isset($_SESSION['user_id'])) {
    header("Location: " . BASE_URL . "index.php");
    exit;
}

$error = '';
$success = '';

$namaInput = '';
$emailInput = '';
$telpInput = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nama = trim($_POST['nama'] ?? '');
    $email = strtolower(trim($_POST['email'] ?? ''));
    $no_telp = trim($_POST['no_telp'] ?? '');
    $password = trim($_POST['password'] ?? '');
    $konfirmasi = trim($_POST['konfirmasi_password'] ?? '');

    $namaInput = $nama;
    $emailInput = $email;
    $telpInput = $no_telp;

    if (empty($nama) || empty($email) || empty($password)) {
        $error = "Mohon lengkapi seluruh kolom yang wajib diisi (*).";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Format alamat email tidak valid.";
    } elseif (strlen($password) < 6) {
        $error = "Kata sandi minimal harus 6 karakter demi keamanan akun.";
    } elseif ($password !== $konfirmasi) {
        $error = "Konfirmasi kata sandi tidak cocok dengan kata sandi yang dibuat.";
    } else {
        // Cek apakah email sudah terdaftar
        $stmt = $pdo->prepare("SELECT id FROM users WHERE LOWER(email) = ? LIMIT 1");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            $error = "Alamat email <strong>" . htmlspecialchars($email) . "</strong> sudah terdaftar. Silakan <a href='" . BASE_URL . "login.php' style='color:#0d9488; font-weight:800; text-decoration:underline;'>Masuk di sini</a>.";
        } else {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $insert = $pdo->prepare("INSERT INTO users (nama, email, password, no_telp, role, created_at) VALUES (?, ?, ?, ?, 'pengunjung', NOW())");
            if ($insert->execute([$nama, $email, $hash, $no_telp])) {
                $newId = $pdo->lastInsertId();
                
                // Log pendaftaran
                try {
                    $log = $pdo->prepare("INSERT INTO transaksi_log (user_id, tipe_transaksi, deskripsi, created_at) VALUES (?, 'kunjungan', ?, NOW())");
                    $log->execute([$newId, 'Pendaftaran akun baru: ' . $nama]);
                } catch (Exception $e) {}

                // Auto login user
                $_SESSION['user_id'] = $newId;
                $_SESSION['user_nama'] = $nama;
                $_SESSION['user_email'] = $email;
                $_SESSION['user_role'] = 'pengunjung';
                $_SESSION['user_foto'] = 'default_avatar.png';

                setFlash('success', 'Selamat datang, <strong>' . htmlspecialchars($nama) . '</strong>! Akun pengunjung Anda berhasil dibuat.');
                header("Location: " . BASE_URL . "pengunjung/index.php");
                exit;
            } else {
                $error = "Terjadi kendala saat mendaftarkan akun. Silakan coba beberapa saat lagi.";
            }
        }
    }
}

$pageTitle = "Daftar Akun Pengunjung - Destinasi Wisata Nusantara";
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/navbar.php';
?>

<div class="auth-luxury-page">
  <div class="container" style="max-width: 1040px; padding: 2.5rem 1rem;">
    
    <div class="auth-luxury-wrapper">
      
      <!-- Left Side: Member Privilege Showcase -->
      <div class="auth-brand-side">
        <div class="auth-brand-badge">
          <i class="fa-solid fa-sparkles text-amber"></i> Keuntungan Pengunjung Resmi
        </div>

        <h1 class="auth-brand-title">
          Buka Pintu <br>
          <span class="text-gradient-gold">Petualangan Seru</span>
        </h1>

        <p class="auth-brand-desc">
          Daftarkan akun pengunjung Anda secara gratis dalam 1 menit dan nikmati beragam fasilitas reservasi tiket digital prioritas terbaik di Indonesia.
        </p>

        <!-- Feature List with icons -->
        <div class="auth-privilege-list">
          <div class="auth-privilege-item">
            <div class="auth-privilege-icon"><i class="fa-solid fa-bolt text-amber"></i></div>
            <div>
              <div class="auth-privilege-heading">Pemesanan Instan 1 Menit</div>
              <div class="auth-privilege-text">Simpan data wisatawan dan pesan tiket dalam hitungan detik.</div>
            </div>
          </div>

          <div class="auth-privilege-item">
            <div class="auth-privilege-icon" style="color: #38bdf8; background: rgba(56, 189, 248, 0.15);"><i class="fa-solid fa-receipt"></i></div>
            <div>
              <div class="auth-privilege-heading">Arsip Riwayat & E-Ticket QR</div>
              <div class="auth-privilege-text">Akses seluruh tiket digital kapan pun tanpa risiko hilang.</div>
            </div>
          </div>

          <div class="auth-privilege-item">
            <div class="auth-privilege-icon" style="color: #fbbf24; background: rgba(245, 158, 11, 0.15);"><i class="fa-solid fa-crown"></i></div>
            <div>
              <div class="auth-privilege-heading">Potongan Harga Rombongan Otomatis</div>
              <div class="auth-privilege-text">Diskon hemat langsung untuk liburan bersama grup & sekolah.</div>
            </div>
          </div>
        </div>

        <div class="auth-brand-footer">
          <span><i class="fa-solid fa-circle-check text-emerald"></i> 100% Pendaftaran Gratis & Tanpa Biaya Tersembunyi</span>
        </div>
      </div>

      <!-- Right Side: Luxury Register Form -->
      <div class="auth-form-side">
        
        <div class="auth-form-header">
          <div class="auth-logo-circle">
            <i class="fa-solid fa-user-plus"></i>
          </div>
          <h2 class="auth-form-title">Daftar Akun Baru</h2>
          <p class="auth-form-subtitle">Lengkapi formulir singkat di bawah ini untuk membuat akun</p>
        </div>

        <?php if ($error): ?>
          <div class="auth-alert auth-alert-danger">
            <i class="fa-solid fa-circle-exclamation auth-alert-icon"></i>
            <div><?= $error ?></div>
          </div>
        <?php endif; ?>

        <form method="POST" action="" autocomplete="on" id="mainRegisterForm">
          
          <!-- Full Name -->
          <div class="auth-form-group">
            <label class="auth-label">Nama Lengkap *</label>
            <div class="auth-input-container">
              <i class="fa-solid fa-user auth-input-icon"></i>
              <input type="text" name="nama" id="regNama" class="auth-input-field" placeholder="Contoh: Budi Santoso" required value="<?= htmlspecialchars($namaInput) ?>" autofocus>
            </div>
          </div>

          <!-- Email -->
          <div class="auth-form-group">
            <label class="auth-label">Alamat Email Resmi *</label>
            <div class="auth-input-container">
              <i class="fa-solid fa-envelope auth-input-icon"></i>
              <input type="email" name="email" id="regEmail" class="auth-input-field" placeholder="nama@email.com" required value="<?= htmlspecialchars($emailInput) ?>">
            </div>
          </div>

          <!-- WhatsApp / Phone -->
          <div class="auth-form-group">
            <label class="auth-label">Nomor WhatsApp / HP Aktif</label>
            <div class="auth-input-container">
              <i class="fa-solid fa-phone auth-input-icon"></i>
              <input type="tel" name="no_telp" id="regTelp" class="auth-input-field" placeholder="081234567890" value="<?= htmlspecialchars($telpInput) ?>">
            </div>
          </div>

          <!-- Password & Confirm in 2 Cols -->
          <div class="grid grid-cols-2 gap-4" style="margin-bottom: 1.35rem;">
            <div>
              <label class="auth-label">Kata Sandi *</label>
              <div class="auth-input-container">
                <i class="fa-solid fa-lock auth-input-icon"></i>
                <input type="password" name="password" id="regPassword" class="auth-input-field" placeholder="Min. 6 Karakter" required oninput="checkStrength(this.value)">
                <button type="button" class="auth-password-toggle" onclick="togglePasswordVisibility('regPassword', 'eyeIconPass')" title="Tampilkan Sandi">
                  <i class="fa-regular fa-eye" id="eyeIconPass"></i>
                </button>
              </div>
            </div>

            <div>
              <label class="auth-label">Konfirmasi Sandi *</label>
              <div class="auth-input-container">
                <i class="fa-solid fa-shield-halved auth-input-icon"></i>
                <input type="password" name="konfirmasi_password" id="regConfirmPassword" class="auth-input-field" placeholder="Ulangi Sandi" required>
                <button type="button" class="auth-password-toggle" onclick="togglePasswordVisibility('regConfirmPassword', 'eyeIconConfirm')" title="Tampilkan Sandi">
                  <i class="fa-regular fa-eye" id="eyeIconConfirm"></i>
                </button>
              </div>
            </div>
          </div>

          <!-- Password Strength Indicator -->
          <div id="passwordStrengthBox" style="margin-top: -0.75rem; margin-bottom: 1.25rem; display: none;">
            <div style="display: flex; align-items: center; justify-content: space-between; font-size: 0.72rem; color: #64748b; margin-bottom: 0.3rem;">
              <span>Kekuatan Kata Sandi:</span>
              <strong id="strengthLabel" style="color: #ef4444;">Lemah</strong>
            </div>
            <div style="height: 4px; background: #e2e8f0; border-radius: 9999px; overflow: hidden;">
              <div id="strengthBar" style="height: 100%; width: 25%; background: #ef4444; transition: all 0.3s ease;"></div>
            </div>
          </div>

          <!-- Terms Checkbox -->
          <div class="auth-extra-row" style="margin-bottom: 1.5rem;">
            <label class="auth-checkbox-label">
              <input type="checkbox" required checked class="auth-checkbox">
              <span>Saya menyetujui seluruh <a href="<?= BASE_URL ?>index.php#faq" style="color:#0d9488; font-weight:700;">Ketentuan Layanan & Privasi</a> tiket wisata.</span>
            </label>
          </div>

          <!-- Submit Button -->
          <button type="submit" class="auth-submit-btn">
            <span>Daftar Akun Pengunjung</span>
            <i class="fa-solid fa-arrow-right"></i>
          </button>

        </form>

        <!-- Switch to Login -->
        <div class="auth-switch-box">
          Sudah memiliki akun terdaftar? 
          <a href="<?= BASE_URL ?>login.php" class="auth-switch-link">Masuk di sini</a>
        </div>

        <div class="auth-security-footnote">
          <i class="fa-solid fa-shield-halved text-emerald"></i>
          <span>Data Pribadi Anda Dijamin Aman & Terlindungi</span>
        </div>

      </div>

    </div>

  </div>
</div>

<style>
/* ==========================================================================
   ULTRA-LUXURY AUTH PAGES STYLING (LOGIN & REGISTER)
   ========================================================================== */
.auth-luxury-page {
  min-height: 86vh;
  display: flex;
  align-items: center;
  justify-content: center;
  background: radial-gradient(circle at 10% 20%, rgba(13, 148, 136, 0.08) 0%, transparent 45%),
              radial-gradient(circle at 90% 80%, rgba(2, 132, 199, 0.08) 0%, transparent 45%),
              #f8fafc;
  padding: 2rem 0;
}

.auth-luxury-wrapper {
  background: #ffffff;
  border-radius: 1.75rem;
  border: 1px solid rgba(226, 232, 240, 0.9);
  box-shadow: 0 25px 60px -15px rgba(15, 23, 42, 0.12), 0 0 0 1px rgba(241, 245, 249, 0.8);
  display: grid;
  grid-template-columns: 1fr 1.1fr;
  overflow: hidden;
}

/* Left Brand Side */
.auth-brand-side {
  background: linear-gradient(135deg, #090d16 0%, #042426 50%, #082f49 100%);
  color: #ffffff;
  padding: 3.5rem 3rem;
  display: flex;
  flex-direction: column;
  justify-content: space-between;
  position: relative;
  overflow: hidden;
}

.auth-brand-side::before {
  content: '';
  position: absolute;
  top: -20%;
  right: -20%;
  width: 350px;
  height: 350px;
  border-radius: 50%;
  background: radial-gradient(circle, rgba(45, 212, 191, 0.18) 0%, transparent 70%);
  pointer-events: none;
}

.auth-brand-badge {
  display: inline-flex;
  align-items: center;
  gap: 0.5rem;
  background: rgba(255, 255, 255, 0.1);
  backdrop-filter: blur(10px);
  border: 1px solid rgba(255, 255, 255, 0.2);
  color: #5eead4;
  font-size: 0.78rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.06em;
  padding: 0.35rem 0.95rem;
  border-radius: 9999px;
  width: fit-content;
  margin-bottom: 2rem;
}

.auth-brand-title {
  font-family: 'Outfit', sans-serif;
  font-size: 2.3rem;
  font-weight: 900;
  line-height: 1.2;
  color: #ffffff;
  margin-bottom: 1rem;
}

.text-gradient-gold {
  background: linear-gradient(135deg, #2dd4bf 0%, #38bdf8 50%, #fbbf24 100%);
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;
}

.auth-brand-desc {
  font-size: 0.92rem;
  color: #94a3b8;
  line-height: 1.7;
  margin-bottom: 2.25rem;
}

.auth-privilege-list {
  display: flex;
  flex-direction: column;
  gap: 1.25rem;
  margin-bottom: 2.5rem;
}

.auth-privilege-item {
  display: flex;
  align-items: flex-start;
  gap: 1rem;
}

.auth-privilege-icon {
  width: 42px;
  height: 42px;
  border-radius: 12px;
  background: rgba(45, 212, 191, 0.15);
  border: 1px solid rgba(45, 212, 191, 0.3);
  color: #2dd4bf;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 1.15rem;
  flex-shrink: 0;
}

.auth-privilege-heading {
  font-weight: 700;
  font-size: 0.92rem;
  color: #ffffff;
  margin-bottom: 0.2rem;
}

.auth-privilege-text {
  font-size: 0.8rem;
  color: #94a3b8;
  line-height: 1.45;
}

.auth-brand-footer {
  font-size: 0.78rem;
  color: #64748b;
  border-top: 1px solid rgba(255, 255, 255, 0.1);
  padding-top: 1.25rem;
}

/* Right Form Side */
.auth-form-side {
  padding: 3.5rem 3rem;
  display: flex;
  flex-direction: column;
  justify-content: center;
  background: #ffffff;
}

.auth-form-header {
  text-align: left;
  margin-bottom: 2rem;
}

.auth-logo-circle {
  width: 54px;
  height: 54px;
  border-radius: 16px;
  background: linear-gradient(135deg, #0d9488 0%, #0284c7 100%);
  color: #ffffff;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 1.5rem;
  margin-bottom: 1.25rem;
  box-shadow: 0 10px 20px rgba(13, 148, 136, 0.25);
}

.auth-form-title {
  font-family: 'Outfit', sans-serif;
  font-size: 1.75rem;
  font-weight: 800;
  color: #0f172a;
  margin-bottom: 0.35rem;
  letter-spacing: -0.02em;
}

.auth-form-subtitle {
  font-size: 0.88rem;
  color: #64748b;
}

/* Alerts */
.auth-alert {
  padding: 0.85rem 1.1rem;
  border-radius: 0.85rem;
  font-size: 0.85rem;
  line-height: 1.5;
  display: flex;
  align-items: center;
  gap: 0.75rem;
  margin-bottom: 1.5rem;
}

.auth-alert-danger {
  background: #fef2f2;
  border: 1px solid #fecaca;
  color: #991b1b;
}

.auth-alert-warning {
  background: #fffbeb;
  border: 1px solid #fde68a;
  color: #92400e;
}

.auth-alert-icon {
  font-size: 1.2rem;
  flex-shrink: 0;
}

/* Form inputs */
.auth-form-group {
  margin-bottom: 1.35rem;
}

.auth-label {
  display: block;
  font-size: 0.78rem;
  font-weight: 800;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  color: #475569;
  margin-bottom: 0.45rem;
}

.auth-input-container {
  position: relative;
  display: flex;
  align-items: center;
}

.auth-input-icon {
  position: absolute;
  left: 1.15rem;
  color: #94a3b8;
  font-size: 1.05rem;
  pointer-events: none;
  transition: color 0.2s;
}

.auth-input-field {
  width: 100%;
  padding: 0.85rem 1.1rem 0.85rem 3rem;
  border: 1.5px solid #cbd5e1;
  border-radius: 0.85rem;
  background: #f8fafc;
  font-size: 0.95rem;
  font-weight: 600;
  color: #0f172a;
  outline: none;
  transition: all 0.25s ease;
}

.auth-input-field:focus {
  background: #ffffff;
  border-color: #0d9488;
  box-shadow: 0 0 0 4px rgba(13, 148, 136, 0.15);
}

.auth-input-field:focus + .auth-input-icon,
.auth-input-container:focus-within .auth-input-icon {
  color: #0d9488;
}

.auth-password-toggle {
  position: absolute;
  right: 1.1rem;
  background: none;
  border: none;
  color: #94a3b8;
  font-size: 1.05rem;
  cursor: pointer;
  padding: 0.25rem;
  transition: color 0.2s;
}

.auth-password-toggle:hover {
  color: #0d9488;
}

.auth-extra-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 1.75rem;
}

.auth-checkbox-label {
  display: flex;
  align-items: flex-start;
  gap: 0.5rem;
  font-size: 0.83rem;
  color: #64748b;
  cursor: pointer;
  user-select: none;
  line-height: 1.5;
}

.auth-checkbox {
  width: 1.05rem;
  height: 1.05rem;
  accent-color: #0d9488;
  border-radius: 0.35rem;
  cursor: pointer;
  margin-top: 0.15rem;
}

/* Submit Button */
.auth-submit-btn {
  width: 100%;
  padding: 0.95rem 1.75rem;
  border-radius: 0.85rem;
  background: linear-gradient(135deg, #0d9488 0%, #0f766e 100%);
  color: #ffffff;
  border: none;
  font-size: 1rem;
  font-weight: 800;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 0.65rem;
  box-shadow: 0 10px 25px rgba(13, 148, 136, 0.35);
  transition: all 0.25s ease;
}

.auth-submit-btn:hover {
  background: linear-gradient(135deg, #0f766e 0%, #115e59 100%);
  box-shadow: 0 14px 30px rgba(13, 148, 136, 0.45);
  transform: translateY(-2px);
}

.auth-submit-btn:active {
  transform: translateY(0);
}

/* Switch Link */
.auth-switch-box {
  text-align: center;
  font-size: 0.88rem;
  color: #64748b;
  margin-top: 1.75rem;
  padding-top: 1.25rem;
  border-top: 1px solid #f1f5f9;
}

.auth-switch-link {
  font-weight: 800;
  color: #0d9488;
  text-decoration: none;
  margin-left: 0.25rem;
}

.auth-switch-link:hover {
  text-decoration: underline;
  color: #0f766e;
}

.auth-security-footnote {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 0.45rem;
  font-size: 0.75rem;
  color: #94a3b8;
  margin-top: 1.5rem;
}

/* Responsive */
@media (max-width: 900px) {
  .auth-luxury-wrapper {
    grid-template-columns: 1fr;
  }
  .auth-brand-side {
    padding: 2.5rem 2rem;
  }
  .auth-form-side {
    padding: 2.5rem 2rem;
  }
}
</style>

<script>
function togglePasswordVisibility(fieldId, iconId) {
  const field = document.getElementById(fieldId);
  const icon = document.getElementById(iconId);
  if (field.type === 'password') {
    field.type = 'text';
    icon.classList.remove('fa-eye');
    icon.classList.add('fa-eye-slash');
  } else {
    field.type = 'password';
    icon.classList.remove('fa-eye-slash');
    icon.classList.add('fa-eye');
  }
}

function checkStrength(pass) {
  const box = document.getElementById('passwordStrengthBox');
  const bar = document.getElementById('strengthBar');
  const label = document.getElementById('strengthLabel');
  
  if (!pass || pass.length === 0) {
    box.style.display = 'none';
    return;
  }
  
  box.style.display = 'block';
  
  let score = 0;
  if (pass.length >= 6) score++;
  if (pass.length >= 8) score++;
  if (/[0-9]/.test(pass)) score++;
  if (/[A-Z]/.test(pass) || /[^A-Za-z0-9]/.test(pass)) score++;
  
  if (score <= 1) {
    bar.style.width = '25%';
    bar.style.backgroundColor = '#ef4444';
    label.innerText = 'Lemah';
    label.style.color = '#ef4444';
  } else if (score === 2) {
    bar.style.width = '50%';
    bar.style.backgroundColor = '#f59e0b';
    label.innerText = 'Sedang';
    label.style.color = '#f59e0b';
  } else if (score === 3) {
    bar.style.width = '75%';
    bar.style.backgroundColor = '#0284c7';
    label.innerText = 'Kuat';
    label.style.color = '#0284c7';
  } else {
    bar.style.width = '100%';
    bar.style.backgroundColor = '#10b981';
    label.innerText = 'Sangat Kuat & Aman';
    label.style.color = '#10b981';
  }
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
