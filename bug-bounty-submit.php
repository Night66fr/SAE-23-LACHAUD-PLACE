<?php
session_start();
if (!isset($_SESSION['auth']) || $_SESSION['auth'] !== 'ok') {
    header('Location: login-page.php'); exit();
}
$dbHost='localhost';$dbName='db_PLACE_NEVEUX';$dbUser='22505078';$dbPasswd='126620';
$pdo = new PDO('mysql:host='.$dbHost.';dbname='.$dbName.';charset=utf8mb4',$dbUser,$dbPasswd);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

if ($_SERVER['REQUEST_METHOD']==='POST') {
    $titre      = trim($_POST['titre']       ?? '');
    $categorie  = trim($_POST['categorie']   ?? '');
    $severite   = $_POST['severite']         ?? 'low';
    $description= trim($_POST['description'] ?? '');

    $sevOK = ['info','low','medium','high','critical'];
    if ($titre && $categorie && $description && in_array($severite,$sevOK)) {
        $pdo->prepare(
            'INSERT INTO bug_bounty (user_id, categorie, severite, titre, description)
             VALUES (:uid, :cat, :sev, :tit, :desc)'
        )->execute([
            ':uid'  => $_SESSION['user_id'],
            ':cat'  => $categorie,
            ':sev'  => $severite,
            ':tit'  => $titre,
            ':desc' => $description,
        ]);
    }
}
header('Location: index-etudiants.php?bug=ok');
exit();
