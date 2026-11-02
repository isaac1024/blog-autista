<?php

declare(strict_types=1);

/*
 * Router for PHP's built-in web server used by tools/ui-check.mjs.
 *
 * Serves existing files from public/ as-is (static CSS, images, ...) and
 * delegates every other request to Symfony's front controller.
 */
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', \PHP_URL_PATH);
$path = \is_string($path) && '' !== $path ? $path : '/';
$file = __DIR__.'/../public'.$path;

if ('/' !== $path && is_file($file)) {
    return false;
}

require __DIR__.'/../public/index.php';
