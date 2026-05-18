<?php
// ============================================================
//  LevelUp – register.php  (style Duolingo)
// ============================================================
session_start();

$dbHost   = 'localhost';
$dbName   = 'db_PLACE_NEVEUX';
$dbUser   = '22505078';
$dbPasswd = '126620';

try {
    $pdo = new PDO('mysql:host='.$dbHost.';dbname='.$dbName.';charset=utf8mb4', $dbUser, $dbPasswd);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die('<p style="color:red;text-align:center;">Erreur BDD : '.htmlspecialchars($e->getMessage()).'</p>');
}

if (isset($_SESSION['auth']) && $_SESSION['auth'] === 'ok') {
    header('Location: index-etudiants.php'); exit();
}

$error = ''; $success = false; $loginFinal = ''; $email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $prenom = trim($_POST['prenom'] ?? '');
    $nom    = trim($_POST['nom']    ?? '');
    $email  = trim($_POST['email']  ?? '');
    $pass1  = $_POST['pass1'] ?? '';
    $pass2  = $_POST['pass2'] ?? '';

    if ($prenom==='' || $nom==='' || $email==='' || $pass1==='') {
        $error = 'Tous les champs sont obligatoires.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Adresse e-mail invalide.';
    } elseif (!preg_match('/@(etu\.umontpellier\.fr|umontpellier\.fr)$/i', $email)) {
        $error = 'Seules les adresses @etu.umontpellier.fr ou @umontpellier.fr sont acceptées.';
    } elseif (strlen($pass1) < 6) {
        $error = 'Le mot de passe doit contenir au moins 6 caractères.';
    } elseif ($pass1 !== $pass2) {
        $error = 'Les deux mots de passe ne correspondent pas.';
    } else {
        $chk = $pdo->prepare('SELECT id FROM users WHERE email = :e LIMIT 1');
        $chk->execute([':e' => $email]);
        if ($chk->fetch()) {
            $error = 'Cette adresse e-mail est déjà utilisée.';
        } else {
            $role       = preg_match('/@etu\.umontpellier\.fr$/i', $email) ? 'etudiant' : 'enseignant';
            $loginFinal = $email;
            $hash       = password_hash($pass1, PASSWORD_BCRYPT);
            $pdo->prepare(
                'INSERT INTO users (user, pass, role, nom, prenom, email, niveau, xp, streak, actif)
                 VALUES (:u,:p,:r,:n,:pr,:e,1,0,0,1)'
            )->execute([':u'=>$loginFinal,':p'=>$hash,':r'=>$role,':n'=>$nom,':pr'=>$prenom,':e'=>$email]);
            $success = true;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="utf-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1"/>
<title>LevelUp – Créer un compte</title>
<link rel="stylesheet" href="styles.css"/>
</head>
<body class="login-page-body">

<!-- Header -->
<div class="reg-header">
  <div class="reg-logo">LevelUp</div>
  <div class="reg-sub">Plateforme d'apprentissage R&amp;T • IUT de Béziers</div>
</div>

<div class="reg-card">

<?php if ($success):
  $roleFinal = preg_match('/@etu\.umontpellier\.fr$/i', $email) ? 'etudiant' : 'enseignant';
?>
  <!-- SUCCÈS -->
  <div class="success-box">
    <div class="success-trophy">
      <svg xmlns="http://www.w3.org/2000/svg" width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="#58cc02" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>
    </div>
    <div class="success-title">Compte créé !</div>
    <div class="success-sub">Ton identifiant de connexion :</div>

    <div class="login-badge"><?= htmlspecialchars($loginFinal) ?></div>
    <p style="font-size:.73rem;color:var(--muted);font-weight:700;margin-bottom:16px">Note-le bien, tu en auras besoin pour te connecter.</p>

    <div>
      <span class="badge-role-pill <?= $roleFinal==='etudiant' ? 'role-etu' : 'role-ens' ?>">
        <?= $roleFinal==='etudiant' ? 'Étudiant' : 'Enseignant' ?>
      </span>
    </div>

    <a class="btn-connect" href="login-page.php">
      <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/><polyline points="10 17 15 12 10 7"/><line x1="15" y1="12" x2="3" y2="12"/></svg>
      Se connecter
    </a>
  </div>

<?php else: ?>

  <h2>Créer un compte</h2>

  <?php if ($error): ?>
  <div class="msg-err">
    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
    <?= htmlspecialchars($error) ?>
  </div>
  <?php endif; ?>

  <!-- Info rôles -->
  <div class="role-info">
    <div class="role-pill etu">Étudiant · @etu.umontpellier.fr</div>
    <div class="role-pill ens">Enseignant · @umontpellier.fr</div>
  </div>

  <form method="post" action="register.php">

    <div class="form-row">
      <div class="form-group">
        <label for="prenom">Prénom</label>
        <input type="text" id="prenom" name="prenom"
               value="<?= htmlspecialchars($_POST['prenom'] ?? '') ?>"
               placeholder="Alice" required autocomplete="given-name"/>
      </div>
      <div class="form-group">
        <label for="nom">Nom</label>
        <input type="text" id="nom" name="nom"
               value="<?= htmlspecialchars($_POST['nom'] ?? '') ?>"
               placeholder="Dupont" required autocomplete="family-name"/>
      </div>
    </div>

    <div class="form-group">
      <label for="email">Adresse e-mail</label>
      <input type="email" id="email" name="email"
             value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
             placeholder="prenom.nom@etu.umontpellier.fr"
             required autocomplete="email"/>
      <div class="hint">
        <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M12 16v-4"/><path d="M12 8h.01"/></svg>
        Ton rôle sera détecté automatiquement via ton adresse.
      </div>
    </div>

    <div class="divider">mot de passe</div>

    <div class="form-row">
      <div class="form-group">
        <label for="pass1">Mot de passe</label>
        <input type="password" id="pass1" name="pass1"
               placeholder="Min. 6 caractères" required autocomplete="new-password"/>
      </div>
      <div class="form-group">
        <label for="pass2">Confirmer</label>
        <input type="password" id="pass2" name="pass2"
               placeholder="Identique" required autocomplete="new-password"/>
      </div>
    </div>

    <button type="submit" class="btn-main">
      <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
      Créer mon compte
    </button>
  </form>

  <div class="bottom-link">
    Déjà un compte ? <a href="login-page.php">Se connecter</a>
  </div>

<?php endif; ?>

</div><!-- fin reg-card -->

<p class="login-footer">SAE23 · R&amp;T · IUT de Béziers</p>

<script>
// Init thème
(function(){
  var t = localStorage.getItem('lu-theme') || 'dark';
  document.body.classList.toggle('dark', t === 'dark');
})();
</script>
</body>
</html>
