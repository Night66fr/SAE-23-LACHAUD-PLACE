<?php
require_once __DIR__.'/config.php';
$title = 'Classement';
include('login.php');
$role = $_SESSION['role'] ?? 'etudiant';

// ---- Données classement ----
// Global toutes catégories
$topGlobal = $pdo->query(
    'SELECT u.id, u.user, u.prenom, u.nom, u.xp, u.niveau, u.streak, u.avatar,
            COUNT(s.id) as nb_missions
     FROM users u
     LEFT JOIN soumissions s ON s.user_id = u.id AND s.statut = "valide"
     WHERE u.actif=1 AND u.role="etudiant"
     GROUP BY u.id
     ORDER BY u.xp DESC LIMIT 20'
)->fetchAll();

$topSemaine = $pdo->query(
    'SELECT u.id, u.user, u.prenom, u.nom, u.xp, u.niveau, u.streak, u.avatar,
            COUNT(s.id) as nb_missions
     FROM users u
     LEFT JOIN soumissions s ON s.user_id = u.id AND s.statut = "valide"
     WHERE u.actif=1 AND u.role="etudiant"
     GROUP BY u.id
     ORDER BY u.xp DESC LIMIT 10'
)->fetchAll();

// Mon rang global
$monRang = 0;
foreach ($topGlobal as $i => $u) {
    if ((int)$u['id'] === (int)$_SESSION['user_id']) {
        $monRang = $i + 1;
        break;
    }
}

// Calcul du rang si pas dans le top 20
if (!$monRang) {
    $stmtRang = $pdo->prepare(
        'SELECT COUNT(*)+1 as rang FROM users
         WHERE actif=1 AND role="etudiant"
         AND xp > (SELECT xp FROM users WHERE id=:id)'
    );
    $stmtRang->execute([':id' => $_SESSION['user_id']]);
    $monRang = $stmtRang->fetch()['rang'] ?? '—';
}

$monXP     = (int)$_SESSION['xp'];
$monNiveau = (int)$_SESSION['niveau'];
$monStreak = (int)$_SESSION['streak'];

// Onglet actif
$vue = $_GET['vue'] ?? 'global';
?>
<link rel="stylesheet" href="styles.css"/>
<style>
/* ---- PAGE CLASSEMENT STYLE HACKERONE ---- */
.lb-page-header{
  background:linear-gradient(135deg,var(--green) 0%,var(--blue) 100%);
  border-radius:var(--radius);padding:28px 32px;margin-bottom:28px;
  display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:16px;
}
.lb-page-title{font-size:1.6rem;font-weight:900;color:#fff}
.lb-page-sub{font-size:.88rem;color:rgba(255,255,255,.8);font-weight:700;margin-top:4px}

/* Ma position */
.my-rank-card{
  background:rgba(255,255,255,.15);border:2px solid rgba(255,255,255,.3);
  border-radius:var(--radius);padding:14px 20px;
  display:flex;align-items:center;gap:16px;backdrop-filter:blur(4px);
}
.my-rank-num{font-size:2rem;font-weight:900;color:#fff}
.my-rank-lbl{font-size:.72rem;color:rgba(255,255,255,.8);font-weight:800;text-transform:uppercase;letter-spacing:.5px}
.my-rank-xp{font-size:.9rem;font-weight:800;color:rgba(255,255,255,.9)}

/* Tabs */
.lb-tabs{display:flex;gap:8px;margin-bottom:22px}
.lb-tab{
  padding:10px 22px;border-radius:var(--radius);background:var(--bg);
  border:2px solid var(--border);color:var(--muted);font-size:.85rem;
  font-weight:800;cursor:pointer;text-decoration:none;transition:.15s;
  text-transform:uppercase;letter-spacing:.3px;
}
.lb-tab.active,.lb-tab:hover{background:var(--green-sh);border-color:var(--green);color:var(--green);text-decoration:none}

/* Table classement HackerOne style */
.lb-table-wrap{
  background:var(--card);border:2px solid var(--border);
  border-radius:var(--radius);overflow:hidden;
}
.lb-table{width:100%;border-collapse:collapse}
.lb-table thead tr{background:var(--bg)}
.lb-table th{
  padding:12px 18px;text-align:left;font-size:.72rem;
  color:var(--muted);text-transform:uppercase;letter-spacing:.8px;
  font-weight:800;border-bottom:2px solid var(--border);
}
.lb-table td{padding:14px 18px;border-bottom:2px solid var(--border);vertical-align:middle}
.lb-table tr:last-child td{border-bottom:none}
.lb-table tbody tr{transition:.15s}
.lb-table tbody tr:hover{background:var(--green-sh)}
.lb-table tbody tr.is-me{background:rgba(88,204,2,.08);border-left:4px solid var(--green)}
body.dark .lb-table thead tr{background:var(--bg2)}
body.dark .lb-table tbody tr:hover{background:rgba(88,204,2,.05)}

/* Rang médailles */
.rank-cell{display:flex;align-items:center;gap:10px;min-width:60px}
.rank-num{
  width:32px;height:32px;border-radius:50%;display:flex;align-items:center;justify-content:center;
  font-size:.85rem;font-weight:900;background:var(--bg);border:2px solid var(--border);color:var(--muted);
  flex-shrink:0;
}
.rank-1{background:linear-gradient(135deg,#ffd700,#ffaa00);border-color:#ffaa00;color:#fff;box-shadow:0 2px 8px rgba(255,170,0,.4)}
.rank-2{background:linear-gradient(135deg,#c0c0c0,#909090);border-color:#909090;color:#fff;box-shadow:0 2px 8px rgba(144,144,144,.4)}
.rank-3{background:linear-gradient(135deg,#cd7f32,#a05a20);border-color:#a05a20;color:#fff;box-shadow:0 2px 8px rgba(160,90,32,.4)}

/* Avatar dans table */
.player-cell{display:flex;align-items:center;gap:12px}
.player-avatar{
  width:38px;height:38px;border-radius:50%;object-fit:cover;
  border:2px solid var(--border);flex-shrink:0;background:var(--green);
  display:flex;align-items:center;justify-content:center;
  font-size:.85rem;font-weight:900;color:#fff;
}
.player-avatar img{width:100%;height:100%;border-radius:50%;object-fit:cover}
.player-name{font-weight:800;color:var(--text);font-size:.9rem}
.player-email{font-size:.72rem;color:var(--muted);font-weight:600}

/* XP bar dans table */
.xp-cell{min-width:160px}
.xp-table-val{font-size:.9rem;font-weight:900;color:var(--orange);margin-bottom:4px;display:flex;align-items:center;gap:4px}
.xp-mini-bar{height:6px;background:var(--border);border-radius:20px;overflow:hidden}
.xp-mini-fill{height:100%;border-radius:20px;background:var(--green);transition:width .5s}

/* Niveau badge */
.niveau-pill{
  display:inline-flex;align-items:center;justify-content:center;
  width:28px;height:28px;border-radius:50%;background:var(--orange);
  font-size:.75rem;font-weight:900;color:#fff;box-shadow:0 2px 0 var(--orange-dk);
}

/* Streak */
.streak-pill{
  display:inline-flex;align-items:center;gap:4px;
  background:var(--red-sh);border:1px solid var(--red);
  border-radius:20px;padding:3px 8px;font-size:.75rem;font-weight:800;color:var(--red);
}

/* Score HackerOne = XP calculé */
.score-cell{text-align:right}
.score-val{font-size:1.1rem;font-weight:900;color:var(--text)}
.score-rank-change{font-size:.72rem;font-weight:800;margin-top:2px}
.rank-up{color:var(--green)}
.rank-same{color:var(--muted)}

/* Podium top 3 */
.podium{display:grid;grid-template-columns:1fr 1.2fr 1fr;gap:12px;margin-bottom:28px;align-items:end}
.podium-item{
  background:var(--card);border:2px solid var(--border);
  border-radius:var(--radius);padding:20px 16px;text-align:center;
  transition:.15s;position:relative;overflow:hidden;
}
.podium-item.p1{
  border-color:var(--yellow);
  background:linear-gradient(180deg,rgba(255,217,0,.08) 0%,var(--card) 100%);
}
.podium-item.p2{
  border-color:#c0c0c0;
  background:linear-gradient(180deg,rgba(192,192,192,.08) 0%,var(--card) 100%);
}
.podium-item.p3{
  border-color:#cd7f32;
  background:linear-gradient(180deg,rgba(205,127,50,.08) 0%,var(--card) 100%);
}
.podium-crown{font-size:1.8rem;margin-bottom:6px}
.podium-avatar{
  width:64px;height:64px;border-radius:50%;background:var(--green);
  display:flex;align-items:center;justify-content:center;
  font-size:1.4rem;font-weight:900;color:#fff;margin:0 auto 10px;
  border:3px solid var(--border);
}
.podium-item.p1 .podium-avatar{border-color:var(--yellow);box-shadow:0 0 20px rgba(255,217,0,.3)}
.podium-item.p2 .podium-avatar{border-color:#c0c0c0}
.podium-item.p3 .podium-avatar{border-color:#cd7f32}
.podium-name{font-size:.9rem;font-weight:900;color:var(--text);margin-bottom:2px}
.podium-xp{font-size:.82rem;font-weight:800;color:var(--orange);margin-bottom:6px;display:flex;align-items:center;justify-content:center;gap:4px}
.podium-niveau{display:inline-flex;align-items:center;justify-content:center;
  width:26px;height:26px;border-radius:50%;background:var(--orange);
  font-size:.72rem;font-weight:900;color:#fff;box-shadow:0 2px 0 var(--orange-dk)}
</style>

<div style="margin-bottom:16px">
  <a href="<?= in_array($_SESSION['role']??'',['enseignant','admin'])?'index-prof.php':'index-etudiants.php' ?>"
     style="display:inline-flex;align-items:center;gap:6px;padding:8px 16px;
     background:var(--bg);border:2px solid var(--border);border-radius:var(--radius-sm);
     color:var(--blue);font-size:.85rem;font-weight:800;text-decoration:none;text-transform:uppercase">
    <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m12 19-7-7 7-7"/><path d="M19 12H5"/></svg>
    Retour Dashboard
  </a>
</div>

<!-- HEADER HERO -->
<div class="lb-page-header">
  <div>
    <div class="lb-page-title">Classement LevelUp</div>
    <div class="lb-page-sub">Progresse, monte en niveau et grimpe dans le classement</div>
  </div>
  <div class="my-rank-card">
    <div>
      <div class="my-rank-lbl">Mon rang</div>
      <div class="my-rank-num">#<?= $monRang ?></div>
    </div>
    <div style="width:1px;height:40px;background:rgba(255,255,255,.3)"></div>
    <div>
      <div class="my-rank-lbl">Mon XP</div>
      <div class="my-rank-xp"><?= $monXP ?> XP · Niv. <?= $monNiveau ?></div>
      <?php if ($monStreak > 0): ?>
      <div style="font-size:.72rem;color:rgba(255,150,0,.9);font-weight:800;margin-top:2px">
        <?= $monStreak ?>j de streak actif
      </div>
      <?php endif; ?>
    </div>
  </div>
</div>

<!-- TABS -->
<div class="lb-tabs">
  <a class="lb-tab <?= $vue==='global' ? 'active' : '' ?>" href="leaderboard.php?vue=global">
    Global
  </a>
  <a class="lb-tab <?= $vue==='semaine' ? 'active' : '' ?>" href="leaderboard.php?vue=semaine">
    Cette semaine
  </a>
</div>

<?php if ($vue === 'global' && count($topGlobal) >= 3): ?>
<!-- PODIUM TOP 3 -->
<?php
  $initiales = function($u) { return strtoupper(mb_substr($u['prenom'],0,1).mb_substr($u['nom'],0,1)); };
  $maxXP = max(array_column($topGlobal,'xp')) ?: 1;
?>
<div class="podium">
  <!-- 2ème -->
  <div class="podium-item p2">
    <div class="podium-crown">🥈</div>
    <div class="podium-avatar"><?= $initiales($topGlobal[1]) ?></div>
    <div class="podium-name"><?= htmlspecialchars($topGlobal[1]['prenom'].' '.$topGlobal[1]['nom']) ?></div>
    <div class="podium-xp">
      <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/></svg>
      <?= $topGlobal[1]['xp'] ?> XP
    </div>
    <?php if (($topGlobal[1]['nb_missions'] ?? 0) > 0): ?>
    <div style="font-size:.72rem;color:var(--green);font-weight:800;margin-top:4px"><?= $topGlobal[1]['nb_missions'] ?> missions ✓</div>
    <?php endif; ?>
    <div class="podium-niveau"><?= $topGlobal[1]['niveau'] ?></div>
  </div>
  <!-- 1er -->
  <div class="podium-item p1">
    <div class="podium-crown">👑</div>
    <div class="podium-avatar" style="width:78px;height:78px;font-size:1.8rem"><?= $initiales($topGlobal[0]) ?></div>
    <div class="podium-name" style="font-size:1rem"><?= htmlspecialchars($topGlobal[0]['prenom'].' '.$topGlobal[0]['nom']) ?></div>
    <div class="podium-xp">
      <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/></svg>
      <?= $topGlobal[0]['xp'] ?> XP
    </div>
    <div class="podium-niveau"><?= $topGlobal[0]['niveau'] ?></div>
  </div>
  <!-- 3ème -->
  <div class="podium-item p3">
    <div class="podium-crown">🥉</div>
    <div class="podium-avatar"><?= $initiales($topGlobal[2]) ?></div>
    <div class="podium-name"><?= htmlspecialchars($topGlobal[2]['prenom'].' '.$topGlobal[2]['nom']) ?></div>
    <div class="podium-xp">
      <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/></svg>
      <?= $topGlobal[2]['xp'] ?> XP
    </div>
    <div class="podium-niveau"><?= $topGlobal[2]['niveau'] ?></div>
  </div>
</div>
<?php endif; ?>

<!-- TABLE CLASSEMENT -->
<div class="lb-table-wrap">
  <table class="lb-table">
    <thead>
      <tr>
        <th>Rang</th>
        <th>Joueur</th>
        <th>Niveau</th>
        <th>XP & Progression</th>
        <th>Missions</th>
        <th>Streak</th>
        <th style="text-align:right">Score</th>
      </tr>
    </thead>
    <tbody>
    <?php
    $liste = $vue === 'semaine' ? $topSemaine : $topGlobal;
    $maxXP = max(array_column($liste,'xp') ?: [1]);
    foreach ($liste as $i => $u):
      $rang   = $i + 1;
      $isMe   = ((int)$u['id'] === (int)$_SESSION['user_id']);
      $init   = strtoupper(mb_substr($u['prenom'],0,1).mb_substr($u['nom'],0,1));
      $pct    = $maxXP > 0 ? round($u['xp'] / $maxXP * 100) : 0;
      $hasAvatar = !empty($u['avatar']) && file_exists($u['avatar']);

      // Couleur rang
      if ($rang===1)      { $rankClass='rank-1'; $rankIcon='👑'; }
      elseif ($rang===2)  { $rankClass='rank-2'; $rankIcon='🥈'; }
      elseif ($rang===3)  { $rankClass='rank-3'; $rankIcon='🥉'; }
      else                { $rankClass='';       $rankIcon=$rang; }
    ?>
      <tr class="<?= $isMe ? 'is-me' : '' ?>">
        <td>
          <div class="rank-cell">
            <div class="rank-num <?= $rankClass ?>"><?= $rankIcon ?></div>
          </div>
        </td>
        <td>
          <div class="player-cell">
            <div class="player-avatar">
              <?php if ($hasAvatar): ?>
                <img src="<?= htmlspecialchars($u['avatar']) ?>" alt="avatar">
              <?php else: ?>
                <?= $init ?>
              <?php endif; ?>
            </div>
            <div>
              <div class="player-name">
                <?= htmlspecialchars($u['prenom'].' '.$u['nom']) ?>
                <?= $isMe ? '<span style="color:var(--green);font-size:.72rem;font-weight:800"> (vous)</span>' : '' ?>
              </div>
              <div class="player-email"><?= htmlspecialchars($u['user']) ?></div>
            </div>
          </div>
        </td>
        <td>
          <div class="niveau-pill"><?= $u['niveau'] ?></div>
        </td>
        <td class="xp-cell">
          <div class="xp-table-val">
            <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/></svg>
            <?= number_format($u['xp']) ?> XP
          </div>
          <div class="xp-mini-bar">
            <div class="xp-mini-fill" style="width:<?= $pct ?>%"></div>
          </div>
        </td>
        <td style="text-align:center">
          <?php if (($u['nb_missions'] ?? 0) > 0): ?>
          <div style="display:inline-flex;align-items:center;gap:4px;background:var(--green-sh);
                      border:1px solid var(--green);border-radius:20px;padding:3px 10px;
                      font-size:.75rem;font-weight:800;color:var(--green)">
            <svg xmlns="http://www.w3.org/2000/svg" width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 6 9 17l-5-5"/></svg>
            <?= $u['nb_missions'] ?>
          </div>
          <?php else: ?>
          <span style="color:var(--muted);font-size:.78rem">—</span>
          <?php endif; ?>
        </td>
        <td>
          <?php if ($u['streak'] > 0): ?>
          <div class="streak-pill">
            <svg xmlns="http://www.w3.org/2000/svg" width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M8.5 14.5A2.5 2.5 0 0 0 11 12c0-1.38-.5-2-1-3-1.072-2.143-.224-4.054 2-6 .5 2.5 2 4.9 4 6.5 2 1.6 3 3.5 3 5.5a7 7 0 1 1-14 0c0-1.153.433-2.294 1-3a2.5 2.5 0 0 0 2.5 2.5z"/></svg>
            <?= $u['streak'] ?>j
          </div>
          <?php else: ?>
          <span style="color:var(--muted);font-size:.78rem">—</span>
          <?php endif; ?>
        </td>
        <td class="score-cell">
          <div class="score-val"><?= number_format($u['xp']) ?></div>
          <div class="score-rank-change rank-up" style="font-size:.7rem">pts</div>
        </td>
      </tr>
    <?php endforeach; ?>
    <?php if (empty($liste)): ?>
      <tr><td colspan="6" style="text-align:center;padding:32px;color:var(--muted);font-weight:700">Aucun étudiant inscrit.</td></tr>
    <?php endif; ?>
    </tbody>
  </table>
</div>

</div>
<script>
(function(){var t=localStorage.getItem('lu-theme')||'dark';document.body.classList.toggle('dark',t==='dark');})();
</script>
</body></html>
