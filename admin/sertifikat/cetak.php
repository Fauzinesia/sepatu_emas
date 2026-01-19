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

$idPel = isset($_GET['id_pelatihan']) ? (int)$_GET['id_pelatihan'] : 0;
$q = trim($_GET['q'] ?? '');

$sql = "SELECT s.id_sertifikat, s.nomor_sertifikat, s.tanggal_terbit, s.file_sertifikat,
               u.nama_lengkap,
               p.nama_pelatihan,
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
$sql .= " ORDER BY s.tanggal_terbit DESC, p.nama_pelatihan ASC, u.nama_lengkap ASC";
$st = $pdo->prepare($sql);
$st->execute($params);
$rows = $st->fetchAll(PDO::FETCH_ASSOC);
?>
<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Cetak Data Sertifikat</title>
  <style>
    :root { --text-color: #000; }
    body { color: var(--text-color); font-family: "Times New Roman", Times, serif; font-size: 12pt; margin: 0; padding: 0 12mm; }
    .letterhead { display: grid; grid-template-columns: 100px 1fr; align-items: center; column-gap: 14px; padding-top: 8mm; padding-bottom: 4mm; border-bottom: 2px solid #000; margin-bottom: 6mm; }
    .letterhead img.logo { height: 90px; width: auto; object-fit: contain; }
    .org-lines { text-align: center; }
    .org-lines .line1 { font-size: 14pt; font-weight: 700; text-transform: uppercase; }
    .org-lines .line2 { font-size: 16pt; font-weight: 700; text-transform: uppercase; }
    .org-lines .line3 { font-size: 14pt; font-weight: 700; text-transform: uppercase; }
    .org-lines .line4 { font-size: 11pt; }
    .title { text-align: center; font-weight: 700; margin: 8mm 0 4mm; font-size: 14pt; text-transform: uppercase; }
    .meta { display: flex; justify-content: space-between; font-size: 10pt; margin-bottom: 4mm; }
    table { width: 100%; border-collapse: collapse; font-size: 11pt; }
    th, td { border: 1px solid #000; padding: 6px 8px; }
    th { font-weight: 700; text-align: left; }
    @page { size: A4; margin: 12mm; }
    @media print { .no-print { display: none !important; } body { padding: 0; } }
    .actions.no-print { margin: 6mm 0; }
  </style>
</head>
<body onload="window.print()">
  <?php $printedAt = date('d/m/Y H:i'); $printedBy = htmlspecialchars($_SESSION['auth']['username'] ?? 'admin'); ?>
  <div class="letterhead">
    <img class="logo" src="../../assets/images/logos/logo-hsu.png" alt="Logo HSU">
    <div class="org-lines">
      <div class="line1">PEMERINTAH KABUPATEN HULU SUNGAI UTARA</div>
      <div class="line2">DINAS PENANAMAN MODAL DAN PELAYANAN TERPADU SATU PINTU</div>
      <div class="line3">BALAI LATIHAN KERJA AMUNTAI</div>
      <div class="line4">Alamat: Kota Raja, Kec. Amuntai Sel., Kabupaten Hulu Sungai Utara, Kalimantan Selatan 71419</div>
    </div>
  </div>

  <div class="title">Data Sertifikat</div>
  <div class="meta">
    <div>Dicetak: <?php echo $printedAt; ?></div>
    <div>Petugas: <?php echo $printedBy; ?></div>
  </div>

  <table>
    <thead>
      <tr>
        <th style="width:50px;">No</th>
        <th>Nama Peserta</th>
        <th>Pelatihan</th>
        <th style="width:140px;">Hasil</th>
        <th style="width:160px;">No Induk</th>
        <th>Nomor Sertifikat</th>
        <th style="width:120px;">Tanggal Terbit</th>
      </tr>
    </thead>
    <tbody>
      <?php if (empty($rows)): ?>
        <tr>
          <td colspan="6" style="text-align:center;">Belum ada data.</td>
        </tr>
      <?php else: ?>
        <?php $no = 1; foreach ($rows as $r): ?>
          <tr>
            <td><?php echo $no++; ?></td>
            <td><?php echo htmlspecialchars($r['nama_lengkap']); ?></td>
            <td><?php echo htmlspecialchars($r['nama_pelatihan']); ?></td>
            <td><?php echo isset($r['keterangan']) && $r['keterangan'] !== null ? htmlspecialchars($r['keterangan']) : '-'; ?></td>
            <td><?php echo htmlspecialchars($r['no_induk_pendaftaran'] ?? '-'); ?></td>
            <td><?php echo htmlspecialchars($r['nomor_sertifikat'] ?? ''); ?></td>
            <td><?php echo htmlspecialchars($r['tanggal_terbit'] ?? ''); ?></td>
          </tr>
        <?php endforeach; ?>
      <?php endif; ?>
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
