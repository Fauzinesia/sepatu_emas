<?php
// Halaman detail pelatihan (publik) + pendaftaran peserta
session_start();
require_once __DIR__ . '/config/koneksi.php';

// Ambil ID pelatihan
$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$pelatihan = null;
$error = '';
$success = '';

// Siapkan CSRF token
if (empty($_SESSION['csrf_token'])) {
  $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Ambil data pelatihan
try {
  $stmt = $pdo->prepare("SELECT id_pelatihan, nama_pelatihan, deskripsi, tanggal_mulai, tanggal_selesai, foto, status, created_at FROM tb_pelatihan WHERE id_pelatihan = ?");
  $stmt->execute([$id]);
  $pelatihan = $stmt->fetch(PDO::FETCH_ASSOC);
  if (!$pelatihan) {
    $error = 'Pelatihan tidak ditemukan.';
  }
} catch (Throwable $e) {
  $error = 'Terjadi kesalahan saat memuat data pelatihan.';
}

// Proses pendaftaran (hanya untuk peserta login)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'daftar') {
  $csrf = $_POST['csrf_token'] ?? '';
  if (!hash_equals($_SESSION['csrf_token'], $csrf)) {
    $error = 'Token tidak valid. Muat ulang halaman.';
  } else if (!$pelatihan) {
    $error = 'Pelatihan tidak valid.';
  } else {
    $role = strtolower($_SESSION['auth']['role'] ?? ($_SESSION['role'] ?? ''));
    $idUser = (int) ($_SESSION['auth']['id_user'] ?? ($_SESSION['user_id'] ?? 0));
    if ($idUser <= 0 || $role !== 'peserta') {
      $error = 'Anda harus login sebagai peserta untuk mendaftar.';
    } else if (($pelatihan['status'] ?? '') !== 'aktif') {
      $error = 'Pelatihan tidak berstatus aktif.';
    } else {
      try {
        // Cek duplikasi
        // $cek = $pdo->prepare("SELECT COUNT(*) FROM tb_pendaftaran WHERE id_user = ? AND id_pelatihan = ?");
        // $cek->execute([$idUser, $pelatihan['id_pelatihan']]);


        $cek = $pdo->prepare("SELECT COUNT(*) from tb_pendaftaran join tb_pelatihan on tb_pendaftaran.id_pelatihan = tb_pelatihan.id_pelatihan where id_user = ? and tb_pelatihan.status = ?");
        $cek->execute([$idUser, 'aktif']);
        $exists = (int) $cek->fetchColumn();

        // echo $exists;
        // exit;

        if ($exists > 0) {
          $error = 'Anda hanya bisa mendaftar sebanyak satu pelatihan dalam satu tahap';
        } else {
          // Kuota telah dihilangkan; pendaftaran tidak dibatasi kuota.

          $stmt = $pdo->prepare("INSERT INTO tb_pendaftaran (id_user, id_pelatihan, status, tanggal_daftar) VALUES (?,?,?,?)");
          $stmt->execute([$idUser, $pelatihan['id_pelatihan'], 'menunggu', date('Y-m-d H:i:s')]);
          // Tangani unggah berkas (opsional), kemudian update record yang baru dibuat
          $lastId = (int) $pdo->lastInsertId();
          // Generate nomor induk pendaftaran berbasis tahun + id auto
          $nomorInduk = 'NIP-' . date('Y') . '/' . str_pad((string) $lastId, 6, '0', STR_PAD_LEFT);
          try {
            $upNo = $pdo->prepare('UPDATE tb_pendaftaran SET no_induk_pendaftaran = ? WHERE id_pendaftaran = ?');
            $upNo->execute([$nomorInduk, $lastId]);
          } catch (Throwable $e) { /* abaikan jika kolom belum ada */
          }
          $uploadDir = __DIR__ . '/uploads/pendaftaran';
          if (!is_dir($uploadDir)) {
            @mkdir($uploadDir, 0775, true);
          }
          $allowed = ['image/jpeg', 'image/png', 'application/pdf', 'image/jpg'];
          $maxSize = 2 * 1024 * 1024; // 2MB
          $fields = ['file_ktp', 'file_ijazah', 'file_kartu_pencari_kerja'];
          $saved = [];
          foreach ($fields as $f) {
            if (!isset($_FILES[$f]) || ($_FILES[$f]['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
              continue;
            }
            $err = $_FILES[$f]['error'];
            if ($err !== UPLOAD_ERR_OK) {
              continue;
            }
            $type = mime_content_type($_FILES[$f]['tmp_name']);
            $size = (int) $_FILES[$f]['size'];
            if ($size > $maxSize) {
              continue;
            }
            if (!in_array($type, $allowed, true)) {
              continue;
            }
            $ext = pathinfo($_FILES[$f]['name'], PATHINFO_EXTENSION);
            $fname = 'reg-' . $lastId . '-' . $f . '-' . bin2hex(random_bytes(6)) . '.' . strtolower($ext ?: 'dat');
            $dest = $uploadDir . '/' . $fname;
            if (!move_uploaded_file($_FILES[$f]['tmp_name'], $dest)) {
              continue;
            }
            $saved[$f] = 'uploads/pendaftaran/' . $fname;
          }
          if (!empty($saved) && $lastId > 0) {
            $set = [];
            $params = [':id' => $lastId];
            foreach ($saved as $col => $path) {
              $set[] = "$col = :$col";
              $params[":" . $col] = $path;
            }
            $sqlUp = 'UPDATE tb_pendaftaran SET ' . implode(', ', $set) . ' WHERE id_pendaftaran = :id';
            $up = $pdo->prepare($sqlUp);
            $up->execute($params);
          }
          $success = 'Pendaftaran berhasil dikirim. Status: menunggu verifikasi.';
        }
      } catch (Throwable $e) {
        $error = 'Gagal menyimpan pendaftaran.';
      }
    }
  }
}

function format_date_id(?string $date): string
{
  if (!$date)
    return '';
  try {
    $dt = new DateTime($date);
    $bulan = [1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
    $m = (int) $dt->format('n');
    return $dt->format('j') . ' ' . ($bulan[$m] ?? $dt->format('F')) . ' ' . $dt->format('Y');
  } catch (Throwable $e) {
    return $date;
  }
}

// Siapkan path foto
$fotoPath = '';
if ($pelatihan && !empty($pelatihan['foto'])) {
  $candidate = __DIR__ . '/assets/images/products/' . $pelatihan['foto'];
  if (is_file($candidate)) {
    $fotoPath = 'assets/images/products/' . htmlspecialchars($pelatihan['foto']);
  }
}
?>
<!doctype html>
<html lang="id">

<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Detail Pelatihan — SEPATU EMAS</title>
  <link rel="stylesheet" href="assets/libs/bootstrap/dist/css/bootstrap.min.css" />
  <link rel="stylesheet" href="assets/css/styles.min.css" />
  <style>
    /* Gambar konsisten rasio 16:9, tampil utuh tanpa crop */
    .hero-img {
      width: 100%;
      height: 100%;
      object-fit: contain;
      border-radius: 12px;
      background-color: #f8f9fa;
    }

    .badge-status {
      text-transform: lowercase;
    }

    .card-shadow {
      box-shadow: 0 12px 32px rgba(0, 0, 0, 0.06);
    }
  </style>
</head>

<body>
  <nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm sticky-top">
    <div class="container">
      <a class="navbar-brand" href="index.php"><img src="assets/images/logos/logo-kemnaker.png" alt="Logo" height="32"
          class="me-2"> SEPATU EMAS</a>
      <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navMain"><span
          class="navbar-toggler-icon"></span></button>
      <div class="collapse navbar-collapse" id="navMain">
        <ul class="navbar-nav ms-auto">
          <li class="nav-item"><a class="nav-link" href="index.php#pelatihan">Pelatihan</a></li>
          <li class="nav-item"><a class="nav-link" href="login.php">Login</a></li>
          <li class="nav-item"><a class="nav-link" href="register.php">Daftar</a></li>
        </ul>
      </div>
    </div>
  </nav>

  <main class="py-4">
    <div class="container">
      <?php if ($error) { ?>
        <div class="alert alert-danger" role="alert"><?php echo htmlspecialchars($error); ?></div>
      <?php } ?>
      <?php if ($success) { ?>
        <div class="alert alert-success" role="alert"><?php echo htmlspecialchars($success); ?></div>
      <?php } ?>

      <?php if ($pelatihan) { ?>
        <div class="row g-4">
          <div class="col-lg-7">
            <div class="card card-shadow">
              <?php if ($fotoPath) { ?>
                <div class="ratio ratio-16x9">
                  <img src="<?php echo $fotoPath; ?>" alt="<?php echo htmlspecialchars($pelatihan['nama_pelatihan']); ?>"
                    class="hero-img">
                </div>
              <?php } ?>
              <div class="card-body">
                <h3 class="card-title mb-2"><?php echo htmlspecialchars($pelatihan['nama_pelatihan']); ?></h3>
                <div class="d-flex align-items-center gap-2 mb-3">
                  <?php if (($pelatihan['status'] ?? '') === 'aktif') { ?>
                    <span class="badge bg-success badge-status">aktif</span>
                  <?php } else { ?>
                    <span class="badge bg-secondary badge-status">nonaktif</span>
                  <?php } ?>
                  <span class="text-muted">Dibuat: <?php echo htmlspecialchars($pelatihan['created_at'] ?? ''); ?></span>
                </div>
                <?php if (!empty($pelatihan['tanggal_mulai']) || !empty($pelatihan['tanggal_selesai'])) { ?>
                  <p class="text-muted">Jadwal: <?php echo format_date_id($pelatihan['tanggal_mulai'] ?? null); ?> —
                    <?php echo format_date_id($pelatihan['tanggal_selesai'] ?? null); ?>
                  </p>
                <?php } ?>
                <?php if (!empty($pelatihan['deskripsi'])) { ?>
                  <p class="card-text"><?php echo nl2br(htmlspecialchars($pelatihan['deskripsi'])); ?></p>
                <?php } else { ?>
                  <p class="card-text text-muted">Deskripsi belum tersedia.</p>
                <?php } ?>
              </div>
            </div>
          </div>
          <div class="col-lg-5">
            <div class="card card-shadow">
              <div class="card-body">
                <h5 class="card-title">Pendaftaran Pelatihan</h5>
                <?php if (($pelatihan['status'] ?? '') !== 'aktif') { ?>
                  <div class="alert alert-warning">Pelatihan tidak aktif. Pendaftaran tidak tersedia.</div>
                <?php } else { ?>
                  <?php
                  $role = strtolower($_SESSION['auth']['role'] ?? ($_SESSION['role'] ?? ''));
                  $idUser = (int) ($_SESSION['auth']['id_user'] ?? ($_SESSION['user_id'] ?? 0));
                  ?>
                  <?php if ($idUser > 0 && $role === 'peserta') { ?>
                    <form method="post" class="mt-2" enctype="multipart/form-data">
                      <input type="hidden" name="action" value="daftar">
                      <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                      <p class="text-muted">Anda login sebagai peserta. Unggah berkas pendukung (opsional) lalu klik tombol
                        daftar.</p>
                      <div class="mb-2">
                        <label class="form-label">KTP (jpg/png/pdf, maks 2MB)</label>
                        <input type="file" name="file_ktp" accept=".pdf,image/*" class="form-control" />
                      </div>
                      <div class="mb-2">
                        <label class="form-label">Ijazah (jpg/png/pdf, maks 2MB)</label>
                        <input type="file" name="file_ijazah" accept=".pdf,image/*" class="form-control" />
                      </div>
                      <div class="mb-3">
                        <label class="form-label">Kartu Pencari Kerja (jpg/png/pdf, maks 2MB)</label>
                        <input type="file" name="file_kartu_pencari_kerja" accept=".pdf,image/*" class="form-control" />
                      </div>
                      <button type="submit" class="btn btn-primary">Daftar Pelatihan</button>
                    </form>
                  <?php } else { ?>
                    <p class="text-muted">Silakan login sebagai peserta untuk mendaftar pelatihan.</p>
                    <div class="d-flex gap-2">
                      <a href="login.php" class="btn btn-outline-primary">Login</a>
                      <a href="register.php" class="btn btn-primary">Daftar Akun</a>
                    </div>
                  <?php } ?>
                <?php } ?>
              </div>
            </div>
          </div>
        </div>
      <?php } ?>
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
</body>

</html>