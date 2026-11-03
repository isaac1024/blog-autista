<?php

declare(strict_types=1);

use Symfony\Component\Dotenv\Dotenv;

require dirname(__DIR__).'/vendor/autoload.php';

// Keep test output clean: Symfony's default logger writes to stderr, and
// Infection aborts the initial test run on the first stderr write.
@mkdir(dirname(__DIR__).'/var/log', 0o777, true);
ini_set('error_log', dirname(__DIR__).'/var/log/test.log');

new Dotenv()->bootEnv(dirname(__DIR__).'/.env');

// Parallel runs give every process a TEST_TOKEN (ParaTest workers and Infection
// threads count from 1) and doctrine.yaml appends it to the database name, so
// each process owns app_test<token>. Create and migrate that database the first
// time it is needed; a marker per token and migration set makes later runs, such
// as every Infection mutant process, skip the work.
(static function (string $projectDir): void {
    $token = (string) ($_SERVER['TEST_TOKEN'] ?? '');
    $migrations = array_map('basename', glob($projectDir.'/src/Shared/Migrations/*.php') ?: []);
    $marker = sprintf(
        '%s/var/test-databases/%s-%s',
        $projectDir,
        '' === $token ? 'default' : $token,
        md5(implode(',', $migrations)),
    );

    if (is_file($marker)) {
        return;
    }

    foreach (['doctrine:database:create --if-not-exists', 'doctrine:migrations:migrate --no-interaction --allow-no-migration'] as $command) {
        exec(sprintf('%s %s %s --env=test --quiet 2>&1', PHP_BINARY, escapeshellarg($projectDir.'/bin/console'), $command), $output, $exitCode);

        if (0 !== $exitCode) {
            throw new RuntimeException(sprintf("Could not prepare the test database for TEST_TOKEN \"%s\":\n%s", $token, implode("\n", $output)));
        }
    }

    @mkdir(dirname($marker), 0o777, true);
    touch($marker);
})(dirname(__DIR__));

if ($_SERVER['APP_DEBUG']) {
    umask(0o000);
}
