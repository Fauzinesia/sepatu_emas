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

$page_title = 'Sertifikat Saya';

// Terbit otomatis sertifikat berdasarkan pendaftaran & nilai
try {
  $userId = (int)($_SESSION['auth']['id_user'] ?? 0);
  if ($userId > 0) {
    $stEligible = $pdo->prepare(
      "SELECT p.id_pelatihan, n.keterangan
       FROM tb_pendaftaran p
       JOIN tb_nilai n ON n.id_user = p.id_user AND n.id_pelatihan = p.id_pelatihan
       WHERE p.id_user = :uid AND p.status = 'diterima' AND n.keterangan IS NOT NULL"
    );
    $stEligible->execute([':uid' => $userId]);
    $eligibleRows = $stEligible->fetchAll(PDO::FETCH_ASSOC);

    if (!empty($eligibleRows)) {
      $cekExist = $pdo->prepare('SELECT 1 FROM tb_sertifikat WHERE id_user = ? AND id_pelatihan = ? LIMIT 1');
      $insCert = $pdo->prepare('INSERT INTO tb_sertifikat (id_user, id_pelatihan, nomor_sertifikat, tanggal_terbit, file_sertifikat) VALUES (?,?,?,?,?)');
      foreach ($eligibleRows as $row) {
        $pid = (int)$row['id_pelatihan'];
        $ket = strtolower(trim((string)($row['keterangan'] ?? '')));
        if ($ket !== 'kompeten') { continue; }
        $cekExist->execute([$userId, $pid]);
        $exists = (bool)$cekExist->fetchColumn();
        if ($exists) { continue; }
        // Generate nomor sertifikat unik berbasis user & pelatihan & tahun
        $nomor = 'BLK-HSU/' . date('Y') . '/' . str_pad((string)$pid, 4, '0', STR_PAD_LEFT) . '/' . str_pad((string)$userId, 4, '0', STR_PAD_LEFT);
        $tanggalTerbit = date('Y-m-d');
        $insCert->execute([$userId, $pid, $nomor, $tanggalTerbit, null]);
      }
    }
  }
} catch (Throwable $e) {
  // Diamkan agar halaman tetap tampil meski penerbitan otomatis gagal
}

// Ambil sertifikat saya
$certs = [];
try {
  $st = $pdo->prepare("SELECT s.id_sertifikat, s.nomor_sertifikat, s.tanggal_terbit, s.file_sertifikat,
                               p.id_pelatihan, p.nama_pelatihan
                        FROM tb_sertifikat s
                        JOIN tb_pelatihan p ON p.id_pelatihan = s.id_pelatihan
                        WHERE s.id_user = :uid
                        ORDER BY s.tanggal_terbit DESC");
  $st->execute([':uid' => $_SESSION['auth']['id_user'] ?? 0]);
  $certs = $st->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
  $certs = [];
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
                  <h5 class="card-title fw-semibold mb-0">Sertifikat Saya</h5>
                </div>

                <div class="table-responsive">
                  <table class="table table-striped align-middle">
                    <thead>
                      <tr>
                        <th>Pelatihan</th>
                        <th>Nomor</th>
                        <th>Tanggal Terbit</th>
                        <th>Aksi</th>
                      </tr>
                    </thead>
                    <tbody>
                      <?php if (empty($certs)) { ?>
                        <tr><td colspan="4" class="text-center text-muted">Belum ada sertifikat yang tersedia.</td></tr>
                      <?php } else { foreach ($certs as $c) { ?>
                        <tr>
                          <td><?php echo htmlspecialchars($c['nama_pelatihan']); ?></td>
                          <td><?php echo htmlspecialchars($c['nomor_sertifikat'] ?: '-'); ?></td>
                          <td><?php echo htmlspecialchars($c['tanggal_terbit'] ? date('d/m/Y', strtotime($c['tanggal_terbit'])) : '-'); ?></td>
                          <td>
                            <div class="d-flex flex-wrap gap-2">
                              <?php if (!empty($c['file_sertifikat'])) { ?>
                                <a class="btn btn-sm btn-outline-primary" href="<?php echo '../' . htmlspecialchars($c['file_sertifikat']); ?>" target="_blank">
                                  <i class="ti ti-eye me-1"></i> Lihat
                                </a>
                                <a class="btn btn-sm btn-primary" href="<?php echo '../' . htmlspecialchars($c['file_sertifikat']); ?>" download>
                                  <i class="ti ti-download me-1"></i> Unduh
                                </a>
                              <?php } ?>
                              <a class="btn btn-sm btn-success btn-print" href="cetak_sertifikat.php?id=<?php echo (int)$c['id_sertifikat']; ?>" target="_blank" data-id="<?php echo (int)$c['id_sertifikat']; ?>">
                                <i class="ti ti-printer me-1"></i> Cetak Sertifikat
                              </a>
                              <?php if (empty($c['file_sertifikat'])) { ?>
                                <span class="text-muted">(file belum tersedia)</span>
                              <?php } ?>
                            </div>
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

  <?php if (isset($_GET['need_tracer']) && $_GET['need_tracer'] == '1') { ?>
    <div class="container mt-3">
      <div class="alert alert-warning" role="alert">
        Sebelum mencetak sertifikat, mohon isi Tracer Study terlebih dahulu.
      </div>
    </div>
  <?php } ?>

  <!-- Modal Tracer Study -->
  <div class="modal fade" id="modalTracer" tabindex="-1" aria-labelledby="modalTracerLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="modalTracerLabel">Konfirmasi Tracer Study</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <p class="mb-2">Sebelum mencetak sertifikat, Anda wajib mengisi formulir Tracer Study.</p>
          <p class="small text-muted">Klik tombol di bawah untuk membuka formulir pada tab baru, isi hingga selesai, lalu kembali ke halaman ini dan tekan "Saya sudah mengisi".</p>
          <a href="https://docs.google.com/forms/d/e/1FAIpQLSd-y1SdlZ_B71ru3jFldl-YaE2wNVrmR1bMx_CMEcmal2ehAg/viewform" target="_blank" rel="noopener" class="btn btn-outline-primary">
            Buka Formulir Tracer Study
          </a>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
          <button type="button" class="btn btn-success" id="btnConfirmTracer">Saya sudah mengisi</button>
        </div>
      </div>
    </div>
  </div>

  <script src="../assets/libs/jquery/dist/jquery.min.js"></script>
  <script src="../assets/libs/bootstrap/dist/js/bootstrap.bundle.min.js"></script>
  <script src="../assets/js/app.min.js"></script>
  <script src="../assets/js/sidebarmenu.js"></script>
  <script>
    (function(){
      const tracerOk = <?php echo json_encode((bool)($_SESSION['auth']['tracer_ok'] ?? false)); ?>;
      const modalEl = document.getElementById('modalTracer');
      const modal = modalEl ? new bootstrap.Modal(modalEl) : null;
      let pendingCertId = null;

      // Intercept tombol cetak
      document.querySelectorAll('.btn-print').forEach(function(btn){
        btn.addEventListener('click', function(ev){
          if (!tracerOk) {
            ev.preventDefault();
            pendingCertId = this.getAttribute('data-id');
            if (modal) modal.show();
          }
        });
      });

      // Konfirmasi bahwa sudah mengisi
      const confirmBtn = document.getElementById('btnConfirmTracer');
      if (confirmBtn) {
        confirmBtn.addEventListener('click', async function(){
          try {
            const resp = await fetch('tracer_confirm.php', { method: 'POST' });
            if (!resp.ok) throw new Error('Gagal konfirmasi');
            const data = await resp.json();
            if (data && data.ok) {
              // Tutup modal dan lanjut cetak
              if (modal) modal.hide();
              const url = 'cetak_sertifikat.php?id=' + encodeURIComponent(pendingCertId);
              window.open(url, '_blank');
            } else {
              alert('Konfirmasi gagal. Silakan coba lagi.');
            }
          } catch (e) {
            alert('Terjadi kesalahan saat konfirmasi.');
          }
        });
      }
    })();
  </script>
</body>
</html>
