<?php
declare(strict_types=1);
require_once __DIR__ . '/../lib.php';

$admin = corsoRequireAdmin();
$id = (int)($_GET['id'] ?? 0);
$cohortId = (int)($_GET['cohort_id'] ?? 0);
$lesson = null;

if ($id) {
    $stmt = hdDb()->prepare('SELECT * FROM lessons WHERE id = ?');
    $stmt->execute([$id]);
    $lesson = $stmt->fetch();
    if ($lesson) $cohortId = (int)$lesson['cohort_id'];
}

$classe = corsoCohort($cohortId);
if (!$classe) { http_response_code(404); exit('Classe non trovata.'); }
$courseId = (int)$classe['course_id'];

// Numero suggerito per una classe nuova: la prossima della sequenza
$nextPos = 1;
if (!$lesson) {
    $st = hdDb()->prepare('SELECT COALESCE(MAX(position),0)+1 FROM lessons WHERE cohort_id = ? AND deleted_at IS NULL');
    $st->execute([$cohortId]);
    $nextPos = (int)$st->fetchColumn();
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hdCsrfVerify($_POST['csrf'] ?? '', 'lezione-edit')) {
        $error = 'Sessione scaduta, riprova.';
    } elseif (isset($_POST['soft_delete']) && $lesson) {
        // R19: soft-delete, i compiti gia postati restano leggibili
        $stmt = hdDb()->prepare('UPDATE lessons SET deleted_at = NOW() WHERE id = ?');
        $stmt->execute([$lesson['id']]);
        header('Location: classe.php?id=' . $cohortId);
        exit;
    } else {
        $title    = trim($_POST['title'] ?? '');
        $position = (int)($_POST['position'] ?? 0);
        $bunnyId  = trim($_POST['bunny_video_id'] ?? '');

        if ($title === '') {
            $error = 'Serve il titolo della lezione.';
        } else {
            $slidePath    = $lesson['pdf_slide_path'] ?? null;
            $exercisePath = $lesson['pdf_exercise_path'] ?? null;
            $baseName  = 'classe' . $cohortId . '-lezione' . ($lesson['id'] ?? 'new') . '-' . time();
            $uploadDir = __DIR__ . '/../private-uploads';

            // R18: MIME + magic bytes, non solo estensione
            if (!empty($_FILES['pdf_slide']['name'])) {
                $newPath = corsoValidatedPdfUpload('pdf_slide', $uploadDir, $baseName . '-slide');
                if ($newPath === null) $error = 'Le slide devono essere un PDF sotto i 20MB.';
                else $slidePath = $newPath;
            }
            if (!$error && !empty($_FILES['pdf_exercise']['name'])) {
                $newPath = corsoValidatedPdfUpload('pdf_exercise', $uploadDir, $baseName . '-exercise');
                if ($newPath === null) $error = 'L\'esercizio deve essere un PDF sotto i 20MB.';
                else $exercisePath = $newPath;
            }

            if (!$error) {
                try {
                    if ($lesson) {
                        $stmt = hdDb()->prepare('UPDATE lessons SET title = ?, position = ?, bunny_video_id = ?, pdf_slide_path = ?, pdf_exercise_path = ? WHERE id = ?');
                        $stmt->execute([$title, $position, $bunnyId ?: null, $slidePath, $exercisePath, $lesson['id']]);
                    } else {
                        $stmt = hdDb()->prepare('INSERT INTO lessons (course_id, cohort_id, title, position, bunny_video_id, pdf_slide_path, pdf_exercise_path) VALUES (?, ?, ?, ?, ?, ?, ?)');
                        $stmt->execute([$courseId, $cohortId, $title, $position, $bunnyId ?: null, $slidePath, $exercisePath]);
                    }
                    header('Location: classe.php?id=' . $cohortId);
                    exit;
                } catch (PDOException $e) {
                    // SEC-CORSO-003: mai esporre il messaggio DB grezzo
                    $error = 'Non è stato possibile salvare la lezione.';
                }
            }
        }
    }
}

corsoHtmlHead($lesson ? 'Modifica lezione' : 'Nuova lezione');
corsoNav($admin, true, 'corsi');
?>
<div class="wrap" style="max-width:560px">
    <p class="eyebrow"><a href="classe.php?id=<?= $cohortId ?>" style="color:inherit;text-decoration:none">&larr; <?= htmlspecialchars($classe['course_title'] . ' · ' . $classe['name']) ?></a></p>
    <h1 class="page"><?= $lesson ? 'Modifica lezione' : 'Nuova lezione' ?></h1>
    <div class="card">
        <?php if ($error): ?><div class="msg err"><?= htmlspecialchars($error) ?></div><?php endif; ?>
        <form method="post" enctype="multipart/form-data">
            <?= corsoCsrfField('lezione-edit') ?>

            <label for="title">Titolo della lezione</label>
            <input type="text" id="title" name="title" value="<?= htmlspecialchars($lesson['title'] ?? '') ?>" required>

            <label for="position">Numero della lezione</label>
            <input type="number" id="position" name="position" min="1" value="<?= (int)($lesson['position'] ?? $nextPos) ?>" required>
            <p class="hint">Determina l'ordine con cui le allieve vedono le lezioni.</p>

            <label for="bunny_video_id">Video della registrazione</label>
            <input type="text" id="bunny_video_id" name="bunny_video_id" value="<?= htmlspecialchars($lesson['bunny_video_id'] ?? '') ?>" placeholder="eb1c4f77-0cda-46be-b47d-1118ad7c2ffe">
            <p class="hint">Carica prima il video su Bunny Stream (libreria <em>corso-base-human-design</em>), poi incolla qui il codice del video. Lascialo vuoto se la registrazione non è ancora pronta.</p>

            <label for="pdf_slide">Slide della lezione (PDF)</label>
            <input type="file" id="pdf_slide" name="pdf_slide" accept="application/pdf">
            <?php if (!empty($lesson['pdf_slide_path'])): ?>
                <p class="hint">Già caricato: <?= htmlspecialchars(basename($lesson['pdf_slide_path'])) ?></p>
            <?php endif; ?>

            <label for="pdf_exercise">Esercizio pratico (PDF)</label>
            <input type="file" id="pdf_exercise" name="pdf_exercise" accept="application/pdf">
            <?php if (!empty($lesson['pdf_exercise_path'])): ?>
                <p class="hint">Già caricato: <?= htmlspecialchars(basename($lesson['pdf_exercise_path'])) ?></p>
            <?php endif; ?>

            <button type="submit" class="btn">Salva lezione</button>
        </form>

        <?php if ($lesson): ?>
        <form method="post" style="margin-top:2rem;border-top:1px solid var(--surface);padding-top:1.25rem"
              onsubmit="return confirm('Nascondere questa lezione alle allieve? I compiti già postati restano salvati.');">
            <?= corsoCsrfField('lezione-edit') ?>
            <input type="hidden" name="soft_delete" value="1">
            <button type="submit" class="btn ghost">Nascondi questa lezione</button>
            <p class="hint" style="margin-top:.75rem">La lezione sparisce dall'area allieve, ma nulla viene cancellato.</p>
        </form>
        <?php endif; ?>
    </div>
</div>
<?php corsoHtmlFoot(); ?>
