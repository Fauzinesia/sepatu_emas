<?php
if (!isset($assetBase) || !isset($urlBase)) {
  $script = $_SERVER['SCRIPT_NAME'] ?? '';
  if (preg_match('#^/([^/]+)/#', $script, $m)) {
    $project = $m[1];
    $assetBase = '/' . $project . '/assets/';
    $urlBase = '/' . $project . '/';
  } else {
    $assetBase = '/assets/';
    $urlBase = '/';
  }
}
// Deteksi role untuk menyesuaikan menu
$role = strtolower($_SESSION['auth']['role'] ?? '');
?>
    <!-- Sidebar Start -->
    <aside class="left-sidebar">
      <!-- Sidebar scroll-->
      <div>
        <div class="brand-logo d-flex align-items-center justify-content-between">
          <a href="<?php echo $urlBase; echo ($role === 'peserta') ? 'peserta/dashboard.php' : 'admin/dashboard.php'; ?>" class="text-nowrap logo-img">
            <img src="<?php echo $assetBase; ?>images/logos/sepatu-emas.png" width="180" alt="SEPATU EMAS" />
          </a>
          <div class="close-btn d-xl-none d-block sidebartoggler cursor-pointer" id="sidebarCollapse">
            <i class="ti ti-x fs-8"></i>
          </div>
        </div>
        <!-- Sidebar navigation-->
        <nav class="sidebar-nav scroll-sidebar" data-simplebar="">
          <ul id="sidebarnav">
            <?php if ($role === 'peserta') { ?>
              <li class="nav-small-cap">
                <i class="ti ti-dots nav-small-cap-icon fs-4"></i>
                <span class="hide-menu">Beranda</span>
              </li>
              <li class="sidebar-item">
                <a class="sidebar-link" href="<?php echo $urlBase; ?>peserta/dashboard.php" aria-expanded="false">
                  <span><i class="ti ti-layout-dashboard"></i></span>
                  <span class="hide-menu">Dashboard</span>
                </a>
              </li>
              <li class="nav-small-cap">
                <i class="ti ti-dots nav-small-cap-icon fs-4"></i>
                <span class="hide-menu">Pelatihan</span>
              </li>
              <li class="sidebar-item">
                <a class="sidebar-link" href="<?php echo $urlBase; ?>peserta/pelatihan.php" aria-expanded="false">
                  <span><i class="ti ti-notebook"></i></span>
                  <span class="hide-menu">Semua Pelatihan</span>
                </a>
              </li>
              <li class="sidebar-item">
                <a class="sidebar-link" href="<?php echo $urlBase; ?>peserta/pendaftaran.php" aria-expanded="false">
                  <span><i class="ti ti-clipboard-list"></i></span>
                  <span class="hide-menu">Pendaftaran Saya</span>
                </a>
              </li>
              <li class="nav-small-cap">
                <i class="ti ti-dots nav-small-cap-icon fs-4"></i>
                <span class="hide-menu">Hasil & Sertifikat</span>
              </li>
              <li class="sidebar-item">
                <a class="sidebar-link" href="<?php echo $urlBase; ?>peserta/nilai.php" aria-expanded="false">
                  <span><i class="ti ti-chart-bar"></i></span>
                  <span class="hide-menu">Nilai</span>
                </a>
              </li>
              <li class="sidebar-item">
                <a class="sidebar-link" href="<?php echo $urlBase; ?>peserta/sertifikat.php" aria-expanded="false">
                  <span><i class="ti ti-certificate"></i></span>
                  <span class="hide-menu">Sertifikat</span>
                </a>
              </li>
            <?php } else { ?>
              <li class="nav-small-cap">
                <i class="ti ti-dots nav-small-cap-icon fs-4"></i>
                <span class="hide-menu">Beranda</span>
              </li>
              <li class="sidebar-item">
                <a class="sidebar-link" href="<?php echo $urlBase; ?>admin/dashboard.php" aria-expanded="false">
                  <span><i class="ti ti-layout-dashboard"></i></span>
                  <span class="hide-menu">Dashboard</span>
                </a>
              </li>
              <li class="nav-small-cap">
                <i class="ti ti-dots nav-small-cap-icon fs-4"></i>
                <span class="hide-menu">Manajemen</span>
              </li>
              <li class="sidebar-item">
                <a class="sidebar-link" href="<?php echo $urlBase; ?>admin/pengguna/pengguna.php" aria-expanded="false">
                  <span><i class="ti ti-users"></i></span>
                  <span class="hide-menu">Pengguna</span>
                </a>
              </li>
              <li class="sidebar-item">
                <a class="sidebar-link" href="<?php echo $urlBase; ?>admin/bidang/bidang.php" aria-expanded="false">
                  <span><i class="ti ti-category"></i></span>
                  <span class="hide-menu">Kejuruan</span>
                </a>
              </li>
              <li class="sidebar-item">
                <a class="sidebar-link" href="<?php echo $urlBase; ?>admin/pelatihan/pelatihan.php" aria-expanded="false">
                  <span><i class="ti ti-notebook"></i></span>
                  <span class="hide-menu">Program Pelatihan</span>
                </a>
              </li>
              <li class="sidebar-item">
                <a class="sidebar-link" href="<?php echo $urlBase; ?>admin/pendaftaran/pendaftaran.php" aria-expanded="false">
                  <span><i class="ti ti-clipboard-list"></i></span>
                  <span class="hide-menu">Pendaftaran</span>
                </a>
              </li>
              <li class="sidebar-item">
                <a class="sidebar-link" href="<?php echo $urlBase; ?>admin/nilai/nilai.php" aria-expanded="false">
                  <span><i class="ti ti-chart-bar"></i></span>
                  <span class="hide-menu">Nilai</span>
                </a>
              </li>
              <li class="sidebar-item">
                <a class="sidebar-link" href="<?php echo $urlBase; ?>admin/sertifikat/sertifikat.php" aria-expanded="false">
                  <span><i class="ti ti-certificate"></i></span>
                  <span class="hide-menu">Sertifikat</span>
                </a>
              </li>
            <?php } ?>
          </ul>
        </nav>
        <!-- End Sidebar navigation -->
      </div>
      <!-- End Sidebar scroll-->
    </aside>
    <!--  Sidebar End -->
