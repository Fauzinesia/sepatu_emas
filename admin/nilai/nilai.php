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

$page_title = 'Nilai';
require_once __DIR__ . '/../../config/koneksi.php';

// CSRF sederhana
if (empty($_SESSION['csrf_token'])) {
  $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Ambil daftar pelatihan
$pelatihan_list = [];
try {
  $stmtPl = $pdo->query("SELECT id_pelatihan, nama_pelatihan FROM tb_pelatihan ORDER BY id_pelatihan DESC");
  $pelatihan_list = $stmtPl->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) { $pelatihan_list = []; }

// Ambil daftar peserta (role=peserta)
$peserta_list = [];
try {
  $stmtPs = $pdo->query("SELECT id_user, nama_lengkap FROM tb_user WHERE role='peserta' ORDER BY nama_lengkap ASC");
  $peserta_list = $stmtPs->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) { $peserta_list = []; }

// Ambil pemetaan pendaftaran: pelatihan -> daftar peserta terdaftar
$map_peserta_by_pelatihan = [];
try {
  $stmtReg = $pdo->query("SELECT p.id_pelatihan, p.id_user, u.nama_lengkap
                          FROM tb_pendaftaran p
                          JOIN tb_user u ON u.id_user = p.id_user
                          LEFT JOIN tb_nilai n ON n.id_user = p.id_user AND n.id_pelatihan = p.id_pelatihan
                          WHERE n.id_nilai IS NULL AND p.status = 'diterima'");
  while ($row = $stmtReg->fetch(PDO::FETCH_ASSOC)) {
    $pid = (int)$row['id_pelatihan'];
    if (!isset($map_peserta_by_pelatihan[$pid])) $map_peserta_by_pelatihan[$pid] = [];
    $map_peserta_by_pelatihan[$pid][] = [
      'id_user' => (int)$row['id_user'],
      'nama_lengkap' => $row['nama_lengkap']
    ];
  }
} catch (Throwable $e) { $map_peserta_by_pelatihan = []; }

// Flash
$flash_success = '';
$flash_error = '';

// Handle POST (create/update/delete)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $action = $_POST['action'] ?? '';
  $csrf = $_POST['csrf_token'] ?? '';
  if (!hash_equals($_SESSION['csrf_token'], $csrf)) {
    $flash_error = 'Token tidak valid. Silakan muat ulang halaman.';
  } else {
    try {
      if ($action === 'create') {
        $id_pelatihan = (int)($_POST['id_pelatihan'] ?? 0);
        $id_user = (int)($_POST['id_user'] ?? 0);
        $hasil = strtolower(trim($_POST['hasil'] ?? ''));
        $ket = ($hasil === 'kompeten' || $hasil === 'tidak kompeten') ? ucfirst($hasil) : '';
        if ($id_pelatihan <= 0 || $id_user <= 0 || $ket === '') {
          throw new Exception('Pelatihan, Peserta, dan Hasil wajib diisi.');
        }
        // Validasi: peserta harus terdaftar pada pelatihan terpilih
        $cekReg = $pdo->prepare('SELECT 1 FROM tb_pendaftaran WHERE id_user = ? AND id_pelatihan = ? LIMIT 1');
        $cekReg->execute([$id_user, $id_pelatihan]);
        if (!$cekReg->fetchColumn()) {
          throw new Exception('Peserta belum terdaftar pada pelatihan terpilih.');
        }
        $nilaiNum = null;
        // Cegah duplikasi: jika nilai untuk (user,pelatihan) sudah ada, lakukan update
        $cek = $pdo->prepare('SELECT id_nilai FROM tb_nilai WHERE id_user = ? AND id_pelatihan = ? LIMIT 1');
        $cek->execute([$id_user, $id_pelatihan]);
        $existingId = (int)($cek->fetchColumn() ?: 0);
        if ($existingId > 0) {
          throw new Exception('Peserta sudah dinilai untuk pelatihan terpilih.');
        } else {
          $ins = $pdo->prepare('INSERT INTO tb_nilai (id_user, id_pelatihan, nilai, keterangan) VALUES (?,?,?,?)');
          $ins->execute([$id_user, $id_pelatihan, $nilaiNum, $ket]);
          $flash_success = 'Nilai peserta ditambahkan.';
        }
      } elseif ($action === 'update') {
        $id_nilai = (int)($_POST['id_nilai'] ?? 0);
        $hasil = strtolower(trim($_POST['hasil'] ?? ''));
        $ket = ($hasil === 'kompeten' || $hasil === 'tidak kompeten') ? ucfirst($hasil) : '';
        if ($id_nilai <= 0 || $ket === '') throw new Exception('ID nilai dan hasil wajib.');
        $up = $pdo->prepare('UPDATE tb_nilai SET nilai = NULL, keterangan = ? WHERE id_nilai = ?');
        $up->execute([$ket, $id_nilai]);
        $flash_success = 'Nilai berhasil diperbarui.';
      } elseif ($action === 'delete') {
        $id_nilai = (int)($_POST['id_nilai'] ?? 0);
        if ($id_nilai <= 0) throw new Exception('ID nilai tidak valid.');
        $del = $pdo->prepare('DELETE FROM tb_nilai WHERE id_nilai = ?');
        $del->execute([$id_nilai]);
        $flash_success = 'Nilai berhasil dihapus.';
      }
    } catch (PDOException $ex) {
      $flash_error = 'Kesalahan database: ' . htmlspecialchars($ex->getMessage());
    } catch (Throwable $e) {
      $flash_error = htmlspecialchars($e->getMessage());
    }
  }
}

// Ambil daftar nilai bergabung dengan user & pelatihan
$nilai_list = [];
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;
$perPage = 10;
$totalRows = 0;
$totalPages = 0;
$offset = 0;

try {
  // Hitung total baris
  $countStmt = $pdo->query("SELECT COUNT(*) FROM tb_nilai");
  $totalRows = (int)$countStmt->fetchColumn();

  $totalPages = ceil($totalRows / $perPage);
  if ($page > $totalPages && $totalPages > 0) $page = $totalPages;
  
  $offset = ($page - 1) * $perPage;

  $stmtNil = $pdo->prepare(
    "SELECT n.id_nilai, n.id_user, u.nama_lengkap, n.id_pelatihan, pl.nama_pelatihan, n.keterangan
     FROM tb_nilai n
     JOIN tb_user u ON u.id_user = n.id_user
     JOIN tb_pelatihan pl ON pl.id_pelatihan = n.id_pelatihan
     ORDER BY n.id_nilai DESC
     LIMIT :limit OFFSET :offset"
  );
  $stmtNil->bindValue(':limit', $perPage, PDO::PARAM_INT);
  $stmtNil->bindValue(':offset', $offset, PDO::PARAM_INT);
  $stmtNil->execute();
  $nilai_list = $stmtNil->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) { $nilai_list = []; }

include_once __DIR__ . '/../../includes/header.php';
include_once __DIR__ . '/../../includes/sidebar.php';
?>
    <div class="body-wrapper">
      <?php include_once __DIR__ . '/../../includes/navbar.php'; ?>
    <div class="container-fluid">
      <div class="d-flex justify-content-between align-items-center mb-3">
        <h5 class="card-title mb-0 fw-semibold">Nilai Peserta</h5>
        <div class="d-flex gap-2">
          <a class="btn btn-secondary" href="cetak.php" target="_blank"><i class="ti ti-printer me-1"></i> Cetak</a>
          <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalCreate"><i class="ti ti-pencil-plus me-1"></i> Input Nilai</button>
        </div>
      </div>

      <?php if ($flash_success) { ?>
        <div class="alert alert-success" role="alert"><?php echo htmlspecialchars($flash_success); ?></div>
      <?php } elseif ($flash_error) { ?>
        <div class="alert alert-danger" role="alert"><?php echo htmlspecialchars($flash_error); ?></div>
      <?php } ?>

      <div class="card">
        <div class="card-body">
          <div class="d-sm-flex d-block align-items-center justify-content-between mb-3">
            <div class="mb-2 mb-sm-0"></div>
            <div class="input-group" style="max-width: 320px;">
              <span class="input-group-text"><i class="ti ti-search"></i></span>
              <input type="text" id="searchInput" class="form-control" placeholder="Cari peserta/pelatihan/keterangan...">
            </div>
          </div>
          <div class="table-responsive">
            <table class="table align-middle text-nowrap" id="nilaiTable">
              <thead class="text-dark fs-4">
                <tr>
                  <th><h6 class="fw-semibold mb-0">No</h6></th>
                  <th><h6 class="fw-semibold mb-0">Peserta</h6></th>
                  <th><h6 class="fw-semibold mb-0">Pelatihan</h6></th>
                  <th><h6 class="fw-semibold mb-0">Hasil</h6></th>
                  <th class="text-end"><h6 class="fw-semibold mb-0">Aksi</h6></th>
                </tr>
              </thead>
              <tbody>
                <?php if (!empty($nilai_list)) { $no = $offset + 1; foreach ($nilai_list as $n) { ?>
                  <tr>
                    <td><?php echo $no++; ?></td>
                    <td><?php echo htmlspecialchars($n['nama_lengkap'] ?? ''); ?></td>
                    <td><?php echo htmlspecialchars($n['nama_pelatihan'] ?? ''); ?></td>
                    <td><?php echo htmlspecialchars($n['keterangan'] ?? ''); ?></td>
                    <td class="text-end">
                      <button class="btn btn-sm btn-outline-primary me-1 btn-edit"
                        data-id="<?php echo (int)$n['id_nilai']; ?>"
                        data-keterangan="<?php echo htmlspecialchars($n['keterangan'] ?? ''); ?>">
                        <i class="ti ti-edit"></i> Edit
                      </button>
                      <button class="btn btn-sm btn-outline-danger btn-delete"
                        data-id="<?php echo (int)$n['id_nilai']; ?>"
                        data-label="<?php echo htmlspecialchars(($n['nama_lengkap'] ?? '') . ' — ' . ($n['nama_pelatihan'] ?? '')); ?>">
                        <i class="ti ti-trash"></i> Hapus
                      </button>
                    </td>
                  </tr>
                <?php } } else { ?>
                  <tr>
                    <td colspan="6" class="text-center">Belum ada data nilai.</td>
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

      <!-- Modal: Input Nilai -->
      <div class="modal fade" id="modalCreate" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
          <div class="modal-content">
            <div class="modal-header">
              <h5 class="modal-title">Input Nilai Peserta</h5>
              <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="post">
              <input type="hidden" name="action" value="create">
              <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
              <div class="modal-body">
                <div class="row g-3">
                  <div class="col-md-6">
                    <label class="form-label">Pelatihan</label>
                    <select name="id_pelatihan" id="create_pelatihan" class="form-select" required>
                      <option value="">— Pilih Pelatihan —</option>
                      <?php foreach ($pelatihan_list as $pl) { ?>
                        <option value="<?php echo (int)$pl['id_pelatihan']; ?>"><?php echo htmlspecialchars($pl['nama_pelatihan']); ?></option>
                      <?php } ?>
                    </select>
                  </div>
                  <div class="col-md-6">
                    <label class="form-label">Peserta</label>
                    <select name="id_user" id="create_peserta" class="form-select" required>
                      <option value="">— Pilih Peserta —</option>
                      <?php foreach ($peserta_list as $ps) { ?>
                        <option value="<?php echo (int)$ps['id_user']; ?>"><?php echo htmlspecialchars($ps['nama_lengkap']); ?></option>
                      <?php } ?>
                    </select>
                    <div class="form-text">Disarankan memilih peserta yang terdaftar di pelatihan terpilih.</div>
                  </div>
                  <div class="col-md-6">
                    <label class="form-label">Hasil</label>
                    <select name="hasil" class="form-select" required>
                      <option value="">— Pilih Hasil —</option>
                      <option value="kompeten">Kompeten</option>
                      <option value="tidak kompeten">Tidak Kompeten</option>
                    </select>
                  </div>
                  <div class="col-md-6">
                    <label class="form-label">Keterangan (opsional)</label>
                    <input type="text" name="keterangan" class="form-control" placeholder="Catatan tambahan">
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

      <!-- Modal: Edit Nilai -->
      <div class="modal fade" id="modalEdit" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-md modal-dialog-centered">
          <div class="modal-content">
            <div class="modal-header">
              <h5 class="modal-title">Edit Nilai</h5>
              <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="post">
              <input type="hidden" name="action" value="update">
              <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
              <input type="hidden" name="id_nilai" id="edit_id_nilai">
              <div class="modal-body">
                <div class="row g-3">
                  <div class="col-md-6">
                    <label class="form-label">Hasil</label>
                    <select name="hasil" id="edit_hasil" class="form-select" required>
                      <option value="">— Pilih Hasil —</option>
                      <option value="kompeten">Kompeten</option>
                      <option value="tidak kompeten">Tidak Kompeten</option>
                    </select>
                  </div>
                  <div class="col-md-6">
                    <label class="form-label">Keterangan (opsional)</label>
                    <input type="text" name="keterangan" id="edit_keterangan" class="form-control">
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

      <!-- Modal: Hapus Nilai -->
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
              <input type="hidden" name="id_nilai" id="delete_id_nilai">
              <div class="modal-body">
                <p>Anda yakin ingin menghapus nilai untuk <strong id="delete_label"></strong>?</p>
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
    // Pencarian sederhana
    const searchInput = document.getElementById('searchInput');
    const rows = document.querySelectorAll('#nilaiTable tbody tr');
    searchInput?.addEventListener('input', function(){
      const q = (this.value || '').toLowerCase();
      rows.forEach(function(tr){
        const text = tr.innerText.toLowerCase();
        tr.style.display = text.indexOf(q) !== -1 ? '' : 'none';
      });
    });

    // Buka modal edit
    document.querySelectorAll('.btn-edit').forEach(function(btn){
      btn.addEventListener('click', function(){
        document.getElementById('edit_id_nilai').value = this.dataset.id;
        document.getElementById('edit_hasil').value = (this.dataset.keterangan || '').toLowerCase() === 'kompeten' ? 'kompeten' : ((this.dataset.keterangan || '').toLowerCase() === 'tidak kompeten' ? 'tidak kompeten' : '');
        document.getElementById('edit_keterangan').value = this.dataset.keterangan || '';
        var modal = new bootstrap.Modal(document.getElementById('modalEdit'));
        modal.show();
      });
    });

    // Buka modal hapus
    document.querySelectorAll('.btn-delete').forEach(function(btn){
      btn.addEventListener('click', function(){
        document.getElementById('delete_id_nilai').value = this.dataset.id;
        document.getElementById('delete_label').innerText = this.dataset.label || '';
        var modal = new bootstrap.Modal(document.getElementById('modalDelete'));
        modal.show();
      });
    });

    // Pemetaan peserta berdasarkan pelatihan (untuk form create)
    const mapPesertaByPelatihan = <?php echo json_encode($map_peserta_by_pelatihan, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES); ?>;
    const selectPel = document.getElementById('create_pelatihan');
    const selectPes = document.getElementById('create_peserta');

    function refillPesertaOptions(pelId){
      const opts = mapPesertaByPelatihan[pelId] || [];
      // simpan selection lama jika ada
      const oldVal = selectPes.value;
      selectPes.innerHTML = '<option value="">— Pilih Peserta —</option>';
      if (opts.length > 0) {
        opts.forEach(function(o){
          const opt = document.createElement('option');
          opt.value = o.id_user;
          opt.textContent = o.nama_lengkap;
          selectPes.appendChild(opt);
        });
      } else {
        const opt = document.createElement('option');
        opt.value = '';
        opt.textContent = '— Tidak ada peserta terdaftar —';
        opt.disabled = true;
        selectPes.appendChild(opt);
      }
      // restore jika tetap valid
      const canRestore = Array.prototype.some.call(selectPes.options, function(o){ return o.value == oldVal; });
      if (canRestore) selectPes.value = oldVal; else selectPes.value = '';
    }

    selectPel?.addEventListener('change', function(){
      const pid = parseInt(this.value || '0', 10);
      if (pid > 0) {
        refillPesertaOptions(pid);
      } else {
        selectPes.innerHTML = '<option value="">— Pilih Peserta —</option>';
      }
    });
  })();
  </script>
<?php include_once __DIR__ . '/../../includes/footer.php'; ?>
