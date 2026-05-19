<?php

declare(strict_types=1);

require __DIR__ . '/../app/bootstrap.php';

$user = current_user();

if (!$user) {
    redirect('/login.php');
}

if (request_method() === 'POST') {
    csrf_require();

    logout_user();

    redirect('/login.php');
}

$pageTitle = 'Logout | Carceris';

require __DIR__ . '/../app/views/logout.php';
