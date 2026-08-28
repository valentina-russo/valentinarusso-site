<?php
declare(strict_types=1);
require_once __DIR__ . '/lib.php';
// R23: riusa catalogo e checkout Stripe gia esistenti sul sito principale,
// nessuna logica di pagamento duplicata qui
require_once __DIR__ . '/../letture-dati/config.php';

$user = corsoRequireStudent();

corsoHtmlHead('Prenota una lettura');
corsoNav($user, corsoIsAdmin((int)$user['id']), 'letture');
?>
<div class="wrap">
    <p class="eyebrow">Sessioni individuali</p>
    <h1 class="hero">Prenota una lettura</h1>
    <p class="hero-sub">Scegli la lettura, completa il pagamento e Valentina ti scrive entro 48 ore per fissare insieme data e ora.</p>

    <?php foreach (LETTURE_CATALOG as $key => $product): ?>
        <div class="card">
            <h3><?= htmlspecialchars($product['name']) ?></h3>
            <p style="margin:.35rem 0 .75rem"><?= htmlspecialchars($product['description']) ?></p>
            <p class="meta" style="margin:0 0 1rem">
                <span class="badge"><?= htmlspecialchars($product['duration']) ?></span>
                <span class="badge oro">€<?= number_format($product['amount'] / 100, 0, ',', '.') ?></span>
            </p>
            <a class="btn" href="/letture-dati/checkout.php?reading=<?= urlencode($key) ?>">Prenota</a>
        </div>
    <?php endforeach; ?>
</div>
<?php corsoHtmlFoot(); ?>
