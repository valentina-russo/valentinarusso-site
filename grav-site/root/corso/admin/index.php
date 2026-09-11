<?php
/**
 * La casa del pannello: il lavoro di Valentina, non la struttura dei dati.
 *
 * Prima qui c'era l'elenco dei corsi (ora in corsi.php). L'elenco e' un
 * archivio: dice com'e' organizzato il corso, non cosa manca. Questa pagina
 * risponde invece a "cosa devo fare adesso", nell'ordine in cui conta:
 * chi aspetta una risposta, quali lezioni non hanno la registrazione, chi ha
 * pagato e non e' mai entrata.
 */
declare(strict_types=1);
require_once __DIR__ . '/../lib.php';

$admin = corsoRequireAdmin();

$avviso = '';
$error  = '';

// Il link d'accesso a un'allieva che non e' mai entrata, da mandare da qui
// senza passare dalla pagina della classe.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['link_user_id'])) {
    if (!hdCsrfVerify($_POST['csrf'] ?? '', 'link-accesso')) {
        $error = 'Sessione scaduta, riprova.';
    } else {
        $st = hdDb()->prepare("SELECT email, name FROM hd_users WHERE id = ? AND role = 'student'");
        $st->execute([(int)$_POST['link_user_id']]);
        $chi = $st->fetch();
        if (!$chi) {
            $error = 'Allieva non trovata.';
        } else {
            $esito = corsoMandaLinkPassword((string)$chi['email'], (string)$chi['name']);
            if ($esito) {
                $avviso = 'Link d\'accesso mandato a ' . $chi['email'] . '. Vale due ore.';
            } else {
                $error = 'La mail non e\' partita. Riprova fra un momento.';
            }
        }
    }
}

$compiti    = corsoPendingHomework();
$senzaVideo = corsoLezioniSenzaVideo();
$maiEntrate = corsoAllieveMaiEntrate();

$classi = hdDb()->query(
    'SELECT co.id, co.name, c.title AS course_title,
            (SELECT COUNT(*) FROM lessons l WHERE l.cohort_id = co.id AND l.deleted_at IS NULL) AS lezioni,
            (SELECT COUNT(*) FROM course_enrollments e WHERE e.cohort_id = co.id) AS iscritte
       FROM cohorts co JOIN courses c ON c.id = co.course_id
      WHERE co.archived_at IS NULL
      ORDER BY c.created_at DESC, co.position ASC'
)->fetchAll();

// Il riassunto in una riga: solo le voci che hanno davvero qualcosa dentro.
$pezzi = [];
if ($compiti)    { $pezzi[] = count($compiti) . ' ' . (count($compiti) === 1 ? 'compito da correggere' : 'compiti da correggere'); }
if ($senzaVideo) { $pezzi[] = count($senzaVideo) . ' ' . (count($senzaVideo) === 1 ? 'lezione senza registrazione' : 'lezioni senza registrazione'); }
if ($maiEntrate) { $pezzi[] = count($maiEntrate) . ' ' . (count($maiEntrate) === 1 ? 'allieva mai entrata' : 'allieve mai entrate'); }

corsoHtmlHead('Oggi');
corsoNav($admin, true, 'oggi');
?>
<div class="wrap">
    <p class="eyebrow">Il pannello del corso</p>
    <h1 class="hero"><?= $pezzi ? 'Cosa ti aspetta' : 'Tutto in ordine' ?></h1>
    <p class="hero-sub">
        <?= $pezzi ? htmlspecialchars(implode(' · ', $pezzi)) : 'Nessun compito in attesa, nessuna lezione senza registrazione, nessuna allieva fuori.' ?>
    </p>

    <?php if ($avviso): ?><div class="msg ok"><?= htmlspecialchars($avviso) ?></div><?php endif; ?>
    <?php if ($error): ?><div class="msg err"><?= htmlspecialchars($error) ?></div><?php endif; ?>

    <?php if ($compiti): ?>
        <h2 class="sect">Ti stanno aspettando</h2>
        <p class="meta" style="margin-top:-.5rem">Compiti senza una tua risposta. In cima chi aspetta da pi&ugrave; tempo.</p>
        <?php foreach (array_slice($compiti, 0, 4) as $t): ?>
            <?php
            $pezzo = trim(preg_replace('/\s+/', ' ', (string)$t['body']));
            if (mb_strlen($pezzo) > 100) { $pezzo = mb_substr($pezzo, 0, 100) . '…'; }
            ?>
            <a class="card task <?= corsoUrgency($t['created_at']) ?>"
               href="../discussione.php?id=<?= (int)$t['id'] ?>&from=compiti">
                <span class="grow">
                    <span class="who"><?= htmlspecialchars($t['student_name'] ?: $t['student_email']) ?></span>
                    <?php if ($t['lesson_position']): ?><span class="badge" style="margin-left:.5rem">Lezione <?= (int)$t['lesson_position'] ?></span><?php endif; ?>
                    <div class="snippet"><?= htmlspecialchars($t['title'] ? $t['title'] . ' — ' . $pezzo : $pezzo) ?></div>
                    <span class="meta"><?= htmlspecialchars(corsoRelativeTime($t['created_at'])) ?> · <?= htmlspecialchars($t['cohort_name']) ?></span>
                </span>
                <span class="dot" aria-hidden="true"></span>
            </a>
        <?php endforeach; ?>
        <?php if (count($compiti) > 4): ?>
            <p><a class="btn ghost" href="compiti.php">Vedi tutti i <?= count($compiti) ?></a></p>
        <?php endif; ?>
    <?php endif; ?>

    <?php if ($senzaVideo): ?>
        <h2 class="sect">Lezioni senza registrazione</h2>
        <p class="meta" style="margin-top:-.5rem">Le allieve le vedono in elenco, ma dentro non trovano il video.</p>
        <div class="card">
        <?php foreach ($senzaVideo as $l): ?>
            <div class="card-row" style="padding:.6rem 0;border-top:1px solid var(--surface)">
                <span class="num"><?= (int)$l['position'] ?></span>
                <span class="grow">
                    <?= htmlspecialchars($l['title']) ?>
                    <div class="meta"><?= htmlspecialchars($l['cohort_name']) ?></div>
                </span>
                <a class="meta" href="lezione-edit.php?id=<?= (int)$l['id'] ?>">Collega il video</a>
            </div>
        <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <?php if ($maiEntrate): ?>
        <h2 class="sect">Non sono mai entrate</h2>
        <p class="meta" style="margin-top:-.5rem">Iscritte a una classe, ma non hanno mai scelto la password. Un link e ci sono.</p>
        <div class="card">
        <?php foreach ($maiEntrate as $a): ?>
            <div class="card-row" style="padding:.65rem 0;border-top:1px solid var(--surface)">
                <span class="grow">
                    <?= htmlspecialchars($a['name'] ?: $a['email']) ?>
                    <div class="meta"><?= htmlspecialchars($a['email']) ?> · <?= htmlspecialchars((string)$a['classi']) ?></div>
                </span>
                <form method="post" data-conferma="Mandare a <?= htmlspecialchars($a['email']) ?> il link per entrare?">
                    <?= corsoCsrfField('link-accesso') ?>
                    <input type="hidden" name="link_user_id" value="<?= (int)$a['id'] ?>">
                    <button type="submit" class="btn ghost" style="min-height:38px;padding:.4rem .8rem;font-size:.8125rem">Manda il link d&rsquo;accesso</button>
                </form>
            </div>
        <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <h2 class="sect">Le tue classi</h2>
    <?php if (empty($classi)): ?>
        <div class="card"><p class="meta">Nessuna classe attiva. Si parte da <a href="corsi.php">Corsi</a>.</p></div>
    <?php else: ?>
        <div class="card">
        <?php foreach ($classi as $cl): ?>
            <?php $n = corsoPendingCount((int)$cl['id']); ?>
            <a class="card-row" href="classe.php?id=<?= (int)$cl['id'] ?>" style="padding:.7rem 0;border-top:1px solid var(--surface);text-decoration:none;color:inherit">
                <span class="grow">
                    <?= htmlspecialchars($cl['name']) ?>
                    <div class="meta">
                        <?= htmlspecialchars($cl['course_title']) ?> ·
                        <?= (int)$cl['lezioni'] ?> <?= (int)$cl['lezioni'] === 1 ? 'lezione' : 'lezioni' ?> ·
                        <?= (int)$cl['iscritte'] ?> <?= (int)$cl['iscritte'] === 1 ? 'iscritta' : 'iscritte' ?>
                    </div>
                </span>
                <?php if ($n > 0): ?><span class="badge oro"><?= $n ?> da correggere</span><?php endif; ?>
                <span class="badge">Apri</span>
            </a>
        <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <p style="margin-top:1.75rem"><a class="btn ghost" href="corsi.php">Corsi, classi e lezioni &rarr;</a></p>
</div>
<?php corsoHtmlFoot(); ?>
