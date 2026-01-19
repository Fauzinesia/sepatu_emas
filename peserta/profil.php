<?php
session_start();
require_once __DIR__ . '/../config/koneksi.php';

// Guard akses: harus login; halaman profil dapat diakses admin maupun peserta
if (!isset($_SESSION['auth']['logged_in']) || $_SESSION['auth']['logged_in'] !== true) {
  $script = $_SERVER['SCRIPT_NAME'] ?? '';
  if (preg_match('#^/([^/]+)/#', $script, $m)) { $urlBase = '/' . $m[1] . '/'; } else { $urlBase = '/'; }
  header('Location: ' . $urlBase . 'login.php');
  exit;
}
$role = strtolower($_SESSION['auth']['role'] ?? ($_SESSION['role'] ?? ''));

// Ambil data user dari DB (opsional) untuk detail profil
$userId = (int)($_SESSION['auth']['id_user'] ?? ($_SESSION['user_id'] ?? 0));
$user = null;
try {
  if ($userId > 0) {
    $st = $pdo->prepare('SELECT nama_lengkap, username, email, no_wa, alamat, role, status, foto FROM tb_user WHERE id_user = ? LIMIT 1');
    $st->execute([$userId]);
    $user = $st->fetch(PDO::FETCH_ASSOC) ?: null;
  }
} catch (Throwable $e) {
  $user = null;
}

// Sertakan layout
include_once __DIR__ . '/../includes/header.php';
include_once __DIR__ . '/../includes/sidebar.php';
include_once __DIR__ . '/../includes/navbar.php';
?>

<?php
// Tentukan sumber gambar profil: gunakan foto dari DB jika ada, selain itu pakai default asset
try {
  $defaultProfile = (isset($assetBase) ? $assetBase : '/assets/') . 'images/profile/user-1.jpg';
  $profileImgSrc = $defaultProfile;
  if (!empty($user['foto'])) {
    // Pastikan urlBase tersedia dari navbar.php; jika belum, hitung cepat
    if (!isset($urlBase)) {
      $script = $_SERVER['SCRIPT_NAME'] ?? '';
      if (preg_match('#^/([^/]+)/#', $script, $m)) { $urlBase = '/' . $m[1] . '/'; } else { $urlBase = '/'; }
    }
    $profileImgSrc = $urlBase . ltrim((string)$user['foto'], '/');
  }
} catch (Throwable $e) {
  $profileImgSrc = (isset($assetBase) ? $assetBase : '/assets/') . 'images/profile/user-1.jpg';
}
?>

<div class="body-wrapper">
  <div class="container-fluid">
    <div class="card">
      <div class="card-body">
        <div class="d-flex align-items-center mb-4">
          <img src="<?php echo htmlspecialchars($profileImgSrc); ?>" alt="Profile" width="72" height="72" class="rounded-circle me-3">
          <div>
            <h2 class="h5 mb-1">Profil Admin</h2>
            <div class="text-muted">Kelola info akun Anda</div>
          </div>
        </div>

        <div class="row g-3">
          <div class="col-md-6">
            <div class="p-3 border rounded">
              <h3 class="h6">Informasi Akun</h3>
              <dl class="row mb-0">
                <dt class="col-sm-4">Nama Lengkap</dt>
                <dd class="col-sm-8"><?php echo htmlspecialchars($user['nama_lengkap'] ?? ($_SESSION['nama_lengkap'] ?? '-')); ?></dd>

                <dt class="col-sm-4">Username</dt>
                <dd class="col-sm-8"><?php echo htmlspecialchars($user['username'] ?? ($_SESSION['auth']['username'] ?? '-')); ?></dd>

                <dt class="col-sm-4">Role</dt>
                <dd class="col-sm-8"><?php echo htmlspecialchars($user['role'] ?? ($_SESSION['auth']['role'] ?? '-')); ?></dd>

                <dt class="col-sm-4">Status</dt>
                <dd class="col-sm-8"><?php echo htmlspecialchars($user['status'] ?? 'aktif'); ?></dd>
              </dl>
            </div>
          </div>
          <div class="col-md-6">
            <div class="p-3 border rounded">
              <h3 class="h6">Kontak</h3>
              <dl class="row mb-0">
                <dt class="col-sm-4">Email</dt>
                <dd class="col-sm-8"><?php echo htmlspecialchars($user['email'] ?? '-'); ?></dd>

                <dt class="col-sm-4">No. WA</dt>
                <dd class="col-sm-8"><?php echo htmlspecialchars($user['no_wa'] ?? '-'); ?></dd>

                <dt class="col-sm-4">Alamat</dt>
                <dd class="col-sm-8"><?php echo nl2br(htmlspecialchars($user['alamat'] ?? '-')); ?></dd>
              </dl>
            </div>
          </div>
        </div>

      </div>
    </div>
  </div>
</div>

<?php include_once __DIR__ . '/../includes/footer.php'; ?>
