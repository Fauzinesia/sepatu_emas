<?php
session_start();
require_once __DIR__ . '/../config/koneksi.php';
if (!isset($_SESSION['auth']['logged_in']) || $_SESSION['auth']['logged_in'] !== true) {
  header('Location: ../login.php');
  exit;
}
if (strtolower($_SESSION['auth']['role'] ?? '') !== 'peserta') {
  header('Location: ../admin/dashboard.php');
  exit;
}
$page_title = 'Semua Pelatihan';
if (empty($_SESSION['csrf_token'])) {
  $_SESSION['csrf_token'] = bin2hex(random_bytes(16));
}
$csrf = $_SESSION['csrf_token'];
$notice = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'daftar_pelatihan') {
  try {
    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
      throw new RuntimeException('Token CSRF tidak valid.');
    }
    $uid = (int)($_SESSION['auth']['id_user'] ?? 0);
    $id_pelatihan = (int)($_POST['id_pelatihan'] ?? 0);
    if ($uid <= 0 || $id_pelatihan <= 0) {
      throw new RuntimeException('Data pendaftaran tidak valid.');
    }
    
    // Cek apakah user sedang mendaftar/mengikuti pelatihan lain (status menunggu/diterima)
    $cekAktif = $pdo->prepare("SELECT p.status, pl.nama_pelatihan 
                               FROM tb_pendaftaran p 
                               JOIN tb_pelatihan pl ON p.id_pelatihan = pl.id_pelatihan 
                               WHERE p.id_user = ? AND p.status IN ('menunggu','diterima') AND p.id_pelatihan != ? 
                               LIMIT 1");
    $cekAktif->execute([$uid, $id_pelatihan]);
    $existing = $cekAktif->fetch(PDO::FETCH_ASSOC);
    if ($existing) {
      throw new RuntimeException('Anda sedang terdaftar di pelatihan "' . $existing['nama_pelatihan'] . '" (' . ucfirst($existing['status']) . '). Tidak dapat mendaftar pelatihan lain.');
    }

    $cekDup = $pdo->prepare('SELECT COUNT(*) FROM tb_pendaftaran WHERE id_user = ? AND id_pelatihan = ?');
    $cekDup->execute([$uid, $id_pelatihan]);
    if ((int)$cekDup->fetchColumn() > 0) {
      throw new RuntimeException('Anda sudah mendaftar pada pelatihan ini.');
    }
    $stK = $pdo->prepare('SELECT kuota, status FROM tb_pelatihan WHERE id_pelatihan = ?');
    $stK->execute([$id_pelatihan]);
    $rowK = $stK->fetch(PDO::FETCH_ASSOC);
    if (!$rowK || strtolower($rowK['status'] ?? '') !== 'aktif') {
      throw new RuntimeException('Pelatihan tidak tersedia.');
    }
    $kuota = (int)($rowK['kuota'] ?? 0);
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
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$perPage = 9;
$q = isset($_GET['q']) ? trim($_GET['q']) : '';
$countSql = "SELECT COUNT(*) FROM tb_pelatihan WHERE status='aktif'";
$countParams = [];
if ($q !== '') {
  $countSql .= " AND (nama_pelatihan LIKE :q OR deskripsi LIKE :q OR lokasi LIKE :q)";
  $countParams[':q'] = '%' . $q . '%';
}
$totalRows = 0;
try {
  $cs = $pdo->prepare($countSql);
  $cs->execute($countParams);
  $totalRows = (int)$cs->fetchColumn();
} catch (Throwable $e) { $totalRows = 0; }
$totalPages = max(1, (int)ceil($totalRows / $perPage));
if ($page > $totalPages) $page = $totalPages;
$offset = ($page - 1) * $perPage;
$sql = "SELECT id_pelatihan, nama_pelatihan, nama_instruktur, kategori, lokasi, deskripsi, tanggal_mulai, tanggal_selesai, foto, kuota 
        FROM tb_pelatihan 
        WHERE status='aktif'";
$params = [];
if ($q !== '') {
  $sql .= " AND (nama_pelatihan LIKE :q OR deskripsi LIKE :q OR lokasi LIKE :q)";
  $params[':q'] = '%' . $q . '%';
}
$sql .= " ORDER BY id_pelatihan DESC LIMIT :limit OFFSET :offset";
$st = $pdo->prepare($sql);
foreach ($params as $k => $v) { $st->bindValue($k, $v); }
$st->bindValue(':limit', (int)$perPage, PDO::PARAM_INT);
$st->bindValue(':offset', (int)$offset, PDO::PARAM_INT);
$st->execute();
$pelatihans = $st->fetchAll(PDO::FETCH_ASSOC);
$countsByPel = [];
try {
  $cs2 = $pdo->query("SELECT id_pelatihan, COUNT(*) AS total FROM tb_pendaftaran WHERE status IN ('menunggu','diterima') GROUP BY id_pelatihan");
  foreach ($cs2->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $countsByPel[(int)$row['id_pelatihan']] = (int)$row['total'];
  }
} catch (Throwable $e) {}
$myRegs = [];
try {
  $mr = $pdo->prepare("SELECT DISTINCT id_pelatihan FROM tb_pendaftaran WHERE id_user = ?");
  $mr->execute([$_SESSION['auth']['id_user'] ?? 0]);
  foreach ($mr->fetchAll(PDO::FETCH_ASSOC) as $r) {
    $myRegs[(int)$r['id_pelatihan']] = true;
  }
} catch (Throwable $e) {}

// Cek apakah punya pendaftaran aktif (menunggu/diterima) di pelatihan mana pun
$hasActiveRegistration = false;
try {
  $chk = $pdo->prepare("SELECT COUNT(*) FROM tb_pendaftaran WHERE id_user = ? AND status IN ('menunggu','diterima')");
  $chk->execute([$_SESSION['auth']['id_user'] ?? 0]);
  $hasActiveRegistration = ((int)$chk->fetchColumn() > 0);
} catch (Throwable $e) {}

function excerpt_text(string $text, int $max = 120): string {
  $text = trim(strip_tags($text));
  if (mb_strlen($text) <= $max) return $text;
  $cut = mb_substr($text, 0, $max);
  $lastSpace = mb_strrpos($cut, ' ');
  if ($lastSpace !== false) $cut = mb_substr($cut, 0, $lastSpace);
  return $cut . '…';
}
function format_date_id(?string $date): string {
  if (!$date) return '';
  try {
    $dt = new DateTime($date);
    $bulan = [1=>'Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];
    $m = (int)$dt->format('n');
    return $dt->format('j') . ' ' . ($bulan[$m] ?? $dt->format('F')) . ' ' . $dt->format('Y');
  } catch (Throwable $e) { return $date; }
}
?>
<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?php echo isset($page_title) ? $page_title.' — SEPATU EMAS' : 'SEPATU EMAS'; ?></title>
  <link rel="shortcut icon" type="image/png" href="../assets/images/logos/favicon.png" />
  <link rel="stylesheet" href="../assets/css/styles.min.css" />
</head>
<body>
  <div class="page-wrapper" id="main-wrapper" data-layout="vertical" data-navbarbg="skin6" data-sidebartype="full" data-sidebar-position="fixed" data-header-position="fixed">
    <?php include_once __DIR__ . '/../includes/sidebar.php'; ?>
    <div class="body-wrapper">
      <?php include_once __DIR__ . '/../includes/navbar.php'; ?>
      <div class="container-fluid">
        <div class="d-flex align-items-center justify-content-between mb-3">
          <h5 class="card-title fw-semibold mb-0">Semua Pelatihan Aktif</h5>
          <div class="input-group" style="max-width: 320px;">
            <span class="input-group-text"><i class="ti ti-search"></i></span>
            <input type="text" id="searchInput" class="form-control" placeholder="Cari nama atau deskripsi..." value="<?php echo htmlspecialchars($q); ?>">
          </div>
        </div>
        <?php if (!empty($notice)) { ?>
          <div class="alert alert-info" role="alert"><?php echo htmlspecialchars($notice); ?></div>
        <?php } ?>
        <?php if ($totalRows === 0) { ?>
          <div class="alert alert-info">Belum ada pelatihan aktif.</div>
        <?php } ?>
        <div class="row g-4" id="listPelatihan">
          <?php foreach ($pelatihans as $p):
            $pid = (int)($p['id_pelatihan'] ?? 0);
            $title = htmlspecialchars($p['nama_pelatihan'] ?? 'Pelatihan');
            $desc = htmlspecialchars(excerpt_text($p['deskripsi'] ?? ''));
            $mulai = format_date_id($p['tanggal_mulai'] ?? null);
            $selesai = format_date_id($p['tanggal_selesai'] ?? null);
            $instruktur = htmlspecialchars($p['nama_instruktur'] ?? '');
            $kategori = htmlspecialchars(strtoupper($p['kategori'] ?? ''));
            $lokasi = htmlspecialchars(strtoupper($p['lokasi'] ?? ''));
            $foto = $p['foto'] ?? '';
            $fotoPath = '';
            if (!empty($foto)) {
              $candidate = __DIR__ . '/../assets/images/products/' . $foto;
              if (is_file($candidate)) {
                $fotoPath = '../assets/images/products/' . htmlspecialchars($foto);
              }
            }
            $cnt = (int)($countsByPel[$pid] ?? 0);
            $ku = (int)($p['kuota'] ?? 0);
            $full = ($ku > 0 && $cnt >= $ku);
            $already = !empty($myRegs[$pid]);
            // Jika sudah terdaftar di SINI, abaikan flag global hasActiveRegistration (karena 'active' itu ya ini)
            // Tapi jika belum terdaftar di SINI, dan punya active registration di tempat lain -> block
            $blockedByOther = (!$already && $hasActiveRegistration);
          ?>
          <div class="col-md-4 pelatihan-item">
            <div class="card h-100">
              <?php if ($fotoPath): ?>
                <div class="ratio ratio-16x9">
                  <img src="<?php echo $fotoPath; ?>" class="card-img-top" alt="<?php echo $title; ?>">
                </div>
              <?php endif; ?>
              <div class="card-body">
                <h5 class="card-title mb-1"><?php echo $title; ?></h5>
                <?php if (!empty($instruktur) || !empty($kategori) || !empty($lokasi)): ?>
                  <p class="card-text text-muted mb-2">
                    <?php if (!empty($instruktur)) { ?>Instruktur: <?php echo $instruktur; ?><?php } ?>
                    <?php if (!empty($kategori)) { echo (!empty($instruktur) ? ' · ' : ''); ?>Kategori: <?php echo $kategori; ?><?php } ?>
                    <?php if (!empty($lokasi)) { echo ((!empty($instruktur) || !empty($kategori)) ? ' · ' : ''); ?>Lokasi: <?php echo $lokasi; ?><?php } ?>
                  </p>
                <?php endif; ?>
                <?php if ($mulai || $selesai): ?>
                  <p class="card-text text-muted mb-2"><?php echo ($mulai ?: '-') . ' — ' . ($selesai ?: '-'); ?></p>
                <?php endif; ?>
                <?php if (!empty($desc)): ?>
                  <p class="card-text"><?php echo $desc; ?></p>
                <?php endif; ?>
                <div class="d-flex align-items-center gap-2">
                  <?php if ($ku > 0) { ?>
                    <span class="badge bg-primary-subtle text-primary">Kuota: <?php echo $ku; ?></span>
                  <?php } ?>
                  <span class="badge bg-info-subtle text-info">Terdaftar: <?php echo $cnt; ?></span>
                </div>
                <div class="mt-2 d-flex gap-2">
                  <a href="<?php echo ($urlBase ?? '/'); ?>peserta/pelatihan-detail.php?id=<?php echo urlencode($pid); ?>" class="btn btn-outline-secondary btn-sm">Detail</a>
                  <form method="post" class="d-inline">
                    <input type="hidden" name="action" value="daftar_pelatihan">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf); ?>">
                    <input type="hidden" name="id_pelatihan" value="<?php echo $pid; ?>">
                    <button type="submit" class="btn btn-primary btn-sm" <?php echo ($full || $already || $blockedByOther) ? 'disabled' : ''; ?>>
                      <?php echo $already ? 'Sudah Terdaftar' : ($blockedByOther ? 'Sedang Mendaftar Lain' : ($full ? 'Kuota Penuh' : 'Daftar')); ?>
                    </button>
                  </form>
                </div>
              </div>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
        <?php if ($totalRows > 0) { ?>
        <div class="d-flex align-items-center justify-content-between mt-3">
          <div class="text-muted small">
            Menampilkan <?php echo $offset + 1; ?>–<?php echo min($offset + $perPage, $totalRows); ?> dari <?php echo $totalRows; ?> data
          </div>
          <nav>
            <ul class="pagination pagination-sm mb-0">
              <?php
                $makeUrl = function($p) use ($q) {
                  $qs = ['page' => $p];
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
        <?php } ?>
      </div>
    </div>
  </div>
  <script src="../assets/libs/bootstrap/dist/js/bootstrap.bundle.min.js"></script>
  <script>
    document.addEventListener('DOMContentLoaded', function(){
      var input = document.getElementById('searchInput');
      if (input) {
        input.addEventListener('keydown', function(e){
          if (e.key === 'Enter') {
            var q = this.value || '';
            var params = new URLSearchParams(window.location.search);
            if (q) { params.set('q', q); } else { params.delete('q'); }
            params.delete('page');
            window.location.search = params.toString();
          }
        });
      }
    });
  </script>
</body>
</html>
