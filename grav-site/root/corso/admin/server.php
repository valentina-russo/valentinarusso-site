<?php
/**
 * Lo stato del server, in chiaro.
 *
 * Serve a due decisioni concrete: quanto spazio resta per le registrazioni
 * delle lezioni, e se questo hosting regge un LMS di quelli che si installano
 * (Chamilo, Moodle) nel caso si valutasse di cambiare piattaforma.
 *
 * Visibile solo a chi e' dentro come amministratrice.
 */
declare(strict_types=1);
require_once __DIR__ . '/../lib.php';

$admin = corsoRequireAdmin();

function gb(float $b): string { return number_format($b / 1073741824, 2, ',', '.') . ' GB'; }

$radice = dirname(__DIR__, 2);          // la cartella del sito sul server
$tot   = @disk_total_space($radice);
$lib   = @disk_free_space($radice);
$usato = ($tot && $lib) ? $tot - $lib : 0;

/** Quanto pesa una cartella, senza farci male su quelle enormi. */
function peso(string $dir): array {
    if (!is_dir($dir)) { return [0, 0]; }
    $b = 0; $n = 0;
    $it = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::LEAVES_ONLY
    );
    foreach ($it as $f) {
        if ($f->isFile()) { $b += $f->getSize(); $n++; if ($n > 20000) break; }
    }
    return [$b, $n];
}

$cartelle = [
    'Piattaforma del corso'  => dirname(__DIR__),
    'Allegati e compiti'     => dirname(__DIR__) . '/private-uploads',
    'Pagine del sito'        => $radice . '/user/pages',
    'Registrazioni (se ci finiranno qui)' => dirname(__DIR__) . '/video',
];

// Quello che chiedono Chamilo e Moodle per girare su hosting condiviso.
$estensioni = ['pdo_mysql', 'mysqli', 'mbstring', 'intl', 'gd', 'zip', 'curl', 'xml', 'json', 'openssl', 'iconv', 'zlib', 'fileinfo', 'exif', 'soap', 'ldap', 'sodium', 'bcmath'];

$db = hdDb();
$versioneDb = (string)$db->query('SELECT VERSION()')->fetchColumn();
$pesoDb = 0;
try {
    $pesoDb = (float)$db->query(
        'SELECT SUM(data_length + index_length) FROM information_schema.tables WHERE table_schema = DATABASE()'
    )->fetchColumn();
} catch (Throwable $e) { $pesoDb = 0; }

$impostazioni = [
    'Versione di PHP'            => PHP_VERSION,
    'Memoria per richiesta'      => ini_get('memory_limit'),
    'Tempo massimo di esecuzione'=> ini_get('max_execution_time') . ' s',
    'File caricabile al massimo' => ini_get('upload_max_filesize'),
    'Invio massimo (POST)'       => ini_get('post_max_size'),
    'Banca dati'                 => $versioneDb,
    'Peso della banca dati'      => $pesoDb ? gb($pesoDb) : 'non dichiarato',
];

corsoHtmlHead('Stato del server');
corsoNav($admin, true, 'oggi');
?>
<div class="wrap">
    <p class="eyebrow"><a href="index.php" style="color:inherit;text-decoration:none">&larr; Oggi</a></p>
    <h1 class="hero">Stato del server</h1>
    <p class="hero-sub">Quanto spazio c&rsquo;&egrave; per le registrazioni, e cosa sa fare questo hosting.</p>

    <h2 class="sect">Spazio sul disco</h2>
    <div class="card">
        <?php if ($tot): ?>
            <p style="font-family:var(--f-head);font-size:1.6rem;margin:0"><?= gb((float)$lib) ?> liberi</p>
            <p class="meta">su <?= gb((float)$tot) ?> in tutto, <?= gb((float)$usato) ?> occupati
               (<?= $tot ? round($usato / $tot * 100) : 0 ?>%).</p>
            <p class="meta">Trenta ore di lezione a 480p pesano circa 11 GB.
               <?= ($lib > 13 * 1073741824) ? 'Ci stanno.' : 'Non ci stanno: servono altre strade per i video.' ?></p>
        <?php else: ?>
            <p class="meta">Il server non dichiara lo spazio del disco. Il numero sta nel pannello dell&rsquo;hosting.</p>
        <?php endif; ?>
    </div>

    <h2 class="sect">Dove va lo spazio</h2>
    <div class="card">
    <?php foreach ($cartelle as $nome => $dir): ?>
        <?php [$b, $n] = peso($dir); ?>
        <div class="card-row" style="padding:.55rem 0;border-top:1px solid var(--surface)">
            <span class="grow"><?= htmlspecialchars($nome) ?><div class="meta"><?= $n ?> file</div></span>
            <span class="badge"><?= gb((float)$b) ?></span>
        </div>
    <?php endforeach; ?>
    </div>

    <h2 class="sect">Com&rsquo;&egrave; messo PHP</h2>
    <div class="card">
    <?php foreach ($impostazioni as $voce => $valore): ?>
        <div class="card-row" style="padding:.5rem 0;border-top:1px solid var(--surface)">
            <span class="grow"><?= htmlspecialchars($voce) ?></span>
            <span class="badge"><?= htmlspecialchars((string)$valore) ?></span>
        </div>
    <?php endforeach; ?>
    </div>

    <h2 class="sect">Estensioni</h2>
    <p class="meta" style="margin-top:-.5rem">Quelle che chiedono i programmi di corsi da installare, tipo Chamilo o Moodle.</p>
    <div class="card">
        <p style="display:flex;flex-wrap:wrap;gap:.4rem;margin:0">
        <?php foreach ($estensioni as $e): ?>
            <span class="badge<?= extension_loaded($e) ? ' oro' : '' ?>" style="opacity:<?= extension_loaded($e) ? '1' : '.45' ?>">
                <?= htmlspecialchars($e) ?><?= extension_loaded($e) ? '' : ' &#10007;' ?>
            </span>
        <?php endforeach; ?>
        </p>
    </div>
</div>
<?php corsoHtmlFoot(); ?>
