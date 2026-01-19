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

$page_title = 'Sertifikat';
require_once __DIR__ . '/../../config/koneksi.php';
if (empty($_SESSION['csrf_token'])) { $_SESSION['csrf_token'] = bin2hex(random_bytes(16)); }
include_once __DIR__ . '/../../includes/header.php';
include_once __DIR__ . '/../../includes/sidebar.php';
?>
    <div class="body-wrapper">
      <?php include_once __DIR__ . '/../../includes/navbar.php'; ?>
    <div class="container-fluid">
      <div class="card">
        <div class="card-body">
          <div class="d-flex align-items-center justify-content-between mb-3">
            <h5 class="card-title mb-0">Sertifikat</h5>
            <?php
              // Build querystring for cetak URL
              $qs = [];
              if (!empty($_GET['id_pelatihan'])) { $qs['id_pelatihan'] = (int)$_GET['id_pelatihan']; }
              if (!empty($_GET['q'])) { $qs['q'] = trim($_GET['q']); }
              $qsStr = http_build_query($qs);
            ?>
            <a href="cetak.php<?php echo $qsStr ? ('?' . $qsStr) : ''; ?>" target="_blank" class="btn btn-primary">
              Cetak
            </a>
          </div>

          <?php
            // Filters
            $idPel = isset($_GET['id_pelatihan']) ? (int)$_GET['id_pelatihan'] : 0;
            $q = trim($_GET['q'] ?? '');

            $issued_count = 0;
            $issuance_error = '';
            try {
              $eligibleSql = "
                SELECT p.id_user, p.id_pelatihan, n.keterangan
                FROM (
                  SELECT p1.id_user, p1.id_pelatihan, p1.status
                  FROM tb_pendaftaran p1
                  WHERE p1.id_pendaftaran IN (
                    SELECT MAX(id_pendaftaran)
                    FROM tb_pendaftaran
                    GROUP BY id_user, id_pelatihan
                  )
                ) p
                JOIN (
                  SELECT n1.id_user, n1.id_pelatihan, n1.keterangan
                  FROM tb_nilai n1
                  WHERE n1.id_nilai IN (
                    SELECT MAX(id_nilai)
                    FROM tb_nilai
                    GROUP BY id_user, id_pelatihan
                  )
                ) n ON n.id_user = p.id_user AND n.id_pelatihan = p.id_pelatihan
                WHERE LOWER(p.status) = 'diterima' AND n.keterangan IS NOT NULL
              ";
              $eligibleParams = [];
              if ($idPel > 0) {
                $eligibleSql .= " AND p.id_pelatihan = :idPel";
                $eligibleParams[':idPel'] = $idPel;
              }
              $stEligible = $pdo->prepare($eligibleSql);
              $stEligible->execute($eligibleParams);
              $eligibleRows = $stEligible->fetchAll(PDO::FETCH_ASSOC);
              if (!empty($eligibleRows)) {
                $cekExist = $pdo->prepare('SELECT 1 FROM tb_sertifikat WHERE id_user = ? AND id_pelatihan = ? LIMIT 1');
                $insCert = $pdo->prepare('INSERT INTO tb_sertifikat (id_user, id_pelatihan, nomor_sertifikat, tanggal_terbit, file_sertifikat) VALUES (?,?,?,?,?)');
                foreach ($eligibleRows as $row) {
                  $uid = (int)$row['id_user'];
                  $pid = (int)$row['id_pelatihan'];
                  $ket = strtolower(trim((string)($row['keterangan'] ?? '')));
                  if ($ket !== 'kompeten') { continue; }
                  $cekExist->execute([$uid, $pid]);
                  $exists = (bool)$cekExist->fetchColumn();
                  if ($exists) { continue; }
                  $nomor = 'BLK-HSU/' . date('Y') . '/' . str_pad((string)$pid, 4, '0', STR_PAD_LEFT) . '/' . str_pad((string)$uid, 4, '0', STR_PAD_LEFT);
                  $tanggalTerbit = date('Y-m-d');
                  $insCert->execute([$uid, $pid, $nomor, $tanggalTerbit, null]);
                  $issued_count++;
                }
              }
            } catch (Throwable $e) {
              $issuance_error = htmlspecialchars($e->getMessage());
            }

            // Pelatihan list for filter
            $stPel = $pdo->query("SELECT id_pelatihan, nama_pelatihan FROM tb_pelatihan ORDER BY nama_pelatihan ASC");
            $pelatihans = $stPel->fetchAll(PDO::FETCH_ASSOC);

            // Build certificates query
            $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
            if ($page < 1) $page = 1;
            $perPage = 10;
            $totalRows = 0;
            $totalPages = 0;
            $offset = 0;

            // Hitung total baris untuk pagination
            $countSql = "SELECT COUNT(*) FROM tb_sertifikat s
                         JOIN tb_user u ON u.id_user = s.id_user
                         JOIN tb_pelatihan p ON p.id_pelatihan = s.id_pelatihan
                         WHERE 1=1";
            $countParams = [];
            if ($idPel > 0) {
              $countSql .= " AND s.id_pelatihan = :idPel";
              $countParams[':idPel'] = $idPel;
            }
            if ($q !== '') {
              $countSql .= " AND (u.nama_lengkap LIKE :q OR p.nama_pelatihan LIKE :q OR s.nomor_sertifikat LIKE :q)";
              $countParams[':q'] = '%' . $q . '%';
            }
            try {
              $countStmt = $pdo->prepare($countSql);
              $countStmt->execute($countParams);
              $totalRows = (int)$countStmt->fetchColumn();
            } catch (Throwable $e) { $totalRows = 0; }

            $totalPages = ceil($totalRows / $perPage);
            if ($page > $totalPages && $totalPages > 0) $page = $totalPages;
            $offset = ($page - 1) * $perPage;

            $sql = "SELECT s.id_sertifikat, s.nomor_sertifikat, s.tanggal_terbit, s.file_sertifikat,
                           u.id_user, u.nama_lengkap,
                           p.id_pelatihan, p.nama_pelatihan, p.nama_instruktur,
                           nres.keterangan, pdres.no_induk_pendaftaran
                    FROM tb_sertifikat s
                    JOIN tb_user u ON u.id_user = s.id_user
                    JOIN tb_pelatihan p ON p.id_pelatihan = s.id_pelatihan
                    LEFT JOIN (
                      SELECT n1.id_user, n1.id_pelatihan, n1.keterangan
                      FROM tb_nilai n1
                      WHERE n1.id_nilai IN (
                        SELECT MAX(id_nilai)
                        FROM tb_nilai
                        WHERE keterangan IS NOT NULL
                        GROUP BY id_user, id_pelatihan
                      )
                    ) nres ON nres.id_user = s.id_user AND nres.id_pelatihan = s.id_pelatihan
                    LEFT JOIN (
                      SELECT p1.id_user, p1.id_pelatihan, p1.no_induk_pendaftaran
                      FROM tb_pendaftaran p1
                      WHERE p1.id_pendaftaran IN (
                        SELECT MAX(id_pendaftaran)
                        FROM tb_pendaftaran
                        GROUP BY id_user, id_pelatihan
                      )
                    ) pdres ON pdres.id_user = s.id_user AND pdres.id_pelatihan = s.id_pelatihan
                    WHERE 1=1";
            $params = [];
            if ($idPel > 0) {
              $sql .= " AND s.id_pelatihan = :idPel";
              $params[':idPel'] = $idPel;
            }
            if ($q !== '') {
              $sql .= " AND (u.nama_lengkap LIKE :q OR p.nama_pelatihan LIKE :q OR s.nomor_sertifikat LIKE :q)";
              $params[':q'] = '%' . $q . '%';
            }
            $sql .= " ORDER BY s.tanggal_terbit DESC, p.nama_pelatihan ASC, u.nama_lengkap ASC LIMIT :limit OFFSET :offset";
            $st = $pdo->prepare($sql);
            foreach ($params as $k => $v) {
              $st->bindValue($k, $v);
            }
            $st->bindValue(':limit', $perPage, PDO::PARAM_INT);
            $st->bindValue(':offset', $offset, PDO::PARAM_INT);
            $st->execute();
            $rows = $st->fetchAll(PDO::FETCH_ASSOC);
          ?>

          <form method="get" class="row g-2 mb-3">
            <div class="col-md-4">
              <label class="form-label">Pelatihan</label>
              <select name="id_pelatihan" class="form-select">
                <option value="">Semua Pelatihan</option>
                <?php foreach ($pelatihans as $pel): ?>
                  <option value="<?php echo (int)$pel['id_pelatihan']; ?>" <?php echo ($idPel === (int)$pel['id_pelatihan']) ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($pel['nama_pelatihan']); ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-4">
              <label class="form-label">Cari</label>
              <input type="text" name="q" value="<?php echo htmlspecialchars($q); ?>" class="form-control" placeholder="Nama peserta / pelatihan / nomor">
            </div>
            <div class="col-md-4 d-flex align-items-end gap-2">
              <button type="submit" class="btn btn-success">Terapkan</button>
              <a href="sertifikat.php" class="btn btn-secondary">Reset</a>
            </div>
          </form>

          <?php if ($issuance_error !== '') { ?>
            <div class="alert alert-warning" role="alert">Sinkronisasi sertifikat gagal: <?php echo $issuance_error; ?></div>
          <?php } elseif ($issued_count > 0) { ?>
            <div class="alert alert-success" role="alert">Sinkronisasi sertifikat: <?php echo (int)$issued_count; ?> diterbitkan.</div>
          <?php } ?>

          <div class="table-responsive">
            <table class="table table-striped table-hover align-middle">
              <thead>
                <tr>
                  <th style="width:60px;">No</th>
                  <th>Nama Peserta</th>
                  <th>Pelatihan</th>
                  <th style="width:160px;">No Induk</th>
                  <th style="width:140px;">Hasil</th>
                  <th>Nomor Sertifikat</th>
                  <th style="width:140px;">Tanggal Terbit</th>
                  <th style="width:140px;">File</th>
                  <th style="width:140px;">Aksi</th>
                </tr>
              </thead>
              <tbody>
                <?php if (empty($rows)): ?>
                  <tr>
                    <td colspan="8" class="text-center">Belum ada sertifikat.</td>
                  </tr>
                <?php else: ?>
                  <?php $no = $offset + 1; foreach ($rows as $r): ?>
                    <tr>
                      <td><?php echo $no++; ?></td>
                      <td><?php echo htmlspecialchars($r['nama_lengkap']); ?></td>
                      <td><?php echo htmlspecialchars($r['nama_pelatihan']); ?></td>
                      <td><?php echo htmlspecialchars($r['no_induk_pendaftaran'] ?? '-'); ?></td>
                      <td><?php echo isset($r['keterangan']) && $r['keterangan'] !== null ? htmlspecialchars($r['keterangan']) : '-'; ?></td>
                      <td><?php echo htmlspecialchars($r['nomor_sertifikat'] ?? ''); ?></td>
                      <td><?php echo htmlspecialchars($r['tanggal_terbit'] ?? ''); ?></td>
                      <td>
                        <?php if (!empty($r['file_sertifikat'])): ?>
                          <a href="../../uploads/sertifikat/<?php echo rawurlencode($r['file_sertifikat']); ?>" target="_blank" class="btn btn-sm btn-outline-primary">Unduh</a>
                        <?php else: ?>
                          <span class="text-muted">Belum ada file</span>
                        <?php endif; ?>
                      </td>
                      <td>
                        <?php if (!empty($r['id_sertifikat'])): ?>
                          <a href="cetak_sertifikat.php?id=<?php echo (int)$r['id_sertifikat']; ?>" target="_blank" class="btn btn-sm btn-primary">Cetak Sertifikat</a>
                          <button type="button" class="btn btn-sm btn-outline-primary btn-lampiran"
                            data-id="<?php echo (int)$r['id_sertifikat']; ?>"
                            data-nama="<?php echo htmlspecialchars($r['nama_lengkap']); ?>"
                            data-pelatihan="<?php echo htmlspecialchars($r['nama_pelatihan']); ?>"
                            data-instruktur="<?php echo htmlspecialchars($r['nama_instruktur'] ?? ''); ?>"
                          >Cetak Lampiran</button>
                        <?php else: ?>
                          <span class="text-muted">Tidak tersedia</span>
                        <?php endif; ?>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                <?php endif; ?>
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
                <?php
                  // Build URL helper
                  $makeUrl = function($p) use ($idPel, $q) {
                    $qs = ['page' => $p];
                    if ($idPel > 0) $qs['id_pelatihan'] = $idPel;
                    if ($q !== '') $qs['q'] = $q;
                    return '?' . http_build_query($qs);
                  };
                ?>
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
  </div>
<?php include_once __DIR__ . '/../../includes/footer.php'; ?>
  <div class="modal fade" id="modalLampiran" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">Lampiran: Daftar Unit Kompetensi</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <form method="post" target="_blank" action="cetak_sertifikat.php">
          <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
          <input type="hidden" name="action" value="lampiran_manual">
          <input type="hidden" name="id_sertifikat" id="lamp_id_sertifikat">
          <div class="modal-body">
            <div class="row g-3 mb-3">
              <div class="col-md-4">
                <label class="form-label">Nama Peserta</label>
                <input type="text" class="form-control" id="lamp_nama" name="nama_override" placeholder="Override nama">
              </div>
              <div class="col-md-4">
                <label class="form-label">Nama Pelatihan</label>
                <input type="text" class="form-control" id="lamp_pelatihan" name="pelatihan_override" placeholder="Override pelatihan">
              </div>
              <div class="col-md-4">
                <label class="form-label">Nama Instruktur</label>
                <input type="text" class="form-control" id="lamp_instruktur" name="instruktur_override" placeholder="Override instruktur">
              </div>
            </div>
            <div class="form-check form-switch mb-3">
              <input class="form-check-input" type="checkbox" id="lamp_save_db" name="save_db" value="1" checked>
              <label class="form-check-label" for="lamp_save_db">Simpan lampiran ke database (disarankan)</label>
            </div>
            <div class="table-responsive">
              <table class="table table-bordered align-middle" id="lampiranTable">
                <thead>
                  <tr>
                    <th style="width:60px;">No</th>
                    <th>Unit Kompetensi</th>
                    <th style="width:220px;">Kode Unit Kompetensi</th>
                    <th style="width:60px;">Aksi</th>
                  </tr>
                </thead>
                <tbody>
                  <tr>
                    <td>1</td>
                    <td><input type="text" class="form-control" name="units[0][nama]" placeholder="Nama unit"></td>
                    <td><input type="text" class="form-control" name="units[0][kode]" placeholder="Kode unit"></td>
                    <td><button type="button" class="btn btn-sm btn-outline-danger btn-del-row">&times;</button></td>
                  </tr>
                </tbody>
              </table>
            </div>
            <button type="button" class="btn btn-outline-secondary" id="btnAddRow"><i class="ti ti-plus"></i> Tambah Baris</button>
            <div class="form-text mt-2">Data akan dicetak sebagai halaman ke-2 lampiran.</div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
            <button type="submit" class="btn btn-primary">Cetak Lampiran</button>
          </div>
        </form>
      </div>
    </div>
  </div>
  <script>
  document.addEventListener('DOMContentLoaded', function(){
    document.querySelectorAll('.btn-lampiran').forEach(function(btn){
      btn.addEventListener('click', function(){
        document.getElementById('lamp_id_sertifikat').value = this.dataset.id || '';
        document.getElementById('lamp_nama').value = this.dataset.nama || '';
        document.getElementById('lamp_pelatihan').value = this.dataset.pelatihan || '';
        document.getElementById('lamp_instruktur').value = this.dataset.instruktur || '';
        var modal = new bootstrap.Modal(document.getElementById('modalLampiran'));
        modal.show();
      });
    });
    var tbody = document.getElementById('lampiranTable').querySelector('tbody');
    document.getElementById('btnAddRow').addEventListener('click', function(){
      var idx = tbody.querySelectorAll('tr').length;
      var tr = document.createElement('tr');
      tr.innerHTML = '<td>'+(idx+1)+'</td>'
        + '<td><input type="text" class="form-control" name="units['+idx+'][nama]" placeholder="Nama unit"></td>'
        + '<td><input type="text" class="form-control" name="units['+idx+'][kode]" placeholder="Kode unit"></td>'
        + '<td><button type="button" class="btn btn-sm btn-outline-danger btn-del-row">&times;</button></td>';
      tbody.appendChild(tr);
    });
    tbody.addEventListener('click', function(e){
      if (e.target && e.target.classList.contains('btn-del-row')) {
        var tr = e.target.closest('tr');
        tr?.parentNode?.removeChild(tr);
        tbody.querySelectorAll('tr').forEach(function(row, i){
          row.querySelector('td').innerText = (i+1);
          row.querySelectorAll('input').forEach(function(inp){
            if (inp.name.includes('units[')) {
              var isNama = inp.name.endsWith('[nama]');
              var isKode = inp.name.endsWith('[kode]');
              inp.name = 'units['+i+']['+(isNama?'nama':'kode')+']';
            }
          });
        });
      }
    });
  });
  </script>
