<?php
require_once __DIR__ . '/../config/database.php';
checkAuth('pengunjung');

$userId = $_SESSION['user_id'];
$userName = $_SESSION['user_nama'] ?? 'Wisatawan';
$today = date('Y-m-d');

// 1. Statistik Pengunjung
$stmtAktif = $pdo->prepare("SELECT COUNT(*) FROM pemesanan WHERE user_id = ? AND status_bayar = 'lunas' AND status_kunjungan = 'belum_digunakan' AND tanggal_kunjungan >= CURDATE()");
$stmtAktif->execute([$userId]);
$countTiketAktif = (int)$stmtAktif->fetchColumn();

$stmtPending = $pdo->prepare("SELECT COUNT(*) FROM pemesanan WHERE user_id = ? AND status_bayar = 'pending'");
$stmtPending->execute([$userId]);
$countPending = (int)$stmtPending->fetchColumn();

$stmtSelesai = $pdo->prepare("SELECT COUNT(*) FROM pemesanan WHERE user_id = ? AND status_kunjungan = 'sudah_digunakan'");
$stmtSelesai->execute([$userId]);
$countSelesai = (int)$stmtSelesai->fetchColumn();

$stmtUlasan = $pdo->prepare("SELECT COUNT(*) FROM ulasan WHERE user_id = ?");
$stmtUlasan->execute([$userId]);
$countUlasan = (int)$stmtUlasan->fetchColumn();

// 2. Tiket Aktif Terdekat (Upcoming Trip Priority)
$stmtUpcoming = $pdo->prepare("SELECT p.*, d.nama_destinasi, d.lokasi, d.foto_utama, d.jam_buka, d.jam_tutup
                                FROM pemesanan p 
                                JOIN destinasi d ON p.destinasi_id = d.id 
                                WHERE p.user_id = ? AND p.status_bayar = 'lunas' AND p.status_kunjungan = 'belum_digunakan' AND p.tanggal_kunjungan >= CURDATE()
                                ORDER BY p.tanggal_kunjungan ASC, p.id ASC LIMIT 1");
$stmtUpcoming->execute([$userId]);
$upcomingTicket = $stmtUpcoming->fetch();

// 3. Transaksi Pemesanan Terbaru (4 data)
$stmtTerbaru = $pdo->prepare("SELECT p.*, d.nama_destinasi, d.lokasi, d.foto_utama
                              FROM pemesanan p 
                              JOIN destinasi d ON p.destinasi_id = d.id 
                              WHERE p.user_id = ? 
                              ORDER BY p.id DESC LIMIT 4");
$stmtTerbaru->execute([$userId]);
$pesananTerbaru = $stmtTerbaru->fetchAll();

// 4. Rekomendasi Destinasi Populer (3 data)
$destinasiPopuler = $pdo->query("SELECT d.*, k.nama_kategori, 
                                 COALESCE(AVG(u.rating), 4.9) as avg_rating,
                                 COUNT(u.id) as total_ulasan
                                 FROM destinasi d 
                                 LEFT JOIN kategori_wisata k ON d.kategori_id = k.id 
                                 LEFT JOIN ulasan u ON d.id = u.destinasi_id 
                                 WHERE d.status = 'buka'
                                 GROUP BY d.id 
                                 ORDER BY avg_rating DESC, d.id DESC LIMIT 3")->fetchAll();

$pageTitle = "Dashboard Wisatawan VIP";
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';
$flash = getFlash();
?>

<main class="admin-main">
  
  <!-- Header Topbar Luxury (Compact & Fit 100%) -->
  <div class="admin-topbar-luxury">
    <div style="display: flex; align-items: center; gap: 0.75rem; flex-wrap: wrap;">
      <div>
        <div style="display: flex; align-items: center; gap: 0.35rem; margin-bottom: 0.1rem;">
          <span class="badge-member badge-member-gold">
            <span style="font-size: 0.8rem;">⭐</span> VIP Member
          </span>
          <span style="font-size: 0.72rem; color: #64748b;">
            <i class="fa-regular fa-calendar" style="margin-right: 2px;"></i> <?= formatTanggalIndo($today) ?>
          </span>
        </div>
        <h1 style="font-size: 1.25rem; font-weight: 800; color: #0f172a; margin: 0; letter-spacing: -0.02em;">
          Selamat Datang, <?= htmlspecialchars($userName) ?>! 👋
        </h1>
      </div>
    </div>

    <div style="display: flex; gap: 0.35rem; flex-wrap: wrap;">
      <a href="<?= BASE_URL ?>pengunjung/tiket_saya.php" class="btn btn-secondary btn-xs" style="background: #ffffff; border: 1px solid #cbd5e1; font-weight: 700; height: 30px; padding: 0 0.75rem; border-radius: 0.45rem; display: inline-flex; align-items: center; gap: 0.35rem;">
        <i class="fa-solid fa-ticket text-primary"></i> E-Ticket Saya
      </a>
      <a href="<?= BASE_URL ?>pengunjung/pesan_tiket.php" class="btn btn-primary btn-xs btn-luxury-pulse" style="font-weight: 800; box-shadow: 0 3px 10px rgba(2, 132, 199, 0.35); height: 30px; padding: 0 0.85rem; border-radius: 0.45rem; display: inline-flex; align-items: center; gap: 0.35rem;">
        <i class="fa-solid fa-cart-plus"></i> Booking Wisata
      </a>
    </div>
  </div>

  <!-- Flash Notification -->
  <?php if ($flash): ?>
    <div class="alert alert-<?= $flash['type'] ?> mb-3" style="border-radius: 0.6rem; padding: 0.6rem 0.85rem; display: flex; align-items: center; gap: 0.5rem; font-size: 0.82rem; box-shadow: 0 1px 4px rgba(0,0,0,0.02);">
      <i class="fa-solid <?= $flash['type'] === 'success' ? 'fa-circle-check text-emerald-600' : 'fa-circle-exclamation text-rose-600' ?>" style="font-size: 1.1rem;"></i>
      <div style="line-height: 1.35;"><?= $flash['message'] ?></div>
    </div>
  <?php endif; ?>

  <!-- 4 KPI Metrics Grid (Compact Row) -->
  <div class="grid grid-cols-4 gap-2 mb-3">
    
    <div class="kpi-card-luxury">
      <div style="display: flex; align-items: center; justify-content: space-between;">
        <div>
          <span style="font-size: 0.65rem; font-weight: 800; text-transform: uppercase; color: #64748b; letter-spacing: 0.04em; display: block;">Tiket Siap Pakai</span>
          <div style="font-size: 1.25rem; font-weight: 900; color: #0284c7; line-height: 1.1; margin-top: 0.1rem; font-family: 'Outfit', sans-serif;">
            <?= $countTiketAktif ?> <span style="font-size: 0.7rem; font-weight: 600; color: #64748b;">Tiket</span>
          </div>
        </div>
        <div class="kpi-icon-wrap" style="background: #e0f2fe; color: #0284c7;">
          <i class="fa-solid fa-qrcode"></i>
        </div>
      </div>
    </div>

    <div class="kpi-card-luxury">
      <div style="display: flex; align-items: center; justify-content: space-between;">
        <div>
          <span style="font-size: 0.65rem; font-weight: 800; text-transform: uppercase; color: #64748b; letter-spacing: 0.04em; display: block;">Menunggu Bayar</span>
          <div style="font-size: 1.25rem; font-weight: 900; color: #d97706; line-height: 1.1; margin-top: 0.1rem; font-family: 'Outfit', sans-serif;">
            <?= $countPending ?> <span style="font-size: 0.7rem; font-weight: 600; color: #64748b;">Invoice</span>
          </div>
        </div>
        <div class="kpi-icon-wrap" style="background: #fef3c7; color: #d97706;">
          <i class="fa-solid fa-clock"></i>
        </div>
      </div>
    </div>

    <div class="kpi-card-luxury">
      <div style="display: flex; align-items: center; justify-content: space-between;">
        <div>
          <span style="font-size: 0.65rem; font-weight: 800; text-transform: uppercase; color: #64748b; letter-spacing: 0.04em; display: block;">Destinasi Dikunjungi</span>
          <div style="font-size: 1.25rem; font-weight: 900; color: #0d9488; line-height: 1.1; margin-top: 0.1rem; font-family: 'Outfit', sans-serif;">
            <?= $countSelesai ?> <span style="font-size: 0.7rem; font-weight: 600; color: #64748b;">Lokasi</span>
          </div>
        </div>
        <div class="kpi-icon-wrap" style="background: #ccfbf1; color: #0d9488;">
          <i class="fa-solid fa-map-location-dot"></i>
        </div>
      </div>
    </div>

    <div class="kpi-card-luxury">
      <div style="display: flex; align-items: center; justify-content: space-between;">
        <div>
          <span style="font-size: 0.65rem; font-weight: 800; text-transform: uppercase; color: #64748b; letter-spacing: 0.04em; display: block;">Ulasan & Rating</span>
          <div style="font-size: 1.25rem; font-weight: 900; color: #9333ea; line-height: 1.1; margin-top: 0.1rem; font-family: 'Outfit', sans-serif;">
            <?= $countUlasan ?> <span style="font-size: 0.7rem; font-weight: 600; color: #64748b;">Review</span>
          </div>
        </div>
        <div class="kpi-icon-wrap" style="background: #f3e8ff; color: #9333ea;">
          <i class="fa-solid fa-star-half-stroke"></i>
        </div>
      </div>
    </div>

  </div>

  <!-- Upcoming Trip Priority Banner (If active ticket exists) -->
  <?php if ($upcomingTicket): 
    $tglTrip = $upcomingTicket['tanggal_kunjungan'];
    $isTodayTrip = ($tglTrip === $today);
    $diffDays = (int)ceil((strtotime($tglTrip) - strtotime($today)) / (60 * 60 * 24));
    $qrUrl = "https://api.qrserver.com/v1/create-qr-code/?size=180x180&data=" . urlencode($upcomingTicket['kode_booking']);
  ?>
    <div class="card mb-3 shadow-sm" style="border-radius: 0.75rem; background: linear-gradient(135deg, #0369a1 0%, #0284c7 60%, #38bdf8 100%); color: #ffffff; border: none; padding: 0.9rem 1.15rem; position: relative; overflow: hidden;">
      <div class="upcoming-banner-grid" style="display: grid; grid-template-columns: 1fr 140px; gap: 1rem; align-items: center; position: relative; z-index: 1;">
        
        <div>
          <div style="display: flex; align-items: center; gap: 0.4rem; margin-bottom: 0.25rem; flex-wrap: wrap;">
            <span style="background: rgba(255,255,255,0.22); backdrop-filter: blur(6px); padding: 0.15rem 0.55rem; border-radius: 9999px; font-size: 0.68rem; font-weight: 800;">
              <?= $isTodayTrip ? '🔥 HARI INI JADWAL KUNJUNGAN ANDA!' : ('🗓️ LIBURAN ' . ($diffDays == 1 ? 'BESOK' : ($diffDays . ' HARI LAGI'))) ?>
            </span>
            <span style="font-size: 0.72rem; opacity: 0.95;">
              Kode: <strong style="font-family: monospace; letter-spacing: 0.05em;"><?= htmlspecialchars($upcomingTicket['kode_booking']) ?></strong>
            </span>
          </div>

          <h2 style="font-size: 1.25rem; font-weight: 900; color: #ffffff; margin: 0 0 0.2rem 0; line-height: 1.2;">
            <?= htmlspecialchars($upcomingTicket['nama_destinasi']) ?>
          </h2>
          
          <div style="display: flex; flex-wrap: wrap; gap: 0.85rem; font-size: 0.78rem; opacity: 0.95; margin-bottom: 0.65rem;">
            <span><i class="fa-solid fa-location-dot text-amber-300"></i> <?= htmlspecialchars($upcomingTicket['lokasi']) ?></span>
            <span><i class="fa-regular fa-calendar text-cyan-200"></i> <?= formatTanggalIndo($upcomingTicket['tanggal_kunjungan']) ?></span>
            <span><i class="fa-solid fa-user-group text-emerald-300"></i> <?= $upcomingTicket['jumlah_tiket'] ?> Wisatawan (<?= ucfirst($upcomingTicket['tipe_rombongan']) ?>)</span>
          </div>

          <div style="display: flex; gap: 0.4rem; flex-wrap: wrap;">
            <button type="button" onclick="openGateModal('<?= htmlspecialchars($upcomingTicket['kode_booking']) ?>', '<?= htmlspecialchars(addslashes($upcomingTicket['nama_destinasi'])) ?>', '<?= formatTanggalIndo($upcomingTicket['tanggal_kunjungan']) ?>', '<?= $upcomingTicket['jumlah_tiket'] ?>')" class="btn btn-warning btn-xs" style="font-weight: 800; border-radius: 0.45rem; height: 28px; padding: 0 0.75rem; color: #78350f; background: #fbbf24; border: none; box-shadow: 0 2px 8px rgba(251, 191, 36, 0.4);">
              <i class="fa-solid fa-qrcode"></i> Buka Gate Pass QR (HP)
            </button>
            <a href="<?= BASE_URL ?>pengunjung/tiket_saya.php" class="btn btn-xs" style="background: rgba(255,255,255,0.2); color: #ffffff; border: 1px solid rgba(255,255,255,0.3); font-weight: 700; border-radius: 0.45rem; height: 28px; padding: 0 0.75rem; display: inline-flex; align-items: center; gap: 0.3rem;">
              <i class="fa-solid fa-ticket"></i> Detail E-Ticket
            </a>
          </div>
        </div>

        <!-- QR Code Preview Box -->
        <div class="qr-preview-box" style="background: #ffffff; padding: 0.45rem; border-radius: 0.65rem; text-align: center; box-shadow: 0 4px 12px rgba(0,0,0,0.15); cursor: pointer;" onclick="openGateModal('<?= htmlspecialchars($upcomingTicket['kode_booking']) ?>', '<?= htmlspecialchars(addslashes($upcomingTicket['nama_destinasi'])) ?>', '<?= formatTanggalIndo($upcomingTicket['tanggal_kunjungan']) ?>', '<?= $upcomingTicket['jumlah_tiket'] ?>')">
          <img src="https://api.qrserver.com/v1/create-qr-code/?size=250x250&margin=2&ecc=M&data=<?= urlencode($upcomingTicket['kode_booking']) ?>" alt="QR Code" style="width: 100%; height: auto; display: block; border-radius: 0.35rem;">
          <span style="display: block; font-size: 0.65rem; color: #0284c7; font-weight: 800; margin-top: 0.25rem;">
            <i class="fa-solid fa-expand"></i> Perbesar QR
          </span>
        </div>

      </div>
    </div>
  <?php endif; ?>

  <!-- Main 2-Column Split: Riwayat Pesanan & Rekomendasi Destinasi -->
  <div class="grid grid-cols-12 gap-3">
    
    <!-- Left Column: Transaksi Pemesanan Terbaru (7 cols) -->
    <div class="col-span-12 lg:col-span-7" style="grid-column: span 7 / span 7;">
      <div class="card-table-luxury">
        <div style="padding: 0.65rem 0.85rem; background: #ffffff; border-bottom: 1px solid #e2e8f0; display: flex; align-items: center; justify-content: space-between;">
          <div style="display: flex; align-items: center; gap: 0.4rem;">
            <i class="fa-solid fa-clock-rotate-left text-primary" style="font-size: 0.95rem;"></i>
            <h2 style="font-size: 0.92rem; font-weight: 800; color: #0f172a; margin: 0;">Aktivitas Pesanan Terbaru</h2>
          </div>
          <a href="<?= BASE_URL ?>pengunjung/riwayat_transaksi.php" style="font-size: 0.72rem; font-weight: 700; color: #0284c7; text-decoration: none; display: inline-flex; align-items: center; gap: 0.25rem;">
            Lihat Semua <i class="fa-solid fa-arrow-right" style="font-size: 0.65rem;"></i>
          </a>
        </div>

        <?php if (empty($pesananTerbaru)): ?>
          <div style="padding: 2.5rem 1rem; text-align: center; color: #64748b;">
            <div style="width: 44px; height: 44px; border-radius: 50%; background: #f1f5f9; color: #94a3b8; display: flex; align-items: center; justify-content: center; margin: 0 auto 0.6rem auto; font-size: 1.15rem;">
              <i class="fa-solid fa-receipt"></i>
            </div>
            <h4 style="font-size: 0.9rem; font-weight: 700; color: #334155; margin: 0 0 0.2rem 0;">Belum Ada Pemesanan</h4>
            <p style="font-size: 0.78rem; margin: 0 0 0.75rem 0;">Yuk mulai rencanakan liburan seru Anda hari ini!</p>
            <a href="<?= BASE_URL ?>pengunjung/pesan_tiket.php" class="btn btn-primary btn-xs" style="border-radius: 0.45rem; font-weight: 700;">
              <i class="fa-solid fa-cart-plus"></i> Pesan Tiket Pertama
            </a>
          </div>
        <?php else: ?>
          <div style="overflow-x: auto;">
            <table class="table-luxury">
              <thead>
                <tr>
                  <th>Kode & Destinasi</th>
                  <th>Kunjungan</th>
                  <th>Total</th>
                  <th>Status</th>
                  <th style="text-align: right;">Aksi</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($pesananTerbaru as $p): 
                  $isLunas = ($p['status_bayar'] === 'lunas');
                  $isSelesai = ($p['status_kunjungan'] === 'sudah_digunakan');
                ?>
                  <tr>
                    <td>
                      <?php 
                        $pFoto = trim($p['foto_utama'] ?? '');
                        if (!empty($pFoto) && (strpos($pFoto, 'http://') === 0 || strpos($pFoto, 'https://') === 0)) {
                            $pFotoUrl = $pFoto;
                        } elseif (!empty($pFoto) && file_exists(__DIR__ . '/../assets/uploads/destinasi/' . $pFoto)) {
                            $pFotoUrl = BASE_URL . 'assets/uploads/destinasi/' . $pFoto;
                        } else {
                            $pFotoUrl = 'https://images.unsplash.com/photo-1507525428034-b723cf961d3e?w=100&q=80';
                        }
                      ?>
                      <div style="display: flex; align-items: center; gap: 0.55rem;">
                        <img src="<?= htmlspecialchars($pFotoUrl) ?>" alt="Thumb" style="width: 34px; height: 34px; border-radius: 6px; object-fit: cover; flex-shrink: 0; background: #0f172a;" onerror="this.onerror=null; this.src='https://images.unsplash.com/photo-1507525428034-b723cf961d3e?w=100&q=80';">
                        <div style="min-width: 0;">
                          <div style="font-weight: 800; color: #0f172a; font-size: 0.82rem; line-height: 1.2; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                            <?= htmlspecialchars($p['nama_destinasi']) ?>
                          </div>
                          <div style="font-family: monospace; font-size: 0.7rem; color: #0284c7; font-weight: 700;">
                            <?= htmlspecialchars($p['kode_booking']) ?>
                          </div>
                        </div>
                      </div>
                    </td>
                    <td>
                      <div style="font-size: 0.78rem; font-weight: 600; color: #334155;">
                        <?= date('d/m/Y', strtotime($p['tanggal_kunjungan'])) ?>
                      </div>
                      <div style="font-size: 0.68rem; color: #64748b;">
                        <?= $p['jumlah_tiket'] ?> Tiket (<?= ucfirst($p['tipe_rombongan']) ?>)
                      </div>
                    </td>
                    <td>
                      <span style="font-weight: 800; color: #0f172a; font-size: 0.8rem;">
                        Rp <?= number_format($p['total_bayar'], 0, ',', '.') ?>
                      </span>
                    </td>
                    <td>
                      <?php if ($isSelesai): ?>
                        <span class="badge badge-secondary" style="font-size: 0.65rem; padding: 0.15rem 0.45rem; border-radius: 9999px;">
                          <i class="fa-solid fa-check-double"></i> Selesai
                        </span>
                      <?php elseif ($isLunas): ?>
                        <span class="badge badge-success" style="font-size: 0.65rem; padding: 0.15rem 0.45rem; border-radius: 9999px; background: #dcfce7; color: #15803d; border: 1px solid #86efac;">
                          <i class="fa-solid fa-circle-check"></i> Lunas
                        </span>
                      <?php else: ?>
                        <span class="badge badge-warning" style="font-size: 0.65rem; padding: 0.15rem 0.45rem; border-radius: 9999px; background: #fef3c7; color: #b45309; border: 1px solid #fde68a;">
                          <i class="fa-solid fa-clock"></i> Belum Bayar
                        </span>
                      <?php endif; ?>
                    </td>
                    <td style="text-align: right;">
                      <?php if ($isLunas): ?>
                        <a href="<?= BASE_URL ?>pengunjung/tiket_saya.php?q=<?= urlencode($p['kode_booking']) ?>" class="btn btn-secondary btn-xs" style="padding: 0.2rem 0.5rem; font-size: 0.7rem; border-radius: 0.35rem; font-weight: 700;" title="Buka Tiket">
                          <i class="fa-solid fa-ticket text-primary"></i> E-Ticket
                        </a>
                      <?php else: ?>
                        <a href="<?= BASE_URL ?>pembayaran.php?kode=<?= urlencode($p['kode_booking']) ?>" class="btn btn-warning btn-xs" style="padding: 0.2rem 0.55rem; font-size: 0.7rem; border-radius: 0.35rem; font-weight: 800;" title="Bayar Sekarang">
                          <i class="fa-solid fa-credit-card"></i> Bayar
                        </a>
                      <?php endif; ?>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>
      </div>
    </div>

    <!-- Right Column: Destinasi Populer Rekomendasi (5 cols) -->
    <div class="col-span-12 lg:col-span-5" style="grid-column: span 5 / span 5;">
      <div class="card p-3 bg-white shadow-sm" style="border-radius: 0.75rem; border: 1px solid #e2e8f0;">
        <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 0.65rem; padding-bottom: 0.4rem; border-bottom: 1px solid #f1f5f9;">
          <div style="display: flex; align-items: center; gap: 0.4rem;">
            <i class="fa-solid fa-fire text-amber-500" style="font-size: 0.95rem;"></i>
            <h2 style="font-size: 0.92rem; font-weight: 800; color: #0f172a; margin: 0;">Destinasi Pilihan Terpopuler</h2>
          </div>
          <a href="<?= BASE_URL ?>pengunjung/pesan_tiket.php" style="font-size: 0.72rem; font-weight: 700; color: #0284c7; text-decoration: none;">
            Jelajahi <i class="fa-solid fa-arrow-right" style="font-size: 0.65rem;"></i>
          </a>
        </div>

        <div style="display: flex; flex-direction: column; gap: 0.5rem;">
          <?php foreach ($destinasiPopuler as $dp): 
            $dpFoto = trim($dp['foto_utama'] ?? '');
            if (!empty($dpFoto) && (strpos($dpFoto, 'http://') === 0 || strpos($dpFoto, 'https://') === 0)) {
                $dpFotoUrl = $dpFoto;
            } elseif (!empty($dpFoto) && file_exists(__DIR__ . '/../assets/uploads/destinasi/' . $dpFoto)) {
                $dpFotoUrl = BASE_URL . 'assets/uploads/destinasi/' . $dpFoto;
            } else {
                $dpFotoUrl = 'https://images.unsplash.com/photo-1507525428034-b723cf961d3e?w=200&q=80';
            }
          ?>
            <div style="display: flex; gap: 0.65rem; align-items: center; padding: 0.5rem; border-radius: 0.55rem; border: 1px solid #f1f5f9; background: #fafafa; transition: background 0.15s ease;">
              
              <!-- Thumbnail -->
              <div style="width: 58px; height: 58px; min-width: 58px; border-radius: 0.45rem; overflow: hidden; position: relative; background: #0f172a;">
                <img src="<?= htmlspecialchars($dpFotoUrl) ?>" 
                     alt="<?= htmlspecialchars($dp['nama_destinasi']) ?>" 
                     style="width: 100%; height: 100%; object-fit: cover;"
                     loading="lazy"
                     onerror="this.onerror=null; this.src='https://images.unsplash.com/photo-1507525428034-b723cf961d3e?w=200&q=80'">
              </div>

              <!-- Detail Info -->
              <div style="flex: 1; min-width: 0;">
                <div style="display: flex; align-items: center; gap: 0.3rem;">
                  <span style="font-size: 0.62rem; font-weight: 700; color: #0284c7; background: #e0f2fe; padding: 0.1rem 0.35rem; border-radius: 3px;">
                    <?= htmlspecialchars($dp['nama_kategori'] ?? 'Wisata') ?>
                  </span>
                  <span style="font-size: 0.68rem; color: #f59e0b; font-weight: 800; display: inline-flex; align-items: center; gap: 2px;">
                    <i class="fa-solid fa-star"></i> <?= number_format((float)$dp['avg_rating'], 1) ?>
                  </span>
                </div>

                <div style="font-weight: 800; font-size: 0.82rem; color: #0f172a; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; margin-top: 0.1rem;">
                  <?= htmlspecialchars($dp['nama_destinasi']) ?>
                </div>

                <div style="font-size: 0.72rem; color: #64748b; margin-top: 0.05rem;">
                  <strong style="color: #0284c7; font-weight: 800;">Rp <?= number_format((float)$dp['harga_tiket'], 0, ',', '.') ?></strong> /orang
                  <?php if ((int)$dp['diskon_rombongan'] > 0): ?>
                    <span style="color: #ea580c; font-weight: 700; font-size: 0.65rem;">• Diskon <?= $dp['diskon_rombongan'] ?>%</span>
                  <?php endif; ?>
                </div>
              </div>

              <!-- Action Button -->
              <div>
                <a href="<?= BASE_URL ?>pengunjung/pesan_tiket.php?kategori=<?= $dp['kategori_id'] ?>&q=<?= urlencode($dp['nama_destinasi']) ?>" class="btn btn-primary btn-xs" style="border-radius: 0.4rem; padding: 0.25rem 0.55rem; font-size: 0.7rem; font-weight: 800;">
                  Pesan
                </a>
              </div>

            </div>
          <?php endforeach; ?>
        </div>

      </div>
    </div>

  </div>

</main>

<!-- Modal Gate Pass QR Code (Cocok & Optimal untuk Layar HP) -->
<div id="modalGatePass" class="luxury-modal-backdrop" style="display: none;">
  <div class="luxury-modal-box" style="max-width: 400px; text-align: center; padding: 1.25rem; border-radius: 1.15rem;">
    
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.4rem;">
      <span class="badge-member badge-member-cyan" style="font-size: 0.68rem;">
        <i class="fa-solid fa-mobile-screen-button"></i> Mobile Gate Pass
      </span>
      <button type="button" onclick="closeLuxuryModal('modalGatePass')" style="background: none; border: none; font-size: 1.2rem; color: #94a3b8; cursor: pointer;">
        <i class="fa-solid fa-xmark"></i>
      </button>
    </div>

    <h3 id="modalGateTitle" style="font-size: 1.15rem; font-weight: 800; color: #0f172a; margin: 0 0 0.15rem 0;">Gate Pass Wisata</h3>
    <span id="modalGateDate" style="font-size: 0.75rem; color: #64748b; display: block; margin-bottom: 0.5rem;"></span>

    <!-- Frame QR Khusus Layar HP dengan Kontras Tinggi -->
    <div class="qr-phone-frame">
      <img id="modalGateQrImg" src="" alt="QR Code Gate Pass">
      <code id="modalGateCode" style="display: block; font-size: 0.92rem; font-weight: 900; color: #0284c7; margin-top: 0.45rem; letter-spacing: 0.08em; font-family: monospace;"></code>
    </div>

    <!-- Tip Kecerahan HP -->
    <div style="background: #fffbeb; border: 1px solid #fde68a; border-radius: 0.5rem; padding: 0.45rem 0.65rem; margin-bottom: 0.85rem; font-size: 0.72rem; color: #92400e; display: flex; align-items: center; gap: 0.4rem; text-align: left;">
      <i class="fa-solid fa-sun text-amber-500" style="font-size: 1rem;"></i>
      <span><strong>Tips Scan HP:</strong> Naikkan kecerahan layar HP ke maksimal saat didekatkan ke scanner gate masuk.</span>
    </div>

    <div style="display: flex; gap: 0.35rem; justify-content: center; flex-wrap: wrap;">
      <button type="button" onclick="copyToClipboard(document.getElementById('modalGateCode').innerText, 'Kode Booking')" class="btn btn-secondary btn-xs" style="border-radius: 0.45rem; font-weight: 700; height: 32px; padding: 0 0.75rem;">
        <i class="fa-solid fa-copy"></i> Salin Kode
      </button>
      <a id="modalDownloadQrBtn" href="" target="_blank" download="GatePass_QR.png" class="btn btn-secondary btn-xs" style="border-radius: 0.45rem; font-weight: 700; height: 32px; padding: 0 0.75rem; display: inline-flex; align-items: center; gap: 0.3rem;">
        <i class="fa-solid fa-download"></i> Simpan Gambar di HP
      </a>
      <button type="button" onclick="closeLuxuryModal('modalGatePass')" class="btn btn-primary btn-xs" style="border-radius: 0.45rem; font-weight: 800; height: 32px; padding: 0 0.9rem;">
        Tutup
      </button>
    </div>
  </div>
</div>

<script>
  function openGateModal(kode, destinasi, tanggal, tiket) {
    document.getElementById('modalGateTitle').innerText = destinasi;
    document.getElementById('modalGateDate').innerText = 'Jadwal: ' + tanggal + ' (' + tiket + ' Tiket)';
    document.getElementById('modalGateCode').innerText = kode;
    
    // Gunakan resolusi tinggi 400x400 dengan margin dan Error Correction M untuk HP
    const qrUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=400x400&margin=2&ecc=M&data=' + encodeURIComponent(kode);
    document.getElementById('modalGateQrImg').src = qrUrl;
    document.getElementById('modalDownloadQrBtn').href = qrUrl;
    
    openLuxuryModal('modalGatePass');
  }
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
