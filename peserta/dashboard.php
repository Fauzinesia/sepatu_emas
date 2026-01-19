<?php
session_start();
require_once __DIR__ . '/../config/koneksi.php';

// Proteksi login
if (!isset($_SESSION['auth']['logged_in']) || $_SESSION['auth']['logged_in'] !== true) {
  header('Location: ../login.php');
  exit;
}

// Jika admin, arahkan ke dashboard admin
if (strtolower($_SESSION['auth']['role'] ?? '') === 'admin') {
  header('Location: ../admin/dashboard.php');
  exit;
}

$page_title = 'Dashboard Peserta';

// Ambil ringkas pendaftaran saya (5 terakhir)
$myRegs = [];
try {
  $st = $pdo->prepare("SELECT p.id_pendaftaran, p.id_pelatihan, p.tanggal_daftar, p.status, p.keterangan,
                               pe.nama_pelatihan
                        FROM tb_pendaftaran p
                        JOIN tb_pelatihan pe ON p.id_pelatihan = pe.id_pelatihan
                        WHERE p.id_user = :uid
                        ORDER BY p.created_at DESC
                        LIMIT 5");
  $st->execute([':uid' => $_SESSION['auth']['id_user'] ?? 0]);
  $myRegs = $st->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
  $myRegs = [];
}
?>
<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?php echo isset($page_title) ? $page_title.' — SEPATU EMAS' : 'SEPATU EMAS'; ?></title>
  <link rel="shortcut icon" type="image/png" href="../assets/images/logos/favicon.png" />
  <link rel="stylesheet" href="../assets/css/styles.min.css" />
</head>
<body>
  <div class="page-wrapper" id="main-wrapper" data-layout="vertical" data-navbarbg="skin6" data-sidebartype="full"
    data-sidebar-position="fixed" data-header-position="fixed">
    <?php include_once __DIR__ . '/../includes/sidebar.php'; ?>
    <div class="body-wrapper">
      <?php include_once __DIR__ . '/../includes/navbar.php'; ?>

      <div class="container-fluid">
        <div class="row">
          <div class="col-12">
            <div class="card">
              <div class="card-body">
                <h5 class="card-title fw-semibold">Selamat Datang</h5>
                <p class="mb-3">Halo, <?php echo htmlspecialchars($_SESSION['auth']['nama_lengkap'] ?? 'Peserta'); ?>. Kelola pendaftaran pelatihan Anda dan unggah berkas yang diperlukan.</p>

                <div class="row g-3">
                  <div class="col-md-4">
                    <a class="btn btn-primary w-100" href="<?php echo $urlBase ?? '/'; ?>peserta/pelatihan.php">
                      <i class="ti ti-notebook me-1"></i> Lihat Semua Pelatihan
                    </a>
                  </div>
                  <div class="col-md-4">
                    <a class="btn btn-outline-primary w-100" href="<?php echo $urlBase ?? '/'; ?>peserta/pendaftaran.php">
                      <i class="ti ti-clipboard-list me-1"></i> Pendaftaran Saya
                    </a>
                  </div>
                  <div class="col-md-4">
                    <a class="btn btn-outline-secondary w-100" href="<?php echo $urlBase ?? '/'; ?>peserta/profil.php">
                      <i class="ti ti-user me-1"></i> Profil
                    </a>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <div class="row">
          <div class="col-12">
            <div class="card">
              <div class="card-body">
                <h5 class="card-title fw-semibold mb-3">Ringkasan Pendaftaran Terakhir</h5>
                <div class="table-responsive">
                  <table class="table table-hover align-middle">
                    <thead>
                      <tr>
                        <th>Pelatihan</th>
                        <th>Tanggal Daftar</th>
                        <th>Status</th>
                        <th>Keterangan</th>
                        <th>Aksi</th>
                      </tr>
                    </thead>
                    <tbody>
                      <?php if (empty($myRegs)) { ?>
                        <tr><td colspan="5" class="text-center text-muted">Belum ada pendaftaran. <a href="<?php echo $urlBase ?? '/'; ?>peserta/pelatihan.php">Daftar pelatihan sekarang</a>.</td></tr>
                      <?php } else { foreach ($myRegs as $r) { ?>
                        <tr>
                          <td><?php echo htmlspecialchars($r['nama_pelatihan']); ?></td>
                          <td><?php echo htmlspecialchars(date('d/m/Y', strtotime($r['tanggal_daftar']))); ?></td>
                          <td>
                            <?php
                              $st = strtolower($r['status']);
                              $badge = 'bg-secondary';
                              if ($st === 'menunggu') $badge = 'bg-warning text-dark';
                              elseif ($st === 'diterima') $badge = 'bg-success';
                              elseif ($st === 'ditolak') $badge = 'bg-danger';
                            ?>
                            <span class="badge <?php echo $badge; ?> text-capitalize"><?php echo htmlspecialchars($r['status']); ?></span>
                          </td>
                          <td><?php echo htmlspecialchars($r['keterangan'] ?: '-'); ?></td>
                          <td>
                            <a href="<?php echo $urlBase ?? '/'; ?>peserta/pelatihan-detail.php?id=<?php echo urlencode($r['id_pelatihan']); ?>" class="btn btn-sm btn-outline-primary">Detail</a>
                          </td>
                        </tr>
                      <?php } } ?>
                    </tbody>
                  </table>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

    </div>
  </div>

  <script src="../assets/libs/jquery/dist/jquery.min.js"></script>
  <script src="../assets/libs/bootstrap/dist/js/bootstrap.bundle.min.js"></script>
  <script src="../assets/js/app.min.js"></script>
  <script src="../assets/js/sidebarmenu.js"></script>
</body>
</html>
