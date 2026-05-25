<?php
require_once __DIR__.'/config.php';
// ============================================================
//  LevelUp – profile-prof.php
//  Page profil dédiée aux enseignants / admins
// ============================================================

// ---- DOIT être AVANT tout HTML : session + auth + upload ----
session_start();
if (!isset($_SESSION['auth']) || $_SESSION['auth'] !== 'ok') {
    header('Location: login-page.php'); exit();
}
if (!in_array($_SESSION['role'] ?? '', ['enseignant','admin'])) {
    header('Location: index-etudiants.php'); exit();
}

// Connexion BDD (nécessaire pour l'upload avant le HTML)
$pdo = getDB(); elseif ($file['size'] > 2 * 1024 * 1024) {
        $uploadMsg = 'error:Fichier trop lourd (max 2 Mo).';
    } else {
        $dir      = 'uploads/avatars/';
        if (!is_dir($dir)) mkdir($dir, 0755, true);
        $filename = 'avatar_'.$_SESSION['user_id'].'.'.$ext;
        $dest     = $dir.$filename;
        if (move_uploaded_file($file['tmp_name'], $dest)) {
            // Supprimer l'ancienne avatar si extension différente
            foreach ($allowed as $e) {
                $old = $dir.'avatar_'.$_SESSION['user_id'].'.'.$e;
                if ($e !== $ext && file_exists($old)) unlink($old);
            }
            // Sauvegarder en BDD
            $pdo->prepare('UPDATE users SET avatar = :a WHERE id = :id')
                ->execute([':a' => $dest, ':id' => $_SESSION['user_id']]);
            $_SESSION['avatar'] = $dest;
            $uploadMsg = 'ok:Photo de profil mise à jour !';
        } else {
            $uploadMsg = 'error:Erreur lors de l\'upload. Vérifiez les permissions du dossier.';
        }
    }
}

$title = 'Mon Profil Professeur';
include('login.php'); // génère <!DOCTYPE> + navbar

// ---- Données utilisateur ----
$stmt = $pdo->prepare('SELECT * FROM users WHERE id = :id');
$stmt->execute([':id' => $_SESSION['user_id']]);
$u = $stmt->fetch();
if (!$u) die('Utilisateur introuvable.');

// ---- Stats enseignant ----
$nbEtudiants   = (int)$pdo->query('SELECT COUNT(*) FROM users WHERE role="etudiant" AND actif=1')->fetchColumn();

// Missions créées par ce prof (ou toutes si admin)
try {
    $nbMissions = (int)$pdo->query('SELECT COUNT(*) FROM missions WHERE actif=1')->fetchColumn();
} catch (Exception $e) { $nbMissions = 0; }

// Validations effectuées par ce prof
try {
    $stmtVal = $pdo->prepare('SELECT COUNT(*) FROM soumissions WHERE valide_par = :id AND statut = "valide"');
    $stmtVal->execute([':id' => $_SESSION['user_id']]);
    $nbValidations = (int)$stmtVal->fetchColumn();
} catch (Exception $e) { $nbValidations = 0; }

// Bugs traités par ce prof
try {
    $stmtBug = $pdo->prepare('SELECT COUNT(*) FROM bug_bounty WHERE statut IN ("valide","invalide")');
    $stmtBug->execute();
    $nbBugsTraites = (int)$stmtBug->fetchColumn();
} catch (Exception $e) { $nbBugsTraites = 0; }

// Missions par catégorie
try {
    $stmtCats = $pdo->query('SELECT categorie, COUNT(*) as nb FROM missions WHERE actif=1 GROUP BY categorie');
    $missionsCat = ['reseau'=>0,'secu'=>0,'dev'=>0];
    foreach ($stmtCats->fetchAll() as $row) $missionsCat[$row['categorie']] = (int)$row['nb'];
} catch (Exception $e) { $missionsCat = ['reseau'=>0,'secu'=>0,'dev'=>0]; }

// Dernières validations effectuées par ce prof
try {
    $stmtRecent = $pdo->prepare(
        'SELECT s.valide_le, m.titre, m.categorie, u.prenom, u.nom, m.xp
         FROM soumissions s
         JOIN missions m ON s.mission_id = m.id
         JOIN users u ON s.user_id = u.id
         WHERE s.valide_par = :id AND s.statut = "valide"
         ORDER BY s.valide_le DESC LIMIT 5'
    );
    $stmtRecent->execute([':id' => $_SESSION['user_id']]);
    $dernieresValidations = $stmtRecent->fetchAll();
} catch (Exception $e) { $dernieresValidations = []; }

// Avatar
$avatarPath = $u['avatar'] ?? '';
$hasAvatar  = $avatarPath && file_exists($avatarPath);
$initiales  = strtoupper(mb_substr($u['prenom'],0,1).mb_substr($u['nom'],0,1));
?>
<link rel="stylesheet" href="styles.css"/>
<style>
/* Spécifique profil prof */
.prof-layout{display:grid;grid-template-columns:280px 1fr;gap:24px;align-items:start}
@media(max-width:860px){.prof-layout{grid-template-columns:1fr}}

/* Carte gauche */
.prof-card{
  background:var(--card);border:2px solid var(--border);
  border-radius:var(--radius);padding:28px 20px;text-align:center;
  position:sticky;top:80px;
}

/* Avatar upload */
.avatar-wrap{position:relative;width:100px;height:100px;margin:0 auto 14px}
.avatar-img{
  width:100px;height:100px;border-radius:50%;object-fit:cover;
  border:3px solid var(--purple);box-shadow:0 0 0 3px var(--card);display:block;
}
.avatar-initiales{
  width:100px;height:100px;border-radius:50%;
  background:linear-gradient(135deg,var(--purple),var(--blue));
  display:flex;align-items:center;justify-content:center;
  font-size:2rem;font-weight:900;color:#fff;
  border:3px solid var(--purple);box-shadow:0 0 0 3px var(--card);
}
.avatar-edit-btn{
  position:absolute;bottom:2px;right:2px;
  width:28px;height:28px;border-radius:50%;
  background:var(--purple);border:2px solid var(--card);
  display:flex;align-items:center;justify-content:center;
  cursor:pointer;transition:.15s;
}
.avatar-edit-btn:hover{background:var(--purple-dk)}
.avatar-edit-btn input[type=file]{
  position:absolute;inset:0;opacity:0;cursor:pointer;border-radius:50%;
}
.upload-form{margin-top:0}
.upload-msg-ok {color:var(--green);font-size:.78rem;font-weight:700;margin-top:8px;display:flex;align-items:center;justify-content:center;gap:4px}
.upload-msg-err{color:var(--red);  font-size:.78rem;font-weight:700;margin-top:8px;display:flex;align-items:center;justify-content:center;gap:4px}

/* Infos prof */
.prof-name{font-size:1.2rem;font-weight:900;color:var(--text);margin-bottom:2px}
.prof-email{font-size:.75rem;color:var(--muted);font-weight:600;margin-bottom:12px;word-break:break-all}
.prof-role-badge{
  display:inline-block;padding:5px 16px;border-radius:20px;
  font-size:.8rem;font-weight:800;text-transform:uppercase;letter-spacing:.5px;
  background:var(--purple);color:#fff;box-shadow:0 2px 0 var(--purple-dk);margin-bottom:18px;
}

/* Stats */
.prof-stats{display:grid;grid-template-columns:1fr 1fr;gap:10px;margin:16px 0}
.prof-stat-card{
  background:var(--bg);border:2px solid var(--border);
  border-radius:var(--radius-sm);padding:12px 10px;text-align:center;
}
body.dark .prof-stat-card{background:var(--bg2)}
.prof-stat-val{font-size:1.6rem;font-weight:900;color:var(--text)}
.prof-stat-lbl{font-size:.65rem;color:var(--muted);font-weight:800;text-transform:uppercase;letter-spacing:.5px;margin-top:2px}

/* Colonne droite */
.info-block{
  background:var(--card);border:2px solid var(--border);
  border-radius:var(--radius);padding:22px 24px;margin-bottom:20px;
}
.info-block h3{font-size:.8rem;font-weight:900;color:var(--muted);text-transform:uppercase;letter-spacing:1px;margin-bottom:16px;display:flex;align-items:center;gap:8px}
.info-block h3::after{content:'';flex:1;height:2px;background:var(--border);border-radius:2px}

/* Champs info */
.info-field{display:flex;align-items:center;justify-content:space-between;padding:10px 0;border-bottom:1px solid var(--border);gap:12px}
.info-field:last-child{border-bottom:none}
.info-label{font-size:.78rem;color:var(--muted);font-weight:800;text-transform:uppercase;letter-spacing:.5px;flex-shrink:0}
.info-value{font-size:.9rem;font-weight:700;color:var(--text);text-align:right;word-break:break-all}

/* Activité timeline */
.timeline{list-style:none;padding:0;margin:0;position:relative}
.timeline::before{content:'';position:absolute;left:16px;top:0;bottom:0;width:2px;background:var(--border)}
.tl-item{display:flex;gap:14px;padding:0 0 18px 0;position:relative}
.tl-dot{
  width:32px;height:32px;border-radius:50%;background:var(--card);border:2px solid var(--border);
  display:flex;align-items:center;justify-content:center;flex-shrink:0;z-index:1;
}
.tl-dot.green{border-color:var(--green);background:var(--green-sh)}
.tl-dot.blue {border-color:var(--blue); background:var(--blue-sh)}
.tl-dot.purple{border-color:var(--purple);background:#ce82ff15}
.tl-dot.orange{border-color:var(--orange);background:#ff960015}
.tl-body{flex:1;padding-top:4px}
.tl-title{font-size:.88rem;font-weight:800;color:var(--text)}
.tl-sub{font-size:.76rem;color:var(--muted);font-weight:600;margin-top:2px}

/* Catégories compétences */
.skills-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(160px,1fr));gap:10px}
.skill-item{
  background:var(--bg);border:2px solid var(--border);border-radius:var(--radius-sm);
  padding:12px 14px;display:flex;align-items:center;gap:10px;
}
body.dark .skill-item{background:var(--bg2)}
.skill-icon{width:36px;height:36px;border-radius:50%;display:flex;align-items:center;justify-content:center;flex-shrink:0}
.skill-name{font-size:.8rem;font-weight:800;color:var(--text)}
.skill-count{font-size:.7rem;color:var(--muted);font-weight:700}

/* Retour btn */
.btn-retour{
  display:inline-flex;align-items:center;gap:8px;padding:10px 18px;
  background:var(--bg);border:2px solid var(--border);border-radius:var(--radius-sm);
  color:var(--purple);font-size:.88rem;font-weight:800;text-decoration:none;
  transition:.15s;margin-bottom:24px;text-transform:uppercase;letter-spacing:.3px;
}
.btn-retour:hover{background:#ce82ff15;border-color:var(--purple);text-decoration:none}
</style>

<!-- RETOUR -->
<a href="index-prof.php" class="btn-retour">
  <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m12 19-7-7 7-7"/><path d="M19 12H5"/></svg>
  Retour Dashboard
</a>

<div class="prof-layout">

<!-- ============================
     COLONNE GAUCHE
     ============================ -->
<aside>
  <div class="prof-card">

    <!-- Avatar + upload -->
    <form class="upload-form" method="post" enctype="multipart/form-data" action="profile-prof.php">
      <div class="avatar-wrap">
        <?php if ($hasAvatar): ?>
          <img class="avatar-img" src="<?= htmlspecialchars($avatarPath).'?v='.time() ?>" alt="Avatar"/>
        <?php else: ?>
          <div class="avatar-initiales"><?= $initiales ?></div>
        <?php endif; ?>
        <label class="avatar-edit-btn" title="Changer la photo">
          <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2"><path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/><circle cx="12" cy="13" r="4"/></svg>
          <input type="file" name="avatar" accept="image/*" onchange="this.form.submit()" style="width:100%;height:100%;border-radius:50%">
        </label>
      </div>
      <?php if ($uploadMsg): 
        [$type,$msg] = explode(':', $uploadMsg, 2);
      ?>
        <div class="upload-msg-<?= $type ?>">
          <?php if($type==='ok'): ?>
            <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 6 9 17l-5-5"/></svg>
          <?php else: ?>
            <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>
          <?php endif; ?>
          <?= htmlspecialchars($msg) ?>
        </div>
      <?php endif; ?>
    </form>

    <div class="prof-name"><?= htmlspecialchars($u['prenom'].' '.$u['nom']) ?></div>
    <div class="prof-email"><?= htmlspecialchars($u['user']) ?></div>
    <div class="prof-role-badge">
      <?= $u['role'] === 'admin' ? 'Administrateur' : 'Enseignant' ?>
    </div>

    <!-- Stats rapides -->
    <div class="prof-stats">
      <div class="prof-stat-card">
        <div class="prof-stat-val" style="color:var(--blue)"><?= $nbEtudiants ?></div>
        <div class="prof-stat-lbl">Étudiants</div>
      </div>
      <div class="prof-stat-card">
        <div class="prof-stat-val" style="color:var(--green)"><?= $nbMissions ?></div>
        <div class="prof-stat-lbl">Missions</div>
      </div>
      <div class="prof-stat-card">
        <div class="prof-stat-val" style="color:var(--orange)"><?= $nbValidations ?></div>
        <div class="prof-stat-lbl">Validations</div>
      </div>
      <div class="prof-stat-card">
        <div class="prof-stat-val" style="color:var(--red)"><?= $nbBugsTraites ?></div>
        <div class="prof-stat-lbl">Bugs traités</div>
      </div>
    </div>

    <!-- Infos connexion -->
    <div style="font-size:.75rem;color:var(--muted);font-weight:700;margin-top:8px;padding-top:12px;border-top:2px dashed var(--border)">
      Membre depuis <?= date('M Y', strtotime($u['created_at'])) ?>
    </div>

  </div>
</aside>

<!-- ============================
     COLONNE DROITE
     ============================ -->
<main>

  <!-- INFOS PERSONNELLES -->
  <div class="info-block">
    <h3>
      <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
      Informations
    </h3>
    <div class="info-field">
      <span class="info-label">Prénom</span>
      <span class="info-value"><?= htmlspecialchars($u['prenom'] ?: '—') ?></span>
    </div>
    <div class="info-field">
      <span class="info-label">Nom</span>
      <span class="info-value"><?= htmlspecialchars($u['nom'] ?: '—') ?></span>
    </div>
    <div class="info-field">
      <span class="info-label">E-mail</span>
      <span class="info-value"><?= htmlspecialchars($u['email'] ?: $u['user']) ?></span>
    </div>
    <div class="info-field">
      <span class="info-label">Rôle</span>
      <span class="info-value" style="color:var(--purple);font-weight:900"><?= ucfirst($u['role']) ?></span>
    </div>
    <div class="info-field">
      <span class="info-label">Dernière connexion</span>
      <span class="info-value">
        <?= $u['last_login'] ? date('d/m/Y à H:i', strtotime($u['last_login'])) : 'Première connexion' ?>
      </span>
    </div>
  </div>

  <!-- CATÉGORIES ENSEIGNÉES -->
  <div class="info-block">
    <h3>
      <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"/><path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"/></svg>
      Catégories de missions
    </h3>
    <div class="skills-grid">
      <div class="skill-item">
        <div class="skill-icon" style="background:#1cb0f622;border:2px solid var(--blue)">
          <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="var(--blue)" stroke-width="2"><rect x="16" y="16" width="6" height="6" rx="1"/><rect x="2" y="16" width="6" height="6" rx="1"/><rect x="9" y="2" width="6" height="6" rx="1"/><path d="M5 16v-3a1 1 0 0 1 1-1h12a1 1 0 0 1 1 1v3"/><path d="M12 12V8"/></svg>
        </div>
        <div>
          <div class="skill-name">Réseau</div>
          <div class="skill-count"><?= $missionsCat['reseau'] ?? 0 ?> missions</div>
        </div>
      </div>
      <div class="skill-item">
        <div class="skill-icon" style="background:#ff4b4b22;border:2px solid var(--red)">
          <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="var(--red)" stroke-width="2"><path d="M20 13c0 5-3.5 7.5-7.66 8.95a1 1 0 0 1-.67-.01C7.5 20.5 4 18 4 13V6a1 1 0 0 1 1-1c2 0 4.5-1.2 6.24-2.72a1.17 1.17 0 0 1 1.52 0C14.51 3.81 17 5 19 5a1 1 0 0 1 1 1z"/></svg>
        </div>
        <div>
          <div class="skill-name">Sécurité</div>
          <div class="skill-count"><?= $missionsCat['secu'] ?? 0 ?> missions</div>
        </div>
      </div>
      <div class="skill-item">
        <div class="skill-icon" style="background:#58cc0222;border:2px solid var(--green)">
          <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="var(--green)" stroke-width="2"><polyline points="16 18 22 12 16 6"/><polyline points="8 6 2 12 8 18"/></svg>
        </div>
        <div>
          <div class="skill-name">Développement</div>
          <div class="skill-count"><?= $missionsCat['dev'] ?? 0 ?> missions</div>
        </div>
      </div>
    </div>
  </div>

  <!-- ACTIVITÉ RÉCENTE -->
  <div class="info-block">
    <h3>
      <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 12h-4l-3 9L9 3l-3 9H2"/></svg>
      Activité récente
    </h3>
    <ul class="timeline">
      <li class="tl-item">
        <div class="tl-dot green">
          <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="var(--green)" stroke-width="2"><path d="M20 6 9 17l-5-5"/></svg>
        </div>
        <div class="tl-body">
          <div class="tl-title">Compte créé</div>
          <div class="tl-sub"><?= date('d/m/Y', strtotime($u['created_at'])) ?></div>
        </div>
      </li>
      <li class="tl-item">
        <div class="tl-dot blue">
          <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="var(--blue)" stroke-width="2"><path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/><polyline points="10 17 15 12 10 7"/><line x1="15" y1="12" x2="3" y2="12"/></svg>
        </div>
        <div class="tl-body">
          <div class="tl-title">Dernière connexion</div>
          <div class="tl-sub">
            <?= $u['last_login'] ? date('d/m/Y à H:i', strtotime($u['last_login'])) : '—' ?>
          </div>
        </div>
      </li>
      <li class="tl-item">
        <div class="tl-dot purple">
          <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="var(--purple)" stroke-width="2"><circle cx="12" cy="12" r="10"/><circle cx="12" cy="12" r="6"/><circle cx="12" cy="12" r="2"/></svg>
        </div>
        <div class="tl-body">
          <div class="tl-title">Missions au catalogue</div>
          <div class="tl-sub"><?= $nbMissions ?> mission<?= $nbMissions>1?'s':'' ?> actives</div>
        </div>
      </li>
      <li class="tl-item">
        <div class="tl-dot orange">
          <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="var(--orange)" stroke-width="2"><path d="M20 6 9 17l-5-5"/></svg>
        </div>
        <div class="tl-body">
          <div class="tl-title">Preuves validées par moi</div>
          <div class="tl-sub"><?= $nbValidations ?> validation<?= $nbValidations>1?'s':'' ?></div>
        </div>
      </li>
      <?php if (!empty($dernieresValidations)): ?>
      <?php foreach (array_slice($dernieresValidations, 0, 3) as $v):
        $catLbl = ['reseau'=>'Réseau','secu'=>'Sécu','dev'=>'Dev'];
        $catCol = ['reseau'=>'var(--blue)','secu'=>'var(--red)','dev'=>'var(--green)'];
        $col = $catCol[$v['categorie']] ?? 'var(--muted)';
      ?>
      <li class="tl-item">
        <div class="tl-dot green">
          <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="var(--green)" stroke-width="2"><path d="M20 6 9 17l-5-5"/></svg>
        </div>
        <div class="tl-body">
          <div class="tl-title" style="font-size:.82rem"><?= htmlspecialchars($v['titre']) ?></div>
          <div class="tl-sub">
            <span style="color:<?= $col ?>;font-weight:800"><?= $catLbl[$v['categorie']] ?? '' ?></span>
            · <?= htmlspecialchars($v['prenom'].' '.$v['nom']) ?>
            · <span style="color:var(--orange);font-weight:800">+<?= $v['xp'] ?> XP</span>
            · <?= $v['valide_le'] ? date('d/m/Y', strtotime($v['valide_le'])) : '' ?>
          </div>
        </div>
      </li>
      <?php endforeach; ?>
      <?php endif; ?>
    </ul>
  </div>

</main>
</div><!-- fin prof-layout -->

</div><!-- fin main-content -->
<script>
(function(){var t=localStorage.getItem('lu-theme')||'dark';document.body.classList.toggle('dark',t==='dark');})();
</script>
</body>
</html>
