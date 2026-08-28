<?php
declare(strict_types=1);
require_once __DIR__ . '/../lib.php';

$admin = corsoRequireAdmin();
$id = (int)($_GET['id'] ?? 0);
$courseId = (int)($_GET['course_id'] ?? 0);
$lesson = null;

if ($id) {
    $stmt = hdDb()->prepare('SELECT * FROM lessons WHERE id = ?');
    $stmt->execute([$id]);
    $lesson = $stmt->fetch();
    if ($lesson) $courseId = (int)$lesson['course_id'];
}

$stmt = hdDb()->prepare('SELECT id, title FROM courses WHERE id = ?');
$stmt->execute([$courseId]);
$course = $stmt->fetch();
if (!$course) {
    http_response_code(404);
    exit('Corso non trovato.');
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hdCsrfVerify($_POST['csrf'] ?? '', 'lezione-edit')) {
        $error = 'Sessione scaduta, riprova.';
    } elseif (isset($_POST['soft_delete']) && $lesson) {
        // R19: soft-delete, mai cancellazione a cascata dei post del forum
        $stmt = hdDb()->prepare('UPDATE lessons SET deleted_at = NOW() WHERE id = ?');
        $stmt->execute([$lesson['id']]);
        header('Location: index.php');
        exit;
    } else {
        $title = trim($_POST['title'] ?? '');
        $position = (int)($_POST['position'] ?? 0);
        $bunnyId = trim($_POST['bunny_video_id'] ?? '');

        if ($title === '') {
            $error = 'Il titolo e obbligatorio.';
        } else {
            $slidePath = $lesson['pdf_slide_path'] ?? null;
            $exercisePath = $lesson['pdf_exercise_path'] ?? null;

            $baseName = 'corso' . $courseId . '-lezione' . ($lesson['id'] ?? 'new') . '-' . time();
            $uploadDir = __DIR__ . '/../private-uploads';

            // R18: validazione MIME + magic bytes, non solo estensione
            if (!empty($_FILES['pdf_slide']['name'])) {
                $newPath = corsoValidatedPdfUpload('pdf_slide', $uploadDir, $baseName . '-slide');
                if ($newPath === null) {
                    $error = 'File slide non valido (deve essere un PDF, max 20MB).';
                } else {
                    $slidePath = $newPath;
                }
            }
            if (!$error && !empty($_FILES['pdf_exercise']['name'])) {
                $newPath = corsoValidatedPdfUpload('pdf_exercise', $uploadDir, $baseName . '-exercise');
                if ($newPath === null) {
                    $error = 'File esercizio non valido (deve essere un PDF, max 20MB).';
                } else {
                    $exercisePath = $newPath;
                }
            }

            if (!$error) {
                try {
                    if ($lesson) {
                        $stmt = hdDb()->prepare('UPDATE lessons SET title = ?, position = ?, bunny_video_id = ?, pdf_slide_path = ?, pdf_exercise_path = ? WHERE id = ?');
                        $stmt->execute([$title, $position, $bunnyId ?: null, $slidePath, $exercisePath, $lesson['id']]);
                    } else {
                        $stmt = hdDb()->prepare('INSERT INTO lessons (course_id, title, position, bunny_video_id, pdf_slide_path, pdf_exercise_path) VALUES (?, ?, ?, ?, ?, ?)');
                        $stmt->execute([$courseId, $title, $position, $bunnyId ?: null, $slidePath, $exercisePath]);
                    }
                    header('Location: index.php');
                    exit;
                } catch (PDOException $e) {
                    // SEC-CORSO-003: mai esporre il messaggio DB grezzo
                    $error = 'Errore nel salvataggio della classe.';
                }
            }
        }
    }
}

corsoHtmlHead($lesson ? 'Modifica classe' : 'Nuova classe');
?>
<div class="top-nav">
    <a href="index.php">&larr; Corsi</a>
    <a href="../logout.php">Esci</a>
</div>
<div class="container">
    <h1><?= $lesson ? 'Modifica classe' : 'Nuova classe' ?> &mdash; <?= htmlspecialchars($course['title']) ?></h1>
    <div class="card">
        <?php if ($error): ?><div class="error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
        <form method="post" enctype="multipart/form-data">
            <?= corsoCsrfField('lezione-edit') ?>
            <label for="title">Titolo classe</label>
            <input type="text" id="title" name="title" value="<?= htmlspecialchars($lesson['title'] ?? '') ?>" required>

            <label for="position">Numero classe (ordine)</label>
            <input type="number" id="position" name="position" value="<?= (int)($lesson['position'] ?? 1) ?>" required>

            <label for="bunny_video_id">ID video Bunny Stream (GUID)</label>
            <input type="text" id="bunny_video_id" name="bunny_video_id" value="<?= htmlspecialchars($lesson['bunny_video_id'] ?? '') ?>" placeholder="es. eb1c4f77-0cda-46be-b47d-1118ad7c2ffe">
            <p class="muted" style="margin-top:-0.5rem;">Carica prima il video su dash.bunny.net (library corso-base-human-design), poi incolla qui il GUID del video.</p>

            <label for="pdf_slide">Slide (PDF)</label>
            <input type="file" id="pdf_slide" name="pdf_slide" accept="application/pdf">
            <?php if (!empty($lesson['pdf_slide_path'])): ?><p class="muted">Attualmente caricato: <?= htmlspecialchars(basename($lesson['pdf_slide_path'])) ?></p><?php endif; ?>

            <label for="pdf_exercise">Esercizio pratico (PDF)</label>
            <input type="file" id="pdf_exercise" name="pdf_exercise" accept="application/pdf">
            <?php if (!empty($lesson['pdf_exercise_path'])): ?><p class="muted">Attualmente caricato: <?= htmlspecialchars(basename($lesson['pdf_exercise_path'])) ?></p><?php endif; ?>

            <button type="submit" class="btn">Salva</button>
        </form>

        <?php if ($lesson): ?>
        <form method="post" style="margin-top:1.5rem;" onsubmit="return confirm('Nascondere questa classe? I compiti gia postati restano visibili.');">
            <?= corsoCsrfField('lezione-edit') ?>
            <input type="hidden" name="soft_delete" value="1">
            <button type="submit" class="btn danger">Nascondi classe</button>
        </form>
        <?php endif; ?>
    </div>
</div>
<?php corsoHtmlFoot(); ?>
