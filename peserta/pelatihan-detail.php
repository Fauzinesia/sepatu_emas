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
if (empty($_SESSION['csrf_token'])) {
  $_SESSION['csrf_token'] = bin2hex(random_bytes(16));
}
$csrf = $_SESSION['csrf_token'];
$notice = '';
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'daftar_pelatihan') {
  try {
    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) throw new RuntimeException('Token CSRF tidak valid.');
    $uid = (int)($_SESSION['auth']['id_user'] ?? 0);
    $pid = (int)($_POST['id_pelatihan'] ?? 0);
    if ($uid <= 0 || $pid <= 0) throw new RuntimeException('Data pendaftaran tidak valid.');
    $dup = $pdo->prepare('SELECT COUNT(*) FROM tb_pendaftaran WHERE id_user = ? AND id_pelatihan = ?');
    $dup->execute([$uid, $pid]);
    if ((int)$dup->fetchColumn() > 0) throw new RuntimeException('Anda sudah mendaftar pada pelatihan ini.');
    $stK = $pdo->prepare('SELECT kuota, status FROM tb_pelatihan WHERE id_pelatihan = ?');
    $stK->execute([$pid]);
    $rowK = $stK->fetch(PDO::FETCH_ASSOC);
    if (!$rowK || strtolower($rowK['status'] ?? '') !== 'aktif') throw new RuntimeException('Pelatihan tidak tersedia.');
    $kuota = (int)($rowK['kuota'] ?? 0);
    if ($kuota > 0) {
      $stCount = $pdo->prepare("SELECT COUNT(*) FROM tb_pendaftaran WHERE id_pelatihan = ? AND status IN ('menunggu','diterima')");
      $stCount->execute([$pid]);
      $totalReg = (int)$stCount->fetchColumn();
      if ($totalReg >= $kuota) throw new RuntimeException('Kuota pendaftaran pelatihan sudah penuh.');
    }
    $ins = $pdo->prepare('INSERT INTO tb_pendaftaran (id_user, id_pelatihan, status, tanggal_daftar) VALUES (?,?,?,?)');
    $ins->execute([$uid, $pid, 'menunggu', date('Y-m-d')]);
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
$detail = null;
try {
  $st = $pdo->prepare("SELECT p.id_pelatihan, p.nama_pelatihan, p.nama_instruktur, p.kategori, p.lokasi, p.deskripsi, p.tanggal_mulai, p.tanggal_selesai, p.foto, p.kuota, p.status, b.nama_bidang
                       FROM tb_pelatihan p
                       LEFT JOIN tb_bidang b ON b.id_bidang = p.id_bidang
                       WHERE p.id_pelatihan = ?");
  $st->execute([$id]);
  $detail = $st->fetch(PDO::FETCH_ASSOC);
} catch (Throwable $e) {}
if (!$detail) { $detail = []; }
$counts = 0;
try {
  $cs = $pdo->prepare("SELECT COUNT(*) FROM tb_pendaftaran WHERE id_pelatihan = ? AND status IN ('menunggu','diterima')");
  $cs->execute([$id]);
  $counts = (int)$cs->fetchColumn();
} catch (Throwable $e) {}
$already = false;
try {
  $mr = $pdo->prepare("SELECT COUNT(*) FROM tb_pendaftaran WHERE id_user = ? AND id_pelatihan = ?");
  $mr->execute([$_SESSION['auth']['id_user'] ?? 0, $id]);
  $already = ((int)$mr->fetchColumn() > 0);
} catch (Throwable $e) {}
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
  <title><?php echo htmlspecialchars($detail['nama_pelatihan'] ?? 'Detail Pelatihan'); ?> — SEPATU EMAS</title>
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
          <h5 class="card-title fw-semibold mb-0"><?php echo htmlspecialchars($detail['nama_pelatihan'] ?? 'Detail Pelatihan'); ?></h5>
          <a href="pelatihan.php" class="btn btn-outline-secondary btn-sm">Kembali</a>
        </div>
        <?php if (!empty($notice)) { ?><div class="alert alert-info" role="alert"><?php echo htmlspecialchars($notice); ?></div><?php } ?>
        <div class="row g-4">
          <div class="col-lg-5">
            <div class="card h-100">
              <?php
                $foto = $detail['foto'] ?? '';
                $fotoPath = '';
                if (!empty($foto)) {
                  $candidate = __DIR__ . '/../assets/images/products/' . $foto;
                  if (is_file($candidate)) {
                    $fotoPath = '../assets/images/products/' . htmlspecialchars($foto);
                  }
                }
              ?>
              <?php if ($fotoPath) { ?>
              <div class="ratio ratio-16x9">
                <img src="<?php echo $fotoPath; ?>" class="card-img-top" alt="<?php echo htmlspecialchars($detail['nama_pelatihan'] ?? ''); ?>">
              </div>
              <?php } ?>
            </div>
          </div>
          <div class="col-lg-7">
            <div class="card h-100">
              <div class="card-body">
                <div class="mb-2 text-muted">
                  <?php if (!empty($detail['nama_bidang'])) { echo 'Bidang: ' . htmlspecialchars($detail['nama_bidang']); } ?>
                </div>
                <p class="mb-2 text-muted">
                  <?php
                    $instruktur = htmlspecialchars($detail['nama_instruktur'] ?? '');
                    $kategori = htmlspecialchars(strtoupper($detail['kategori'] ?? ''));
                    $lokasi = htmlspecialchars(strtoupper($detail['lokasi'] ?? ''));
                    $parts = [];
                    if ($instruktur) $parts[] = 'Instruktur: ' . $instruktur;
                    if ($kategori) $parts[] = 'Kategori: ' . $kategori;
                    if ($lokasi) $parts[] = 'Lokasi: ' . $lokasi;
                    echo implode(' · ', $parts);
                  ?>
                </p>
                <p class="mb-2 text-muted">
                  <?php echo format_date_id($detail['tanggal_mulai'] ?? null) . ' — ' . format_date_id($detail['tanggal_selesai'] ?? null); ?>
                </p>
                <p><?php echo htmlspecialchars($detail['deskripsi'] ?? ''); ?></p>
                <div class="d-flex align-items-center gap-2 mb-3">
                  <?php $ku = (int)($detail['kuota'] ?? 0); if ($ku > 0) { ?>
                    <span class="badge bg-primary-subtle text-primary">Kuota: <?php echo $ku; ?></span>
                  <?php } ?>
                  <span class="badge bg-info-subtle text-info">Terdaftar: <?php echo (int)$counts; ?></span>
                </div>
                <form method="post" class="d-inline">
                  <input type="hidden" name="action" value="daftar_pelatihan">
                  <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf); ?>">
                  <input type="hidden" name="id_pelatihan" value="<?php echo (int)$id; ?>">
                  <?php
                    $full = ($ku > 0 && $counts >= $ku);
                    $disabled = ($full || $already || strtolower($detail['status'] ?? '') !== 'aktif');
                  ?>
                  <button type="submit" class="btn btn-primary" <?php echo $disabled ? 'disabled' : ''; ?>>
                    <?php
                      if (strtolower($detail['status'] ?? '') !== 'aktif') echo 'Tidak Tersedia';
                      else echo $already ? 'Sudah Terdaftar' : ($full ? 'Kuota Penuh' : 'Daftar');
                    ?>
                  </button>
                </form>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
  <script src="../assets/libs/bootstrap/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
