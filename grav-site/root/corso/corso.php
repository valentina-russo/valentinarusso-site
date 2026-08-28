<?php
// I corsi ora sono organizzati in classi: vecchi link ?slug=... reindirizzati
declare(strict_types=1);
require_once __DIR__ . '/lib.php';
$user = corsoRequireStudent();
$slug = $_GET['slug'] ?? '';
$st = hdDb()->prepare('SELECT co.id FROM cohorts co JOIN courses c ON c.id = co.course_id
                       JOIN course_enrollments e ON e.cohort_id = co.id
                       WHERE c.slug = ? AND e.user_id = ? AND co.archived_at IS NULL
                       ORDER BY co.position LIMIT 1');
$st->execute([$slug, $user['id']]);
$cid = $st->fetchColumn();
header('Location: ' . ($cid ? 'classe.php?id=' . (int)$cid : 'index.php'));
exit;
