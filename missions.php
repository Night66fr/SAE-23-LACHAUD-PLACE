<?php
$title = 'Catalogue des missions';
include('login.php');

// ---- Filtres ----
$cat  = $_GET['cat']  ?? 'toutes';
$ue   = $_GET['ue']   ?? 'toutes';
$diff = $_GET['diff']  ?? 'toutes';
$q    = trim($_GET['q'] ?? '');

// ---- Requête missions depuis BDD ----
$missions = [];
try {
    $where  = ['m.actif = 1'];
    $params = [];
    if ($cat  !== 'toutes') { $where[] = 'm.categorie = :cat';    $params[':cat']  = $cat; }
    if ($ue   !== 'toutes') { $where[] = 'm.ue = :ue';            $params[':ue']   = $ue;  }
    if ($diff !== 'toutes') { $where[] = 'm.difficulte = :diff';  $params[':diff'] = $diff; }
    if ($q)                 { $where[] = '(m.titre LIKE :q OR m.description LIKE :q)'; $params[':q'] = '%'.$q.'%'; }

    $sql = 'SELECT m.* FROM missions m WHERE '.implode(' AND ', $where).' ORDER BY m.categorie, m.difficulte DESC, m.xp DESC';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $missions = $stmt->fetchAll();
} catch (Exception $e) {
    // Table pas encore créée → missions fake
    $missions = [];
}

// ---- Soumissions de l'utilisateur connecté ----
$mesSubmissions = [];
try {
    $stmtSoum = $pdo->prepare('SELECT mission_id, statut FROM soumissions WHERE user_id = :uid');
    $stmtSoum->execute([':uid' => $_SESSION['user_id']]);
    foreach ($stmtSoum->fetchAll() as $row) {
        $mesSubmissions[$row['mission_id']] = $row['statut'];
    }
} catch (Exception $e) {}

// Onglet actif
$onglet = $_GET['onglet'] ?? 'disponibles';

// ---- Stats GLOBALES depuis BDD (sans filtre = toujours les vrais totaux) ----
$stats = ['reseau'=>0,'secu'=>0,'dev'=>0,'autre'=>0];
$totalMissions = 0;
try {
    $stmtAllStats = $pdo->query('SELECT categorie, COUNT(*) as nb FROM missions WHERE actif=1 GROUP BY categorie');
    foreach ($stmtAllStats->fetchAll() as $row) {
        $stats[$row['categorie']] = (int)$row['nb'];
    }
    $totalMissions = (int)$pdo->query('SELECT COUNT(*) FROM missions WHERE actif=1')->fetchColumn();
} catch (Exception $e) {
    $totalMissions = count($missions);
}

// ---- UEs disponibles pour filtre ----
$ues = [];
try {
    $ues = $pdo->query('SELECT DISTINCT ue FROM missions WHERE actif=1 ORDER BY ue')->fetchAll(PDO::FETCH_COLUMN);
} catch (Exception $e) {}

$role = $_SESSION['role'] ?? 'etudiant';
?>
<link rel="stylesheet" href="styles.css"/>
<style>
/* ---- PAGE MISSIONS ---- */
.missions-header{
  display:flex;align-items:center;justify-content:space-between;
  flex-wrap:wrap;gap:16px;margin-bottom:24px;
}
.missions-title{font-size:1.4rem;font-weight:900;color:var(--text)}

/* Stats rapides */
.cat-stats{display:flex;gap:10px;flex-wrap:wrap;margin-bottom:20px}
.cat-stat{
  display:flex;align-items:center;gap:8px;padding:8px 16px;
  border-radius:var(--radius);border:2px solid var(--border);
  background:var(--card);font-weight:800;font-size:.85rem;cursor:pointer;
  transition:.15s;text-decoration:none;color:var(--text);
}
.cat-stat:hover,.cat-stat.active{text-decoration:none}
.cat-stat.reseau:hover,.cat-stat.reseau.active{border-color:var(--blue);background:var(--blue-sh);color:var(--blue)}
.cat-stat.securite:hover,.cat-stat.securite.active{border-color:var(--red);background:var(--red-sh);color:var(--red)}
.cat-stat.dev:hover,.cat-stat.dev.active{border-color:var(--green);background:var(--green-sh);color:var(--green)}
.cat-stat.toutes:hover,.cat-stat.toutes.active{border-color:var(--purple);background:#ce82ff15;color:var(--purple)}
.cat-count{font-size:.75rem;background:var(--border);border-radius:20px;padding:1px 7px;font-weight:900}

/* Filtres */
.filters-bar{
  display:flex;gap:10px;flex-wrap:wrap;align-items:center;
  background:var(--card);border:2px solid var(--border);
  border-radius:var(--radius);padding:14px 18px;margin-bottom:22px;
}
.filters-bar select,.filters-bar input{
  padding:8px 12px;background:var(--bg);border:2px solid var(--border);
  border-radius:var(--radius-sm);color:var(--text);font-family:var(--font);
  font-size:.85rem;font-weight:700;outline:none;transition:.15s;
}
.filters-bar select:focus,.filters-bar input:focus{border-color:var(--blue)}
.filters-bar input{flex:1;min-width:180px}
body.dark .filters-bar select,body.dark .filters-bar input{background:var(--bg2)}

/* Grille missions */
.missions-full-grid{
  display:grid;
  grid-template-columns:repeat(auto-fill,minmax(300px,1fr));
  gap:16px;
}

/* Carte mission enrichie */
.mission-full-card{
  background:var(--card);border:2px solid var(--border);
  border-radius:var(--radius);padding:20px;
  display:flex;flex-direction:column;gap:10px;
  transition:.2s;position:relative;overflow:hidden;
}
.mission-full-card::before{
  content:'';position:absolute;top:0;left:0;right:0;height:3px;
}
.mission-full-card.cat-reseau::before{background:var(--blue)}
.mission-full-card.cat-secu::before{background:var(--red)}
.mission-full-card.cat-dev::before{background:var(--green)}
.mission-full-card:hover{transform:translateY(-3px);box-shadow:0 8px 24px rgba(0,0,0,.1)}

.mission-full-card.done{opacity:.7;border-color:var(--green)}
.mission-full-card.done::before{background:var(--green)}

.mission-card-header{display:flex;align-items:center;justify-content:space-between;gap:8px;flex-wrap:wrap}
.mission-full-title{font-size:.95rem;font-weight:900;color:var(--text);line-height:1.3}
.mission-full-desc{font-size:.82rem;color:var(--muted);font-weight:600;line-height:1.5;flex:1}
.mission-full-footer{display:flex;align-items:center;justify-content:space-between;gap:8px;margin-top:auto;flex-wrap:wrap}

/* Difficulté */
.diff-badge{
  font-size:.65rem;font-weight:900;text-transform:uppercase;letter-spacing:.8px;
  padding:3px 9px;border-radius:20px;
}
.diff-facile  {background:var(--green-sh);color:var(--green);border:1px solid var(--green)}
.diff-moyen   {background:#ff960018;color:var(--orange);border:1px solid var(--orange)}
.diff-difficile{background:var(--red-sh);color:var(--red);border:1px solid var(--red)}

/* UE tag */
.ue-tag{
  font-size:.65rem;font-weight:800;color:var(--muted);
  background:var(--bg);border:1px solid var(--border);
  border-radius:20px;padding:2px 8px;
}

/* XP badge */
.xp-badge{
  display:inline-flex;align-items:center;gap:4px;
  font-size:.82rem;font-weight:900;color:var(--orange);
}

/* Bouton soumettre */
.btn-submit-mission{
  padding:8px 16px;background:var(--blue);color:#fff;
  border:none;border-radius:var(--radius-sm);font-family:var(--font);
  font-size:.8rem;font-weight:800;cursor:pointer;transition:.1s;
  text-transform:uppercase;letter-spacing:.3px;box-shadow:0 3px 0 var(--blue-dk);
  text-decoration:none;display:inline-flex;align-items:center;gap:6px;
}
.btn-submit-mission:hover{filter:brightness(1.05);text-decoration:none}
.btn-submit-mission:active{box-shadow:none;transform:translateY(3px)}

/* Badge validée */
.done-badge{
  display:inline-flex;align-items:center;gap:4px;
  padding:6px 12px;background:var(--green-sh);border:2px solid var(--green);
  border-radius:var(--radius-sm);color:var(--green);font-weight:800;font-size:.8rem;
}

/* Section UE */
.ue-section{margin-bottom:36px}
.ue-header{
  display:flex;align-items:center;gap:12px;margin-bottom:16px;
  padding-bottom:12px;border-bottom:2px solid var(--border);
}
.ue-icon{
  width:42px;height:42px;border-radius:var(--radius-sm);
  display:flex;align-items:center;justify-content:center;flex-shrink:0;
}
.ue-title{font-size:1rem;font-weight:900;color:var(--text)}
.ue-sub{font-size:.78rem;color:var(--muted);font-weight:700}
.ue-count{margin-left:auto;font-size:.78rem;color:var(--muted);font-weight:800;
  background:var(--bg);border:2px solid var(--border);border-radius:20px;padding:3px 10px}

/* Empty state */
.empty-state{
  text-align:center;padding:60px 20px;color:var(--muted);
  grid-column:1/-1;
}
.empty-state svg{margin:0 auto 16px;display:block;opacity:.4}
.empty-state p{font-weight:700;font-size:1rem}
</style>

<!-- HEADER -->
<div style="margin-bottom:16px">
  <a href="index-etudiants.php" class="btn-retour" style="display:inline-flex;align-items:center;gap:6px;padding:8px 16px;background:var(--bg);border:2px solid var(--border);border-radius:var(--radius-sm);color:var(--blue);font-size:.85rem;font-weight:800;text-decoration:none;text-transform:uppercase">
    <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m12 19-7-7 7-7"/><path d="M19 12H5"/></svg>
    Retour Dashboard
  </a>
</div>

<div class="missions-header">
  <div>
    <div class="missions-title">Catalogue des missions</div>
    <div style="font-size:.85rem;color:var(--muted);font-weight:700;margin-top:4px">
      <?= $totalMissions ?> missions disponibles · Basées sur les UE R&T
    </div>
  </div>
  <?php if ($role === 'enseignant' || $role === 'admin'): ?>
  <a href="missions-crud.php?action=ajouter" class="btn-submit-mission" style="background:var(--green);box-shadow:0 3px 0 var(--green-dk)">
    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 12h14"/><path d="M12 5v14"/></svg>
    Ajouter une mission
  </a>
  <?php endif; ?>
</div>

<!-- STATS CATÉGORIES -->
<div class="cat-stats">
  <a class="cat-stat toutes <?= $cat==='toutes'?'active':'' ?>" href="?cat=toutes">
    Toutes <span class="cat-count"><?= $totalMissions ?></span>
  </a>
  <a class="cat-stat reseau <?= $cat==='reseau'?'active':'' ?>" href="?cat=reseau">
    <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="16" y="16" width="6" height="6" rx="1"/><rect x="2" y="16" width="6" height="6" rx="1"/><rect x="9" y="2" width="6" height="6" rx="1"/><path d="M5 16v-3a1 1 0 0 1 1-1h12a1 1 0 0 1 1 1v3"/><path d="M12 12V8"/></svg>
    Réseau <span class="cat-count"><?= $stats['reseau'] ?? 0 ?></span>
  </a>
  <a class="cat-stat securite <?= $cat==='secu'?'active':'' ?>" href="?cat=secu">
    <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 13c0 5-3.5 7.5-7.66 8.95a1 1 0 0 1-.67-.01C7.5 20.5 4 18 4 13V6a1 1 0 0 1 1-1c2 0 4.5-1.2 6.24-2.72a1.17 1.17 0 0 1 1.52 0C14.51 3.81 17 5 19 5a1 1 0 0 1 1 1z"/></svg>
    Sécurité <span class="cat-count"><?= $stats['secu'] ?? 0 ?></span>
  </a>
  <a class="cat-stat dev <?= $cat==='dev'?'active':'' ?>" href="?cat=dev">
    <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="16 18 22 12 16 6"/><polyline points="8 6 2 12 8 18"/></svg>
    Dev <span class="cat-count"><?= $stats['dev'] ?? 0 ?></span>
  </a>
</div>

<!-- TABS DISPONIBLES / FINIES -->
<div class="tabs" style="margin-bottom:16px">
  <a class="tab <?= $onglet==='disponibles'?'active':'' ?>" href="missions.php?onglet=disponibles">
    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><circle cx="12" cy="12" r="6"/><circle cx="12" cy="12" r="2"/></svg>
    Disponibles
    <span style="background:var(--blue);color:#fff;border-radius:20px;padding:1px 8px;font-size:.72rem"><?= count(array_filter($missions, function($m) use ($mesSubmissions){ return !isset($mesSubmissions[$m['id']]) || $mesSubmissions[$m['id']]==='refuse'; })) ?></span>
  </a>
  <a class="tab <?= $onglet==='attente'?'active':'' ?>" href="missions.php?onglet=attente">
    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
    En attente
    <span style="background:var(--orange);color:#fff;border-radius:20px;padding:1px 8px;font-size:.72rem"><?= count(array_filter($missions, function($m) use ($mesSubmissions){ return isset($mesSubmissions[$m['id']]) && $mesSubmissions[$m['id']]==='en_attente'; })) ?></span>
  </a>
  <a class="tab <?= $onglet==='finies'?'active':'' ?>" href="missions.php?onglet=finies">
    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 6 9 17l-5-5"/></svg>
    Validées
    <span style="background:var(--green);color:#fff;border-radius:20px;padding:1px 8px;font-size:.72rem"><?= count(array_filter($missions, function($m) use ($mesSubmissions){ return isset($mesSubmissions[$m['id']]) && $mesSubmissions[$m['id']]==='valide'; })) ?></span>
  </a>
</div>

<!-- BARRE DE FILTRES -->
<div class="filters-bar">
  <input type="text" placeholder="Rechercher une mission..." id="searchInput"
         value="<?= htmlspecialchars($q) ?>"
         oninput="filterMissions()">
  <select id="filterUE" onchange="filterMissions()">
    <option value="">Toutes les UE</option>
    <option value="UE1 - Administrer" <?= $ue==='UE1 - Administrer'?'selected':'' ?>>UE1 · Administrer</option>
    <option value="UE2 - Connecter"   <?= $ue==='UE2 - Connecter'  ?'selected':'' ?>>UE2 · Connecter</option>
    <option value="UE3 - Programmer"  <?= $ue==='UE3 - Programmer' ?'selected':'' ?>>UE3 · Programmer</option>
    <option value="Transversal"       <?= $ue==='Transversal'      ?'selected':'' ?>>Transversal</option>
  </select>
  <select id="filterDiff" onchange="filterMissions()">
    <option value="">Toutes difficultés</option>
    <option value="facile"    <?= $diff==='facile'?'selected':'' ?>>Facile</option>
    <option value="moyen"     <?= $diff==='moyen'?'selected':'' ?>>Moyen</option>
    <option value="difficile" <?= $diff==='difficile'?'selected':'' ?>>Difficile</option>
  </select>
</div>

<!-- GRILLE MISSIONS -->
<?php
// Si BDD vide, missions fake issues des grilles
if (empty($missions)) {
    $missions = [
      ['id'=>1,'titre'=>'Identifier les composants d\'un réseau local','description'=>'Schématise un réseau local simple. Identifie chaque composant et son rôle.','categorie'=>'reseau','ue'=>'UE1 - Administrer','difficulte'=>'facile','xp'=>30],
      ['id'=>2,'titre'=>'Installer un OS Linux sur une VM','description'=>'Installe Ubuntu Server sur VirtualBox. Configure le réseau en bridge.','categorie'=>'reseau','ue'=>'UE1 - Administrer','difficulte'=>'facile','xp'=>40],
      ['id'=>3,'titre'=>'Configurer des VLANs','description'=>'Configure au moins 3 VLANs sur un switch Cisco. Vérifie l\'isolation.','categorie'=>'reseau','ue'=>'UE1 - Administrer','difficulte'=>'moyen','xp'=>70],
      ['id'=>4,'titre'=>'Installer un serveur DHCP','description'=>'Installe isc-dhcp-server sur Linux. Teste l\'attribution automatique.','categorie'=>'reseau','ue'=>'UE1 - Administrer','difficulte'=>'moyen','xp'=>60],
      ['id'=>5,'titre'=>'Configurer un point d\'accès WiFi','description'=>'Configure un AP WiFi WPA2-AES. Mesure le débit avec iperf.','categorie'=>'reseau','ue'=>'UE2 - Connecter','difficulte'=>'moyen','xp'=>65],
      ['id'=>6,'titre'=>'Mettre en place un tunnel VPN','description'=>'Configure un tunnel IPSec entre deux routeurs. Teste le chiffrement.','categorie'=>'securite','ue'=>'UE2 - Connecter','difficulte'=>'moyen','xp'=>90],
      ['id'=>7,'titre'=>'Créer une API REST en PHP','description'=>'Développe une API REST complète GET/POST/PUT/DELETE. Teste avec curl.','categorie'=>'dev','ue'=>'UE3 - Programmer','difficulte'=>'moyen','xp'=>80],
      ['id'=>8,'titre'=>'Sécuriser une app contre OWASP Top 10','description'=>'Identifie et corrige 5 failles OWASP sur DVWA. Rédige un rapport.','categorie'=>'securite','ue'=>'UE3 - Programmer','difficulte'=>'difficile','xp'=>180],
      ['id'=>9,'titre'=>'Mettre en place un pare-feu iptables','description'=>'Configure iptables pour filtrer le trafic. Teste chaque règle.','categorie'=>'securite','ue'=>'Transversal','difficulte'=>'moyen','xp'=>80],
      ['id'=>10,'titre'=>'Créer une page PHP dynamique avec BDD','description'=>'PHP + MySQL via PDO. Affiche les données avec pagination.','categorie'=>'dev','ue'=>'UE3 - Programmer','difficulte'=>'moyen','xp'=>60],
      ['id'=>11,'titre'=>'Scanner des vulnérabilités avec nmap','description'=>'Scan réseau autorisé. Identifie les CVE potentielles. Rapport.','categorie'=>'securite','ue'=>'Transversal','difficulte'=>'moyen','xp'=>80],
      ['id'=>12,'titre'=>'Déployer une app web LAMP','description'=>'PHP/MySQL sur Linux. Virtual hosts Apache, pare-feu, permissions.','categorie'=>'dev','ue'=>'UE3 - Programmer','difficulte'=>'difficile','xp'=>120],
    ];
}

$catIcon = [
    'secu'     => '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 13c0 5-3.5 7.5-7.66 8.95a1 1 0 0 1-.67-.01C7.5 20.5 4 18 4 13V6a1 1 0 0 1 1-1c2 0 4.5-1.2 6.24-2.72a1.17 1.17 0 0 1 1.52 0C14.51 3.81 17 5 19 5a1 1 0 0 1 1 1z"/></svg>',
    'reseau'   => '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="16" y="16" width="6" height="6" rx="1"/><rect x="2" y="16" width="6" height="6" rx="1"/><rect x="9" y="2" width="6" height="6" rx="1"/><path d="M5 16v-3a1 1 0 0 1 1-1h12a1 1 0 0 1 1 1v3"/><path d="M12 12V8"/></svg>',
    'securite' => '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 13c0 5-3.5 7.5-7.66 8.95a1 1 0 0 1-.67-.01C7.5 20.5 4 18 4 13V6a1 1 0 0 1 1-1c2 0 4.5-1.2 6.24-2.72a1.17 1.17 0 0 1 1.52 0C14.51 3.81 17 5 19 5a1 1 0 0 1 1 1z"/></svg>',
    'dev'      => '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="16 18 22 12 16 6"/><polyline points="8 6 2 12 8 18"/></svg>',
];
$catLabel = ['reseau'=>'Réseau','secu'=>'Sécurité','securite'=>'Sécurité','dev'=>'Dev','autre'=>'Autre'];
$catColor = ['reseau'=>'var(--blue)','secu'=>'var(--red)','securite'=>'var(--red)','dev'=>'var(--green)','autre'=>'var(--muted)'];
?>

<div class="missions-full-grid" id="missionsGrid">
<?php
// Filtrer selon l'onglet
$missionsFiltrees = array_filter($missions, function($m) use ($mesSubmissions, $onglet) {
    $statut = $mesSubmissions[$m['id']] ?? null;
    if ($onglet === 'finies')       return $statut === 'valide';
    if ($onglet === 'attente')      return $statut === 'en_attente';
    return $statut !== 'valide' && $statut !== 'en_attente'; // disponibles + refusées
});
foreach ($missionsFiltrees as $m):
  $cat_m = $m['categorie'];
  $statut_m = $mesSubmissions[$m['id']] ?? null;
  $isDone = ($statut_m === 'valide');
  $isAttente = ($statut_m === 'en_attente');
?>
  <div class="mission-full-card cat-<?= $cat_m ?> <?= $isDone?'done':'' ?>"
       data-cat="<?= $cat_m ?>"
       data-ue="<?= htmlspecialchars($m['ue']) ?>"
       data-diff="<?= $m['difficulte'] ?>"
       data-titre="<?= strtolower(htmlspecialchars($m['titre'])) ?>">

    <div class="mission-card-header">
      <span class="mission-cat cat-<?= $cat_m ?>" style="display:flex;align-items:center;gap:4px">
        <?= $catIcon[$cat_m] ?? '' ?>
        <?= $catLabel[$cat_m] ?>
      </span>
      <span class="diff-badge diff-<?= $m['difficulte'] ?>"><?= ucfirst($m['difficulte']) ?></span>
      <?php if ($isDone): ?>
        <span style="color:var(--green);font-size:.72rem;font-weight:800;display:flex;align-items:center;gap:3px">
          <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 6 9 17l-5-5"/></svg>Validée
        </span>
      <?php endif; ?>
    </div>

    <div class="mission-full-title"><?= htmlspecialchars($m['titre']) ?></div>

    <?php if (!empty($m['ue'])): ?>
    <span class="ue-tag"><?= htmlspecialchars($m['ue']) ?></span>
    <?php endif; ?>

    <div class="mission-full-desc"><?= htmlspecialchars($m['description']) ?></div>

    <div class="mission-full-footer">
      <div class="xp-badge">
        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/></svg>
        +<?= $m['xp'] ?> XP
      </div>
      <?php if ($isDone): ?>
        <div class="done-badge">
          <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 6 9 17l-5-5"/></svg>
          XP encaissé
        </div>
      <?php elseif ($isAttente): ?>
        <div style="padding:7px 14px;background:rgba(255,150,0,.1);border:2px solid var(--orange);border-radius:var(--radius-sm);color:var(--orange);font-size:.8rem;font-weight:800;display:flex;align-items:center;gap:5px">
          <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
          En attente...
        </div>
      <?php elseif ($role === 'etudiant'): ?>
        <a class="btn-submit-mission" href="soumettre.php?mission=<?= $m['id'] ?>">
          <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect width="18" height="18" x="3" y="3" rx="2"/><circle cx="9" cy="9" r="2"/><path d="m21 15-3.086-3.086a2 2 0 0 0-2.828 0L6 21"/></svg>
          Soumettre
        </a>
      <?php else: ?>
        <a class="btn-submit-mission" href="missions-crud.php?action=editer&id=<?= $m['id'] ?>"
           style="background:var(--orange);box-shadow:0 3px 0 var(--orange-dk)">
          <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21.174 6.812a1 1 0 0 0-3.986-3.987L3.842 16.174a2 2 0 0 0-.5.83l-1.321 4.352a.5.5 0 0 0 .623.622l4.353-1.32a2 2 0 0 0 .83-.497z"/></svg>
          Éditer
        </a>
      <?php endif; ?>
    </div>
  </div>
<?php endforeach; ?>

<div class="empty-state" id="emptyState" style="display:none">
  <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>
  <p>Aucune mission ne correspond à ta recherche</p>
</div>
</div>

</div>
<script>
(function(){var t=localStorage.getItem('lu-theme')||'dark';document.body.classList.toggle('dark',t==='dark');})();

function filterMissions() {
  var q    = document.getElementById('searchInput').value.toLowerCase();
  var ue   = document.getElementById('filterUE').value.toLowerCase();
  var diff = document.getElementById('filterDiff').value.toLowerCase();
  var cat  = '<?= $cat ?>';

  var cards = document.querySelectorAll('.mission-full-card');
  var visible = 0;
  cards.forEach(function(c) {
    var matchQ    = !q    || c.dataset.titre.includes(q);
    var matchUE   = !ue   || c.dataset.ue.toLowerCase() === ue;
    var matchDiff = !diff || c.dataset.diff === diff;
    var matchCat  = cat === 'toutes' || c.dataset.cat === cat;
    var show = matchQ && matchUE && matchDiff && matchCat;
    c.style.display = show ? '' : 'none';
    if (show) visible++;
  });
  document.getElementById('emptyState').style.display = visible === 0 ? '' : 'none';
}
</script>
</body></html>
