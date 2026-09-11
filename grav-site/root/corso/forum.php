<?php
/**
 * Indice del forum, come in un forum classico: l'elenco delle sezioni con
 * quante discussioni e quanti messaggi contengono, e chi ha scritto per ultimo.
 *
 * Una sezione e' una classe. E' il confine che conta davvero: un'allieva vede
 * solo le sezioni delle classi a cui e' iscritta, Valentina le vede tutte.
 * Dentro una sezione si va con sezione.php, la discussione sta in
 * discussione.php.
 */
declare(strict_types=1);
require_once __DIR__ . '/lib.php';

$user      = corsoRequireStudent();
$isAdmin   = corsoIsAdmin((int)$user['id']);
$uid       = (int)$user['id'];
$cohortIds = array_map('intval', corsoVisibleCohortIds($user, $isAdmin));

corsoEnsureForumSchema();
$sezioni = corsoSezioniForum($cohortIds);

corsoHtmlHead('Forum');
corsoNav($user, $isAdmin, 'forum');
?>
<div class="wrap larga">
    <div class="aula-testa">
        <p class="briciola" style="cursor:default">Lo spazio della classe</p>
        <h1>Forum</h1>
        <p class="sotto">Qui si consegnano i compiti e si fanno domande. Entra nella sezione della tua classe.</p>
    </div>

    <?php if (empty($sezioni)): ?>
        <div class="card empty"><p>Non risulti iscritta a nessuna classe.</p>
        <p class="meta">Appena Valentina ti iscrive, qui compare la sezione del tuo corso.</p></div>
    <?php else: ?>
        <?php foreach ($sezioni as $sez): ?>
            <div class="fo-sez">
                <div class="fo-sez-testa">
                    <h2><?= htmlspecialchars($sez['course_title']) ?></h2>
                    <span class="conta">
                        <?= (int)$sez['discussioni'] ?> <?= (int)$sez['discussioni'] === 1 ? 'discussione' : 'discussioni' ?>
                        &middot; <?= (int)$sez['messaggi'] ?> <?= (int)$sez['messaggi'] === 1 ? 'messaggio' : 'messaggi' ?>
                    </span>
                </div>
                <div class="fo-riga">
                    <div class="tit">
                        <a href="sezione.php?classe=<?= (int)$sez['id'] ?>"><?= htmlspecialchars($sez['name']) ?></a>
                        <div class="sotto">Domande, compiti consegnati e risposte di Valentina</div>
                    </div>
                    <div class="fo-num"><?= (int)$sez['discussioni'] ?><span>disc.</span></div>
                    <div class="fo-num letture"><?= (int)$sez['messaggi'] ?><span>msg</span></div>
                    <div class="ultimo">
                        <?php if ($sez['ultimo']): $u = $sez['ultimo']; ?>
                            <?= corsoAvatar($u['name'], $u['email'], $u['role'] === 'admin', 34, null) ?>
                            <span style="min-width:0">
                                <a class="chi" href="discussione.php?id=<?= (int)$u['discussione_id'] ?>" style="color:inherit"><?= htmlspecialchars(mb_strimwidth((string)$u['titolo'], 0, 34, '…')) ?></a>
                                <?= htmlspecialchars($u['name'] ?: $u['email']) ?>, <?= htmlspecialchars(corsoRelativeTime($u['created_at'])) ?>
                            </span>
                        <?php else: ?>
                            <span>Nessun messaggio ancora</span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>
<?php corsoHtmlFoot(); ?>
