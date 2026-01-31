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

$page_title = 'Pendaftaran';
require_once __DIR__ . '/../../config/koneksi.php';

// Filter status (server-side) untuk konsistensi dengan halaman cetak
$statusFilter = isset($_GET['status']) ? strtolower(trim($_GET['status'])) : '';
$allowedStatus = ['', 'menunggu','diterima','ditolak'];
if (!in_array($statusFilter, $allowedStatus, true)) { $statusFilter = ''; }

$export = isset($_GET['export']) ? strtolower(trim($_GET['export'])) : '';
if ($export === 'csv') {
  try {
    $sql = "SELECT p.id_pendaftaran, p.no_induk_pendaftaran, p.tanggal_daftar, p.status, p.keterangan, p.created_at, p.updated_at,
                   u.id_user, u.nama_lengkap, u.username, u.jenis_kelamin, u.email, u.no_hp, u.no_wa, u.pendidikan_terakhir, u.alamat,
                   pr.nama AS nama_provinsi, ko.nama AS nama_kabupaten, kc.nama AS nama_kecamatan, kl.nama AS nama_desa,
                   pe.id_pelatihan, pe.nama_pelatihan, pe.status AS status_pelatihan, pe.kategori, pe.lokasi, pe.tanggal_mulai, pe.tanggal_selesai
            FROM tb_pendaftaran p
            JOIN tb_user u ON p.id_user = u.id_user
            JOIN tb_pelatihan pe ON p.id_pelatihan = pe.id_pelatihan
            LEFT JOIN t_provinsi pr ON pr.id = u.id_provinsi
            LEFT JOIN t_kota ko ON ko.id = u.id_kabupaten
            LEFT JOIN t_kecamatan kc ON kc.id = u.id_kecamatan
            LEFT JOIN t_kelurahan kl ON kl.id = u.id_desa";
    if ($statusFilter !== '') { $sql .= " WHERE p.status = :status"; }
    $sql .= " ORDER BY p.created_at DESC";
    $st = $pdo->prepare($sql);
    if ($statusFilter !== '') { $st->bindValue(':status', $statusFilter, PDO::PARAM_STR); }
    $st->execute();
    $rows = $st->fetchAll(PDO::FETCH_ASSOC);
  } catch (Throwable $e) { $rows = []; }
  header('Content-Type: text/csv; charset=UTF-8');
  header('Content-Disposition: attachment; filename="pendaftaran_' . date('Ymd_His') . '.csv"');
  echo "\xEF\xBB\xBF";
  $out = fopen('php://output', 'w');
  fputcsv($out, [
    'ID Pendaftaran','No Induk','Tanggal Daftar','Status','Keterangan','Created At','Updated At',
    'ID User','Nama Lengkap','Username','Jenis Kelamin','Email','No HP','No WA','Pendidikan Terakhir','Alamat',
    'Provinsi','Kabupaten','Kecamatan','Desa',
    'ID Pelatihan','Nama Pelatihan','Status Pelatihan','Kategori','Lokasi','Mulai','Selesai'
  ]);
  foreach ($rows as $r) {
    fputcsv($out, [
      (int)($r['id_pendaftaran'] ?? 0),
      (string)($r['no_induk_pendaftaran'] ?? ''),
      (string)($r['tanggal_daftar'] ?? ''),
      (string)($r['status'] ?? ''),
      (string)($r['keterangan'] ?? ''),
      (string)($r['created_at'] ?? ''),
      (string)($r['updated_at'] ?? ''),
      (int)($r['id_user'] ?? 0),
      (string)($r['nama_lengkap'] ?? ''),
      (string)($r['username'] ?? ''),
      (string)($r['jenis_kelamin'] ?? ''),
      (string)($r['email'] ?? ''),
      (string)($r['no_hp'] ?? ''),
      (string)($r['no_wa'] ?? ''),
      (string)($r['pendidikan_terakhir'] ?? ''),
      (string)($r['alamat'] ?? ''),
      (string)($r['nama_provinsi'] ?? ''),
      (string)($r['nama_kabupaten'] ?? ''),
      (string)($r['nama_kecamatan'] ?? ''),
      (string)($r['nama_desa'] ?? ''),
      (int)($r['id_pelatihan'] ?? 0),
      (string)($r['nama_pelatihan'] ?? ''),
      (string)($r['status_pelatihan'] ?? ''),
      (string)($r['kategori'] ?? ''),
      (string)($r['lokasi'] ?? ''),
      (string)($r['tanggal_mulai'] ?? ''),
      (string)($r['tanggal_selesai'] ?? '')
    ]);
  }
  fclose($out);
  exit;
}
if ($export === 'excel') {
  try {
    $sql = "SELECT p.id_pendaftaran, p.no_induk_pendaftaran, p.tanggal_daftar, p.status, p.keterangan, p.created_at, p.updated_at,
                   u.id_user, u.nama_lengkap, u.username, u.jenis_kelamin, u.email, u.no_hp, u.no_wa, u.pendidikan_terakhir, u.alamat,
                   pr.nama AS nama_provinsi, ko.nama AS nama_kabupaten, kc.nama AS nama_kecamatan, kl.nama AS nama_desa,
                   pe.id_pelatihan, pe.nama_pelatihan, pe.status AS status_pelatihan, pe.kategori, pe.lokasi, pe.tanggal_mulai, pe.tanggal_selesai
            FROM tb_pendaftaran p
            JOIN tb_user u ON p.id_user = u.id_user
            JOIN tb_pelatihan pe ON p.id_pelatihan = pe.id_pelatihan
            LEFT JOIN t_provinsi pr ON pr.id = u.id_provinsi
            LEFT JOIN t_kota ko ON ko.id = u.id_kabupaten
            LEFT JOIN t_kecamatan kc ON kc.id = u.id_kecamatan
            LEFT JOIN t_kelurahan kl ON kl.id = u.id_desa";
    if ($statusFilter !== '') { $sql .= " WHERE p.status = :status"; }
    $sql .= " ORDER BY p.created_at DESC";
    $st = $pdo->prepare($sql);
    if ($statusFilter !== '') { $st->bindValue(':status', $statusFilter, PDO::PARAM_STR); }
    $st->execute();
    $rows = $st->fetchAll(PDO::FETCH_ASSOC);
  } catch (Throwable $e) { $rows = []; }
  header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
  header('Content-Disposition: attachment; filename="pendaftaran_' . date('Ymd_His') . '.xls"');
  echo '<html><head><meta charset="UTF-8"><style>';
  echo 'table{border-collapse:collapse;width:100%;font-family:"Times New Roman",Times,serif;font-size:12px}';
  echo 'th,td{border:1px solid #000;padding:6px 8px;vertical-align:top}';
  echo 'thead th{background:#f0f0f0;font-weight:700}';
  echo '.meta{margin-bottom:10px;font-size:12px}';
  echo '</style></head><body>';
  echo '<div class="meta">Status: ' . htmlspecialchars($statusFilter !== '' ? $statusFilter : 'Semua') . ' | Tanggal: ' . htmlspecialchars(date('d/m/Y H:i')) . '</div>';
  echo '<table><thead><tr>';
  $headers = [
    'ID Pendaftaran','No Induk','Tanggal Daftar','Status','Keterangan','Created At','Updated At',
    'ID User','Nama Lengkap','Username','Jenis Kelamin','Email','No HP','No WA','Pendidikan Terakhir','Alamat',
    'Provinsi','Kabupaten','Kecamatan','Desa',
    'ID Pelatihan','Nama Pelatihan','Status Pelatihan','Kategori','Lokasi','Mulai','Selesai'
  ];
  foreach ($headers as $h) { echo '<th>' . htmlspecialchars($h) . '</th>'; }
  echo '</tr></thead><tbody>';
  foreach ($rows as $r) {
    echo '<tr>';
    echo '<td>' . (int)($r['id_pendaftaran'] ?? 0) . '</td>';
    echo '<td>' . htmlspecialchars((string)($r['no_induk_pendaftaran'] ?? '')) . '</td>';
    echo '<td>' . htmlspecialchars((string)($r['tanggal_daftar'] ?? '')) . '</td>';
    echo '<td>' . htmlspecialchars((string)($r['status'] ?? '')) . '</td>';
    echo '<td>' . htmlspecialchars((string)($r['keterangan'] ?? '')) . '</td>';
    echo '<td>' . htmlspecialchars((string)($r['created_at'] ?? '')) . '</td>';
    echo '<td>' . htmlspecialchars((string)($r['updated_at'] ?? '')) . '</td>';
    echo '<td>' . (int)($r['id_user'] ?? 0) . '</td>';
    echo '<td>' . htmlspecialchars((string)($r['nama_lengkap'] ?? '')) . '</td>';
    echo '<td>' . htmlspecialchars((string)($r['username'] ?? '')) . '</td>';
    echo '<td>' . htmlspecialchars((string)($r['jenis_kelamin'] ?? '')) . '</td>';
    echo '<td>' . htmlspecialchars((string)($r['email'] ?? '')) . '</td>';
    echo '<td>' . htmlspecialchars((string)($r['no_hp'] ?? '')) . '</td>';
    echo '<td>' . htmlspecialchars((string)($r['no_wa'] ?? '')) . '</td>';
    echo '<td>' . htmlspecialchars((string)($r['pendidikan_terakhir'] ?? '')) . '</td>';
    echo '<td>' . htmlspecialchars((string)($r['alamat'] ?? '')) . '</td>';
    echo '<td>' . htmlspecialchars((string)($r['nama_provinsi'] ?? '')) . '</td>';
    echo '<td>' . htmlspecialchars((string)($r['nama_kabupaten'] ?? '')) . '</td>';
    echo '<td>' . htmlspecialchars((string)($r['nama_kecamatan'] ?? '')) . '</td>';
    echo '<td>' . htmlspecialchars((string)($r['nama_desa'] ?? '')) . '</td>';
    echo '<td>' . (int)($r['id_pelatihan'] ?? 0) . '</td>';
    echo '<td>' . htmlspecialchars((string)($r['nama_pelatihan'] ?? '')) . '</td>';
    echo '<td>' . htmlspecialchars((string)($r['status_pelatihan'] ?? '')) . '</td>';
    echo '<td>' . htmlspecialchars((string)($r['kategori'] ?? '')) . '</td>';
    echo '<td>' . htmlspecialchars((string)($r['lokasi'] ?? '')) . '</td>';
    echo '<td>' . htmlspecialchars((string)($r['tanggal_mulai'] ?? '')) . '</td>';
    echo '<td>' . htmlspecialchars((string)($r['tanggal_selesai'] ?? '')) . '</td>';
    echo '</tr>';
  }
  echo '</tbody></table></body></html>';
  exit;
}

// Pagination
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$perPage = isset($_GET['per_page']) ? max(1, min(100, (int)$_GET['per_page'])) : 10;
$offset = ($page - 1) * $perPage;

// CSRF token
if (empty($_SESSION['csrf_token'])) {
  $_SESSION['csrf_token'] = bin2hex(random_bytes(16));
}

$flash_success = '';
$flash_error   = '';

// Data untuk modal create
$pelatihan_list = [];
$peserta_list = [];
try {
  $stmtPl = $pdo->query("SELECT id_pelatihan, nama_pelatihan, kuota FROM tb_pelatihan ORDER BY nama_pelatihan ASC");
  $pelatihan_list = $stmtPl->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) { $pelatihan_list = []; }
try {
  $stmtPs = $pdo->query("SELECT id_user, nama_lengkap FROM tb_user WHERE role='peserta' ORDER BY nama_lengkap ASC");
  $peserta_list = $stmtPs->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) { $peserta_list = []; }

// Handle update status (terima/tolak)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $action = $_POST['action'] ?? '';
  $csrf   = $_POST['csrf_token'] ?? '';
  if (!hash_equals($_SESSION['csrf_token'], $csrf)) {
    $flash_error = 'Token CSRF tidak valid.';
  } else {
    if ($action === 'update_status') {
      $id   = (int)($_POST['id_pendaftaran'] ?? 0);
      $stat = strtolower(trim($_POST['status'] ?? ''));
      $ket  = trim($_POST['keterangan'] ?? '');
      $allowed = ['menunggu','diterima','ditolak'];
      if ($id > 0 && in_array($stat, $allowed, true)) {
        try {
          $cur = $pdo->prepare('SELECT status FROM tb_pendaftaran WHERE id_pendaftaran = ?');
          $cur->execute([$id]);
          $current = strtolower($cur->fetchColumn() ?: '');
          if ($current !== 'menunggu') {
            $flash_error = 'Status final tidak bisa diubah.';
          } else {
            $stmt = $pdo->prepare('UPDATE tb_pendaftaran SET status = ?, keterangan = ?, updated_at = NOW() WHERE id_pendaftaran = ?');
            $stmt->execute([$stat, ($ket !== '' ? $ket : null), $id]);
            $flash_success = 'Status pendaftaran berhasil diperbarui.';
          }
        } catch (Throwable $e) {
          $flash_error = 'Gagal memperbarui status pendaftaran.';
        }
      } else {
        $flash_error = 'Data tidak valid.';
      }
    } elseif ($action === 'edit') {
      $id   = (int)($_POST['id_pendaftaran'] ?? 0);
      $id_pelatihan_new = (int)($_POST['id_pelatihan'] ?? 0);
      $ket  = trim($_POST['keterangan'] ?? '');
      if ($id > 0 && $id_pelatihan_new > 0) {
        try {
          $cur = $pdo->prepare('SELECT status, id_user, id_pelatihan FROM tb_pendaftaran WHERE id_pendaftaran = ?');
          $cur->execute([$id]);
          $rowCur = $cur->fetch(PDO::FETCH_ASSOC);
          if (!$rowCur) {
            $flash_error = 'Data tidak ditemukan.';
          } else {
            $currentStatus = strtolower($rowCur['status'] ?? '');
            $id_user_cur = (int)($rowCur['id_user'] ?? 0);
            $id_pelatihan_old = (int)($rowCur['id_pelatihan'] ?? 0);
            if ($currentStatus !== 'menunggu') {
              $flash_error = 'Status final tidak bisa diubah.';
            } else {
              if ($id_pelatihan_new !== $id_pelatihan_old) {
                $cekDup = $pdo->prepare('SELECT COUNT(*) FROM tb_pendaftaran WHERE id_user = ? AND id_pelatihan = ?');
                $cekDup->execute([$id_user_cur, $id_pelatihan_new]);
                if ((int)$cekDup->fetchColumn() > 0) {
                  throw new Exception('Peserta sudah terdaftar pada pelatihan ini.');
                }
                $kuota = 0;
                try {
                  $stK = $pdo->prepare('SELECT kuota FROM tb_pelatihan WHERE id_pelatihan = ?');
                  $stK->execute([$id_pelatihan_new]);
                  $kuota = (int)($stK->fetchColumn() ?: 0);
                } catch (Throwable $e) { $kuota = 0; }
                if ($kuota > 0) {
                  $stCount = $pdo->prepare("SELECT COUNT(*) FROM tb_pendaftaran WHERE id_pelatihan = ? AND status IN ('menunggu','diterima')");
                  $stCount->execute([$id_pelatihan_new]);
                  $totalReg = (int)$stCount->fetchColumn();
                  if ($totalReg >= $kuota) {
                    throw new Exception('Kuota pendaftaran pelatihan sudah penuh.');
                  }
                }
              }
              $stmt = $pdo->prepare('UPDATE tb_pendaftaran SET id_pelatihan = ?, keterangan = ?, updated_at = NOW() WHERE id_pendaftaran = ?');
              $stmt->execute([$id_pelatihan_new, ($ket !== '' ? $ket : null), $id]);

              // --- Logika Upload Berkas (Admin) ---
              $uploadDir = __DIR__ . '/../../uploads/pendaftaran';
              if (!is_dir($uploadDir)) @mkdir($uploadDir, 0775, true);
              $maxSize = 2 * 1024 * 1024; // 2MB
              $forbidden = ['php', 'php3', 'php4', 'php5', 'phtml', 'exe', 'bat', 'sh', 'cmd', 'js', 'html', 'htm', 'jar', 'vbs'];
              $fields = ['file_ktp', 'file_ijazah', 'file_kartu_pencari_kerja'];
              $saved = [];
              foreach ($fields as $f) {
                if (!isset($_FILES[$f]) || $_FILES[$f]['error'] === UPLOAD_ERR_NO_FILE) continue;
                if ($_FILES[$f]['error'] === UPLOAD_ERR_OK) {
                  if ($_FILES[$f]['size'] > $maxSize) continue;
                  $ext = strtolower(pathinfo($_FILES[$f]['name'], PATHINFO_EXTENSION));
                  if (in_array($ext, $forbidden, true)) continue;
                  $fname = 'reg-' . $id . '-' . $f . '-' . bin2hex(random_bytes(6)) . '.' . ($ext ?: 'dat');
                  if (move_uploaded_file($_FILES[$f]['tmp_name'], $uploadDir . '/' . $fname)) {
                    $saved[$f] = 'uploads/pendaftaran/' . $fname;
                  }
                }
              }
              if (!empty($saved)) {
                $setSql = []; $prms = [':id' => $id];
                foreach ($saved as $col => $path) {
                  $setSql[] = "$col = :$col";
                  $prms[":$col"] = $path;
                }
                $upd = $pdo->prepare("UPDATE tb_pendaftaran SET " . implode(', ', $setSql) . " WHERE id_pendaftaran = :id");
                $upd->execute($prms);
              }
              // --- End Logika Upload ---

              $flash_success = 'Pendaftaran berhasil diperbarui.';
            }
          }
        } catch (Throwable $e) {
          $flash_error = htmlspecialchars($e->getMessage());
        }
      } else {
        $flash_error = 'Data tidak valid.';
      }
    } elseif ($action === 'delete') {
      $id = (int)($_POST['id_pendaftaran'] ?? 0);
      if ($id > 0) {
        try {
          $stf = $pdo->prepare('SELECT status, file_ktp, file_ijazah, file_kartu_pencari_kerja FROM tb_pendaftaran WHERE id_pendaftaran = ?');
          $stf->execute([$id]);
          $filesRow = $stf->fetch(PDO::FETCH_ASSOC) ?: [];
          // Hapus batasan status final agar admin bisa menghapus data kapan saja
          // if ($current !== 'menunggu') { ... }
          
            $del = $pdo->prepare('DELETE FROM tb_pendaftaran WHERE id_pendaftaran = ?');
            $del->execute([$id]);

            foreach (['file_ktp','file_ijazah','file_kartu_pencari_kerja'] as $col) {
              $rel = $filesRow[$col] ?? '';
              if ($rel) {
                $abs = __DIR__ . '/../../' . ltrim($rel, '/');
                if (is_file($abs)) { @unlink($abs); }
              }
            }
            $flash_success = 'Pendaftaran berhasil dihapus.';
          // }
        } catch (Throwable $e) {
          $flash_error = 'Gagal menghapus pendaftaran.';
        }
      } else {
        $flash_error = 'ID pendaftaran tidak valid.';
      }
    } elseif ($action === 'create') {
      $id_pelatihan = (int)($_POST['id_pelatihan'] ?? 0);
      $id_user = (int)($_POST['id_user'] ?? 0);
      $stat = strtolower(trim($_POST['status'] ?? 'menunggu'));
      $ket  = trim($_POST['keterangan'] ?? '');
      $allowed = ['menunggu','diterima','ditolak'];
      if ($id_pelatihan > 0 && $id_user > 0 && in_array($stat, $allowed, true)) {
        try {
          // Cegah duplikasi pendaftaran user ke pelatihan
          $cekDup = $pdo->prepare('SELECT COUNT(*) FROM tb_pendaftaran WHERE id_user = ? AND id_pelatihan = ?');
          $cekDup->execute([$id_user, $id_pelatihan]);
          if ((int)$cekDup->fetchColumn() > 0) {
            throw new Exception('Peserta sudah terdaftar pada pelatihan ini.');
          }
          // Batasi kuota jika tersedia
          $kuota = 0;
          try {
            $stK = $pdo->prepare('SELECT kuota FROM tb_pelatihan WHERE id_pelatihan = ?');
            $stK->execute([$id_pelatihan]);
            $kuota = (int)($stK->fetchColumn() ?: 0);
          } catch (Throwable $e) { $kuota = 0; }
          if ($kuota > 0) {
            $stCount = $pdo->prepare("SELECT COUNT(*) FROM tb_pendaftaran WHERE id_pelatihan = ? AND status IN ('menunggu','diterima')");
            $stCount->execute([$id_pelatihan]);
            $totalReg = (int)$stCount->fetchColumn();
            if ($totalReg >= $kuota) {
              throw new Exception('Kuota pendaftaran pelatihan sudah penuh.');
            }
          }
          // Insert pendaftaran
          $ins = $pdo->prepare('INSERT INTO tb_pendaftaran (id_user, id_pelatihan, status, tanggal_daftar, keterangan) VALUES (?,?,?,?,?)');
          $ins->execute([$id_user, $id_pelatihan, $stat, date('Y-m-d'), ($ket !== '' ? $ket : null)]);
          $lastId = (int)$pdo->lastInsertId();

          // --- Logika Upload Berkas (Admin) ---
          $uploadDir = __DIR__ . '/../../uploads/pendaftaran';
          if (!is_dir($uploadDir)) @mkdir($uploadDir, 0775, true);
          $maxSize = 2 * 1024 * 1024;
          $forbidden = ['php', 'php3', 'php4', 'php5', 'phtml', 'exe', 'bat', 'sh', 'cmd', 'js', 'html', 'htm', 'jar', 'vbs'];
          $fields = ['file_ktp', 'file_ijazah', 'file_kartu_pencari_kerja'];
          $saved = [];
          foreach ($fields as $f) {
            if (!isset($_FILES[$f]) || $_FILES[$f]['error'] === UPLOAD_ERR_NO_FILE) continue;
            if ($_FILES[$f]['error'] === UPLOAD_ERR_OK) {
              if ($_FILES[$f]['size'] > $maxSize) continue;
              $ext = strtolower(pathinfo($_FILES[$f]['name'], PATHINFO_EXTENSION));
              if (in_array($ext, $forbidden, true)) continue;
              $fname = 'reg-' . $lastId . '-' . $f . '-' . bin2hex(random_bytes(6)) . '.' . ($ext ?: 'dat');
              if (move_uploaded_file($_FILES[$f]['tmp_name'], $uploadDir . '/' . $fname)) {
                $saved[$f] = 'uploads/pendaftaran/' . $fname;
              }
            }
          }
          if (!empty($saved)) {
            $setSql = []; $prms = [':id' => $lastId];
            foreach ($saved as $col => $path) {
              $setSql[] = "$col = :$col";
              $prms[":$col"] = $path;
            }
            $upd = $pdo->prepare("UPDATE tb_pendaftaran SET " . implode(', ', $setSql) . " WHERE id_pendaftaran = :id");
            $upd->execute($prms);
          }
          // --- End Logika Upload ---

          // Generate no_induk_pendaftaran jika kolom tersedia
          $noInduk = 'NIP-' . date('Y') . '/' . str_pad((string)$lastId, 6, '0', STR_PAD_LEFT);
          try {
            $upNo = $pdo->prepare('UPDATE tb_pendaftaran SET no_induk_pendaftaran = ? WHERE id_pendaftaran = ?');
            $upNo->execute([$noInduk, $lastId]);
          } catch (Throwable $e) { /* abaikan jika kolom belum ada */ }
          $flash_success = 'Pendaftaran berhasil ditambahkan.';
        } catch (Throwable $e) {
          $flash_error = htmlspecialchars($e->getMessage());
        }
      } else {
        $flash_error = 'Data tidak valid.';
      }
    }
  }
}

// Ambil data pendaftaran join user & pelatihan
$pendaftarans = [];
try {
  // Total rows for pagination
  $countSql = "SELECT COUNT(*) FROM tb_pendaftaran p";
  if ($statusFilter !== '') { $countSql .= " WHERE p.status = :status"; }
  $countSt = $pdo->prepare($countSql);
  if ($statusFilter !== '') { $countSt->bindValue(':status', $statusFilter, PDO::PARAM_STR); }
  $countSt->execute();
  $totalRows = (int)$countSt->fetchColumn();
  $totalPages = max(1, (int)ceil($totalRows / $perPage));

  $sql = "SELECT p.*,
                 u.nama_lengkap, u.username, u.jenis_kelamin, u.email, u.no_wa, u.pendidikan_terakhir, u.alamat, u.foto,
                 pr.nama AS nama_provinsi, ko.nama AS nama_kabupaten, kc.nama AS nama_kecamatan, kl.nama AS nama_desa,
                 pe.nama_pelatihan
          FROM tb_pendaftaran p
          JOIN tb_user u ON p.id_user = u.id_user
          JOIN tb_pelatihan pe ON p.id_pelatihan = pe.id_pelatihan
          LEFT JOIN t_provinsi pr ON pr.id = u.id_provinsi
          LEFT JOIN t_kota ko ON ko.id = u.id_kabupaten
          LEFT JOIN t_kecamatan kc ON kc.id = u.id_kecamatan
          LEFT JOIN t_kelurahan kl ON kl.id = u.id_desa";
  if ($statusFilter !== '') { $sql .= " WHERE p.status = :status"; }
  $sql .= " ORDER BY p.created_at DESC";
  $sql .= " LIMIT :offset, :limit";
  $st = $pdo->prepare($sql);
  if ($statusFilter !== '') { $st->bindValue(':status', $statusFilter, PDO::PARAM_STR); }
  $st->bindValue(':offset', (int)$offset, PDO::PARAM_INT);
  $st->bindValue(':limit', (int)$perPage, PDO::PARAM_INT);
  $st->execute();
  $pendaftarans = $st->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
  $pendaftarans = [];
}

// Count participants per pelatihan
$countsByPelatihan = [];
try {
  $cs = $pdo->query("SELECT id_pelatihan, COUNT(*) AS total FROM tb_pendaftaran WHERE status IN ('menunggu','diterima') GROUP BY id_pelatihan");
  foreach ($cs->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $countsByPelatihan[(int)$row['id_pelatihan']] = (int)$row['total'];
  }
} catch (Throwable $e) {}
function badge_class(string $status): string {
  switch ($status) {
    case 'diterima': return 'bg-success';
    case 'ditolak':  return 'bg-danger';
    default:         return 'bg-warning text-dark';
  }
}

include_once __DIR__ . '/../../includes/header.php';
include_once __DIR__ . '/../../includes/sidebar.php';
?>
    <div class="body-wrapper">
      <?php include_once __DIR__ . '/../../includes/navbar.php'; ?>
    <div class="container-fluid">
      <div class="card">
        <div class="card-body">
          <div class="d-flex align-items-center justify-content-between mb-3">
            <h5 class="card-title mb-0">Pendaftaran</h5>
            <div class="d-flex align-items-center gap-2">
              <select id="statusFilter" class="form-select" style="max-width:180px">
                <option value="" <?php echo $statusFilter === '' ? 'selected' : ''; ?>>Semua</option>
                <option value="menunggu" <?php echo $statusFilter === 'menunggu' ? 'selected' : ''; ?>>Menunggu</option>
                <option value="diterima" <?php echo $statusFilter === 'diterima' ? 'selected' : ''; ?>>Diterima</option>
                <option value="ditolak" <?php echo $statusFilter === 'ditolak' ? 'selected' : ''; ?>>Ditolak</option>
              </select>
              <div class="input-group" style="max-width:280px;">
                <span class="input-group-text"><i class="ti ti-search"></i></span>
                <input type="text" id="searchInput" class="form-control" placeholder="Cari peserta/pelatihan...">
              </div>
              <a href="cetak.php<?php echo $statusFilter !== '' ? ('?status=' . urlencode($statusFilter)) : ''; ?>" target="_blank" class="btn btn-outline-primary"><i class="ti ti-printer me-1"></i>Cetak</a>
              <a href="pendaftaran.php<?php echo ($statusFilter !== '' ? ('?status=' . urlencode($statusFilter) . '&') : '?'); ?>export=excel" class="btn btn-outline-success"><i class="ti ti-file-spreadsheet me-1"></i>Export Excel</a>
              <a href="pendaftaran.php<?php echo ($statusFilter !== '' ? ('?status=' . urlencode($statusFilter) . '&') : '?'); ?>export=csv" class="btn btn-outline-secondary"><i class="ti ti-file-text me-1"></i>Export CSV</a>
              <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalCreateReg"><i class="ti ti-clipboard-plus me-1"></i> Tambah</button>
            </div>
          </div>

          <?php if ($flash_success): ?>
            <div class="alert alert-success" role="alert"><?php echo htmlspecialchars($flash_success); ?></div>
          <?php endif; ?>
          <?php if ($flash_error): ?>
            <div class="alert alert-danger" role="alert"><?php echo htmlspecialchars($flash_error); ?></div>
          <?php endif; ?>

          <div class="table-responsive">
            <table class="table table-striped align-middle" id="tablePendaftaran">
              <thead>
                <tr>
                  <th style="width:56px;">No</th>
                  <th>Peserta</th>
                  <th>Pelatihan</th>
                  <th>No Induk</th>
                  <th>Tanggal Daftar</th>
                  <th>Status</th>
                  <th>Berkas</th>
                  <th style="width:220px;">Aksi</th>
                </tr>
              </thead>
              <tbody>
                <?php if (!empty($pendaftarans)): ?>
                  <?php foreach ($pendaftarans as $i => $d): ?>
                    <?php
                      $id = (int)$d['id_pendaftaran'];
                      $peserta = htmlspecialchars($d['nama_lengkap'] ?? $d['username'] ?? '');
                      $pelatihan = htmlspecialchars($d['nama_pelatihan'] ?? '');
                      $tgl = htmlspecialchars($d['tanggal_daftar'] ?? '');
                      $status = strtolower($d['status'] ?? 'menunggu');
                      $ket = htmlspecialchars($d['keterangan'] ?? '');
                      $files = [];
                      if (!empty($d['file_ktp']))    $files[] = ['label' => 'KTP',                 'file' => $d['file_ktp']];
                      if (!empty($d['file_ijazah'])) $files[] = ['label' => 'Ijazah',              'file' => $d['file_ijazah']];
                      if (!empty($d['file_kartu_pencari_kerja']))   $files[] = ['label' => 'Kartu Pencari Kerja', 'file' => $d['file_kartu_pencari_kerja']];
                    ?>
                    <tr class="row-item">
                      <td><?php echo $i + 1; ?></td>
                      <td><?php echo $peserta; ?></td>
                      <td><?php
                        echo $pelatihan;
                        $pid = (int)$d['id_pelatihan'];
                        $cnt = $countsByPelatihan[$pid] ?? 0;
                        if ($cnt > 0) {
                          echo ' <span class="badge bg-primary-subtle text-primary">Ikut: ' . $cnt . '</span>';
                        }
                      ?></td>
                      <td><?php echo htmlspecialchars($d['no_induk_pendaftaran'] ?? '-'); ?></td>
                      <td><?php echo $tgl; ?></td>
                      <td><span class="badge <?php echo badge_class($status); ?> text-capitalize"><?php echo $status; ?></span></td>
                      <td>
                        <?php if (!empty($files)): ?>
                          <?php foreach ($files as $f): ?>
                            <?php
                              // Path relatif disimpan di kolom (contoh: 'uploads/pendaftaran/namafile.ext')
                              $rel = ltrim((string)$f['file'], '/');
                              $href = '../../' . htmlspecialchars($rel);
                            ?>
                            <a href="<?php echo $href; ?>" target="_blank" class="badge bg-info-subtle text-info text-decoration-none me-1">
                              <i class="ti ti-file-text me-1"></i><?php echo htmlspecialchars($f['label']); ?>
                            </a>
                          <?php endforeach; ?>
                        <?php else: ?>
                          <span class="text-muted">—</span>
                        <?php endif; ?>
                      </td>
                      <td>
                        <?php if ($status === 'menunggu') { ?>
                          <form method="post" class="d-inline">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                            <input type="hidden" name="action" value="update_status">
                            <input type="hidden" name="id_pendaftaran" value="<?php echo $id; ?>">
                            <input type="hidden" name="status" value="diterima">
                            <button type="submit" class="btn btn-success btn-sm"><i class="ti ti-checks me-1"></i>Terima</button>
                          </form>
                          <form method="post" class="d-inline ms-1">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                            <input type="hidden" name="action" value="update_status">
                            <input type="hidden" name="id_pendaftaran" value="<?php echo $id; ?>">
                            <input type="hidden" name="status" value="ditolak">
                            <button type="submit" class="btn btn-danger btn-sm"><i class="ti ti-x me-1"></i>Tolak</button>
                          </form>
                        <?php } ?>
                        <!-- Tombol detail opsional -->
                        <button type="button" class="btn btn-outline-secondary btn-sm ms-1" data-bs-toggle="collapse" data-bs-target="#det-<?php echo $id; ?>" aria-expanded="false" aria-controls="det-<?php echo $id; ?>">
                          Detail
                        </button>
                        <?php if ($status === 'menunggu') { ?>
                          <button type="button" class="btn btn-outline-primary btn-sm ms-1 btn-edit-reg"
                            data-id="<?php echo $id; ?>"
                            data-pelatihan="<?php echo (int)$d['id_pelatihan']; ?>"
                            data-status="<?php echo htmlspecialchars($status); ?>"
                            data-keterangan="<?php echo $ket; ?>">
                            <i class="ti ti-edit"></i> Edit
                          </button>
                        <?php } ?>
                        <button type="button" class="btn btn-outline-danger btn-sm ms-1 btn-delete-reg"
                          data-id="<?php echo $id; ?>"
                          data-label="<?php echo htmlspecialchars($peserta . ' — ' . $pelatihan); ?>">
                          <i class="ti ti-trash"></i> Hapus
                        </button>
                      </td>
                    </tr>
                    <tr class="collapse" id="det-<?php echo $id; ?>">
                      <td colspan="8" class="bg-light">
                        <div class="p-3">
                          <div class="row g-3">
                            <div class="col-lg-4">
                              <div class="d-flex align-items-center gap-2">
                                <?php
                                  $profile = !empty($d['foto']) ? ('../../' . ltrim($d['foto'],'/')) : '../../assets/images/profile/user-1.jpg';
                                ?>
                                <img src="<?php echo htmlspecialchars($profile); ?>" alt="Foto" width="60" height="60" class="rounded-circle">
                                <div>
                                  <div class="fw-semibold"><?php echo htmlspecialchars($d['nama_lengkap'] ?? $d['username'] ?? ''); ?></div>
                                  <div class="text-muted small"><?php echo htmlspecialchars($d['username'] ?? ''); ?></div>
                                </div>
                              </div>
                            </div>
                            <div class="col-lg-8">
                              <div class="row g-3">
                                <div class="col-md-6">
                                  <div class="small text-muted">Jenis Kelamin</div>
                                  <div><?php echo htmlspecialchars($d['jenis_kelamin'] ?? '-'); ?></div>
                                </div>
                                <div class="col-md-6">
                                  <div class="small text-muted">Email</div>
                                  <div><?php echo htmlspecialchars($d['email'] ?? '-'); ?></div>
                                </div>
                                <div class="col-md-6">
                                  <div class="small text-muted">No. WA</div>
                                  <div><?php echo htmlspecialchars($d['no_wa'] ?? '-'); ?></div>
                                </div>
                                <div class="col-md-6">
                                  <div class="small text-muted">Pendidikan Terakhir</div>
                                  <div><?php echo htmlspecialchars($d['pendidikan_terakhir'] ?? '-'); ?></div>
                                </div>
                                <div class="col-md-6">
                                  <div class="small text-muted">Alamat</div>
                                  <div><?php echo htmlspecialchars($d['alamat'] ?? '-'); ?></div>
                                </div>
                                <div class="col-md-12">
                                  <div class="small text-muted">Wilayah</div>
                                  <div><?php
                                    $wil = [];
                                    if (!empty($d['nama_provinsi'])) $wil[] = 'Provinsi: ' . $d['nama_provinsi'];
                                    if (!empty($d['nama_kabupaten'])) $wil[] = 'Kab/Kota: ' . $d['nama_kabupaten'];
                                    if (!empty($d['nama_kecamatan'])) $wil[] = 'Kecamatan: ' . $d['nama_kecamatan'];
                                    if (!empty($d['nama_desa'])) $wil[] = 'Desa: ' . $d['nama_desa'];
                                    echo htmlspecialchars(!empty($wil) ? implode(' • ', $wil) : '-');
                                  ?></div>
                                </div>
                              </div>
                            </div>
                            <div class="col-12">
                              <hr class="my-2">
                            </div>
                            <div class="col-md-6">
                              <div class="small text-muted">Keterangan Admin</div>
                              <div><?php echo $ket !== '' ? htmlspecialchars($ket) : '-'; ?></div>
                            </div>
                            <div class="col-md-6">
                              <div class="small text-muted">Info</div>
                              <div class="small text-muted">ID Pendaftaran: <?php echo $id; ?> • ID User: <?php echo (int)$d['id_user']; ?> • ID Pelatihan: <?php echo (int)$d['id_pelatihan']; ?></div>
                            </div>
                            <div class="col-12">
                              <div class="small text-muted mb-2">Berkas Unggahan</div>
                              <div class="d-flex flex-wrap gap-2">
                                <?php
                                  $makeLink = function($path) {
                                    $rel = ltrim((string)$path, '/');
                                    return '../../' . htmlspecialchars($rel);
                                  };
                                ?>
                                <?php if (!empty($d['file_ktp'])) { ?>
                                  <a href="<?php echo $makeLink($d['file_ktp']); ?>" target="_blank" class="badge bg-info-subtle text-info text-decoration-none"><i class="ti ti-file-text me-1"></i>KTP</a>
                                <?php } ?>
                                <?php if (!empty($d['file_ijazah'])) { ?>
                                  <a href="<?php echo $makeLink($d['file_ijazah']); ?>" target="_blank" class="badge bg-info-subtle text-info text-decoration-none"><i class="ti ti-file-text me-1"></i>Ijazah</a>
                                <?php } ?>
                                <?php if (!empty($d['file_kartu_pencari_kerja'])) { ?>
                                  <a href="<?php echo $makeLink($d['file_kartu_pencari_kerja']); ?>" target="_blank" class="badge bg-info-subtle text-info text-decoration-none"><i class="ti ti-file-text me-1"></i>Kartu Pencari Kerja</a>
                                <?php } ?>
                                <?php if (empty($d['file_ktp']) && empty($d['file_ijazah']) && empty($d['file_kartu_pencari_kerja'])) { ?>
                                  <span class="text-muted">—</span>
                                <?php } ?>
                              </div>
                            </div>
                          </div>
                        </div>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                <?php else: ?>
                  <tr>
                    <td colspan="7" class="text-center text-muted">Belum ada pendaftaran.</td>
                  </tr>
                <?php endif; ?>
              </tbody>
            </table>
            <?php
              $basePath = $_SERVER['PHP_SELF'] ?? '';
              $qs = [];
              if ($statusFilter !== '') { $qs['status'] = $statusFilter; }
              $qs['per_page'] = (string)$perPage;
              $makeUrl = function($p) use ($basePath, $qs) {
                $qs['page'] = (string)$p;
                return $basePath . '?' . http_build_query($qs);
              };
              $start = $offset + 1;
              $end = min($offset + $perPage, $totalRows);
            ?>
            <div class="d-flex align-items-center justify-content-between mt-3">
              <div class="text-muted">
                <?php echo $totalRows > 0 ? ('Menampilkan ' . $start . '–' . $end . ' dari ' . $totalRows) : 'Tidak ada data'; ?>
              </div>
              <nav>
                <ul class="pagination mb-0">
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
  </div>
  </div>
  </div>
  </div>
  </div>
  </div>
  
  <!-- Modal: Edit Pendaftaran -->
  <div class="modal fade" id="modalEditReg" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-md modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">Edit Pendaftaran</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <form method="post" enctype="multipart/form-data">
          <input type="hidden" name="action" value="edit">
          <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
          <input type="hidden" name="id_pendaftaran" id="edit_reg_id">
          <div class="modal-body">
            <div class="row g-3">
              <div class="col-md-6">
                <label class="form-label">Pelatihan</label>
                <select name="id_pelatihan" id="edit_reg_pelatihan" class="form-select" required>
                  <option value="">— Pilih Pelatihan —</option>
                  <?php foreach ($pelatihan_list as $pl) { ?>
                    <option value="<?php echo (int)$pl['id_pelatihan']; ?>"><?php echo htmlspecialchars($pl['nama_pelatihan']); ?></option>
                  <?php } ?>
                </select>
              </div>
              <div class="col-md-6">
                <label class="form-label">Keterangan (opsional)</label>
                <input type="text" name="keterangan" id="edit_reg_ket" class="form-control">
              </div>
              <div class="col-12"><hr></div>
              <div class="col-md-4">
                <label class="form-label small fw-bold">Update KTP</label>
                <input type="file" name="file_ktp" class="form-control form-control-sm">
              </div>
              <div class="col-md-4">
                <label class="form-label small fw-bold">Update Ijazah</label>
                <input type="file" name="file_ijazah" class="form-control form-control-sm">
              </div>
              <div class="col-md-4">
                <label class="form-label small fw-bold">Update Kartu Pencari Kerja</label>
                <input type="file" name="file_kartu_pencari_kerja" class="form-control form-control-sm">
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

  <!-- Modal: Tambah Pendaftaran -->
  <div class="modal fade" id="modalCreateReg" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">Tambah Pendaftaran</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <form method="post" enctype="multipart/form-data">
          <input type="hidden" name="action" value="create">
          <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
          <div class="modal-body">
            <div class="row g-3">
              <div class="col-md-6">
                <label class="form-label">Pelatihan</label>
                <select name="id_pelatihan" class="form-select" required>
                  <option value="">— Pilih Pelatihan —</option>
                  <?php foreach ($pelatihan_list as $pl) { ?>
                    <option value="<?php echo (int)$pl['id_pelatihan']; ?>"><?php echo htmlspecialchars($pl['nama_pelatihan']); ?></option>
                  <?php } ?>
                </select>
              </div>
              <div class="col-md-6">
                <label class="form-label">Peserta</label>
                <select name="id_user" class="form-select" required>
                  <option value="">— Pilih Peserta —</option>
                  <?php foreach ($peserta_list as $ps) { ?>
                    <option value="<?php echo (int)$ps['id_user']; ?>"><?php echo htmlspecialchars($ps['nama_lengkap']); ?></option>
                  <?php } ?>
                </select>
              </div>
              <div class="col-md-6">
                <label class="form-label">Status</label>
                <select name="status" class="form-select" required>
                  <option value="menunggu">menunggu</option>
                  <option value="diterima">diterima</option>
                  <option value="ditolak">ditolak</option>
                </select>
              </div>
              <div class="col-md-6">
                <label class="form-label">Keterangan (opsional)</label>
                <input type="text" name="keterangan" class="form-control" placeholder="Catatan admin">
              </div>
              <div class="col-12">
                <label class="form-label">No Induk Pendaftaran</label>
                <input type="text" class="form-control" value="(dibuat otomatis setelah simpan)" readonly>
              </div>
              <div class="col-12"><hr></div>
              <div class="col-md-4">
                <label class="form-label small fw-bold">File KTP</label>
                <input type="file" name="file_ktp" class="form-control form-control-sm">
              </div>
              <div class="col-md-4">
                <label class="form-label small fw-bold">File Ijazah</label>
                <input type="file" name="file_ijazah" class="form-control form-control-sm">
              </div>
              <div class="col-md-4">
                <label class="form-label small fw-bold">File Kartu Pencari Kerja</label>
                <input type="file" name="file_kartu_pencari_kerja" class="form-control form-control-sm">
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

  <!-- Modal: Hapus Pendaftaran -->
  <div class="modal fade" id="modalDeleteReg" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">Konfirmasi Hapus</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <form method="post">
          <input type="hidden" name="action" value="delete">
          <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
          <input type="hidden" name="id_pendaftaran" id="delete_reg_id">
          <div class="modal-body">
            <p>Anda yakin ingin menghapus pendaftaran <strong id="delete_reg_label"></strong>?</p>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
            <button type="submit" class="btn btn-danger">Hapus</button>
          </div>
        </form>
      </div>
    </div>
  </div>
<?php include_once __DIR__ . '/../../includes/footer.php'; ?>

  <script>
  // Pencarian sederhana klien-side
  document.addEventListener('DOMContentLoaded', function(){
    const q = document.getElementById('searchInput');
    const rows = document.querySelectorAll('#tablePendaftaran tbody .row-item');
    q?.addEventListener('input', function(){
      const term = (this.value || '').toLowerCase();
      rows.forEach(function(r){
        const t = r.innerText.toLowerCase();
        r.style.display = t.indexOf(term) !== -1 ? '' : 'none';
      });
    });
  });
  </script>
  <script>
  // Ubah filter status dengan reload halaman dan query string ?status=
  document.addEventListener('DOMContentLoaded', function(){
    const sel = document.getElementById('statusFilter');
    sel?.addEventListener('change', function(){
      const v = this.value;
      const base = window.location.pathname;
      const url = v ? (base + '?status=' + encodeURIComponent(v)) : base;
      window.location.href = url;
    });
  });
  </script>
<script>
  // Edit & Delete modal binding
  document.addEventListener('DOMContentLoaded', function(){
    document.querySelectorAll('.btn-edit-reg').forEach(function(btn){
      btn.addEventListener('click', function(){
        document.getElementById('edit_reg_id').value = this.dataset.id || '';
        document.getElementById('edit_reg_pelatihan').value = (this.dataset.pelatihan || '');
        document.getElementById('edit_reg_ket').value = this.dataset.keterangan || '';
        var modal = new bootstrap.Modal(document.getElementById('modalEditReg'));
        modal.show();
      });
    });
    document.querySelectorAll('.btn-delete-reg:not([disabled])').forEach(function(btn){
      btn.addEventListener('click', function(){
        document.getElementById('delete_reg_id').value = this.dataset.id || '';
        document.getElementById('delete_reg_label').innerText = this.dataset.label || '';
        var modal = new bootstrap.Modal(document.getElementById('modalDeleteReg'));
        modal.show();
      });
    });
  });
</script>
