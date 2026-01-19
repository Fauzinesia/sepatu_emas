<?php
// Header include: meta, stylesheet, pembuka page-wrapper
// Variabel yang bisa diset dari halaman: $page_title, $assetBase
$page_title = isset($page_title) ? $page_title : 'SEPATU EMAS';
if (!isset($assetBase)) {
  $script = $_SERVER['SCRIPT_NAME'] ?? '';
  if (preg_match('#^/([^/]+)/#', $script, $m)) {
    $assetBase = '/' . $m[1] . '/assets/';
  } else {
    $assetBase = '/assets/';
  }
}
?>
<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?php echo htmlspecialchars($page_title); ?> — SEPATU EMAS</title>
  <link rel="shortcut icon" type="image/png" href="<?php echo $assetBase; ?>images/logos/favicon.png" />
  <link rel="stylesheet" href="<?php echo $assetBase; ?>css/styles.min.css" />
  <style> .grow{flex-grow:1} </style>
</head>
<body>
  <!--  Body Wrapper -->
  <div class="page-wrapper" id="main-wrapper" data-layout="vertical" data-navbarbg="skin6" data-sidebartype="full"
    data-sidebar-position="fixed" data-header-position="fixed">
