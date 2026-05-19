<?php

declare(strict_types=1);

require __DIR__ . '/../app/bootstrap.php';

if (is_logged_in()) {
    redirect('/index.php');
}

if (request_method() === 'POST') {
    csrf_require();

    $username = post_string('username', 80);
    $password = (string) ($_POST['password'] ?? '');

    if ($username === '' || $password === '') {
        flash_set('error', 'Username and password are required.');
        redirect('/login.php');
    }

    if (login_user($username, $password)) {
        redirect('/index.php');
    }

    flash_set('error', 'Invalid username or password, or the account is temporarily locked due to repeated failed attempts.');
    redirect('/login.php');
}

require __DIR__ . '/../app/views/login.php';
