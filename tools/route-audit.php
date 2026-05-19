<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$routes = array_merge(
    glob($root . '/public/*.php') ?: [],
    glob($root . '/public/admin/*.php') ?: []
);

sort($routes, SORT_NATURAL | SORT_FLAG_CASE);

foreach ($routes as $route) {
    $source = file_get_contents($route) ?: '';
    $relative = str_replace($root . '/', '', $route);

    $requiresLogin = str_contains($source, 'require_login()') ? 'yes' : 'no';
    $hasPermission = str_contains($source, 'user_can($user') ? 'yes' : 'no';
    $hasPostHandler = str_contains($source, "request_method() === 'POST'") ? 'yes' : 'no';
    $postOnly = str_contains($source, "request_method() !== 'POST'") ? 'yes' : 'no';
    $hasCsrf = str_contains($source, 'csrf_require()') ? 'yes' : 'no';
    $isDownload = str_contains($source, 'Content-Disposition') ? 'yes' : 'no';

    echo $relative
        . ' | login=' . $requiresLogin
        . ' | permission=' . $hasPermission
        . ' | post_handler=' . $hasPostHandler
        . ' | post_only=' . $postOnly
        . ' | csrf=' . $hasCsrf
        . ' | download=' . $isDownload
        . PHP_EOL;
}
