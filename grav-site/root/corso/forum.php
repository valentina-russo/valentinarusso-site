<?php
declare(strict_types=1);
require_once __DIR__ . '/lib.php';

$user     = corsoRequireStudent();
$isAdmin  = corsoIsAdmin((int)$user['id']);
$cohortIds = array_map('intval', corsoVisibleCohortIds($user, $isAdmin));

$filtro = isset($_GET['classe']) ? (int)$_GET['classe'] : null;
if ($filtro !== null && !in_array($filtro, $cohortIds, true)) $filtro = null;

$error = '';

// Apertura di una nuova discussione
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hdCsrfVerify($_POST['csrf'] ?? '', 'nuova-discussione')) {
        $error = 'Sessione scaduta, riprova.';
    } else {
        $cohortId = (int)($_POST['cohort_id'] ?? 0);
        $lessonId = (int)($_POST['lesson_id'] ?? 0);
        $title    = trim($_POST['title'] ?? '');
        $body     = $_POST['body'] ?? '';

        // R11: si puo scrivere solo in una classe a cui si ha accesso
        if (!in_array($cohortId, $cohortIds, true)) {
            $error = 'Classe non valida.';
        } elseif ($e = corsoValidateTitle($title)) {
            $error = $e;
        } elseif ($e = corsoValidatePostBody($body)) {
            $error = $e;
        } else {
            // la lezione, se indicata, deve appartenere a quella classe
            $lessonOk = null;
            if ($lessonId) {
                $st = hdDb()->prepare('SELECT id, course_id FROM lessons WHERE id = ? AND cohort_id = ? AND deleted_at IS NULL');
                $st->execute([$lessonId, $cohortId]);
                $row = $st->fetch();
                if ($row) $lessonOk = (int)$row['id'];
            }
            hdDb()->prepare('INSERT INTO forum_posts (lesson_id, cohort_id, parent_id, title, user_id, body) VALUES (?,?,NULL,?,?,?)')
                  ->execute([$lessonOk, $cohortId, $title, $user['id'], trim($body)]);
            header('Location: discussione.php?id=' . (int)hdDb()->lastInsertId());
            exit;
        }
    }
}

$threads = corsoThreads($cohortIds, $filtro);

// classi disponibili, per filtro e per il form
$classi = [];
if ($cohortIds) {
    $in = implode(',', array_fill(0, count($cohortIds), '?'));
    $st = hdDb()->prepare("SELECT co.id, co.name, c.title AS course_title
                           FROM cohorts co JOIN courses c ON c.id = co.course_id
                           WHERE co.id IN ($in) ORDER BY c.created_at DESC, co.position");
    $st->execute($cohortIds);
    $classi = $st->fetchAll();
}

corsoHtmlHead('Forum');
corsoNav($user, $isAdmin, 'forum');
?>
<div class="wrap">
    <p class="eyebrow">Lo spazio della classe</p>
    <h1 class="hero">Forum</h1>
    <p class="hero-sub">Qui si consegnano i compiti e si fanno domande. Valentina risponde dentro ogni discussione.</p>

    <?php if ($error): ?><div class="msg err"><?= htmlspecialchars($error) ?></div><?php endif; ?>

    <?php if (count($classi) > 1): ?>
        <p style="display:flex;gap:.4rem;flex-wrap:wrap;margin-bottom:1.5rem">
            <a class="badge<?= $filtro === null ? ' oro' : '' ?>" style="text-decoration:none;padding:.4rem .85rem" href="forum.php">Tutto</a>
            <?php foreach ($classi as $cl): ?>
                <a class="badge<?= $filtro === (int)$cl['id'] ? ' oro' : '' ?>" style="text-decoration:none;padding:.4rem .85rem"
                   href="forum.php?classe=<?= (int)$cl['id'] ?>"><?= htmlspecialchars($cl['course_title']) ?><?= $isAdmin ? ' · ' . htmlspecialchars($cl['name']) : '' ?></a>
            <?php endforeach; ?>
        </p>
    <?php endif; ?>

    <?php if (empty($classi)): ?>
        <div class="card empty"><p>Non risulti iscritta a nessuna classe.</p></div>
    <?php else: ?>

    <details class="card" <?= $error ? 'open' : '' ?>>
        <summary style="cursor:pointer;font-weight:600;color:var(--navy)">Apri una nuova discussione</summary>
        <form method="post" style="margin-top:1.25rem">
            <?= corsoCsrfField('nuova-discussione') ?>

            <?php if (count($classi) > 1): ?>
                <label for="cohort_id">Classe</label>
                <select id="cohort_id" name="cohort_id" required>
                    <?php foreach ($classi as $cl): ?>
                        <option value="<?= (int)$cl['id'] ?>"<?= $filtro === (int)$cl['id'] ? ' selected' : '' ?>>
                            <?= htmlspecialchars($cl['course_title'] . ($isAdmin ? ' · ' . $cl['name'] : '')) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            <?php else: ?>
                <input type="hidden" name="cohort_id" value="<?= (int)$classi[0]['id'] ?>">
            <?php endif; ?>

            <label for="lesson_id">Lezione di riferimento</label>
            <select id="lesson_id" name="lesson_id">
                <option value="0">Nessuna, è una domanda generale</option>
                <?php foreach ($classi as $cl): ?>
                    <?php
                    $st = hdDb()->prepare('SELECT id, position, title FROM lessons WHERE cohort_id = ? AND deleted_at IS NULL ORDER BY position');
                    $st->execute([$cl['id']]);
                    foreach ($st->fetchAll() as $l): ?>
                        <option value="<?= (int)$l['id'] ?>">Lezione <?= (int)$l['position'] ?> · <?= htmlspecialchars($l['title']) ?></option>
                    <?php endforeach; ?>
                <?php endforeach; ?>
            </select>
            <p class="hint">Se stai consegnando un compito, scegli la lezione a cui si riferisce.</p>

            <label for="title">Titolo</label>
            <input type="text" id="title" name="title" maxlength="180" required placeholder="Es. Compito lezione 3, oppure Domanda sull'autorità emozionale">

            <label for="body">Messaggio</label>
            <textarea id="body" name="body" rows="6" maxlength="10000" required></textarea>

            <button type="submit" class="btn">Pubblica</button>
        </form>
    </details>

    <h2 class="sect">Discussioni</h2>
    <?php if (empty($threads)): ?>
        <div class="card empty">
            <p>Non c'è ancora nessuna discussione.</p>
            <p class="meta">Apri la prima: consegna un compito o fai una domanda.</p>
        </div>
    <?php else: ?>
        <?php foreach ($threads as $t): ?>
            <?php $snippet = trim(preg_replace('/\s+/', ' ', $t['body']));
                  if (mb_strlen($snippet) > 120) $snippet = mb_substr($snippet, 0, 120) . '…'; ?>
            <a class="card card-row" href="discussione.php?id=<?= (int)$t['id'] ?>">
                <span class="grow">
                    <h3><?= htmlspecialchars($t['title'] ?: 'Senza titolo') ?></h3>
                    <div class="snippet"><?= htmlspecialchars($snippet) ?></div>
                    <span class="meta">
                        <?= htmlspecialchars($t['author_name'] ?: $t['author_email']) ?>
                        · <?= htmlspecialchars(corsoRelativeTime($t['last_activity'])) ?>
                        <?php if ($t['lesson_position']): ?> · Lezione <?= (int)$t['lesson_position'] ?><?php endif; ?>
                        <?php if ($isAdmin): ?> · <?= htmlspecialchars($t['course_title'] . ' · ' . $t['cohort_name']) ?><?php endif; ?>
                    </span>
                </span>
                <span style="text-align:right;flex-shrink:0">
                    <?php if ((int)$t['replies'] > 0): ?>
                        <span class="badge"><?= (int)$t['replies'] ?> <?= (int)$t['replies'] === 1 ? 'risposta' : 'risposte' ?></span><br>
                    <?php endif; ?>
                    <?php if (!$t['answered'] && $t['author_role'] === 'student'): ?>
                        <span class="badge oro" style="margin-top:.35rem;display:inline-block">In attesa</span>
                    <?php endif; ?>
                </span>
            </a>
        <?php endforeach; ?>
    <?php endif; ?>

    <?php endif; ?>
</div>
<?php corsoHtmlFoot(); ?>
