<?php
// Halaman daftar semua pelatihan aktif (publik)
session_start();
require_once __DIR__ . '/config/koneksi.php';

$pelatihans = [];
try {
  $sql = "SELECT p.id_pelatihan, p.nama_pelatihan, p.nama_instruktur, p.kategori, p.lokasi, p.deskripsi, p.tanggal_mulai, p.tanggal_selesai, p.foto, p.status, b.nama_bidang
          FROM tb_pelatihan p
          LEFT JOIN tb_bidang b ON b.id_bidang = p.id_bidang
          WHERE p.status = 'aktif'
          ORDER BY p.id_pelatihan DESC";
  $stmt = $pdo->prepare($sql);
  $stmt->execute();
  $pelatihans = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
  $pelatihans = [];
}

function excerpt_text(string $text, int $max = 120): string {
  $text = trim(strip_tags($text));
  if (mb_strlen($text) <= $max) return $text;
  $cut = mb_substr($text, 0, $max);
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
  <title>Semua Pelatihan — SEPATU EMAS</title>
  <link rel="stylesheet" href="assets/libs/bootstrap/dist/css/bootstrap.min.css" />
  <link rel="stylesheet" href="assets/css/styles.min.css" />
  <style>
    .card-feature { border: none; box-shadow: 0 12px 32px rgba(0,0,0,0.06); transition: transform .2s ease, box-shadow .2s ease; }
    .card-feature:hover { transform: translateY(-4px); box-shadow: 0 16px 40px rgba(0,0,0,0.08); }
    /* Opsi 2: konsisten rasio dengan letterbox, gambar utuh tanpa crop */
    .card-img-top { width: 100%; height: 100%; object-fit: contain; background-color: #f8f9fa; }
  </style>
</head>
<body>
  <nav class="navbar navbar-expand-lg navbar-custom sticky-top shadow-sm">
    <div class="container">
      <a class="navbar-brand d-flex align-items-center gap-2" href="index.php">
        <span class="logo-wrap d-flex align-items-center gap-2">
          <img src="assets/images/logos/logo-kemnaker.png" alt="Logo Kemnaker" loading="lazy" width="36" height="36">
          <img src="assets/images/logos/logo-hsu.png" alt="Logo HSU" loading="lazy" width="36" height="36">
        </span>
        <span class="ms-2 fw-bold">SEPATU EMAS</span>
      </a>
      <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navMain" aria-controls="navMain" aria-expanded="false" aria-label="Toggle navigation">
        <span class="navbar-toggler-icon"></span>
      </button>
      <div class="collapse navbar-collapse" id="navMain">
        <ul class="navbar-nav ms-auto mb-2 mb-lg-0">
          <li class="nav-item"><a class="nav-link" href="index.php#beranda">Beranda</a></li>
          <li class="nav-item"><a class="nav-link" href="index.php#pelatihan">Pelatihan</a></li>
          <li class="nav-item"><a class="nav-link" href="login.php">Login</a></li>
          <li class="nav-item"><a class="nav-link" href="register.php">Daftar</a></li>
        </ul>
      </div>
    </div>
  </nav>

  <main class="py-5">
    <div class="container">
      <div class="d-flex align-items-center justify-content-between mb-4">
        <h2 class="mb-0">Semua Program Pelatihan</h2>
        <div class="input-group" style="max-width: 320px;">
          <span class="input-group-text"><i class="ti ti-search"></i></span>
          <input type="text" id="searchInput" class="form-control" placeholder="Cari nama atau deskripsi...">
        </div>
      </div>

      <?php if (empty($pelatihans)) { ?>
        <div class="alert alert-info">Belum ada pelatihan aktif untuk ditampilkan.</div>
      <?php } ?>

      <div class="row g-4" id="listPelatihan">
        <?php foreach ($pelatihans as $i => $p):
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
          <div class="col-md-4 pelatihan-item">
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
                <?php if (!empty($instruktur) || !empty($kategori) || !empty($lokasi)): ?>
                  <p class="card-text text-muted mb-2">
                    <?php if (!empty($instruktur)) { ?>Instruktur: <?php echo $instruktur; ?><?php } ?>
                    <?php if (!empty($kategori)) { echo (!empty($instruktur) ? ' · ' : ''); ?>Kategori: <?php echo $kategori; ?><?php } ?>
                    <?php if (!empty($lokasi)) { echo ((!empty($instruktur) || !empty($kategori)) ? ' · ' : ''); ?>Lokasi: <?php echo $lokasi; ?><?php } ?>
                  </p>
                <?php endif; ?>
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
      </div>
    </div>
  </main>

  <footer class="py-4">
    <div class="container d-flex flex-column flex-lg-row align-items-center justify-content-between">
      <p class="mb-2 mb-lg-0">© <?php echo date('Y'); ?> SEPATU EMAS — UPTD BLK Amuntai</p>
      <div class="d-flex gap-3">
        <a href="index.php" class="text-decoration-none">Beranda</a>
        <a href="index.php#pelatihan" class="text-decoration-none">Program Pelatihan</a>
        <a href="login.php" class="text-decoration-none">Login Admin</a>
      </div>
    </div>
  </footer>

  <script src="assets/libs/bootstrap/dist/js/bootstrap.bundle.min.js"></script>
  <script>
    // Filter pencarian sederhana di client
    document.addEventListener('DOMContentLoaded', function(){
      const input = document.getElementById('searchInput');
      const items = document.querySelectorAll('#listPelatihan .pelatihan-item');
      input?.addEventListener('input', function(){
        const q = (this.value || '').toLowerCase();
        items.forEach(function(item){
          const text = item.innerText.toLowerCase();
          item.style.display = text.indexOf(q) !== -1 ? '' : 'none';
        });
      });
    });
  </script>
</body>
</html>
