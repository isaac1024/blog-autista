<?php

declare(strict_types=1);

use Symfony\Component\Dotenv\Dotenv;

require dirname(__DIR__).'/vendor/autoload.php';

// Keep test output clean: Symfony's default logger writes to stderr, and
// Infection aborts the initial test run on the first stderr write.
@mkdir(dirname(__DIR__).'/var/log', 0o777, true);
ini_set('error_log', dirname(__DIR__).'/var/log/test.log');

new Dotenv()->bootEnv(dirname(__DIR__).'/.env');

if ($_SERVER['APP_DEBUG']) {
    umask(0o000);
}
