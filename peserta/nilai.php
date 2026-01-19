<?php
session_start();
require_once __DIR__ . '/../config/koneksi.php';

// Proteksi login & role peserta
if (!isset($_SESSION['auth']['logged_in']) || $_SESSION['auth']['logged_in'] !== true) {
  header('Location: ../login.php');
  exit;
}
if (strtolower($_SESSION['auth']['role'] ?? '') !== 'peserta') {
  header('Location: ../admin/dashboard.php');
  exit;
}

$page_title = 'Nilai Saya';

// Ambil nilai saya
$nilai_list = [];
try {
  $st = $pdo->prepare("SELECT n.id_nilai, n.keterangan, n.tanggal_input,
                               p.id_pelatihan, p.nama_pelatihan
                        FROM tb_nilai n
                        JOIN tb_pelatihan p ON p.id_pelatihan = n.id_pelatihan
                        WHERE n.id_user = :uid
                        ORDER BY n.tanggal_input DESC");
  $st->execute([':uid' => $_SESSION['auth']['id_user'] ?? 0]);
  $nilai_list = $st->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
  $nilai_list = [];
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
                <div class="d-flex align-items-center justify-content-between mb-2">
                  <h5 class="card-title fw-semibold mb-0">Nilai Saya</h5>
                </div>

                <div class="table-responsive">
                  <table class="table table-striped align-middle">
                    <thead>
                      <tr>
                        <th>Pelatihan</th>
                        <th>Tanggal Input</th>
                        <th>Hasil</th>
                      </tr>
                    </thead>
                    <tbody>
                      <?php if (empty($nilai_list)) { ?>
                        <tr><td colspan="4" class="text-center text-muted">Belum ada nilai yang tercatat.</td></tr>
                      <?php } else { foreach ($nilai_list as $n) { ?>
                        <tr>
                          <td><?php echo htmlspecialchars($n['nama_pelatihan']); ?></td>
                          <td><?php echo htmlspecialchars($n['tanggal_input'] ? date('d/m/Y', strtotime($n['tanggal_input'])) : '-'); ?></td>
                          <td><span class="badge bg-primary"><?php echo htmlspecialchars($n['keterangan'] ?: '-'); ?></span></td>
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
