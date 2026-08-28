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
        $slug = trim($_POST['slug'] ?? '');
        $slug = preg_replace('/[^a-z0-9\-]/', '', strtolower(str_replace(' ', '-', $slug)));

        if ($title === '' || $slug === '') {
            $error = 'Titolo e slug sono obbligatori.';
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
                $error = 'Slug gia in uso, scegline un altro.';
            }
        }
    }
}

corsoHtmlHead($course ? 'Modifica corso' : 'Nuovo corso');
?>
<div class="top-nav">
    <a href="index.php">&larr; Corsi</a>
    <a href="../logout.php">Esci</a>
</div>
<div class="container">
    <h1><?= $course ? 'Modifica corso' : 'Nuovo corso' ?></h1>
    <div class="card">
        <?php if ($error): ?><div class="error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
        <form method="post">
            <?= corsoCsrfField('corso-edit') ?>
            <label for="title">Titolo</label>
            <input type="text" id="title" name="title" value="<?= htmlspecialchars($course['title'] ?? '') ?>" required>
            <label for="slug">Slug (per l'URL, solo lettere/numeri/trattini)</label>
            <input type="text" id="slug" name="slug" value="<?= htmlspecialchars($course['slug'] ?? '') ?>" required>
            <button type="submit" class="btn">Salva</button>
        </form>
    </div>
</div>
<?php corsoHtmlFoot(); ?>
