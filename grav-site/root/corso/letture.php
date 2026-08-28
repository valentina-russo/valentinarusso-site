<?php
declare(strict_types=1);
require_once __DIR__ . '/lib.php';
// R23: riusa il catalogo e il checkout Stripe gia esistenti sul sito
// principale - nessuna logica di pagamento duplicata qui
require_once __DIR__ . '/../letture-dati/config.php';

$user = corsoRequireStudent();

corsoHtmlHead('Prenota una lettura');
?>
<div class="top-nav">
    <a href="index.php">&larr; I miei corsi</a>
    <a href="logout.php">Esci</a>
</div>
<div class="container">
    <h1>Prenota una lettura</h1>
    <p class="muted">Scegli una lettura e procedi al pagamento. Valentina ti scrivera entro 48 ore per fissare data e ora.</p>

    <?php foreach (LETTURE_CATALOG as $key => $product): ?>
        <div class="card">
            <h3><?= htmlspecialchars($product['name']) ?></h3>
            <p><?= htmlspecialchars($product['description']) ?></p>
            <p class="muted"><?= htmlspecialchars($product['duration']) ?> &middot; €<?= number_format($product['amount'] / 100, 0, ',', '.') ?></p>
            <a class="btn" href="/letture-dati/checkout.php?reading=<?= urlencode($key) ?>">Prenota e paga</a>
        </div>
    <?php endforeach; ?>
</div>
<?php corsoHtmlFoot(); ?>
