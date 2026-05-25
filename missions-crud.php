<?php
require_once __DIR__.'/config.php';
// ============================================================
//  LevelUp – missions-crud.php  (prof uniquement)
// ============================================================
session_start();
if (!isset($_SESSION['auth']) || $_SESSION['auth'] !== 'ok') {
    header('Location: login-page.php'); exit();
}
if (!in_array($_SESSION['role'] ?? '', ['enseignant','admin'])) {
    header('Location: index-etudiants.php'); exit();
}

$pdo = getDB(); else {
        $xp = $b['defaut'];
    }

    if ($postAction === 'ajouter') {
        $titre = trim($_POST['titre'] ?? '');
        $desc  = trim($_POST['description'] ?? '');
        $ue    = trim($_POST['ue'] ?? '');

        if (strlen($titre) < 5) {
            $msg = 'Le titre doit faire au moins 5 caractères.'; $msgType = 'err';
        } elseif (strlen($desc) < 20) {
            $msg = 'La description doit faire au moins 20 caractères.'; $msgType = 'err';
        } else {
            $pdo->prepare(
                'INSERT INTO missions (titre, description, categorie, ue, difficulte, xp, actif)
                 VALUES (:t, :d, :c, :u, :diff, :xp, 1)'
            )->execute([':t'=>$titre,':d'=>$desc,':c'=>$cat,':u'=>$ue,':diff'=>$diff,':xp'=>$xp]);
            $msg = 'Mission "'.$titre.'" créée avec succès (+' .$xp.' XP) !';
            $msgType = 'ok';
            $action = 'liste';
        }
    } elseif ($postAction === 'modifier') {
        $id    = (int)($_POST['id'] ?? 0);
        $titre = trim($_POST['titre'] ?? '');
        $desc  = trim($_POST['description'] ?? '');
        $ue    = trim($_POST['ue'] ?? '');
        if ($id && strlen($titre) >= 5 && strlen($desc) >= 20) {
            $pdo->prepare(
                'UPDATE missions SET titre=:t, description=:d, categorie=:c, ue=:u, difficulte=:diff, xp=:xp WHERE id=:id'
            )->execute([':t'=>$titre,':d'=>$desc,':c'=>$cat,':u'=>$ue,':diff'=>$diff,':xp'=>$xp,':id'=>$id]);
            $msg = 'Mission modifiée !'; $msgType = 'ok'; $action = 'liste';
        } else {
            $msg = 'Titre (5+ car.) et description (20+ car.) obligatoires.'; $msgType = 'err';
        }
    } elseif ($postAction === 'archiver') {
        $id  = (int)($_POST['id'] ?? 0);
        $act = (int)($_POST['actif_actuel'] ?? 1);
        if ($id) {
            $pdo->prepare('UPDATE missions SET actif=:a WHERE id=:id')
                ->execute([':a' => $act ? 0 : 1, ':id' => $id]);
            $msg = $act ? 'Mission archivée.' : 'Mission restaurée.';
            $msgType = 'ok';
        }
    }
}

// ---- Charger mission pour édition ----
if ($action === 'editer' && $editId) {
    $missionEdit = $pdo->prepare('SELECT * FROM missions WHERE id=:id');
    $missionEdit->execute([':id' => $editId]);
    $missionEdit = $missionEdit->fetch();
    if (!$missionEdit) { $action = 'liste'; }
}

// ---- Liste des missions ----
$filtreCat  = $_GET['cat']  ?? 'toutes';
$filtreActif= $_GET['actif'] ?? '1';
$whereList  = [];
$paramsL    = [];
if ($filtreCat !== 'toutes') { $whereList[] = 'categorie=:cat'; $paramsL[':cat'] = $filtreCat; }
if ($filtreActif !== 'toutes') { $whereList[] = 'actif=:act'; $paramsL[':act'] = (int)$filtreActif; }
$whereSQL = $whereList ? 'WHERE '.implode(' AND ', $whereList) : '';
$missions = $pdo->prepare("SELECT * FROM missions $whereSQL ORDER BY actif DESC, difficulte ASC, xp ASC");
$missions->execute($paramsL);
$missions = $missions->fetchAll();

// Stats
$stmtStats = $pdo->query('SELECT categorie, COUNT(*) as nb FROM missions WHERE actif=1 GROUP BY categorie');
$stats = ['reseau'=>0,'secu'=>0,'dev'=>0];
foreach ($stmtStats->fetchAll() as $r) $stats[$r['categorie']] = $r['nb'];
$total = array_sum($stats);

$title = 'Gérer les missions';
include('login.php');
?>
<link rel="stylesheet" href="styles.css"/>
<style>
.crud-layout { display: grid; grid-template-columns: 1fr 380px; gap: 24px; align-items: start; }
@media(max-width:900px) { .crud-layout { grid-template-columns: 1fr; } }

/* Formulaire création/édition */
.form-card {
  background: var(--card); border: 2px solid var(--border);
  border-radius: var(--radius); padding: 24px; position: sticky; top: 80px;
}
.form-card h2 {
  font-size: .9rem; font-weight: 900; text-transform: uppercase;
  letter-spacing: .8px; color: var(--text); margin-bottom: 20px;
  display: flex; align-items: center; gap: 8px;
}

/* XP preview */
.xp-preview {
  background: var(--bg); border: 2px solid var(--border);
  border-radius: var(--radius-sm); padding: 14px 16px; margin: 14px 0;
  transition: .3s;
}
.xp-preview-val {
  font-size: 2rem; font-weight: 900; color: var(--orange);
  display: flex; align-items: center; gap: 8px;
}
.xp-preview-range {
  font-size: .75rem; color: var(--muted); font-weight: 700; margin-top: 4px;
}
.xp-slider {
  width: 100%; margin-top: 10px; accent-color: var(--orange);
}
.diff-selector { display: flex; gap: 8px; margin-bottom: 4px; }
.diff-btn {
  flex: 1; padding: 10px 6px; border: 2px solid var(--border);
  border-radius: var(--radius-sm); background: var(--bg);
  cursor: pointer; font-family: var(--font); font-size: .8rem;
  font-weight: 800; text-align: center; transition: .15s; text-transform: uppercase;
}
.diff-btn.facile.selected    { border-color: var(--green);  color: var(--green);  background: var(--green-sh); }
.diff-btn.moyen.selected     { border-color: var(--orange); color: var(--orange); background: rgba(255,150,0,.1); }
.diff-btn.difficile.selected { border-color: var(--red);    color: var(--red);    background: var(--red-sh); }

/* Table missions */
.missions-mgmt { background: var(--card); border: 2px solid var(--border); border-radius: var(--radius); overflow: hidden; }
.missions-mgmt table { width: 100%; border-collapse: collapse; font-size: .87rem; }
.missions-mgmt th {
  background: var(--bg); color: var(--muted); padding: 11px 16px;
  text-align: left; font-size: .7rem; text-transform: uppercase;
  letter-spacing: .8px; font-weight: 800; border-bottom: 2px solid var(--border);
}
.missions-mgmt td { padding: 12px 16px; border-bottom: 2px solid var(--border); vertical-align: middle; }
.missions-mgmt tr:last-child td { border-bottom: none; }
.missions-mgmt tbody tr { transition: .15s; }
.missions-mgmt tbody tr:hover { background: var(--bg); }
.missions-mgmt tbody tr.archived { opacity: .55; }
body.dark .missions-mgmt th { background: var(--bg2); }
body.dark .missions-mgmt tbody tr:hover { background: var(--bg2); }

.m-titre { font-weight: 800; color: var(--text); font-size: .88rem; }
.m-ue    { font-size: .72rem; color: var(--muted); font-weight: 600; margin-top: 2px; }

/* Filtres */
.filters-row {
  display: flex; gap: 10px; align-items: center; flex-wrap: wrap;
  padding: 14px 16px; border-bottom: 2px solid var(--border); background: var(--bg);
}
body.dark .filters-row { background: var(--bg2); }
.filter-btn {
  padding: 6px 14px; border-radius: 20px; border: 2px solid var(--border);
  background: var(--card); color: var(--muted); font-size: .78rem;
  font-weight: 800; cursor: pointer; text-decoration: none; transition: .15s; text-transform: uppercase;
}
.filter-btn:hover { text-decoration: none; }
.filter-btn.active { border-color: var(--green); color: var(--green); background: var(--green-sh); }

/* Stats header */
.crud-header { display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 12px; margin-bottom: 20px; }
.stat-chips { display: flex; gap: 8px; flex-wrap: wrap; }
.stat-chip {
  display: flex; align-items: center; gap: 6px;
  padding: 6px 14px; border-radius: 20px; border: 2px solid var(--border);
  background: var(--card); font-size: .8rem; font-weight: 800; color: var(--muted);
}
</style>

<!-- RETOUR -->
<div style="margin-bottom:16px">
  <a href="index-prof.php" style="display:inline-flex;align-items:center;gap:6px;padding:8px 16px;
     background:var(--bg);border:2px solid var(--border);border-radius:var(--radius-sm);
     color:var(--blue);font-size:.85rem;font-weight:800;text-decoration:none;text-transform:uppercase">
    <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m12 19-7-7 7-7"/><path d="M19 12H5"/></svg>
    Retour Dashboard
  </a>
</div>

<!-- HEADER -->
<div class="crud-header">
  <div>
    <h1 style="margin-bottom:4px">Gérer les missions</h1>
    <div style="font-size:.85rem;color:var(--muted);font-weight:700"><?= $total ?> missions actives au catalogue</div>
  </div>
  <div class="stat-chips">
    <div class="stat-chip"><span style="color:var(--blue);font-weight:900"><?= $stats['reseau'] ?></span> Réseau</div>
    <div class="stat-chip"><span style="color:var(--red);font-weight:900"><?= $stats['secu'] ?></span> Sécu</div>
    <div class="stat-chip"><span style="color:var(--green);font-weight:900"><?= $stats['dev'] ?></span> Dev</div>
  </div>
</div>

<?php if ($msg): ?>
<div class="msg-<?= $msgType === 'ok' ? 'ok' : 'err' ?>" style="margin-bottom:16px">
  <?= htmlspecialchars($msg) ?>
</div>
<?php endif; ?>

<div class="crud-layout">

<!-- ============================
     LISTE MISSIONS (gauche)
     ============================ -->
<div>

  <!-- Filtres -->
  <div class="missions-mgmt">
    <div class="filters-row">
      <a class="filter-btn <?= $filtreCat==='toutes'?'active':'' ?>" href="missions-crud.php?cat=toutes&actif=<?= $filtreActif ?>">Toutes</a>
      <a class="filter-btn <?= $filtreCat==='reseau' ?'active':'' ?>" href="missions-crud.php?cat=reseau&actif=<?= $filtreActif ?>" style="<?= $filtreCat==='reseau'?'border-color:var(--blue);color:var(--blue);background:var(--blue-sh)':'' ?>">Réseau</a>
      <a class="filter-btn <?= $filtreCat==='secu'   ?'active':'' ?>" href="missions-crud.php?cat=secu&actif=<?= $filtreActif ?>"   style="<?= $filtreCat==='secu'  ?'border-color:var(--red);color:var(--red);background:var(--red-sh)':'' ?>">Sécurité</a>
      <a class="filter-btn <?= $filtreCat==='dev'    ?'active':'' ?>" href="missions-crud.php?cat=dev&actif=<?= $filtreActif ?>"    style="<?= $filtreCat==='dev'   ?'border-color:var(--green);color:var(--green);background:var(--green-sh)':'' ?>">Dev</a>
      <div style="margin-left:auto;display:flex;gap:6px">
        <a class="filter-btn <?= $filtreActif==='1'?'active':'' ?>" href="missions-crud.php?cat=<?= $filtreCat ?>&actif=1">Actives</a>
        <a class="filter-btn <?= $filtreActif==='0'?'active':'' ?>" href="missions-crud.php?cat=<?= $filtreCat ?>&actif=0">Archivées</a>
        <a class="filter-btn <?= $filtreActif==='toutes'?'active':'' ?>" href="missions-crud.php?cat=<?= $filtreCat ?>&actif=toutes">Toutes</a>
      </div>
    </div>

    <table>
      <thead>
        <tr>
          <th>Mission</th>
          <th>Cat.</th>
          <th>Diff.</th>
          <th>XP</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
      <?php if (empty($missions)): ?>
        <tr><td colspan="5" style="text-align:center;padding:32px;color:var(--muted);font-weight:700">
          Aucune mission trouvée. Crée ta première mission →
        </td></tr>
      <?php endif; ?>
      <?php foreach ($missions as $m):
        $catColor = ['reseau'=>'var(--blue)','secu'=>'var(--red)','dev'=>'var(--green)'];
        $catLabel = ['reseau'=>'Réseau','secu'=>'Sécu','dev'=>'Dev'];
        $diffColor = ['facile'=>'var(--green)','moyen'=>'var(--orange)','difficile'=>'var(--red)'];
        $col = $catColor[$m['categorie']] ?? 'var(--muted)';
        $dcol = $diffColor[$m['difficulte']] ?? 'var(--muted)';

        // Nombre de soumissions pour cette mission
        $stmtSoum = $pdo->prepare('SELECT COUNT(*) FROM soumissions WHERE mission_id=:id AND statut="valide"');
        $stmtSoum->execute([':id'=>$m['id']]);
        $nbSoum = (int)$stmtSoum->fetchColumn();
      ?>
        <tr class="<?= $m['actif']?'':'archived' ?>">
          <td>
            <div class="m-titre"><?= htmlspecialchars($m['titre']) ?></div>
            <div class="m-ue"><?= htmlspecialchars($m['ue'] ?? '') ?></div>
            <?php if ($nbSoum > 0): ?>
            <div style="font-size:.68rem;color:var(--green);font-weight:800;margin-top:2px">
              ✓ <?= $nbSoum ?> étudiant<?= $nbSoum>1?'s':'' ?> validé<?= $nbSoum>1?'s':'' ?>
            </div>
            <?php endif; ?>
          </td>
          <td>
            <span style="font-size:.68rem;font-weight:900;text-transform:uppercase;letter-spacing:.5px;
                         color:<?= $col ?>;background:<?= $col ?>22;border:1px solid <?= $col ?>66;
                         border-radius:20px;padding:2px 8px">
              <?= $catLabel[$m['categorie']] ?? $m['categorie'] ?>
            </span>
          </td>
          <td>
            <span style="font-size:.72rem;font-weight:900;color:<?= $dcol ?>"><?= ucfirst($m['difficulte']) ?></span>
          </td>
          <td style="font-weight:900;color:var(--orange);font-size:.9rem">
            <?= $m['xp'] ?> <span style="font-size:.7rem;color:var(--muted);font-weight:700">XP</span>
          </td>
          <td>
            <div style="display:flex;gap:6px;flex-wrap:wrap">
              <a href="missions-crud.php?action=editer&id=<?= $m['id'] ?>"
                 style="padding:5px 10px;background:var(--blue-sh);border:2px solid var(--blue);
                        border-radius:var(--radius-sm);color:var(--blue);font-size:.75rem;
                        font-weight:800;text-decoration:none;text-transform:uppercase">
                Éditer
              </a>
              <form method="post" action="missions-crud.php" style="margin:0">
                <input type="hidden" name="action" value="archiver">
                <input type="hidden" name="id" value="<?= $m['id'] ?>">
                <input type="hidden" name="actif_actuel" value="<?= $m['actif'] ?>">
                <button type="submit" style="padding:5px 10px;background:<?= $m['actif']?'var(--red-sh)':'var(--green-sh)' ?>;
                       border:2px solid <?= $m['actif']?'var(--red)':'var(--green)' ?>;border-radius:var(--radius-sm);
                       color:<?= $m['actif']?'var(--red)':'var(--green)' ?>;font-size:.75rem;
                       font-weight:800;cursor:pointer;font-family:var(--font);text-transform:uppercase">
                  <?= $m['actif']?'Archiver':'Restaurer' ?>
                </button>
              </form>
            </div>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- ============================
     FORMULAIRE (droite, sticky)
     ============================ -->
<div>
  <div class="form-card">
    <h2>
      <?php if ($action === 'editer' && $missionEdit): ?>
        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21.174 6.812a1 1 0 0 0-3.986-3.987L3.842 16.174a2 2 0 0 0-.5.83l-1.321 4.352a.5.5 0 0 0 .623.622l4.353-1.32a2 2 0 0 0 .83-.497z"/></svg>
        Modifier la mission
      <?php else: ?>
        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 12h14"/><path d="M12 5v14"/></svg>
        Nouvelle mission
      <?php endif; ?>
    </h2>

    <form method="post" action="missions-crud.php" id="missionForm">
      <input type="hidden" name="action" value="<?= ($action==='editer'&&$missionEdit)?'modifier':'ajouter' ?>">
      <?php if ($action==='editer'&&$missionEdit): ?>
      <input type="hidden" name="id" value="<?= $missionEdit['id'] ?>">
      <?php endif; ?>

      <!-- Titre -->
      <div class="form-group">
        <label>Titre de la mission <span style="color:var(--red)">*</span></label>
        <input type="text" name="titre" required maxlength="200"
               value="<?= htmlspecialchars($missionEdit['titre'] ?? '') ?>"
               placeholder="Ex: Configurer un VPN site-à-site">
      </div>

      <!-- Catégorie -->
      <div class="form-group">
        <label>Catégorie <span style="color:var(--red)">*</span></label>
        <div style="display:flex;gap:8px">
          <?php $selCat = $missionEdit['categorie'] ?? 'reseau'; ?>
          <?php foreach (['reseau'=>['Réseau','var(--blue)'],'secu'=>['Sécurité','var(--red)'],'dev'=>['Dev','var(--green)']] as $val=>[$lbl,$col]): ?>
          <label style="flex:1;cursor:pointer">
            <input type="radio" name="categorie" value="<?= $val ?>" class="cat-radio" style="display:none"
                   <?= $selCat===$val?'checked':'' ?>>
            <div class="cat-opt" data-color="<?= $col ?>"
                 style="border:2px solid var(--border);border-radius:var(--radius-sm);padding:8px 4px;
                        text-align:center;font-size:.78rem;font-weight:800;transition:.15s;
                        <?= $selCat===$val ? "border-color:$col;color:$col;background:{$col}22" : 'color:var(--muted)' ?>">
              <?= $lbl ?>
            </div>
          </label>
          <?php endforeach; ?>
        </div>
      </div>

      <!-- UE -->
      <div class="form-group">
        <label>UE associée</label>
        <select name="ue">
          <?php $selUE = $missionEdit['ue'] ?? ''; ?>
          <option value="" <?= $selUE===''?'selected':'' ?>>— Aucune —</option>
          <option value="UE1 - Administrer" <?= $selUE==='UE1 - Administrer'?'selected':'' ?>>UE1 · Administrer</option>
          <option value="UE2 - Connecter"   <?= $selUE==='UE2 - Connecter'  ?'selected':'' ?>>UE2 · Connecter</option>
          <option value="UE3 - Programmer"  <?= $selUE==='UE3 - Programmer' ?'selected':'' ?>>UE3 · Programmer</option>
          <option value="Transversal"       <?= $selUE==='Transversal'      ?'selected':'' ?>>Transversal</option>
        </select>
      </div>

      <!-- Difficulté + XP preview -->
      <div class="form-group">
        <label>Difficulté <span style="color:var(--red)">*</span></label>
        <?php $selDiff = $missionEdit['difficulte'] ?? 'moyen'; ?>
        <div class="diff-selector">
          <button type="button" class="diff-btn facile <?= $selDiff==='facile'?'selected':'' ?>"
                  onclick="setDiff('facile')">
            Facile<br><span style="font-size:.65rem;font-weight:700">20–60 XP</span>
          </button>
          <button type="button" class="diff-btn moyen <?= $selDiff==='moyen'?'selected':'' ?>"
                  onclick="setDiff('moyen')">
            Moyen<br><span style="font-size:.65rem;font-weight:700">60–120 XP</span>
          </button>
          <button type="button" class="diff-btn difficile <?= $selDiff==='difficile'?'selected':'' ?>"
                  onclick="setDiff('difficile')">
            Difficile<br><span style="font-size:.65rem;font-weight:700">120–200 XP</span>
          </button>
        </div>
        <input type="hidden" name="difficulte" id="difficulteInput" value="<?= $selDiff ?>">
      </div>

      <!-- XP preview + slider -->
      <div class="xp-preview" id="xpPreview">
        <div style="font-size:.72rem;color:var(--muted);font-weight:800;text-transform:uppercase;letter-spacing:.5px;margin-bottom:6px">
          XP attribués à la validation
        </div>
        <div class="xp-preview-val">
          <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="var(--orange)" stroke-width="2"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/></svg>
          <span id="xpVal"><?= $missionEdit['xp'] ?? 80 ?></span> XP
        </div>
        <input type="range" class="xp-slider" id="xpSlider"
               min="20" max="200" step="5"
               value="<?= $missionEdit['xp'] ?? 80 ?>"
               oninput="updateXP(this.value)">
        <div class="xp-preview-range" id="xpRange">Fourchette recommandée : 60–120 XP pour Moyen</div>
        <input type="hidden" name="xp" id="xpInput" value="<?= $missionEdit['xp'] ?? 80 ?>">
      </div>

      <!-- Description -->
      <div class="form-group">
        <label>Description <span style="color:var(--red)">*</span></label>
        <textarea name="description" rows="5" required
                  placeholder="Décris précisément ce que l'étudiant doit faire, les outils à utiliser, et ce qu'il doit rendre comme preuve..."><?= htmlspecialchars($missionEdit['description'] ?? '') ?></textarea>
      </div>

      <!-- Boutons -->
      <div style="display:flex;gap:10px">
        <button type="submit" style="flex:1;padding:12px;background:var(--green);color:#fff;
                border:none;border-radius:var(--radius);font-family:var(--font);font-size:.92rem;
                font-weight:900;cursor:pointer;box-shadow:0 4px 0 var(--green-dk);transition:.1s;
                text-transform:uppercase;letter-spacing:.5px">
          <?= ($action==='editer'&&$missionEdit) ? 'Enregistrer' : 'Créer la mission' ?>
        </button>
        <?php if ($action==='editer'&&$missionEdit): ?>
        <a href="missions-crud.php" style="padding:12px 16px;background:var(--bg);border:2px solid var(--border);
           border-radius:var(--radius);color:var(--muted);font-weight:800;font-size:.88rem;
           text-decoration:none;display:flex;align-items:center;text-transform:uppercase">
          Annuler
        </a>
        <?php endif; ?>
      </div>
    </form>
  </div>

  <!-- Rappel barème -->
  <div style="background:var(--card);border:2px solid var(--border);border-radius:var(--radius);
              padding:16px 20px;margin-top:16px">
    <div style="font-size:.75rem;font-weight:900;text-transform:uppercase;letter-spacing:.8px;
                color:var(--muted);margin-bottom:12px">Barème XP recommandé</div>
    <?php foreach ($bareme as $diff => $b): ?>
    <div style="display:flex;align-items:center;justify-content:space-between;padding:8px 0;
                border-bottom:1px solid var(--border)">
      <span style="font-size:.82rem;font-weight:800;color:<?= ['facile'=>'var(--green)','moyen'=>'var(--orange)','difficile'=>'var(--red)'][$diff] ?>">
        <?= ucfirst($diff) ?>
      </span>
      <span style="font-size:.78rem;font-weight:700;color:var(--muted)">
        <?= $b['min'] ?>–<?= $b['max'] ?> XP
        <span style="color:var(--orange);font-weight:800">(défaut <?= $b['defaut'] ?>)</span>
      </span>
    </div>
    <?php endforeach; ?>
    <div style="font-size:.72rem;color:var(--muted);margin-top:10px;font-weight:600;line-height:1.5">
      Le barème est une recommandation. Tu peux ajuster librement avec le slider dans la fourchette.
    </div>
  </div>
</div>

</div><!-- fin crud-layout -->

</div>
<script>
// ---- Barème XP par difficulté ----
var bareme = {
    facile:    { min: 20,  defaut: 40,  max: 60  },
    moyen:     { min: 60,  defaut: 80,  max: 120 },
    difficile: { min: 120, defaut: 160, max: 200 }
};

var currentDiff = document.getElementById('difficulteInput').value || 'moyen';

function setDiff(diff) {
    currentDiff = diff;
    document.getElementById('difficulteInput').value = diff;

    // Mettre à jour les boutons
    document.querySelectorAll('.diff-btn').forEach(function(btn) {
        btn.classList.remove('selected');
    });
    document.querySelector('.diff-btn.'+diff).classList.add('selected');

    // Mettre à jour le slider
    var b = bareme[diff];
    var slider = document.getElementById('xpSlider');
    slider.min   = b.min;
    slider.max   = b.max;
    slider.value = b.defaut;
    updateXP(b.defaut);

    // Couleur de la preview selon difficulté
    var colors = { facile: '#58cc02', moyen: '#ff9600', difficile: '#ff4b4b' };
    document.getElementById('xpPreview').style.borderColor = colors[diff];
}

function updateXP(val) {
    val = parseInt(val);
    document.getElementById('xpVal').textContent = val;
    document.getElementById('xpInput').value     = val;
    document.getElementById('xpSlider').value    = val;
    var b = bareme[currentDiff];
    var labels = { facile: 'Facile', moyen: 'Moyen', difficile: 'Difficile' };
    document.getElementById('xpRange').textContent =
        'Fourchette recommandée : ' + b.min + '–' + b.max + ' XP pour ' + labels[currentDiff];
}

// Initialiser selon la difficulté courante au chargement
(function() {
    var b = bareme[currentDiff];
    var slider = document.getElementById('xpSlider');
    slider.min = b.min;
    slider.max = b.max;
    updateXP(slider.value);
})();

// Sélection catégorie visuelle
document.querySelectorAll('.cat-radio').forEach(function(radio) {
    radio.addEventListener('change', function() {
        document.querySelectorAll('.cat-opt').forEach(function(opt) {
            opt.style.borderColor = 'var(--border)';
            opt.style.color = 'var(--muted)';
            opt.style.background = 'transparent';
        });
        var opt = radio.nextElementSibling;
        var col = opt.dataset.color;
        opt.style.borderColor = col;
        opt.style.color = col;
        opt.style.background = col.replace(')', ', 0.12)').replace('var', 'rgba').replace('(--', '(');
    });
});

// Theme
(function(){var t=localStorage.getItem('lu-theme')||'dark';document.body.classList.toggle('dark',t==='dark');})();
</script>
</body></html>
