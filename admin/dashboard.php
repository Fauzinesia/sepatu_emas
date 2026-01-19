<?php
session_start();
require_once __DIR__ . '/../config/koneksi.php';
if (!isset($_SESSION['auth']['logged_in']) || $_SESSION['auth']['logged_in'] !== true) {
  header('Location: ../login.php');
  exit;
}
if (strtolower($_SESSION['auth']['role'] ?? '') !== 'admin') {
  header('Location: ../peserta/dashboard.php');
  exit;
}
$page_title = 'Dashboard Admin';

// --- FILTER HANDLING ---
$filterYear      = isset($_GET['tahun'])      ? (int)$_GET['tahun']      : date('Y');
$filterKategori  = isset($_GET['kategori'])   ? trim($_GET['kategori'])  : ''; // APBD, APBN
$filterRole      = isset($_GET['role'])       ? trim($_GET['role'])      : ''; // admin, peserta
$filterGender    = isset($_GET['gender'])     ? trim($_GET['gender'])    : ''; // L, P

// Validasi tahun
$currentYear = (int)date('Y');
if ($filterYear < 2025) $filterYear = 2025;
if ($filterYear > $currentYear + 5) $filterYear = $currentYear + 5;

// Helper WHERE clauses
// 1. Untuk tb_pendaftaran (p) - Filter per Tahun
$wherePendaftaran = " WHERE YEAR(p.tanggal_daftar) = $filterYear ";
if ($filterKategori !== '') {
  $wherePendaftaran .= " AND pe.kategori = " . $pdo->quote($filterKategori);
}
if ($filterRole !== '') {
  $wherePendaftaran .= " AND u.role = " . $pdo->quote($filterRole);
}
if ($filterGender !== '') {
  $wherePendaftaran .= " AND u.jenis_kelamin = " . $pdo->quote($filterGender);
}

// 2. Untuk tb_user (u) - Filter per Tahun Created At
$whereUser = " WHERE YEAR(u.created_at) = $filterYear ";
if ($filterRole !== '') {
  $whereUser .= " AND u.role = " . $pdo->quote($filterRole);
}
if ($filterGender !== '') {
  $whereUser .= " AND u.jenis_kelamin = " . $pdo->quote($filterGender);
}

// --- DATA FETCHING ---
$total_users = 0;
$total_admins = 0;
$total_peserta = 0;
$pelatihan_aktif = 0;
$pelatihan_nonaktif = 0;
$pendaftaran_menunggu = 0;
$pendaftaran_diterima = 0;
$pendaftaran_ditolak = 0;

// Statistik Kartu (Global / Filtered?)
// Agar konsisten dengan dashboard, kartu biasanya global status saat ini.
// Namun user minta filter, jadi sebaiknya angka di kartu juga merefleksikan filter jika memungkinkan.
// Tapi "Pelatihan Aktif" adalah state sekarang, tidak terpengaruh "Tahun Pendaftaran".
// Jadi kita biarkan kartu sebagai "Snapshot Saat Ini" (kecuali user minta spesifik).
// Request: "Analisis... untuk mengevaluasi kemungkinan penambahan fitur visualisasi data... Tambahkan opsi filter..."
// Biasanya filter dashboard mempengaruhi SEMUA visualisasi.
// Tapi untuk "Total User", jika difilter tahun 2020-2020, apakah hanya user yg daftar 2020? Ya.
// Mari kita buat dinamis.

try {
  // Total Users (Filtered)
  $total_users = (int)$pdo->query("SELECT COUNT(*) FROM tb_user u $whereUser")->fetchColumn();
  $total_admins = (int)$pdo->query("SELECT COUNT(*) FROM tb_user u $whereUser AND u.role='admin'")->fetchColumn();
  $total_peserta = (int)$pdo->query("SELECT COUNT(*) FROM tb_user u $whereUser AND u.role='peserta'")->fetchColumn();
} catch (Throwable $e) {}

try {
  // Pelatihan (Snapshot saat ini, filter Kategori relevan)
  $sqlPel = "SELECT COUNT(*) FROM tb_pelatihan WHERE status = 'aktif'";
  if ($filterKategori) $sqlPel .= " AND kategori = " . $pdo->quote($filterKategori);
  $pelatihan_aktif = (int)$pdo->query($sqlPel)->fetchColumn();

  $sqlPel = "SELECT COUNT(*) FROM tb_pelatihan WHERE status = 'nonaktif'";
  if ($filterKategori) $sqlPel .= " AND kategori = " . $pdo->quote($filterKategori);
  $pelatihan_nonaktif = (int)$pdo->query($sqlPel)->fetchColumn();
} catch (Throwable $e) {}

try {
  // Pendaftaran Status (Filtered)
  // Query dasar join
  $basePend = "SELECT COUNT(*) FROM tb_pendaftaran p JOIN tb_user u ON p.id_user = u.id_user JOIN tb_pelatihan pe ON p.id_pelatihan = pe.id_pelatihan $wherePendaftaran";
  
  $pendaftaran_menunggu = (int)$pdo->query("$basePend AND p.status='menunggu'")->fetchColumn();
  $pendaftaran_diterima = (int)$pdo->query("$basePend AND p.status='diterima'")->fetchColumn();
  $pendaftaran_ditolak = (int)$pdo->query("$basePend AND p.status='ditolak'")->fetchColumn();
} catch (Throwable $e) {}


// --- CHARTS DATA ---

// 1. Trend Peserta Pelatihan per Bulan (Line/Bar Chart)
$trendLabels = ['Jan','Feb','Mar','Apr','Mei','Jun','Jul','Agust','Sep','Okt','Nov','Des'];
$trendData = array_fill(0, 12, 0);

try {
  $sql = "SELECT MONTH(p.tanggal_daftar) as mth, COUNT(*) as total 
          FROM tb_pendaftaran p 
          JOIN tb_user u ON p.id_user = u.id_user 
          JOIN tb_pelatihan pe ON p.id_pelatihan = pe.id_pelatihan 
          $wherePendaftaran 
          GROUP BY mth 
          ORDER BY mth ASC";
  $rows = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
  
  foreach ($rows as $r) {
    $m = (int)$r['mth'];
    if ($m >= 1 && $m <= 12) {
        $trendData[$m - 1] = (int)$r['total'];
    }
  }
} catch (Throwable $e) {}

// 2. Distribusi Role (Pie/Donut)
$roleLabels = ['Admin', 'Peserta'];
$roleData = [0, 0];
try {
  $sql = "SELECT LOWER(role) as role, COUNT(*) as total FROM tb_user u $whereUser GROUP BY role";
  $rows = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
  $map = [];
  foreach ($rows as $r) { $map[$r['role']] = (int)$r['total']; }
  $roleData = [$map['admin'] ?? 0, $map['peserta'] ?? 0];
} catch (Throwable $e) {}

// 3. Gender Comparison (Grouped Bar / Donut) - Peserta Pelatihan (Pendaftaran)
// Menggunakan data pendaftaran agar kena filter 'Jenis Pelatihan'
$genderLabels = ['Laki-laki', 'Perempuan'];
$genderData = [0, 0];
try {
  $sql = "SELECT u.jenis_kelamin, COUNT(*) as total 
          FROM tb_pendaftaran p 
          JOIN tb_user u ON p.id_user = u.id_user 
          JOIN tb_pelatihan pe ON p.id_pelatihan = pe.id_pelatihan 
          $wherePendaftaran 
          GROUP BY u.jenis_kelamin";
  $rows = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
  $map = [];
  foreach ($rows as $r) { $map[$r['jenis_kelamin']] = (int)$r['total']; }
  $genderData = [$map['L'] ?? 0, $map['P'] ?? 0];
} catch (Throwable $e) {}

// 4. Top Pelatihan (Bar) - Filtered
$topPelatihanLabels = [];
$topPelatihanCounts = [];
try {
  $sql = "SELECT pe.nama_pelatihan, COUNT(*) as total 
          FROM tb_pendaftaran p 
          JOIN tb_user u ON p.id_user = u.id_user 
          JOIN tb_pelatihan pe ON p.id_pelatihan = pe.id_pelatihan 
          $wherePendaftaran AND p.status IN ('menunggu','diterima') 
          GROUP BY p.id_pelatihan, pe.nama_pelatihan 
          ORDER BY total DESC LIMIT 5";
  $rows = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
  foreach ($rows as $r) {
    $topPelatihanLabels[] = $r['nama_pelatihan'];
    $topPelatihanCounts[] = (int)$r['total'];
  }
} catch (Throwable $e) {}

// 5. Latest Registration - Filtered
$latestRegs = [];
try {
  $sql = "SELECT p.id_pendaftaran, u.nama_lengkap, u.username, pe.nama_pelatihan, p.status, p.tanggal_daftar, p.created_at 
          FROM tb_pendaftaran p 
          JOIN tb_user u ON p.id_user = u.id_user 
          JOIN tb_pelatihan pe ON p.id_pelatihan = pe.id_pelatihan 
          $wherePendaftaran 
          ORDER BY p.created_at DESC LIMIT 5";
  $latestRegs = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) { $latestRegs = []; }

include_once __DIR__ . '/../includes/header.php';
include_once __DIR__ . '/../includes/sidebar.php';
include_once __DIR__ . '/../includes/navbar.php';
?>
      <div class="body-wrapper">
        <div class="container-fluid">
          
          <!-- Filter Form -->
          <div class="card mb-4">
            <div class="card-body p-3">
              <h5 class="card-title fw-semibold mb-3">Filter Dashboard</h5>
              <form method="GET" action="">
                <div class="row g-3">
                  <div class="col-md-2">
                    <label class="form-label small">Tahun</label>
                    <select name="tahun" class="form-select form-select-sm">
                      <?php 
                      $curr = (int)date('Y');
                      // Tampilkan range tahun dari 2025 sampai Current+5
                      for($y = 2025; $y <= $curr + 5; $y++) {
                        $sel = $y == $filterYear ? 'selected' : '';
                        echo "<option value='$y' $sel>$y</option>";
                      }
                      ?>
                    </select>
                  </div>
                  <div class="col-md-2">
                    <label class="form-label small">Jenis Pelatihan</label>
                    <select name="kategori" class="form-select form-select-sm">
                      <option value="">Semua</option>
                      <option value="APBD" <?php echo $filterKategori === 'APBD' ? 'selected' : ''; ?>>APBD</option>
                      <option value="APBN" <?php echo $filterKategori === 'APBN' ? 'selected' : ''; ?>>APBN</option>
                    </select>
                  </div>
                  <div class="col-md-2">
                    <label class="form-label small">Peran</label>
                    <select name="role" class="form-select form-select-sm">
                      <option value="">Semua</option>
                      <option value="admin" <?php echo $filterRole === 'admin' ? 'selected' : ''; ?>>Admin</option>
                      <option value="peserta" <?php echo $filterRole === 'peserta' ? 'selected' : ''; ?>>Peserta</option>
                    </select>
                  </div>
                  <div class="col-md-2">
                    <label class="form-label small">Gender</label>
                    <select name="gender" class="form-select form-select-sm">
                      <option value="">Semua</option>
                      <option value="L" <?php echo $filterGender === 'L' ? 'selected' : ''; ?>>Laki-laki</option>
                      <option value="P" <?php echo $filterGender === 'P' ? 'selected' : ''; ?>>Perempuan</option>
                    </select>
                  </div>
                  <div class="col-md-2 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary btn-sm w-100"><i class="ti ti-filter me-1"></i> Terapkan</button>
                  </div>
                </div>
              </form>
            </div>
          </div>

          <div class="row mb-3">
            <div class="col-12">
              <div class="d-flex flex-wrap gap-2">
                <a class="btn btn-primary" href="<?php echo $urlBase; ?>admin/pengguna/pengguna.php"><i class="ti ti-users me-1"></i> Kelola Pengguna</a>
                <a class="btn btn-outline-primary" href="<?php echo $urlBase; ?>admin/pendaftaran/pendaftaran.php"><i class="ti ti-clipboard-list me-1"></i> Kelola Pendaftaran</a>
                <a class="btn btn-outline-secondary" href="<?php echo $urlBase; ?>admin/pelatihan/pelatihan.php"><i class="ti ti-notebook me-1"></i> Kelola Pelatihan</a>
              </div>
            </div>
          </div>
          <div class="row">
            <div class="col-12">
              <div class="d-flex flex-wrap gap-3 mb-3">
                <a href="<?php echo $urlBase; ?>admin/pengguna/pengguna.php" class="card grow text-decoration-none" style="min-width:220px; transition: transform 0.2s;">
                  <div class="card-body">
                    <h6 class="text-muted mb-1">Total Pengguna</h6>
                    <div class="h3 fw-semibold text-dark"><?php echo (int)$total_users; ?></div>
                  </div>
                </a>
                <a href="<?php echo $urlBase; ?>admin/pengguna/pengguna.php?role=admin" class="card grow text-decoration-none" style="min-width:220px; transition: transform 0.2s;">
                  <div class="card-body">
                    <h6 class="text-muted mb-1">Admin</h6>
                    <div class="h3 fw-semibold text-dark"><?php echo (int)$total_admins; ?></div>
                  </div>
                </a>
                <a href="<?php echo $urlBase; ?>admin/pengguna/pengguna.php?role=peserta" class="card grow text-decoration-none" style="min-width:220px; transition: transform 0.2s;">
                  <div class="card-body">
                    <h6 class="text-muted mb-1">Peserta</h6>
                    <div class="h3 fw-semibold text-dark"><?php echo (int)$total_peserta; ?></div>
                  </div>
                </a>
                <a href="<?php echo $urlBase; ?>admin/pelatihan/pelatihan.php" class="card grow text-decoration-none" style="min-width:220px; transition: transform 0.2s;">
                  <div class="card-body">
                    <h6 class="text-muted mb-1">Pelatihan Aktif</h6>
                    <div class="h3 fw-semibold text-dark"><?php echo (int)$pelatihan_aktif; ?></div>
                  </div>
                </a>
                <a href="<?php echo $urlBase; ?>admin/pelatihan/pelatihan.php" class="card grow text-decoration-none" style="min-width:220px; transition: transform 0.2s;">
                  <div class="card-body">
                    <h6 class="text-muted mb-1">Pelatihan Nonaktif</h6>
                    <div class="h3 fw-semibold text-dark"><?php echo (int)$pelatihan_nonaktif; ?></div>
                  </div>
                </a>
              </div>
            </div>
          </div>
          
          <!-- Row 1: Trend & Gender -->
          <div class="row">
            <div class="col-lg-8">
              <div class="card">
                <div class="card-body">
                  <h5 class="card-title fw-semibold">Tren Peserta Pelatihan (<?php echo $filterYear; ?>)</h5>
                  <div id="chartTrend"></div>
                </div>
              </div>
            </div>
            <div class="col-lg-4">
              <div class="card">
                <div class="card-body">
                  <h5 class="card-title fw-semibold">Distribusi Gender</h5>
                  <div id="chartGender"></div>
                </div>
              </div>
            </div>
          </div>

          <!-- Row 2: Status & Role -->
          <div class="row">
            <div class="col-lg-6">
              <div class="card">
                <div class="card-body">
                  <h5 class="card-title fw-semibold">Pendaftaran per Status</h5>
                  <div id="chartStatus"></div>
                </div>
              </div>
            </div>
            <div class="col-lg-6">
              <div class="card">
                <div class="card-body">
                  <h5 class="card-title fw-semibold">Distribusi Peran (Role)</h5>
                  <div id="chartRole"></div>
                </div>
              </div>
            </div>
          </div>

          <!-- Row 3: Top Pelatihan -->
          <div class="row">
            <div class="col-12">
              <div class="card">
                <div class="card-body">
                  <h5 class="card-title fw-semibold">Top Pelatihan Terpopuler</h5>
                  <div id="chartTopPelatihan"></div>
                </div>
              </div>
            </div>
          </div>

          <!-- Row 4: Table -->
          <div class="row">
            <div class="col-12">
              <div class="card">
                <div class="card-body">
                  <h5 class="card-title fw-semibold">Pendaftaran Terbaru (Filtered)</h5>
                  <div class="table-responsive">
                    <table class="table table-striped align-middle">
                      <thead>
                        <tr>
                          <th>Peserta</th>
                          <th>Pelatihan</th>
                          <th>Tanggal Daftar</th>
                          <th>Status</th>
                        </tr>
                      </thead>
                      <tbody>
                        <?php if (!empty($latestRegs)) { foreach ($latestRegs as $r) { ?>
                        <tr>
                          <td><?php echo htmlspecialchars($r['nama_lengkap'] ?? $r['username'] ?? ''); ?></td>
                          <td><?php echo htmlspecialchars($r['nama_pelatihan'] ?? ''); ?></td>
                          <td><?php echo htmlspecialchars($r['tanggal_daftar'] ?? ''); ?></td>
                          <td><?php echo htmlspecialchars($r['status'] ?? ''); ?></td>
                        </tr>
                        <?php } } else { ?>
                        <tr><td colspan="4">Tidak ada data.</td></tr>
                        <?php } ?>
                      </tbody>
                    </table>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
<?php include_once __DIR__ . '/../includes/footer.php'; ?>
<script>
  // Data from PHP
  const statusCategories = ['Menunggu','Diterima','Ditolak'];
  const statusData = [<?php echo (int)$pendaftaran_menunggu; ?>, <?php echo (int)$pendaftaran_diterima; ?>, <?php echo (int)$pendaftaran_ditolak; ?>];
  
  const trendLabels = <?php echo json_encode($trendLabels); ?>;
  const trendData   = <?php echo json_encode($trendData); ?>;
  
  const genderLabels = <?php echo json_encode($genderLabels); ?>;
  const genderData   = <?php echo json_encode($genderData); ?>;
  
  const roleLabels = <?php echo json_encode($roleLabels); ?>;
  const roleData   = <?php echo json_encode($roleData); ?>;
  
  const topPelatihanLabels = <?php echo json_encode($topPelatihanLabels); ?>;
  const topPelatihanCounts = <?php echo json_encode($topPelatihanCounts); ?>;
  
  const urlBase = <?php echo json_encode($urlBase ?? '/'); ?>;

  // 1. Chart Trend (Line/Bar)
  const chartTrend = new ApexCharts(document.querySelector('#chartTrend'), {
    chart: { type: 'area', height: 300, toolbar: { show: false }, animations: { enabled: true } },
    series: [{ name: 'Peserta', data: trendData }],
    xaxis: { categories: trendLabels },
    colors: ['#0E4DA4'],
    dataLabels: { enabled: false },
    stroke: { curve: 'smooth', width: 2 },
    tooltip: { theme: 'light' }
  });
  chartTrend.render();

  // 2. Chart Gender (Donut/Pie)
  const chartGender = new ApexCharts(document.querySelector('#chartGender'), {
    chart: { type: 'donut', height: 300, events: {
      dataPointSelection: function(e, ctx, cfg){
        var idx = cfg.dataPointIndex;
        var label = genderLabels[idx];
        var url = urlBase + 'admin/pengguna/pengguna.php?role=peserta&jenis_kelamin=' + encodeURIComponent(label);
        window.location.href = url;
      }
    } },
    series: genderData,
    labels: genderLabels,
    colors: ['#145DA0', '#E91E63'],
    legend: { position: 'bottom' },
    plotOptions: { pie: { donut: { size: '65%' } } },
    tooltip: { theme: 'light' }
  });
  chartGender.render();

  // 3. Chart Status (Bar)
  const chartStatus = new ApexCharts(document.querySelector('#chartStatus'), {
    chart: { type: 'bar', height: 300, toolbar: { show: false }, events: {
      dataPointSelection: function(e, ctx, cfg){
        var idx = cfg.dataPointIndex;
        var status = statusCategories[idx].toLowerCase();
        var url = urlBase + 'admin/pendaftaran/pendaftaran.php?status=' + encodeURIComponent(status);
        window.location.href = url;
      }
    } },
    series: [{ name: 'Jumlah', data: statusData }],
    xaxis: { categories: statusCategories },
    colors: ['#FFC107', '#198754', '#DC3545'], // Yellow, Green, Red
    plotOptions: { bar: { distributed: true, borderRadius: 4 } },
    dataLabels: { enabled: true },
    legend: { show: false }
  });
  chartStatus.render();

  // 4. Chart Role (Pie)
  const chartRole = new ApexCharts(document.querySelector('#chartRole'), {
    chart: { type: 'pie', height: 300, events: {
      dataPointSelection: function(e, ctx, cfg){
        var idx = cfg.dataPointIndex;
        var label = roleLabels[idx].toLowerCase();
        var url = urlBase + 'admin/pengguna/pengguna.php?role=' + encodeURIComponent(label);
        window.location.href = url;
      }
    } },
    series: roleData,
    labels: roleLabels,
    colors: ['#0E4DA4','#19A86E'],
    legend: { position: 'bottom' },
    tooltip: { theme: 'light' }
  });
  chartRole.render();

  // 5. Chart Top Pelatihan (Bar)
  const chartTopPelatihan = new ApexCharts(document.querySelector('#chartTopPelatihan'), {
    chart: { type: 'bar', height: 300, toolbar: { show: false }, events: {
      dataPointSelection: function(e, ctx, cfg){
        window.location.href = urlBase + 'admin/pelatihan/pelatihan.php';
      }
    } },
    series: [{ name: 'Pendaftar', data: topPelatihanCounts }],
    xaxis: { categories: topPelatihanLabels },
    colors: ['#145DA0'],
    dataLabels: { enabled: true },
    plotOptions: { bar: { horizontal: true, borderRadius: 4 } } // Horizontal bar for better label reading
  });
  chartTopPelatihan.render();
</script>
