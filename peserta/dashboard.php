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
                               p.file_ktp, p.file_ijazah, p.file_kartu_pencari_kerja,
                               pe.nama_pelatihan, pe.tanggal_mulai, pe.tanggal_selesai,
                               n.keterangan AS status_kelulusan,
                               s.id_sertifikat, s.nomor_sertifikat, s.tanggal_terbit
                        FROM tb_pendaftaran p
                        JOIN tb_pelatihan pe ON p.id_pelatihan = pe.id_pelatihan
                        LEFT JOIN tb_nilai n ON p.id_user = n.id_user AND p.id_pelatihan = n.id_pelatihan
                        LEFT JOIN tb_sertifikat s ON p.id_user = s.id_user AND p.id_pelatihan = s.id_pelatihan
                        WHERE p.id_user = :uid
                        ORDER BY p.created_at DESC
                        LIMIT 5");
  $st->execute([':uid' => $_SESSION['auth']['id_user'] ?? 0]);
  $myRegs = $st->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
  $myRegs = [];
}

// Hitung statistik untuk summary cards
$count_menunggu = 0;
$count_diterima = 0;
$count_ongoing = 0;
$count_certified = 0;
$alerts = [];

foreach ($myRegs as $r) {
  if ($r['status'] === 'menunggu') $count_menunggu++;
  if ($r['status'] === 'diterima') {
    $count_diterima++;
    // Cek apakah sedang berlangsung
    if (!empty($r['tanggal_mulai']) && !empty($r['tanggal_selesai'])) {
      $now = time();
      $start = strtotime($r['tanggal_mulai']);
      $end = strtotime($r['tanggal_selesai']);
      if ($now >= $start && $now <= $end) {
        $count_ongoing++;
      }
    }
  }
  if (!empty($r['id_sertifikat'])) $count_certified++;
}

// Generate alerts
$incomplete = array_filter($myRegs, fn($r) => 
  (count(array_filter([$r['file_ktp'], $r['file_ijazah'], $r['file_kartu_pencari_kerja']])) < 3) 
  && $r['status'] === 'menunggu'
);
if (count($incomplete) > 0) {
  $alerts[] = [
    'type' => 'warning',
    'icon' => 'alert-triangle',
    'message' => 'Anda memiliki <strong>'.count($incomplete).' pendaftaran</strong> dengan berkas belum lengkap. <a href="pendaftaran.php" class="alert-link">Lengkapi sekarang</a>.'
  ];
}

$rejected = array_filter($myRegs, fn($r) => $r['status'] === 'ditolak');
if (count($rejected) > 0) {
  $alerts[] = [
    'type' => 'danger',
    'icon' => 'x-circle',
    'message' => '<strong>'.count($rejected).' pendaftaran</strong> Anda ditolak. Silakan periksa keterangan dan daftar ulang jika perlu.'
  ];
}

$newCerts = array_filter($myRegs, fn($r) => 
  !empty($r['id_sertifikat']) && 
  strtotime($r['tanggal_terbit']) > strtotime('-7 days')
);
if (count($newCerts) > 0) {
  $alerts[] = [
    'type' => 'success',
    'icon' => 'certificate',
    'message' => 'Selamat! Anda memiliki <strong>'.count($newCerts).' sertifikat baru</strong>. <a href="sertifikat.php" class="alert-link">Lihat sertifikat</a>.'
  ];
}

// Fungsi untuk menentukan progress stage
function getProgressStage($row) {
  if (!empty($row['id_sertifikat'])) 
    return ['stage' => 5, 'label' => 'Sertifikat Terbit', 'color' => 'success', 'icon' => 'certificate'];
  
  if (!empty($row['status_kelulusan'])) 
    return ['stage' => 4, 'label' => 'Sudah Dinilai', 'color' => 'info', 'icon' => 'clipboard-check'];
  
  if ($row['status'] === 'diterima') {
    if (!empty($row['tanggal_selesai']) && strtotime($row['tanggal_selesai']) < time()) {
      return ['stage' => 3, 'label' => 'Pelatihan Selesai', 'color' => 'primary', 'icon' => 'school'];
    }
    return ['stage' => 2, 'label' => 'Sedang Pelatihan', 'color' => 'primary', 'icon' => 'book'];
  }
  
  if ($row['status'] === 'menunggu') 
    return ['stage' => 1, 'label' => 'Menunggu Verifikasi', 'color' => 'warning', 'icon' => 'clock'];
  
  if ($row['status'] === 'ditolak') 
    return ['stage' => 0, 'label' => 'Ditolak', 'color' => 'danger', 'icon' => 'x'];
  
  return ['stage' => 0, 'label' => 'Unknown', 'color' => 'secondary', 'icon' => 'help'];
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
        <!-- Section Alerts -->
        <?php if (!empty($alerts)): ?>
          <div class="row">
            <div class="col-12">
              <?php foreach ($alerts as $a): ?>
                <div class="alert alert-<?php echo $a['type']; ?> alert-dismissible fade show shadow-sm border-0 mb-3" role="alert">
                  <div class="d-flex align-items-center">
                    <i class="ti ti-<?php echo $a['icon']; ?> fs-6 me-3"></i>
                    <div><?php echo $a['message']; ?></div>
                  </div>
                  <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
              <?php endforeach; ?>
            </div>
          </div>
        <?php endif; ?>

        <!-- Section Summary Cards -->
        <div class="row g-3 mb-4">
          <div class="col-6 col-lg-3">
            <div class="card shadow-sm border-0 bg-warning-subtle mb-0">
              <div class="card-body p-3 text-center">
                <i class="ti ti-clock fs-7 text-warning mb-2 d-block"></i>
                <h4 class="fw-bold mb-0"><?php echo $count_menunggu; ?></h4>
                <p class="text-muted mb-0 small">Verifikasi</p>
              </div>
            </div>
          </div>
          <div class="col-6 col-lg-3">
            <div class="card shadow-sm border-0 bg-success-subtle mb-0">
              <div class="card-body p-3 text-center">
                <i class="ti ti-check fs-7 text-success mb-2 d-block"></i>
                <h4 class="fw-bold mb-0"><?php echo $count_diterima; ?></h4>
                <p class="text-muted mb-0 small">Diterima</p>
              </div>
            </div>
          </div>
          <div class="col-6 col-lg-3">
            <div class="card shadow-sm border-0 bg-info-subtle mb-0">
              <div class="card-body p-3 text-center">
                <i class="ti ti-school fs-7 text-info mb-2 d-block"></i>
                <h4 class="fw-bold mb-0"><?php echo $count_ongoing; ?></h4>
                <p class="text-muted mb-0 small">Pelatihan</p>
              </div>
            </div>
          </div>
          <div class="col-6 col-lg-3">
            <div class="card shadow-sm border-0 bg-primary-subtle mb-0">
              <div class="card-body p-3 text-center">
                <i class="ti ti-certificate fs-7 text-primary mb-2 d-block"></i>
                <h4 class="fw-bold mb-0"><?php echo $count_certified; ?></h4>
                <p class="text-muted mb-0 small">Sertifikat</p>
              </div>
            </div>
          </div>
        </div>

        <div class="row">
          <div class="col-12">
            <div class="card shadow-sm border-0 mb-4">
              <div class="card-body p-4">
                <h5 class="card-title fw-semibold mb-3">Aksi Cepat</h5>
                <div class="row g-2">
                  <div class="col-md-4">
                    <a class="btn btn-primary w-100 d-flex align-items-center justify-content-center py-2" href="<?php echo $urlBase ?? '/'; ?>peserta/pelatihan.php">
                      <i class="ti ti-notebook fs-5 me-2"></i> Jelajahi Pelatihan
                    </a>
                  </div>
                  <div class="col-md-4">
                    <a class="btn btn-outline-primary w-100 d-flex align-items-center justify-content-center py-2" href="<?php echo $urlBase ?? '/'; ?>peserta/pendaftaran.php">
                      <i class="ti ti-clipboard-list fs-5 me-2"></i> Pendaftaran Saya
                    </a>
                  </div>
                  <div class="col-md-4">
                    <a class="btn btn-outline-secondary w-100 d-flex align-items-center justify-content-center py-2" href="<?php echo $urlBase ?? '/'; ?>peserta/profil.php">
                      <i class="ti ti-user fs-5 me-2"></i> Lengkapi Profil
                    </a>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <div class="row">
          <div class="col-12">
            <div class="card shadow-sm border-0">
              <div class="card-body p-4">
                <h5 class="card-title fw-semibold mb-4">Ringkasan Riwayat Pelatihan</h5>
                <div class="table-responsive">
                  <table class="table table-hover align-middle">
                    <thead class="text-dark fs-4">
                      <tr>
                        <th class="border-bottom-0"><h6 class="fw-semibold mb-0">Nama Pelatihan</h6></th>
                        <th class="border-bottom-0"><h6 class="fw-semibold mb-0">Tahap Terkini</h6></th>
                        <th class="border-bottom-0"><h6 class="fw-semibold mb-0">Berkas</h6></th>
                        <th class="border-bottom-0"><h6 class="fw-semibold mb-0">Kelulusan</h6></th>
                        <th class="border-bottom-0"><h6 class="fw-semibold mb-0">Sertifikat</h6></th>
                        <th class="border-bottom-0 text-center"><h6 class="fw-semibold mb-0">Aksi</h6></th>
                      </tr>
                    </thead>
                    <tbody>
                      <?php if (empty($myRegs)) { ?>
                        <tr><td colspan="6" class="text-center py-5 text-muted">Belum ada riwayat pendaftaran. <a href="<?php echo $urlBase ?? '/'; ?>peserta/pelatihan.php" class="text-primary fw-bold">Daftar sekarang</a></td></tr>
                      <?php } else { foreach ($myRegs as $r) {
                        $prog = getProgressStage($r);
                        
                        // Kelengkapan Berkas
                        $files = [$r['file_ktp'], $r['file_ijazah'], $r['file_kartu_pencari_kerja']];
                        $filled = count(array_filter($files));
                        $total = 3;
                        $berkasClass = ($filled === $total) ? 'text-success' : 'text-warning';

                        // Status Kelulusan
                        $grad = $r['status_kelulusan'];
                        $badgeGrad = 'bg-light text-dark';
                        $labelGrad = 'Menunggu';
                        if ($grad) {
                          $gradLower = strtolower($grad);
                          if ($gradLower === 'kompeten') {
                            $badgeGrad = 'bg-success text-white';
                            $labelGrad = 'Kompeten';
                          } elseif ($gradLower === 'tidak kompeten') {
                            $badgeGrad = 'bg-danger text-white';
                            $labelGrad = 'Tidak Kompeten';
                          }
                        }
                      ?>
                        <tr>
                          <td class="border-bottom-0">
                            <h6 class="fw-semibold mb-1"><?php echo htmlspecialchars($r['nama_pelatihan']); ?></h6>
                            <span class="text-muted small"><i class="ti ti-calendar-event me-1"></i>Daftar: <?php echo date('d M Y', strtotime($r['tanggal_daftar'])); ?></span>
                            <?php if ($r['keterangan']): ?>
                              <div class="mt-1">
                                <span class="text-danger small"><i class="ti ti-info-circle me-1"></i><?php echo htmlspecialchars($r['keterangan']); ?></span>
                              </div>
                            <?php endif; ?>
                          </td>
                          <td class="border-bottom-0">
                            <span class="badge bg-<?php echo $prog['color']; ?>-subtle text-<?php echo $prog['color']; ?> fw-bold px-3 py-2">
                              <i class="ti ti-<?php echo $prog['icon']; ?> me-1"></i>
                              <?php echo $prog['label']; ?>
                            </span>
                          </td>
                          <td class="border-bottom-0">
                            <div class="d-flex align-items-center">
                              <span class="fw-bold <?php echo $berkasClass; ?> me-1"><?php echo $filled . '/' . $total; ?></span>
                              <?php if ($filled === $total): ?>
                                <i class="ti ti-circle-check-filled text-success fs-5"></i>
                              <?php else: ?>
                                <i class="ti ti-alert-circle text-warning fs-5"></i>
                              <?php endif; ?>
                            </div>
                          </td>
                          <td class="border-bottom-0">
                            <span class="badge <?php echo $badgeGrad; ?>"><?php echo $labelGrad; ?></span>
                          </td>
                          <td class="border-bottom-0">
                             <?php if (!empty($r['id_sertifikat'])): ?>
                               <a href="<?php echo $urlBase ?? '/'; ?>peserta/cetak_sertifikat.php?id=<?php echo $r['id_sertifikat']; ?>" class="badge bg-success text-decoration-none px-3 py-2" target="_blank">
                                 <i class="ti ti-download me-1"></i> Unduh
                               </a>
                             <?php elseif (!empty($grad) && strtolower($grad) === 'kompeten'): ?>
                               <span class="text-muted small text-nowrap"><i class="ti ti-hourglass-low me-1"></i>Proses Terbit</span>
                             <?php else: ?>
                               <span class="text-muted opacity-50 px-3">-</span>
                             <?php endif; ?>
                          </td>
                          <td class="border-bottom-0 text-center">
                            <div class="dropdown">
                              <button class="btn btn-link text-muted p-0" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                <i class="ti ti-dots-vertical fs-6"></i>
                              </button>
                              <ul class="dropdown-menu dropdown-menu-end shadow-sm">
                                <li><a class="dropdown-item" href="<?php echo $urlBase ?? '/'; ?>peserta/pelatihan-detail.php?id=<?php echo $r['id_pelatihan']; ?>"><i class="ti ti-eye me-2"></i>Detail Pelatihan</a></li>
                                <?php if ($filled < $total && strtolower($r['status']) === 'menunggu'): ?>
                                  <li><a class="dropdown-item text-warning" href="<?php echo $urlBase ?? '/'; ?>peserta/pendaftaran.php"><i class="ti ti-upload me-2"></i>Lengkapi Berkas</a></li>
                                <?php endif; ?>
                              </ul>
                            </div>
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
