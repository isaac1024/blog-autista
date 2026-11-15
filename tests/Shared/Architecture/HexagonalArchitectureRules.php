<?php

declare(strict_types=1);

namespace Shared\Tests\Architecture;

use PHPat\Selector\Selector;
use PHPat\Selector\SelectorInterface;
use PHPat\Test\Builder\Rule;
use PHPat\Test\PHPat;

/**
 * Enforces the dependency direction of the hexagonal architecture in every
 * module of both contexts: dependencies point inwards (Infrastructure ->
 * Application -> Domain) and the inner layers stay framework-agnostic. The
 * Doctrine mapping attributes are the one piece of a library the domain
 * carries; Shared's own domain wraps the libraries for the rest.
 */
final class HexagonalArchitectureRules
{
    private const string DOMAIN = '/^(Cms|Web)\\\\[A-Za-z]+\\\\Domain(\\\\|$)/';
    private const string APPLICATION = '/^(Cms|Web)\\\\[A-Za-z]+\\\\Application(\\\\|$)/';
    private const string INFRASTRUCTURE = '/^((Cms|Web)\\\\[A-Za-z]+|Shared)\\\\Infrastructure(\\\\|$)/';

    public function testDomainDoesNotDependOnOuterLayersOrTheFramework(): Rule
    {
        return PHPat::rule()
            ->classes(Selector::inNamespace(self::DOMAIN, true))
            ->shouldNot()
            ->dependOn()
            ->classes(
                Selector::inNamespace('Symfony'),
                self::doctrineButItsMapping(),
                Selector::inNamespace(self::APPLICATION, true),
                Selector::inNamespace(self::INFRASTRUCTURE, true),
            )
            ->because('the domain is the innermost layer and must stay framework-agnostic.');
    }

    public function testApplicationDoesNotDependOnInfrastructureOrTheFramework(): Rule
    {
        return PHPat::rule()
            ->classes(Selector::inNamespace(self::APPLICATION, true))
            ->shouldNot()
            ->dependOn()
            ->classes(
                Selector::inNamespace('Symfony'),
                Selector::inNamespace('Doctrine'),
                Selector::inNamespace(self::INFRASTRUCTURE, true),
            )
            ->because('use cases may only depend on the domain ports they orchestrate.');
    }

    private static function doctrineButItsMapping(): SelectorInterface
    {
        return Selector::AllOf(
            Selector::inNamespace('Doctrine'),
            Selector::Not(Selector::inNamespace('Doctrine\ORM\Mapping')),
        );
    }
}
