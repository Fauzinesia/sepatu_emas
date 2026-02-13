<?php
session_start();
require_once __DIR__ . '/../config/koneksi.php';

// Proteksi login & role peserta
if (!isset($_SESSION['auth']['logged_in']) || $_SESSION['auth']['logged_in'] !== true) {
  header('Location: ../login.php');
  exit;
}
if (strtolower($_SESSION['auth']['role'] ?? '') !== 'peserta') {
  header('Location: ../admin/dashboard.php');
  exit;
}

$page_title = 'Pendaftaran Saya';

// CSRF token
if (empty($_SESSION['csrf_token'])) {
  $_SESSION['csrf_token'] = bin2hex(random_bytes(16));
}
$csrf = $_SESSION['csrf_token'];

$notice = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'daftar_pelatihan') {
  try {
    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
      throw new RuntimeException('Token CSRF tidak valid.');
    }
    $uid = (int)($_SESSION['auth']['id_user'] ?? 0);
    $id_pelatihan = (int)($_POST['id_pelatihan'] ?? 0);
    if ($uid <= 0 || $id_pelatihan <= 0) {
      throw new RuntimeException('Data pendaftaran tidak valid.');
    }
    $cekDup = $pdo->prepare('SELECT COUNT(*) FROM tb_pendaftaran WHERE id_user = ? AND id_pelatihan = ?');
    $cekDup->execute([$uid, $id_pelatihan]);
    if ((int)$cekDup->fetchColumn() > 0) {
      throw new RuntimeException('Anda sudah mendaftar pada pelatihan ini.');
    }
    $kuota = 0;
    try {
      $stK = $pdo->prepare('SELECT kuota, status FROM tb_pelatihan WHERE id_pelatihan = ?');
      $stK->execute([$id_pelatihan]);
      $rowK = $stK->fetch(PDO::FETCH_ASSOC);
      if (!$rowK || strtolower($rowK['status'] ?? '') !== 'aktif') {
        throw new RuntimeException('Pelatihan tidak tersedia.');
      }
      $kuota = (int)($rowK['kuota'] ?? 0);
    } catch (Throwable $e) { $kuota = 0; }
    if ($kuota > 0) {
      $stCount = $pdo->prepare("SELECT COUNT(*) FROM tb_pendaftaran WHERE id_pelatihan = ? AND status IN ('menunggu','diterima')");
      $stCount->execute([$id_pelatihan]);
      $totalReg = (int)$stCount->fetchColumn();
      if ($totalReg >= $kuota) {
        throw new RuntimeException('Kuota pendaftaran pelatihan sudah penuh.');
      }
    }
    $ins = $pdo->prepare('INSERT INTO tb_pendaftaran (id_user, id_pelatihan, status, tanggal_daftar) VALUES (?,?,?,?)');
    $ins->execute([$uid, $id_pelatihan, 'menunggu', date('Y-m-d')]);
    $lastId = (int)$pdo->lastInsertId();
    $noInduk = 'NIP-' . date('Y') . '/' . str_pad((string)$lastId, 6, '0', STR_PAD_LEFT);
    try {
      $upNo = $pdo->prepare('UPDATE tb_pendaftaran SET no_induk_pendaftaran = ? WHERE id_pendaftaran = ?');
      $upNo->execute([$noInduk, $lastId]);
    } catch (Throwable $e) {}
    $notice = 'Pendaftaran berhasil dibuat.';
  } catch (Throwable $e) {
    $notice = 'Error: ' . htmlspecialchars($e->getMessage());
  }
}

// Handler unggah berkas
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'upload_berkas') {
  try {
    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
      throw new RuntimeException('Token CSRF tidak valid.');
    }
    $id_pendaftaran = (int) ($_POST['id_pendaftaran'] ?? 0);
    if ($id_pendaftaran <= 0) {
      throw new RuntimeException('ID pendaftaran tidak valid.');
    }

    // Pastikan pendaftaran milik user ini dan belum diterima
    $st = $pdo->prepare('SELECT id_user, status FROM tb_pendaftaran WHERE id_pendaftaran = :id');
    $st->execute([':id' => $id_pendaftaran]);
    $owner = $st->fetch(PDO::FETCH_ASSOC);
    if (!$owner || (int) $owner['id_user'] !== (int) ($_SESSION['auth']['id_user'] ?? 0)) {
      throw new RuntimeException('Akses ditolak untuk pendaftaran ini.');
    }
    if (strtolower($owner['status'] ?? '') === 'diterima') {
      throw new RuntimeException('Pendaftaran sudah diterima; berkas tidak bisa diubah.');
    }

    // Direktori upload
    $uploadDir = __DIR__ . '/../uploads/pendaftaran';
    if (!is_dir($uploadDir)) {
      @mkdir($uploadDir, 0775, true);
    }

    $maxSize = 2 * 1024 * 1024; // 2MB
    
    // Ekstensi berbahaya yang dilarang (Blacklist)
    $forbidden = ['php', 'php3', 'php4', 'php5', 'phtml', 'exe', 'bat', 'sh', 'cmd', 'js', 'html', 'htm', 'jar', 'vbs'];

    $fields = ['file_ktp', 'file_ijazah', 'file_kartu_pencari_kerja'];
    $saved = [];
    foreach ($fields as $f) {
      if (!isset($_FILES[$f]) || ($_FILES[$f]['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        continue;
      }
      $err = $_FILES[$f]['error'];
      if ($err !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Gagal mengunggah ' . $f . '.');
      }
      
      $size = (int) $_FILES[$f]['size'];
      if ($size > $maxSize) {
        throw new RuntimeException('Ukuran berkas ' . $f . ' melebihi 2MB.');
      }

      $rawExt = pathinfo($_FILES[$f]['name'], PATHINFO_EXTENSION);
      $ext = strtolower($rawExt ?: '');
      
      // Cek blacklist ekstensi
      if (in_array($ext, $forbidden, true)) {
        throw new RuntimeException('Tipe berkas ' . $f . ' tidak aman dan tidak diizinkan.');
      }
      $fname = 'reg-' . $id_pendaftaran . '-' . $f . '-' . bin2hex(random_bytes(6)) . '.' . strtolower($ext ?: 'dat');
      $dest = $uploadDir . '/' . $fname;
      if (!move_uploaded_file($_FILES[$f]['tmp_name'], $dest)) {
        throw new RuntimeException('Tidak bisa menyimpan berkas ' . $f . '.');
      }
      // Simpan relatif path untuk penggunaan web
      $saved[$f] = 'uploads/pendaftaran/' . $fname;
    }

    if (!empty($saved)) {
      $set = [];
      $params = [':id' => $id_pendaftaran];
      foreach ($saved as $col => $path) {
        $set[] = "$col = :$col";
        $params[":" . $col] = $path;
      }
      $sql = 'UPDATE tb_pendaftaran SET ' . implode(', ', $set) . ' WHERE id_pendaftaran = :id';
      $up = $pdo->prepare($sql);
      $up->execute($params);
      $notice = 'Berkas berhasil diunggah.';
    } else {
      $notice = 'Tidak ada berkas yang dipilih.';
    }
  } catch (Throwable $e) {
    $notice = 'Error: ' . $e->getMessage();
  }
}

// Ambil pendaftaran saya
$pendaftarans = [];
try {
  $st = $pdo->prepare("SELECT p.*, pe.nama_pelatihan
                       FROM tb_pendaftaran p
                       JOIN tb_pelatihan pe ON p.id_pelatihan = pe.id_pelatihan
                       WHERE p.id_user = :uid
                       ORDER BY p.created_at DESC");
  $st->execute([':uid' => $_SESSION['auth']['id_user'] ?? 0]);
  $pendaftarans = $st->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
  $pendaftarans = [];
}

$pelatihan_aktif = [];
$countsByPel = [];
try {
  $st = $pdo->query("SELECT id_pelatihan, nama_pelatihan, kuota, kategori, lokasi FROM tb_pelatihan WHERE status='aktif' ORDER BY id_pelatihan DESC");
  $pelatihan_aktif = $st->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) { $pelatihan_aktif = []; }
try {
  $cs = $pdo->query("SELECT id_pelatihan, COUNT(*) AS total FROM tb_pendaftaran WHERE status IN ('menunggu','diterima') GROUP BY id_pelatihan");
  foreach ($cs->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $countsByPel[(int)$row['id_pelatihan']] = (int)$row['total'];
  }
} catch (Throwable $e) {}
?>
<!doctype html>
<html lang="id">

<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?php echo isset($page_title) ? $page_title . ' — SEPATU EMAS' : 'SEPATU EMAS'; ?></title>
  <link rel="shortcut icon" type="image/png" href="../assets/images/logos/favicon.png" />
  <link rel="stylesheet" href="../assets/css/styles.min.css" />
</head>

<body>
  <div class="page-wrapper" id="main-wrapper" data-layout="vertical" data-navbarbg="skin6" data-sidebartype="full"
    data-sidebar-position="fixed" data-header-position="fixed">
    <?php include_once __DIR__ . '/../includes/sidebar.php'; ?>
    <div class="body-wrapper">
      <?php include_once __DIR__ . '/../includes/navbar.php'; ?>

      <div class="container-fluid">
        <div class="row">
          <div class="col-12">
            <div class="card">
              <div class="card-body">
                <div class="d-flex align-items-center justify-content-between mb-2">
                  <h5 class="card-title fw-semibold mb-0">Pendaftaran Saya</h5>
                  <button class="btn btn-primary" type="button" data-bs-toggle="modal" data-bs-target="#modalBrowsePelatihan"><i class="ti ti-notebook me-1"></i> Daftar Pelatihan</button>
                </div>
                <?php if (!empty($notice)) { ?>
                  <div class="alert alert-info" role="alert"><?php echo htmlspecialchars($notice); ?></div>
                <?php } ?>

                <div class="alert alert-primary shadow-sm mb-4" role="alert">
                  <div class="d-flex align-items-center">
                    <i class="ti ti-info-circle fs-5 me-3"></i>
                    <div>
                      <h6 class="alert-heading fw-bold mb-1">Informasi Kelengkapan Berkas</h6>
                      <p class="mb-0 small">
                        Agar pendaftaran Anda dapat segera diverifikasi oleh admin, mohon lengkapi berkas persyaratan berikut:
                        <strong>KTP</strong>, <strong>Ijazah</strong>, dan <strong>Kartu Pencari Kerja</strong>.
                        <br>Klik tombol <strong>"Kelola Berkas"</strong> pada kolom Aksi untuk mengunggah dokumen Anda.
                        Pendaftaran dengan berkas tidak lengkap tidak akan diproses ke tahap selanjutnya.
                      </p>
                    </div>
                  </div>
                </div>

                <div class="table-responsive">
                  <table class="table table-striped align-middle">
                    <thead>
                      <tr>
                        <th>Pelatihan</th>
                        <th>No Induk</th>
                        <th>Tanggal Daftar</th>
                        <th>Status</th>
                        <th>Keterangan</th>
                        <th>Berkas</th>
                        <th>Aksi</th>
                      </tr>
                    </thead>
                    <tbody>
                      <?php if (empty($pendaftarans)) { ?>
                        <tr>
                          <td colspan="7" class="text-center text-muted">Belum ada pendaftaran. Silakan mendaftar pada
                            halaman pelatihan.</td>
                        </tr>
                      <?php } else {
                        foreach ($pendaftarans as $p) { ?>
                          <tr>
                            <td><?php echo htmlspecialchars($p['nama_pelatihan']); ?></td>
                            <td><?php echo htmlspecialchars($p['no_induk_pendaftaran'] ?? '-'); ?></td>
                            <td><?php echo htmlspecialchars(date('d/m/Y', strtotime($p['tanggal_daftar']))); ?></td>
                            <td>
                              <?php
                              $st = strtolower($p['status']);
                              $badge = 'bg-secondary';
                              if ($st === 'menunggu')
                                $badge = 'bg-warning text-dark';
                              elseif ($st === 'diterima')
                                $badge = 'bg-success';
                              elseif ($st === 'ditolak')
                                $badge = 'bg-danger';
                              ?>
                              <span
                                class="badge <?php echo $badge; ?> text-capitalize"><?php echo htmlspecialchars($p['status']); ?></span>
                            </td>
                            <td>
                              <?php if ($st === 'ditolak' && !empty($p['keterangan'])): ?>
                                <div class="p-2 bg-danger-subtle text-danger rounded border border-danger small">
                                  <i class="ti ti-alert-circle me-1"></i>
                                  <?php echo htmlspecialchars($p['keterangan']); ?>
                                </div>
                              <?php else: ?>
                                <?php echo htmlspecialchars($p['keterangan'] ?: '-'); ?>
                              <?php endif; ?>
                            </td>
                            <td>
                              <div class="d-flex flex-wrap gap-2">
                                <?php
                                $badge = function ($exists, $label, $href) {
                                  if ($exists) {
                                    echo '<a href="' . '../' . htmlspecialchars($href) . '" target="_blank" class="badge bg-success-subtle text-success text-decoration-none">'
                                      . '<i class="ti ti-file-check me-1"></i>' . htmlspecialchars($label) . '</a>';
                                  } else {
                                    echo '<span class="badge bg-secondary-subtle text-secondary">'
                                      . '<i class="ti ti-file-x me-1"></i>' . htmlspecialchars($label) . '</span>';
                                  }
                                };
                                $badge(!empty($p['file_ktp']), 'KTP', $p['file_ktp'] ?? '');
                                $badge(!empty($p['file_ijazah']), 'Ijazah', $p['file_ijazah'] ?? '');
                                $badge(!empty($p['file_kartu_pencari_kerja']), 'Kartu Pencari Kerja', $p['file_kartu_pencari_kerja'] ?? '');
                                ?>
                              </div>
                            </td>
                            <td style="min-width:160px;">
                              <?php if (strtolower($p['status']) === 'diterima') { ?>
                                <span class="text-muted"><i class="ti ti-lock me-1"></i> Berkas terkunci (diterima)</span>
                              <?php } else { ?>
                                <button type="button" class="btn btn-sm btn-outline-primary btn-upload"
                                  data-id="<?php echo (int) $p['id_pendaftaran']; ?>">
                                  <i class="ti ti-upload me-1"></i> Kelola Berkas
                                </button>
                              <?php } ?>
                            </td>
                          </tr>
                        <?php }
                      } ?>
                    </tbody>
                  </table>
                </div>

                <!-- Modal Upload Berkas -->
                <div class="modal fade" id="modalUpload" tabindex="-1" aria-hidden="true">
                  <div class="modal-dialog modal-lg modal-dialog-centered">
                    <div class="modal-content">
                      <div class="modal-header">
                        <h5 class="modal-title">Unggah Berkas Pendaftaran</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                      </div>
                      <form method="post" enctype="multipart/form-data">
                        <input type="hidden" name="action" value="upload_berkas" />
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf); ?>" />
                        <input type="hidden" name="id_pendaftaran" id="upload_id_pendaftaran" />
                        <div class="modal-body">
                          <p class="text-muted">Pilih berkas yang ingin Anda unggah atau perbarui. Format bebas (Dokumen/Gambar, kecuali file program/eksekusi). Maksimal 2MB per berkas.
                          </p>
                          <div class="row g-3">
                            <div class="col-md-12">
                              <label class="form-label">KTP</label>
                              <input type="file" name="file_ktp" class="form-control" />
                            </div>
                            <div class="col-md-12">
                              <label class="form-label">Ijazah</label>
                              <input type="file" name="file_ijazah" class="form-control" />
                            </div>
                            <div class="col-md-12">
                              <label class="form-label">Kartu Pencari Kerja</label>
                              <input type="file" name="file_kartu_pencari_kerja" class="form-control" />
                            </div>
                          </div>
                        </div>
                        <div class="modal-footer">
                          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                          <button type="submit" class="btn btn-primary"><i class="ti ti-upload me-1"></i>
                            Unggah</button>
                        </div>
                      </form>
                    </div>
                  </div>
                </div>
                <div class="modal fade" id="modalBrowsePelatihan" tabindex="-1" aria-hidden="true">
                  <div class="modal-dialog modal-lg modal-dialog-centered">
                    <div class="modal-content">
                      <div class="modal-header">
                        <h5 class="modal-title">Pilih Pelatihan</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                      </div>
                      <div class="modal-body">
                        <div class="input-group mb-3" style="max-width: 320px;">
                          <span class="input-group-text"><i class="ti ti-search"></i></span>
                          <input type="text" id="searchPelBrowse" class="form-control" placeholder="Cari pelatihan...">
                        </div>
                        <div class="table-responsive">
                          <table class="table table-hover align-middle" id="tablePelBrowse">
                            <thead>
                              <tr>
                                <th>Nama</th>
                                <th>Kuota</th>
                                <th>Terdaftar</th>
                                <th>Kategori</th>
                                <th>Lokasi</th>
                                <th>Aksi</th>
                              </tr>
                            </thead>
                            <tbody>
                              <?php if (empty($pelatihan_aktif)) { ?>
                                <tr><td colspan="6" class="text-center text-muted">Tidak ada pelatihan aktif.</td></tr>
                              <?php } else { foreach ($pelatihan_aktif as $pl) {
                                $pid = (int)($pl['id_pelatihan'] ?? 0);
                                $nm = htmlspecialchars($pl['nama_pelatihan'] ?? '');
                                $ku = (int)($pl['kuota'] ?? 0);
                                $cnt = (int)($countsByPel[$pid] ?? 0);
                                $kat = htmlspecialchars(strtoupper($pl['kategori'] ?? ''));
                                $lok = htmlspecialchars(strtoupper($pl['lokasi'] ?? ''));
                              ?>
                                <tr class="browse-row">
                                  <td><?php echo $nm; ?></td>
                                  <td><?php echo $ku > 0 ? $ku : '-'; ?></td>
                                  <td><?php echo $cnt; ?></td>
                                  <td><?php echo $kat; ?></td>
                                  <td><?php echo $lok; ?></td>
                                  <td>
                                    <form method="post" class="d-inline">
                                      <input type="hidden" name="action" value="daftar_pelatihan" />
                                      <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf); ?>" />
                                      <input type="hidden" name="id_pelatihan" value="<?php echo $pid; ?>" />
                                      <button type="submit" class="btn btn-sm btn-outline-primary" <?php echo ($ku > 0 && $cnt >= $ku) ? 'disabled' : ''; ?>><i class="ti ti-clipboard-check me-1"></i> Daftar</button>
                                    </form>
                                  </td>
                                </tr>
                              <?php } } ?>
                            </tbody>
                          </table>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

    </div>
  </div>

  <script src="../assets/libs/jquery/dist/jquery.min.js"></script>
  <script src="../assets/libs/bootstrap/dist/js/bootstrap.bundle.min.js"></script>
  <script src="../assets/js/app.min.js"></script>
  <script src="../assets/js/sidebarmenu.js"></script>
  <script>
    (function () {
      document.querySelectorAll('.btn-upload').forEach(function (btn) {
        btn.addEventListener('click', function () {
          var id = this.dataset.id || '';
          document.getElementById('upload_id_pendaftaran').value = id;
          var modal = new bootstrap.Modal(document.getElementById('modalUpload'));
          modal.show();
        });
      });
      var input = document.getElementById('searchPelBrowse');
      var rows = document.querySelectorAll('#tablePelBrowse .browse-row');
      if (input && rows) {
        input.addEventListener('input', function () {
          var q = (this.value || '').toLowerCase();
          rows.forEach(function (r) {
            var t = r.innerText.toLowerCase();
            r.style.display = t.indexOf(q) !== -1 ? '' : 'none';
          });
        });
      }
      var params = new URLSearchParams(window.location.search);
      if (params.get('open') === 'browse') {
        var m2 = new bootstrap.Modal(document.getElementById('modalBrowsePelatihan'));
        m2.show();
      }
    })();
  </script>
</body>

</html>
