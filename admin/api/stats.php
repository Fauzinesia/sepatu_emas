<?php
// Endpoint JSON untuk statistik dashboard
// Autentikasi: hanya admin yang diizinkan
// Output: { users:{admins,peserta,total}, pelatihan:{aktif,nonaktif}, pendaftaran:{menunggu,diterima,ditolak}, trend:{labels:[],series:[]} }

session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/../../config/koneksi.php';

try {
  if (!isset($_SESSION['auth']['logged_in']) || $_SESSION['auth']['logged_in'] !== true) {
    http_response_code(401);
    echo json_encode(['error' => 'unauthorized']);
    exit;
  }
  $role = strtolower($_SESSION['auth']['role'] ?? ($_SESSION['role'] ?? ''));
  if ($role !== 'admin') {
    http_response_code(403);
    echo json_encode(['error' => 'forbidden']);
    exit;
  }

  // Pengguna
  $total_users = (int)$pdo->query("SELECT COUNT(*) FROM tb_user")->fetchColumn();
  $total_admins = (int)$pdo->query("SELECT COUNT(*) FROM tb_user WHERE role='admin'")->fetchColumn();
  $total_peserta = (int)$pdo->query("SELECT COUNT(*) FROM tb_user WHERE role='peserta'")->fetchColumn();

  // Pelatihan
  $pelatihan_aktif = (int)$pdo->query("SELECT COUNT(*) FROM tb_pelatihan WHERE status='aktif'")->fetchColumn();
  $pelatihan_nonaktif = (int)$pdo->query("SELECT COUNT(*) FROM tb_pelatihan WHERE status='nonaktif'")->fetchColumn();

  // Pendaftaran
  $pendaftaran_menunggu = (int)$pdo->query("SELECT COUNT(*) FROM tb_pendaftaran WHERE status='menunggu'")->fetchColumn();
  $pendaftaran_diterima = (int)$pdo->query("SELECT COUNT(*) FROM tb_pendaftaran WHERE status='diterima'")->fetchColumn();
  $pendaftaran_ditolak = (int)$pdo->query("SELECT COUNT(*) FROM tb_pendaftaran WHERE status='ditolak'")->fetchColumn();

  // Trend 7 hari terakhir berdasarkan tanggal_daftar
  $daysMap = [];
  $labels = [];
  for ($i = 6; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i day"));
    $labels[] = date('d/m', strtotime($date));
    $daysMap[$date] = 0;
  }
  $stmtTrend = $pdo->query("SELECT DATE(tanggal_daftar) AS day, COUNT(*) AS cnt FROM tb_pendaftaran WHERE DATE(tanggal_daftar) >= DATE_SUB(CURDATE(), INTERVAL 6 DAY) GROUP BY day");
  foreach ($stmtTrend->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $day = $row['day'];
    $cnt = (int)$row['cnt'];
    if (isset($daysMap[$day])) { $daysMap[$day] = $cnt; }
  }
  $series = array_values($daysMap);

  echo json_encode([
    'users' => ['admins' => $total_admins, 'peserta' => $total_peserta, 'total' => $total_users],
    'pelatihan' => ['aktif' => $pelatihan_aktif, 'nonaktif' => $pelatihan_nonaktif],
    'pendaftaran' => ['menunggu' => $pendaftaran_menunggu, 'diterima' => $pendaftaran_diterima, 'ditolak' => $pendaftaran_ditolak],
    'trend' => ['labels' => $labels, 'series' => $series]
  ]);
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['error' => 'server_error']);
}
?>