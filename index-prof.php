<?php
$title = 'Dashboard Professeur';
include('login.php');
requireRole(['enseignant','admin']);

$stmt = $pdo->prepare('SELECT * FROM users WHERE id = :id LIMIT 1');
$stmt->execute([':id' => $_SESSION['user_id']]);
$user = $stmt->fetch();

$nbEtudiants = $pdo->query('SELECT COUNT(*) FROM users WHERE role="etudiant" AND actif=1')->fetchColumn();
$nbMissions  = 0;
$nbEnAttente = 0;
$nbBugs      = 0;
?>
<link rel="stylesheet" href="styles.css"/>

<h1>Bonjour, <span style="color:var(--yellow)"><?= htmlspecialchars($user['prenom'] ?: $user['user']) ?></span> !</h1>

<!-- TABS -->
<div class="tabs">
  <a class="tab active" href="index-prof.php">
    <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m3 9 9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
    Dashboard
  </a>
  <a class="tab" href="missions-crud.php">
    <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><circle cx="12" cy="12" r="6"/><circle cx="12" cy="12" r="2"/></svg>
    Gérer les missions
  </a>
  <a class="tab" href="valider.php">
    <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 6 9 17l-5-5"/></svg>
    Valider les preuves
  </a>
  <a class="tab" href="leaderboard.php">
    <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 9H4.5a2.5 2.5 0 0 1 0-5H6"/><path d="M18 9h1.5a2.5 2.5 0 0 0 0-5H18"/><path d="M4 22h16"/><path d="M10 14.66V17c0 .55-.47.98-.97 1.21C7.85 18.75 7 20.24 7 22"/><path d="M14 14.66V17c0 .55.47.98.97 1.21C16.15 18.75 17 20.24 17 22"/><path d="M18 2H6v7a6 6 0 0 0 12 0V2z"/></svg>
    Classement
  </a>
  <a class="tab" href="profile-prof.php">
    <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
    Mon profil
  </a>
</div>

<!-- STATS -->
<div class="dashboard">
  <div class="card info">
    <div class="card-title">Étudiants inscrits</div>
    <div class="card-val" style="color:var(--blue)"><?= $nbEtudiants ?></div>
    <div class="card-sub">comptes actifs</div>
  </div>
  <div class="card warn">
    <div class="card-title">Preuves en attente</div>
    <div class="card-val" style="color:var(--yellow)"><?= $nbEnAttente ?></div>
    <div class="card-sub">à valider ou refuser</div>
  </div>
  <div class="card success">
    <div class="card-title">Missions actives</div>
    <div class="card-val" style="color:var(--green)"><?= $nbMissions ?></div>
    <div class="card-sub">dans le catalogue</div>
  </div>
  <div class="card danger">
    <div class="card-title">Bug Bounty signalés</div>
    <div class="card-val" style="color:var(--red)"><?= $nbBugs ?></div>
    <div class="card-sub">en attente de traitement</div>
  </div>
</div>

<!-- GESTION MISSIONS -->
<div class="section-title">
  <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><circle cx="12" cy="12" r="6"/><circle cx="12" cy="12" r="2"/></svg>
  Catalogue des missions
  <a class="btn-add" href="missions-crud.php?action=ajouter" style="margin-left:auto">
    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 12h14"/><path d="M12 5v14"/></svg>
    Ajouter
  </a>
</div>
<table class="mission-table">
  <thead>
    <tr>
      <th>Mission</th><th>Catégorie</th><th>XP</th><th>Statut</th><th>Actions</th>
    </tr>
  </thead>
  <tbody>
    <?php
    $missionsFake = [
      ['titre'=>'Configurer un VLAN',          'cat'=>'reseau','xp'=>50, 'actif'=>1],
      ['titre'=>'Mettre en place un pare-feu',  'cat'=>'secu',  'xp'=>80, 'actif'=>1],
      ['titre'=>'Créer une page PHP dynamique', 'cat'=>'dev',   'xp'=>60, 'actif'=>1],
      ['titre'=>'Analyser un trafic Wireshark', 'cat'=>'reseau','xp'=>70, 'actif'=>0],
    ];
    foreach ($missionsFake as $m):
      $catLabel = ['reseau'=>'Réseau','secu'=>'Sécurité','dev'=>'Dev'][$m['cat']];
    ?>
    <tr>
      <td style="font-weight:700"><?= htmlspecialchars($m['titre']) ?></td>
      <td><span class="cat-badge cat-<?= $m['cat'] ?>"><?= $catLabel ?></span></td>
      <td style="color:var(--orange);font-weight:800">
        <span style="display:flex;align-items:center;gap:4px">
          <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/></svg>
          <?= $m['xp'] ?> XP
        </span>
      </td>
      <td>
        <?php if ($m['actif']): ?>
          <span style="color:var(--green);font-weight:800;font-size:.8rem;display:flex;align-items:center;gap:4px">
            <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="m9 12 2 2 4-4"/></svg>Active
          </span>
        <?php else: ?>
          <span style="color:var(--muted);font-weight:700;font-size:.8rem">Archivée</span>
        <?php endif; ?>
      </td>
      <td style="display:flex;gap:6px;flex-wrap:wrap">
        <a class="btn-edit" href="missions-crud.php?action=editer">
          <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21.174 6.812a1 1 0 0 0-3.986-3.987L3.842 16.174a2 2 0 0 0-.5.83l-1.321 4.352a.5.5 0 0 0 .623.622l4.353-1.32a2 2 0 0 0 .83-.497z"/></svg>
          Éditer
        </a>
        <a class="btn-archive" href="missions-crud.php?action=archiver">
          <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect width="20" height="5" x="2" y="3" rx="1"/><path d="M4 8v11a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8"/><path d="M10 12h4"/></svg>
          <?= $m['actif'] ? 'Archiver' : 'Restaurer' ?>
        </a>
      </td>
    </tr>
    <?php endforeach; ?>
  </tbody>
</table>

<!-- PREUVES EN ATTENTE -->
<div class="section-title">
  <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect width="18" height="18" x="3" y="3" rx="2" ry="2"/><circle cx="9" cy="9" r="2"/><path d="m21 15-3.086-3.086a2 2 0 0 0-2.828 0L6 21"/></svg>
  Preuves à valider
</div>
<?php if ($nbEnAttente === 0): ?>
  <div class="card" style="text-align:center;color:var(--muted);padding:28px">
    <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin-bottom:8px;color:var(--green)"><path d="M20 6 9 17l-5-5"/></svg>
    <div style="font-weight:800">Aucune preuve en attente</div>
  </div>
<?php endif; ?>

<!-- LEADERBOARD -->
<div class="section-title" style="margin-top:28px">
  <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 9H4.5a2.5 2.5 0 0 1 0-5H6"/><path d="M18 9h1.5a2.5 2.5 0 0 0 0-5H18"/><path d="M4 22h16"/><path d="M10 14.66V17c0 .55-.47.98-.97 1.21C7.85 18.75 7 20.24 7 22"/><path d="M14 14.66V17c0 .55.47.98.97 1.21C16.15 18.75 17 20.24 17 22"/><path d="M18 2H6v7a6 6 0 0 0 12 0V2z"/></svg>
  Classement des étudiants
</div>
<div class="leaderboard">
<?php
$top = $pdo->query('SELECT user,prenom,nom,xp,niveau,streak FROM users WHERE actif=1 AND role="etudiant" ORDER BY xp DESC LIMIT 8')->fetchAll();
$medals = ['gold','silver','bronze'];
foreach ($top as $i => $u):
?>
  <div class="lb-row">
    <div class="lb-rank <?= $medals[$i] ?? '' ?>">
      <?= $i===0 ? '🥇' : ($i===1 ? '🥈' : ($i===2 ? '🥉' : ($i+1))) ?>
    </div>
    <div class="lb-name"><?= htmlspecialchars($u['prenom'] ? $u['prenom'].' '.$u['nom'] : $u['user']) ?></div>
    <div class="lb-streak" style="display:flex;align-items:center;gap:3px">
      <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M8.5 14.5A2.5 2.5 0 0 0 11 12c0-1.38-.5-2-1-3-1.072-2.143-.224-4.054 2-6 .5 2.5 2 4.9 4 6.5 2 1.6 3 3.5 3 5.5a7 7 0 1 1-14 0c0-1.153.433-2.294 1-3a2.5 2.5 0 0 0 2.5 2.5z"/></svg>
      <?= $u['streak'] ?>j
    </div>
    <div class="lb-niveau">Niv. <?= $u['niveau'] ?></div>
    <div class="lb-xp" style="display:flex;align-items:center;gap:3px">
      <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/></svg>
      <?= $u['xp'] ?> XP
    </div>
  </div>
<?php endforeach; ?>
<?php if (empty($top)): ?>
  <div class="lb-row"><span style="color:var(--muted);font-size:.9rem;font-weight:700">Aucun étudiant inscrit.</span></div>
<?php endif; ?>
</div>

<!-- BUG BOUNTY PROF -->
<div class="section-title">
  <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m8 2 1.88 1.88"/><path d="M14.12 3.88 16 2"/><rect width="8" height="14" x="8" y="8" rx="5"/><path d="M19 13h-2"/><path d="M19 18h-2"/><path d="M5 13H7"/><path d="M5 18H7"/><path d="M7 8 5.5 5.5"/><path d="M17 8l1.5-2.5"/></svg>
  Bug Bounty – Signalements reçus
</div>

<?php
// Lire les vrais bugs depuis la BDD
$bugs = [];
try {
    $stmtBugs = $pdo->query(
        'SELECT b.*, u.prenom, u.nom, u.user as email
         FROM bug_bounty b
         JOIN users u ON b.user_id = u.id
         WHERE b.statut = "ouvert"
         ORDER BY FIELD(b.severite,"critical","high","medium","low","info"), b.created_at DESC
         LIMIT 20'
    );
    $bugs = $stmtBugs->fetchAll();
} catch (Exception $e) { /* table pas encore créée */ }

$severiteStyle = [
    'info'     => ['#afafaf', 'Info',     '+10 XP'],
    'low'      => ['#1cb0f6', 'Low',      '+25 XP'],
    'medium'   => ['#ff9600', 'Medium',   '+50 XP'],
    'high'     => ['#ff4b4b', 'High',     '+100 XP'],
    'critical' => ['#ce82ff', 'Critical', '+200 XP'],
];
$xpBySeverite = ['info'=>10,'low'=>25,'medium'=>50,'high'=>100,'critical'=>200];
?>

<?php if (empty($bugs)): ?>
  <div class="card" style="text-align:center;color:var(--muted);padding:28px">
    <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin-bottom:8px;color:var(--green)"><path d="M20 6 9 17l-5-5"/></svg>
    <div style="font-weight:800">Aucun bug en attente de traitement</div>
    <div style="font-size:.82rem;margin-top:4px">Les étudiants peuvent signaler des bugs via leur dashboard</div>
  </div>
<?php else: ?>
<div class="bug-list">
  <?php foreach ($bugs as $bug):
    $sev = $bug['severite'] ?? 'low';
    [$sevCol, $sevLbl, $sevXP] = $severiteStyle[$sev];
    $xpDefaut = $xpBySeverite[$sev];
  ?>
  <div class="bug-card" style="border-left-color:<?= $sevCol ?>">
    <div class="bug-body" style="flex:1">
      <!-- Header -->
      <div style="display:flex;align-items:center;gap:8px;margin-bottom:8px;flex-wrap:wrap">
        <!-- Badge sévérité -->
        <span style="font-size:.65rem;font-weight:900;text-transform:uppercase;letter-spacing:.8px;
                     padding:3px 10px;border-radius:20px;background:<?= $sevCol ?>22;
                     color:<?= $sevCol ?>;border:1px solid <?= $sevCol ?>66">
          <?= $sevLbl ?>
        </span>
        <span class="bug-cat"><?= htmlspecialchars($bug['categorie']) ?></span>
        <span style="font-size:.72rem;color:var(--muted);font-weight:600;margin-left:auto">
          <?= date('d/m/Y H:i', strtotime($bug['created_at'])) ?>
        </span>
      </div>
      <!-- Titre -->
      <div style="font-weight:800;color:var(--text);margin-bottom:4px">
        <?= htmlspecialchars($bug['titre'] ?? 'Sans titre') ?>
      </div>
      <!-- Auteur -->
      <div class="bug-user">
        <?= htmlspecialchars($bug['prenom'].' '.$bug['nom']) ?> · <?= htmlspecialchars($bug['email']) ?>
      </div>
      <!-- Description -->
      <div class="bug-desc" style="margin-top:6px;max-height:60px;overflow:hidden;font-size:.82rem">
        <?= nl2br(htmlspecialchars($bug['description'])) ?>
      </div>
    </div>

    <!-- Actions prof -->
    <div style="display:flex;flex-direction:column;gap:6px;min-width:160px">
      <!-- Valider avec choix XP -->
      <form method="post" action="bug-bounty-valider.php">
        <input type="hidden" name="bug_id" value="<?= $bug['id'] ?>">
        <select name="xp_attribue" style="width:100%;margin-bottom:6px;padding:6px 8px;
                background:var(--bg);border:2px solid var(--border);border-radius:var(--radius-sm);
                color:var(--text);font-weight:700;font-size:.82rem;font-family:var(--font)">
          <option value="10" <?= $xpDefaut===10?'selected':'' ?>>Info → +10 XP</option>
          <option value="25" <?= $xpDefaut===25?'selected':'' ?>>Low → +25 XP</option>
          <option value="50" <?= $xpDefaut===50?'selected':'' ?>>Medium → +50 XP</option>
          <option value="100" <?= $xpDefaut===100?'selected':'' ?>>High → +100 XP</option>
          <option value="200" <?= $xpDefaut===200?'selected':'' ?>>Critical → +200 XP</option>
        </select>
        <button class="btn-bug-valider" type="submit" style="width:100%">
          Valider &amp; Attribuer XP
        </button>
      </form>
      <form method="post" action="bug-bounty-valider.php">
        <input type="hidden" name="bug_id" value="<?= $bug['id'] ?>">
        <input type="hidden" name="action" value="invalider">
        <button class="btn-bug-ignorer" type="submit" style="width:100%">
          Invalider
        </button>
      </form>
    </div>
  </div>
  <?php endforeach; ?>
</div>
<?php endif; ?>

</div><!-- fin main-content -->
<script>
(function(){var t=localStorage.getItem('lu-theme')||'dark';document.body.classList.toggle('dark',t==='dark');})();
</script>
</body></html>
