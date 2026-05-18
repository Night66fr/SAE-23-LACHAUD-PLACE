<?php
// ---- Redirection profs AVANT tout output ----
// On démarre la session ici pour lire le rôle SANS inclure login.php
// (qui génèrerait du HTML et bloquerait le header)
session_start();
if (!isset($_SESSION['auth']) || $_SESSION['auth'] !== 'ok') {
    header('Location: login-page.php');
    exit();
}
if (in_array($_SESSION['role'] ?? '', ['enseignant','admin'])) {
    header('Location: profile-prof.php');
    exit();
}

// ---- Connexion BDD pour traiter l'upload AVANT le HTML ----
$dbHost = 'localhost'; $dbName = 'db_PLACE_NEVEUX'; $dbUser = '22505078'; $dbPasswd = '126620';
try {
    $pdo = new PDO('mysql:host='.$dbHost.';dbname='.$dbName.';charset=utf8mb4',$dbUser,$dbPasswd);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) { die('Erreur BDD : '.htmlspecialchars($e->getMessage())); }

// ---- Upload avatar SÉCURISÉ (avant le HTML) ----
$uploadMsg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['avatar'])) {
    $file = $_FILES['avatar'];

    // 1. Vérifier que c'est bien un upload HTTP valide (pas une injection)
    if (!is_uploaded_file($file['tmp_name'])) {
        $uploadMsg = 'error:Fichier invalide.';

    // 2. Taille max 2 Mo
    } elseif ($file['size'] > 2 * 1024 * 1024) {
        $uploadMsg = 'error:Fichier trop lourd (max 2 Mo).';

    // 3. Vérifier le VRAI type MIME via getimagesize() — pas l'extension
    //    getimagesize() lit les octets réels du fichier, pas son nom
    //    Un fichier PHP renommé en .jpg échouera ici
    } elseif (!($imgInfo = @getimagesize($file['tmp_name']))) {
        $uploadMsg = "error:Le fichier n'est pas une image valide.";

    } else {
        // 4. Types MIME autorisés (liste blanche stricte)
        $allowedMimes = [
            'image/jpeg' => 'jpg',
            'image/png'  => 'png',
            'image/gif'  => 'gif',
            'image/webp' => 'webp',
        ];
        $detectedMime = $imgInfo['mime']; // MIME réel lu dans le fichier

        if (!isset($allowedMimes[$detectedMime])) {
            $uploadMsg = "error:Format non autorise. Seuls jpg, png, gif et webp sont acceptes.";

        } else {
            // 5. Extension forcée depuis le MIME réel (ignore le nom original)
            //    → impossible d'uploader shell.php en le nommant photo.jpg
            $ext  = $allowedMimes[$detectedMime];
            $dir  = 'uploads/avatars/';
            if (!is_dir($dir)) mkdir($dir, 0777, true);

            // 6. Nom de fichier basé sur l'ID user uniquement (pas sur le nom d'origine)
            //    → pas de path traversal, pas d'injection dans le nom
            $filename = 'avatar_' . (int)$_SESSION['user_id'] . '.' . $ext;
            $dest     = $dir . $filename;

            // 7. Supprimer les anciens avatars de l'utilisateur (toutes extensions)
            foreach ($allowedMimes as $oldExt) {
                $old2 = $dir . 'avatar_' . (int)$_SESSION['user_id'] . '.' . $old2Ext = $oldExt;
                if (file_exists($old2) && $old2 !== $dest) unlink($old2);
            }

            // 8. Déplacer le fichier
            if (move_uploaded_file($file['tmp_name'], $dest)) {
                // 9. Enregistrer en BDD
                $pdo->prepare('UPDATE users SET avatar = :a WHERE id = :id')
                    ->execute([':a' => $dest, ':id' => (int)$_SESSION['user_id']]);
                $uploadMsg = 'ok:Photo de profil mise à jour !';
            } else {
                $uploadMsg = 'error:Erreur upload — chmod 777 uploads/avatars/ requis.';
            }
        }
    }
}

$title = 'Mon Profil';
include('login.php');

$retour      = in_array($_SESSION['role'], ['enseignant','admin']) ? 'index-prof.php' : 'index-etudiants.php';
$retourLabel = in_array($_SESSION['role'], ['enseignant','admin']) ? 'Dashboard Prof' : 'Dashboard';

$stmt = $pdo->prepare("SELECT * FROM users WHERE id = :id");
$stmt->execute([':id' => $_SESSION['user_id']]);
$u = $stmt->fetch();
if (!$u) die("Utilisateur introuvable.");

$xp_palier  = 1000;
$xpActuel   = (int)$u['xp'];
$niveauActuel = (int)$u['niveau'];
$xpDansNiveau = $xpActuel % $xp_palier;
$progression  = min(100, round($xpDansNiveau / $xp_palier * 100));
$streak       = (int)$u['streak'];

// ============================================================
// DÉFINITION DES BADGES (easter eggs / achievements)
// Chaque badge : id, icon (SVG path), name, desc, condition, color, rarity
// condition = false = pas encore débloqué (tout à 0 pour l'instant)
// ============================================================
// ============================================================
// DONNÉES RÉELLES pour les badges
// ============================================================
$nbMissionsTotal  = 0;
$nbMissionsReseau = 0;
$nbMissionsSecu   = 0;
$nbMissionsDev    = 0;
$nbBugsValides    = 0;
$nbSoumissions    = 0;
$isFirstValideur  = false;
$heureCo          = (int)date('G'); // heure actuelle 0-23

try {
    // Missions validées par catégorie
    $stmtM = $pdo->prepare(
        'SELECT m.categorie, COUNT(*) as nb FROM soumissions s
         JOIN missions m ON s.mission_id=m.id
         WHERE s.user_id=:uid AND s.statut="valide"
         GROUP BY m.categorie'
    );
    $stmtM->execute([':uid'=>$u['id']]);
    foreach ($stmtM->fetchAll() as $row) {
        $nbMissionsTotal += (int)$row['nb'];
        if ($row['categorie']==='reseau') $nbMissionsReseau = (int)$row['nb'];
        if ($row['categorie']==='secu')   $nbMissionsSecu   = (int)$row['nb'];
        if ($row['categorie']==='dev')    $nbMissionsDev    = (int)$row['nb'];
    }
    // Bugs validés
    $stmtB = $pdo->prepare('SELECT COUNT(*) FROM bug_bounty WHERE user_id=:uid AND statut="valide"');
    $stmtB->execute([':uid'=>$u['id']]);
    $nbBugsValides = (int)$stmtB->fetchColumn();

    // Soumissions totales (pour "premier envoi")
    $stmtS = $pdo->prepare('SELECT COUNT(*) FROM soumissions WHERE user_id=:uid');
    $stmtS->execute([':uid'=>$u['id']]);
    $nbSoumissions = (int)$stmtS->fetchColumn();

    // Premier valideur sur la plateforme
    $premierVal = $pdo->query(
        'SELECT user_id FROM soumissions WHERE statut="valide" ORDER BY valide_le ASC LIMIT 1'
    )->fetchColumn();
    $isFirstValideur = ((int)$premierVal === (int)$u['id']);

    // Heure de connexion réelle (last_login)
    $heureCo = $u['last_login'] ? (int)date('G', strtotime($u['last_login'])) : (int)date('G');

} catch (Exception $e) {}

// Conditions de déblocage
$streak          = (int)$u['streak'];
$xpTotal         = (int)$u['xp'];
$niveau          = (int)$u['niveau'];
$hasAllCats      = ($nbMissionsReseau>=1 && $nbMissionsSecu>=1 && $nbMissionsDev>=1);

$badges = [
  // ---- COMMUN ----
  ['id'=>'first_step',
   'icon'=>'rocket', 'name'=>'Premier pas', 'rarity'=>'common', 'color'=>'#58cc02',
   'desc'=>"Tu as créé ton compte sur LevelUp. Bienvenue dans l'aventure !",
   'condition'=>"Avoir un compte actif",
   'unlocked'=> true // tout le monde l'a dès la création
  ],
  ['id'=>'login_1',
   'icon'=>'zap', 'name'=>'Allumé', 'rarity'=>'common', 'color'=>'#ff9600',
   'desc'=>"Tu t'es connecté pour la première fois. Le voyage commence !",
   'condition'=>"Se connecter au moins une fois",
   'unlocked'=> ($streak >= 1 || $xpTotal >= 0)
  ],
  ['id'=>'mission_1',
   'icon'=>'target', 'name'=>'En mission', 'rarity'=>'common', 'color'=>'#1cb0f6',
   'desc'=>"Tu as soumis ta première preuve de mission. C'est parti !",
   'condition'=>"Soumettre au moins 1 preuve",
   'unlocked'=> ($nbSoumissions >= 1)
  ],
  ['id'=>'bug_1',
   'icon'=>'bug', 'name'=>'Chasseur de bugs', 'rarity'=>'common', 'color'=>'#ff4b4b',
   'desc'=>"Tu as signalé ton premier bug sur la plateforme. Bon oeil !",
   'condition'=>"Signaler au moins 1 bug",
   'unlocked'=> ($nbBugsValides >= 1)
  ],

  // ---- RARE ----
  ['id'=>'streak_7',
   'icon'=>'flame', 'name'=>'En feu', 'rarity'=>'rare', 'color'=>'#ff6600',
   'desc'=>"7 jours de connexion consécutifs. Tu es serieux ! Bonus XP x1.5 actif.",
   'condition'=>"Maintenir un streak de 7 jours",
   'unlocked'=> ($streak >= 7)
  ],
  ['id'=>'streak_14',
   'icon'=>'flame', 'name'=>'Une semaine et demie !', 'rarity'=>'rare', 'color'=>'#ff4400',
   'desc'=>"14 jours de presence non-stop. Tu es regulier ! Bonus XP x1.75.",
   'condition'=>"Maintenir un streak de 14 jours",
   'unlocked'=> ($streak >= 14)
  ],
  ['id'=>'xp_500',
   'icon'=>'award', 'name'=>'Montée en grade', 'rarity'=>'rare', 'color'=>'#ce82ff',
   'desc'=>"500 XP accumules. Tu te demarques dans le classement.",
   'condition'=>"Atteindre 500 XP total",
   'unlocked'=> ($xpTotal >= 500)
  ],
  ['id'=>'missions_5',
   'icon'=>'check', 'name'=>'Régulier', 'rarity'=>'rare', 'color'=>'#58cc02',
   'desc'=>"5 missions validees par ton prof. Tu prends l'habitude, continue !",
   'condition'=>"Faire valider 5 missions",
   'unlocked'=> ($nbMissionsTotal >= 5)
  ],
  ['id'=>'reseau_3',
   'icon'=>'network', 'name'=>'Architecte réseau', 'rarity'=>'rare', 'color'=>'#1cb0f6',
   'desc'=>"3 missions Reseau validees. VLANs, routage et switchs n'ont plus de secrets !",
   'condition'=>"Valider 3 missions categorie Reseau",
   'unlocked'=> ($nbMissionsReseau >= 3)
  ],
  ['id'=>'secu_3',
   'icon'=>'shield', 'name'=>'Gardien', 'rarity'=>'rare', 'color'=>'#ff4b4b',
   'desc'=>"3 missions Securite validees. Les failles n'ont plus de secrets pour toi.",
   'condition'=>"Valider 3 missions categorie Securite",
   'unlocked'=> ($nbMissionsSecu >= 3)
  ],
  ['id'=>'dev_3',
   'icon'=>'code', 'name'=>'Codeur', 'rarity'=>'rare', 'color'=>'#58cc02',
   'desc'=>"3 missions Dev validees. PHP, Python, scripts - tu maitrises !",
   'condition'=>"Valider 3 missions categorie Dev",
   'unlocked'=> ($nbMissionsDev >= 3)
  ],

  // ---- ÉPIQUE ----
  ['id'=>'streak_21',
   'icon'=>'flame', 'name'=>'Trois semaines de feu', 'rarity'=>'epic', 'color'=>'#ff3300',
   'desc'=>'21 jours consécutifs ! Tu es clairement accro à LevelUp. Bonus XP x2 !',
   'condition'=>'Maintenir un streak de 21 jours',
   'unlocked'=> ($streak >= 21)
  ],
  ['id'=>'xp_2000',
   'icon'=>'award', 'name'=>'Niveau supérieur', 'rarity'=>'epic', 'color'=>'#ce82ff',
   'desc'=>'2000 XP au compteur. Tu es parmi les meilleurs de la promo !',
   'condition'=>"Atteindre 2000 XP total",
   'unlocked'=> ($xpTotal >= 2000)
  ],
  ['id'=>'missions_20',
   'icon'=>'trophy', 'name'=>'Accumulation', 'rarity'=>'epic', 'color'=>'#ffd900',
   'desc'=>'20 missions validées. Tu as prouvé ta valeur sur toute la ligne !',
   'condition'=>"Faire valider 20 missions",
   'unlocked'=> ($nbMissionsTotal >= 20)
  ],
  ['id'=>'bugs_5',
   'icon'=>'bug', 'name'=>'Bug Bounty Hunter', 'rarity'=>'epic', 'color'=>'#ff4b4b',
   'desc'=>'5 bugs validés par les profs. Tu contribues activement à améliorer la plateforme.',
   'condition'=>"Avoir 5 bugs valides",
   'unlocked'=> ($nbBugsValides >= 5)
  ],
  ['id'=>'nuit_blanche',
   'icon'=>'moon', 'name'=>'Nuit blanche', 'rarity'=>'epic', 'color'=>'#4a4aff',
   'desc'=>'Connecté entre 00h et 4h du matin. Tu travailles quand tout le monde dort !',
   'condition'=>'Se connecter entre minuit et 4h du matin',
   'unlocked'=> ($heureCo >= 0 && $heureCo < 4 && $u['last_login'] && date('G', strtotime($u['last_login'])) < 4)
  ],

  // ---- LÉGENDAIRE ----
  ['id'=>'first_blood',
   'icon'=>'zap', 'name'=>'First Blood', 'rarity'=>'legendary', 'color'=>'#ffd900',
   'desc'=>"Tu es le premier etudiant a avoir fait valider une mission sur la plateforme. Historique !",
   'condition'=>"Etre le tout premier a valider une mission",
   'unlocked'=> $isFirstValideur
  ],
  ['id'=>'full_house',
   'icon'=>'trophy', 'name'=>'Full House', 'rarity'=>'legendary', 'color'=>'#ffd900',
   'desc'=>"Au moins 1 mission validee dans chaque categorie (Reseau, Securite, Dev). Touche-a-tout !",
   'condition'=>"Valider 1 mission dans chaque categorie",
   'unlocked'=> $hasAllCats
  ],
  ['id'=>'phantom',
   'icon'=>'ghost', 'name'=>'Fantôme', 'rarity'=>'legendary', 'color'=>'#ce82ff',
   'desc'=>"Badge secret. Tu l as decouvert en explorant la plateforme. Bien joue !",
   'condition'=>"???",
   'unlocked'=> false // Easter egg à activer manuellement
  ],
  ['id'=>'perfectionist',
   'icon'=>'star', 'name'=>'Perfectionniste', 'rarity'=>'legendary', 'color'=>'#ffd900',
   'desc'=>"Niveau 20 atteint - le maximum absolu. Tu es une legende de la promo !",
   'condition'=>"Atteindre le niveau maximum (20)",
   'unlocked'=> ($niveau >= 20)
  ],
];

// SVG icons inline
function svgIcon($name, $size=24, $color='currentColor') {
  $icons = [
    'rocket'  => '<path d="M4.5 16.5c-1.5 1.26-2 5-2 5s3.74-.5 5-2c.71-.84.7-2.13-.09-2.91a2.18 2.18 0 0 0-2.91-.09z"/><path d="m12 15-3-3a22 22 0 0 1 2-3.95A12.88 12.88 0 0 1 22 2c0 2.72-.78 7.5-6 11a22.35 22.35 0 0 1-4 2z"/><path d="M9 12H4s.55-3.03 2-4c1.62-1.08 5 0 5 0"/><path d="M12 15v5s3.03-.55 4-2c1.08-1.62 0-5 0-5"/>',
    'zap'     => '<polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/>',
    'target'  => '<circle cx="12" cy="12" r="10"/><circle cx="12" cy="12" r="6"/><circle cx="12" cy="12" r="2"/>',
    'bug'     => '<path d="m8 2 1.88 1.88"/><path d="M14.12 3.88 16 2"/><rect width="8" height="14" x="8" y="8" rx="5"/><path d="M19 13h-2"/><path d="M19 18h-2"/><path d="M5 13H7"/><path d="M5 18H7"/><path d="M7 8 5.5 5.5"/><path d="M17 8l1.5-2.5"/>',
    'flame'   => '<path d="M8.5 14.5A2.5 2.5 0 0 0 11 12c0-1.38-.5-2-1-3-1.072-2.143-.224-4.054 2-6 .5 2.5 2 4.9 4 6.5 2 1.6 3 3.5 3 5.5a7 7 0 1 1-14 0c0-1.153.433-2.294 1-3a2.5 2.5 0 0 0 2.5 2.5z"/>',
    'award'   => '<circle cx="12" cy="8" r="6"/><path d="M15.477 12.89 17 22l-5-3-5 3 1.523-9.11"/>',
    'check'   => '<path d="M20 6 9 17l-5-5"/>',
    'network' => '<rect x="16" y="16" width="6" height="6" rx="1"/><rect x="2" y="16" width="6" height="6" rx="1"/><rect x="9" y="2" width="6" height="6" rx="1"/><path d="M5 16v-3a1 1 0 0 1 1-1h12a1 1 0 0 1 1 1v3"/><path d="M12 12V8"/>',
    'shield'  => '<path d="M20 13c0 5-3.5 7.5-7.66 8.95a1 1 0 0 1-.67-.01C7.5 20.5 4 18 4 13V6a1 1 0 0 1 1-1c2 0 4.5-1.2 6.24-2.72a1.17 1.17 0 0 1 1.52 0C14.51 3.81 17 5 19 5a1 1 0 0 1 1 1z"/>',
    'code'    => '<polyline points="16 18 22 12 16 6"/><polyline points="8 6 2 12 8 18"/>',
    'trophy'  => '<path d="M6 9H4.5a2.5 2.5 0 0 1 0-5H6"/><path d="M18 9h1.5a2.5 2.5 0 0 0 0-5H18"/><path d="M4 22h16"/><path d="M10 14.66V17c0 .55-.47.98-.97 1.21C7.85 18.75 7 20.24 7 22"/><path d="M14 14.66V17c0 .55.47.98.97 1.21C16.15 18.75 17 20.24 17 22"/><path d="M18 2H6v7a6 6 0 0 0 12 0V2z"/>',
    'moon'    => '<path d="M12 3a6 6 0 0 0 9 9 9 9 0 1 1-9-9Z"/>',
    'ghost'   => '<path d="M9 10h.01"/><path d="M15 10h.01"/><path d="M12 2a8 8 0 0 0-8 8v12l3-3 2.5 2.5L12 19l2.5 2.5L17 19l3 3V10a8 8 0 0 0-8-8z"/>',
    'star'    => '<polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/>',
    'lock'    => '<rect width="18" height="11" x="3" y="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/>',
    'user'    => '<path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>',
    'qr'      => '<rect width="5" height="5" x="3" y="3" rx="1"/><rect width="5" height="5" x="16" y="3" rx="1"/><rect width="5" height="5" x="3" y="16" rx="1"/><path d="M21 16h-3a2 2 0 0 0-2 2v3"/><path d="M21 21v.01"/><path d="M12 7v3a2 2 0 0 1-2 2H7"/><path d="M3 12h.01"/><path d="M12 3h.01"/><path d="M12 16v.01"/><path d="M16 12h1"/><path d="M21 12v.01"/><path d="M12 21v-1"/>',
  ];
  $path = $icons[$name] ?? $icons['star'];
  return '<svg xmlns="http://www.w3.org/2000/svg" width="'.$size.'" height="'.$size.'" viewBox="0 0 24 24" fill="none" stroke="'.$color.'" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">'.$path.'</svg>';
}

// ============================================================
// SAUVEGARDE AUTO DES BADGES DÉBLOQUÉS EN BDD
// ============================================================
try {
    foreach ($badges as $b) {
        if ($b['unlocked'] && in_array($b['rarity'], ['common','rare','epic'])) {
            // INSERT IGNORE : ne fait rien si déjà enregistré
            $pdo->prepare(
                'INSERT IGNORE INTO user_badges (user_id, badge_id) VALUES (:uid, :bid)'
            )->execute([':uid' => $u['id'], ':bid' => $b['id']]);
        }
    }
} catch (Exception $e) { /* table pas encore créée */ }

$rarityLabel = ['common'=>'Commun','rare'=>'Rare','epic'=>'Épique','legendary'=>'Légendaire'];
$rarityColor = ['common'=>'#afafaf','rare'=>'#1cb0f6','epic'=>'#ce82ff','legendary'=>'#ffd900'];
$unlockedCount = count(array_filter($badges, function($b){ return $b['unlocked']; }));
// Vraies stats soumissions
$nbMissionsValideesP = 0;
try {
    $sm = $pdo->prepare('SELECT COUNT(*) FROM soumissions WHERE user_id=:uid AND statut="valide"');
    $sm->execute([':uid' => $u['id']]);
    $nbMissionsValideesP = (int)$sm->fetchColumn();
} catch (Exception $e) {}
?>
<link rel="stylesheet" href="styles.css?v=<?= time() ?>"/>
<style>
/* Force streak colors en dark mode */
body.dark .streak-day.level-1 { background: #58cc0250 !important; }
body.dark .streak-day.level-2 { background: #58cc0280 !important; }
body.dark .streak-day.level-3 { background: #58cc02   !important; }
.streak-day.level-1 { background: #58cc0250 !important; }
.streak-day.level-2 { background: #58cc0280 !important; }
.streak-day.level-3 { background: #58cc02   !important; }
</style>

<!-- RETOUR -->
<a href="<?= $retour ?>" class="btn-retour">
  <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m12 19-7-7 7-7"/><path d="M19 12H5"/></svg>
  Retour <?= htmlspecialchars($retourLabel) ?>
</a>

<div class="profile-layout">

<!-- ============================
     COLONNE GAUCHE : Carte profil
     ============================ -->
<aside>
  <div class="profile-card">

    <!-- Avatar + bouton upload -->
    <?php
    $avatarPath = $u['avatar'] ?? '';
    $hasAvatar  = $avatarPath && file_exists($avatarPath);
    $initiales  = strtoupper(mb_substr($u['prenom'],0,1).mb_substr($u['nom'],0,1));
    ?>
    <form method="post" enctype="multipart/form-data" action="profile.php" style="position:relative;width:100px;margin:0 auto 14px">
      <?php if ($hasAvatar): ?>
        <img src="<?= htmlspecialchars($avatarPath).'?v='.time() ?>" alt="Avatar"
             style="width:100px;height:100px;border-radius:50%;object-fit:cover;
                    border:3px solid var(--green);box-shadow:0 0 0 3px var(--card);display:block">
      <?php else: ?>
        <div class="avatar-circle" style="margin:0"><?= $initiales ?></div>
      <?php endif; ?>
      <label title="Changer ma photo"
             style="position:absolute;bottom:2px;right:2px;width:30px;height:30px;border-radius:50%;
                    background:var(--green);border:2px solid var(--card);display:flex;
                    align-items:center;justify-content:center;cursor:pointer;transition:.15s"
             onmouseover="this.style.background='var(--green-dk)'"
             onmouseout="this.style.background='var(--green)'">
        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2"><path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/><circle cx="12" cy="13" r="4"/></svg>
        <input type="file" name="avatar" accept="image/*" onchange="this.form.submit()"
               style="position:absolute;inset:0;opacity:0;cursor:pointer;border-radius:50%">
      </label>
    </form>
    <?php if ($uploadMsg):
      [$msgType,$msgTxt] = explode(':', $uploadMsg, 2);
    ?>
      <div style="font-size:.75rem;font-weight:700;margin-bottom:8px;display:flex;align-items:center;justify-content:center;gap:4px;
                  color:<?= $msgType==='ok' ? 'var(--green)' : 'var(--red)' ?>">
        <?php if ($msgType==='ok'): ?>
          <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 6 9 17l-5-5"/></svg>
        <?php else: ?>
          <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>
        <?php endif; ?>
        <?= htmlspecialchars($msgTxt) ?>
      </div>
    <?php endif; ?>

    <div class="profile-name"><?= htmlspecialchars($u['prenom'].' '.$u['nom']) ?></div>
    <div class="profile-email"><?= htmlspecialchars($u['user']) ?></div>
    <div class="profile-role-badge"><?= ucfirst($u['role']) ?></div>

    <!-- Stats rapides -->
    <div class="profile-stats-row">
      <div class="stat-item">
        <div class="stat-val" style="color:var(--orange)"><?= $xpActuel ?></div>
        <div class="stat-lbl">XP Total</div>
      </div>
      <div class="stat-divider"></div>
      <div class="stat-item">
        <div class="stat-val" style="color:var(--red)"><?= $streak ?></div>
        <div class="stat-lbl">Streak</div>
      </div>
      <div class="stat-divider"></div>
      <div class="stat-item">
        <div class="stat-val" style="color:var(--green)"><?= $nbMissionsValideesP ?></div>
        <div class="stat-lbl">Missions</div>
      </div>
    </div>

    <!-- Barre XP -->
    <div class="xp-section">
      <div class="xp-niveau-row">
        <span class="xp-niveau-txt">Niveau <?= $niveauActuel ?> / 20</span>
        <span class="xp-niveau-num"><?= $xpDansNiveau ?> / <?= $xp_palier ?> XP</span>
      </div>
      <div class="xp-bar-bg">
        <div class="xp-bar-fill" style="width:<?= $progression ?>%"></div>
      </div>
      <div class="xp-bar-txt">
        <span>Niv. <?= $niveauActuel ?></span>
        <span><?= $progression ?>%</span>
        <span>Niv. <?= $niveauActuel+1 ?></span>
      </div>
    </div>

    <!-- QR Code + boutons export -->
    <div class="qr-section">
      <div style="font-size:.75rem;color:var(--muted);font-weight:700;margin-bottom:10px">Portfolio Public</div>
      <div style="background:#fff;padding:8px;display:inline-block;border-radius:8px;border:2px solid var(--border);margin-bottom:12px">
        <img src="https://api.qrserver.com/v1/create-qr-code/?size=90x90&data=http://r207.borelly.net/~<?= urlencode($u['user']) ?>/portfolio.php" alt="QR Code" width="90" height="90">
      </div>
      <div style="display:flex;flex-direction:column;gap:8px">
        <!-- Télécharger PDF (génération côté JS via print) -->
        <button class="btn-export" onclick="downloadPDF()"
                style="background:var(--green);display:flex;align-items:center;justify-content:center;gap:6px">
          <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
          Télécharger CV (PDF)
        </button>
        <!-- Imprimer -->
        <button class="btn-export" onclick="window.print()"
                style="background:var(--orange);display:flex;align-items:center;justify-content:center;gap:6px">
          <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg>
          Imprimer / Exporter
        </button>
      </div>
    </div>

  </div>
</aside>

<!-- ============================
     COLONNE DROITE : Contenu
     ============================ -->
<main>

  <!-- BADGES -->
  <div class="section-title">
    <?= svgIcon('trophy',16,'currentColor') ?>
    Badges &amp; Succès
    <span style="font-size:.75rem;text-transform:none;letter-spacing:0;color:var(--muted);font-weight:700"><?= $unlockedCount ?> / <?= count($badges) ?> débloqués</span>
  </div>

  <!-- Filtres par rareté -->
  <div class="rarity-filters">
    <span class="rarity-filter active-common" onclick="filterBadges('all',this)" style="border-color:#afafaf;color:#afafaf">Tous</span>
    <span class="rarity-filter" onclick="filterBadges('common',this)" style="--c:#afafaf">Commun</span>
    <span class="rarity-filter" onclick="filterBadges('rare',this)" style="--c:#1cb0f6">Rare</span>
    <span class="rarity-filter" onclick="filterBadges('epic',this)" style="--c:#ce82ff">Épique</span>
    <span class="rarity-filter" onclick="filterBadges('legendary',this)" style="--c:#ffd900">Légendaire</span>
  </div>

  <div class="badges-grid" id="badgesGrid">
    <?php foreach ($badges as $b):
      $col   = $b['color'];
      $rlbl  = $rarityLabel[$b['rarity']];
      $rcol  = $rarityColor[$b['rarity']];
      $isLeg = $b['rarity'] === 'legendary';

      // Tooltip : desc + condition pour common/rare/epic, secret pour legendary
      if ($b['unlocked']) {
          $tooltip = '✓ ' . $b['desc'];
      } elseif ($isLeg) {
          $tooltip = '??? Badge secret - continue a explorer';
      } else {
          $tooltip = 'Condition : ' . $b['condition'];
      }

      // Texte modal description
      $desc = $b['unlocked'] ? $b['desc'] : ($isLeg ? '??? Badge secret' : $b['desc']);
    ?>
    <div class="badge-card <?= $b['unlocked'] ? 'unlocked' : 'locked' ?> <?= ($isLeg && $b['unlocked']) ? 'legendary-glow' : '' ?>"
         data-rarity="<?= $b['rarity'] ?>"
         data-tooltip="<?= htmlspecialchars($tooltip) ?>"
         onclick="showBadge(this)"
         data-name="<?= htmlspecialchars($b['name']) ?>"
         data-desc="<?= htmlspecialchars($desc) ?>"
         data-condition="<?= htmlspecialchars(!$b['unlocked'] && !$isLeg ? $b['condition'] : '') ?>"
         data-rarity-label="<?= htmlspecialchars($rlbl) ?>"
         data-color="<?= $col ?>"
         data-rcol="<?= $rcol ?>"
         data-unlocked="<?= $b['unlocked'] ? '1' : '0' ?>"
         style="--current-color:<?= $col ?>;cursor:pointer;position:relative">

      <?php if (!$b['unlocked']): ?>
        <div style="position:absolute;top:8px;right:8px;opacity:.4">
          <?= svgIcon('lock',12,'currentColor') ?>
        </div>
      <?php endif; ?>

      <div class="badge-icon-wrap" style="opacity:<?= $b['unlocked'] ? '1' : '.35' ?>">
        <?= svgIcon($b['icon'], 26, $b['unlocked'] ? $col : '#afafaf') ?>
      </div>
      <div class="badge-name" style="<?= $b['unlocked'] ? '' : 'opacity:.5' ?>"><?= htmlspecialchars($b['name']) ?></div>
      <div class="badge-rarity-tag" style="background:<?= $rcol ?>22;color:<?= $rcol ?>;border:1px solid <?= $rcol ?>44">
        <?= $rlbl ?>
      </div>
    </div>
    <?php endforeach; ?>
  </div>

  <!-- Modal badge -->
  <div id="badgeModal" onclick="if(event.target===this)closeBadge()"
       style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.65);
              z-index:9999;align-items:center;justify-content:center;padding:20px">
    <div style="background:var(--card);border:2px solid var(--border);border-radius:var(--radius);
                padding:28px 24px;max-width:340px;width:100%;text-align:center;position:relative">
      <button onclick="closeBadge()"
              style="position:absolute;top:10px;right:14px;background:transparent;border:none;
                     color:var(--muted);font-size:1.6rem;cursor:pointer;line-height:1;font-family:var(--font)">×</button>
      <div id="modalIcon" style="width:72px;height:72px;border-radius:50%;
                display:flex;align-items:center;justify-content:center;
                margin:0 auto 14px;font-size:2rem;border:2px solid transparent"></div>
      <div id="modalRarity" style="font-size:.7rem;font-weight:900;text-transform:uppercase;
                letter-spacing:.8px;margin-bottom:6px"></div>
      <div id="modalName" style="font-size:1.1rem;font-weight:900;color:var(--text);margin-bottom:10px"></div>
      <div id="modalDesc" style="font-size:.85rem;color:var(--muted);font-weight:600;line-height:1.5"></div>
      <div id="modalCondition" style="display:none;margin-top:12px;padding:10px 14px;
                border-radius:var(--radius-sm);background:var(--bg);border:2px solid var(--border);
                font-size:.8rem;font-weight:700;color:var(--text);text-align:left;line-height:1.4">
      </div>
      <div id="modalStatus" style="margin-top:12px;padding:8px 16px;border-radius:var(--radius-sm);
                font-size:.85rem;font-weight:800"></div>
    </div>
  </div>

  <!-- STREAK CALENDAR STYLE GITHUB -->
  <div class="section-title" style="margin-top:32px">
    <?= svgIcon('flame',16,'currentColor') ?>
    Streak de présence
    <span style="font-size:.75rem;text-transform:none;letter-spacing:0;color:var(--muted);font-weight:700"><?= $streak ?> jour<?= $streak>1?'s':'' ?> actuellement</span>
  </div>

  <div class="streak-section">
    <div class="streak-header">
      <span class="streak-title">Calendrier des connexions (12 dernières semaines)</span>
      <span class="streak-count" style="display:flex;align-items:center;gap:6px">
        <?= svgIcon('flame',14,'currentColor') ?> <?= $streak ?>j
      </span>
    </div>

    <?php
    // Lire les vraies connexions depuis la table connexions (84 derniers jours)
    $today = new DateTime();
    $dateDebut = (clone $today)->modify('-83 days')->format('Y-m-d');

    // Récupérer toutes les dates de connexion de l'utilisateur sur 84 jours
    $stmtCo = $pdo->prepare(
        'SELECT date_co FROM connexions
         WHERE user_id = :uid AND date_co >= :debut
         ORDER BY date_co ASC'
    );
    $stmtCo->execute([':uid' => $u['id'], ':debut' => $dateDebut]);
    $datesConnectees = array_column($stmtCo->fetchAll(), 'date_co');
    $setDates = array_flip($datesConnectees); // pour lookup O(1)

    // Recalculer le vrai streak depuis la BDD
    $vraiStreak = 0;
    $check = clone $today;
    while (true) {
        $dateStr = $check->format('Y-m-d');
        if (isset($setDates[$dateStr])) {
            $vraiStreak++;
            $check->modify('-1 day');
        } else {
            break;
        }
    }
    $streak = $vraiStreak; // override la valeur session

    // ============================================================
    // CALENDRIER STREAK - VERSION SIMPLE ET FIABLE
    // ============================================================
    $todayStr = $today->format('Y-m-d');

    // Ajouter aujourd'hui de force (user est connecté puisqu'il voit la page)
    if (!isset($setDates[$todayStr])) {
        $setDates[$todayStr] = $todayStr;
    }

    // Aller au lundi de la semaine courante
    $startDate = clone $today;
    $dowNum = (int)$startDate->format('N'); // 1=lundi 7=dimanche
    if ($dowNum > 1) $startDate->modify('-'.($dowNum-1).' days');
    $startDate->modify('-11 weeks'); // 12 semaines au total

    // Construire les semaines jour par jour
    $weeks = [];
    $semaine = [];
    $d = clone $startDate;

    while ($d->format('Y-m-d') <= $todayStr) {
        $ds        = $d->format('Y-m-d');
        $connected = isset($setDates[$ds]);
        $isToday   = ($ds === $todayStr);

        // Calcul du niveau de vert
        $level = 0;
        if ($connected) {
            $daysAgo = (int)(new DateTime($todayStr))->diff(new DateTime($ds))->days;
            if ($daysAgo === 0)      $level = 3; // aujourd'hui = vert foncé
            elseif ($daysAgo < 7)   $level = 3;
            elseif ($daysAgo < 21)  $level = 2;
            else                    $level = 1;
        }

        $semaine[] = ['ds'=>$ds, 'level'=>$level, 'isToday'=>$isToday];

        // Dimanche ou dernier jour : fermer la semaine
        if ((int)$d->format('N') === 7 || $ds === $todayStr) {
            // Compléter jusqu'à 7 cases si besoin
            while (count($semaine) < 7) {
                $semaine[] = ['ds'=>'', 'level'=>-1, 'isToday'=>false];
            }
            $weeks[] = $semaine;
            $semaine = [];
        }

        $d->modify('+1 day');
    }

    ?>

    <div class="streak-calendar">
      <?php foreach ($weeks as $week): ?>
      <div class="streak-week">
        <?php foreach ($week as $day): ?>
          <?php if ($day['level'] === -1): ?>
            <div style="width:14px;height:14px;flex-shrink:0"></div>
          <?php else: ?>
            <div class="streak-day <?= $day['level'] > 0 ? 'level-'.$day['level'] : '' ?> <?= $day['isToday'] ? 'today' : '' ?>"
                 title="<?= $day['ds'] ?><?= $day['level'] > 0 ? ' — Connecté' : '' ?>"></div>
          <?php endif; ?>
        <?php endforeach; ?>
      </div>
      <?php endforeach; ?>
    </div>
    <div class="streak-legend" style="display:none"></div>
  </div>

  <!-- MISSIONS SUGGÉRÉES -->
  <div class="section-title" style="margin-top:32px">
    <?= svgIcon('target',16,'currentColor') ?>
    Missions suggérées
    <a href="missions.php" style="margin-left:auto;font-size:.75rem;color:var(--blue);font-weight:800;text-transform:none;letter-spacing:0;text-decoration:none">Voir tout →</a>
  </div>
  <?php
  $missionsSuggereees = [];
  try {
      $stmtSug = $pdo->prepare(
          'SELECT m.id, m.titre, m.categorie, m.ue, m.difficulte, m.xp
           FROM missions m
           WHERE m.actif = 1
           AND m.id NOT IN (
               SELECT mission_id FROM soumissions
               WHERE user_id = :uid AND statut IN ("valide","en_attente")
           )
           ORDER BY FIELD(m.difficulte,"facile","moyen","difficile"), m.xp ASC
           LIMIT 8'
      );
      $stmtSug->execute([':uid' => $u['id']]);
      $missionsSuggereees = $stmtSug->fetchAll();
  } catch (Exception $e) {}
  $catLbl = ['reseau'=>'Réseau','secu'=>'Sécurité','securite'=>'Sécurité','dev'=>'Dev'];
  $catCol = ['reseau'=>'var(--blue)','secu'=>'var(--red)','securite'=>'var(--red)','dev'=>'var(--green)'];
  $diffCol = ['facile'=>'var(--green)','moyen'=>'var(--orange)','difficile'=>'var(--red)'];
  ?>
  <?php if (empty($missionsSuggereees)): ?>
    <div style="text-align:center;padding:28px;background:var(--card);border:2px solid var(--green);border-radius:var(--radius)">
      <div style="font-size:1.5rem;margin-bottom:8px">🏆</div>
      <div style="font-weight:900;color:var(--green)">Toutes les missions complétées !</div>
    </div>
  <?php else: ?>
  <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(260px,1fr));gap:10px;margin-bottom:16px">
    <?php foreach ($missionsSuggereees as $ms): $col = $catCol[$ms['categorie']] ?? 'var(--muted)'; ?>
    <a href="soumettre.php?mission=<?= $ms['id'] ?>&from=profile" style="text-decoration:none">
      <div style="background:var(--card);border:2px solid var(--border);border-radius:var(--radius-sm);
                  padding:14px 16px;transition:.15s;border-left:3px solid <?= $col ?>">
        <div style="display:flex;justify-content:space-between;margin-bottom:6px">
          <span style="font-size:.65rem;font-weight:900;text-transform:uppercase;color:<?= $col ?>"><?= $catLbl[$ms['categorie']] ?? $ms['categorie'] ?></span>
          <span style="font-size:.65rem;font-weight:800;color:<?= $diffCol[$ms['difficulte']] ?? 'var(--muted)' ?>;text-transform:uppercase"><?= $ms['difficulte'] ?></span>
        </div>
        <div style="font-size:.85rem;font-weight:800;color:var(--text);margin-bottom:4px;line-height:1.3"><?= htmlspecialchars($ms['titre']) ?></div>
        <div style="font-size:.75rem;color:var(--muted);font-weight:600;margin-bottom:6px"><?= htmlspecialchars($ms['ue'] ?? '') ?></div>
        <div style="font-size:.82rem;font-weight:900;color:var(--orange)">⚡ +<?= $ms['xp'] ?> XP</div>
      </div>
    </a>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

  <!-- DERNIÈRES MISSIONS VALIDÉES -->
  <?php
  $missionsFaites = [];
  try {
      $stmtMF = $pdo->prepare(
          'SELECT m.titre, m.categorie, s.valide_le, m.xp
           FROM soumissions s
           JOIN missions m ON s.mission_id = m.id
           WHERE s.user_id = :uid AND s.statut = "valide"
           ORDER BY s.valide_le DESC LIMIT 5'
      );
      $stmtMF->execute([':uid' => $u['id']]);
      $missionsFaites = $stmtMF->fetchAll();
  } catch (Exception $e) {}
  ?>
  <?php if (!empty($missionsFaites)): ?>
  <div class="section-title" style="margin-top:24px">
    <?= svgIcon('check',16,'currentColor') ?>
    Dernières missions validées
  </div>
  <div class="missions-table-wrap">
    <table>
      <thead><tr><th>Mission</th><th>Catégorie</th><th>Date</th><th>XP</th></tr></thead>
      <tbody>
        <?php foreach ($missionsFaites as $m):
          $colP = $catCol[$m['categorie']] ?? 'var(--muted)';
          $lblP = $catLbl[$m['categorie']] ?? $m['categorie'];
        ?>
        <tr>
          <td style="font-weight:700"><?= htmlspecialchars($m['titre']) ?></td>
          <td><span style="background:<?= $colP ?>22;color:<?= $colP ?>;padding:2px 8px;border-radius:20px;font-size:.75rem;font-weight:800;border:1px solid <?= $colP ?>"><?= $lblP ?></span></td>
          <td style="color:var(--muted)"><?= $m['valide_le'] ? date('d/m/Y', strtotime($m['valide_le'])) : '—' ?></td>
          <td style="color:var(--green);font-weight:800">+<?= $m['xp'] ?> XP</td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>

</div><!-- fin colonne droite -->
</div><!-- fin profile-layout -->

</div><!-- fin main-content -->

<style>
@media print {
  .topbar,.btn-retour,.rarity-filters,.badge-lock-icon,.streak-section,.tabs{display:none!important}
  body{background:#fff!important;color:#000!important}
  .profile-layout{grid-template-columns:220px 1fr!important}
  .profile-card{position:static!important;border:1px solid #ccc!important}
  .badge-card.locked{display:none!important}
  .badge-card.unlocked{border:1px solid #ccc!important;break-inside:avoid}
  .badges-grid{grid-template-columns:repeat(4,1fr)!important}
  .xp-bar-fill{background:#58cc02!important;-webkit-print-color-adjust:exact;print-color-adjust:exact}
  .avatar-circle{-webkit-print-color-adjust:exact;print-color-adjust:exact}
}
</style>
<script>
// ---- Filtres badges ----
function filterBadges(rarity, btn) {
  document.querySelectorAll('.rarity-filter-btn').forEach(function(b) {
    b.style.opacity = '0.5';
    b.style.background = 'transparent';
  });
  if (btn) { btn.style.opacity = '1'; btn.style.background = btn.style.borderColor + '22'; }

  document.querySelectorAll('.badge-card').forEach(function(card) {
    card.style.display = (rarity === 'all' || card.dataset.rarity === rarity) ? '' : 'none';
  });
}

function showBadge(el) {
  var modal     = document.getElementById('badgeModal');
  var unlocked  = el.dataset.unlocked === '1';
  var col       = el.dataset.color;
  var rcol      = el.dataset.rcol;

  var iconEl = document.getElementById('modalIcon');
  iconEl.style.background  = col + '22';
  iconEl.style.borderColor = col;
  iconEl.textContent       = unlocked ? '🏆' : '🔒';

  document.getElementById('modalRarity').textContent = el.dataset.rarityLabel;
  document.getElementById('modalRarity').style.color = rcol;
  document.getElementById('modalName').textContent   = el.dataset.name;
  document.getElementById('modalDesc').textContent   = el.dataset.desc;

  var condEl  = document.getElementById('modalCondition');
  var cond    = el.dataset.condition;
  if (cond && !unlocked) {
    condEl.style.display = '';
    condEl.innerHTML = '<span style="color:var(--orange);font-weight:900">📋 Pour débloquer :</span><br>' + cond;
  } else {
    condEl.style.display = 'none';
  }

  var statusEl = document.getElementById('modalStatus');
  if (unlocked) {
    statusEl.textContent      = '✓ Badge débloqué !';
    statusEl.style.background = 'rgba(88,204,2,.12)';
    statusEl.style.color      = '#58cc02';
    statusEl.style.border     = '2px solid #58cc02';
  } else {
    statusEl.textContent      = '🔒 Non débloqué';
    statusEl.style.background = 'rgba(175,175,175,.1)';
    statusEl.style.color      = '#afafaf';
    statusEl.style.border     = '2px solid #afafaf';
  }

  modal.style.display = 'flex';
  document.body.style.overflow = 'hidden';
}

function closeBadge() {
  document.getElementById('badgeModal').style.display = 'none';
  document.body.style.overflow = '';
}

document.addEventListener('keydown', function(e) {
  if (e.key === 'Escape') closeBadge();
});

// ---- Tooltip global fixe (suit la souris, z-index max) ----
var tt = document.createElement('div');
tt.style.cssText = 'position:fixed;z-index:9999;background:#111827;color:#f9fafb;' +
  'font-size:.78rem;font-weight:700;padding:10px 14px;border-radius:10px;max-width:220px;' +
  'line-height:1.5;text-align:center;pointer-events:none;opacity:0;transition:opacity .15s;' +
  'box-shadow:0 8px 24px rgba(0,0,0,.6);border:1px solid rgba(255,255,255,.15);display:none';
document.body.appendChild(tt);

function moveTT(e) {
  var tw = tt.offsetWidth || 220;
  var th = tt.offsetHeight || 50;
  var x  = e.clientX - tw / 2;
  var y  = e.clientY - th - 16;
  if (y < 8) y = e.clientY + 16;
  x = Math.max(8, Math.min(x, window.innerWidth - tw - 8));
  tt.style.left = x + 'px';
  tt.style.top  = y + 'px';
}

// Délégation sur le body — fonctionne même si les cartes sont créées après
var ttHideTimer = null;

document.body.addEventListener('mouseover', function(e) {
  var card = e.target.closest('.badge-card[data-tooltip]');
  if (card) {
    // Annuler tout timer de masquage en cours
    if (ttHideTimer) { clearTimeout(ttHideTimer); ttHideTimer = null; }
    tt.textContent   = card.dataset.tooltip;
    tt.style.display = 'block';
    tt.style.opacity = '1';
    moveTT(e);
  }
});

document.body.addEventListener('mousemove', function(e) {
  var card = e.target.closest('.badge-card[data-tooltip]');
  if (card) {
    if (ttHideTimer) { clearTimeout(ttHideTimer); ttHideTimer = null; }
    tt.style.display = 'block';
    tt.style.opacity = '1';
    moveTT(e);
  }
});

document.body.addEventListener('mouseleave', function() {
  tt.style.opacity = '0';
  ttHideTimer = setTimeout(function(){ tt.style.display = 'none'; }, 200);
});

document.body.addEventListener('mouseout', function(e) {
  // Masquer seulement si on quitte vraiment la carte (pas un enfant)
  var card = e.target.closest('.badge-card[data-tooltip]');
  if (!card) return;
  var to = e.relatedTarget;
  // Si on va vers un enfant du même badge-card, ne pas masquer
  if (to && card.contains(to)) return;
  tt.style.opacity = '0';
  ttHideTimer = setTimeout(function(){ tt.style.display = 'none'; }, 200);
});

function downloadPDF() {
  var noprint = document.querySelectorAll('.theme-toggle,.btn-retour,.rarity-filters');
  noprint.forEach(function(el){ el.dataset.origDisplay = el.style.display; el.style.display='none'; });
  window.print();
  setTimeout(function(){
    noprint.forEach(function(el){ el.style.display = el.dataset.origDisplay || ''; });
  }, 1500);
}
(function(){
  var t = localStorage.getItem("lu-theme") || "dark";
  document.body.classList.toggle("dark", t === "dark");
})();
</script>
</body>
</html>
