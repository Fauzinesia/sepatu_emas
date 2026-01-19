<?php
session_start();

// Pastikan hanya peserta yang bisa konfirmasi
if (!isset($_SESSION['auth']['logged_in']) || $_SESSION['auth']['logged_in'] !== true) {
  http_response_code(401);
  echo json_encode(['ok' => false, 'error' => 'unauthorized']);
  exit;
}
if (strtolower($_SESSION['auth']['role'] ?? '') !== 'peserta') {
  http_response_code(403);
  echo json_encode(['ok' => false, 'error' => 'forbidden']);
  exit;
}

// Set flag sesi bahwa tracer telah dikonfirmasi
$_SESSION['auth']['tracer_ok'] = true;

header('Content-Type: application/json');
echo json_encode(['ok' => true]);
?>

