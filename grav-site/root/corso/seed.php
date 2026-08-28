<?php
/**
 * Corso Base Human Design - Seed dati DIMOSTRATIVI (one-time, self-deletes)
 * Access: https://valentinarussobg5.com/corso/seed.php?token=corso_seed_2026_d3m0x7
 *
 * Crea 4 corsi finti con classi, corsiste demo e compiti nel forum, per
 * poter provare la piattaforma con contenuti realistici prima del lancio.
 * Tutte le email demo finiscono con @demo.local cosi sono riconoscibili
 * e cancellabili in blocco (vedi seed.php?token=...&pulisci=1).
 *
 * NON usare in presenza di corsiste reali gia iscritte.
 */

header('Content-Type: application/json; charset=utf-8');

if (!hash_equals('corso_seed_2026_d3m0x7', $_GET['token'] ?? '')) {
    http_response_code(403);
    die(json_encode(['ok' => false, 'error' => 'Forbidden']));
}

$configPath = __DIR__ . '/../hd-db-config.php';
if (!file_exists($configPath)) {
    http_response_code(500);
    die(json_encode(['ok' => false, 'error' => 'hd-db-config.php mancante']));
}
require_once $configPath;

$log = [];
$errors = [];

// GUID reali di 4 video demo caricati sulla libreria Bunny corso-base-human-design
$V = [
    '17115a0b-e965-4202-9630-e68fb0883d01',
    '98ae4b09-903e-4669-9fa4-b6d8da1fa809',
    '06a01793-b056-40fa-b0f0-1862414f4753',
    'bcf60e05-f42e-4c42-9d8e-58423448a023',
];

$COURSES = [
    [
        'slug'  => 'corso-base-semestre-1',
        'title' => 'Corso Base Human Design · Semestre 1',
        'lessons' => [
            'Introduzione all\'Human Design e al Bodygraph',
            'I 5 Tipi energetici: panoramica',
            'Il Generatore e il Generatore Manifestante',
            'Il Proiettore e la sua Strategia',
            'Il Manifestatore e il Riflettore',
            'La Strategia: come decidere senza forzare',
            'L\'Autorità interiore: le 7 forme',
            'I 9 Centri: definiti e aperti',
            'I temi del Non-Sé da riconoscere',
            'Sintesi del Semestre 1 e pratica guidata',
        ],
    ],
    [
        'slug'  => 'corso-base-semestre-2',
        'title' => 'Corso Base Human Design · Semestre 2',
        'lessons' => [
            'Le Grandi Tematiche del Bodygraph',
            'Il Circuito di Integrazione',
            'I canali 10-20, 20-34, 34-57 e 10-57',
            'Il Circuito della Conoscenza',
            'Il Circuito Logico: condividere in sequenza',
            'Il Circuito Astratto: raccontare per esperienza',
            'Il Circuito Tribale: sostenere chi ti sta vicino',
            'Le 64 Porte, parte prima',
            'Le 64 Porte, parte seconda',
            'Profilo e Linee: le 12 combinazioni',
        ],
    ],
    [
        'slug'  => 'workshop-intelligenza-emozionale',
        'title' => 'Workshop · Intelligenza Emozionale',
        'lessons' => [
            'L\'onda emozionale: come funziona davvero',
            'Autorità emozionale e tempi di decisione',
            'Emozioni proprie ed emozioni assorbite',
            'Pratica: attraversare l\'onda senza agire',
        ],
    ],
    [
        'slug'  => 'human-design-liberi-professionisti',
        'title' => 'Human Design per Liberi Professionisti',
        'lessons' => [
            'Come ti proponi: il tuo Tipo al lavoro',
            'Riconoscere i clienti giusti per il tuo Disegno',
            'Ambiente e ritmo: dove lavori meglio',
            'Costruire un\'offerta coerente con te',
            'Sintesi e piano personale',
        ],
    ],
];

$STUDENTS = [
    ['elena.demo@demo.local',  'Elena Bianchi'],
    ['marta.demo@demo.local',  'Marta Conti'],
    ['giulia.demo@demo.local', 'Giulia Ferrari'],
    ['sara.demo@demo.local',   'Sara Ricci'],
    ['chiara.demo@demo.local', 'Chiara Moretti'],
];

try {
    $pdo = new PDO(
        sprintf('mysql:host=%s;dbname=%s;charset=utf8mb4', HD_DB_HOST, HD_DB_NAME),
        HD_DB_USER, HD_DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
    );

    // ── Pulizia: rimuove SOLO i dati demo ───────────────────────────────────
    if (isset($_GET['pulisci'])) {
        $slugs = array_column($COURSES, 'slug');
        $in = implode(',', array_fill(0, count($slugs), '?'));
        $ids = $pdo->prepare("SELECT id FROM courses WHERE slug IN ($in)");
        $ids->execute($slugs);
        $courseIds = array_column($ids->fetchAll(), 'id');
        if ($courseIds) {
            $cin = implode(',', array_fill(0, count($courseIds), '?'));
            $pdo->prepare("DELETE p FROM forum_posts p JOIN lessons l ON l.id = p.lesson_id WHERE l.course_id IN ($cin)")->execute($courseIds);
            $pdo->prepare("DELETE FROM lessons WHERE course_id IN ($cin)")->execute($courseIds);
            $pdo->prepare("DELETE FROM course_enrollments WHERE course_id IN ($cin)")->execute($courseIds);
            $pdo->prepare("DELETE FROM cohorts WHERE course_id IN ($cin)")->execute($courseIds);
            $pdo->prepare("DELETE FROM courses WHERE id IN ($cin)")->execute($courseIds);
        }
        $pdo->exec("DELETE FROM hd_users WHERE email LIKE '%@demo.local'");
        echo json_encode(['ok' => true, 'log' => ['Dati demo rimossi. Gli account reali non sono stati toccati.']], JSON_PRETTY_PRINT);
        exit;
    }

    // ── Corsiste demo ───────────────────────────────────────────────────────
    $studentIds = [];
    foreach ($STUDENTS as [$email, $name]) {
        $s = $pdo->prepare('SELECT id FROM hd_users WHERE email = ?');
        $s->execute([$email]);
        $row = $s->fetch();
        if ($row) {
            $studentIds[] = (int)$row['id'];
            continue;
        }
        // password uguale per tutte le demo: si accede e si prova subito
        $peppered = hash_hmac('sha256', 'demo2026', HD_PEPPER);
        $hash = defined('PASSWORD_ARGON2ID')
            ? password_hash($peppered, PASSWORD_ARGON2ID, ['memory_cost' => 65536, 'time_cost' => 3, 'threads' => 2])
            : password_hash($peppered, PASSWORD_BCRYPT, ['cost' => 12]);
        $pdo->prepare('INSERT INTO hd_users (email, password_hash, name, role, verified_at, gdpr_consent, gdpr_date) VALUES (?,?,?,?,NOW(),1,NOW())')
            ->execute([$email, $hash, $name, 'student']);
        $studentIds[] = (int)$pdo->lastInsertId();
    }
    $log[] = count($studentIds) . ' corsiste demo pronte (password: demo2026)';

    // ── Corsi, classi (coorti), lezioni ─────────────────────────────────────
    $lessonRefs = [];
    foreach ($COURSES as $ci => $c) {
        $s = $pdo->prepare('SELECT id FROM courses WHERE slug = ?');
        $s->execute([$c['slug']]);
        $row = $s->fetch();
        if ($row) {
            $courseId = (int)$row['id'];
        } else {
            $pdo->prepare('INSERT INTO courses (slug, title) VALUES (?,?)')->execute([$c['slug'], $c['title']]);
            $courseId = (int)$pdo->lastInsertId();
        }

        // il primo corso ha 3 classi (il caso "pieno"), gli altri 1
        $nClassi = $ci === 0 ? 3 : 1;
        for ($k = 1; $k <= $nClassi; $k++) {
            $s = $pdo->prepare('SELECT id FROM cohorts WHERE course_id = ? AND position = ?');
            $s->execute([$courseId, $k]);
            $r = $s->fetch();
            if ($r) {
                $cohortId = (int)$r['id'];
            } else {
                $pdo->prepare('INSERT INTO cohorts (course_id, name, position) VALUES (?,?,?)')
                    ->execute([$courseId, 'Classe ' . $k, $k]);
                $cohortId = (int)$pdo->lastInsertId();
            }

            foreach ($c['lessons'] as $li => $lTitle) {
                $pos = $li + 1;
                $s = $pdo->prepare('SELECT id FROM lessons WHERE cohort_id = ? AND position = ?');
                $s->execute([$cohortId, $pos]);
                if ($s->fetch()) continue;

                // le classi piu avanti nel tempo hanno meno registrazioni caricate
                $limite = max(2, count($c['lessons']) - 2 * $k);
                $guid = $li < $limite ? $V[($ci + $li + $k) % 4] : null;

                $pdo->prepare('INSERT INTO lessons (course_id, cohort_id, position, title, bunny_video_id) VALUES (?,?,?,?,?)')
                    ->execute([$courseId, $cohortId, $pos, $lTitle, $guid]);
                if ($k === 1) $lessonRefs[] = ['id' => (int)$pdo->lastInsertId()];
            }

            // allieve diverse in classi diverse
            $slice = $k === 1 ? array_slice($studentIds, 0, 3)
                   : ($k === 2 ? array_slice($studentIds, 3) : array_slice($studentIds, 1, 2));
            if ($ci > 0) $slice = array_slice($studentIds, 0, max(2, count($studentIds) - $ci));
            foreach ($slice as $sid) {
                $pdo->prepare('INSERT IGNORE INTO course_enrollments (user_id, course_id, cohort_id) VALUES (?,?,?)')
                    ->execute([$sid, $courseId, $cohortId]);
            }
        }
        $log[] = 'Corso "' . $c['title'] . '" pronto con ' . $nClassi . ' ' . ($nClassi === 1 ? 'classe' : 'classi');
    }

    // ── Compiti nel forum ───────────────────────────────────────────────────
    // Alcuni gia con risposta di Valentina, altri no: cosi la vista
    // "Da correggere" mostra dati veri con urgenze diverse.
    $adminId = (int)$pdo->query("SELECT id FROM hd_users WHERE role='admin' ORDER BY id LIMIT 1")->fetchColumn();

    $COMPITI = [
        ['Ho riletto il mio Bodygraph e ho notato che ho il centro della gola definito ma non collegato alla Sacrale. Nella pratica mi accorgo che parlo molto, ma non sempre poi agisco. È coerente con quello che hai spiegato?', 9],
        ['Esercizio della settimana: ho osservato per 5 giorni quando rispondo di getto e quando aspetto. Ho contato 11 volte in cui ho detto sì troppo in fretta, quasi tutte per telefono.', 7],
        ['Domanda sulla Strategia del Proiettore: come faccio a distinguere un invito vero da una richiesta di cortesia? A volte mi sembra che la differenza sia sottile.', 4],
        ['Ho fatto l\'esercizio sull\'onda emozionale. Ho notato che il mio picco arriva sempre la sera tardi e che se aspetto la mattina dopo la decisione cambia quasi sempre.', 3],
        ['Allego le mie osservazioni sui centri aperti. Il plesso solare aperto mi sta facendo capire molte cose sulle relazioni passate.', 1],
        ['Consegna esercizio classe 2: ho mappato i canali definiti e ho provato a riconoscerli nelle situazioni di lavoro di questa settimana.', 0],
    ];

    $n = 0;
    foreach ($COMPITI as $i => [$body, $daysAgo]) {
        if (!isset($lessonRefs[$i * 2])) continue;
        $lid = $lessonRefs[$i * 2]['id'];
        $sid = $studentIds[$i % count($studentIds)];
        $when = date('Y-m-d H:i:s', time() - $daysAgo * 86400 - 3600 * ($i + 1));
        $pdo->prepare('INSERT INTO forum_posts (lesson_id, user_id, body, created_at) VALUES (?,?,?,?)')
            ->execute([$lid, $sid, $body, $when]);
        $n++;

        // Valentina ha gia risposto ai due piu vecchi: restano 4 da correggere
        if ($i < 2 && $adminId) {
            $pdo->prepare('INSERT INTO forum_posts (lesson_id, user_id, body, created_at) VALUES (?,?,?,?)')
                ->execute([$lid, $adminId,
                    'Ottima osservazione. È esattamente il punto: la gola definita ti dà la capacità di esprimere, ma senza collegamento alla Sacrale l\'energia per portare avanti le cose arriva da altrove. Nella prossima lezione lo riprendiamo con esempi.',
                    date('Y-m-d H:i:s', strtotime($when) + 86400)]);
        }
    }
    $log[] = $n . ' compiti creati nel forum (2 gia corretti, il resto in attesa)';

} catch (PDOException $e) {
    $errors[] = 'DB error: ' . $e->getMessage();
}

if (empty($errors)) {
    $log[] = 'Seed completato.';
    $log[] = 'Accesso demo corsista: elena.demo@demo.local / demo2026';
    $log[] = 'Per rimuovere tutto: aggiungi &pulisci=1 all\'URL (lo script resta finche non lo cancelli a mano).';
}

echo json_encode(['ok' => empty($errors), 'log' => $log, 'errors' => $errors], JSON_PRETTY_PRINT);
