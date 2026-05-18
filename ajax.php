<?php
// ============================================================
//  ajax.php – Gestion de toutes les requêtes AJAX
//  N'inclut PAS login.php (qui génère du HTML)
// ============================================================
if (session_status() === PHP_SESSION_NONE) session_start();

header('Content-Type: application/json');

// Auth check
if (!isset($_SESSION['auth']) || $_SESSION['auth'] !== 'ok') {
    echo json_encode(['ok'=>false,'msg'=>'Non authentifié.']);
    exit;
}

// Connexion BDD
$dbHost = 'localhost'; $dbName = 'db_PLACE_NEVEUX'; $dbUser = '22505078'; $dbPasswd = '126620';
try {
    $pdo = new PDO('mysql:host='.$dbHost.';dbname='.$dbName.';charset=utf8mb4', $dbUser, $dbPasswd);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    echo json_encode(['ok'=>false,'msg'=>'Erreur BDD.']);
    exit;
}

$action = $_POST['action'] ?? '';

// ── Mise à jour niveau auto ──────────────────────────────────
function majNiveau($pdo, $user_id) {
    $u = $pdo->prepare('SELECT xp, niveau FROM users WHERE id=:id');
    $u->execute([':id'=>$user_id]);
    $u = $u->fetch();
    $nouveauNiveau = max(1, min(20, (int)floor($u['xp'] / 100) + 1));
    if ($nouveauNiveau != (int)$u['niveau']) {
        $pdo->prepare('UPDATE users SET niveau=:n WHERE id=:id')
            ->execute([':n'=>$nouveauNiveau, ':id'=>$user_id]);
    }
}

// ============================================================
//  ACTIONS ÉTUDIANT
// ============================================================

// ── Reporter un bug ──────────────────────────────────────────
if ($action === 'reporter_bug') {
    $cat  = trim($_POST['categorie'] ?? '');
    $desc = trim($_POST['description'] ?? '');
    if (!$cat || !$desc) {
        echo json_encode(['ok'=>false,'msg'=>'Catégorie et description obligatoires.']);
        exit;
    }
    // Anti-spam : max 3 bugs en attente
    $nb = $pdo->prepare('SELECT COUNT(*) FROM bugs WHERE user_id=:u AND statut="en_attente"');
    $nb->execute([':u'=>$_SESSION['user_id']]);
    if ((int)$nb->fetchColumn() >= 3) {
        echo json_encode(['ok'=>false,'msg'=>'Tu as déjà 3 bugs en attente. Attends qu\'ils soient traités.']);
        exit;
    }
    $pdo->prepare('INSERT INTO bugs (user_id,categorie,description) VALUES (:u,:c,:d)')
        ->execute([':u'=>$_SESSION['user_id'], ':c'=>$cat, ':d'=>$desc]);
    echo json_encode(['ok'=>true,'msg'=>'Bug signalé ! +25 XP si validé par un prof.']);
    exit;
}

// ── Soumettre une preuve de mission ─────────────────────────
if ($action === 'soumettre_preuve') {
    $mission_id  = (int)($_POST['mission_id'] ?? 0);
    $commentaire = trim($_POST['commentaire'] ?? '');
    if (!$mission_id) {
        echo json_encode(['ok'=>false,'msg'=>'Mission introuvable.']);
        exit;
    }
    // Mission active ?
    $m = $pdo->prepare('SELECT id FROM missions WHERE id=:id AND actif=1');
    $m->execute([':id'=>$mission_id]);
    if (!$m->fetch()) {
        echo json_encode(['ok'=>false,'msg'=>'Mission inexistante ou inactive.']);
        exit;
    }
    // Déjà soumis ?
    $dup = $pdo->prepare('SELECT id FROM soumissions WHERE mission_id=:m AND user_id=:u AND statut IN ("en_attente","valide")');
    $dup->execute([':m'=>$mission_id, ':u'=>$_SESSION['user_id']]);
    if ($dup->fetch()) {
        echo json_encode(['ok'=>false,'msg'=>'Tu as déjà soumis une preuve pour cette mission.']);
        exit;
    }
    // Fichier joint optionnel
    $fichier = null;
    if (!empty($_FILES['fichier']['name'])) {
        $dir = 'uploads/preuves/';
        if (!is_dir($dir)) mkdir($dir, 0777, true);
        $ext     = strtolower(pathinfo($_FILES['fichier']['name'], PATHINFO_EXTENSION));
        $allowed = ['pdf','png','jpg','jpeg','gif','webp','zip','txt','md'];
        if (!in_array($ext, $allowed)) {
            echo json_encode(['ok'=>false,'msg'=>'Type de fichier non autorisé.']);
            exit;
        }
        if ($_FILES['fichier']['size'] > 10 * 1024 * 1024) {
            echo json_encode(['ok'=>false,'msg'=>'Fichier trop lourd (max 10 Mo).']);
            exit;
        }
        $fichier = $dir.'preuve_'.$_SESSION['user_id'].'_'.$mission_id.'_'.time().'.'.$ext;
        move_uploaded_file($_FILES['fichier']['tmp_name'], $fichier);
    }
    $pdo->prepare('INSERT INTO soumissions (mission_id,user_id,commentaire,fichier) VALUES (:m,:u,:c,:f)')
        ->execute([':m'=>$mission_id, ':u'=>$_SESSION['user_id'], ':c'=>$commentaire, ':f'=>$fichier]);
    echo json_encode(['ok'=>true,'msg'=>'Preuve soumise ! Un prof va la valider bientôt.']);
    exit;
}

// ============================================================
//  ACTIONS PROF (vérification rôle)
// ============================================================
$roleUser = $_SESSION['role'] ?? '';
if (!in_array($roleUser, ['enseignant','admin'])) {
    echo json_encode(['ok'=>false,'msg'=>'Accès refusé.']);
    exit;
}

// ── Valider un bug ───────────────────────────────────────────
if ($action === 'valider_bug') {
    $bug_id = (int)($_POST['bug_id'] ?? 0);
    $bug = $pdo->prepare('SELECT * FROM bugs WHERE id=:id AND statut="en_attente"');
    $bug->execute([':id'=>$bug_id]);
    $bug = $bug->fetch();
    if (!$bug) {
        echo json_encode(['ok'=>false,'msg'=>'Bug introuvable ou déjà traité.']);
        exit;
    }
    $xp = (int)$bug['xp_attribue'];
    $pdo->prepare('UPDATE users SET xp=xp+:xp WHERE id=:id')
        ->execute([':xp'=>$xp, ':id'=>$bug['user_id']]);
    majNiveau($pdo, $bug['user_id']);
    $pdo->prepare('UPDATE bugs SET statut="valide", traite_par=:p, traite_le=NOW() WHERE id=:id')
        ->execute([':p'=>$_SESSION['user_id'], ':id'=>$bug_id]);
    echo json_encode(['ok'=>true,'msg'=>"+{$xp} XP attribués à l'étudiant !"]);
    exit;
}

// ── Ignorer un bug ───────────────────────────────────────────
if ($action === 'ignorer_bug') {
    $bug_id = (int)($_POST['bug_id'] ?? 0);
    $pdo->prepare('UPDATE bugs SET statut="ignore", traite_par=:p, traite_le=NOW() WHERE id=:id')
        ->execute([':p'=>$_SESSION['user_id'], ':id'=>$bug_id]);
    echo json_encode(['ok'=>true,'msg'=>'Bug ignoré.']);
    exit;
}

// ── Valider une preuve ───────────────────────────────────────
if ($action === 'valider_preuve') {
    $soum_id = (int)($_POST['soum_id'] ?? 0);
    $soum = $pdo->prepare('SELECT s.*, m.xp AS mission_xp FROM soumissions s JOIN missions m ON m.id=s.mission_id WHERE s.id=:id AND s.statut="en_attente"');
    $soum->execute([':id'=>$soum_id]);
    $soum = $soum->fetch();
    if (!$soum) {
        echo json_encode(['ok'=>false,'msg'=>'Soumission introuvable ou déjà traitée.']);
        exit;
    }
    $xp = (int)$soum['mission_xp'];
    $pdo->prepare('UPDATE users SET xp=xp+:xp WHERE id=:id')
        ->execute([':xp'=>$xp, ':id'=>$soum['user_id']]);
    majNiveau($pdo, $soum['user_id']);
    $pdo->prepare('UPDATE soumissions SET statut="valide", xp_attribue=:xp, valide_par=:p, valide_le=NOW() WHERE id=:id')
        ->execute([':xp'=>$xp, ':p'=>$_SESSION['user_id'], ':id'=>$soum_id]);
    echo json_encode(['ok'=>true,'msg'=>"+{$xp} XP attribués à l'étudiant !"]);
    exit;
}

// ── Refuser une preuve ───────────────────────────────────────
if ($action === 'refuser_preuve') {
    $soum_id = (int)($_POST['soum_id'] ?? 0);
    $raison  = trim($_POST['raison'] ?? '');
    $pdo->prepare('UPDATE soumissions SET statut="refuse", valide_par=:p, valide_le=NOW(), refuse_raison=:r WHERE id=:id')
        ->execute([':p'=>$_SESSION['user_id'], ':r'=>$raison, ':id'=>$soum_id]);
    echo json_encode(['ok'=>true,'msg'=>'Preuve refusée.']);
    exit;
}

echo json_encode(['ok'=>false,'msg'=>'Action inconnue.']);
