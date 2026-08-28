<?php
declare(strict_types=1);
require_once __DIR__ . '/../lib.php';

$admin = corsoRequireAdmin();
$id = (int)($_GET['id'] ?? 0);
$course = null;
if ($id) {
    $stmt = hdDb()->prepare('SELECT * FROM courses WHERE id = ?');
    $stmt->execute([$id]);
    $course = $stmt->fetch();
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hdCsrfVerify($_POST['csrf'] ?? '', 'corso-edit')) {
        $error = 'Sessione scaduta, riprova.';
    } else {
        $title = trim($_POST['title'] ?? '');
        $slug  = trim($_POST['slug'] ?? '');
        $slug  = preg_replace('/[^a-z0-9\-]/', '', strtolower(str_replace(' ', '-', $slug)));

        if ($title === '' || $slug === '') {
            $error = 'Servono sia il titolo sia lo slug.';
        } else {
            try {
                if ($course) {
                    $stmt = hdDb()->prepare('UPDATE courses SET title = ?, slug = ? WHERE id = ?');
                    $stmt->execute([$title, $slug, $course['id']]);
                } else {
                    $stmt = hdDb()->prepare('INSERT INTO courses (title, slug) VALUES (?, ?)');
                    $stmt->execute([$title, $slug]);
                }
                header('Location: index.php');
                exit;
            } catch (PDOException $e) {
                // SEC-CORSO-003: mai esporre il messaggio DB grezzo
                $error = 'Questo slug è già usato da un altro corso, scegline un altro.';
            }
        }
    }
}

corsoHtmlHead($course ? 'Modifica corso' : 'Nuovo corso');
corsoNav($admin, true, 'corsi');
?>
<div class="wrap" style="max-width:560px">
    <p class="eyebrow"><a href="index.php" style="color:inherit;text-decoration:none">&larr; I tuoi corsi</a></p>
    <h1 class="page"><?= $course ? 'Modifica corso' : 'Nuovo corso' ?></h1>
    <div class="card">
        <?php if ($error): ?><div class="msg err"><?= htmlspecialchars($error) ?></div><?php endif; ?>
        <form method="post">
            <?= corsoCsrfField('corso-edit') ?>
            <label for="title">Titolo del corso</label>
            <input type="text" id="title" name="title" value="<?= htmlspecialchars($course['title'] ?? '') ?>" required>

            <label for="slug">Indirizzo</label>
            <input type="text" id="slug" name="slug" value="<?= htmlspecialchars($course['slug'] ?? '') ?>" required>
            <p class="hint">Solo lettere minuscole, numeri e trattini. Es. <em>corso-base-2026</em></p>

            <button type="submit" class="btn">Salva</button>
        </form>
    </div>
</div>
<?php corsoHtmlFoot(); ?>
