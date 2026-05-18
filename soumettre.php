<?php
// ============================================================
//  LevelUp – soumettre.php
//  Formulaire de soumission de preuve pour une mission
// ============================================================
session_start();
if (!isset($_SESSION['auth']) || $_SESSION['auth'] !== 'ok') {
    header('Location: login-page.php'); exit();
}
if ($_SESSION['role'] !== 'etudiant') {
    header('Location: index-prof.php'); exit();
}

$dbHost='localhost'; $dbName='db_PLACE_NEVEUX'; $dbUser='22505078'; $dbPasswd='126620';
try {
    $pdo = new PDO('mysql:host='.$dbHost.';dbname='.$dbName.';charset=utf8mb4',$dbUser,$dbPasswd);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) { die('Erreur BDD'); }

$missionId = (int)($_GET['mission'] ?? $_POST['mission_id'] ?? 0);
$mission   = null;
$msg       = '';
$msgType   = '';

if ($missionId) {
    $mission = $pdo->prepare('SELECT * FROM missions WHERE id=:id AND actif=1');
    $mission->execute([':id' => $missionId]);
    $mission = $mission->fetch();
}

// Vérifier si déjà soumis
$dejaSubmis = false;
if ($mission) {
    $chk = $pdo->prepare('SELECT statut FROM soumissions WHERE user_id=:u AND mission_id=:m');
    $chk->execute([':u'=>$_SESSION['user_id'],':m'=>$missionId]);
    $existant = $chk->fetch();
    $dejaSubmis = $existant !== false;
}

// Traitement POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $mission && !$dejaSubmis) {
    $commentaire = trim($_POST['commentaire'] ?? '');
    if (strlen($commentaire) < 10) {
        $msg = 'Décris ta réalisation en au moins 10 caractères.';
        $msgType = 'err';
    } else {
        // Upload fichier preuve (optionnel)
        $fichierPath = null;
        if (isset($_FILES['fichier']) && $_FILES['fichier']['error'] === 0) {
            $file    = $_FILES['fichier'];
            $ext     = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $allowed = ['jpg','jpeg','png','gif','webp','pdf'];
            $imgExts = ['jpg','jpeg','png','gif','webp'];
            if (in_array($ext, $allowed) && $file['size'] <= 5*1024*1024) {
                // Vérifier que c'est une vraie image si extension image
                $valide = true;
                if (in_array($ext, $imgExts) && !@getimagesize($file['tmp_name'])) {
                    $valide = false;
                }
                if ($valide) {
                    $dir = 'uploads/preuves/';
                    if (!is_dir($dir)) mkdir($dir, 0777, true);
                    $fname = 'preuve_'.$_SESSION['user_id'].'_'.$missionId.'_'.time().'.'.$ext;
                    if (move_uploaded_file($file['tmp_name'], $dir.$fname)) {
                        $fichierPath = $dir.$fname;
                    }
                }
            }
        }

        $pdo->prepare(
            'INSERT INTO soumissions (user_id, mission_id, statut, commentaire, fichier) VALUES (:u, :m, "en_attente", :c, :f)'
        )->execute([':u'=>$_SESSION['user_id'],':m'=>$missionId,':c'=>$commentaire,':f'=>$fichierPath]);
        $msg = 'Preuve soumise ! Le professeur va la valider.';
        $msgType = 'ok';
        $dejaSubmis = true;
    }
}

$title = 'Soumettre une preuve';
include('login.php');
?>
<link rel="stylesheet" href="styles.css"/>
<style>
.submit-wrap{max-width:640px;margin:0 auto}
.mission-recap{
  background:var(--card);border:2px solid var(--border);border-radius:var(--radius);
  padding:20px 24px;margin-bottom:24px;
}
.mission-recap-cat{font-size:.72rem;font-weight:900;text-transform:uppercase;letter-spacing:.8px;margin-bottom:8px}
.mission-recap-titre{font-size:1.1rem;font-weight:900;color:var(--text);margin-bottom:8px}
.mission-recap-desc{font-size:.85rem;color:var(--muted);font-weight:600;line-height:1.5;margin-bottom:12px}
.mission-recap-xp{display:inline-flex;align-items:center;gap:6px;font-size:.9rem;font-weight:900;color:var(--orange)}

.submit-card{background:var(--card);border:2px solid var(--border);border-radius:var(--radius);padding:24px}
.submit-card h2{font-size:1rem;font-weight:900;text-transform:uppercase;letter-spacing:.8px;margin-bottom:20px;color:var(--text)}

.btn-retour{display:inline-flex;align-items:center;gap:6px;padding:8px 16px;background:var(--bg);
  border:2px solid var(--border);border-radius:var(--radius-sm);color:var(--blue);
  font-size:.85rem;font-weight:800;text-decoration:none;transition:.15s;margin-bottom:20px;text-transform:uppercase}
.btn-retour:hover{background:var(--blue-sh);border-color:var(--blue);text-decoration:none}

.status-done{
  background:var(--green-sh);border:2px solid var(--green);border-radius:var(--radius);
  padding:20px 24px;text-align:center;
}
.status-done .icon{font-size:2.5rem;margin-bottom:8px}
.status-done h3{color:var(--green);font-weight:900;font-size:1.1rem;margin-bottom:6px}
.status-done p{color:var(--muted);font-size:.88rem;font-weight:700}
</style>

<div class="submit-wrap">
  <?php
  // Détecter d'où vient l'utilisateur pour le bon bouton retour
  $retourUrl   = 'missions.php';
  $retourLabel = 'Retour aux missions';
  $referer = $_SERVER['HTTP_REFERER'] ?? '';
  if (strpos($referer, 'profile.php') !== false) {
      $retourUrl   = 'profile.php';
      $retourLabel = 'Retour au profil';
  } elseif (strpos($referer, 'index-etudiants.php') !== false) {
      $retourUrl   = 'index-etudiants.php';
      $retourLabel = 'Retour au dashboard';
  }
  // Conserver via GET param pour les redirections post-form
  if (isset($_GET['from'])) {
      if ($_GET['from'] === 'profile')    { $retourUrl = 'profile.php';          $retourLabel = 'Retour au profil'; }
      if ($_GET['from'] === 'dashboard')  { $retourUrl = 'index-etudiants.php';  $retourLabel = 'Retour au dashboard'; }
  }
  ?>
  <a href="<?= $retourUrl ?>" class="btn-retour">
    <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m12 19-7-7 7-7"/><path d="M19 12H5"/></svg>
    <?= $retourLabel ?>
  </a>

<?php if (!$mission): ?>
  <div class="msg-err">Mission introuvable.</div>

<?php else: ?>

  <!-- Récap de la mission -->
  <div class="mission-recap">
    <?php
    $catLabel = ['reseau'=>'Réseau','secu'=>'Sécurité','securite'=>'Sécurité','dev'=>'Dev'];
    $catColor = ['reseau'=>'var(--blue)','secu'=>'var(--red)','securite'=>'var(--red)','dev'=>'var(--green)'];
    $cat = $mission['categorie'];
    $col = $catColor[$cat] ?? 'var(--muted)';
    ?>
    <div class="mission-recap-cat" style="color:<?= $col ?>"><?= $catLabel[$cat] ?? $cat ?> · <?= htmlspecialchars($mission['ue'] ?? '') ?></div>
    <div class="mission-recap-titre"><?= htmlspecialchars($mission['titre']) ?></div>
    <div class="mission-recap-desc"><?= htmlspecialchars($mission['description']) ?></div>
    <div class="mission-recap-xp">
      <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/></svg>
      +<?= $mission['xp'] ?> XP si validée
    </div>
  </div>

  <?php if ($dejaSubmis && $msgType !== 'ok'): ?>
  <!-- Déjà soumis -->
  <div class="status-done">
    <div style="font-size:2.5rem;margin-bottom:8px">⏳</div>
    <h3>Preuve déjà soumise</h3>
    <p>En attente de validation par le professeur. Tu seras notifié quand elle sera traitée.</p>
    <a href="missions.php" style="display:inline-flex;align-items:center;gap:6px;margin-top:14px;
       padding:10px 20px;background:var(--green);border-radius:var(--radius-sm);color:#fff;
       font-weight:800;font-size:.88rem;text-decoration:none;box-shadow:0 3px 0 var(--green-dk)">
      Voir d'autres missions
    </a>
  </div>

  <?php elseif ($msgType === 'ok'): ?>
  <!-- Succès -->
  <div class="status-done">
    <div style="font-size:2.5rem;margin-bottom:8px">✅</div>
    <h3>Preuve soumise avec succès !</h3>
    <p>Le professeur va examiner ta réalisation. Tu recevras <?= $mission['xp'] ?> XP si validée.</p>
    <a href="missions.php" style="display:inline-flex;align-items:center;gap:6px;margin-top:14px;
       padding:10px 20px;background:var(--green);border-radius:var(--radius-sm);color:#fff;
       font-weight:800;font-size:.88rem;text-decoration:none;box-shadow:0 3px 0 var(--green-dk)">
      Voir d'autres missions
    </a>
  </div>

  <?php else: ?>
  <!-- Formulaire -->
  <div class="submit-card">
    <h2>Soumettre ta preuve</h2>
    <?php if ($msg && $msgType === 'err'): ?>
      <div class="msg-err" style="margin-bottom:16px"><?= htmlspecialchars($msg) ?></div>
    <?php endif; ?>
    <form method="post" action="soumettre.php?mission=<?= $missionId ?>" enctype="multipart/form-data">
      <input type="hidden" name="mission_id" value="<?= $missionId ?>">
      <div class="form-group">
        <label for="commentaire">Décris ce que tu as réalisé <span style="color:var(--red)">*</span></label>
        <textarea id="commentaire" name="commentaire" rows="5" required
                  placeholder="Explique ce que tu as fait, les difficultés rencontrées, comment tu les as résolues..."></textarea>
      </div>

      <div class="form-group">
        <label for="fichier">Fichier preuve <span style="color:var(--muted);font-weight:600;font-size:.8rem">(optionnel · image, PDF, ZIP, scripts, configs Cisco · max 100 Mo)</span></label>
        <div style="border:2px dashed var(--border);border-radius:var(--radius-sm);padding:24px;text-align:center;
                    background:var(--bg);cursor:pointer;transition:.15s" id="dropzone"
             onclick="document.getElementById('fichier').click()"
             ondragover="this.style.borderColor='var(--blue)';event.preventDefault()"
             ondragleave="this.style.borderColor='var(--border)'"
             ondrop="handleDrop(event)">
          <svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="color:var(--muted);margin:0 auto 8px;display:block"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
          <div style="font-size:.88rem;color:var(--muted);font-weight:700;margin-bottom:4px" id="dropzone-txt">
            Glisse ton fichier ici ou clique pour choisir
          </div>
          <div style="font-size:.72rem;color:var(--muted);font-weight:600;line-height:1.6">
            Images · PDF · ZIP · Scripts Python, PHP, SQL, JS...<br>
            Fichiers Cisco (.pkt, .conf, startup-config) · Max <strong>100 Mo</strong>
          </div>
          <input type="file" id="fichier" name="fichier"
                 accept="image/*,.pdf,.zip,.tar,.gz,.7z,.py,.sh,.php,.js,.html,.css,.sql,.json,.xml,.yaml,.yml,.conf,.cfg,.pkt,.net,.gns3,.txt,.md"
                 style="display:none"
                 onchange="updateDropzone(this)">
        </div>
        <!-- Info si fichier trop gros -->
        <div style="margin-top:10px;background:rgba(255,150,0,.08);border:2px solid var(--orange);
                    border-radius:var(--radius-sm);padding:10px 14px;font-size:.8rem;color:var(--orange);font-weight:700;
                    display:flex;align-items:flex-start;gap:8px">
          <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="flex-shrink:0;margin-top:1px"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
          <div>
            Si ton fichier dépasse 100 Mo, mets un lien <strong>Google Drive</strong> ou <strong>GitHub</strong> dans ta description ci-dessus.
            <br>Exemple : <span style="font-family:monospace;font-size:.78rem">https://github.com/ton-compte/ton-projet</span>
          </div>
        </div>
      </div>

      <button type="submit" style="width:100%;padding:13px;background:var(--green);color:#fff;
              border:none;border-radius:var(--radius);font-family:var(--font);font-size:.95rem;
              font-weight:900;cursor:pointer;box-shadow:0 4px 0 var(--green-dk);transition:.1s;
              text-transform:uppercase;letter-spacing:.5px">
        Envoyer ma preuve
      </button>
    </form>
  </div>
  <?php endif; ?>

<?php endif; ?>
</div>

</div>
<script>(function(){var t=localStorage.getItem('lu-theme')||'dark';document.body.classList.toggle('dark',t==='dark');})();</script>
<script>
function updateDropzone(input) {
    var file = input.files[0];
    if (!file) return;
    var maxMo = 100;
    var sizeMo = (file.size / 1024 / 1024).toFixed(1);
    var txt = document.getElementById('dropzone-txt');
    var dz  = document.getElementById('dropzone');
    if (file.size > maxMo * 1024 * 1024) {
        txt.textContent = file.name + ' (' + sizeMo + ' Mo) — TROP LOURD, joins un lien Drive/GitHub';
        dz.style.borderColor = 'var(--red)';
        input.value = '';
    } else {
        txt.textContent = file.name + ' (' + sizeMo + ' Mo)';
        dz.style.borderColor = 'var(--green)';
    }
}
function handleDrop(e) {
    e.preventDefault();
    var file = e.dataTransfer.files[0];
    if (file) {
        document.getElementById('fichier').files = e.dataTransfer.files;
        updateDropzone(document.getElementById('fichier'));
    }
}
</script>
</body></html>
