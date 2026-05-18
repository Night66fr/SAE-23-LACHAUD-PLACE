<?php
// ============================================================
//  LevelUp – logout.php
// ============================================================
session_start();
if (isset($_REQUEST['logout']) && $_REQUEST['logout'] === 'true') {
    session_destroy();
    $_SESSION = [];
    $base = 'http://'.$_SERVER['HTTP_HOST'].dirname($_SERVER['PHP_SELF']);
    header('Location: '.$base.'/login-page.php');
    exit();
}
header('Location: index.php');
exit();
?>
