<?php

declare(strict_types=1);

use Shared\Infrastructure\Ui\WebLayout;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

/*
 * Each context is served from its own host.
 *
 * Symfony forbids %env()% placeholders in routing configuration (see
 * Router::resolveParameters()), so the hosts are read from the process
 * environment here instead. "development" defaults live in .env; production
 * overrides them with real environment variables. Routes are cached, so clear
 * the cache after changing them.
 */
return static function (RoutingConfigurator $routes): void {
    $host = static function (string $name): string {
        $value = $_SERVER[$name] ?? $_ENV[$name] ?? getenv($name);

        if (!\is_string($value) || '' === $value) {
            throw new RuntimeException(\sprintf('%s must be set and not empty.', $name));
        }

        return $value;
    };

    // The public site is only in Spanish, whatever language the browser asks for.
    $routes->import(['path' => '../src/Web/', 'namespace' => 'Web'], 'attribute')
        ->host($host('WEB_HOST'))
        ->defaults(['_locale' => WebLayout::LOCALE]);

    $routes->import(['path' => '../src/Cms/', 'namespace' => 'Cms'], 'attribute')
        ->host($host('CMS_HOST'));
};
