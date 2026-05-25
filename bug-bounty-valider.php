<?php
require_once __DIR__.'/config.php';
session_start();
if (!isset($_SESSION['auth']) || $_SESSION['auth'] !== 'ok') {
    header('Location: login-page.php'); exit();
}
if (!in_array($_SESSION['role']??'', ['enseignant','admin'])) {
    header('Location: index-etudiants.php'); exit();
}
$pdo = getDB();

if ($_SERVER['REQUEST_METHOD']==='POST') {
    $bugId = (int)($_POST['bug_id'] ?? 0);
    $action= $_POST['action'] ?? 'valider';

    if ($bugId > 0) {
        if ($action === 'invalider') {
            $pdo->prepare('UPDATE bug_bounty SET statut="invalide" WHERE id=:id')
                ->execute([':id' => $bugId]);
        } else {
            $xp = (int)($_POST['xp_attribue'] ?? 25);
            $xp = in_array($xp,[10,25,50,100,200]) ? $xp : 25;

            // Récupérer le user_id du bug
            $bug = $pdo->prepare('SELECT user_id FROM bug_bounty WHERE id=:id');
            $bug->execute([':id' => $bugId]);
            $bugData = $bug->fetch();

            if ($bugData) {
                // Valider le bug et attribuer les XP
                $pdo->prepare(
                    'UPDATE bug_bounty SET statut="valide", xp_attribue=:xp WHERE id=:id'
                )->execute([':xp' => $xp, ':id' => $bugId]);

                // Ajouter les XP à l'étudiant
                $pdo->prepare('UPDATE users SET xp = xp + :xp WHERE id=:uid')
                    ->execute([':xp' => $xp, ':uid' => $bugData['user_id']]);

                // Montée de niveau si nécessaire
                $student = $pdo->prepare('SELECT xp, niveau FROM users WHERE id=:id');
                $student->execute([':id' => $bugData['user_id']]);
                $s = $student->fetch();
                $nouveauNiveau = min(20, floor($s['xp'] / 100) + 1);
                if ($nouveauNiveau > $s['niveau']) {
                    $pdo->prepare('UPDATE users SET niveau=:n WHERE id=:id')
                        ->execute([':n' => $nouveauNiveau, ':id' => $bugData['user_id']]);
                }
            }
        }
    }
}
header('Location: index-prof.php?bug=traite');
exit();
