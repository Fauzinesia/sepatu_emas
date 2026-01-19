<?php
// Landing page SEPATU EMAS
// Ambil data pelatihan aktif untuk ditampilkan
require_once __DIR__ . '/config/koneksi.php';

$pelatihans = [];
try {
  $sql = "SELECT p.id_pelatihan, p.nama_pelatihan, p.nama_instruktur, p.kategori, p.lokasi, p.deskripsi, p.tanggal_mulai, p.tanggal_selesai, p.foto, b.nama_bidang
          FROM tb_pelatihan p
          LEFT JOIN tb_bidang b ON b.id_bidang = p.id_bidang
          WHERE p.status = 'aktif'
          ORDER BY p.id_pelatihan DESC
          LIMIT 6";
  $stmt = $pdo->prepare($sql);
  $stmt->execute();
  $pelatihans = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
  // Biarkan halaman tetap tampil tanpa data jika terjadi error
  $pelatihans = [];
}

function excerpt_text(string $text, int $max = 120): string {
  $text = trim(strip_tags($text));
  if (mb_strlen($text) <= $max) return $text;
  $cut = mb_substr($text, 0, $max);
  // potong di spasi terakhir agar rapi
  $lastSpace = mb_strrpos($cut, ' ');
  if ($lastSpace !== false) $cut = mb_substr($cut, 0, $lastSpace);
  return $cut . '…';
}

function format_date_id(?string $date): string {
  if (!$date) return '';
  try {
    $dt = new DateTime($date);
    $bulan = [1=>'Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];
    $m = (int)$dt->format('n');
    return $dt->format('j') . ' ' . ($bulan[$m] ?? $dt->format('F')) . ' ' . $dt->format('Y');
  } catch (Throwable $e) { return $date; }
}
?>
<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>SEPATU EMAS — UPTD BLK Amuntai</title>
  <meta name="description" content="Sistem Pelayanan Pelatihan Terpadu, Efisien, Modern, Akuntabel, dan Sistematis (SEPATU EMAS) UPTD BLK Amuntai.">
  <meta name="keywords" content="SEPATU EMAS, BLK Amuntai, pelatihan kerja, pendaftaran pelatihan, sertifikat, peserta, admin">
  <meta name="author" content="UPTD BLK Amuntai">
  <meta property="og:title" content="SEPATU EMAS — UPTD BLK Amuntai">
  <meta property="og:description" content="Pelayanan pelatihan kerja terintegrasi untuk masyarakat Amuntai dan sekitarnya.">
  <meta property="og:type" content="website">
  <meta property="og:url" content="http://localhost/">
  <meta property="og:image" content="assets/images/logos/logo-kemnaker.png">
  <link rel="shortcut icon" type="image/png" href="assets/images/logos/favicon.png" />
  <meta name="theme-color" content="#145DA0" />
  <!-- Bootstrap CSS for layout and components -->
  <link rel="stylesheet" href="assets/libs/bootstrap/dist/css/bootstrap.min.css" />
  <link rel="stylesheet" href="assets/css/styles.min.css" />
  <style>
    :root {
      /* Palet warna harmonis (estimasi dari logo Kemnaker & HSU) */
      --primary-blue: #0E4DA4; /* Biru Kemnaker */
      --primary-green: #19A86E; /* Hijau Kemnaker */
      --secondary-blue: #145DA0; /* Biru HSU */
      --accent: #00BFA6; /* Aksen kebiruan */
      --text-dark: #1F2937;
      --text-light: #ffffff;
      --bg-light: #f7f9fc;
    }
    body {
      background: linear-gradient(135deg, var(--bg-light) 0%, #ffffff 100%);
      color: var(--text-dark);
      }
    .brand-gradient {
      background: linear-gradient(135deg, var(--primary-green), var(--primary-blue));
      -webkit-background-clip: text;
      -webkit-text-fill-color: transparent;
              background-clip: text;
              color: transparent;
    }
    .btn-brand {
      background: linear-gradient(135deg, var(--accent), var(--secondary-blue));
      border: none;
      color: var(--text-light);
      box-shadow: 0 10px 20px rgba(20, 93, 160, 0.2);
      transition: transform .2s ease, box-shadow .2s ease;
    }
    .btn-brand:hover { transform: translateY(-2px); box-shadow: 0 14px 28px rgba(20, 93, 160, 0.25); }
    .btn-outline-primary { border-color: var(--secondary-blue); color: var(--secondary-blue); }
    .btn-outline-primary:hover { background-color: var(--secondary-blue); color: #fff; }
    /* Konsistensi tombol Login & Daftar */
    .btn-auth {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      width: 110px; /* sama untuk kedua tombol */
      height: 38px; /* sama untuk kedua tombol */
      padding: 0 16px; /* seragam */
      margin: 0; /* dasar seragam, gunakan utilitas gap untuk jarak */
      border-radius: 10px; /* identik */
      font-size: 0.95rem; /* konsisten */
      font-weight: 600; /* konsisten */
      letter-spacing: .2px;
      border: none;
      color: #fff; /* konsisten */
      box-shadow: 0 8px 18px rgba(20, 93, 160, 0.18);
      transition: transform .18s ease, box-shadow .18s ease, opacity .18s ease;
    }
    .btn-auth--primary {
      background: linear-gradient(135deg, var(--secondary-blue), var(--primary-blue)); /* serasi */
    }
    .btn-auth--accent {
      background: linear-gradient(135deg, var(--accent), var(--secondary-blue)); /* serasi */
    }
    .btn-auth:hover { transform: translateY(-2px); box-shadow: 0 12px 24px rgba(20, 93, 160, 0.25); opacity: .98; }
    .btn-auth:active { transform: translateY(0); box-shadow: 0 6px 12px rgba(20, 93, 160, 0.22); opacity: .95; }
    /* Jarak proporsional antar tombol dalam navbar */
    .navbar-nav .btn-auth + .btn-auth { margin-left: 12px; }
    @media (max-width: 991.98px) {
      /* Responsif: tombol full width saat menu collapse */
      .navbar-nav .nav-item { margin-bottom: 6px; }
      .btn-auth { width: 100%; height: 42px; }
    }
    .navbar-custom { backdrop-filter: saturate(180%) blur(8px); background-color: rgba(255,255,255,0.85); }
    .logo-wrap img { height: 48px; object-fit: contain; }
    .hero {
      position: relative;
      background-image: url('assets/images/landing.jpg');
      background-size: cover;
      background-position: center;
      background-repeat: no-repeat;
    }
    .hero::before {
      content: "";
      position: absolute;
      inset: 0;
      /* Overlay gradasi gelap untuk meningkatkan keterbacaan teks */
      background: linear-gradient(
        to right,
        rgba(0,0,0,0.55) 0%,
        rgba(0,0,0,0.38) 40%,
        rgba(0,0,0,0.22) 100%
      );
    }
    .hero > * { position: relative; z-index: 1; }
    .hero-title { font-weight: 800; letter-spacing: .2px; }
    .section-title { color: var(--secondary-blue); font-weight: 700; }
    .card-feature { border: none; box-shadow: 0 12px 32px rgba(0,0,0,0.06); transition: transform .2s ease, box-shadow .2s ease; }
    .card-feature:hover { transform: translateY(-4px); box-shadow: 0 16px 40px rgba(0,0,0,0.08); }
    /* Gambar konsisten rasio 16:9, tampil utuh tanpa crop */
    .card-img-top { width: 100%; height: 100%; object-fit: contain; background-color: #f8f9fa; }
    .fade-up { opacity: 0; transform: translateY(12px); animation: fadeUp .6s ease forwards; }
    @keyframes fadeUp { to { opacity: 1; transform: translateY(0); } }
  </style>
  <!-- Prefetch assets for performance -->
  <link rel="preload" href="assets/images/logos/logo-kemnaker.png" as="image">
  <link rel="preload" href="assets/images/logos/logo-hsu.png" as="image">
  <link rel="preload" href="assets/images/landing.jpg" as="image">
</head>
<body>
  <!-- Navbar -->
  <nav class="navbar navbar-expand-lg navbar-custom sticky-top shadow-sm">
    <div class="container">
      <a class="navbar-brand d-flex align-items-center gap-2" href="#">
        <span class="logo-wrap d-flex align-items-center gap-2">
          <img src="assets/images/logos/logo-kemnaker.png" alt="Logo Kemnaker" loading="lazy" width="48" height="48">
          <img src="assets/images/logos/logo-hsu.png" alt="Logo HSU" loading="lazy" width="48" height="48">
        </span>
        <span class="ms-2 fw-bold brand-gradient">SEPATU EMAS</span>
      </a>
      <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navMain" aria-controls="navMain" aria-expanded="false" aria-label="Toggle navigation">
        <span class="navbar-toggler-icon"></span>
      </button>
      <div class="collapse navbar-collapse" id="navMain">
        <ul class="navbar-nav ms-auto mb-2 mb-lg-0">
          <li class="nav-item"><a class="nav-link" href="#beranda">Beranda</a></li>
          <li class="nav-item"><a class="nav-link" href="#pelatihan">Pelatihan</a></li>
          <li class="nav-item"><a class="nav-link" href="#fitur">Fitur</a></li>
        </ul>
      </div>
    </div>
  </nav>

  <!-- Hero -->
  <section id="beranda" class="hero py-5">
    <div class="container">
      <div class="row align-items-center g-4">
        <div class="col-12 col-lg-7 fade-up" style="animation-delay:.1s">
          <h1 class="display-5 hero-title brand-gradient">Sistem Pelayanan Pelatihan Terpadu</h1>
          <p class="lead mt-3">Efisien, Modern, Akuntabel, dan Sistematis untuk mendukung peningkatan kompetensi kerja di UPTD BLK Amuntai.</p>
          <div class="mt-4 d-flex gap-2">
            <a href="register.php" class="btn btn-brand btn-lg">Mulai Daftar Pelatihan</a>
            <a href="#pelatihan" class="btn btn-outline-primary btn-lg">Lihat Program</a>
          </div>
          <p class="text-muted mt-3">Terintegrasi dengan pendaftaran, penilaian, dan sertifikat dalam satu platform.</p>
        </div>
        </div>
      </div>
    </div>
  </section>

  <!-- Features -->
  <section id="fitur" class="py-5">
    <div class="container">
      <h2 class="section-title text-center mb-4">Fitur Utama</h2>
      <div class="row g-4">
        <div class="col-md-4 fade-up" style="animation-delay:.1s">
          <div class="card card-feature h-100">
            <div class="card-body">
              <h5 class="card-title">Terintegrasi</h5>
              <p class="card-text">Pendaftaran, verifikasi, penjadwalan, nilai, dan sertifikat dalam satu sistem.</p>
            </div>
          </div>
        </div>
        <div class="col-md-4 fade-up" style="animation-delay:.2s">
          <div class="card card-feature h-100">
            <div class="card-body">
              <h5 class="card-title">Efisien & Modern</h5>
              <p class="card-text">Antarmuka responsif, cepat, dan ramah pengguna berbasis Bootstrap modern.</p>
            </div>
          </div>
        </div>
        <div class="col-md-4 fade-up" style="animation-delay:.3s">
          <div class="card card-feature h-100">
            <div class="card-body">
              <h5 class="card-title">Akuntabel & Sistematis</h5>
              <p class="card-text">Status dan riwayat tercatat jelas dengan kontrol akses peran admin/peserta.</p>
            </div>
          </div>
        </div>
      </div>
    </div>
  </section>

  <!-- Programs teaser -->
  <section id="pelatihan" class="py-5 bg-light">
    <div class="container">
      <h2 class="section-title text-center mb-4">Program Pelatihan</h2>
      <p class="text-center text-muted mb-5">Pelatihan aktif dari database. Daftar cepat, verifikasi mudah, sertifikat resmi.</p>
      <div class="row g-4">
        <?php if (!empty($pelatihans)): ?>
          <?php foreach ($pelatihans as $i => $p): ?>
            <?php
              $title = htmlspecialchars($p['nama_pelatihan'] ?? 'Pelatihan');
              $desc = htmlspecialchars(excerpt_text($p['deskripsi'] ?? ''));
              $mulai = format_date_id($p['tanggal_mulai'] ?? null);
              $selesai = format_date_id($p['tanggal_selesai'] ?? null);
              $bidang = htmlspecialchars($p['nama_bidang'] ?? '');
              $instruktur = htmlspecialchars($p['nama_instruktur'] ?? '');
              $kategori = htmlspecialchars(strtoupper($p['kategori'] ?? ''));
              $lokasi = htmlspecialchars(strtoupper($p['lokasi'] ?? ''));
              $foto = $p['foto'] ?? '';
              $fotoPath = '';
              if (!empty($foto)) {
                $candidate = __DIR__ . '/assets/images/products/' . $foto;
                if (is_file($candidate)) {
                  $fotoPath = 'assets/images/products/' . htmlspecialchars($foto);
                }
              }
            ?>
            <div class="col-md-4 fade-up" style="animation-delay: <?php echo number_format(0.12 + ($i*0.06), 2); ?>s">
              <div class="card h-100 card-feature">
                <?php if ($fotoPath): ?>
                  <div class="ratio ratio-16x9">
                    <img src="<?php echo $fotoPath; ?>" class="card-img-top" alt="<?php echo $title; ?>">
                  </div>
                <?php endif; ?>
                <div class="card-body">
                  <h5 class="card-title mb-1"><?php echo $title; ?></h5>
                  <?php if (!empty($bidang)) { ?>
                    <p class="card-text text-muted mb-2">Bidang: <?php echo $bidang; ?></p>
                  <?php } ?>
                  <?php if (!empty($instruktur) || !empty($kategori) || !empty($lokasi)) { ?>
                    <p class="card-text text-muted mb-2">
                      <?php if (!empty($instruktur)) { ?>Instruktur: <?php echo $instruktur; ?><?php } ?>
                      <?php if (!empty($kategori)) { echo (!empty($instruktur) ? ' · ' : ''); ?>Kategori: <?php echo $kategori; ?><?php } ?>
                      <?php if (!empty($lokasi)) { echo ((!empty($instruktur) || !empty($kategori)) ? ' · ' : ''); ?>Lokasi: <?php echo $lokasi; ?><?php } ?>
                    </p>
                  <?php } ?>
                  <?php if ($mulai || $selesai): ?>
                    <p class="card-text text-muted mb-2"><?php echo ($mulai ?: '-') . ' — ' . ($selesai ?: '-'); ?></p>
                  <?php endif; ?>
                  <?php if (!empty($desc)): ?>
                    <p class="card-text"><?php echo $desc; ?></p>
                  <?php endif; ?>
                  <a href="pelatihan.php?id=<?php echo (int)($p['id_pelatihan'] ?? 0); ?>" class="btn btn-outline-primary btn-sm">Lihat Detail</a>
                </div>
              </div>
            </div>
          <?php endforeach; ?>
        <?php else: ?>
          <div class="col-12">
            <div class="alert alert-info" role="alert">
              Belum ada pelatihan aktif untuk ditampilkan.
            </div>
          </div>
        <?php endif; ?>
      </div>
      <div class="text-center mt-4">
        <a href="pelatihan-list.php" class="btn btn-brand">Lihat Semua Pelatihan</a>
      </div>
    </div>
  </section>

  <!-- Footer -->
  <footer class="py-4">
    <div class="container d-flex flex-column flex-lg-row align-items-center justify-content-between">
      <p class="mb-2 mb-lg-0">© <?php echo date('Y'); ?> SEPATU EMAS — UPTD BLK Amuntai</p>
      <div class="d-flex gap-3">
        <a href="#" class="text-decoration-none">Kebijakan Privasi</a>
        <a href="#" class="text-decoration-none">Syarat & Ketentuan</a>
        <a href="login.php" class="text-decoration-none">Login Admin</a>
      </div>
    </div>
  </footer>

  <script src="assets/libs/bootstrap/dist/js/bootstrap.bundle.min.js" defer></script>
  <script>
    // Tambah delay animasi bertahap untuk elemen bernuansa "fade-up"
    document.addEventListener('DOMContentLoaded', function () {
      const items = document.querySelectorAll('.fade-up');
      items.forEach((el, i) => {
        el.style.animationDelay = `${0.08 * (i+1)}s`;
      });
    });
  </script>
</body>
</html>
