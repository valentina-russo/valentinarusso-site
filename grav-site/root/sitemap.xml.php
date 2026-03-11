<?php
declare(strict_types=1);

header('Content-Type: application/xml; charset=utf-8');
header('Cache-Control: public, max-age=3600');

$base_url  = 'https://valentinarussobg5.com';
$pages_dir = realpath(__DIR__ . '/user/pages');

if (!$pages_dir || !is_dir($pages_dir)) {
    echo '<?xml version="1.0" encoding="UTF-8"?><urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"></urlset>';
    exit;
}

function sitemap_strip_prefix(string $name): string {
    return preg_replace('/^\d+\./', '', $name);
}

function sitemap_parse_frontmatter(string $content): array {
    $fm = [];
    if (preg_match('/^---\s*\n(.*?)\n---/s', $content, $m)) {
        foreach (explode("\n", $m[1]) as $line) {
            if (preg_match('/^(\w+):\s*(.+)$/', trim($line), $lm)) {
                $fm[$lm[1]] = trim($lm[2], '"\'');
            }
        }
    }
    return $fm;
}

function sitemap_collect(string $dir, string $base_dir): array {
    $pages = [];
    $items = @scandir($dir);
    if (!$items) return $pages;

    foreach ($items as $item) {
        if ($item[0] === '.') continue;
        $path = $dir . DIRECTORY_SEPARATOR . $item;
        if (!is_dir($path)) continue;

        $md_files = glob($path . '/*.md');
        if ($md_files) {
            $content = @file_get_contents($md_files[0]);
            if ($content !== false) {
                $fm = sitemap_parse_frontmatter($content);
                $skip_vals = ['false', '0', 'no'];
                $published = !isset($fm['published']) || !in_array(strtolower((string)$fm['published']), $skip_vals, true);
                $routable  = !isset($fm['routable'])  || !in_array(strtolower((string)$fm['routable']),  $skip_vals, true);

                if ($published && $routable) {
                    $rel   = str_replace($base_dir . DIRECTORY_SEPARATOR, '', $path);
                    $parts = explode(DIRECTORY_SEPARATOR, $rel);
                    $slug  = '/' . implode('/', array_map('sitemap_strip_prefix', $parts));
                    if ($slug === '/home') $slug = '/';

                    $pages[] = [
                        'url'     => $slug,
                        'lastmod' => date('Y-m-d', (int)filemtime($md_files[0])),
                    ];
                }
            }
        }

        $pages = array_merge($pages, sitemap_collect($path, $base_dir));
    }

    return $pages;
}

$pages = sitemap_collect($pages_dir, $pages_dir);

// Deduplication e ordinamento
$seen = [];
$unique = [];
foreach ($pages as $p) {
    if (!isset($seen[$p['url']])) {
        $seen[$p['url']] = true;
        $unique[] = $p;
    }
}
usort($unique, fn($a, $b) => strcmp($a['url'], $b['url']));

echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
foreach ($unique as $p) {
    echo "  <url>\n";
    echo "    <loc>" . htmlspecialchars($base_url . $p['url'], ENT_XML1 | ENT_QUOTES) . "</loc>\n";
    echo "    <lastmod>" . $p['lastmod'] . "</lastmod>\n";
    echo "    <changefreq>weekly</changefreq>\n";
    $priority = ($p['url'] === '/') ? '1.0' : (substr_count($p['url'], '/') === 1 ? '0.8' : '0.6');
    echo "    <priority>" . $priority . "</priority>\n";
    echo "  </url>\n";
}
echo '</urlset>';
