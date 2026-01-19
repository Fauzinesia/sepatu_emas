<?php
session_start();
// Validasi login peserta
if (!isset($_SESSION['auth']['logged_in']) || $_SESSION['auth']['logged_in'] !== true) {
  header('Location: ../login.php');
  exit;
}
// Halaman ini untuk peserta; jika role bukan peserta, arahkan sesuai
if (strtolower($_SESSION['auth']['role'] ?? '') !== 'peserta') {
  header('Location: ../admin/dashboard.php');
  exit;
}

// Wajib konfirmasi tracer sebelum cetak
if (!isset($_SESSION['auth']['tracer_ok']) || $_SESSION['auth']['tracer_ok'] !== true) {
  header('Location: sertifikat.php?need_tracer=1');
  exit;
}

require_once __DIR__ . '/../config/koneksi.php';

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if ($id <= 0) {
  http_response_code(400);
  echo 'ID sertifikat tidak valid.';
  exit;
}

$stmt = $pdo->prepare(
  "SELECT s.id_sertifikat, s.nomor_sertifikat, s.tanggal_terbit, s.file_sertifikat,
          u.nama_lengkap, u.id_user,
          p.nama_pelatihan, p.nama_instruktur, p.tanggal_mulai, p.tanggal_selesai,
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
   WHERE s.id_sertifikat = :id AND s.id_user = :uid
   LIMIT 1"
);
$stmt->execute([':id' => $id, ':uid' => (int) ($_SESSION['auth']['id_user'] ?? 0)]);
$data = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$data) {
  http_response_code(404);
  error_log('Cetak sertifikat gagal: tidak ditemukan atau bukan milik user. id=' . $id . ', uid=' . ((int) ($_SESSION['auth']['id_user'] ?? 0)));
  echo 'Sertifikat tidak ditemukan.';
  exit;
}

$printedAt = date('d/m/Y H:i');
$printedBy = htmlspecialchars($_SESSION['auth']['username'] ?? 'admin');
$unitsToPrint = [];
try {
  $stU = $pdo->prepare('SELECT posisi, nama_unit, kode_unit FROM tb_sertifikat_unit WHERE id_sertifikat = ? ORDER BY posisi ASC');
  $stU->execute([$id]);
  $rowsU = $stU->fetchAll(PDO::FETCH_ASSOC);
  if (!empty($rowsU)) {
    foreach ($rowsU as $ru) {
      $unitsToPrint[] = ['nama' => (string)($ru['nama_unit'] ?? ''), 'kode' => (string)($ru['kode_unit'] ?? '')];
    }
  }
} catch (Throwable $e) { $unitsToPrint = []; }
?>
<!doctype html>
<html lang="id">

<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Sertifikat Pelatihan</title>
  <style>
    :root {
      --text: #2b2b2b;
      --accent: #8b6f47;
      /* warna emas */
      --border: #c9b07a;
    }

    @page {
      size: A4 landscape;
      margin: 0;
    }

    @media print {
      .no-print {
        display: none !important;
      }
    }

    body {
      margin: 0;
      font-family: "Times New Roman", Times, serif;
      color: var(--text);
      background: #fff;
    }

    .certificate {
      position: relative;
      width: 297mm;
      /* lebar A4 landscape */
      height: 210mm;
      /* tinggi A4 landscape */
      margin: 0 auto;
      padding: 14mm 16mm;
      /* ruang dalam aman */
      border: 8px double var(--border);
      box-shadow: 0 0 0 6px rgba(201, 176, 122, 0.25) inset;
      box-sizing: border-box;
      /* sertakan border & padding dalam ukuran */
      overflow: hidden;
      /* hindari konten keluar */
    }

    .watermark {
      position: absolute;
      inset: 0;
      display: flex;
      align-items: center;
      justify-content: center;
      pointer-events: none;
      opacity: 0.06;
      z-index: 0;
    }

    .watermark img {
      max-width: 65%;
      filter: grayscale(100%);
    }

    .header {
      display: flex;
      align-items: center;
      justify-content: space-between;
      margin-bottom: 10mm;
      z-index: 1;
      position: relative;
    }

    .header .brand {
      display: flex;
      align-items: center;
      gap: 10px;
    }

    .brand img {
      height: 70px;
    }

    .brand .org {
      font-size: 11pt;
      line-height: 1.35;
      text-transform: uppercase;
      font-weight: 700;
    }

    .title {
      text-align: center;
      font-size: 32pt;
      letter-spacing: 2px;
      color: var(--accent);
      font-weight: 800;
      margin: 2mm 0 6mm;
      z-index: 1;
      position: relative;
    }

    .subtitle {
      text-align: center;
      font-size: 12pt;
      font-style: italic;
      margin-bottom: 10mm;
    }

    .recipient {
      text-align: center;
      font-size: 22pt;
      font-weight: 700;
      text-transform: uppercase;
      margin-bottom: 6mm;
    }

    .statement {
      text-align: center;
      font-size: 12pt;
      margin: 0 20mm 8mm;
      line-height: 1.6;
    }

    .details {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 6mm;
      margin: 0 30mm 10mm;
      font-size: 11pt;
    }

    .details .item {
      display: flex;
      gap: 6px;
    }

    .details .label {
      font-weight: 700;
      min-width: 150px;
    }

    .footer {
      display: flex;
      justify-content: space-between;
      align-items: flex-end;
      margin-top: 12mm;
    }

    .number {
      font-size: 11pt;
      font-weight: 700;
    }

    .sign {
      text-align: right;
      min-width: 320px;
    }

    .sign .role {
      font-weight: 700;
    }

    .sign .space {
      height: 28mm;
    }

    .sign .name {
      font-weight: 700;
      text-transform: uppercase;
    }

    .sign .grade {
      font-size: 10pt;
    }

    .actions.no-print {
      margin: 6mm 20mm;
      display: flex;
      gap: 10px;
    }

    .btn {
      padding: 6px 12px;
      border: 1px solid #444;
      background: #f5f5f5;
      cursor: pointer;
    }
    .table-units { width: 100%; border-collapse: collapse; font-size: 11pt; }
    .table-units th, .table-units td { border: 1px solid #000; padding: 6px 8px; }
    .table-units th { font-weight: 700; }
    .section { margin: 0 30mm 10mm; }
  </style>
</head>

<body onload="window.print()">
  <div class="certificate">
    <div class="watermark"><img src="../assets/images/logos/logo-hsu.png" alt="Watermark"></div>
    <div class="header">
      <div class="brand">
        <img src="../assets/images/logos/logo-hsu.png" alt="Logo HSU">
        <div class="org">
          Pemerintah Kabupaten Hulu Sungai Utara<br>
          Dinas Penanaman Modal dan Pelayanan Terpadu Satu Pintu<br>
          Balai Latihan Kerja Amuntai
        </div>
      </div>
      <div class="number">No: <?php echo htmlspecialchars($data['nomor_sertifikat'] ?? ''); ?></div>
    </div>

    <div class="title">SERTIFIKAT</div>
    <div class="subtitle">Certificate of Completion</div>

    <div class="recipient"><?php echo htmlspecialchars($data['nama_lengkap'] ?? ''); ?></div>
    <div class="statement">
      Dinyatakan telah menyelesaikan dan lulus pelatihan
      <strong><?php echo htmlspecialchars($data['nama_pelatihan'] ?? ''); ?></strong>
      yang diselenggarakan oleh Balai Latihan Kerja Amuntai,
      dengan hasil evaluasi:
      <strong><?php echo isset($data['keterangan']) && $data['keterangan'] !== null ? htmlspecialchars($data['keterangan']) : '-'; ?></strong>.
    </div>

    <div class="details">
      <div class="item">
        <div class="label">Periode</div>
        <div class="value">:
          <?php echo htmlspecialchars(($data['tanggal_mulai'] ?? '') . ' s/d ' . ($data['tanggal_selesai'] ?? '')); ?>
        </div>
      </div>
      <div class="item">
        <div class="label">No Induk Pendaftaran</div>
        <div class="value">: <?php echo htmlspecialchars($data['no_induk_pendaftaran'] ?? '-'); ?></div>
      </div>
      <div class="item">
        <div class="label">Tanggal Terbit</div>
        <div class="value">: <?php echo htmlspecialchars($data['tanggal_terbit'] ?? ''); ?></div>
      </div>
    </div>

    <div class="footer">
      <div></div>
      <div class="sign">
        <div class="role">Kepala BLK Amuntai</div>
        <div class="space"></div>
        <div class="name">AHMAD HUMAIDI,ST</div>
        <div class="grade">Penata Tk.I</div>
        <div class="grade">NIP.19760226 2008 1 017</div>
      </div>
    </div>
  </div>

  <?php if (!empty($unitsToPrint)) { ?>
  <div style="page-break-before: always;"></div>
  <div class="certificate">
    <div class="watermark"><img src="../assets/images/logos/logo-hsu.png" alt="Watermark"></div>
    <div class="header">
      <div class="brand">
        <img src="../assets/images/logos/logo-hsu.png" alt="Logo HSU">
        <div class="org">
          Pemerintah Kabupaten Hulu Sungai Utara<br>
          Dinas Penanaman Modal dan Pelayanan Terpadu Satu Pintu<br>
          Balai Latihan Kerja Amuntai
        </div>
      </div>
      <div class="number">
        No: <?php echo htmlspecialchars($data['nomor_sertifikat'] ?? ''); ?>
        <?php if (!empty($data['nama_instruktur'])) { ?>
          <div style="font-size: 11pt; font-weight: normal; margin-top: 4px;">Instruktur: <?php echo htmlspecialchars($data['nama_instruktur']); ?></div>
        <?php } ?>
      </div>
    </div>
    <div class="subtitle">Daftar Unit Kompetensi Yang Dicapai</div>
    <div class="statement">
      Pelatihan <strong><?php echo htmlspecialchars($data['nama_pelatihan'] ?? ''); ?></strong>
    </div>
    <div class="section">
      <table class="table-units">
        <thead>
          <tr>
            <th style="width:64px;">No</th>
            <th>Unit Kompetensi</th>
            <th style="width:260px;">Kode Unit Kompetensi</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($unitsToPrint as $i => $u) { ?>
          <tr>
            <td><?php echo $i + 1; ?></td>
            <td><?php echo htmlspecialchars($u['nama'] ?? ''); ?></td>
            <td><?php echo htmlspecialchars($u['kode'] ?? ''); ?></td>
          </tr>
          <?php } ?>
        </tbody>
      </table>
    </div>
    <div class="footer">
      <div></div>
    </div>
  </div>
  <?php } ?>

  <div class="actions no-print">
    <button class="btn" onclick="window.print()">Cetak</button>
    <button class="btn" onclick="window.close()">Tutup</button>
  </div>
</body>

</html>
