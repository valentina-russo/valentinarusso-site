<?php
// VERSION: 2026-03-08-CMS-V3-ULTRA
header('Content-Type: application/json; charset=utf-8');

$dirs = [
    'privati' => 'content/blog-privati',
    'aziende' => 'content/blog-aziende'
];

$allPosts = [];

foreach ($dirs as $category => $dir) {
    if (!is_dir($dir))
        continue;

    $files = scandir($dir);
    foreach ($files as $file) {
        if (pathinfo($file, PATHINFO_EXTENSION) !== 'md')
            continue;

        $filePath = $dir . '/' . $file;
        $content = file_get_contents($filePath);

        // Regex ultra-robusta per il frontmatter
        if (preg_match('/^---\s*(.*?)\n---\s*/s', $content, $matches)) {
            $frontmatter = $matches[1];
            $lines = explode("\n", str_replace("\r", "", $frontmatter));
            $metadata = [];
            foreach ($lines as $line) {
                if (trim($line) === '')
                    continue;
                $parts = explode(':', $line, 2);
                if (count($parts) === 2) {
                    $key = trim($parts[0]);
                    $val = trim(trim($parts[1]), '"\' ');
                    $metadata[$key] = $val;
                }
            }
        }

        // Logica Scheduling: Solo articoli con data passata (o presente)
        $postDateStr = $metadata['date'] ?? date('c', filemtime($filePath));
        $postTimestamp = strtotime($postDateStr);
        $now = time();

        $status = isset($metadata['status']) ? strtolower($metadata['status']) : 'draft';
        $isPublishedStatus = ($status === 'published' || $status === 'scheduled');

        // Se è pubblicato e la data è passata, lo includiamo
        if ($isPublishedStatus && $postTimestamp <= $now) {
            $id = pathinfo($file, PATHINFO_FILENAME);
            $allPosts[] = [
                'id' => $id,
                'category' => $category,
                'title' => $metadata['title'] ?? 'Senza Titolo',
                'date' => $postDateStr,
                'image' => $metadata['featured_image'] ?? '/assets/placeholder.jpg',
                'image_alt' => $metadata['image_alt'] ?? ($metadata['title'] ?? ''),
                'description' => $metadata['description'] ?? '',
                'author' => $metadata['author'] ?? 'Valentina Russo',
                'tags' => isset($metadata['tags']) ? array_map('trim', explode(',', $metadata['tags'])) : [],
                'url' => "/articolo.html?id=$id&category=$category",
                'seo' => [
                    'title' => $metadata['seo_title'] ?? '',
                    'desc' => $metadata['seo_desc'] ?? ''
                ],
                'geo' => $metadata['geo_location'] ?? '',
                'aeo' => $metadata['aeo_answer'] ?? '',
                'faq' => isset($metadata['faq']) ? (is_array($metadata['faq']) ? $metadata['faq'] : json_decode($metadata['faq'], true)) : []
            ];
        }
    }
}

// Ordina per data decrescente
usort($allPosts, function ($a, $b) {
    return strtotime($b['date']) - strtotime($a['date']);
});

// Filtro per categoria se richiesto
$filter = $_GET['category'] ?? null;
if ($filter) {
    $allPosts = array_values(array_filter($allPosts, function ($p) use ($filter) {
        return $p['category'] === $filter;
    }));
}

echo json_encode($allPosts);
?>