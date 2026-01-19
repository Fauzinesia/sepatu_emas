<?php
session_start();
if (!isset($_SESSION['auth']['logged_in']) || $_SESSION['auth']['logged_in'] !== true) {
  header('Location: ../../login.php');
  exit;
}
if (strtolower($_SESSION['auth']['role'] ?? '') !== 'admin') {
  header('Location: ../../peserta/dashboard.php');
  exit;
}

$page_title = 'Pengguna';
require_once __DIR__ . '/../../config/koneksi.php';

$hasNik = false;
$hasTempatLahir = false;
$hasTanggalLahir = false;
try {
  $stCols = $pdo->prepare("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tb_user'");
  $stCols->execute();
  $cols = array_map(function($r){ return strtolower($r['COLUMN_NAME']); }, $stCols->fetchAll(PDO::FETCH_ASSOC));
  $hasNik = in_array('nik', $cols, true);
  $hasTempatLahir = in_array('tempat_lahir', $cols, true);
  $hasTanggalLahir = in_array('tanggal_lahir', $cols, true);
} catch (Throwable $e) {}
// CSRF token sederhana untuk operasi POST
if (empty($_SESSION['csrf_token'])) {
  $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Penanganan aksi CRUD
$flash_success = '';
$flash_error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $action = $_POST['action'] ?? '';
  $csrf = $_POST['csrf_token'] ?? '';
  if (!hash_equals($_SESSION['csrf_token'], $csrf)) {
    $flash_error = 'Token tidak valid. Silakan muat ulang halaman.';
  } else {
    try {
      if ($action === 'create') {
        $nama_lengkap = trim($_POST['nama_lengkap'] ?? '');
        $jenis_kelamin = trim($_POST['jenis_kelamin'] ?? '');
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        $role = strtolower(trim($_POST['role'] ?? 'peserta'));
        $email = trim($_POST['email'] ?? '');
        $no_wa = trim($_POST['no_wa'] ?? '');
        $pendidikan_terakhir = trim($_POST['pendidikan_terakhir'] ?? '');
        $alamat = trim($_POST['alamat'] ?? '');
        $id_provinsi = trim($_POST['id_provinsi'] ?? '');
        $id_kabupaten = trim($_POST['id_kabupaten'] ?? '');
        $id_kecamatan = trim($_POST['id_kecamatan'] ?? '');
        $id_desa = trim($_POST['id_desa'] ?? '');
        $status = strtolower(trim($_POST['status'] ?? 'aktif'));
        $nik = preg_replace('/\D+/', '', $_POST['nik'] ?? '');
        $tempat_lahir = trim($_POST['tempat_lahir'] ?? '');
        $tanggal_lahir_raw = trim($_POST['tanggal_lahir'] ?? '');
        $tanggal_lahir = $tanggal_lahir_raw !== '' ? $tanggal_lahir_raw : null;
        if ($nama_lengkap === '' || $username === '' || $password === '') {
          throw new Exception('Nama lengkap, username, dan password wajib diisi.');
        }
        if ($hasNik && $nik !== '' && strlen($nik) !== 16) {
          throw new Exception('NIK harus 16 digit.');
        }
        if ($hasTanggalLahir && $tanggal_lahir !== null && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $tanggal_lahir)) {
          throw new Exception('Tanggal lahir tidak valid.');
        }
        $hash = password_hash($password, PASSWORD_DEFAULT);

        // Upload foto (opsional)
        $foto_path = null;
        if (isset($_FILES['foto']) && is_array($_FILES['foto']) && ($_FILES['foto']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
          if ($_FILES['foto']['error'] === UPLOAD_ERR_OK) {
            $tmp = $_FILES['foto']['tmp_name'];
            $size = (int)$_FILES['foto']['size'];
            $mime = @mime_content_type($tmp) ?: '';
            $allowed = ['image/jpeg','image/png'];
            if (!in_array($mime, $allowed, true)) { throw new Exception('Format foto harus JPG atau PNG.'); }
            if ($size > 3 * 1024 * 1024) { throw new Exception('Ukuran foto maksimal 3MB.'); }
            $ext = strtolower(pathinfo($_FILES['foto']['name'], PATHINFO_EXTENSION));
            if (!in_array($ext, ['jpg','jpeg','png'], true)) { $ext = $mime === 'image/png' ? 'png' : 'jpg'; }
            $uploadDir = __DIR__ . '/../../uploads/profile';
            if (!is_dir($uploadDir)) { @mkdir($uploadDir, 0775, true); }
            $filename = 'user-foto-' . bin2hex(random_bytes(6)) . '.' . $ext;
            $destAbs = $uploadDir . '/' . $filename;
            if (!@move_uploaded_file($tmp, $destAbs)) { throw new Exception('Gagal menyimpan foto profil.'); }
            $foto_path = 'uploads/profile/' . $filename;
          } else {
            throw new Exception('Gagal upload foto profil (kode: ' . (int)$_FILES['foto']['error'] . ').');
          }
        }

        $cols = "nama_lengkap, jenis_kelamin, username, password, role, email, no_wa, pendidikan_terakhir, alamat, id_provinsi, id_kabupaten, id_kecamatan, id_desa, status, foto";
        $place = implode(',', array_fill(0, 15, '?'));
        $vals = [$nama_lengkap, $jenis_kelamin ?: null, $username, $hash, $role, $email, $no_wa ?: null, $pendidikan_terakhir ?: null, $alamat, $id_provinsi ?: null, $id_kabupaten ?: null, $id_kecamatan ?: null, $id_desa ?: null, $status, $foto_path];
        if ($hasNik) { $cols .= ", nik"; $place .= ", ?"; $vals[] = ($nik !== '' ? $nik : null); }
        if ($hasTempatLahir) { $cols .= ", tempat_lahir"; $place .= ", ?"; $vals[] = ($tempat_lahir !== '' ? $tempat_lahir : null); }
        if ($hasTanggalLahir) { $cols .= ", tanggal_lahir"; $place .= ", ?"; $vals[] = $tanggal_lahir; }
        $stmt = $pdo->prepare("INSERT INTO tb_user ($cols) VALUES ($place)");
        $stmt->execute($vals);
        $flash_success = 'Pengguna berhasil ditambahkan.';
      } elseif ($action === 'update') {
        $id_user = (int)($_POST['id_user'] ?? 0);
        $nama_lengkap = trim($_POST['nama_lengkap'] ?? '');
        $jenis_kelamin = trim($_POST['jenis_kelamin'] ?? '');
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        $role = strtolower(trim($_POST['role'] ?? 'peserta'));
        $email = trim($_POST['email'] ?? '');
        $no_wa = trim($_POST['no_wa'] ?? '');
        $pendidikan_terakhir = trim($_POST['pendidikan_terakhir'] ?? '');
        $alamat = trim($_POST['alamat'] ?? '');
        $id_provinsi = trim($_POST['id_provinsi'] ?? '');
        $id_kabupaten = trim($_POST['id_kabupaten'] ?? '');
        $id_kecamatan = trim($_POST['id_kecamatan'] ?? '');
        $id_desa = trim($_POST['id_desa'] ?? '');
        $status = strtolower(trim($_POST['status'] ?? 'aktif'));
        $nik = preg_replace('/\D+/', '', $_POST['nik'] ?? '');
        $tempat_lahir = trim($_POST['tempat_lahir'] ?? '');
        $tanggal_lahir_raw = trim($_POST['tanggal_lahir'] ?? '');
        $tanggal_lahir = $tanggal_lahir_raw !== '' ? $tanggal_lahir_raw : null;
        if ($id_user <= 0 || $nama_lengkap === '' || $username === '') {
          throw new Exception('ID pengguna, nama lengkap, dan username wajib diisi.');
        }
        if ($hasNik && $nik !== '' && strlen($nik) !== 16) {
          throw new Exception('NIK harus 16 digit.');
        }
        if ($hasTanggalLahir && $tanggal_lahir !== null && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $tanggal_lahir)) {
          throw new Exception('Tanggal lahir tidak valid.');
        }
        // Siapkan update foto jika ada upload baru
        $new_foto_path = null;
        if (isset($_FILES['foto']) && is_array($_FILES['foto']) && ($_FILES['foto']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
          if ($_FILES['foto']['error'] === UPLOAD_ERR_OK) {
            $tmp = $_FILES['foto']['tmp_name'];
            $size = (int)$_FILES['foto']['size'];
            $mime = @mime_content_type($tmp) ?: '';
            $allowed = ['image/jpeg','image/png'];
            if (!in_array($mime, $allowed, true)) { throw new Exception('Format foto harus JPG atau PNG.'); }
            if ($size > 3 * 1024 * 1024) { throw new Exception('Ukuran foto maksimal 3MB.'); }
            $ext = strtolower(pathinfo($_FILES['foto']['name'], PATHINFO_EXTENSION));
            if (!in_array($ext, ['jpg','jpeg','png'], true)) { $ext = $mime === 'image/png' ? 'png' : 'jpg'; }
            $uploadDir = __DIR__ . '/../../uploads/profile';
            if (!is_dir($uploadDir)) { @mkdir($uploadDir, 0775, true); }
            $filename = 'user-' . $id_user . '-foto-' . bin2hex(random_bytes(6)) . '.' . $ext;
            $destAbs = $uploadDir . '/' . $filename;
            if (!@move_uploaded_file($tmp, $destAbs)) { throw new Exception('Gagal menyimpan foto profil.'); }
            $new_foto_path = 'uploads/profile/' . $filename;
          } else {
            throw new Exception('Gagal upload foto profil (kode: ' . (int)$_FILES['foto']['error'] . ').');
          }
        }

        // Jika ada foto baru, hapus foto lama
        if ($new_foto_path !== null) {
          try {
            $st = $pdo->prepare('SELECT foto FROM tb_user WHERE id_user=?');
            $st->execute([$id_user]);
            $old = $st->fetch(PDO::FETCH_ASSOC);
            $oldPath = $old['foto'] ?? '';
            if ($oldPath) {
              $abs = __DIR__ . '/../../' . ltrim($oldPath, '/');
              if (is_file($abs)) { @unlink($abs); }
            }
          } catch (Throwable $e) { /* abaikan */ }
        }

        $extraSet = '';
        $extraParams = [];
        if ($hasNik) { $extraSet .= ', nik=?'; $extraParams[] = ($nik !== '' ? $nik : null); }
        if ($hasTempatLahir) { $extraSet .= ', tempat_lahir=?'; $extraParams[] = ($tempat_lahir !== '' ? $tempat_lahir : null); }
        if ($hasTanggalLahir) { $extraSet .= ', tanggal_lahir=?'; $extraParams[] = $tanggal_lahir; }

        if ($password !== '') {
          $hash = password_hash($password, PASSWORD_DEFAULT);
          $sql = "UPDATE tb_user SET nama_lengkap=?, jenis_kelamin=?, username=?, password=?, role=?, email=?, no_wa=?, pendidikan_terakhir=?, alamat=?, id_provinsi=?, id_kabupaten=?, id_kecamatan=?, id_desa=?, status=?" . $extraSet . ($new_foto_path!==null?", foto=?":"") . " WHERE id_user=?";
          $params = [$nama_lengkap, $jenis_kelamin ?: null, $username, $hash, $role, $email, $no_wa ?: null, $pendidikan_terakhir ?: null, $alamat, $id_provinsi ?: null, $id_kabupaten ?: null, $id_kecamatan ?: null, $id_desa ?: null, $status];
          $params = array_merge($params, $extraParams);
          if ($new_foto_path!==null) { $params[] = $new_foto_path; }
          $params[] = $id_user;
          $stmt = $pdo->prepare($sql);
          $stmt->execute($params);
        } else {
          $sql = "UPDATE tb_user SET nama_lengkap=?, jenis_kelamin=?, username=?, role=?, email=?, no_wa=?, pendidikan_terakhir=?, alamat=?, id_provinsi=?, id_kabupaten=?, id_kecamatan=?, id_desa=?, status=?" . $extraSet . ($new_foto_path!==null?", foto=?":"") . " WHERE id_user=?";
          $params = [$nama_lengkap, $jenis_kelamin ?: null, $username, $role, $email, $no_wa ?: null, $pendidikan_terakhir ?: null, $alamat, $id_provinsi ?: null, $id_kabupaten ?: null, $id_kecamatan ?: null, $id_desa ?: null, $status];
          $params = array_merge($params, $extraParams);
          if ($new_foto_path!==null) { $params[] = $new_foto_path; }
          $params[] = $id_user;
          $stmt = $pdo->prepare($sql);
          $stmt->execute($params);
        }
        $flash_success = 'Pengguna berhasil diperbarui.';
      } elseif ($action === 'set_status') {
        $id_user = (int)($_POST['id_user'] ?? 0);
        $new_status = strtolower(trim($_POST['status'] ?? ''));
        if ($id_user <= 0) {
          throw new Exception('ID pengguna tidak valid.');
        }
        if (!in_array($new_status, ['aktif','nonaktif'], true)) {
          throw new Exception('Status tidak valid.');
        }
        $stmt = $pdo->prepare("UPDATE tb_user SET status = ? WHERE id_user = ?");
        $stmt->execute([$new_status, $id_user]);
        if ($new_status === 'aktif') {
          $flash_success = 'Akun pengguna berhasil diverifikasi (diaktifkan).';
        } else {
          $flash_success = 'Akun pengguna berhasil dinonaktifkan.';
        }
      } elseif ($action === 'delete') {
        $id_user = (int)($_POST['id_user'] ?? 0);
        if ($id_user <= 0) { throw new Exception('ID pengguna tidak valid.'); }
        $stmt = $pdo->prepare("DELETE FROM tb_user WHERE id_user = ?");
        $stmt->execute([$id_user]);
        $flash_success = 'Pengguna berhasil dihapus.';
      }
    } catch (PDOException $ex) {
      if ($ex->getCode() === '23000') {
        $flash_error = 'Username sudah digunakan. Pilih username lain.';
      } else {
        $flash_error = 'Kesalahan database: ' . htmlspecialchars($ex->getMessage());
      }
    } catch (Throwable $e) {
      $flash_error = htmlspecialchars($e->getMessage());
    }
  }
}

// Ambil parameter filter dari GET
$filter_role = isset($_GET['role']) && $_GET['role'] !== '' ? trim($_GET['role']) : '';
$filter_status = isset($_GET['status']) && $_GET['status'] !== '' ? trim($_GET['status']) : '';
$filter_jk = isset($_GET['jenis_kelamin']) && $_GET['jenis_kelamin'] !== '' ? trim($_GET['jenis_kelamin']) : '';
$filter_kecamatan = isset($_GET['kecamatan']) && $_GET['kecamatan'] !== '' ? trim($_GET['kecamatan']) : '';

// Pagination
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$perPage = isset($_GET['per_page']) ? max(1, min(100, (int)$_GET['per_page'])) : 10;
$offset = ($page - 1) * $perPage;

// Ambil data pengguna dengan filter
$users = [];
$totalRows = 0;
$totalPages = 1;
try {
  $base = " FROM tb_user u
            LEFT JOIN t_provinsi p ON u.id_provinsi = p.id
            LEFT JOIN t_kota k ON u.id_kabupaten = k.id
            LEFT JOIN t_kecamatan kc ON u.id_kecamatan = kc.id
            LEFT JOIN t_kelurahan d ON u.id_desa = d.id
            WHERE 1=1";
  $whereParams = [];
  $whereSql = "";
  if ($filter_role !== '') { $whereSql .= " AND role = ?"; $whereParams[] = $filter_role; }
  if ($filter_status !== '') { $whereSql .= " AND status = ?"; $whereParams[] = $filter_status; }
  if ($filter_jk !== '') { $whereSql .= " AND jenis_kelamin = ?"; $whereParams[] = $filter_jk; }
  if ($filter_kecamatan !== '') { $whereSql .= " AND kc.nama = ?"; $whereParams[] = $filter_kecamatan; }

  $countStmt = $pdo->prepare("SELECT COUNT(*)" . $base . $whereSql);
  $countStmt->execute($whereParams);
  $totalRows = (int)$countStmt->fetchColumn();
  $totalPages = max(1, (int)ceil($totalRows / $perPage));

  $extraCols = '';
  if ($hasNik) { $extraCols .= ", u.nik"; }
  if ($hasTempatLahir) { $extraCols .= ", u.tempat_lahir"; }
  if ($hasTanggalLahir) { $extraCols .= ", u.tanggal_lahir"; }
  $dataSql = "SELECT u.id_user, u.nama_lengkap, u.jenis_kelamin, u.username, u.role, u.email, u.no_wa, u.pendidikan_terakhir, u.status, u.created_at, u.foto, u.alamat, 
                     u.id_provinsi, u.id_kabupaten, u.id_kecamatan, u.id_desa" . $extraCols . ",
                     p.nama as nama_provinsi, k.nama as nama_kabupaten, kc.nama as nama_kecamatan, d.nama as nama_desa" .
             $base . $whereSql .
             " ORDER BY created_at DESC LIMIT :offset, :limit";
  $stmt = $pdo->prepare($dataSql);
  foreach ($whereParams as $idx => $val) {
    $stmt->bindValue($idx + 1, $val);
  }
  $stmt->bindValue(':offset', (int)$offset, PDO::PARAM_INT);
  $stmt->bindValue(':limit', (int)$perPage, PDO::PARAM_INT);
  $stmt->execute();
  $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
  $users = [];
}

include_once __DIR__ . '/../../includes/header.php';
include_once __DIR__ . '/../../includes/sidebar.php';
?>
    <div class="body-wrapper">
      <?php include_once __DIR__ . '/../../includes/navbar.php'; ?>
    <div class="container-fluid">
      <div class="d-flex justify-content-between align-items-center mb-3">
        <h5 class="card-title mb-0 fw-semibold">Pengguna</h5>
        <div class="d-flex gap-2">
          <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalCreate"><i class="ti ti-user-plus me-1"></i> Tambah</button>
          <button id="btnPrint" class="btn btn-outline-secondary"><i class="ti ti-printer me-1"></i> Cetak</button>
        </div>
      </div>

      <?php if ($flash_success) { ?>
        <div class="alert alert-success" role="alert"><?php echo htmlspecialchars($flash_success); ?></div>
      <?php } elseif ($flash_error) { ?>
        <div class="alert alert-danger" role="alert"><?php echo htmlspecialchars($flash_error); ?></div>
      <?php } ?>

      <div class="card">
        <div class="card-body">
          <!-- Filter Section -->
          <div class="mb-4 p-3 bg-light rounded">
            <form method="get" action="pengguna.php" id="filterForm">
              <div class="row g-3 align-items-end">
                <div class="col-md-3">
                  <label class="form-label fw-semibold">Role</label>
                  <select name="role" class="form-select form-select-sm">
                    <option value="">-- Semua Role --</option>
                    <option value="admin" <?php echo $filter_role === 'admin' ? 'selected' : ''; ?>>Admin</option>
                    <option value="peserta" <?php echo $filter_role === 'peserta' ? 'selected' : ''; ?>>Peserta</option>
                  </select>
                </div>
                <div class="col-md-3">
                  <label class="form-label fw-semibold">Status</label>
                  <select name="status" class="form-select form-select-sm">
                    <option value="">-- Semua Status --</option>
                    <option value="aktif" <?php echo $filter_status === 'aktif' ? 'selected' : ''; ?>>Aktif</option>
                    <option value="nonaktif" <?php echo $filter_status === 'nonaktif' ? 'selected' : ''; ?>>Nonaktif</option>
                  </select>
                </div>
                <div class="col-md-3">
                  <label class="form-label fw-semibold">Jenis Kelamin</label>
                  <select name="jenis_kelamin" class="form-select form-select-sm">
                    <option value="">-- Semua --</option>
                    <option value="Laki-laki" <?php echo $filter_jk === 'Laki-laki' ? 'selected' : ''; ?>>Laki-laki</option>
                    <option value="Perempuan" <?php echo $filter_jk === 'Perempuan' ? 'selected' : ''; ?>>Perempuan</option>
                  </select>
                </div>
                <div class="col-md-3">
                  <label class="form-label fw-semibold">Kecamatan</label>
                  <select name="kecamatan" class="form-select form-select-sm">
                    <option value="">-- Semua Kecamatan --</option>
                    <option value="Amuntai Selatan" <?php echo $filter_kecamatan === 'Amuntai Selatan' ? 'selected' : ''; ?>>Amuntai Selatan</option>
                    <option value="Amuntai Tengah" <?php echo $filter_kecamatan === 'Amuntai Tengah' ? 'selected' : ''; ?>>Amuntai Tengah</option>
                    <option value="Amuntai Utara" <?php echo $filter_kecamatan === 'Amuntai Utara' ? 'selected' : ''; ?>>Amuntai Utara</option>
                    <option value="Babirik" <?php echo $filter_kecamatan === 'Babirik' ? 'selected' : ''; ?>>Babirik</option>
                    <option value="Banjang" <?php echo $filter_kecamatan === 'Banjang' ? 'selected' : ''; ?>>Banjang</option>
                    <option value="Danau Panggang" <?php echo $filter_kecamatan === 'Danau Panggang' ? 'selected' : ''; ?>>Danau Panggang</option>
                    <option value="Haur Gading" <?php echo $filter_kecamatan === 'Haur Gading' ? 'selected' : ''; ?>>Haur Gading</option>
                    <option value="Paminggir" <?php echo $filter_kecamatan === 'Paminggir' ? 'selected' : ''; ?>>Paminggir</option>
                    <option value="Sungai Pandan" <?php echo $filter_kecamatan === 'Sungai Pandan' ? 'selected' : ''; ?>>Sungai Pandan</option>
                    <option value="Sungai Tabukan" <?php echo $filter_kecamatan === 'Sungai Tabukan' ? 'selected' : ''; ?>>Sungai Tabukan</option>
                  </select>
                </div>
                <div class="col-md-12">
                  <button type="submit" class="btn btn-primary btn-sm">
                    <i class="ti ti-filter me-1"></i> Filter
                  </button>
                  <a href="pengguna.php" class="btn btn-secondary btn-sm">
                    <i class="ti ti-refresh me-1"></i> Reset
                  </a>
                  <span class="ms-3 text-muted">
                    <i class="ti ti-users"></i> Total: <strong><?php echo $totalRows; ?></strong> pengguna
                  </span>
                </div>
              </div>
            </form>
          </div>

          <div class="d-sm-flex d-block align-items-center justify-content-between mb-3">
            <div class="mb-2 mb-sm-0">
              <p class="text-muted mb-0">Kelola akun pengguna (admin dan peserta)</p>
            </div>
            <div class="input-group" style="max-width: 280px;">
              <span class="input-group-text"><i class="ti ti-search"></i></span>
              <input type="text" id="searchInput" class="form-control" placeholder="Cari nama, username, atau role...">
            </div>
          </div>
          <div class="table-responsive">
            <table class="table align-middle text-nowrap" id="usersTable">
              <thead class="text-dark fs-4">
                <tr>
                  <th><h6 class="fw-semibold mb-0">No</h6></th>
                  <th><h6 class="fw-semibold mb-0">Nama Lengkap</h6></th>
                  <th><h6 class="fw-semibold mb-0">Username</h6></th>
                  <th><h6 class="fw-semibold mb-0">Role</h6></th>
                  <th><h6 class="fw-semibold mb-0">Foto</h6></th>
                  <th><h6 class="fw-semibold mb-0">Alamat Lengkap</h6></th>
                  <th><h6 class="fw-semibold mb-0">Status</h6></th>
                  <th><h6 class="fw-semibold mb-0">Dibuat</h6></th>
                  <th class="text-end"><h6 class="fw-semibold mb-0">Aksi</h6></th>
                </tr>
              </thead>
              <tbody>
                <?php if (!empty($users)) { $no = 1; foreach ($users as $u) { 
                  // Format alamat lengkap
                  $alamat_lengkap = [];
                  if (!empty($u['alamat'])) $alamat_lengkap[] = $u['alamat'];
                  if (!empty($u['nama_desa'])) $alamat_lengkap[] = 'Desa ' . ucwords(strtolower($u['nama_desa']));
                  if (!empty($u['nama_kecamatan'])) $alamat_lengkap[] = 'Kec. ' . ucwords(strtolower($u['nama_kecamatan']));
                  if (!empty($u['nama_kabupaten'])) $alamat_lengkap[] = ucwords(strtolower($u['nama_kabupaten']));
                  if (!empty($u['nama_provinsi'])) $alamat_lengkap[] = ucwords(strtolower($u['nama_provinsi']));
                  $str_alamat = implode(', ', $alamat_lengkap);
                ?>
                  <tr>
                    <td><?php echo $no++; ?></td>
                    <td>
                      <div class="d-flex flex-column">
                        <span class="fw-semibold"><?php echo htmlspecialchars($u['nama_lengkap']); ?></span>
                    <span class="text-muted fs-2"><?php echo htmlspecialchars($u['email'] ?? '-'); ?></span>
                    <span class="text-muted fs-2"><?php echo htmlspecialchars($u['no_wa'] ?? '-'); ?></span>
                    <?php if ($hasNik) { ?>
                      <span class="text-muted fs-2"><?php echo 'NIK: ' . htmlspecialchars($u['nik'] ?? '-'); ?></span>
                    <?php } ?>
                    <?php if ($hasTempatLahir || $hasTanggalLahir) { ?>
                      <span class="text-muted fs-2">
                        <?php
                          $ttlParts = [];
                          if ($hasTempatLahir && !empty($u['tempat_lahir'])) { $ttlParts[] = $u['tempat_lahir']; }
                          if ($hasTanggalLahir && !empty($u['tanggal_lahir'])) { $ttlParts[] = date('d/m/Y', strtotime($u['tanggal_lahir'])); }
                          echo 'TTL: ' . htmlspecialchars(!empty($ttlParts) ? implode(', ', $ttlParts) : '-');
                        ?>
                      </span>
                    <?php } ?>
                  </div>
                </td>
                    <td><?php echo htmlspecialchars($u['username']); ?></td>
                    <td><span class="badge bg-primary"><?php echo htmlspecialchars($u['role']); ?></span></td>
                    <td>
                      <?php if (!empty($u['foto'])) { ?>
                        <img src="<?php echo '../../' . htmlspecialchars($u['foto']); ?>" alt="Foto" class="rounded-circle" style="width:36px;height:36px;object-fit:cover;">
                      <?php } else { ?>
                        <span class="text-muted">—</span>
                      <?php } ?>
                    </td>
                    <td><small><?php echo htmlspecialchars($str_alamat ?: '-'); ?></small></td>
                    <td>
                      <?php if (($u['status'] ?? '') === 'aktif') { ?>
                        <span class="badge bg-success">aktif</span>
                      <?php } else { ?>
                        <span class="badge bg-secondary">nonaktif</span>
                      <?php } ?>
                    </td>
                    <td><?php echo htmlspecialchars($u['created_at']); ?></td>
                    <td class="text-end">
                      <?php if (($u['status'] ?? '') === 'nonaktif') { ?>
                        <form method="post" class="d-inline">
                          <input type="hidden" name="action" value="set_status">
                          <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                          <input type="hidden" name="id_user" value="<?php echo (int)$u['id_user']; ?>">
                          <input type="hidden" name="status" value="aktif">
                          <button type="submit" class="btn btn-sm btn-success me-1">
                            Verifikasi
                          </button>
                        </form>
                      <?php } elseif (($u['status'] ?? '') === 'aktif') { ?>
                        <form method="post" class="d-inline">
                          <input type="hidden" name="action" value="set_status">
                          <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                          <input type="hidden" name="id_user" value="<?php echo (int)$u['id_user']; ?>">
                          <input type="hidden" name="status" value="nonaktif">
                          <button type="submit" class="btn btn-sm btn-warning me-1">
                            Nonaktifkan
                          </button>
                        </form>
                      <?php } ?>
                      <button class="btn btn-sm btn-outline-primary me-1 btn-edit"
                        data-id="<?php echo (int)$u['id_user']; ?>"
                        data-nama="<?php echo htmlspecialchars($u['nama_lengkap']); ?>"
                        data-jk="<?php echo htmlspecialchars($u['jenis_kelamin'] ?? ''); ?>"
                        data-username="<?php echo htmlspecialchars($u['username']); ?>"
                        data-role="<?php echo htmlspecialchars($u['role'] ?? ''); ?>"
                        data-email="<?php echo htmlspecialchars($u['email'] ?? ''); ?>"
                        data-no-wa="<?php echo htmlspecialchars($u['no_wa'] ?? ''); ?>"
                        data-pendidikan-terakhir="<?php echo htmlspecialchars($u['pendidikan_terakhir'] ?? ''); ?>"
                        data-alamat="<?php echo htmlspecialchars($u['alamat'] ?? ''); ?>"
                        data-id-provinsi="<?php echo htmlspecialchars($u['id_provinsi'] ?? ''); ?>"
                        data-id-kabupaten="<?php echo htmlspecialchars($u['id_kabupaten'] ?? ''); ?>"
                        data-id-kecamatan="<?php echo htmlspecialchars($u['id_kecamatan'] ?? ''); ?>"
                        data-id-desa="<?php echo htmlspecialchars($u['id_desa'] ?? ''); ?>"
                        <?php if ($hasNik) { ?>data-nik="<?php echo htmlspecialchars($u['nik'] ?? ''); ?>"<?php } ?>
                        <?php if ($hasTempatLahir) { ?>data-tempat-lahir="<?php echo htmlspecialchars($u['tempat_lahir'] ?? ''); ?>"<?php } ?>
                        <?php if ($hasTanggalLahir) { ?>data-tanggal-lahir="<?php echo htmlspecialchars($u['tanggal_lahir'] ?? ''); ?>"<?php } ?>
                        data-status="<?php echo htmlspecialchars($u['status'] ?? ''); ?>">
                        <i class="ti ti-edit"></i>
                        Edit
                      </button>
                      <button class="btn btn-sm btn-outline-danger btn-delete"
                        data-id="<?php echo (int)$u['id_user']; ?>"
                        data-nama="<?php echo htmlspecialchars($u['nama_lengkap']); ?>">
                        <i class="ti ti-trash"></i>
                        Hapus
                      </button>
                    </td>
                  </tr>
                <?php } } else { ?>
                  <tr>
                    <td colspan="10" class="text-center">Tidak ada data pengguna.</td>
                  </tr>
                <?php } ?>
              </tbody>
            </table>
            <?php
              $basePath = $_SERVER['PHP_SELF'] ?? '';
              $qs = [];
              if ($filter_role !== '') { $qs['role'] = $filter_role; }
              if ($filter_status !== '') { $qs['status'] = $filter_status; }
              if ($filter_jk !== '') { $qs['jenis_kelamin'] = $filter_jk; }
              if ($filter_kecamatan !== '') { $qs['kecamatan'] = $filter_kecamatan; }
              $qs['per_page'] = (string)$perPage;
              $makeUrl = function($p) use ($basePath, $qs) {
                $qs['page'] = (string)$p;
                return $basePath . '?' . http_build_query($qs);
              };
              $start = $offset + 1;
              $end = min($offset + $perPage, $totalRows);
            ?>
            <?php if ($totalRows > 0): ?>
            <div class="d-flex align-items-center justify-content-between mt-3">
              <div class="text-muted small">
                Menampilkan <?php echo $start; ?>–<?php echo $end; ?> dari <?php echo $totalRows; ?> data
              </div>
              <nav>
                <ul class="pagination pagination-sm mb-0">
                  <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                    <a class="page-link" href="<?php echo $page <= 1 ? '#' : $makeUrl($page - 1); ?>">Prev</a>
                  </li>
                  <?php
                    $maxPagesToShow = 5;
                    $startPage = max(1, $page - 2);
                    $endPage = min($totalPages, $startPage + $maxPagesToShow - 1);
                    if ($endPage - $startPage + 1 < $maxPagesToShow) {
                      $startPage = max(1, $endPage - $maxPagesToShow + 1);
                    }
                    for ($p = $startPage; $p <= $endPage; $p++) {
                      $active = $p === $page ? 'active' : '';
                      echo '<li class="page-item ' . $active . '"><a class="page-link" href="' . $makeUrl($p) . '">' . $p . '</a></li>';
                    }
                  ?>
                  <li class="page-item <?php echo $page >= $totalPages ? 'disabled' : ''; ?>">
                    <a class="page-link" href="<?php echo $page >= $totalPages ? '#' : $makeUrl($page + 1); ?>">Next</a>
                  </li>
                </ul>
              </nav>
            </div>
            <?php endif; ?>
          </div>
        </div>
      </div>

      <!-- Modal: Tambah Pengguna -->
      <div class="modal fade" id="modalCreate" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
          <div class="modal-content">
            <div class="modal-header">
              <h5 class="modal-title">Tambah Pengguna</h5>
              <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="post" enctype="multipart/form-data">
              <input type="hidden" name="action" value="create">
              <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
              <div class="modal-body">
                <div class="row g-3">
                  <div class="col-md-6">
                    <label class="form-label">Nama Lengkap</label>
                    <input type="text" name="nama_lengkap" class="form-control" required>
                  </div>
                  <div class="col-md-6">
                    <label class="form-label">NIK</label>
                    <input type="text" name="nik" class="form-control" maxlength="16" pattern="\d{16}" placeholder="16 digit">
                  </div>
                  <div class="col-md-6">
                    <label class="form-label">Jenis Kelamin</label>
                    <select name="jenis_kelamin" class="form-select">
                      <option value="">-- Pilih --</option>
                      <option value="Laki-laki">Laki-laki</option>
                      <option value="Perempuan">Perempuan</option>
                    </select>
                  </div>
                  <div class="col-md-6">
                    <label class="form-label">Tempat Lahir</label>
                    <input type="text" name="tempat_lahir" class="form-control">
                  </div>
                  <div class="col-md-6">
                    <label class="form-label">Username</label>
                    <input type="text" name="username" class="form-control" required>
                  </div>
                  <div class="col-md-6">
                    <label class="form-label">Password</label>
                    <input type="password" name="password" class="form-control" required>
                  </div>
                  <div class="col-md-6">
                    <label class="form-label">Tanggal Lahir</label>
                    <input type="date" name="tanggal_lahir" class="form-control">
                  </div>
                  <div class="col-md-6">
                    <label class="form-label">Role</label>
                    <select name="role" class="form-select">
                      <option value="peserta">Peserta</option>
                      <option value="admin">Admin</option>
                    </select>
                  </div>
                <div class="col-md-6">
                  <label class="form-label">Email</label>
                  <input type="email" name="email" class="form-control">
                </div>
                <div class="col-md-6">
                </div>
                <div class="col-md-6">
                  <label class="form-label">No WA</label>
                  <input type="text" name="no_wa" class="form-control">
                </div>
                <div class="col-md-6">
                  <label class="form-label">Pendidikan Terakhir</label>
                  <select name="pendidikan_terakhir" class="form-select">
                    <option value="">-- Pilih --</option>
                    <option value="SMP">SMP</option>
                    <option value="SMA/SMK">SMA/SMK</option>
                    <option value="D3">D3</option>
                    <option value="S1">S1</option>
                    <option value="S2">S2</option>
                    <option value="S3">S3</option>
                  </select>
                </div>
                  
                  <!-- Cascading Dropdown Wilayah -->
                  <div class="col-md-6">
                    <label class="form-label">Provinsi <span class="text-danger">*</span></label>
                    <select name="id_provinsi" id="provinsi" class="form-select" required>
                      <option value="">-- Pilih Provinsi --</option>
                    </select>
                  </div>
                  <div class="col-md-6">
                    <label class="form-label">Kabupaten/Kota <span class="text-danger">*</span></label>
                    <select name="id_kabupaten" id="kabupaten" class="form-select" required>
                      <option value="">-- Pilih Kabupaten --</option>
                    </select>
                  </div>
                  <div class="col-md-6">
                    <label class="form-label">Kecamatan <span class="text-danger">*</span></label>
                    <select name="id_kecamatan" id="kecamatan" class="form-select" required>
                      <option value="">-- Pilih Kecamatan --</option>
                    </select>
                  </div>
                  <div class="col-md-6">
                    <label class="form-label">Desa/Kelurahan</label>
                    <select name="id_desa" id="desa" class="form-select">
                      <option value="">-- Pilih Desa --</option>
                    </select>
                  </div>
                  <div class="col-12">
                    <label class="form-label">Alamat Detail (RT/RW, Jalan, No. Rumah)</label>
                    <textarea name="alamat" class="form-control" rows="2" placeholder="Contoh: RT 02 RW 03, Jl. Veteran No. 123"></textarea>
                  </div>
                  
                  
                  <div class="col-md-6">
                    <label class="form-label">Status</label>
                    <select name="status" class="form-select">
                      <option value="aktif">aktif</option>
                      <option value="nonaktif">nonaktif</option>
                    </select>
                  </div>
                  <div class="col-md-6">
                    <label class="form-label">Foto Profil (opsional)</label>
                    <input type="file" name="foto" class="form-control" accept="image/png,image/jpeg,image/jpg">
                  </div>
                </div>
              </div>
              <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                <button type="submit" class="btn btn-primary">Simpan</button>
              </div>
            </form>
          </div>
        </div>
      </div>

      <!-- Modal: Edit Pengguna -->
      <div class="modal fade" id="modalEdit" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
          <div class="modal-content">
            <div class="modal-header">
              <h5 class="modal-title">Edit Pengguna</h5>
              <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="post" enctype="multipart/form-data">
              <input type="hidden" name="action" value="update">
              <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
              <input type="hidden" name="id_user" id="edit_id_user">
              <div class="modal-body">
                <div class="row g-3">
                  <div class="col-md-6">
                    <label class="form-label">Nama Lengkap</label>
                    <input type="text" name="nama_lengkap" id="edit_nama" class="form-control" required>
                  </div>
                  <div class="col-md-6">
                    <label class="form-label">NIK</label>
                    <input type="text" name="nik" id="edit_nik" class="form-control" maxlength="16" pattern="\d{16}" placeholder="16 digit">
                  </div>
                  <div class="col-md-6">
                    <label class="form-label">Jenis Kelamin</label>
                    <select name="jenis_kelamin" id="edit_jk" class="form-select">
                      <option value="">-- Pilih --</option>
                      <option value="Laki-laki">Laki-laki</option>
                      <option value="Perempuan">Perempuan</option>
                    </select>
                  </div>
                  <div class="col-md-6">
                    <label class="form-label">Tempat Lahir</label>
                    <input type="text" name="tempat_lahir" id="edit_tempat_lahir" class="form-control">
                  </div>
                  <div class="col-md-6">
                    <label class="form-label">Username</label>
                    <input type="text" name="username" id="edit_username" class="form-control" required>
                  </div>
                  <div class="col-md-6">
                    <label class="form-label">Password (biarkan kosong jika tidak diubah)</label>
                    <input type="password" name="password" class="form-control" placeholder="(opsional)">
                  </div>
                  <div class="col-md-6">
                    <label class="form-label">Tanggal Lahir</label>
                    <input type="date" name="tanggal_lahir" id="edit_tanggal_lahir" class="form-control">
                  </div>
                  <div class="col-md-6">
                    <label class="form-label">Role</label>
                    <select name="role" id="edit_role" class="form-select">
                      <option value="peserta">Peserta</option>
                      <option value="admin">Admin</option>
                    </select>
                  </div>
                  <div class="col-md-6">
                    <label class="form-label">Email</label>
                    <input type="email" name="email" id="edit_email" class="form-control">
                  </div>
                  <div class="col-md-6">
                  </div>
                  <div class="col-md-6">
                    <label class="form-label">No WA</label>
                    <input type="text" name="no_wa" id="edit_no_wa" class="form-control">
                  </div>
                  <div class="col-md-6">
                    <label class="form-label">Pendidikan Terakhir</label>
                    <select name="pendidikan_terakhir" id="edit_pendidikan_terakhir" class="form-select">
                      <option value="">-- Pilih --</option>
                      <option value="SMP">SMP</option>
                      <option value="SMA/SMK">SMA/SMK</option>
                      <option value="D3">D3</option>
                      <option value="S1">S1</option>
                      <option value="S2">S2</option>
                      <option value="S3">S3</option>
                    </select>
                  </div>
                  
                  <!-- Cascading Dropdown Wilayah -->
                  <div class="col-md-6">
                    <label class="form-label">Provinsi <span class="text-danger">*</span></label>
                    <select name="id_provinsi" id="edit_provinsi" class="form-select" required>
                      <option value="">-- Pilih Provinsi --</option>
                    </select>
                  </div>
                  <div class="col-md-6">
                    <label class="form-label">Kabupaten/Kota <span class="text-danger">*</span></label>
                    <select name="id_kabupaten" id="edit_kabupaten" class="form-select" required>
                      <option value="">-- Pilih Kabupaten --</option>
                    </select>
                  </div>
                  <div class="col-md-6">
                    <label class="form-label">Kecamatan <span class="text-danger">*</span></label>
                    <select name="id_kecamatan" id="edit_kecamatan" class="form-select" required>
                      <option value="">-- Pilih Kecamatan --</option>
                    </select>
                  </div>
                  <div class="col-md-6">
                    <label class="form-label">Desa/Kelurahan</label>
                    <select name="id_desa" id="edit_desa" class="form-select">
                      <option value="">-- Pilih Desa --</option>
                    </select>
                  </div>
                  <div class="col-12">
                    <label class="form-label">Alamat Detail (RT/RW, Jalan, No. Rumah)</label>
                    <textarea name="alamat" id="edit_alamat" class="form-control" rows="2" placeholder="Contoh: RT 02 RW 03, Jl. Veteran No. 123"></textarea>
                  </div>
                  
                  <div class="col-md-6">
                    <label class="form-label">Status</label>
                    <select name="status" id="edit_status" class="form-select">
                      <option value="aktif">aktif</option>
                      <option value="nonaktif">nonaktif</option>
                    </select>
                  </div>
                  <div class="col-md-6">
                    <label class="form-label">Foto Profil (opsional)</label>
                    <input type="file" name="foto" class="form-control" accept="image/png,image/jpeg,image/jpg">
                  </div>
                </div>
              </div>
              <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                <button type="submit" class="btn btn-primary">Simpan Perubahan</button>
              </div>
            </form>
          </div>
        </div>
      </div>

      <!-- Modal: Hapus Pengguna -->
      <div class="modal fade" id="modalDelete" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
          <div class="modal-content">
            <div class="modal-header">
              <h5 class="modal-title">Konfirmasi Hapus</h5>
              <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="post">
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
              <input type="hidden" name="id_user" id="delete_id_user">
              <div class="modal-body">
                <p>Anda yakin ingin menghapus pengguna <strong id="delete_nama_label"></strong>?</p>
              </div>
              <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                <button type="submit" class="btn btn-danger">Hapus</button>
              </div>
            </form>
          </div>
        </div>
      </div>
    </div>
  </div>
<script>
  (function(){
    // Cetak dengan filter parameter
    document.getElementById('btnPrint')?.addEventListener('click', function(){
      const params = new URLSearchParams(window.location.search);
      const filterParams = new URLSearchParams();
      
      // Ambil parameter filter yang aktif
      if (params.get('role')) filterParams.set('role', params.get('role'));
      if (params.get('status')) filterParams.set('status', params.get('status'));
      if (params.get('jenis_kelamin')) filterParams.set('jenis_kelamin', params.get('jenis_kelamin'));
      if (params.get('kecamatan')) filterParams.set('kecamatan', params.get('kecamatan'));
      
      const url = 'cetak.php' + (filterParams.toString() ? '?' + filterParams.toString() : '');
      window.open(url, '_blank');
    });

    // Filter pencarian sederhana
    const searchInput = document.getElementById('searchInput');
    const rows = document.querySelectorAll('#usersTable tbody tr');
    searchInput?.addEventListener('input', function(){
      const q = (this.value || '').toLowerCase();
      rows.forEach(function(tr){
        const text = tr.innerText.toLowerCase();
        tr.style.display = text.indexOf(q) !== -1 ? '' : 'none';
      });
    });



    // ============================================
    // CASCADING DROPDOWN WILAYAH INDONESIA
    // ============================================
    
    // Helper function untuk load wilayah via AJAX
    async function loadWilayah(tingkat, parent, targetSelectId) {
      try {
        const url = parent 
          ? `api_wilayah.php?tingkat=${tingkat}&parent=${parent}`
          : `api_wilayah.php?tingkat=${tingkat}`;
        
        const response = await fetch(url);
        const result = await response.json();
        
        if (!result.success) {
          console.error('Error loading wilayah:', result.error);
          return [];
        }
        
        const select = document.getElementById(targetSelectId);
        if (!select) return [];
        
        // Clear existing options
        select.innerHTML = `<option value="">-- Pilih ${tingkat.charAt(0).toUpperCase() + tingkat.slice(1)} --</option>`;
        
        // Add new options
        result.data.forEach(item => {
          const option = document.createElement('option');
          option.value = item.id;
          option.textContent = item.nama;
          select.appendChild(option);
        });
        
        return result.data;
      } catch (error) {
        console.error('Error loading wilayah:', error);
        return [];
      }
    }
    
    // Helper function untuk reset dropdown child
    function resetChildDropdowns(selectIds) {
      selectIds.forEach(id => {
        const select = document.getElementById(id);
        if (select) {
          select.innerHTML = '<option value="">-- Pilih --</option>';
        }
      });
    }
    
    // ============================================
    // MODAL CREATE - Cascading Dropdown
    // ============================================
    
    // Load provinsi saat page load
    document.addEventListener('DOMContentLoaded', function() {
      console.log('DOMContentLoaded - Loading provinsi...');
      loadWilayah('provinsi', null, 'provinsi');
      loadWilayah('provinsi', null, 'edit_provinsi');
    });
    
    // TAMBAHAN: Load provinsi saat modal CREATE dibuka (Bootstrap event)
    const modalCreate = document.getElementById('modalCreate');
    if (modalCreate) {
      modalCreate.addEventListener('shown.bs.modal', function() {
        console.log('Modal CREATE opened - Loading provinsi...');
        loadWilayah('provinsi', null, 'provinsi');
      });
    }
    
    // TAMBAHAN: Load provinsi saat modal EDIT dibuka (Bootstrap event)
    const modalEdit = document.getElementById('modalEdit');
    if (modalEdit) {
      modalEdit.addEventListener('shown.bs.modal', function() {
        console.log('Modal EDIT opened - Loading provinsi...');
        loadWilayah('provinsi', null, 'edit_provinsi');
      });
    }
    
    // Provinsi change -> Load Kabupaten
    document.getElementById('provinsi')?.addEventListener('change', async function() {
      const provId = this.value;
      resetChildDropdowns(['kabupaten', 'kecamatan', 'desa']);
      
      if (provId) {
        await loadWilayah('kabupaten', provId, 'kabupaten');
      }
    });
    
    // Kabupaten change -> Load Kecamatan
    document.getElementById('kabupaten')?.addEventListener('change', async function() {
      const kabId = this.value;
      resetChildDropdowns(['kecamatan', 'desa']);
      
      if (kabId) {
        await loadWilayah('kecamatan', kabId, 'kecamatan');
      }
    });
    
    // Kecamatan change -> Load Desa
    document.getElementById('kecamatan')?.addEventListener('change', async function() {
      const kecId = this.value;
      resetChildDropdowns(['desa']);
      
      if (kecId) {
        await loadWilayah('desa', kecId, 'desa');
      }
    });
    
    // ============================================
    // MODAL EDIT - Cascading Dropdown
    // ============================================
    
    // Edit Provinsi change -> Load Kabupaten
    document.getElementById('edit_provinsi')?.addEventListener('change', async function() {
      const provId = this.value;
      resetChildDropdowns(['edit_kabupaten', 'edit_kecamatan', 'edit_desa']);
      
      if (provId) {
        await loadWilayah('kabupaten', provId, 'edit_kabupaten');
      }
    });
    
    // Edit Kabupaten change -> Load Kecamatan
    document.getElementById('edit_kabupaten')?.addEventListener('change', async function() {
      const kabId = this.value;
      resetChildDropdowns(['edit_kecamatan', 'edit_desa']);
      
      if (kabId) {
        await loadWilayah('kecamatan', kabId, 'edit_kecamatan');
      }
    });
    
    // Edit Kecamatan change -> Load Desa
    document.getElementById('edit_kecamatan')?.addEventListener('change', async function() {
      const kecId = this.value;
      resetChildDropdowns(['edit_desa']);
      
      if (kecId) {
        await loadWilayah('desa', kecId, 'edit_desa');
      }
    });

    // Buka modal edit dengan data baris
    document.querySelectorAll('.btn-edit').forEach(function(btn){
      btn.addEventListener('click', async function(){
        document.getElementById('edit_id_user').value = this.dataset.id;
        document.getElementById('edit_nama').value = this.dataset.nama;
        document.getElementById('edit_jk').value = this.dataset.jk || '';
        document.getElementById('edit_username').value = this.dataset.username;
        document.getElementById('edit_role').value = (this.dataset.role||'').toLowerCase();
        document.getElementById('edit_email').value = this.dataset.email || '';
        document.getElementById('edit_no_wa').value = this.dataset.noWa || '';
        document.getElementById('edit_pendidikan_terakhir').value = this.dataset.pendidikanTerakhir || '';
        document.getElementById('edit_alamat').value = this.dataset.alamat || '';
        document.getElementById('edit_status').value = (this.dataset.status||'aktif').toLowerCase();
        
        // Populate wilayah dropdowns
        const idProv = this.dataset.idProvinsi || '';
        const idKab = this.dataset.idKabupaten || '';
        const idKec = this.dataset.idKecamatan || '';
        const idDesa = this.dataset.idDesa || '';
        
        // Set provinsi
        if (idProv) {
          document.getElementById('edit_provinsi').value = idProv;
          // Load kabupaten
          await loadWilayah('kabupaten', idProv, 'edit_kabupaten');
          if (idKab) {
            document.getElementById('edit_kabupaten').value = idKab;
            // Load kecamatan
            await loadWilayah('kecamatan', idKab, 'edit_kecamatan');
            if (idKec) {
              document.getElementById('edit_kecamatan').value = idKec;
              // Load desa
              await loadWilayah('desa', idKec, 'edit_desa');
              if (idDesa) {
                document.getElementById('edit_desa').value = idDesa;
              }
            }
          }
        }
        
        var modal = new bootstrap.Modal(document.getElementById('modalEdit'));
        modal.show();
      });
    });

    // Buka modal hapus
    document.querySelectorAll('.btn-delete').forEach(function(btn){
      btn.addEventListener('click', function(){
        document.getElementById('delete_id_user').value = this.dataset.id;
        document.getElementById('delete_nama_label').innerText = this.dataset.nama || '';
        var modal = new bootstrap.Modal(document.getElementById('modalDelete'));
        modal.show();
      });
    });
  })();
</script>
<?php include_once __DIR__ . '/../../includes/footer.php'; ?>
