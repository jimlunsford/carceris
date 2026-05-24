<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$errors = [];

$expectedDuplicates = [
    'assets/css/app.css' => 'public/assets/css/app.css',
    'assets/js/app.js' => 'public/assets/js/app.js',
    'assets/js/print.js' => 'public/assets/js/print.js',
    'robots.txt' => 'public/robots.txt',
];

foreach ($expectedDuplicates as $source => $copy) {
    $sourcePath = $root . '/' . $source;
    $copyPath = $root . '/' . $copy;

    if (!is_file($sourcePath) || !is_file($copyPath)) {
        $errors[] = 'Missing duplicated deployment asset: ' . $source . ' or ' . $copy;
        continue;
    }

    if (hash_file('sha256', $sourcePath) !== hash_file('sha256', $copyPath)) {
        $errors[] = 'Duplicated deployment asset drift: ' . $source . ' differs from ' . $copy;
    }
}

$manifestPath = $root . '/RELEASE_MANIFEST.json';

if (!is_file($manifestPath)) {
    $errors[] = 'Missing RELEASE_MANIFEST.json.';
} else {
    $manifest = json_decode((string) file_get_contents($manifestPath), true);

    if (!is_array($manifest)) {
        $errors[] = 'RELEASE_MANIFEST.json is invalid JSON.';
    } else {
        $manifestPaths = [];

        foreach (($manifest['files'] ?? []) as $file) {
            if (!is_array($file)) {
                $errors[] = 'Manifest contains an invalid file entry.';
                continue;
            }

            $path = str_replace('\\', '/', (string) ($file['path'] ?? ''));

            if ($path === '' || $path === 'RELEASE_MANIFEST.json') {
                continue;
            }

            if (str_starts_with($path, '/') || preg_match('#(^|/)\.\.(/|$)#', $path)) {
                $errors[] = 'Manifest contains an unsafe file path: ' . $path;
                continue;
            }

            if (isset($manifestPaths[$path])) {
                $errors[] = 'Manifest contains a duplicate file entry: ' . $path;
                continue;
            }

            $manifestPaths[$path] = true;
            $fullPath = $root . '/' . $path;

            if (!is_file($fullPath)) {
                $errors[] = 'Manifest lists a missing file: ' . $path;
                continue;
            }

            $actual = hash_file('sha256', $fullPath);

            if ($actual !== ($file['sha256'] ?? null)) {
                $errors[] = 'Manifest hash mismatch: ' . $path;
            }
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $item) {
            if (!$item->isFile()) {
                continue;
            }

            $relative = str_replace('\\', '/', substr($item->getPathname(), strlen($root) + 1));

            if ($relative === 'RELEASE_MANIFEST.json') {
                continue;
            }

            if (!isset($manifestPaths[$relative])) {
                $errors[] = 'Project file is missing from release manifest: ' . $relative;
            }
        }
    }
}

if ($errors) {
    echo "Release audit failed:" . PHP_EOL;
    foreach ($errors as $error) {
        echo '- ' . $error . PHP_EOL;
    }
    exit(1);
}

echo "Release audit passed." . PHP_EOL;
