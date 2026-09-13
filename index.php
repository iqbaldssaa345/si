<?php
require_once __DIR__ . '/config/database.php';

// Ambil Kategori Wisata
$kategoriList = $pdo->query("SELECT k.*, COUNT(d.id) as total_destinasi 
                             FROM kategori_wisata k 
                             LEFT JOIN destinasi d ON k.id = d.kategori_id AND d.status = 'buka' 
                             GROUP BY k.id 
                             ORDER BY k.nama_kategori ASC")->fetchAll();

// Ambil Destinasi Populer / Unggulan
$destinasiUnggulan = $pdo->query("SELECT d.*, k.nama_kategori 
                                  FROM destinasi d 
                                  JOIN kategori_wisata k ON d.kategori_id = k.id 
                                  WHERE d.status = 'buka' 
                                  ORDER BY d.rating DESC LIMIT 6")->fetchAll();

// Ambil Semua Destinasi untuk Live Calculator Widget
$allDestinasi = $pdo->query("SELECT id, nama_destinasi, harga_tiket, diskon_rombongan, min_rombongan, lokasi FROM destinasi WHERE status = 'buka' ORDER BY nama_destinasi ASC")->fetchAll();

// Ambil Testimoni Terbaru
$ulasanTerbaru = $pdo->query("SELECT u.*, us.nama as nama_user, us.foto as user_foto, d.nama_destinasi 
                              FROM ulasan u 
                              JOIN users us ON u.user_id = us.id 
                              JOIN destinasi d ON u.destinasi_id = d.id 
                              ORDER BY u.created_at DESC LIMIT 4")->fetchAll();

// Total Statistik untuk Hero Counters
$totalDestinasi = $pdo->query("SELECT COUNT(*) FROM destinasi WHERE status = 'buka'")->fetchColumn();
$totalKunjungan = $pdo->query("SELECT COALESCE(SUM(jumlah_pengunjung), 0) FROM presensi_kunjungan")->fetchColumn();
$totalUlasan = $pdo->query("SELECT COUNT(*) FROM ulasan")->fetchColumn();

$pageTitle = "Pesona Nusantara - Sistem Informasi & Reservasi Wisata Berkelas Indonesia";
require_once __DIR__ . '/includes/header.php';
?>

<!-- ==========================================================================
     EXECUTIVE REAL-TIME STATUS BAR (LIVE CLOCK, HARI, TANGGAL, CUACA & STATUS)
     ========================================================================== -->
<div class="live-luxury-topbar">
  <div class="container live-luxury-topbar-inner">
    
    <!-- Left: Dynamic Greeting, Day & Indonesian Full Date -->
    <div class="live-status-group">
      <span class="live-greeting-pill" id="liveGreetingText">
        <i class="fa-solid fa-sparkles text-amber"></i> <span id="greetingLabel">Selamat Datang</span>
      </span>

      <div class="live-date-pill">
        <i class="fa-solid fa-calendar-days text-teal"></i>
        <span id="liveFullDate">Memuat kalender pariwisata...</span>
      </div>

      <div class="live-clock-pill" title="Waktu Indonesia Barat (WIB)">
        <i class="fa-solid fa-clock"></i>
        <span id="liveDigitalClock">00:00:00 WIB</span>
      </div>
    </div>

    <!-- Right: Simulated Weather, Gate Operational Badge & VIP Concierge Link -->
    <div class="live-status-group">
      
      <div class="live-weather-pill" title="Kondisi Cuaca Kawasan Wisata">
        <i class="fa-solid fa-cloud-sun text-amber" id="liveWeatherIcon"></i>
        <span id="liveWeatherText">28°C Cerah Berawan</span>
      </div>

      <div class="live-gate-badge" title="Status Pintu Masuk Loket Resmi">
        <span class="live-gate-pulse"></span>
        <span>Gerbang Wisata: BUKA</span>
      </div>

      <a href="https://wa.me/<?= preg_replace('/[^0-9]/', '', $settings['kontak_telp'] ?? '6282199887766') ?>?text=Halo%20VIP%20Concierge,%20saya%20butuh%20panduan%20reservasi%20tiket" target="_blank" class="live-vip-pill" title="Layanan Bantuan Khusus Pengunjung VIP">
        <i class="fa-brands fa-whatsapp text-emerald"></i> <span>VIP Concierge</span>
      </a>

    </div>

  </div>
</div>

<?php require_once __DIR__ . '/includes/navbar.php'; ?>

<!-- ==========================================================================
     HERO SECTION ULTRA LUXURY WITH REALTIME HUD
     ========================================================================== -->
<section class="hero-luxury-wrapper">
  <div class="container hero-content" style="position: relative; z-index: 10; text-align: center;">
    
    <!-- Top Announcement Badge -->
    <div class="hero-announcement-ticker">
      <span class="ticker-dot"></span>
      <span><i class="fa-solid fa-crown text-amber"></i> <?= htmlspecialchars($settings['tagline'] ?? 'Sistem Informasi & Reservasi Wisata Berkelas Indonesia') ?></span>
    </div>

    <!-- Main Hero Headline -->
    <h1 class="hero-title-luxury">
      Nikmati Keajaiban Alam & Pesona <br>
      <span class="hero-title-gradient">Wisata Terbaik Nusantara</span>
    </h1>

    <p class="hero-subtitle-luxury">
      Temukan keindahan tak terbatas destinasi wisata Indonesia. Nikmati kemudahan reservasi tiket resmi terintegrasi, promo rombongan otomatis, dan E-Ticket QR Code instan tanpa antre di loket.
    </p>

    <!-- Interactive Live Clock & Tourism HUD Glass Card -->
    <div class="hero-clock-hud">
      <div class="hero-clock-item">
        <div class="hero-clock-icon"><i class="fa-solid fa-calendar-check"></i></div>
        <div>
          <div class="hero-clock-label">Hari & Tanggal</div>
          <div class="hero-clock-value" id="hudDayDate">Memuat Hari...</div>
        </div>
      </div>

      <div class="hero-clock-divider"></div>

      <div class="hero-clock-item">
        <div class="hero-clock-icon" style="color: #fbbf24;"><i class="fa-solid fa-stopwatch"></i></div>
        <div>
          <div class="hero-clock-label">Waktu Real-Time</div>
          <div class="hero-clock-value text-amber" id="hudClock">00:00:00 WIB</div>
        </div>
      </div>

      <div class="hero-clock-divider"></div>

      <div class="hero-clock-item">
        <div class="hero-clock-icon" style="color: #34d399;"><i class="fa-solid fa-shield-halved"></i></div>
        <div>
          <div class="hero-clock-label">Sistem E-Ticketing</div>
          <div class="hero-clock-value text-emerald">QR Code Online</div>
        </div>
      </div>

      <div class="hero-clock-divider"></div>

      <div class="hero-clock-item">
        <div class="hero-clock-icon" style="color: #38bdf8;"><i class="fa-solid fa-circle-check"></i></div>
        <div>
          <div class="hero-clock-label">Garansi Layanan</div>
          <div class="hero-clock-value text-blue">100% Resmi & Sah</div>
        </div>
      </div>
    </div>

    <!-- Luxury Interactive Discovery & Search Box -->
    <div class="hero-search-container">
      
      <!-- Quick Filter Tabs -->
      <div class="hero-search-tabs">
        <button type="button" class="search-tab-btn active" onclick="setSearchFilter('', '')">
          <i class="fa-solid fa-compass"></i> Semua Destinasi
        </button>
        <button type="button" class="search-tab-btn" onclick="setSearchFilter('', 'rombongan')">
          <i class="fa-solid fa-users text-amber"></i> Promo Rombongan
        </button>
        <button type="button" class="search-tab-btn" onclick="setSearchFilter('1', '')">
          <i class="fa-solid fa-mountain-sun text-teal"></i> Wisata Alam
        </button>
        <button type="button" class="search-tab-btn" onclick="setSearchFilter('2', '')">
          <i class="fa-solid fa-water text-blue"></i> Wisata Bahari
        </button>
      </div>

      <!-- Search Form -->
      <form action="<?= BASE_URL ?>destinasi.php" method="GET" id="heroSearchForm">
        <div class="search-fields-grid">
          
          <div class="search-field-luxury">
            <label><i class="fa-solid fa-magnifying-glass text-primary"></i> Cari Nama / Lokasi Wisata</label>
            <div class="search-input-wrapper">
              <i class="fa-solid fa-location-dot field-icon"></i>
              <input type="text" name="q" id="searchInput" placeholder="Cth: Bromo, Borobudur, Kelingking, Batu...">
            </div>
          </div>

          <div class="search-field-luxury">
            <label><i class="fa-solid fa-layer-group text-primary"></i> Kategori Wisata</label>
            <div class="search-input-wrapper">
              <i class="fa-solid fa-shapes field-icon"></i>
              <select name="kategori" id="searchKategori">
                <option value="">Semua Kategori Wisata</option>
                <?php foreach ($kategoriList as $kat): ?>
                  <option value="<?= $kat['id'] ?>"><?= htmlspecialchars($kat['nama_kategori']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>

          <button type="submit" class="btn-search-luxury">
            <i class="fa-solid fa-paper-plane"></i> Temukan Wisata
          </button>

        </div>
      </form>

      <!-- Quick Popular Tags -->
      <div class="hero-trending-tags">
        <span class="trending-label"><i class="fa-solid fa-fire text-amber"></i> Populer:</span>
        <a href="javascript:void(0)" onclick="quickTagSearch('Gunung Bromo')" class="trending-chip">🌋 Gunung Bromo</a>
        <a href="javascript:void(0)" onclick="quickTagSearch('Candi Borobudur')" class="trending-chip">🏛️ Borobudur</a>
        <a href="javascript:void(0)" onclick="quickTagSearch('Kelingking')" class="trending-chip">🌊 Nusa Penida</a>
        <a href="javascript:void(0)" onclick="quickTagSearch('Kawah Ijen')" class="trending-chip">✨ Kawah Ijen</a>
        <a href="javascript:void(0)" onclick="quickTagSearch('Raja Ampat')" class="trending-chip">🏝️ Raja Ampat</a>
      </div>

    </div>

    <!-- Luxury Stats Counters Stack -->
    <div class="hero-stats-luxury">
      <div class="hero-stat-card">
        <div class="hero-stat-icon"><i class="fa-solid fa-map-location-dot"></i></div>
        <div>
          <div class="hero-stat-num"><?= number_format($totalDestinasi) ?>+</div>
          <div class="hero-stat-desc">Destinasi Pilihan</div>
        </div>
      </div>

      <div class="hero-stat-card">
        <div class="hero-stat-icon"><i class="fa-solid fa-users"></i></div>
        <div>
          <div class="hero-stat-num"><?= number_format($totalKunjungan + 2850) ?>+</div>
          <div class="hero-stat-desc">Wisatawan Terdata</div>
        </div>
      </div>

      <div class="hero-stat-card">
        <div class="hero-stat-icon"><i class="fa-solid fa-star text-amber"></i></div>
        <div>
          <div class="hero-stat-num">99.8%</div>
          <div class="hero-stat-desc">Rating Kepuasan</div>
        </div>
      </div>

      <div class="hero-stat-card">
        <div class="hero-stat-icon"><i class="fa-solid fa-shield-check text-emerald"></i></div>
        <div>
          <div class="hero-stat-num">100%</div>
          <div class="hero-stat-desc">Resmi & Terverifikasi</div>
        </div>
      </div>
    </div>

  </div>
</section>

<!-- ==========================================================================
     FEATURE PILLARS / 4 KEUNGGULAN UTAMA
     ========================================================================== -->
<section class="container feature-pillars-section">
  <div class="grid grid-cols-4 gap-6">
    
    <div class="pillar-card">
      <div class="flex justify-between items-center mb-3">
        <span class="badge badge-primary text-xs"><i class="fa-solid fa-bolt text-amber"></i> Cepat</span>
        <span class="text-xs font-bold text-muted">FITUR 01</span>
      </div>
      <div class="pillar-icon-box pillar-icon-teal">
        <i class="fa-solid fa-qrcode"></i>
      </div>
      <h3 class="pillar-title">E-Ticket QR Instan</h3>
      <p class="pillar-desc">Pesan tiket dalam 1 menit, barcode digital langsung terbit di smartphone tanpa perlu antre di loket.</p>
    </div>

    <div class="pillar-card">
      <div class="flex justify-between items-center mb-3">
        <span class="badge badge-accent text-xs"><i class="fa-solid fa-tags text-amber"></i> Hemat s/d 20%</span>
        <span class="text-xs font-bold text-muted">FITUR 02</span>
      </div>
      <div class="pillar-icon-box pillar-icon-amber">
        <i class="fa-solid fa-users-line"></i>
      </div>
      <h3 class="pillar-title">Diskon Rombongan Otomatis</h3>
      <p class="pillar-desc">Dapatkan potongan harga spesial langsung dihitung otomatis untuk rombongan sekolah, instansi & keluarga.</p>
    </div>

    <div class="pillar-card">
      <div class="flex justify-between items-center mb-3">
        <span class="badge badge-primary text-xs"><i class="fa-solid fa-shield-check text-blue"></i> Terverifikasi</span>
        <span class="text-xs font-bold text-muted">FITUR 03</span>
      </div>
      <div class="pillar-icon-box pillar-icon-blue">
        <i class="fa-solid fa-credit-card"></i>
      </div>
      <h3 class="pillar-title">Multi Pembayaran Resmi</h3>
      <p class="pillar-desc">Didukung QRIS instan, Transfer Bank BCA/Mandiri/BRI terverifikasi otomatis, dan loket tunai terpadu.</p>
    </div>

    <div class="pillar-card">
      <div class="flex justify-between items-center mb-3">
        <span class="badge badge-success text-xs"><i class="fa-solid fa-circle-check text-emerald"></i> < 3 Detik</span>
        <span class="text-xs font-bold text-muted">FITUR 04</span>
      </div>
      <div class="pillar-icon-box pillar-icon-emerald">
        <i class="fa-solid fa-shield-halved"></i>
      </div>
      <h3 class="pillar-title">Validasi Gerbang Kilat</h3>
      <p class="pillar-desc">Scan barcode real-time oleh petugas di pintu masuk untuk proses check-in cepat kurang dari 3 detik.</p>
    </div>

  </div>
</section>

<!-- ==========================================================================
     KATEGORI WISATA SECTION
     ========================================================================== -->
<section class="section" id="kategori">
  <div class="container">
    <div class="section-header">
      <span class="badge badge-primary"><i class="fa-solid fa-shapes"></i> Kategori Pilihan</span>
      <h2 class="section-title">Eksplorasi Sesuai Minat Wisata Anda</h2>
      <p class="section-subtitle">Pilih beragam destinasi wisata alam sejuk, bahari eksotis, warisan budaya megah, hingga rekreasi keluarga seru.</p>
    </div>

    <div class="category-grid">
      <?php foreach ($kategoriList as $kat): ?>
        <a href="<?= BASE_URL ?>destinasi.php?kategori=<?= $kat['id'] ?>" class="category-card-luxury">
          <div class="category-icon-luxury">
            <?= renderKategoriIcon($kat['icon'], 'fa-shapes') ?>
          </div>
          <h3 class="category-title-luxury"><?= htmlspecialchars($kat['nama_kategori']) ?></h3>
          <span class="category-pill-count"><?= $kat['total_destinasi'] ?> Destinasi Pilihan</span>
        </a>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<!-- ==========================================================================
     DESTINASI UNGGULAN SHOWCASE
     ========================================================================== -->
<section class="section bg-light" id="destinasi">
  <div class="container">
    
    <div class="flex items-center justify-between" style="margin-bottom: 2rem; flex-wrap: wrap; gap: 1rem;">
      <div>
        <span class="badge badge-primary"><i class="fa-solid fa-fire text-amber"></i> Destinasi Unggulan</span>
        <h2 class="section-title" style="margin-bottom: 0.35rem;">Paling Favorit & Banyak Dikunjungi</h2>
        <p class="text-muted" style="margin: 0;">Destinasi dengan rating ulasan tertinggi dan fasilitas terlengkap pilihan wisatawan.</p>
      </div>
      <a href="<?= BASE_URL ?>destinasi.php" class="btn btn-outline-primary shadow-sm" style="font-weight: 700;">
        Lihat Semua Wisata (<?= $totalDestinasi ?>) <i class="fa-solid fa-arrow-right"></i>
      </a>
    </div>

    <!-- Category Filter Pills Bar -->
    <div class="filter-pills-bar">
      <button type="button" class="filter-pill-btn active" onclick="filterDestinasiCards('all', this)">
        <i class="fa-solid fa-border-all"></i> Semua Wisata Unggulan
      </button>
      <?php foreach ($kategoriList as $kat): ?>
        <button type="button" class="filter-pill-btn" onclick="filterDestinasiCards('<?= $kat['id'] ?>', this)">
          <?= renderKategoriIcon($kat['icon'], 'fa-tag') ?>
          <?= htmlspecialchars($kat['nama_kategori']) ?>
        </button>
      <?php endforeach; ?>
    </div>

    <!-- Destination Cards Grid -->
    <div class="destinasi-grid" id="destinasiGrid">
      <?php foreach ($destinasiUnggulan as $wisata): 
        $foto = !empty($wisata['foto_utama']) ? (strpos($wisata['foto_utama'], 'http') === 0 ? $wisata['foto_utama'] : BASE_URL . 'assets/uploads/destinasi/' . $wisata['foto_utama']) : 'https://images.unsplash.com/photo-1507525428034-b723cf961d3e?auto=format&fit=crop&w=800&q=80';
      ?>
        <div class="destinasi-card-luxury destinasi-item-card" data-kategori="<?= $wisata['kategori_id'] ?>">
          <div class="destinasi-img-container">
            <img src="<?= htmlspecialchars($foto) ?>" alt="<?= htmlspecialchars($wisata['nama_destinasi']) ?>" loading="lazy">
            <div class="destinasi-gradient-overlay"></div>
            
            <span class="badge-category-glass">
              <i class="fa-solid fa-shapes"></i> <?= htmlspecialchars($wisata['nama_kategori']) ?>
            </span>
            
            <?php if ($wisata['diskon_rombongan'] > 0): ?>
              <span class="badge-discount-glass">
                <i class="fa-solid fa-tag"></i> Diskon <?= $wisata['diskon_rombongan'] ?>% Grup
              </span>
            <?php endif; ?>
          </div>

          <div class="destinasi-card-body">
            
            <div class="destinasi-meta-top">
              <div class="rating-badge-gold">
                <i class="fa-solid fa-star"></i>
                <span><?= number_format($wisata['rating'], 1) ?></span>
                <span class="text-xs text-muted" style="font-weight: 500;">/ 5.0</span>
              </div>
              <div class="text-xs text-muted">
                <i class="fa-solid fa-location-dot text-primary"></i> <?= htmlspecialchars(substr($wisata['lokasi'], 0, 24)) ?>
              </div>
            </div>

            <h3 class="destinasi-title">
              <a href="<?= BASE_URL ?>detail.php?id=<?= $wisata['id'] ?>"><?= htmlspecialchars($wisata['nama_destinasi']) ?></a>
            </h3>

            <p class="destinasi-excerpt">
              <?= htmlspecialchars(substr($wisata['deskripsi'], 0, 105)) . '...' ?>
            </p>

            <div class="destinasi-footer-box">
              <div class="destinasi-price-tag">
                <span class="price-label-text">Tiket Masuk:</span>
                <span class="price-nominal-text"><?= formatRupiah($wisata['harga_tiket']) ?></span>
              </div>
              <div class="flex gap-2">
                <a href="<?= BASE_URL ?>detail.php?id=<?= $wisata['id'] ?>" class="btn btn-sm btn-secondary" title="Detail Destinasi">
                  <i class="fa-solid fa-eye"></i>
                </a>
                <a href="<?= BASE_URL ?>booking.php?id=<?= $wisata['id'] ?>" class="btn btn-sm btn-primary shadow-glow">
                  <i class="fa-solid fa-ticket"></i> Pesan Tiket
                </a>
              </div>
            </div>

          </div>
        </div>
      <?php endforeach; ?>
    </div>

  </div>
</section>

<!-- ==========================================================================
     LIVE INTERACTIVE TICKET & GROUP TRIP ESTIMATOR WIDGET
     ========================================================================== -->
<section class="section" id="kalkulator">
  <div class="container">
    <div class="luxury-calc-container">
      <div class="grid grid-cols-2 gap-8 items-center">
        
        <!-- Left Side: Interactive Input Controls -->
        <div>
          <span class="badge badge-accent mb-2"><i class="fa-solid fa-calculator"></i> Live Simulator Biaya</span>
          <h2 class="text-3xl font-black text-dark mb-2">Hitung Estimasi Tiket & Diskon Rombongan</h2>
          <p class="text-slate-600 mb-6 text-sm" style="line-height: 1.6;">
            Rencanakan perjalanan wisata bersama keluarga, komunitas, atau sekolah dengan simulasi harga langsung yang transparan dan hemat.
          </p>

          <!-- Select Destinasi -->
          <div class="form-group mb-4">
            <label class="form-label font-bold text-xs uppercase text-slate-600">Pilih Destinasi Wisata</label>
            <select id="simDestinasi" class="form-control font-bold text-dark" style="font-size: 0.95rem;">
              <?php foreach ($allDestinasi as $ad): ?>
                <option value="<?= $ad['id'] ?>" 
                        data-harga="<?= $ad['harga_tiket'] ?>" 
                        data-diskon="<?= $ad['diskon_rombongan'] ?>" 
                        data-min="<?= $ad['min_rombongan'] ?>">
                  <?= htmlspecialchars($ad['nama_destinasi']) ?> - <?= formatRupiah($ad['harga_tiket']) ?> / tiket
                </option>
              <?php endforeach; ?>
            </select>
          </div>

          <!-- Quantity Stepper & Category -->
          <div class="grid grid-cols-2 gap-4 mb-4">
            <div class="form-group">
              <label class="form-label font-bold text-xs uppercase text-slate-600">Jumlah Tiket (Wisatawan)</label>
              <div class="flex items-center gap-2">
                <button type="button" class="stepper-btn" onclick="stepQty(-1)"><i class="fa-solid fa-minus"></i></button>
                <input type="number" id="simJumlah" class="form-control font-bold text-center text-lg" value="10" min="1" max="500" style="padding: 0.5rem;">
                <button type="button" class="stepper-btn" onclick="stepQty(1)"><i class="fa-solid fa-plus"></i></button>
              </div>
            </div>

            <div class="form-group">
              <label class="form-label font-bold text-xs uppercase text-slate-600">Tipe Pemesanan</label>
              <select id="simTipe" class="form-control font-semibold">
                <option value="sendiri">Perorangan / Reguler</option>
                <option value="rombongan" selected>Rombongan / Grup</option>
              </select>
            </div>
          </div>

          <!-- Quick Quantity Shortcuts -->
          <div class="flex items-center gap-2 mb-4" style="flex-wrap: wrap;">
            <span class="text-xs text-muted font-bold">Pintasan Jumlah:</span>
            <button type="button" class="quick-qty-chip" onclick="setSimQty(1)">1 Orang</button>
            <button type="button" class="quick-qty-chip" onclick="setSimQty(5)">5 Orang</button>
            <button type="button" class="quick-qty-chip active" onclick="setSimQty(10)">10 Orang (Grup)</button>
            <button type="button" class="quick-qty-chip" onclick="setSimQty(20)">20 Orang</button>
            <button type="button" class="quick-qty-chip" onclick="setSimQty(50)">50 Orang</button>
            <button type="button" class="quick-qty-chip" onclick="setSimQty(100)">100 Orang</button>
          </div>

          <!-- Feature checklist -->
          <div class="flex flex-col gap-2 text-xs text-slate-600 mt-4">
            <div class="flex items-center gap-2"><i class="fa-solid fa-circle-check text-emerald"></i> 1 Barcode terpadu untuk seluruh anggota rombongan</div>
            <div class="flex items-center gap-2"><i class="fa-solid fa-circle-check text-emerald"></i> Potongan harga diskon grup dihitung otomatis</div>
            <div class="flex items-center gap-2"><i class="fa-solid fa-circle-check text-emerald"></i> Garansi E-Ticket resmi terverifikasi</div>
          </div>

        </div>

        <!-- Right Side: Luxury Digital Receipt Box -->
        <div class="card p-6 bg-white border-2 border-slate-200 shadow-lg" style="border-radius: var(--radius-xl);">
          
          <div class="flex items-center justify-between pb-3 mb-4 border-b">
            <span class="font-bold text-dark text-base"><i class="fa-solid fa-receipt text-primary"></i> Estimasi Tagihan Tiket</span>
            <span class="badge badge-success" id="simDiskonBadge">Diskon Rombongan Aktif</span>
          </div>

          <div class="flex flex-col gap-3 text-sm text-slate-600 mb-6">
            <div class="flex items-center justify-between">
              <span>Tarif Satuan Tiket:</span>
              <strong id="simHargaSatuan" class="text-dark font-bold">Rp 35.000</strong>
            </div>
            
            <div class="flex items-center justify-between">
              <span>Jumlah Tiket:</span>
              <strong id="simJumlahDisplay" class="text-dark font-bold">10 Wisatawan</strong>
            </div>

            <div class="flex items-center justify-between">
              <span>Subtotal Kotor:</span>
              <strong id="simSubtotal" class="text-dark font-bold">Rp 350.000</strong>
            </div>

            <div class="flex items-center justify-between text-success" id="simDiskonRow">
              <span class="flex items-center gap-1"><i class="fa-solid fa-sparkles text-amber"></i> Hemat Potongan Rombongan:</span>
              <strong id="simNominalDiskon" class="font-black text-emerald">- Rp 52.500</strong>
            </div>

            <hr style="border-top: 1.5px dashed #cbd5e1; margin: 0.5rem 0;">

            <div class="flex items-baseline justify-between">
              <div>
                <span class="font-black text-dark text-base block">Total Pembayaran:</span>
                <span class="text-xs text-muted">Sudah termasuk pajak & akses resmi</span>
              </div>
              <strong id="simTotalBayar" class="text-3xl text-primary font-black">Rp 297.500</strong>
            </div>
          </div>

          <a href="<?= BASE_URL ?>booking.php?id=1" id="simBookingBtn" class="btn btn-primary btn-block btn-lg shadow-glow" style="font-weight: 800;">
            <i class="fa-solid fa-ticket"></i> Lanjutkan Pemesanan Tiket Ini
          </a>

          <p class="text-center text-xs text-muted mt-3" style="margin-bottom: 0;">
            <i class="fa-solid fa-lock text-primary"></i> Transaksi Aman 100% Terverifikasi E-Ticket QR Code
          </p>

        </div>

      </div>
    </div>
  </div>
</section>

<!-- ==========================================================================
     VIP GROUP & CORPORATE TRAVEL PRIVILEGE BANNER
     ========================================================================== -->
<section class="section bg-light">
  <div class="container">
    <div class="vip-promo-banner">
      <div class="grid grid-cols-2 gap-8 items-center">
        
        <div>
          <span class="badge badge-accent mb-3"><i class="fa-solid fa-crown text-amber"></i> Penawaran Eksklusif Rombongan</span>
          <h2 class="text-3xl font-black text-white mb-3" style="line-height: 1.25;">
            Liburan Bersama Grup, Sekolah & Instansi Jadi Jauh Lebih Praktis & Hemat!
          </h2>
          <p class="text-slate-300 text-sm mb-6" style="line-height: 1.7;">
            Dapatkan fasilitas check-in Fast Track dengan <strong>1 Kode QR Terpadu</strong> untuk seluruh peserta, potongan harga langsung hingga <strong>20%</strong>, serta rekapitulasi data kunjungan resmi.
          </p>

          <div class="flex gap-3" style="flex-wrap: wrap;">
            <a href="<?= BASE_URL ?>destinasi.php" class="btn btn-accent btn-lg shadow-glow-gold">
              <i class="fa-solid fa-users"></i> Booking Paket Rombongan
            </a>
            <a href="https://wa.me/<?= preg_replace('/[^0-9]/', '', $settings['kontak_telp'] ?? '6282199887766') ?>?text=Halo%20Admin,%20saya%20ingin%20konsultasi%20reservasi%20tiket%20rombongan" target="_blank" class="btn btn-glass btn-lg">
              <i class="fa-brands fa-whatsapp text-emerald"></i> Konsultasi via WhatsApp
            </a>
          </div>
        </div>

        <div class="flex justify-center">
          <div class="card p-6 bg-white border-2 border-emerald shadow-2xl" style="max-width: 380px; border-radius: var(--radius-xl); color: var(--dark-900);">
            <div class="flex items-center gap-3 mb-4">
              <div class="pillar-icon-box pillar-icon-teal" style="width: 48px; height: 48px; font-size: 1.25rem;">
                <i class="fa-solid fa-shield-halved"></i>
              </div>
              <div>
                <h4 style="margin: 0; font-weight: 800; font-size: 1rem;">Official Gate Fast Track</h4>
                <span class="text-xs text-muted">Jaminan E-Ticket Resmi</span>
              </div>
            </div>

            <div class="flex flex-col gap-2 text-xs text-slate-600 mb-4">
              <div class="flex items-center gap-2"><i class="fa-solid fa-check text-emerald font-bold"></i> 1 QR Code untuk 5 s/d 500 Peserta</div>
              <div class="flex items-center gap-2"><i class="fa-solid fa-check text-emerald font-bold"></i> Validasi Barcode Petugas < 3 Detik</div>
              <div class="flex items-center gap-2"><i class="fa-solid fa-check text-emerald font-bold"></i> Rekap Riwayat Presensi Lengkap</div>
            </div>

            <div class="p-3 bg-light rounded text-center border">
              <span class="text-xs text-muted block mb-1">Status Sistem E-Ticketing:</span>
              <strong class="text-emerald font-extrabold text-sm"><i class="fa-solid fa-circle-dot text-emerald"></i> ONLINE & AKTIF</strong>
            </div>
          </div>
        </div>

      </div>
    </div>
  </div>
</section>

<!-- ==========================================================================
     CARA KERJA (4 LANGKAH MUDAH RESERVASI)
     ========================================================================== -->
<section class="section">
  <div class="container">
    <div class="section-header">
      <span class="badge badge-primary"><i class="fa-solid fa-route"></i> Alur Praktis</span>
      <h2 class="section-title">4 Langkah Mudah Menikmati Liburan Impian</h2>
      <p class="section-subtitle">Proses reservasi tiket wisata digital resmi yang cepat, aman, dan instan.</p>
    </div>

    <div class="grid grid-cols-4 gap-6">
      
      <div class="journey-step-card">
        <span class="journey-step-num">Langkah 01</span>
        <div class="pillar-icon-box pillar-icon-teal mx-auto mb-4" style="margin: 0.5rem auto 1rem;">
          <i class="fa-solid fa-map-location-dot"></i>
        </div>
        <h3 class="text-dark font-bold text-lg mb-2">Pilih Destinasi</h3>
        <p class="text-sm text-slate-600">Cari dan tentukan objek wisata impian Anda dari katalog nusantara terlengkap.</p>
      </div>

      <div class="journey-step-card">
        <span class="journey-step-num">Langkah 02</span>
        <div class="pillar-icon-box pillar-icon-blue mx-auto mb-4" style="margin: 0.5rem auto 1rem;">
          <i class="fa-solid fa-calendar-check"></i>
        </div>
        <h3 class="text-dark font-bold text-lg mb-2">Tentukan Tiket</h3>
        <p class="text-sm text-slate-600">Pilih tanggal kunjungan dan jumlah tiket (perorangan atau rombongan berdiskon).</p>
      </div>

      <div class="journey-step-card">
        <span class="journey-step-num">Langkah 03</span>
        <div class="pillar-icon-box pillar-icon-amber mx-auto mb-4" style="margin: 0.5rem auto 1rem;">
          <i class="fa-solid fa-credit-card"></i>
        </div>
        <h3 class="text-dark font-bold text-lg mb-2">Bayar Instan</h3>
        <p class="text-sm text-slate-600">Selesaikan pembayaran melalui QRIS, Transfer Bank, atau bayar di loket.</p>
      </div>

      <div class="journey-step-card">
        <span class="journey-step-num">Langkah 04</span>
        <div class="pillar-icon-box pillar-icon-emerald mx-auto mb-4" style="margin: 0.5rem auto 1rem;">
          <i class="fa-solid fa-qrcode"></i>
        </div>
        <h3 class="text-dark font-bold text-lg mb-2">Scan & Masuk</h3>
        <p class="text-sm text-slate-600">Tunjukkan QR Code E-Ticket di smartphone pada petugas gerbang masuk.</p>
      </div>

    </div>
  </div>
</section>

<!-- ==========================================================================
     TESTIMONI WISATAWAN
     ========================================================================== -->
<?php if (!empty($ulasanTerbaru)): ?>
<section class="section bg-light" id="testimoni">
  <div class="container">
    <div class="section-header">
      <span class="badge badge-primary"><i class="fa-solid fa-comments"></i> Ulasan Nyata</span>
      <h2 class="section-title">Apa Kata Mereka Tentang Wisata Kami?</h2>
      <p class="section-subtitle">Pengalaman nyata dari ribuan wisatawan yang telah menikmati liburan seru dan kemudahan reservasi.</p>
    </div>

    <div class="grid grid-cols-2 gap-6">
      <?php foreach ($ulasanTerbaru as $ul): ?>
        <div class="testimonial-card-luxury">
          <div>
            <div class="flex items-center justify-between mb-3">
              <div class="flex items-center gap-3">
                <?php 
                  $uFoto = trim($ul['user_foto'] ?? '');
                  $userAvatarUrl = '';
                  if (!empty($uFoto) && $uFoto !== 'default_avatar.png') {
                      if (strpos($uFoto, 'http://') === 0 || strpos($uFoto, 'https://') === 0) {
                          $userAvatarUrl = $uFoto;
                      } elseif (file_exists(__DIR__ . '/assets/uploads/users/' . $uFoto)) {
                          $userAvatarUrl = BASE_URL . 'assets/uploads/users/' . $uFoto;
                      }
                  }
                ?>
                <?php if (!empty($userAvatarUrl)): ?>
                  <img src="<?= htmlspecialchars($userAvatarUrl) ?>" alt="<?= htmlspecialchars($ul['nama_user']) ?>" style="width: 46px; height: 46px; border-radius: 50%; object-fit: cover; border: 2px solid #38bdf8; box-shadow: 0 4px 10px rgba(0,0,0,0.15);" onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                  <div class="avatar" style="display: none; width: 46px; height: 46px; font-size: 1.1rem; background: linear-gradient(135deg, var(--primary), var(--secondary));">
                    <?= strtoupper(substr($ul['nama_user'], 0, 1)) ?>
                  </div>
                <?php else: ?>
                  <div class="avatar" style="width: 46px; height: 46px; font-size: 1.1rem; background: linear-gradient(135deg, var(--primary), var(--secondary));">
                    <?= strtoupper(substr($ul['nama_user'], 0, 1)) ?>
                  </div>
                <?php endif; ?>
                <div>
                  <h4 style="margin: 0; font-size: 1.05rem; font-weight: 800; color: var(--dark-900);"><?= htmlspecialchars($ul['nama_user']) ?></h4>
                  <span class="text-xs text-muted">Berkunjung ke: <strong class="text-primary"><?= htmlspecialchars($ul['nama_destinasi']) ?></strong></span>
                </div>
              </div>
              <span class="badge badge-success text-xs"><i class="fa-solid fa-circle-check"></i> Terverifikasi</span>
            </div>

            <div class="flex gap-1 text-amber mb-3">
              <?php for ($i = 1; $i <= 5; $i++): ?>
                <i class="fa-<?= $i <= $ul['rating'] ? 'solid' : 'regular' ?> fa-star"></i>
              <?php endfor; ?>
            </div>

            <p class="text-slate-600 text-sm" style="line-height: 1.7; font-style: italic;">
              "<?= htmlspecialchars($ul['komentar']) ?>"
            </p>
          </div>

          <div class="text-xs text-muted pt-3 border-t mt-4 flex items-center justify-between">
            <span><i class="fa-solid fa-clock"></i> <?= formatTanggalIndo($ul['created_at']) ?></span>
            <span class="text-primary font-bold"><i class="fa-solid fa-shield-check"></i> Pembeli Resmi</span>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php endif; ?>

<!-- ==========================================================================
     FAQ SECTION ACCORDION
     ========================================================================== -->
<section class="section" id="faq">
  <div class="container" style="max-width: 860px;">
    <div class="section-header">
      <span class="badge badge-primary"><i class="fa-solid fa-circle-question"></i> Tanya Jawab</span>
      <h2 class="section-title">Pertanyaan yang Sering Diajukan</h2>
      <p class="section-subtitle">Jawaban lengkap seputar pemesanan tiket, promo diskon rombongan, dan sistem E-Ticket QR Code.</p>
    </div>

    <div>
      <div class="faq-item-luxury active">
        <div class="faq-header-luxury" onclick="toggleFaq(this)">
          <span>Bagaimana cara memesan tiket rombongan agar mendapat diskon otomatis?</span>
          <i class="fa-solid fa-chevron-down faq-icon-chevron"></i>
        </div>
        <div class="faq-body-luxury">
          Cukup pilih destinasi wisata yang diinginkan, pilih tipe tiket <strong>Rombongan</strong> atau masukkan jumlah tiket minimal sesuai ketentuan (biasanya minimal 8-10 orang). Sistem kami secara otomatis menghitung dan memotong harga total dengan persentase diskon yang berlaku tanpa perlu kode voucher manual.
        </div>
      </div>

      <div class="faq-item-luxury">
        <div class="faq-header-luxury" onclick="toggleFaq(this)">
          <span>Apakah E-Ticket dengan QR Code bisa langsung ditunjukkan di smartphone?</span>
          <i class="fa-solid fa-chevron-down faq-icon-chevron"></i>
        </div>
        <div class="faq-body-luxury">
          Ya, Anda tidak perlu mencetak tiket di atas kertas. Setelah pembayaran diverifikasi, E-Ticket ber-QR Code dapat langsung dibuka melalui halaman <strong>Tiket Saya</strong> atau bukti e-ticket di smartphone untuk divalidasi oleh petugas pintu masuk gerbang wisata.
        </div>
      </div>

      <div class="faq-item-luxury">
        <div class="faq-header-luxury" onclick="toggleFaq(this)">
          <span>Metode pembayaran apa saja yang didukung oleh sistem?</span>
          <i class="fa-solid fa-chevron-down faq-icon-chevron"></i>
        </div>
        <div class="faq-body-luxury">
          Kami mendukung pembayaran instan via <strong>QRIS</strong> (mendukung GoPay, OVO, Dana, ShopeePay, LinkAja, dan seluruh Mobile Banking), <strong>Transfer Bank</strong> (BCA, Mandiri, BRI), serta opsi pembayaran tunai langsung di loket resmi.
        </div>
      </div>

      <div class="faq-item-luxury">
        <div class="faq-header-luxury" onclick="toggleFaq(this)">
          <span>Apakah untuk 1 rombongan besar cukup menggunakan 1 E-Ticket QR Code?</span>
          <i class="fa-solid fa-chevron-down faq-icon-chevron"></i>
        </div>
        <div class="faq-body-luxury">
          Benar sekali! Untuk pemesanan grup rombongan, sistem menerbitkan 1 QR Code terpadu yang memuat total kuota seluruh peserta. Petugas gerbang akan memvalidasi presensi seluruh rombongan sekaligus, sehingga proses masuk sangat cepat dan tertib.
        </div>
      </div>
    </div>

  </div>
</section>

<!-- ==========================================================================
     CALL TO ACTION & ASSISTANCE FOOTER BANNER
     ========================================================================== -->
<section class="section bg-light" id="kontak">
  <div class="container">
    <div class="card p-8 shadow-xl" style="border-radius: var(--radius-xl); border: 2px solid var(--primary-light); background: linear-gradient(135deg, #ffffff 0%, #f0fdfa 100%);">
      <div class="grid grid-cols-2 gap-8 items-center">
        
        <div>
          <span class="badge badge-primary mb-2"><i class="fa-solid fa-headset"></i> Layanan Bantuan 24/7</span>
          <h2 class="text-3xl font-black text-dark mb-3">Siap Menjelajahi Keindahan Nusantara?</h2>
          <p class="text-slate-600 mb-6 text-sm" style="line-height: 1.7;">
            Tim customer care kami siap membantu kebutuhan informasi tiket grup, reservasi acara khusus, maupun panduan perjalanan Anda kapan saja.
          </p>

          <div class="flex flex-col gap-3">
            <div class="flex items-center gap-3">
              <div class="pillar-icon-box pillar-icon-teal" style="width: 40px; height: 40px; font-size: 1rem;">
                <i class="fa-solid fa-location-dot"></i>
              </div>
              <div>
                <strong class="text-dark text-xs block">Alamat Kantor:</strong>
                <span class="text-xs text-muted"><?= htmlspecialchars($settings['alamat'] ?? 'Kawasan Pusat Pariwisata Indonesia') ?></span>
              </div>
            </div>

            <div class="flex items-center gap-3">
              <div class="pillar-icon-box pillar-icon-blue" style="width: 40px; height: 40px; font-size: 1rem;">
                <i class="fa-solid fa-phone"></i>
              </div>
              <div>
                <strong class="text-dark text-xs block">Telepon & WhatsApp:</strong>
                <span class="text-xs text-muted"><?= htmlspecialchars($settings['kontak_telp'] ?? '+62 821-9988-7766') ?></span>
              </div>
            </div>

            <div class="flex items-center gap-3">
              <div class="pillar-icon-box pillar-icon-amber" style="width: 40px; height: 40px; font-size: 1rem;">
                <i class="fa-solid fa-envelope"></i>
              </div>
              <div>
                <strong class="text-dark text-xs block">Email Layanan:</strong>
                <span class="text-xs text-muted"><?= htmlspecialchars($settings['kontak_email'] ?? 'kontak@pesonanusantara.id') ?></span>
              </div>
            </div>
          </div>
        </div>

        <div class="card p-8 bg-white border-2 border-slate-200 shadow-md text-center" style="border-radius: var(--radius-lg);">
          <span class="badge badge-accent mb-3"><i class="fa-solid fa-ticket"></i> Mulai Sekarang</span>
          <h3 class="text-2xl font-black text-dark mb-2">Pesan Tiket Anda Hari Ini</h3>
          <p class="text-sm text-slate-600 mb-6">Nikmati pengalaman liburan tanpa antre dengan e-ticketing resmi.</p>

          <div class="flex flex-col gap-3">
            <a href="<?= BASE_URL ?>destinasi.php" class="btn btn-primary btn-block btn-lg shadow-glow" style="font-weight: 800;">
              <i class="fa-solid fa-compass"></i> Buka Katalog Semua Destinasi
            </a>
            <?php if (!isset($_SESSION['user_id'])): ?>
              <a href="<?= BASE_URL ?>register.php" class="btn btn-secondary btn-block">
                <i class="fa-solid fa-user-plus"></i> Buat Akun Pengunjung Gratis
              </a>
            <?php else: ?>
              <a href="<?= BASE_URL ?>riwayat.php" class="btn btn-secondary btn-block">
                <i class="fa-solid fa-receipt"></i> Cek Tiket & Riwayat Saya
              </a>
            <?php endif; ?>
          </div>
        </div>

      </div>
    </div>
  </div>
</section>

<!-- ==========================================================================
     FLOATING QUICK CONCIERGE / ACTION DOCK
     ========================================================================== -->
<div class="floating-quick-dock">
  <button type="button" class="btn-float-action btn-float-top" id="btnScrollTop" title="Kembali ke Atas" onclick="window.scrollTo({top: 0, behavior: 'smooth'})">
    <i class="fa-solid fa-arrow-up"></i>
  </button>
  <a href="https://wa.me/<?= preg_replace('/[^0-9]/', '', $settings['kontak_telp'] ?? '6282199887766') ?>?text=Halo%20Admin%20Wisata,%20saya%20butuh%20bantuan%20pemesanan%20tiket" target="_blank" class="btn-float-action btn-float-wa" title="Konsultasi WhatsApp VIP">
    <i class="fa-brands fa-whatsapp"></i>
  </a>
</div>

<!-- ==========================================================================
     INTERACTIVE JAVASCRIPT LOGIC & REAL-TIME CLOCK ENGINE
     ========================================================================== -->
<script>
  // Dynamic Live Real-Time Indonesian Clock, Day, Date & Weather Engine
  const hariNames = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
  const bulanNames = [
    'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
    'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'
  ];

  function updateLiveClock() {
    const now = new Date();
    const dayName = hariNames[now.getDay()];
    const dateNum = now.getDate();
    const monthName = bulanNames[now.getMonth()];
    const yearNum = now.getFullYear();

    const hours = String(now.getHours()).padStart(2, '0');
    const minutes = String(now.getMinutes()).padStart(2, '0');
    const seconds = String(now.getSeconds()).padStart(2, '0');

    // Format strings
    const fullDateStr = `${dayName}, ${dateNum} ${monthName} ${yearNum}`;
    const clockStr = `${hours}:${minutes}:${seconds} WIB`;

    // Update Topbar
    const dateEl = document.getElementById('liveFullDate');
    const clockEl = document.getElementById('liveDigitalClock');
    if (dateEl) dateEl.innerText = fullDateStr;
    if (clockEl) {
      clockEl.innerHTML = `${hours}<span class="clock-colon">:</span>${minutes}<span class="clock-colon">:</span>${seconds} <span style="font-size:0.75rem; color:#94a3b8;">WIB</span>`;
    }

    // Update Hero HUD
    const hudDayDate = document.getElementById('hudDayDate');
    const hudClock = document.getElementById('hudClock');
    if (hudDayDate) hudDayDate.innerText = `${dayName}, ${dateNum} ${monthName}`;
    if (hudClock) hudClock.innerText = clockStr;

    // Dynamic Greeting based on Indonesian Time
    const currentHour = now.getHours();
    let greeting = 'Selamat Datang';
    let weatherIcon = 'fa-cloud-sun';
    let weatherText = '28°C Cerah Berawan';

    if (currentHour >= 4 && currentHour < 11) {
      greeting = 'Selamat Pagi, Wisatawan';
      weatherIcon = 'fa-sun text-amber';
      weatherText = '25°C Udara Sejuk Pagi';
    } else if (currentHour >= 11 && currentHour < 15) {
      greeting = 'Selamat Siang, Wisatawan';
      weatherIcon = 'fa-sun text-amber';
      weatherText = '31°C Cerah Tropis';
    } else if (currentHour >= 15 && currentHour < 18) {
      greeting = 'Selamat Sore, Wisatawan';
      weatherIcon = 'fa-cloud-sun text-amber';
      weatherText = '27°C Angin Sore Sejuk';
    } else {
      greeting = 'Selamat Malam, Wisatawan';
      weatherIcon = 'fa-moon text-blue';
      weatherText = '24°C Malam Nyaman';
    }

    const greetingEl = document.getElementById('greetingLabel');
    if (greetingEl) greetingEl.innerText = greeting;

    const wIconEl = document.getElementById('liveWeatherIcon');
    const wTextEl = document.getElementById('liveWeatherText');
    if (wIconEl) wIconEl.className = `fa-solid ${weatherIcon}`;
    if (wTextEl) wTextEl.innerText = weatherText;
  }

  // FAQ Toggle
  function toggleFaq(headerEl) {
    const item = headerEl.parentElement;
    const isCurrentActive = item.classList.contains('active');
    document.querySelectorAll('.faq-item-luxury').forEach(i => i.classList.remove('active'));
    if (!isCurrentActive) {
      item.classList.add('active');
    }
  }

  // Quick Tag Search
  function quickTagSearch(keyword) {
    const input = document.getElementById('searchInput');
    if (input) {
      input.value = keyword;
      input.focus();
      const form = document.getElementById('heroSearchForm');
      if (form) form.submit();
    }
  }

  // Search Filter Tabs
  function setSearchFilter(katId, promoType) {
    const tabs = document.querySelectorAll('.search-tab-btn');
    tabs.forEach(t => t.classList.remove('active'));
    if (window.event && window.event.currentTarget) {
      window.event.currentTarget.classList.add('active');
    }

    const select = document.getElementById('searchKategori');
    if (select) {
      select.value = katId;
    }

    if (promoType === 'rombongan') {
      const calcSection = document.getElementById('kalkulator');
      if (calcSection) {
        calcSection.scrollIntoView({ behavior: 'smooth' });
      }
    }
  }

  // Destination Category Filter Pills
  function filterDestinasiCards(kategoriId, btnEl) {
    const pills = document.querySelectorAll('.filter-pill-btn');
    pills.forEach(p => p.classList.remove('active'));
    if (btnEl) btnEl.classList.add('active');

    const items = document.querySelectorAll('.destinasi-item-card');
    items.forEach(card => {
      if (kategoriId === 'all' || card.getAttribute('data-kategori') === String(kategoriId)) {
        card.style.display = 'flex';
      } else {
        card.style.display = 'none';
      }
    });
  }

  // Live Simulator Functions
  const simDest = document.getElementById('simDestinasi');
  const simQty = document.getElementById('simJumlah');
  const simType = document.getElementById('simTipe');
  const simHargaSatuan = document.getElementById('simHargaSatuan');
  const simJumlahDisplay = document.getElementById('simJumlahDisplay');
  const simSubtotal = document.getElementById('simSubtotal');
  const simNominalDiskon = document.getElementById('simNominalDiskon');
  const simTotalBayar = document.getElementById('simTotalBayar');
  const simBookingBtn = document.getElementById('simBookingBtn');
  const simDiskonBadge = document.getElementById('simDiskonBadge');
  const simDiskonRow = document.getElementById('simDiskonRow');

  function updateSimulator() {
    if (!simDest || !simQty) return;
    const opt = simDest.options[simDest.selectedIndex];
    if (!opt) return;

    const destId = opt.value;
    const harga = parseFloat(opt.dataset.harga || '0');
    const diskonPersen = parseFloat(opt.dataset.diskon || '0');
    const minRombongan = parseInt(opt.dataset.min || '10', 10);
    
    let qty = parseInt(simQty.value || '1', 10);
    if (isNaN(qty) || qty < 1) qty = 1;

    const isRombongan = (simType && simType.value === 'rombongan') || qty >= minRombongan;
    const subtotal = qty * harga;
    let diskon = 0;

    if (isRombongan && qty >= minRombongan && diskonPersen > 0) {
      diskon = Math.round((subtotal * diskonPersen) / 100);
      if (simDiskonBadge) {
        simDiskonBadge.innerHTML = `<i class="fa-solid fa-sparkles"></i> Diskon ${diskonPersen}% Aktif`;
        simDiskonBadge.className = 'badge badge-accent';
      }
      if (simDiskonRow) simDiskonRow.style.display = 'flex';
    } else {
      if (simDiskonBadge) {
        simDiskonBadge.innerText = 'Tarif Reguler';
        simDiskonBadge.className = 'badge badge-light';
      }
      if (simDiskonRow) simDiskonRow.style.display = 'none';
    }

    const total = subtotal - diskon;

    if (simHargaSatuan) simHargaSatuan.innerText = 'Rp ' + harga.toLocaleString('id-ID');
    if (simJumlahDisplay) simJumlahDisplay.innerText = qty + ' Wisatawan';
    if (simSubtotal) simSubtotal.innerText = 'Rp ' + subtotal.toLocaleString('id-ID');
    if (simNominalDiskon) simNominalDiskon.innerText = '- Rp ' + diskon.toLocaleString('id-ID') + (diskonPersen > 0 ? ` (${diskonPersen}%)` : '');
    if (simTotalBayar) simTotalBayar.innerText = 'Rp ' + total.toLocaleString('id-ID');
    if (simBookingBtn) simBookingBtn.href = `<?= BASE_URL ?>booking.php?id=${destId}&qty=${qty}&tipe=${isRombongan ? 'rombongan' : 'sendiri'}`;
  }

  function stepQty(delta) {
    if (!simQty) return;
    let val = parseInt(simQty.value || '1', 10) + delta;
    if (val < 1) val = 1;
    if (val > 500) val = 500;
    simQty.value = val;
    highlightQtyChip(val);
    updateSimulator();
  }

  function setSimQty(val) {
    if (!simQty) return;
    simQty.value = val;
    highlightQtyChip(val);
    updateSimulator();
  }

  function highlightQtyChip(val) {
    const chips = document.querySelectorAll('.quick-qty-chip');
    chips.forEach(chip => {
      const match = chip.innerText.includes(val + ' Orang');
      if (match) chip.classList.add('active');
      else chip.classList.remove('active');
    });
  }

  // Scroll to Top Listener
  window.addEventListener('scroll', () => {
    const btnTop = document.getElementById('btnScrollTop');
    if (btnTop) {
      if (window.scrollY > 400) {
        btnTop.classList.add('visible');
      } else {
        btnTop.classList.remove('visible');
      }
    }
  });

  document.addEventListener('DOMContentLoaded', () => {
    // Start Clock Tick
    updateLiveClock();
    setInterval(updateLiveClock, 1000);

    // Initialize Simulator
    if (simDest && simQty) {
      simDest.addEventListener('change', updateSimulator);
      simQty.addEventListener('input', updateSimulator);
      if (simType) simType.addEventListener('change', updateSimulator);
      updateSimulator();
    }
  });
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
