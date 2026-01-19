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

require_once __DIR__ . '/../../config/koneksi.php';

$page_title = 'Bidang';

// CSRF token sederhana
if (empty($_SESSION['csrf_token'])) {
  $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Flash messages
$flash_success = '';
$flash_error = '';

// Handle POST (create/update/delete) menggunakan satu form
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $action = $_POST['action'] ?? '';
  $csrf = $_POST['csrf_token'] ?? '';
  if (!hash_equals($_SESSION['csrf_token'], $csrf)) {
    $flash_error = 'Token tidak valid. Silakan muat ulang halaman.';
  } else {
    try {
      if ($action === 'create') {
        $nama_bidang = trim($_POST['nama_bidang'] ?? '');
        $deskripsi = trim($_POST['deskripsi'] ?? '');
        if ($nama_bidang === '') {
          throw new Exception('Nama bidang wajib diisi.');
        }
        $stmt = $pdo->prepare("INSERT INTO tb_bidang (nama_bidang, deskripsi) VALUES (?, ?)");
        $stmt->execute([$nama_bidang, $deskripsi !== '' ? $deskripsi : null]);
        $flash_success = 'Bidang berhasil ditambahkan.';
      } elseif ($action === 'update') {
        $id_bidang = (int)($_POST['id_bidang'] ?? 0);
        $nama_bidang = trim($_POST['nama_bidang'] ?? '');
        $deskripsi = trim($_POST['deskripsi'] ?? '');
        if ($id_bidang <= 0 || $nama_bidang === '') {
          throw new Exception('ID bidang dan nama bidang wajib diisi.');
        }
        $stmt = $pdo->prepare("UPDATE tb_bidang SET nama_bidang=?, deskripsi=? WHERE id_bidang=?");
        $stmt->execute([$nama_bidang, $deskripsi !== '' ? $deskripsi : null, $id_bidang]);
        $flash_success = 'Bidang berhasil diperbarui.';
      } elseif ($action === 'delete') {
        $id_bidang = (int)($_POST['id_bidang'] ?? 0);
        if ($id_bidang <= 0) { throw new Exception('ID bidang tidak valid.'); }
        $stmt = $pdo->prepare("DELETE FROM tb_bidang WHERE id_bidang = ?");
        $stmt->execute([$id_bidang]);
        $flash_success = 'Bidang berhasil dihapus.';
      }
    } catch (Throwable $e) {
      $flash_error = htmlspecialchars($e->getMessage());
    }
  }
}

// Data bidang dengan Pagination
$bidang_list = [];
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;
$perPage = 10;
$totalRows = 0;
$totalPages = 0;
$offset = 0;

try {
  // Hitung total baris
  $countStmt = $pdo->query("SELECT COUNT(*) FROM tb_bidang");
  $totalRows = (int)$countStmt->fetchColumn();

  $totalPages = ceil($totalRows / $perPage);
  if ($page > $totalPages && $totalPages > 0) $page = $totalPages;
  
  $offset = ($page - 1) * $perPage;

  // Ambil data dengan LIMIT dan OFFSET
  $stmt = $pdo->prepare("SELECT id_bidang, nama_bidang, deskripsi, created_at, updated_at FROM tb_bidang ORDER BY created_at DESC LIMIT :limit OFFSET :offset");
  $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
  $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
  $stmt->execute();
  $bidang_list = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
  $bidang_list = [];
  $flash_error = "Gagal memuat data: " . htmlspecialchars($e->getMessage());
}

include_once __DIR__ . '/../../includes/header.php';
include_once __DIR__ . '/../../includes/sidebar.php';
?>
    <div class="body-wrapper">
      <?php include_once __DIR__ . '/../../includes/navbar.php'; ?>
    <div class="container-fluid">
      <div class="d-flex justify-content-between align-items-center mb-3">
        <h5 class="card-title mb-0 fw-semibold">Bidang</h5>
        <div class="d-flex gap-2">
          <button id="btnPrintBidang" class="btn btn-outline-secondary"><i class="ti ti-printer me-1"></i> Cetak</button>
        </div>
      </div>

      <?php if ($flash_success) { ?>
        <div class="alert alert-success" role="alert"><?php echo htmlspecialchars($flash_success); ?></div>
      <?php } elseif ($flash_error) { ?>
        <div class="alert alert-danger" role="alert"><?php echo htmlspecialchars($flash_error); ?></div>
      <?php } ?>

      <div class="card mb-3">
        <div class="card-body">
          <h6 class="fw-semibold mb-3" id="formTitle">Tambah Bidang</h6>
          <form id="bidangForm" method="post">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
            <input type="hidden" name="action" id="form_action" value="create">
            <input type="hidden" name="id_bidang" id="id_bidang" value="">
            <div class="row g-3">
              <div class="col-md-6">
                <label class="form-label">Nama Bidang</label>
                <input type="text" name="nama_bidang" id="nama_bidang" class="form-control" required>
              </div>
              <div class="col-md-6">
                <label class="form-label">Deskripsi</label>
                <textarea name="deskripsi" id="deskripsi" class="form-control" rows="2" placeholder="(opsional)"></textarea>
              </div>
            </div>
            <div class="mt-3 d-flex gap-2">
              <button type="submit" id="btnSubmit" class="btn btn-primary">Simpan</button>
              <button type="button" id="btnDelete" class="btn btn-danger" style="display:none;">Hapus</button>
              <button type="button" id="btnReset" class="btn btn-secondary">Reset ke Tambah</button>
            </div>
          </form>
        </div>
      </div>

      <div class="card">
        <div class="card-body">
          <div class="d-sm-flex d-block align-items-center justify-content-between mb-3">
            <div class="mb-2 mb-sm-0">
              <p class="text-muted mb-0">Daftar Bidang</p>
            </div>
            <div class="input-group" style="max-width: 280px;">
              <span class="input-group-text"><i class="ti ti-search"></i></span>
              <input type="text" id="searchBidang" class="form-control" placeholder="Cari nama atau deskripsi...">
            </div>
          </div>
          <div class="table-responsive">
            <table class="table align-middle text-nowrap" id="bidangTable">
              <thead class="text-dark fs-4">
                <tr>
                  <th><h6 class="fw-semibold mb-0">No</h6></th>
                  <th><h6 class="fw-semibold mb-0">Nama Bidang</h6></th>
                  <th><h6 class="fw-semibold mb-0">Deskripsi</h6></th>
                  <th><h6 class="fw-semibold mb-0">Dibuat</h6></th>
                  <th><h6 class="fw-semibold mb-0">Diperbarui</h6></th>
                  <th class="text-end"><h6 class="fw-semibold mb-0">Aksi</h6></th>
                </tr>
              </thead>
              <tbody>
                <?php if (!empty($bidang_list)) { $no = $offset + 1; foreach ($bidang_list as $b) { ?>
                  <tr>
                    <td><?php echo $no++; ?></td>
                    <td><?php echo htmlspecialchars($b['nama_bidang']); ?></td>
                    <td><?php echo htmlspecialchars($b['deskripsi'] ?? ''); ?></td>
                    <td><?php echo htmlspecialchars($b['created_at'] ?? ''); ?></td>
                    <td><?php echo htmlspecialchars($b['updated_at'] ?? ''); ?></td>
                    <td class="text-end">
                      <button class="btn btn-sm btn-outline-primary me-1 btn-edit-bidang"
                        data-id="<?php echo (int)$b['id_bidang']; ?>"
                        data-nama="<?php echo htmlspecialchars($b['nama_bidang']); ?>"
                        data-deskripsi="<?php echo htmlspecialchars($b['deskripsi'] ?? ''); ?>">
                        <i class="ti ti-edit"></i>
                        Edit
                      </button>
                      <button class="btn btn-sm btn-outline-danger btn-delete-bidang"
                        data-id="<?php echo (int)$b['id_bidang']; ?>"
                        data-nama="<?php echo htmlspecialchars($b['nama_bidang']); ?>">
                        <i class="ti ti-trash"></i>
                        Hapus
                      </button>
                    </td>
                  </tr>
                <?php } } else { ?>
                  <tr>
                    <td colspan="6" class="text-center">Tidak ada data bidang.</td>
                  </tr>
                <?php } ?>
              </tbody>
            </table>
            
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
      </div>
    </div>
  </div>
<script>
  (function(){
    // Cetak bidang
    document.getElementById('btnPrintBidang')?.addEventListener('click', function(){
      window.open('cetak.php', '_blank');
    });

    // Filter pencarian sederhana
    const searchInput = document.getElementById('searchBidang');
    const rows = document.querySelectorAll('#bidangTable tbody tr');
    searchInput?.addEventListener('input', function(){
      const q = (this.value || '').toLowerCase();
      rows.forEach(function(tr){
        const text = tr.innerText.toLowerCase();
        tr.style.display = text.indexOf(q) !== -1 ? '' : 'none';
      });
    });

    // Helper: set mode tambah
    function setCreateMode(){
      document.getElementById('formTitle').innerText = 'Tambah Bidang';
      document.getElementById('form_action').value = 'create';
      document.getElementById('id_bidang').value = '';
      document.getElementById('nama_bidang').value = '';
      document.getElementById('deskripsi').value = '';
      document.getElementById('btnSubmit').innerText = 'Simpan';
      document.getElementById('btnDelete').style.display = 'none';
    }

    // Helper: set mode edit
    function setEditMode(id, nama, deskripsi){
      document.getElementById('formTitle').innerText = 'Edit Bidang';
      document.getElementById('form_action').value = 'update';
      document.getElementById('id_bidang').value = id;
      document.getElementById('nama_bidang').value = nama;
      document.getElementById('deskripsi').value = deskripsi || '';
      document.getElementById('btnSubmit').innerText = 'Simpan Perubahan';
      document.getElementById('btnDelete').style.display = '';
    }

    // Edit dari tabel
    document.querySelectorAll('.btn-edit-bidang').forEach(function(btn){
      btn.addEventListener('click', function(){
        setEditMode(this.dataset.id, this.dataset.nama, this.dataset.deskripsi);
      });
    });

    // Hapus dari tabel -> pakai satu form
    document.querySelectorAll('.btn-delete-bidang').forEach(function(btn){
      btn.addEventListener('click', function(){
        const id = this.dataset.id;
        const nama = this.dataset.nama || '';
        if (confirm('Hapus bidang "' + nama + '"?')){
          document.getElementById('form_action').value = 'delete';
          document.getElementById('id_bidang').value = id;
          document.getElementById('bidangForm').submit();
        }
      });
    });

    // Tombol Hapus pada form (aktif saat edit)
    document.getElementById('btnDelete')?.addEventListener('click', function(){
      const id = document.getElementById('id_bidang').value;
      const nama = document.getElementById('nama_bidang').value;
      if (!id){ return; }
      if (confirm('Hapus bidang "' + (nama||'') + '"?')){
        document.getElementById('form_action').value = 'delete';
        document.getElementById('bidangForm').submit();
      }
    });

    // Reset ke mode tambah
    document.getElementById('btnReset')?.addEventListener('click', function(){
      setCreateMode();
    });

    // Default: mode tambah
    setCreateMode();
  })();
</script>
<?php include_once __DIR__ . '/../../includes/footer.php'; ?>