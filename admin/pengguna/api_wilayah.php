<?php
/**
 * API untuk mendapatkan data wilayah Indonesia (cascading dropdown)
 * Source: https://github.com/ibnux/data-indonesia
 * 
 * Endpoints:
 * - api_wilayah.php?tingkat=provinsi
 * - api_wilayah.php?tingkat=kabupaten&parent=63
 * - api_wilayah.php?tingkat=kecamatan&parent=6302
 * - api_wilayah.php?tingkat=desa&parent=630201
 */

session_start();
// Proteksi: hanya untuk user yang sudah login
// if (!isset($_SESSION['auth']['logged_in']) || $_SESSION['auth']['logged_in'] !== true) {
//   http_response_code(401);
//   echo json_encode(['error' => 'Unauthorized']);
//   exit;
// }

require_once __DIR__ . '/../../config/koneksi.php';

header('Content-Type: application/json');

try {
  $tingkat = isset($_GET['tingkat']) ? trim($_GET['tingkat']) : '';
  $parent = isset($_GET['parent']) ? trim($_GET['parent']) : '';
  
  // Validasi tingkat
  $validTingkat = ['provinsi', 'kabupaten', 'kecamatan', 'desa'];
  if (!in_array($tingkat, $validTingkat, true)) {
    throw new Exception('Tingkat wilayah tidak valid');
  }
  
  // Query berdasarkan tingkat dan parent
  if ($tingkat === 'provinsi') {
    // Ambil semua provinsi
    $sql = "SELECT id, nama FROM t_provinsi ORDER BY nama";
    $stmt = $pdo->query($sql);
    
  } elseif ($tingkat === 'kabupaten') {
    if (!$parent) throw new Exception('Parameter parent required');
    // Ambil kabupaten berdasarkan provinsi
    // ID kabupaten dimulai dengan ID provinsi (misal: 63xx untuk Kalsel)
    $sql = "SELECT id, nama FROM t_kota WHERE id LIKE ? ORDER BY nama";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$parent . '%']);
    
  } elseif ($tingkat === 'kecamatan') {
    if (!$parent) throw new Exception('Parameter parent required');
    // Ambil kecamatan berdasarkan kabupaten
    $sql = "SELECT id, nama FROM t_kecamatan WHERE id LIKE ? ORDER BY nama";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$parent . '%']);
    
  } elseif ($tingkat === 'desa') {
    if (!$parent) throw new Exception('Parameter parent required');
    // Ambil desa berdasarkan kecamatan
    $sql = "SELECT id, nama FROM t_kelurahan WHERE id LIKE ? ORDER BY nama";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$parent . '%']);
  }
  
  $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
  
  echo json_encode([
    'success' => true,
    'data' => $data,
    'count' => count($data)
  ]);
  
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode([
    'success' => false,
    'error' => $e->getMessage()
  ]);
}
