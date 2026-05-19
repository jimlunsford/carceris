<?php

declare(strict_types=1);

require __DIR__ . '/../app/bootstrap.php';

$licensePath = carceris_project_root() . '/LICENSE';
$licenseText = is_file($licensePath) ? file_get_contents($licensePath) : 'License file not found.';

header('Content-Type: text/plain; charset=utf-8');
echo $licenseText;
