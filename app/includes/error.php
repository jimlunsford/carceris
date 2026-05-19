<?php

declare(strict_types=1);

function carceris_debug_enabled_from_config(): bool
{
    global $config;

    return (bool) ($config['app']['debug'] ?? false);
}

function carceris_environment_from_config(): string
{
    global $config;

    $mode = strtolower((string) ($config['app']['environment_mode'] ?? 'testing'));

    return in_array($mode, ['testing', 'internal', 'production'], true) ? $mode : 'testing';
}

function carceris_configure_error_display(): void
{
    $debug = carceris_debug_enabled_from_config();
    $environment = carceris_environment_from_config();

    if ($debug && $environment !== 'production') {
        ini_set('display_errors', '1');
        error_reporting(E_ALL);
        return;
    }

    ini_set('display_errors', '0');
    error_reporting(E_ALL);
}

function carceris_register_exception_handler(): void
{
    set_exception_handler(function (Throwable $exception): void {
        error_log('Carceris uncaught exception: ' . $exception->getMessage() . ' in ' . $exception->getFile() . ':' . $exception->getLine());

        if (!headers_sent()) {
            http_response_code(500);
            header('Content-Type: text/plain; charset=utf-8');
        }

        if (carceris_debug_enabled_from_config() && carceris_environment_from_config() !== 'production') {
            echo "Carceris error:\n";
            echo $exception->getMessage() . "\n";
            echo $exception->getFile() . ':' . $exception->getLine() . "\n";
            return;
        }

        echo "Carceris encountered a server error. Contact the system administrator.";
    });
}

carceris_configure_error_display();
carceris_register_exception_handler();
