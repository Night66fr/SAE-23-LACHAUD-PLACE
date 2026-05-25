<?php
require_once __DIR__.'/config.php';
// ============================================================
//  LevelUp – login-page.php
//  Connexion avec redirection par rôle
// ============================================================
session_start();

$pdo = getDB();


$error   = '';
$success = '';
$vue     = $_GET['vue'] ?? 'login';
$roleActif = $_POST['role_choisi'] ?? $_GET['role'] ?? 'etudiant';

// ============================================================
//  TRAITEMENT CONNEXION
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'login') {
    $user      = trim($_POST['user'] ?? '');
    $pass      = $_POST['pass'] ?? '';
    if ($user === '' || $pass === '') {
        $error = 'Veuillez remplir tous les champs.';
    } else {
        $stmt = $pdo->prepare(
            'SELECT id, user, pass, role, nom, prenom, xp, niveau, streak
             FROM users WHERE (user = :u OR email = :u2) AND actif = 1 LIMIT 1'
        );
        $stmt->execute([':u' => $user, ':u2' => $user]);
        $row = $stmt->fetch();

        if (!$row || !password_verify($pass, $row['pass'])) {
            $error = 'Identifiant ou mot de passe incorrect.';
        } else {
            // ---- Streak ----
            $s2 = $pdo->prepare('SELECT last_login, streak FROM users WHERE id=:id');
            $s2->execute([':id' => $row['id']]);
            $prev      = $s2->fetch();
            $today     = (new DateTime())->format('Y-m-d');
            $newStreak = (int)$row['streak'];

            // ---- Enregistrer la connexion du jour (TOUJOURS, avant tout le reste) ----
            $pdo->prepare('INSERT IGNORE INTO connexions (user_id, date_co) VALUES (:uid, CURDATE())')
                ->execute([':uid' => $row['id']]);

            if ($prev && $prev['last_login']) {
                $lastDate = (new DateTime($prev['last_login']))->format('Y-m-d');
                if ($lastDate === $today) {
                    // Déjà connecté aujourd'hui → streak inchangé, pas de UPDATE inutile
                    $newStreak = (int)$prev['streak'];
                } else {
                    $yesterday = (new DateTime('yesterday'))->format('Y-m-d');
                    $newStreak = ($lastDate === $yesterday) ? (int)$prev['streak'] + 1 : 1;
                    $pdo->prepare('UPDATE users SET last_login=NOW(), streak=:s WHERE id=:id')
                        ->execute([':s' => $newStreak, ':id' => $row['id']]);
                }
            } else {
                $newStreak = 1;
                $pdo->prepare('UPDATE users SET last_login=NOW(), streak=:s WHERE id=:id')
                    ->execute([':s' => $newStreak, ':id' => $row['id']]);
            }

            // ---- Session ----
            $_SESSION['auth']    = 'ok';
            $_SESSION['user_id'] = $row['id'];
            $_SESSION['user']    = $row['user'];
            $_SESSION['role']    = $row['role'];
            $_SESSION['nom']     = $row['nom'];
            $_SESSION['prenom']  = $row['prenom'];
            $_SESSION['xp']      = $row['xp'];
            $_SESSION['niveau']  = $row['niveau'];
            $_SESSION['streak']  = $newStreak;

            // ---- Redirection selon rôle ----
            $dest = in_array($row['role'], ['enseignant','admin']) ? 'index-prof.php' : 'index-etudiants.php';
            header('Location: '.$dest);
            exit();
        }
    }
}

// ============================================================
//  TRAITEMENT MOT DE PASSE OUBLIÉ (simulé)
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'oubli') {
    $email = trim($_POST['email'] ?? '');
    $vue   = 'oubli';
    if ($email === '') {
        $error = 'Veuillez entrer votre adresse e-mail.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Adresse e-mail invalide.';
    } else {
        $success = 'Si cette adresse est connue, un e-mail de réinitialisation a été envoyé.';
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="utf-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1"/>
<title>LevelUp – Connexion</title>
<link rel="stylesheet" href="styles.css"/>
</head>
<body class="login-page-body">

<div class="login-header">
  <div style="color:var(--blue);margin-bottom:8px"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"/><path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"/></svg></div>
  <div class="flag">
    <div class="flag-band bleu"></div>
    <div class="flag-band blanc"></div>
    <div class="flag-band rouge"></div>
  </div>
  <h1>LevelUp</h1>
  <p>Plateforme d'apprentissage R&amp;T &bull; IUT de Béziers</p>
</div>

<div class="login-card">

<?php if ($vue === 'oubli'): ?>

  <a class="btn-back" href="login-page.php"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m12 19-7-7 7-7"/><path d="M19 12H5"/></svg> Retour</a>
  <h2><svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="18" height="11" x="3" y="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg> Mot de passe oublié</h2>
  <?php if ($error)   echo '<div class="msg-err">'.htmlspecialchars($error).'</div>'; ?>
  <?php if ($success) echo '<div class="msg-ok">'.htmlspecialchars($success).'</div>'; ?>
  <?php if (!$success): ?>
  <form method="post" action="login-page.php?vue=oubli">
    <input type="hidden" name="action" value="oubli"/>
    <div class="form-group">
      <label for="email"><svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="20" height="16" x="2" y="4" rx="2"/><path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"/></svg> Votre adresse e-mail universitaire</label>
      <input type="email" id="email" name="email" placeholder="prenom.nom@etu.umontpellier.fr" required/>
    </div>
    <button type="submit" class="btn-main"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m22 2-7 20-4-9-9-4Z"/><path d="M22 2 11 13"/></svg> Envoyer le lien de réinitialisation</button>
  </form>
  <?php endif; ?>

<?php else: ?>

  <h2>Connexion</h2>
  <?php if ($error) echo '<div class="msg-err">'.htmlspecialchars($error).'</div>'; ?>

  <form method="post" action="login-page.php" id="loginForm">
    <input type="hidden" name="action" value="login"/>
    <input type="hidden" name="role_choisi" id="role_choisi" value="<?= htmlspecialchars($roleActif) ?>"/>

    <div class="role-switch">
      <button type="button" class="role-btn <?= $roleActif==='etudiant' ? 'active-etudiant' : '' ?>"
              onclick="setRole('etudiant',this)"><svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 10v6M2 10l10-5 10 5-10 5z"/><path d="M6 12v5c3 3 9 3 12 0v-5"/></svg> Étudiant</button>
      <button type="button" class="role-btn <?= $roleActif==='enseignant' ? 'active-enseignant' : '' ?>"
              onclick="setRole('enseignant',this)"><svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><polyline points="16 11 18 13 22 9"/></svg> Enseignant</button>
    </div>

    <div class="form-group">
      <label for="user"><svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="20" height="16" x="2" y="4" rx="2"/><path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"/></svg> Votre e-mail</label>
      <input type="text" id="user" name="user" placeholder="prenom.nom@etu.umontpellier.fr"
             value="<?= htmlspecialchars($_POST['user'] ?? '') ?>" required/>
    </div>
    <div class="form-group">
      <label for="pass"><svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="18" height="11" x="3" y="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg> Mot de passe</label>
      <input type="password" id="pass" name="pass" placeholder="••••••••" required/>
    </div>


    <button type="submit" class="btn-main">Se connecter</button>
  </form>

  <div class="links">
    <a href="login-page.php?vue=oubli">Mot de passe oublié ?</a>
    <a href="register.php">Créer un compte</a>
  </div>

<?php endif; ?>

</div>

<p class="login-footer">SAE23 &bull; R&amp;T &bull; IUT de Béziers</p>

<script>
function setRole(role, btn) {
  document.getElementById('role_choisi').value = role;
  document.querySelectorAll('.role-btn').forEach(b => b.className = 'role-btn');
  btn.classList.add('active-' + role);
}
</script>
<script>
(function(){
  var t = localStorage.getItem("lu-theme") || "dark";
  document.body.classList.toggle("dark", t === "dark");
})();
</script>
</body>
</html>