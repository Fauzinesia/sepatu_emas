<?php
session_start();
require_once __DIR__ . '/config/koneksi.php';

$errors = [];
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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nama_lengkap = trim($_POST['nama_lengkap'] ?? '');
    $jenis_kelamin = trim($_POST['jenis_kelamin'] ?? '');
    $username     = trim($_POST['username'] ?? '');
    $password     = $_POST['password'] ?? '';
    $email        = trim($_POST['email'] ?? '');
    $pendidikan_terakhir = trim($_POST['pendidikan_terakhir'] ?? '');
    $no_wa        = trim($_POST['no_wa'] ?? '');
    $alamat       = trim($_POST['alamat'] ?? '');
    $id_provinsi  = trim($_POST['id_provinsi'] ?? '');
    $id_kabupaten = trim($_POST['id_kabupaten'] ?? '');
    $id_kecamatan = trim($_POST['id_kecamatan'] ?? '');
    $id_desa      = trim($_POST['id_desa'] ?? '');
    $nik = preg_replace('/\D+/', '', $_POST['nik'] ?? '');
    $tempat_lahir = trim($_POST['tempat_lahir'] ?? '');
    $tanggal_lahir_raw = trim($_POST['tanggal_lahir'] ?? '');
    $tanggal_lahir = $tanggal_lahir_raw !== '' ? $tanggal_lahir_raw : null;

    if ($nama_lengkap === '' || $username === '' || $password === '') {
        $errors[] = 'Nama lengkap, username, dan password wajib diisi.';
    }
    if ($hasNik && $nik !== '' && strlen($nik) !== 16) {
        $errors[] = 'NIK harus 16 digit.';
    }
    if ($hasTanggalLahir && $tanggal_lahir !== null && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $tanggal_lahir)) {
        $errors[] = 'Tanggal lahir tidak valid.';
    }

    if (empty($errors)) {
        try {
            // Cek username unik
            $cek = $pdo->prepare('SELECT 1 FROM tb_user WHERE username = ? LIMIT 1');
            $cek->execute([$username]);
            if ($cek->fetchColumn()) {
                $errors[] = 'Username sudah digunakan, silakan pilih yang lain.';
            } else {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $cols = "nama_lengkap, jenis_kelamin, username, password, role, email, no_wa, pendidikan_terakhir, alamat, id_provinsi, id_kabupaten, id_kecamatan, id_desa";
                $vals = [$nama_lengkap, $jenis_kelamin ?: null, $username, $hash, 'peserta', $email ?: null, $no_wa ?: null, $pendidikan_terakhir ?: null, $alamat ?: null, $id_provinsi ?: null, $id_kabupaten ?: null, $id_kecamatan ?: null, $id_desa ?: null];
                if ($hasNik) { $cols .= ", nik"; $vals[] = ($nik !== '' ? $nik : null); }
                if ($hasTempatLahir) { $cols .= ", tempat_lahir"; $vals[] = ($tempat_lahir !== '' ? $tempat_lahir : null); }
                if ($hasTanggalLahir) { $cols .= ", tanggal_lahir"; $vals[] = $tanggal_lahir; }
                $sql = "INSERT INTO tb_user ($cols, status, created_at) VALUES (" . implode(',', array_fill(0, count($vals), '?')) . ", \"nonaktif\", NOW())";
                $ins = $pdo->prepare($sql);
                $ins->execute($vals);
                $_SESSION['success'] = 'Registrasi berhasil. Akun Anda akan diverifikasi admin sebelum bisa login.';
                header('Location: login.php');
                exit;
            }
        } catch (Throwable $e) {
            $errors[] = 'Terjadi kesalahan saat registrasi.';
        }
    }
}
?>
<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Daftar — SEPATU EMAS</title>
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
    <div class="container" style="max-width:600px;">
      <div class="card shadow-sm">
        <div class="card-body p-4">
          <div class="text-center mb-3">
            <img src="assets/images/logos/sepatu-emas.png" alt="SEPATU EMAS" width="72" height="72">
          </div>
          <h1 class="h4 text-center brand-gradient mb-2">Buat Akun Peserta</h1>
          <p class="text-center text-muted mb-3">Daftar untuk mengikuti program pelatihan.</p>
          <?php if (!empty($errors)): ?>
            <div class="alert alert-danger" role="alert">
              <?php foreach ($errors as $err) { echo '<div>'.htmlspecialchars($err).'</div>'; } ?>
            </div>
          <?php endif; ?>
          <form method="post" action="register.php" novalidate>
            <div class="row g-3">
              <div class="col-md-6">
                <label for="nama_lengkap" class="form-label">Nama Lengkap</label>
                <input type="text" class="form-control" id="nama_lengkap" name="nama_lengkap" required>
              </div>
              <div class="col-md-6">
                <label for="jenis_kelamin" class="form-label">Jenis Kelamin</label>
                <select class="form-select" id="jenis_kelamin" name="jenis_kelamin">
                  <option value="">-- Pilih --</option>
                  <option value="Laki-laki">Laki-laki</option>
                  <option value="Perempuan">Perempuan</option>
                </select>
              </div>
              <div class="col-md-6">
                <label for="nik" class="form-label">NIK</label>
                <input type="text" class="form-control" id="nik" name="nik" maxlength="16" pattern="\d{16}" placeholder="16 digit">
              </div>
              <div class="col-md-6">
                <label for="tempat_lahir" class="form-label">Tempat Lahir</label>
                <input type="text" class="form-control" id="tempat_lahir" name="tempat_lahir">
              </div>
              <div class="col-md-6">
                <label for="username" class="form-label">Username</label>
                <input type="text" class="form-control" id="username" name="username" required>
              </div>
              <div class="col-md-6">
                <label for="email" class="form-label">Email (opsional)</label>
                <input type="email" class="form-control" id="email" name="email">
              </div>
              <div class="col-md-6">
                <label for="tanggal_lahir" class="form-label">Tanggal Lahir</label>
                <input type="date" class="form-control" id="tanggal_lahir" name="tanggal_lahir">
              </div>
              <div class="col-md-6">
                <label for="no_wa" class="form-label">No. WA</label>
                <input type="text" class="form-control" id="no_wa" name="no_wa">
              </div>
              
              <!-- Cascading Dropdown Wilayah -->
              <div class="col-md-6">
                <label for="provinsi" class="form-label">Provinsi <span class="text-danger">*</span></label>
                <select class="form-select" id="provinsi" name="id_provinsi" required>
                  <option value="">-- Pilih Provinsi --</option>
                </select>
              </div>
              <div class="col-md-6">
                <label for="kabupaten" class="form-label">Kabupaten/Kota <span class="text-danger">*</span></label>
                <select class="form-select" id="kabupaten" name="id_kabupaten" required>
                  <option value="">-- Pilih Kabupaten --</option>
                </select>
              </div>
              <div class="col-md-6">
                <label for="kecamatan" class="form-label">Kecamatan <span class="text-danger">*</span></label>
                <select class="form-select" id="kecamatan" name="id_kecamatan" required>
                  <option value="">-- Pilih Kecamatan --</option>
                </select>
              </div>
              <div class="col-md-6">
                <label for="desa" class="form-label">Desa/Kelurahan</label>
                <select class="form-select" id="desa" name="id_desa">
                  <option value="">-- Pilih Desa --</option>
                </select>
              </div>

              <div class="col-md-6">
                <label for="pendidikan_terakhir" class="form-label">Pendidikan Terakhir</label>
                <select class="form-select" id="pendidikan_terakhir" name="pendidikan_terakhir">
                  <option value="">-- Pilih --</option>
                  <option value="SMP">SMP</option>
                  <option value="SMA/SMK">SMA/SMK</option>
                  <option value="D3">D3</option>
                  <option value="S1">S1</option>
                  <option value="S2">S2</option>
                  <option value="S3">S3</option>
                </select>
              </div>
              <div class="col-md-6">
                <label for="alamat" class="form-label">Alamat Detail (RT/RW, Jalan, No. Rumah)</label>
                <textarea class="form-control" id="alamat" name="alamat" rows="2" placeholder="Contoh: RT 02 RW 03, Jl. Veteran No. 123"></textarea>
              </div>
              <div class="col-12">
                <label for="password" class="form-label">Password</label>
                <input type="password" class="form-control" id="password" name="password" required>
              </div>
            </div>
            <button type="submit" class="btn btn-primary w-100 mt-3">Daftar</button>
          </form>
          <div class="text-center mt-3">
            <span class="text-muted">Sudah punya akun?</span>
            <a href="login.php" class="fw-semibold">Login</a>
          </div>
          <div class="text-center mt-2"><a href="index.php" class="text-decoration-none">Kembali ke Beranda</a></div>
        </div>
      </div>
    </div>
  </div>
  <script src="assets/libs/bootstrap/dist/js/bootstrap.bundle.min.js"></script>
  <script>
    // ============================================
    // CASCADING DROPDOWN WILAYAH INDONESIA
    // ============================================
    
    // Helper function untuk load wilayah via AJAX
    async function loadWilayah(tingkat, parent, targetSelectId) {
      try {
        const url = parent 
          ? `admin/pengguna/api_wilayah.php?tingkat=${tingkat}&parent=${parent}`
          : `admin/pengguna/api_wilayah.php?tingkat=${tingkat}`;
        
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
    
    // Load provinsi saat page load
    document.addEventListener('DOMContentLoaded', function() {
      loadWilayah('provinsi', null, 'provinsi');
    });
    
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
  </script>
  </body>
</html>
