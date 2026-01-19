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

$page_title = 'Pelatihan';
require_once __DIR__ . '/../../config/koneksi.php';

// CSRF token sederhana untuk operasi POST
if (empty($_SESSION['csrf_token'])) {
  $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Ambil daftar bidang untuk pilihan select di form
$bidang_list = [];
try {
  $stmtBid = $pdo->query("SELECT id_bidang, nama_bidang FROM tb_bidang ORDER BY nama_bidang ASC");
  $bidang_list = $stmtBid->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
  $bidang_list = [];
}

// Helper upload foto ke assets/images/products
function upload_foto_product(array $file): ?string {
  if (empty($file['name']) || $file['error'] !== UPLOAD_ERR_OK) return null;
  $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
  $type = $file['type'] ?? '';
  if (!isset($allowed[$type])) return null;
  $ext = $allowed[$type];
  $base = preg_replace('/[^a-zA-Z0-9_-]/', '-', pathinfo($file['name'], PATHINFO_FILENAME));
  $targetName = $base . '-' . time() . '.' . $ext;
  $targetDir = __DIR__ . '/../../assets/images/products/';
  if (!is_dir($targetDir)) { @mkdir($targetDir, 0777, true); }
  $targetPath = $targetDir . $targetName;
  if (move_uploaded_file($file['tmp_name'], $targetPath)) {
    return $targetName; // simpan nama file saja di DB
  }
  return null;
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
        $nama = trim($_POST['nama_pelatihan'] ?? '');
        $deskripsi = trim($_POST['deskripsi'] ?? '');
        $mulai = trim($_POST['tanggal_mulai'] ?? '');
        $selesai = trim($_POST['tanggal_selesai'] ?? '');
        $id_bidang = (int)($_POST['id_bidang'] ?? 0);
        $id_bidang = $id_bidang > 0 ? $id_bidang : null;
        $nama_instruktur = trim($_POST['nama_instruktur'] ?? '');
        $nama_instruktur = $nama_instruktur !== '' ? $nama_instruktur : null;
        $kategori = strtoupper(trim($_POST['kategori'] ?? ''));
        $allowedKat = ['APBD','APBN'];
        if (!in_array($kategori, $allowedKat, true)) { $kategori = null; }
        $lokasi = strtoupper(trim($_POST['lokasi'] ?? ''));
        $allowedLok = ['BLK','DESA','KECAMATAN'];
        if (!in_array($lokasi, $allowedLok, true)) { $lokasi = null; }
        $status = strtolower(trim($_POST['status'] ?? 'nonaktif'));
        if ($nama === '') { throw new Exception('Nama pelatihan wajib diisi.'); }
        $fotoName = null;
        if (!empty($_FILES['foto'])) { $fotoName = upload_foto_product($_FILES['foto']) ?: null; }
        $stmt = $pdo->prepare("INSERT INTO tb_pelatihan (id_bidang, nama_pelatihan, nama_instruktur, deskripsi, tanggal_mulai, tanggal_selesai, kategori, lokasi, foto, status) VALUES (?,?,?,?,?,?,?,?,?,?)");
        $stmt->execute([$id_bidang, $nama, $nama_instruktur, $deskripsi, $mulai ?: null, $selesai ?: null, $kategori, $lokasi, $fotoName, $status]);
        $flash_success = 'Pelatihan berhasil ditambahkan.';
      } elseif ($action === 'update') {
        $id = (int)($_POST['id_pelatihan'] ?? 0);
        $nama = trim($_POST['nama_pelatihan'] ?? '');
        $deskripsi = trim($_POST['deskripsi'] ?? '');
        $mulai = trim($_POST['tanggal_mulai'] ?? '');
        $selesai = trim($_POST['tanggal_selesai'] ?? '');
        $id_bidang = (int)($_POST['id_bidang'] ?? 0);
        $id_bidang = $id_bidang > 0 ? $id_bidang : null;
        $nama_instruktur = trim($_POST['nama_instruktur'] ?? '');
        $nama_instruktur = $nama_instruktur !== '' ? $nama_instruktur : null;
        $kategori = strtoupper(trim($_POST['kategori'] ?? ''));
        $allowedKat = ['APBD','APBN'];
        if (!in_array($kategori, $allowedKat, true)) { $kategori = null; }
        $lokasi = strtoupper(trim($_POST['lokasi'] ?? ''));
        $allowedLok = ['BLK','DESA','KECAMATAN'];
        if (!in_array($lokasi, $allowedLok, true)) { $lokasi = null; }
        $status = strtolower(trim($_POST['status'] ?? 'nonaktif'));
        if ($id <= 0 || $nama === '') { throw new Exception('ID pelatihan dan nama wajib diisi.'); }
        $fotoName = null;
        if (!empty($_FILES['foto']) && ($_FILES['foto']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
          $fotoName = upload_foto_product($_FILES['foto']) ?: null;
        }
        if ($fotoName !== null) {
          $stmt = $pdo->prepare("UPDATE tb_pelatihan SET id_bidang=?, nama_pelatihan=?, nama_instruktur=?, deskripsi=?, tanggal_mulai=?, tanggal_selesai=?, kategori=?, lokasi=?, foto=?, status=? WHERE id_pelatihan=?");
          $stmt->execute([$id_bidang, $nama, $nama_instruktur, $deskripsi, $mulai ?: null, $selesai ?: null, $kategori, $lokasi, $fotoName, $status, $id]);
        } else {
          $stmt = $pdo->prepare("UPDATE tb_pelatihan SET id_bidang=?, nama_pelatihan=?, nama_instruktur=?, deskripsi=?, tanggal_mulai=?, tanggal_selesai=?, kategori=?, lokasi=?, status=? WHERE id_pelatihan=?");
          $stmt->execute([$id_bidang, $nama, $nama_instruktur, $deskripsi, $mulai ?: null, $selesai ?: null, $kategori, $lokasi, $status, $id]);
        }
        $flash_success = 'Pelatihan berhasil diperbarui.';
      } elseif ($action === 'delete') {
        $id = (int)($_POST['id_pelatihan'] ?? 0);
        if ($id <= 0) { throw new Exception('ID pelatihan tidak valid.'); }
        $stmt = $pdo->prepare("DELETE FROM tb_pelatihan WHERE id_pelatihan = ?");
        $stmt->execute([$id]);
        $flash_success = 'Pelatihan berhasil dihapus.';
      }
    } catch (PDOException $ex) {
      $flash_error = 'Kesalahan database: ' . htmlspecialchars($ex->getMessage());
    } catch (Throwable $e) {
      $flash_error = htmlspecialchars($e->getMessage());
    }
  }
}

// Ambil data pelatihan untuk ditampilkan dengan Pagination
$pelatihans = [];
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;
$perPage = 10;
$totalRows = 0;
$totalPages = 0;
$offset = 0;

try {
  // Hitung total baris
  $countStmt = $pdo->query("SELECT COUNT(*) FROM tb_pelatihan");
  $totalRows = (int)$countStmt->fetchColumn();

  $totalPages = ceil($totalRows / $perPage);
  if ($page > $totalPages && $totalPages > 0) $page = $totalPages;
  
  $offset = ($page - 1) * $perPage;

  $stmt = $pdo->prepare("SELECT t.id_pelatihan, t.id_bidang, b.nama_bidang, t.nama_pelatihan, t.nama_instruktur, t.deskripsi, t.tanggal_mulai, t.tanggal_selesai, t.kategori, t.lokasi, t.foto, t.status
                       FROM tb_pelatihan t
                       LEFT JOIN tb_bidang b ON b.id_bidang = t.id_bidang
                       ORDER BY t.id_pelatihan DESC
                       LIMIT :limit OFFSET :offset");
  $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
  $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
  $stmt->execute();
  $pelatihans = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) { 
  $pelatihans = []; 
  $flash_error = "Gagal memuat data: " . htmlspecialchars($e->getMessage());
}

$selected_pelatihan = null;
$selected_peserta = [];
$selectedPelatihanId = isset($_GET['pelatihan_id']) ? (int)$_GET['pelatihan_id'] : 0;
if ($selectedPelatihanId > 0) {
  try {
    $stInfo = $pdo->prepare("SELECT id_pelatihan, nama_pelatihan FROM tb_pelatihan WHERE id_pelatihan = ?");
    $stInfo->execute([$selectedPelatihanId]);
    $selected_pelatihan = $stInfo->fetch(PDO::FETCH_ASSOC) ?: null;
    if ($selected_pelatihan) {
      $stPes = $pdo->prepare("SELECT p.id_pendaftaran, p.tanggal_daftar, p.status, u.nama_lengkap, u.username, u.no_wa, u.email FROM tb_pendaftaran p JOIN tb_user u ON p.id_user = u.id_user WHERE p.id_pelatihan = ? ORDER BY p.tanggal_daftar DESC");
      $stPes->execute([$selectedPelatihanId]);
      $selected_peserta = $stPes->fetchAll(PDO::FETCH_ASSOC);
    }
  } catch (Throwable $e) {
    $selected_pelatihan = null;
    $selected_peserta = [];
  }
}

include_once __DIR__ . '/../../includes/header.php';
include_once __DIR__ . '/../../includes/sidebar.php';
?>
    <div class="body-wrapper">
      <?php include_once __DIR__ . '/../../includes/navbar.php'; ?>

      <div class="container-fluid">
        <div class="d-flex justify-content-between align-items-center mb-3">
          <h5 class="card-title mb-0 fw-semibold">Pelatihan</h5>
          <div class="d-flex gap-2">
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalCreate"><i class="ti ti-notebook me-1"></i> Tambah</button>
            <button id="btnPrint" class="btn btn-outline-secondary"><i class="ti ti-printer me-1"></i> Cetak</button>
          </div>
        </div>

        <?php if ($flash_success) { ?>
          <div class="alert alert-success" role="alert"><?php echo htmlspecialchars($flash_success); ?></div>
        <?php } elseif ($flash_error) { ?>
          <div class="alert alert-danger" role="alert"><?php echo htmlspecialchars($flash_error); ?></div>
        <?php } ?>

        <?php if (!empty($selected_pelatihan)) { ?>
          <div class="card mb-3" id="pesertaPelatihan">
            <div class="card-body">
              <h6 class="card-title fw-semibold mb-2">Peserta Pelatihan: <?php echo htmlspecialchars($selected_pelatihan['nama_pelatihan']); ?></h6>
              <?php if (empty($selected_peserta)) { ?>
                <p class="text-muted mb-0">Belum ada peserta yang terdaftar pada pelatihan ini.</p>
              <?php } else { ?>
                <div class="table-responsive">
                  <table class="table table-sm align-middle">
                    <thead>
                      <tr>
                        <th>Nama</th>
                        <th>Username</th>
                        <th>Tanggal Daftar</th>
                        <th>Status</th>
                        <th>Kontak</th>
                      </tr>
                    </thead>
                    <tbody>
                      <?php foreach ($selected_peserta as $sp) { ?>
                        <tr>
                          <td><?php echo htmlspecialchars($sp['nama_lengkap'] ?? ''); ?></td>
                          <td><?php echo htmlspecialchars($sp['username'] ?? ''); ?></td>
                          <td><?php echo htmlspecialchars($sp['tanggal_daftar'] ?? ''); ?></td>
                          <td><?php echo htmlspecialchars($sp['status'] ?? ''); ?></td>
                          <td>
                            <?php
                              $kontakParts = [];
                              if (!empty($sp['no_wa'])) { $kontakParts[] = 'WA: ' . $sp['no_wa']; }
                              if (!empty($sp['email'])) { $kontakParts[] = $sp['email']; }
                              echo htmlspecialchars(!empty($kontakParts) ? implode(' | ', $kontakParts) : '-');
                            ?>
                          </td>
                        </tr>
                      <?php } ?>
                    </tbody>
                  </table>
                </div>
              <?php } ?>
            </div>
          </div>
        <?php } ?>

        <div class="card">
          <div class="card-body">
            <div class="d-sm-flex d-block align-items-center justify-content-between mb-3">
              <div class="mb-2 mb-sm-0">
              </div>
              <div class="input-group" style="max-width: 320px;">
                <span class="input-group-text"><i class="ti ti-search"></i></span>
                <input type="text" id="searchInput" class="form-control" placeholder="Cari nama atau status pelatihan...">
              </div>
            </div>
            <div class="table-responsive">
              <table class="table align-middle text-nowrap" id="pelatihanTable">
                <thead class="text-dark fs-4">
                  <tr>
                    <th><h6 class="fw-semibold mb-0">No</h6></th>
                    <th><h6 class="fw-semibold mb-0">Nama Pelatihan</h6></th>
                    <th><h6 class="fw-semibold mb-0">Bidang</h6></th>
                    <th><h6 class="fw-semibold mb-0">Instruktur</h6></th>
                    <th><h6 class="fw-semibold mb-0">Kategori</h6></th>
                    <th><h6 class="fw-semibold mb-0">Lokasi</h6></th>
                    <th><h6 class="fw-semibold mb-0">Status</h6></th>
                    <th><h6 class="fw-semibold mb-0">Mulai</h6></th>
                    <th><h6 class="fw-semibold mb-0">Selesai</h6></th>
                    
                    <th class="text-end"><h6 class="fw-semibold mb-0">Aksi</h6></th>
                  </tr>
                </thead>
                <tbody>
                  <?php if (!empty($pelatihans)) { $no = $offset + 1; foreach ($pelatihans as $p) { ?>
                    <tr>
                      <td><?php echo $no++; ?></td>
                      <td><?php echo htmlspecialchars($p['nama_pelatihan']); ?></td>
                      <td><?php echo htmlspecialchars($p['nama_bidang'] ?? ''); ?></td>
                      <td><?php echo htmlspecialchars($p['nama_instruktur'] ?? ''); ?></td>
                      <td><?php echo htmlspecialchars($p['kategori'] ?? ''); ?></td>
                      <td><?php echo htmlspecialchars($p['lokasi'] ?? ''); ?></td>
                      <td>
                        <?php if (($p['status'] ?? '') === 'aktif') { ?>
                          <span class="badge bg-success">aktif</span>
                        <?php } else { ?>
                          <span class="badge bg-secondary">nonaktif</span>
                        <?php } ?>
                      </td>
                      <td><?php echo htmlspecialchars($p['tanggal_mulai'] ?? ''); ?></td>
                      <td><?php echo htmlspecialchars($p['tanggal_selesai'] ?? ''); ?></td>
                      <td class="text-end">
                        <a href="pelatihan.php?pelatihan_id=<?php echo (int)$p['id_pelatihan']; ?>" class="btn btn-sm btn-outline-secondary me-1">
                          Peserta
                        </a>
                        <button class="btn btn-sm btn-outline-primary me-1 btn-edit"
                          data-id="<?php echo (int)$p['id_pelatihan']; ?>"
                          data-bidang="<?php echo (int)($p['id_bidang'] ?? 0); ?>"
                          data-nama="<?php echo htmlspecialchars($p['nama_pelatihan']); ?>"
                          data-instruktur="<?php echo htmlspecialchars($p['nama_instruktur'] ?? ''); ?>"
                          data-deskripsi="<?php echo htmlspecialchars($p['deskripsi'] ?? ''); ?>"
                          data-mulai="<?php echo htmlspecialchars($p['tanggal_mulai'] ?? ''); ?>"
                          data-selesai="<?php echo htmlspecialchars($p['tanggal_selesai'] ?? ''); ?>"
                          data-kategori="<?php echo htmlspecialchars($p['kategori'] ?? ''); ?>"
                          data-lokasi="<?php echo htmlspecialchars($p['lokasi'] ?? ''); ?>"
                          data-status="<?php echo htmlspecialchars($p['status'] ?? 'nonaktif'); ?>">
                          <i class="ti ti-edit"></i>
                          Edit
                        </button>
                        <button class="btn btn-sm btn-outline-danger btn-delete"
                          data-id="<?php echo (int)$p['id_pelatihan']; ?>"
                          data-nama="<?php echo htmlspecialchars($p['nama_pelatihan']); ?>">
                          <i class="ti ti-trash"></i>
                          Hapus
                        </button>
                      </td>
                    </tr>
                  <?php } } else { ?>
                    <tr>
                    <td colspan="10" class="text-center">Tidak ada data pelatihan.</td>
                    </tr>
                  <?php } ?>
                </tbody>
              </table>
            </div>

            <!-- Pagination -->
            <?php if ($totalRows > 0): ?>
            <div class="d-flex align-items-center justify-content-between mt-3">
              <div class="text-muted small">
                Menampilkan <?php echo $offset + 1; ?>–<?php echo min($offset + $perPage, $totalRows); ?> dari <?php echo $totalRows; ?> data
              </div>
              <nav>
                <ul class="pagination pagination-sm mb-0">
                  <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                    <a class="page-link" href="?page=<?php echo $page - 1; ?>">Prev</a>
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
                      echo '<li class="page-item ' . $active . '"><a class="page-link" href="?page=' . $p . '">' . $p . '</a></li>';
                    }
                  ?>
                  <li class="page-item <?php echo $page >= $totalPages ? 'disabled' : ''; ?>">
                    <a class="page-link" href="?page=<?php echo $page + 1; ?>">Next</a>
                  </li>
                </ul>
              </nav>
            </div>
            <?php endif; ?>

          </div>
        </div>

        <!-- Modal: Tambah Pelatihan -->
        <div class="modal fade" id="modalCreate" tabindex="-1" aria-hidden="true">
          <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
              <div class="modal-header">
                <h5 class="modal-title">Tambah Pelatihan</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
              </div>
              <form method="post" enctype="multipart/form-data">
                <input type="hidden" name="action" value="create">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                <div class="modal-body">
                  <div class="row g-3">
                    <div class="col-md-6">
                      <label class="form-label">Nama Pelatihan</label>
                      <input type="text" name="nama_pelatihan" class="form-control" required>
                    </div>
                    <div class="col-md-6">
                      <label class="form-label">Status</label>
                      <select name="status" class="form-select">
                        <option value="aktif">aktif</option>
                        <option value="nonaktif">nonaktif</option>
                      </select>
                    </div>
                    <div class="col-md-6">
                      <label class="form-label">Bidang</label>
                      <select name="id_bidang" class="form-select">
                        <option value="">— Pilih Bidang —</option>
                        <?php foreach ($bidang_list as $b) { ?>
                          <option value="<?php echo (int)$b['id_bidang']; ?>"><?php echo htmlspecialchars($b['nama_bidang']); ?></option>
                        <?php } ?>
                      </select>
                    </div>
                    <div class="col-md-6">
                      <label class="form-label">Nama Instruktur</label>
                      <input type="text" name="nama_instruktur" class="form-control" placeholder="Nama instruktur (opsional)">
                    </div>
                    <div class="col-12">
                      <label class="form-label">Deskripsi</label>
                      <textarea name="deskripsi" class="form-control" rows="3"></textarea>
                    </div>
                    <div class="col-md-6">
                      <label class="form-label">Tanggal Mulai</label>
                      <input type="date" name="tanggal_mulai" class="form-control">
                    </div>
                    <div class="col-md-6">
                      <label class="form-label">Tanggal Selesai</label>
                      <input type="date" name="tanggal_selesai" class="form-control">
                    </div>
                    <div class="col-md-6">
                      <label class="form-label">Kategori</label>
                      <select name="kategori" class="form-select">
                        <option value="">— Pilih —</option>
                        <option value="APBD">APBD</option>
                        <option value="APBN">APBN</option>
                      </select>
                    </div>
                    <div class="col-md-6">
                      <label class="form-label">Lokasi</label>
                      <select name="lokasi" class="form-select">
                        <option value="">— Pilih —</option>
                        <option value="BLK">BLK</option>
                        <option value="DESA">Desa</option>
                        <option value="KECAMATAN">Kecamatan</option>
                      </select>
                    </div>
                    <div class="col-md-6">
                      <label class="form-label">Foto (opsional)</label>
                      <input type="file" name="foto" class="form-control" accept="image/*">
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

        <!-- Modal: Edit Pelatihan -->
        <div class="modal fade" id="modalEdit" tabindex="-1" aria-hidden="true">
          <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
              <div class="modal-header">
                <h5 class="modal-title">Edit Pelatihan</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
              </div>
              <form method="post" enctype="multipart/form-data">
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                <input type="hidden" name="id_pelatihan" id="edit_id_pelatihan">
                <div class="modal-body">
                  <div class="row g-3">
                    <div class="col-md-6">
                      <label class="form-label">Nama Pelatihan</label>
                      <input type="text" name="nama_pelatihan" id="edit_nama" class="form-control" required>
                    </div>
                    <div class="col-md-6">
                      <label class="form-label">Status</label>
                      <select name="status" id="edit_status" class="form-select">
                        <option value="aktif">aktif</option>
                        <option value="nonaktif">nonaktif</option>
                      </select>
                    </div>
                    <div class="col-md-6">
                      <label class="form-label">Bidang</label>
                      <select name="id_bidang" id="edit_bidang" class="form-select">
                        <option value="">— Pilih Bidang —</option>
                        <?php foreach ($bidang_list as $b) { ?>
                          <option value="<?php echo (int)$b['id_bidang']; ?>"><?php echo htmlspecialchars($b['nama_bidang']); ?></option>
                        <?php } ?>
                      </select>
                    </div>
                    <div class="col-md-6">
                      <label class="form-label">Nama Instruktur</label>
                      <input type="text" name="nama_instruktur" id="edit_nama_instruktur" class="form-control">
                    </div>
                    <div class="col-12">
                      <label class="form-label">Deskripsi</label>
                      <textarea name="deskripsi" id="edit_deskripsi" class="form-control" rows="3"></textarea>
                    </div>
                    <div class="col-md-6">
                      <label class="form-label">Tanggal Mulai</label>
                      <input type="date" name="tanggal_mulai" id="edit_mulai" class="form-control">
                    </div>
                    <div class="col-md-6">
                      <label class="form-label">Tanggal Selesai</label>
                      <input type="date" name="tanggal_selesai" id="edit_selesai" class="form-control">
                    </div>
                    <div class="col-md-6">
                      <label class="form-label">Kategori</label>
                      <select name="kategori" id="edit_kategori" class="form-select">
                        <option value="">— Pilih —</option>
                        <option value="APBD">APBD</option>
                        <option value="APBN">APBN</option>
                      </select>
                    </div>
                    <div class="col-md-6">
                      <label class="form-label">Lokasi</label>
                      <select name="lokasi" id="edit_lokasi" class="form-select">
                        <option value="">— Pilih —</option>
                        <option value="BLK">BLK</option>
                        <option value="DESA">Desa</option>
                        <option value="KECAMATAN">Kecamatan</option>
                      </select>
                    </div>
                    <div class="col-md-6">
                      <label class="form-label">Foto (opsional, unggah untuk mengganti)</label>
                      <input type="file" name="foto" class="form-control" accept="image/*">
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

        <!-- Modal: Hapus Pelatihan -->
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
                <input type="hidden" name="id_pelatihan" id="delete_id_pelatihan">
                <div class="modal-body">
                  <p>Anda yakin ingin menghapus pelatihan <strong id="delete_nama_label"></strong>?</p>
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
    // Cetak dialihkan ke halaman khusus cetak.php
    document.getElementById('btnPrint')?.addEventListener('click', function(){
      window.open('cetak.php', '_blank');
    });

    // Filter pencarian sederhana
    const searchInput = document.getElementById('searchInput');
    const rows = document.querySelectorAll('#pelatihanTable tbody tr');
    searchInput?.addEventListener('input', function(){
      const q = (this.value || '').toLowerCase();
      rows.forEach(function(tr){
        const text = tr.innerText.toLowerCase();
        tr.style.display = text.indexOf(q) !== -1 ? '' : 'none';
      });
    });

    // Buka modal edit dengan data baris
    document.querySelectorAll('.btn-edit').forEach(function(btn){
      btn.addEventListener('click', function(){
        document.getElementById('edit_id_pelatihan').value = this.dataset.id;
        document.getElementById('edit_bidang').value = this.dataset.bidang || '';
        document.getElementById('edit_nama').value = this.dataset.nama;
        document.getElementById('edit_nama_instruktur').value = this.dataset.instruktur || '';
        document.getElementById('edit_deskripsi').value = this.dataset.deskripsi || '';
        document.getElementById('edit_mulai').value = this.dataset.mulai || '';
        document.getElementById('edit_selesai').value = this.dataset.selesai || '';
        document.getElementById('edit_kategori').value = this.dataset.kategori || '';
        document.getElementById('edit_lokasi').value = this.dataset.lokasi || '';
        document.getElementById('edit_status').value = (this.dataset.status||'nonaktif').toLowerCase();
        var modal = new bootstrap.Modal(document.getElementById('modalEdit'));
        modal.show();
      });
    });

    // Buka modal hapus
    document.querySelectorAll('.btn-delete').forEach(function(btn){
      btn.addEventListener('click', function(){
        document.getElementById('delete_id_pelatihan').value = this.dataset.id;
        document.getElementById('delete_nama_label').innerText = this.dataset.nama || '';
        var modal = new bootstrap.Modal(document.getElementById('modalDelete'));
        modal.show();
      });
    });
  })();
</script>
<?php include_once __DIR__ . '/../../includes/footer.php'; ?>
