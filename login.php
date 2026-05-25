<?php
require_once __DIR__.'/config.php';
// ============================================================
//  LevelUp – login.php
//  À inclure EN PREMIER dans chaque page protégée.
//  Si non connecté → redirige vers login-page.php
// ============================================================
if (session_status() === PHP_SESSION_NONE) if (session_status() === PHP_SESSION_NONE) session_start();

// ---- PDO : connexion BDD ----
$pdo = getDB();
function cbPrintf() {
    $args = func_get_args();
    if (!$args) $args = [''];
    $args[0] .= "\n";
    call_user_func_array('printf', $args);
}

// ---- Restriction par rôle (à appeler après include) ----
function requireRole($roles) {
    if (!is_array($roles)) $roles = [$roles];
    if (!in_array(cbGetValue($_SESSION, 'role', ''), $roles, true)) {
        // Mauvais rôle → redirection vers la bonne page
        $role = cbGetValue($_SESSION, 'role', 'etudiant');
        $dest = in_array($role, ['enseignant', 'admin']) ? 'index-prof.php' : 'index-etudiants.php';
        header('Location: ' . $dest);
        exit();
    }
}

// ---- Non connecté → login ----
if (cbGetValue($_SESSION, 'auth') !== 'ok') {
    header('Location: login-page.php');
    exit();
}

// ---- En-tête HTML ----
$pageTitle = isset($title) ? $title : 'LevelUp';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="utf-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1"/>
<title><?= htmlspecialchars($pageTitle) ?> – LevelUp</title>
<link rel="stylesheet" href="styles.css"/>
<style>
/* Surcharge topbar pour thème actuel */
body.dark .topbar{background:#0d1b35;border-color:#2b3d47}
body.dark .main-content{background:var(--bg)}
</style>
</head>
<body>
<?php
// ---- Navbar ----
$prenom = htmlspecialchars(cbGetValue($_SESSION,'prenom','') ?: cbGetValue($_SESSION,'user',''));
$role   = htmlspecialchars(cbGetValue($_SESSION,'role','etudiant'));
$xp     = (int)cbGetValue($_SESSION,'xp',0);
$niveau = (int)cbGetValue($_SESSION,'niveau',1);
$streak = (int)cbGetValue($_SESSION,'streak',0);

echo '<nav class="topbar">';
$homeUrl = in_array($_SESSION['role']??'', ['enseignant','admin']) ? 'index-prof.php' : 'index-etudiants.php';
echo '<a href="'.$homeUrl.'" class="topbar-brand" style="text-decoration:none;cursor:pointer"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"/><path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"/></svg> LevelUp &ndash; Plateforme R&amp;T</a>';
echo '<div class="topbar-info">';
echo '<span class="topbar-user">'.$prenom.'</span>';
echo '<span class="badge-role">'.$role.'</span>';
echo '<span class="badge-xp">'.$xp.' XP &bull; Niv. '.$niveau.'</span>';
if ($streak > 0) echo '<span class="badge-streak">&#128293; '.$streak.'j</span>';
echo '<a href="profile.php" class="nav-link">Mon profil</a>';
echo '<button class="theme-toggle" id="themeBtn" onclick="toggleTheme()"><svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3a6 6 0 0 0 9 9 9 9 0 1 1-9-9Z"/></svg></button>';
echo '<a class="btn-logout" href="logout.php?logout=true">D&eacute;connexion</a>';
echo '</div></nav>';
echo '<div class="main-content">';
?><script>
// ================================================================
//  LevelUp – Toggle thème clair / sombre
//  Persisté dans localStorage, appliqué immédiatement
// ================================================================
(function(){
  var saved = localStorage.getItem('lu-theme') || 'dark';
  applyTheme(saved, false);
})();

function applyTheme(t, animate){
  if(animate) document.body.style.transition = 'background .3s, color .3s';
  document.body.classList.toggle('dark', t === 'dark');
  localStorage.setItem('lu-theme', t);
  // Mettre à jour l'icône du bouton
  var btn = document.getElementById('themeBtn');
  if(!btn) return;
  if(t === 'dark'){
    // Montrer icône soleil (pour passer au clair)
    btn.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.93 4.93l1.41 1.41M17.66 17.66l1.41 1.41M2 12h2M20 12h2M6.34 17.66l-1.41 1.41M19.07 4.93l-1.41 1.41"/></svg>';
    btn.title = 'Passer en thème clair';
  } else {
    // Montrer icône lune (pour passer au sombre)
    btn.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3a6 6 0 0 0 9 9 9 9 0 1 1-9-9Z"/></svg>';
    btn.title = 'Passer en thème sombre';
  }
}

function toggleTheme(){
  var current = localStorage.getItem('lu-theme') || 'dark';
  applyTheme(current === 'dark' ? 'light' : 'dark', true);
}

// Appliquer l'icône correcte dès le chargement DOM
document.addEventListener('DOMContentLoaded', function(){
  var saved = localStorage.getItem('lu-theme') || 'dark';
  applyTheme(saved, false);
});
</script>
