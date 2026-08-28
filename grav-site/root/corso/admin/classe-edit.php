<?php
declare(strict_types=1);
require_once __DIR__ . '/../lib.php';

$admin = corsoRequireAdmin();
$id       = (int)($_GET['id'] ?? 0);
$courseId = (int)($_GET['course_id'] ?? 0);

$classe = $id ? corsoCohort($id) : null;
if ($classe) $courseId = (int)$classe['course_id'];

$st = hdDb()->prepare('SELECT id, title FROM courses WHERE id = ?');
$st->execute([$courseId]);
$course = $st->fetch();
if (!$course) { http_response_code(404); exit('Corso non trovato.'); }

// Tetto di 3 classi attive: oltre, per Valentina diventa ingestibile
if (!$classe && !corsoCanAddCohort($courseId)) {
    corsoHtmlHead('Limite raggiunto');
    corsoNav($admin, true, 'corsi');
    echo '<div class="wrap"><div class="card empty"><p>Questo corso ha già '
       . CORSO_MAX_CLASSI . ' classi attive.</p><p class="meta">Archivia una classe conclusa per farne spazio a una nuova.</p>'
       . '<p><a class="btn ghost" href="index.php">Torna ai corsi</a></p></div></div>';
    corsoHtmlFoot();
    exit;
}

$suggerito = 'Classe ' . (count(corsoCohorts($courseId)) + 1);
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hdCsrfVerify($_POST['csrf'] ?? '', 'classe-edit')) {
        $error = 'Sessione scaduta, riprova.';
    } elseif (isset($_POST['archivia']) && $classe) {
        hdDb()->prepare('UPDATE cohorts SET archived_at = NOW() WHERE id = ?')->execute([$classe['id']]);
        header('Location: index.php');
        exit;
    } else {
        $name = trim($_POST['name'] ?? '');
        if ($name === '') {
            $error = 'Serve un nome per la classe.';
        } else {
            try {
                if ($classe) {
                    hdDb()->prepare('UPDATE cohorts SET name = ? WHERE id = ?')->execute([$name, $classe['id']]);
                    $dest = 'classe.php?id=' . (int)$classe['id'];
                } else {
                    $pos = count(corsoCohorts($courseId)) + 1;
                    hdDb()->prepare('INSERT INTO cohorts (course_id, name, position) VALUES (?,?,?)')
                          ->execute([$courseId, $name, $pos]);
                    $dest = 'classe.php?id=' . (int)hdDb()->lastInsertId();
                }
                header('Location: ' . $dest);
                exit;
            } catch (PDOException $e) {
                $error = 'Non è stato possibile salvare la classe.';
            }
        }
    }
}

corsoHtmlHead($classe ? 'Rinomina classe' : 'Nuova classe');
corsoNav($admin, true, 'corsi');
?>
<div class="wrap" style="max-width:560px">
    <p class="eyebrow"><a href="index.php" style="color:inherit;text-decoration:none">&larr; <?= htmlspecialchars($course['title']) ?></a></p>
    <h1 class="page"><?= $classe ? 'Rinomina classe' : 'Nuova classe' ?></h1>
    <div class="card">
        <?php if ($error): ?><div class="msg err"><?= htmlspecialchars($error) ?></div><?php endif; ?>
        <form method="post">
            <?= corsoCsrfField('classe-edit') ?>
            <label for="name">Nome della classe</label>
            <input type="text" id="name" name="name" required autofocus
                   value="<?= htmlspecialchars($classe['name'] ?? $suggerito) ?>">
            <p class="hint">Serve a te per distinguere i gruppi. Es. <em>Classe 1</em>, oppure <em>Gruppo gennaio 2026</em>.</p>
            <button type="submit" class="btn">Salva</button>
        </form>

        <?php if ($classe): ?>
        <form method="post" style="margin-top:2rem;border-top:1px solid var(--surface);padding-top:1.25rem"
              onsubmit="return confirm('Archiviare questa classe? Sparisce dal pannello ma nulla viene cancellato.');">
            <?= corsoCsrfField('classe-edit') ?>
            <input type="hidden" name="archivia" value="1">
            <button type="submit" class="btn ghost">Archivia classe</button>
            <p class="hint" style="margin-top:.75rem">Usalo quando un gruppo ha finito il percorso: libera un posto tra le <?= CORSO_MAX_CLASSI ?> classi attive, senza cancellare lezioni o compiti.</p>
        </form>
        <?php endif; ?>
    </div>
</div>
<?php corsoHtmlFoot(); ?>
