<?php
require_once __DIR__.'/config.php';
// ============================================================
//  LevelUp – valider.php  (prof uniquement)
// ============================================================
session_start();
if (!isset($_SESSION['auth']) || $_SESSION['auth'] !== 'ok') { header('Location: login-page.php'); exit(); }
if (!in_array($_SESSION['role']??'', ['enseignant','admin'])) { header('Location: index-etudiants.php'); exit(); }

$pdo = getDB(); else {
                $pdo->prepare('UPDATE soumissions SET statut="refuse", refuse_raison=:c, valide_par=:vp, valide_le=NOW() WHERE id=:id')
                    ->execute([':c'=>$comment, ':vp'=>$_SESSION['user_id'], ':id'=>$soumId]);
            }
        }
    }
    header('Location: valider.php?ok=1'); exit();
}

// Lire les soumissions en attente
$soumissions = $pdo->query(
    'SELECT s.*, m.titre, m.xp, m.categorie, m.ue,
            u.prenom, u.nom, u.user as email, u.niveau, u.xp as xp_etudiant
     FROM soumissions s
     JOIN missions m ON s.mission_id = m.id
     JOIN users u ON s.user_id = u.id
     WHERE s.statut = "en_attente"
     ORDER BY s.created_at ASC'
)->fetchAll();

$traitees = $pdo->query(
    'SELECT s.*, m.titre, m.xp, m.categorie,
            u.prenom, u.nom
     FROM soumissions s
     JOIN missions m ON s.mission_id = m.id
     JOIN users u ON s.user_id = u.id
     WHERE s.statut != "en_attente"
     ORDER BY s.valide_le DESC LIMIT 20'
)->fetchAll();

$title = 'Valider les preuves';
include('login.php');
?>
<link rel="stylesheet" href="styles.css"/>
<style>
.soum-card{
  background:var(--card);border:2px solid var(--border);border-radius:var(--radius);
  padding:20px 24px;margin-bottom:14px;
}
.soum-header{display:flex;align-items:flex-start;justify-content:space-between;gap:12px;flex-wrap:wrap;margin-bottom:12px}
.soum-meta{font-size:.75rem;color:var(--muted);font-weight:700;margin-bottom:4px}
.soum-titre{font-size:.95rem;font-weight:900;color:var(--text)}
.soum-student{display:flex;align-items:center;gap:8px;margin-top:6px}
.soum-avatar{width:32px;height:32px;border-radius:50%;background:var(--green);display:flex;align-items:center;justify-content:center;font-size:.78rem;font-weight:900;color:#fff;flex-shrink:0}
.soum-commentaire{
  background:var(--bg);border:2px solid var(--border);border-radius:var(--radius-sm);
  padding:12px 16px;margin:12px 0;font-size:.85rem;color:var(--text);font-weight:600;line-height:1.5;
}
body.dark .soum-commentaire{background:var(--bg2)}
.soum-actions{display:flex;gap:10px;flex-wrap:wrap;align-items:flex-end}
.soum-actions .form-group{margin:0;flex:1;min-width:200px}
.soum-actions textarea{resize:none;height:60px;font-size:.82rem}
.xp-pill{display:inline-flex;align-items:center;gap:4px;background:rgba(255,150,0,.12);border:2px solid var(--orange);border-radius:20px;padding:3px 10px;font-size:.78rem;font-weight:900;color:var(--orange)}

/* Badges statut */
.badge-attente{background:rgba(255,150,0,.12);color:var(--orange);border:1px solid var(--orange);border-radius:20px;padding:3px 10px;font-size:.72rem;font-weight:800;text-transform:uppercase}
.badge-valide{background:var(--green-sh);color:var(--green);border:1px solid var(--green);border-radius:20px;padding:3px 10px;font-size:.72rem;font-weight:800;text-transform:uppercase}
.badge-refuse{background:var(--red-sh);color:var(--red);border:1px solid var(--red);border-radius:20px;padding:3px 10px;font-size:.72rem;font-weight:800;text-transform:uppercase}

.tabs-valider{display:flex;gap:6px;margin-bottom:22px}
.tv-tab{padding:9px 18px;border-radius:var(--radius);background:var(--bg);border:2px solid var(--border);color:var(--muted);font-size:.85rem;font-weight:800;cursor:pointer;text-decoration:none;transition:.15s;text-transform:uppercase}
.tv-tab.active,.tv-tab:hover{background:var(--green-sh);border-color:var(--green);color:var(--green);text-decoration:none}
</style>

<div style="margin-bottom:16px">
  <a href="index-prof.php" style="display:inline-flex;align-items:center;gap:6px;padding:8px 16px;
     background:var(--bg);border:2px solid var(--border);border-radius:var(--radius-sm);
     color:var(--blue);font-size:.85rem;font-weight:800;text-decoration:none;text-transform:uppercase">
    <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m12 19-7-7 7-7"/><path d="M19 12H5"/></svg>
    Retour Dashboard
  </a>
</div>

<?php if (isset($_GET['ok'])): ?>
  <div class="msg-ok" style="margin-bottom:20px">Soumission traitée avec succès !</div>
<?php endif; ?>

<h1 style="margin-bottom:6px">Valider les preuves</h1>
<p style="color:var(--muted);font-weight:700;font-size:.88rem;margin-bottom:22px">
  <?= count($soumissions) ?> en attente · <?= count($traitees) ?> traitées récemment
</p>

<div class="tabs-valider">
  <a class="tv-tab <?= !isset($_GET['vue'])||$_GET['vue']==='attente'?'active':'' ?>" href="valider.php?vue=attente">
    En attente <span style="background:var(--orange);color:#fff;border-radius:20px;padding:1px 8px;font-size:.72rem;margin-left:4px"><?= count($soumissions) ?></span>
  </a>
  <a class="tv-tab <?= isset($_GET['vue'])&&$_GET['vue']==='traitees'?'active':'' ?>" href="valider.php?vue=traitees">
    Traitées <span style="background:var(--muted);color:#fff;border-radius:20px;padding:1px 8px;font-size:.72rem;margin-left:4px"><?= count($traitees) ?></span>
  </a>
</div>

<?php $vue = $_GET['vue'] ?? 'attente'; ?>

<?php if ($vue === 'attente'): ?>

<?php if (empty($soumissions)): ?>
  <div class="card" style="text-align:center;padding:40px;color:var(--muted)">
    <svg xmlns="http://www.w3.org/2000/svg" width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" style="margin:0 auto 12px;display:block;color:var(--green)"><path d="M20 6 9 17l-5-5"/></svg>
    <div style="font-weight:800;font-size:1rem">Aucune preuve en attente</div>
    <div style="font-size:.85rem;margin-top:4px">Les étudiants soumettent leurs preuves via le catalogue des missions.</div>
  </div>
<?php else: ?>
  <?php foreach ($soumissions as $s):
    $catColor = ['reseau'=>'var(--blue)','secu'=>'var(--red)','dev'=>'var(--green)'];
    $col = $catColor[$s['categorie']] ?? 'var(--muted)';
    $init = strtoupper(mb_substr($s['prenom'],0,1).mb_substr($s['nom'],0,1));
  ?>
  <div class="soum-card">
    <div class="soum-header">
      <div>
        <div class="soum-meta" style="color:<?= $col ?>">
          <?= ucfirst($s['categorie']) ?> · <?= htmlspecialchars($s['ue'] ?? '') ?>
        </div>
        <div class="soum-titre"><?= htmlspecialchars($s['titre']) ?></div>
        <div class="soum-student">
          <div class="soum-avatar"><?= $init ?></div>
          <div>
            <div style="font-weight:800;font-size:.88rem"><?= htmlspecialchars($s['prenom'].' '.$s['nom']) ?></div>
            <div style="font-size:.73rem;color:var(--muted);font-weight:600"><?= htmlspecialchars($s['email']) ?> · Niv. <?= $s['niveau'] ?></div>
          </div>
        </div>
      </div>
      <div style="display:flex;flex-direction:column;align-items:flex-end;gap:6px">
        <span class="badge-attente">En attente</span>
        <span class="xp-pill">
          <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/></svg>
          +<?= $s['xp'] ?> XP
        </span>
        <div style="font-size:.72rem;color:var(--muted);font-weight:600"><?= date('d/m/Y H:i', strtotime($s['created_at'])) ?></div>
      </div>
    </div>

    <div class="soum-commentaire"><?= nl2br(htmlspecialchars($s['commentaire'])) ?></div>

    <?php if (!empty($s['fichier']) && file_exists($s['fichier'])): ?>
    <div style="margin:10px 0">
      <?php $ext = strtolower(pathinfo($s['fichier'], PATHINFO_EXTENSION)); ?>
      <?php if (in_array($ext, ['jpg','jpeg','png','gif','webp'])): ?>
        <a href="<?= htmlspecialchars($s['fichier']) ?>" target="_blank">
          <img src="<?= htmlspecialchars($s['fichier']) ?>" alt="Preuve"
               style="max-width:100%;max-height:300px;border-radius:var(--radius-sm);border:2px solid var(--border);cursor:zoom-in">
        </a>
      <?php else:
        $size = round(filesize($s['fichier'])/1024/1024, 1);
      ?>
        <a href="<?= htmlspecialchars($s['fichier']) ?>" target="_blank"
           style="display:inline-flex;align-items:center;gap:8px;padding:10px 16px;
                  background:var(--bg);border:2px solid var(--border);border-radius:var(--radius-sm);
                  color:var(--blue);font-weight:800;font-size:.88rem;text-decoration:none">
          Télécharger la preuve (.<?= $ext ?> · <?= $size ?> Mo)
        </a>
      <?php endif; ?>
    </div>
    <?php endif; ?>

    <form method="post" action="valider.php">
      <input type="hidden" name="soum_id" value="<?= $s['id'] ?>">
      <div class="soum-actions">
        <div class="form-group">
          <label for="com-<?= $s['id'] ?>">Commentaire (optionnel)</label>
          <textarea id="com-<?= $s['id'] ?>" name="commentaire" placeholder="Feedback pour l'étudiant..."></textarea>
        </div>
        <div style="display:flex;gap:8px;flex-shrink:0">
          <button type="submit" name="action" value="valider" class="btn-valider" style="display:flex;align-items:center;gap:6px">
            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 6 9 17l-5-5"/></svg>
            Valider (+<?= $s['xp'] ?> XP)
          </button>
          <button type="submit" name="action" value="refuser" class="btn-refuser" style="display:flex;align-items:center;gap:6px">
            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>
            Refuser
          </button>
        </div>
      </div>
    </form>
  </div>
  <?php endforeach; ?>
<?php endif; ?>

<?php else: ?>

  <?php if (empty($traitees)): ?>
    <div class="card" style="text-align:center;padding:40px;color:var(--muted)">
      <div style="font-weight:800">Aucune soumission traitée pour le moment.</div>
    </div>
  <?php else: ?>
    <?php foreach ($traitees as $s): ?>
    <div class="soum-card" style="opacity:.8">
      <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:8px">
        <div>
          <div style="font-weight:900;font-size:.9rem"><?= htmlspecialchars($s['titre']) ?></div>
          <div style="font-size:.78rem;color:var(--muted);margin-top:2px"><?= htmlspecialchars($s['prenom'].' '.$s['nom']) ?></div>
        </div>
        <div style="display:flex;align-items:center;gap:8px">
          <?php if ($s['statut']==='valide'): ?>
            <span class="badge-valide">Validée</span>
            <span class="xp-pill">+<?= $s['xp_attribue'] ?? $s['xp'] ?> XP</span>
          <?php else: ?>
            <span class="badge-refuse">Refusée</span>
          <?php endif; ?>
          <span style="font-size:.72rem;color:var(--muted)"><?= $s['valide_le'] ? date('d/m/Y', strtotime($s['valide_le'])) : '' ?></span>
        </div>
      </div>
    </div>
    <?php endforeach; ?>
  <?php endif; ?>

<?php endif; ?>

</div>
<script>(function(){var t=localStorage.getItem('lu-theme')||'dark';document.body.classList.toggle('dark',t==='dark');})();</script>
</body></html>
