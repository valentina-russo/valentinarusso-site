<?php
header('Content-Type: text/plain');

$old_email = 'valentinebers@gmail.com';
$new_email = 'info@valentinarussobg5.com';
$base_dir = __DIR__ . '/user/pages';

echo "FORCING EMAIL UPDATE IN PAGES...\n";

if (!is_dir($base_dir)) {
    echo "ERROR: Directory $base_dir not found.\n";
    exit;
}

$it = new RecursiveDirectoryIterator($base_dir);
$count = 0;
foreach (new RecursiveIteratorIterator($it) as $file) {
    if ($file->isDir())
        continue;
    if ($file->getExtension() !== 'md')
        continue;

    $path = $file->getPathname();
    $content = file_get_contents($path);

    if (strpos($content, $old_email) !== false) {
        $new_content = str_replace($old_email, $new_email, $content);
        file_put_contents($path, $new_content);
        echo "FIXED: $path\n";
        $count++;
    }
}

echo "\nDone. Updated $count files.\n";
echo "Please delete this script after use.\n";
?>