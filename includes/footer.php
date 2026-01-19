<?php
// Footer include: scripts dan penutup wrapper/body/html
if (!isset($assetBase)) {
  $script = $_SERVER['SCRIPT_NAME'] ?? '';
  if (preg_match('#^/([^/]+)/#', $script, $m)) {
    $assetBase = '/' . $m[1] . '/assets/';
  } else {
    $assetBase = '/assets/';
  }
}
?>
  </div> <!-- end .page-wrapper or .body-wrapper dibuka di halaman -->
  <script src="<?php echo $assetBase; ?>libs/jquery/dist/jquery.min.js"></script>
  <script src="<?php echo $assetBase; ?>libs/bootstrap/dist/js/bootstrap.bundle.min.js"></script>
  <script src="<?php echo $assetBase; ?>libs/apexcharts/dist/apexcharts.min.js"></script>
  <script src="<?php echo $assetBase; ?>js/app.min.js"></script>
  <script src="<?php echo $assetBase; ?>js/sidebarmenu.js"></script>
</body>
</html>