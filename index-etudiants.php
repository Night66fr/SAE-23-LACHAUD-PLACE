<?php
$title = 'Tableau de bord';
include('login.php');
requireRole('etudiant');

$stmt = $pdo->prepare('SELECT * FROM users WHERE id = :id LIMIT 1');
$stmt->execute([':id' => $_SESSION['user_id']]);
$user = $stmt->fetch();

function xpPourNiveau($n) { return $n * 100; }
$xpActuel    = (int)$user['xp'];
$niveauActuel= (int)$user['niveau'];
$xpProcNiveau= xpPourNiveau($niveauActuel);
$xpPct       = $xpProcNiveau > 0 ? min(100, round(($xpActuel % $xpProcNiveau) / $xpProcNiveau * 100)) : 0;
$streak      = (int)$user['streak'];

// ---- Stats missions réelles (à remplacer par vraie table missions plus tard) ----
// Pour l'instant on compte les missions fake validées
// Vraies stats depuis la BDD
$nbMissionsValidees = 0; $nbMissionsAttente = 0; $nbBadges = 0;
try {
    $sm = $pdo->prepare('SELECT statut, COUNT(*) as nb FROM soumissions WHERE user_id=:uid GROUP BY statut');
    $sm->execute([':uid' => $_SESSION['user_id']]);
    foreach ($sm->fetchAll() as $row) {
        if ($row['statut'] === 'valide')     $nbMissionsValidees = (int)$row['nb'];
        if ($row['statut'] === 'en_attente') $nbMissionsAttente  = (int)$row['nb'];
    }
} catch (Exception $e) {}

// ---- Multiplicateur XP selon le streak ----
// Duolingo : streak boost les gains d'XP
if ($streak >= 35)      $multiplicateur = 3;
elseif ($streak >= 28)  $multiplicateur = 2.5;
elseif ($streak >= 21)  $multiplicateur = 2;
elseif ($streak >= 14)  $multiplicateur = 1.75;
elseif ($streak >= 7)   $multiplicateur = 1.5;
else                    $multiplicateur = 1;
?>
<link rel="stylesheet" href="styles.css"/>

<h1>Bonjour, <span style="color:var(--green)"><?= htmlspecialchars($user['prenom'] ?: $user['user']) ?></span> !</h1>

<!-- TABS -->
<div class="tabs">
  <a class="tab active" href="index-etudiants.php">
    <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m3 9 9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
    Dashboard
  </a>
  <a class="tab" href="missions.php">
    <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><circle cx="12" cy="12" r="6"/><circle cx="12" cy="12" r="2"/></svg>
    Missions
  </a>
  <a class="tab" href="leaderboard.php">
    <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 9H4.5a2.5 2.5 0 0 1 0-5H6"/><path d="M18 9h1.5a2.5 2.5 0 0 0 0-5H18"/><path d="M4 22h16"/><path d="M10 14.66V17c0 .55-.47.98-.97 1.21C7.85 18.75 7 20.24 7 22"/><path d="M14 14.66V17c0 .55.47.98.97 1.21C16.15 18.75 17 20.24 17 22"/><path d="M18 2H6v7a6 6 0 0 0 12 0V2z"/></svg>
    Classement
  </a>
  <a class="tab" href="profile.php">
    <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
    Mon profil
  </a>
</div>

<!-- STATS CARDS -->
<div class="dashboard">

  <!-- Niveau -->
  <div class="card">
    <div class="card-title">Niveau actuel</div>
    <div style="display:flex;align-items:center;gap:14px">
      <div class="niveau-badge"><?= $niveauActuel ?></div>
      <div>
        <div class="card-val" style="font-size:1.5rem"><?= $niveauActuel ?><span style="font-size:.9rem;color:var(--muted);font-weight:700"> / 20</span></div>
        <div class="card-sub"><?= $xpActuel ?> XP total</div>
      </div>
    </div>
    <div class="xp-bar-wrap" style="margin-top:12px">
      <div class="xp-bar-bg"><div class="xp-bar-fill" style="width:<?= $xpPct ?>%"></div></div>
      <div class="xp-labels"><span><?= $xpActuel % $xpProcNiveau ?> XP</span><span><?= $xpProcNiveau ?> XP</span></div>
    </div>
  </div>

  <!-- Streak -->
  <div class="card streak-card">
    <div class="card-title">Streak de présence</div>
    <div class="card-val streak-val" style="display:flex;align-items:center;gap:8px;font-size:2rem">
      <svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="var(--red)" stroke-width="2"><path d="M8.5 14.5A2.5 2.5 0 0 0 11 12c0-1.38-.5-2-1-3-1.072-2.143-.224-4.054 2-6 .5 2.5 2 4.9 4 6.5 2 1.6 3 3.5 3 5.5a7 7 0 1 1-14 0c0-1.153.433-2.294 1-3a2.5 2.5 0 0 0 2.5 2.5z"/></svg>
      <?= $streak ?> jour<?= $streak > 1 ? 's' : '' ?>
    </div>
    <div class="card-sub" style="margin-top:6px">
      <?php if ($streak >= 35): ?>Légendaire ! Tu es inarrêtable !
      <?php elseif ($streak >= 28): ?>Quatre semaines non-stop, respect !
      <?php elseif ($streak >= 21): ?>Trois semaines d'affilée, impressionnant !
      <?php elseif ($streak >= 14): ?>Deux semaines, tu es en feu !
      <?php elseif ($streak >= 7): ?>Une semaine, super départ !
      <?php elseif ($streak >= 1): ?>Continue comme ça !
      <?php else: ?>Reviens demain pour lancer ta série !
      <?php endif; ?>
    </div>
    <?php if ($multiplicateur > 1): ?>
    <div style="margin-top:10px;background:rgba(255,150,0,.12);border:2px solid var(--orange);
                border-radius:var(--radius-sm);padding:8px 12px;">
      <div style="font-size:.72rem;color:var(--orange);font-weight:900;text-transform:uppercase;letter-spacing:.5px;margin-bottom:2px">
        Bonus streak actif
      </div>
      <div style="font-size:1.1rem;font-weight:900;color:var(--orange)">
        x<?= $multiplicateur ?> sur les gains d'XP
      </div>

    </div>
    <?php else: ?>
    <div style="margin-top:8px;font-size:.75rem;color:var(--muted);font-weight:700">
      7 jours de suite = bonus x1.5 !
    </div>
    <?php endif; ?>
  </div>

  <!-- Missions -->
  <div class="card">
    <div class="card-title">Missions accomplies</div>
    <div class="card-val"><span style="color:var(--green)"><?= $nbMissionsValidees ?></span></div>
    <div class="card-sub">
      <?php if ($nbMissionsAttente > 0): ?>
        <span style="color:var(--orange);font-weight:800"><?= $nbMissionsAttente ?> en attente</span>
      <?php else: ?>
        Aucune en attente de validation
      <?php endif; ?>
    </div>
  </div>

  <!-- Badges -->
  <div class="card">
    <div class="card-title">Badges obtenus</div>
    <div class="card-val"><span style="color:var(--purple)"><?= $nbBadges ?></span></div>
    <div class="card-sub" style="margin-top:8px">
      <?php if ($nbBadges === 0): ?>
        Complète des missions pour débloquer des badges !
      <?php else: ?>
        Voir mon profil pour les détails
      <?php endif; ?>
    </div>
  </div>

</div>

<!-- MISSIONS DISPONIBLES -->
<div class="section-title">
  <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><circle cx="12" cy="12" r="6"/><circle cx="12" cy="12" r="2"/></svg>
  Missions disponibles
</div>
<div class="missions-grid">
  <?php
  // 8 missions les plus simples NON encore validées ni en attente
  $missionsIndex = [];
  try {
      $stMiss = $pdo->prepare(
          'SELECT id, titre, description, categorie, xp FROM missions
           WHERE actif=1
           AND id NOT IN (
               SELECT mission_id FROM soumissions
               WHERE user_id=:uid AND statut IN ("valide","en_attente")
           )
           ORDER BY FIELD(difficulte,"facile","moyen","difficile"), xp ASC
           LIMIT 8'
      );
      $stMiss->execute([':uid' => $_SESSION['user_id']]);
      $missionsIndex = $stMiss->fetchAll();
  } catch (Exception $e) {}

  $catLabel = ['reseau'=>'Réseau','secu'=>'Sécurité','securite'=>'Sécurité','dev'=>'Dev'];
  foreach ($missionsIndex as $m):
      $cat_m = $m['categorie'];
  ?>
  <div class="mission-card">
    <span class="mission-cat cat-<?= $cat_m ?>"><?= $catLabel[$cat_m] ?? $cat_m ?></span>
    <div class="mission-title"><?= htmlspecialchars($m['titre']) ?></div>
    <div style="font-size:.78rem;color:var(--muted);font-weight:600;line-height:1.4"><?= htmlspecialchars(mb_substr($m['description'],0,100)).'...' ?></div>
    <div class="mission-xp" style="display:flex;align-items:center;gap:4px">
      <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/></svg>
      +<?= $m['xp'] ?> XP
    </div>
    <a class="btn-soumettre" href="soumettre.php?mission=<?= $m['id'] ?>">Soumettre une preuve</a>
  </div>
  <?php endforeach; ?>
  <?php if (empty($missionsIndex)): ?>
    <div style="grid-column:1/-1;text-align:center;padding:32px;color:var(--muted);font-weight:700">
      Aucune mission disponible. <a href="missions.php" style="color:var(--blue)">Voir le catalogue</a>
    </div>
  <?php endif; ?>
</div>

<!-- LEADERBOARD -->
<div class="section-title">
  <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 9H4.5a2.5 2.5 0 0 1 0-5H6"/><path d="M18 9h1.5a2.5 2.5 0 0 0 0-5H18"/><path d="M4 22h16"/><path d="M10 14.66V17c0 .55-.47.98-.97 1.21C7.85 18.75 7 20.24 7 22"/><path d="M14 14.66V17c0 .55.47.98.97 1.21C16.15 18.75 17 20.24 17 22"/><path d="M18 2H6v7a6 6 0 0 0 12 0V2z"/></svg>
  Classement global
</div>
<div class="leaderboard">
<?php
$top = $pdo->query('SELECT id,user,prenom,nom,xp,niveau FROM users WHERE actif=1 AND role="etudiant" ORDER BY xp DESC LIMIT 5')->fetchAll();
$medals = ['gold','silver','bronze'];
foreach ($top as $i => $u):
  $isMe = ((int)$u['id'] === (int)$_SESSION['user_id']);
?>
  <div class="lb-row <?= $isMe ? 'me' : '' ?>">
    <div class="lb-rank <?= $medals[$i] ?? '' ?>">
      <?= $i===0 ? '🥇' : ($i===1 ? '🥈' : ($i===2 ? '🥉' : ($i+1))) ?>
    </div>
    <div class="lb-name">
      <?= htmlspecialchars($u['prenom'] ? $u['prenom'].' '.$u['nom'] : $u['user']) ?>
      <?= $isMe ? ' <span style="color:var(--green);font-size:.75rem;font-weight:800">(vous)</span>' : '' ?>
    </div>
    <div class="lb-niveau">Niv. <?= $u['niveau'] ?></div>
    <div class="lb-xp" style="display:flex;align-items:center;gap:4px">
      <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/></svg>
      <?= $u['xp'] ?> XP
    </div>
  </div>
<?php endforeach; ?>
<?php if (empty($top)): ?>
  <div class="lb-row"><span style="color:var(--muted);font-size:.9rem;font-weight:700">Aucun étudiant inscrit.</span></div>
<?php endif; ?>
</div>

<!-- BUG BOUNTY -->
<div class="section-title">
  <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m8 2 1.88 1.88"/><path d="M14.12 3.88 16 2"/><rect width="8" height="14" x="8" y="8" rx="5"/><path d="M19 13h-2"/><path d="M19 18h-2"/><path d="M5 13H7"/><path d="M5 18H7"/><path d="M7 8 5.5 5.5"/><path d="M17 8l1.5-2.5"/></svg>
  Bug Bounty
  <span style="font-size:.75rem;color:var(--green);font-weight:700;text-transform:none;letter-spacing:0">jusqu'à +200 XP selon la sévérité</span>
</div>

<!-- Échelle de sévérité style HackerOne -->
<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(150px,1fr));gap:10px;margin-bottom:20px">
  <div style="background:var(--card);border:2px solid #afafaf;border-radius:var(--radius-sm);padding:12px 14px;text-align:center">
    <div style="font-size:.65rem;font-weight:900;text-transform:uppercase;letter-spacing:.8px;color:#afafaf;margin-bottom:4px">Info</div>
    <div style="font-size:1.2rem;font-weight:900;color:#afafaf">+10 XP</div>
    <div style="font-size:.68rem;color:var(--muted);margin-top:3px;font-weight:600">Cosmétique, typo</div>
  </div>
  <div style="background:var(--card);border:2px solid var(--blue);border-radius:var(--radius-sm);padding:12px 14px;text-align:center">
    <div style="font-size:.65rem;font-weight:900;text-transform:uppercase;letter-spacing:.8px;color:var(--blue);margin-bottom:4px">Low</div>
    <div style="font-size:1.2rem;font-weight:900;color:var(--blue)">+25 XP</div>
    <div style="font-size:.68rem;color:var(--muted);margin-top:3px;font-weight:600">Bug mineur</div>
  </div>
  <div style="background:var(--card);border:2px solid var(--orange);border-radius:var(--radius-sm);padding:12px 14px;text-align:center">
    <div style="font-size:.65rem;font-weight:900;text-transform:uppercase;letter-spacing:.8px;color:var(--orange);margin-bottom:4px">Medium</div>
    <div style="font-size:1.2rem;font-weight:900;color:var(--orange)">+50 XP</div>
    <div style="font-size:.68rem;color:var(--muted);margin-top:3px;font-weight:600">Fonctionnalité cassée</div>
  </div>
  <div style="background:var(--card);border:2px solid var(--red);border-radius:var(--radius-sm);padding:12px 14px;text-align:center">
    <div style="font-size:.65rem;font-weight:900;text-transform:uppercase;letter-spacing:.8px;color:var(--red);margin-bottom:4px">High</div>
    <div style="font-size:1.2rem;font-weight:900;color:var(--red)">+100 XP</div>
    <div style="font-size:.68rem;color:var(--muted);margin-top:3px;font-weight:600">Faille sécurité</div>
  </div>
  <div style="background:var(--card);border:2px solid var(--purple);border-radius:var(--radius-sm);padding:12px 14px;text-align:center;position:relative;overflow:hidden">
    <div style="position:absolute;top:0;left:0;right:0;height:2px;background:linear-gradient(90deg,var(--purple),var(--blue))"></div>
    <div style="font-size:.65rem;font-weight:900;text-transform:uppercase;letter-spacing:.8px;color:var(--purple);margin-bottom:4px">Critical</div>
    <div style="font-size:1.2rem;font-weight:900;color:var(--purple)">+200 XP</div>
    <div style="font-size:.68rem;color:var(--muted);margin-top:3px;font-weight:600">Faille critique !</div>
  </div>
</div>

<div class="bug-form">
  <h3>Signaler un problème</h3>
  <form method="post" action="bug-bounty-submit.php">
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
      <div class="form-group">
        <label for="bug-titre">Titre du bug</label>
        <input type="text" id="bug-titre" name="titre" placeholder="Ex: Bouton login ne fonctionne pas" required/>
      </div>
      <div class="form-group">
        <label for="bug-cat">Catégorie</label>
        <select id="bug-cat" name="categorie" required>
          <option value="" disabled selected>Choisir...</option>
          <option value="affichage">Affichage / UI</option>
          <option value="fonctionnel">Fonctionnalité cassée</option>
          <option value="performance">Performance</option>
          <option value="securite">Sécurité</option>
          <option value="autre">Autre</option>
        </select>
      </div>
    </div>

    <!-- Sévérité style HackerOne -->
    <div class="form-group">
      <label>Sévérité estimée <span style="color:var(--muted);font-weight:600;font-size:.75rem">(le prof valide la sévérité finale)</span></label>
      <div style="display:flex;gap:8px;flex-wrap:wrap">
        <?php
        $severites = [
          'info'     => ['#afafaf','Info',    '+10 XP'],
          'low'      => ['#1cb0f6','Low',     '+25 XP'],
          'medium'   => ['#ff9600','Medium',  '+50 XP'],
          'high'     => ['#ff4b4b','High',    '+100 XP'],
          'critical' => ['#ce82ff','Critical','+200 XP'],
        ];
        foreach ($severites as $val => [$col, $lbl, $xp]):
        ?>
        <label style="flex:1;min-width:80px;cursor:pointer">
          <input type="radio" name="severite" value="<?= $val ?>" style="display:none" class="sev-radio">
          <div class="sev-btn" data-color="<?= $col ?>"
               style="border:2px solid var(--border);border-radius:var(--radius-sm);
                      padding:8px 6px;text-align:center;transition:.15s;background:var(--bg)">
            <div style="font-size:.65rem;font-weight:900;text-transform:uppercase;letter-spacing:.5px;color:<?= $col ?>"><?= $lbl ?></div>
            <div style="font-size:.85rem;font-weight:900;color:<?= $col ?>;margin-top:2px"><?= $xp ?></div>
          </div>
        </label>
        <?php endforeach; ?>
      </div>
    </div>

    <div class="form-group">
      <label for="bug-desc">Description détaillée</label>
      <textarea id="bug-desc" name="description" rows="4"
                placeholder="Étapes pour reproduire :&#10;1. Aller sur...&#10;2. Cliquer sur...&#10;3. Observer que..." required></textarea>
    </div>

    <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:10px">
      <button class="btn-bug" type="submit">Signaler ce bug</button>
      <span style="font-size:.78rem;color:var(--muted);font-weight:700">
        Le prof évalue et attribue les XP selon la vraie sévérité.
      </span>
    </div>
  </form>
</div>

<script>
// Sélection sévérité visuelle
document.querySelectorAll('.sev-radio').forEach(function(radio) {
  radio.addEventListener('change', function() {
    document.querySelectorAll('.sev-btn').forEach(function(btn) {
      btn.style.background = 'var(--bg)';
      btn.style.borderColor = 'var(--border)';
    });
    var btn = radio.nextElementSibling;
    var col = btn.dataset.color;
    btn.style.background = col + '22';
    btn.style.borderColor = col;
    btn.style.boxShadow = '0 3px 0 ' + col + '88';
  });
});
</script>

</div><!-- fin main-content -->
<script>
(function(){var t=localStorage.getItem('lu-theme')||'dark';document.body.classList.toggle('dark',t==='dark');})();
<style>
.mission-done{
  opacity:.75;
  border-color:var(--green) !important;
  position:relative;
}
.mission-done::after{
  content:'';
  position:absolute;inset:0;
  border-radius:var(--radius);
  pointer-events:none;
}
</style>
</script>
</body></html>
