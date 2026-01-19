<?php
session_start();
require_once __DIR__ . '/config/koneksi.php';

$errors = [];
$success = $_SESSION['success'] ?? null;
$logoutFlag = isset($_GET['logout']) ? true : false;
unset($_SESSION['success']);
$hasNik = false;
try {
    $stCols = $pdo->prepare("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tb_user'");
    $stCols->execute();
    $cols = array_map(function($r){ return strtolower($r['COLUMN_NAME']); }, $stCols->fetchAll(PDO::FETCH_ASSOC));
    $hasNik = in_array('nik', $cols, true);
} catch (Throwable $e) {}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username === '' || $password === '') {
        $errors[] = 'Username dan password wajib diisi.';
    } else {
        try {
            if ($hasNik) {
                $nikCandidate = preg_replace('/\D+/', '', $username);
                if (strlen($nikCandidate) === 16) {
                    $stmt = $pdo->prepare("SELECT id_user, nama_lengkap, username, password, role, status FROM tb_user WHERE username = ? OR nik = ? LIMIT 1");
                    $stmt->execute([$username, $nikCandidate]);
                } else {
                    $stmt = $pdo->prepare("SELECT id_user, nama_lengkap, username, password, role, status FROM tb_user WHERE username = ? LIMIT 1");
                    $stmt->execute([$username]);
                }
            } else {
                $stmt = $pdo->prepare("SELECT id_user, nama_lengkap, username, password, role, status FROM tb_user WHERE username = ? LIMIT 1");
                $stmt->execute([$username]);
            }
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$user) {
                $errors[] = 'Username tidak ditemukan.';
            } elseif (($user['status'] ?? 'nonaktif') !== 'aktif') {
                $errors[] = 'Akun Anda belum aktif. Silakan menunggu verifikasi admin.';
            } elseif (!password_verify($password, $user['password'])) {
                $errors[] = 'Password salah.';
            } else {
                // Set sesi konsisten (struktur 'auth' dan kunci tingkat atas)
                session_regenerate_id(true);
                $_SESSION['auth'] = [
                    'id_user' => (int)$user['id_user'],
                    'nama_lengkap' => $user['nama_lengkap'],
                    'username' => $user['username'],
                    'role' => $user['role'],
                    'logged_in' => true,
                ];
                // Kompatibilitas dengan halaman admin yang mengecek kunci tingkat atas
                $_SESSION['user_id'] = (int)$user['id_user'];
                $_SESSION['role'] = strtolower($user['role'] ?? '');
                $_SESSION['nama_lengkap'] = $user['nama_lengkap'];
                $_SESSION['logged_in'] = true;
                // Arahkan berdasarkan role
                $role = strtolower(trim($user['role'] ?? ''));
                // Tentukan base URL proyek (mis. /sepati_emas/)
                $script = $_SERVER['SCRIPT_NAME'] ?? '';
                if (preg_match('#^/([^/]+)/#', $script, $m)) {
                    $urlBase = '/' . $m[1] . '/';
                } else {
                    $urlBase = '/';
                }
                if ($role === 'admin') {
                    header('Location: ' . $urlBase . 'admin/dashboard.php');
                } else {
                    header('Location: ' . $urlBase . 'peserta/dashboard.php');
                }
                exit;
            }
        } catch (Throwable $e) {
            $errors[] = 'Terjadi kesalahan saat login.';
        }
    }
}
?>
<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Login — SEPATU EMAS</title>
  <link rel="shortcut icon" type="image/png" href="assets/images/logos/favicon.png" />
  <link rel="stylesheet" href="assets/libs/bootstrap/dist/css/bootstrap.min.css" />
  <link rel="stylesheet" href="assets/css/styles.min.css" />
  <style>
    :root { --primary-blue:#0E4DA4; --primary-green:#19A86E; --secondary-blue:#145DA0; }
    .brand-gradient { background: linear-gradient(135deg, var(--primary-green), var(--primary-blue)); -webkit-background-clip:text; -webkit-text-fill-color:transparent; background-clip:text; color:transparent; }
    .radial-gradient { background: radial-gradient(1200px circle at 10% 10%, rgba(31,173,119,0.15), transparent 40%), radial-gradient(1200px circle at 90% 20%, rgba(20,93,160,0.18), transparent 40%); }
  </style>
</head>
<body>
  <div class="min-vh-100 d-flex align-items-center justify-content-center radial-gradient">
    <div class="container" style="max-width:420px;">
      <div class="card shadow-sm">
        <div class="card-body p-4">
          <div class="text-center mb-3">
            <img src="assets/images/logos/sepatu-emas.png" alt="SEPATU EMAS" width="72" height="72">
          </div>
          <h1 class="h4 text-center brand-gradient mb-2">Masuk ke SEPATU EMAS</h1>
          <p class="text-center text-muted mb-3">Silakan login untuk melanjutkan.</p>
          <?php if (!empty($success)): ?>
            <div class="alert alert-success" role="alert"><?php echo htmlspecialchars($success); ?></div>
          <?php endif; ?>
          <?php if (!empty($logoutFlag)): ?>
            <div class="alert alert-success" role="alert">Anda telah keluar. Silakan login kembali.</div>
          <?php endif; ?>
          <?php if (!empty($errors)): ?>
            <div class="alert alert-danger" role="alert">
              <?php foreach ($errors as $err) { echo '<div>'.htmlspecialchars($err).'</div>'; } ?>
            </div>
          <?php endif; ?>
          <form method="post" action="login.php" novalidate>
            <div class="mb-3">
              <label for="username" class="form-label">Username atau NIK</label>
              <input type="text" class="form-control" id="username" name="username" placeholder="Masukkan username atau NIK (16 digit)" required>
            </div>
            <div class="mb-3">
              <label for="password" class="form-label">Password</label>
              <input type="password" class="form-control" id="password" name="password" required>
            </div>
            <button type="submit" class="btn btn-primary w-100">Login</button>
          </form>
          <div class="text-center mt-3">
            <span class="text-muted">Belum punya akun?</span>
            <a href="register.php" class="fw-semibold">Daftar</a>
          </div>
          <div class="text-center mt-2"><a href="index.php" class="text-decoration-none">Kembali ke Beranda</a></div>
        </div>
      </div>
    </div>
  </div>
  <script src="assets/libs/bootstrap/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
