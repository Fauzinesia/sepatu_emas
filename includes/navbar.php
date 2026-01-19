<?php
// Navbar include dari template header
if (!isset($assetBase)) {
  $script = $_SERVER['SCRIPT_NAME'] ?? '';
  if (preg_match('#^/([^/]+)/#', $script, $m)) {
    $assetBase = '/' . $m[1] . '/assets/';
  } else {
    $assetBase = '/assets/';
  }
}
// Hitung base URL proyek untuk tautan halaman
if (!isset($urlBase)) {
  $script = $_SERVER['SCRIPT_NAME'] ?? '';
  if (preg_match('#^/([^/]+)/#', $script, $m)) {
    $urlBase = '/' . $m[1] . '/';
  } else {
    $urlBase = '/';
  }
}

// Ambil foto profil pengguna (jika ada) dari database
$profileImgSrc = $assetBase . 'images/profile/user-1.jpg';
try {
  if (!empty($_SESSION['auth']['id_user'])) {
    require_once __DIR__ . '/../config/koneksi.php';
    $st = $pdo->prepare('SELECT foto FROM tb_user WHERE id_user = ? LIMIT 1');
    $st->execute([(int)$_SESSION['auth']['id_user']]);
    $row = $st->fetch(PDO::FETCH_ASSOC);
    $fotoRel = $row['foto'] ?? '';
    if ($fotoRel) {
      $profileImgSrc = $urlBase . ltrim($fotoRel, '/');
    }
  }
} catch (Throwable $e) { /* abaikan kegagalan ambil foto */ }
?>
      <!--  Header Start -->
      <header class="app-header">
        <nav class="navbar navbar-expand-lg navbar-light px-3 px-md-4 px-lg-4 px-xl-5 px-xxl-5">
          <ul class="navbar-nav align-items-center">
            <li class="nav-item d-block d-xl-none">
              <a class="nav-link sidebartoggler nav-icon-hover" id="headerCollapse" href="javascript:void(0)">
                <i class="ti ti-menu-2"></i>
              </a>
            </li>
          </ul>
      <div class="navbar-collapse justify-content-end px-2 px-md-3 px-lg-4" id="navbarNav">
        <ul class="navbar-nav flex-row ms-auto align-items-center justify-content-end gap-2 gap-md-3 pe-3 pe-lg-4 pe-xl-5 pe-xxl-5">
          <li class="nav-item">
            <a class="nav-link nav-icon-hover" href="javascript:void(0)">
              <i class="ti ti-bell-ringing"></i>
              <div class="notification bg-primary rounded-circle"></div>
            </a>
          </li>
          <li class="nav-item dropdown">
            <a class="nav-link nav-icon-hover" href="javascript:void(0)" id="drop2" data-bs-toggle="dropdown"
              aria-expanded="false">
              <img src="<?php echo htmlspecialchars($profileImgSrc); ?>" alt="Profile" width="35" height="35" class="rounded-circle me-1">
            </a>
                <div class="dropdown-menu dropdown-menu-end dropdown-menu-animate-up" aria-labelledby="drop2">
                  <div class="message-body">
                    <a href="<?php echo $urlBase; ?>admin/profil.php" class="d-flex align-items-center gap-2 dropdown-item">
                      <i class="ti ti-user fs-6"></i>
                      <p class="mb-0 fs-3">Profil</p>
                    </a>
                    <a href="<?php echo $urlBase; ?>logout.php" class="btn btn-outline-primary mx-3 mt-2 d-block">Logout</a>
                  </div>
                </div>
              </li>
            </ul>
          </div>
        </nav>
      </header>
      <!--  Header End -->