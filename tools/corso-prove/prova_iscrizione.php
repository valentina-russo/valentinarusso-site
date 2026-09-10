<?php
/**
 * Prova del percorso "pagamento -> accesso", contro un MySQL locale.
 * Non tocca il server: usa il config di prova in grav-site/root/hd-db-config.php.
 */
declare(strict_types=1);
$R = 'D:/valentinarussomentaladvisor.it/grav-site/root';
require_once $R . '/corso/lib.php';
require_once $R . '/corso-dati/config.php';

function ok(string $t, bool $c): void { echo ($c ? '  ok   ' : '  FALLITO ') . $t . "\n"; if (!$c) { $GLOBALS['ko'] = true; } }
$ko = false;
$db = hdDb();

echo "1. Le tabelle nuove nascono da se'\n";
corsoEnsureIscrizioneSchema();
$tab = array_column($db->query('SHOW TABLES')->fetchAll(PDO::FETCH_NUM), 0);
ok('cohort_products creata', in_array('cohort_products', $tab, true));
ok('course_payments creata', in_array('course_payments', $tab, true));
ok('richiamarla due volte non da errore', (function () { corsoEnsureIscrizioneSchema(); return true; })());

echo "\n2. Mappatura prodotto -> classe\n";
corsoImpostaProdottiClasse(1, ['bg5-foundation', 'bg5-foundation-s1']);
corsoImpostaProdottiClasse(2, ['bg5-foundation', 'bg5-foundation-s2']);
ok('la classe 1 serve due prodotti', count(corsoProdottiDiClasse(1)) === 2);
ok('il percorso completo mappa su due classi', count(corsoClassiPerProdotto('bg5-foundation')) === 2);
ok('il semestre 1 mappa su una classe', count(corsoClassiPerProdotto('bg5-foundation-s1')) === 1);
ok('un prodotto non mappato da zero classi', count(corsoClassiPerProdotto('inesistente')) === 0);

echo "\n3. Primo acquisto: account nuovo, link di attivazione\n";
$r = corsoIscriviDaPagamento('cs_test_uno', 'Allieva@Esempio.IT', 'Anna', 'bg5-foundation');
ok('esito "account creato"', $r['esito'] === 'account creato');
ok('token consegnato', is_string($r['token']) && strlen($r['token']) === 64);
ok('iscritta a due classi', count($r['classi']) === 2);
$u = $db->query("SELECT * FROM hd_users WHERE email = 'allieva@esempio.it'")->fetch();
ok('email normalizzata in minuscolo', (bool)$u);
ok('ruolo student, non admin', $u['role'] === 'student');
ok('a riposo c e l impronta, non il token', $u['reset_token'] === hash('sha256', $r['token']));
ok('il token in chiaro non e nel database', $u['reset_token'] !== $r['token']);
ok('scadenza impostata', $u['reset_expires'] !== null);
ok('non ancora verificata', $u['verified_at'] === null);
$n = (int)$db->query('SELECT COUNT(*) FROM course_enrollments')->fetchColumn();
ok('due righe in course_enrollments', $n === 2);

echo "\n4. La stessa sessione di pagamento non si spende due volte\n";
$r2 = corsoIscriviDaPagamento('cs_test_uno', 'altra@esempio.it', 'Altra', 'bg5-foundation');
ok('rifiutata', $r2['esito'] === 'sessione riusata');
ok('nessun account creato per l altra', !$db->query("SELECT 1 FROM hd_users WHERE email='altra@esempio.it'")->fetchColumn());

echo "\n5. Chi ha gia un account viene solo iscritto\n";
$r3 = corsoIscriviDaPagamento('cs_test_due', 'allieva@esempio.it', 'Anna', 'bg5-foundation-s2');
ok('esito "account esistente"', $r3['esito'] === 'account esistente');
ok('nessun token nuovo', $r3['token'] === null);
$u2 = $db->query("SELECT * FROM hd_users WHERE email='allieva@esempio.it'")->fetch();
ok('la sua password non e stata toccata', $u2['password_hash'] === $u['password_hash']);
ok('sessioni non invalidate', (int)$u2['session_ver'] === (int)$u['session_ver']);

echo "\n6. Prodotto senza classi collegate: non si indovina\n";
$db->exec("DELETE FROM cohort_products WHERE catalog_key='bg5-foundation-s1'");
$r4 = corsoIscriviDaPagamento('cs_test_tre', 'orfana@esempio.it', 'Orfana', 'bg5-foundation-s1');
ok('segnalato "classe mancante"', $r4['esito'] === 'classe mancante');
ok('con una nota per Valentina', $r4['nota'] !== '');
ok('l account esiste comunque', (bool)$db->query("SELECT 1 FROM hd_users WHERE email='orfana@esempio.it'")->fetchColumn());
ok('ma senza iscrizioni', (int)$db->query("SELECT COUNT(*) FROM course_enrollments e JOIN hd_users u ON u.id=e.user_id WHERE u.email='orfana@esempio.it'")->fetchColumn() === 0);

echo "\n7. Un indirizzo di amministratore non viene toccato\n";
$db->exec("INSERT INTO hd_users (email, password_hash, name, role) VALUES ('capo@esempio.it', 'x', 'Capo', 'admin')");
$r5 = corsoIscriviDaPagamento('cs_test_quattro', 'capo@esempio.it', 'Capo', 'bg5-foundation');
ok('rifiutato', $r5['esito'] === 'amministratore');
ok('hash intatto', $db->query("SELECT password_hash FROM hd_users WHERE email='capo@esempio.it'")->fetchColumn() === 'x');

echo "\n8. Attivazione\n";
ok('token buono trovato', (bool)corsoUtenteDaTokenAttivazione($r['token']));
ok('token inventato rifiutato', corsoUtenteDaTokenAttivazione(str_repeat('a', 64)) === null);
ok('token di lunghezza sbagliata rifiutato', corsoUtenteDaTokenAttivazione('abc') === null);
ok('token non esadecimale rifiutato', corsoUtenteDaTokenAttivazione(str_repeat('z', 64)) === null);
corsoAttivaAccount((int)$u['id'], 'UnaPasswordLunga2026!');
$u3 = $db->query("SELECT * FROM hd_users WHERE email='allieva@esempio.it'")->fetch();
ok('la password scelta funziona', hdVerifyPassword('UnaPasswordLunga2026!', $u3['password_hash']));
ok('token speso', $u3['reset_token'] === null && $u3['reset_expires'] === null);
ok('ora e verificata', $u3['verified_at'] !== null);
ok('sessioni vecchie invalidate', (int)$u3['session_ver'] > (int)$u['session_ver']);
ok('il token non vale piu', corsoUtenteDaTokenAttivazione($r['token']) === null);

echo "\n9. Token scaduto\n";
$db->exec("UPDATE hd_users SET reset_token = '" . hash('sha256', str_repeat('b', 64)) . "', reset_expires = DATE_SUB(NOW(), INTERVAL 1 DAY) WHERE email='orfana@esempio.it'");
ok('un token scaduto non apre niente', corsoUtenteDaTokenAttivazione(str_repeat('b', 64)) === null);

echo "\n" . ($GLOBALS['ko'] ?? false ? 'CI SONO FALLIMENTI' : 'tutto verde') . "\n";
