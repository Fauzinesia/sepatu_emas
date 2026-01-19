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

// Ambil data nilai bergabung dengan user & pelatihan
$nilais = [];
try {
  $stmt = $pdo->query(
    "SELECT n.id_nilai, u.nama_lengkap, pl.nama_pelatihan, n.keterangan
     FROM tb_nilai n
     JOIN tb_user u ON u.id_user = n.id_user
     JOIN tb_pelatihan pl ON pl.id_pelatihan = n.id_pelatihan
     ORDER BY n.id_nilai DESC"
  );
  $nilais = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
  $nilais = [];
}

// Info dicetak (samakan gaya dengan pelatihan/cetak.php)
$printedAt = date('d/m/Y H:i');
$printedBy = htmlspecialchars($_SESSION['auth']['username'] ?? 'admin');
?>
<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Cetak Nilai Peserta</title>
  <style>
    :root {
      --text-color: #000;
    }
    body {
      color: var(--text-color);
      font-family: "Times New Roman", Times, serif;
      font-size: 12pt;
      margin: 0;
      padding: 0 12mm;
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

  <div class="title">Data Nilai Peserta</div>
  <div class="meta">
    <div>Dicetak: <?php echo htmlspecialchars($printedAt); ?></div>
    <div>Petugas: <?php echo $printedBy; ?></div>
  </div>

  <table>
    <thead>
      <tr>
        <th style="width: 40px;">No</th>
        <th>Peserta</th>
        <th>Pelatihan</th>
        <th>Hasil</th>
      </tr>
    </thead>
    <tbody>
    <?php if (!empty($nilais)) { $no = 1; foreach ($nilais as $n) { ?>
      <tr>
        <td><?php echo $no++; ?></td>
        <td><?php echo htmlspecialchars($n['nama_lengkap'] ?? ''); ?></td>
        <td><?php echo htmlspecialchars($n['nama_pelatihan'] ?? ''); ?></td>
        <td><?php echo htmlspecialchars($n['keterangan'] ?? ''); ?></td>
      </tr>
    <?php } } else { ?>
      <tr>
        <td colspan="5" style="text-align:center;">Tidak ada data nilai.</td>
      </tr>
    <?php } ?>
    </tbody>
  </table>

  <div class="actions no-print">
    <button onclick="window.print()">Cetak</button>
    <button onclick="window.close()">Tutup</button>
  </div>

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
</body>
</html>
