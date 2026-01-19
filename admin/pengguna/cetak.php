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

// Ambil parameter filter dari GET
$filter_role = isset($_GET['role']) && $_GET['role'] !== '' ? trim($_GET['role']) : '';
$filter_status = isset($_GET['status']) && $_GET['status'] !== '' ? trim($_GET['status']) : '';
$filter_jk = isset($_GET['jenis_kelamin']) && $_GET['jenis_kelamin'] !== '' ? trim($_GET['jenis_kelamin']) : '';
$filter_kecamatan = isset($_GET['kecamatan']) && $_GET['kecamatan'] !== '' ? trim($_GET['kecamatan']) : '';
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

// Ambil data pengguna dengan filter
$users = [];
try {
  $extra = '';
  if ($hasNik) { $extra .= ", u.nik"; }
  if ($hasTempatLahir) { $extra .= ", u.tempat_lahir"; }
  if ($hasTanggalLahir) { $extra .= ", u.tanggal_lahir"; }
  $sql = "SELECT u.id_user, u.nama_lengkap, u.jenis_kelamin, u.role, u.email, u.no_wa, u.pendidikan_terakhir, u.alamat, u.status, u.created_at" . $extra . ",
          p.nama as nama_provinsi, k.nama as nama_kabupaten, kc.nama as nama_kecamatan, d.nama as nama_desa
          FROM tb_user u
          LEFT JOIN t_provinsi p ON u.id_provinsi = p.id
          LEFT JOIN t_kota k ON u.id_kabupaten = k.id
          LEFT JOIN t_kecamatan kc ON u.id_kecamatan = kc.id
          LEFT JOIN t_kelurahan d ON u.id_desa = d.id
          WHERE 1=1";
  $params = [];
  
  if ($filter_role !== '') {
    $sql .= " AND role = ?";
    $params[] = $filter_role;
  }
  if ($filter_status !== '') {
    $sql .= " AND status = ?";
    $params[] = $filter_status;
  }
  if ($filter_jk !== '') {
    $sql .= " AND jenis_kelamin = ?";
    $params[] = $filter_jk;
  }
  if ($filter_kecamatan !== '') {
    $sql .= " AND kc.nama = ?";
    $params[] = $filter_kecamatan;
  }
  
  $sql .= " ORDER BY created_at DESC";
  
  $stmt = $pdo->prepare($sql);
  $stmt->execute($params);
  $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
  $users = [];
}

// Info dicetak
$printedAt = date('d/m/Y H:i');
$printedBy = htmlspecialchars($_SESSION['auth']['username'] ?? 'admin');
?>
<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Cetak Data Pengguna</title>
  <style>
    :root {
      --text-color: #000;
    }
    body {
      color: var(--text-color);
      font-family: "Times New Roman", Times, serif;
      font-size: 12pt;
      margin: 0;
      padding: 0 12mm; /* sedikit margin kiri kanan */
    }
    .letterhead {
      display: grid;
      grid-template-columns: 100px 1fr;
      align-items: center;
      column-gap: 14px;
      padding-top: 8mm;
      padding-bottom: 4mm;
      border-bottom: 2px solid #000;
      margin-bottom: 6mm;
    }
    .letterhead img.logo {
      height: 90px;
      width: auto;
      object-fit: contain;
    }
    .org-lines { text-align: center; }
    .org-lines .line1 { font-size: 14pt; font-weight: 700; text-transform: uppercase; }
    .org-lines .line2 { font-size: 16pt; font-weight: 700; text-transform: uppercase; }
    .org-lines .line3 { font-size: 14pt; font-weight: 700; text-transform: uppercase; }
    .org-lines .line4 { font-size: 11pt; }
    .title {
      text-align: center;
      font-weight: 700;
      margin: 8mm 0 4mm;
      font-size: 14pt;
      text-transform: uppercase;
    }
    .meta {
      display: flex;
      justify-content: space-between;
      font-size: 10pt;
      margin-bottom: 4mm;
    }
    table {
      width: 100%;
      border-collapse: collapse;
      font-size: 11pt;
    }
    th, td {
      border: 1px solid #000;
      padding: 6px 8px;
    }
    th {
      font-weight: 700;
      text-align: left;
    }
    tfoot td {
      border: none;
      padding-top: 6mm;
      font-size: 10pt;
    }
    @page { size: A4; margin: 12mm; }
    @media print {
      .no-print { display: none !important; }
      body { padding: 0; }
    }
    .actions.no-print { margin: 6mm 0; }
  </style>
</head>
<body onload="window.print()">
  <div class="letterhead">
    <img class="logo" src="../../assets/images/logos/logo-hsu.png" alt="Logo HSU">
    <div class="org-lines">
      <div class="line1">PEMERINTAH KABUPATEN HULU SUNGAI UTARA</div>
      <div class="line2">DINAS PENANAMAN MODAL DAN PELAYANAN TERPADU SATU PINTU</div>
      <div class="line3">BALAI LATIHAN KERJA AMUNTAI</div>
      <div class="line4">Alamat: Kota Raja, Kec. Amuntai Sel., Kabupaten Hulu Sungai Utara, Kalimantan Selatan 71419</div>
    </div>
  </div>

  <div class="title">Data Pengguna</div>
  
  <?php 
  // Tampilkan info filter jika ada
  $hasFilter = $filter_role || $filter_status || $filter_jk || $filter_kecamatan;
  if ($hasFilter) {
  ?>
  <div style="margin-bottom: 4mm; padding: 6px 10px; background: #f0f0f0; border-left: 3px solid #0E4DA4;">
    <strong>Filter Aktif:</strong>
    <?php 
    $filters = [];
    if ($filter_role) $filters[] = "Role: " . htmlspecialchars(ucfirst($filter_role));
    if ($filter_status) $filters[] = "Status: " . htmlspecialchars(ucfirst($filter_status));
    if ($filter_jk) $filters[] = "Jenis Kelamin: " . htmlspecialchars($filter_jk);
    if ($filter_kecamatan) $filters[] = "Kecamatan: " . htmlspecialchars($filter_kecamatan);
    echo implode(' | ', $filters);
    ?>
  </div>
  <?php } ?>
  
  <div class="meta">
    <div>Dicetak: <?php echo htmlspecialchars($printedAt); ?></div>
    <div>Petugas: <?php echo $printedBy; ?></div>
  </div>

  <table>
    <thead>
      <tr>
        <th style="width: 40px;">No</th>
        <th>Nama Lengkap</th>
        <?php if ($hasNik) { ?><th>NIK</th><?php } ?>
        <th>Jenis Kelamin</th>
        <?php if ($hasTempatLahir) { ?><th>Tempat Lahir</th><?php } ?>
        <?php if ($hasTanggalLahir) { ?><th>Tanggal Lahir</th><?php } ?>
        <th>Role</th>
        <th>Email</th>
        <th>No WA</th>
        <th>Pendidikan Terakhir</th>
        <th>Alamat Lengkap</th>
        <th>Status</th>
      </tr>
    </thead>
    <tbody>
    <?php if (!empty($users)) { $no = 1; foreach ($users as $u) { 
      $alamat_lengkap = [];
      if (!empty($u['alamat'])) $alamat_lengkap[] = $u['alamat'];
      if (!empty($u['nama_desa'])) $alamat_lengkap[] = 'Desa ' . ucwords(strtolower($u['nama_desa']));
      if (!empty($u['nama_kecamatan'])) $alamat_lengkap[] = 'Kec. ' . ucwords(strtolower($u['nama_kecamatan']));
      if (!empty($u['nama_kabupaten'])) $alamat_lengkap[] = ucwords(strtolower($u['nama_kabupaten']));
      if (!empty($u['nama_provinsi'])) $alamat_lengkap[] = ucwords(strtolower($u['nama_provinsi']));
      $str_alamat = implode(', ', $alamat_lengkap);
    ?>
      <tr>
        <td style="text-align:center;"><?php echo $no++; ?></td>
        <td><?php echo htmlspecialchars($u['nama_lengkap']); ?></td>
        <?php if ($hasNik) { ?><td><?php echo htmlspecialchars($u['nik'] ?? '-'); ?></td><?php } ?>
        <td><?php echo htmlspecialchars($u['jenis_kelamin'] ?? '-'); ?></td>
        <?php if ($hasTempatLahir) { ?><td><?php echo htmlspecialchars($u['tempat_lahir'] ?? '-'); ?></td><?php } ?>
        <?php if ($hasTanggalLahir) { 
          $tgl = !empty($u['tanggal_lahir']) ? date('d/m/Y', strtotime($u['tanggal_lahir'])) : '-';
        ?><td><?php echo htmlspecialchars($tgl); ?></td><?php } ?>
        <td><?php echo htmlspecialchars($u['role']); ?></td>
        <td><?php echo htmlspecialchars($u['email'] ?? '-'); ?></td>
        <td><?php echo htmlspecialchars($u['no_wa'] ?? '-'); ?></td>
        <td><?php echo htmlspecialchars($u['pendidikan_terakhir'] ?? '-'); ?></td>
        <td><?php echo htmlspecialchars($str_alamat ?: '-'); ?></td>
        <td><?php echo htmlspecialchars($u['status']); ?></td>
      </tr>
    <?php } } else { ?>
      <tr>
        <td colspan="8" style="text-align:center;">Tidak ada data pengguna.</td>
      </tr>
    <?php } ?>
    </tbody>
  </table>

  <!-- Tanda tangan -->
  <div style="display:flex; justify-content:flex-end; margin-top: 10mm;">
    <div style="text-align:right;">
      <div style="font-weight:700;">Kepala BLK Amuntai</div>
      <div style="height: 24mm;"></div>
      <div style="font-weight:700; text-transform: uppercase;">AHMAD HUMAIDI,ST</div>
      <div>Penata Tk.I</div>
      <div>NIP.19760226 2008 1 017</div>
    </div>
  </div>

  <div class="actions no-print">
    <button onclick="window.print()">Cetak</button>
    <button onclick="window.close()">Tutup</button>
  </div>
</body>
</html>
