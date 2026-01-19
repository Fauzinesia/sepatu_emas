<?php
session_start();

// Hapus semua data sesi dengan aman
$_SESSION = [];
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
}
session_destroy();

// Tentukan base URL proyek (mis. /sepati_emas/)
$script = $_SERVER['SCRIPT_NAME'] ?? '';
if (preg_match('#^/([^/]+)/#', $script, $m)) {
    $urlBase = '/' . $m[1] . '/';
} else {
    $urlBase = '/';
}

// Redirect ke login dengan indikator logout
header('Location: ' . $urlBase . 'login.php?logout=1');
exit;
?>