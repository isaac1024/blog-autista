<?php

declare(strict_types=1);

namespace Cms\Tests\Architecture;

use PHPat\Selector\Selector;
use PHPat\Test\Builder\Rule;
use PHPat\Test\PHPat;

/**
 * Enforces the dependency direction of the hexagonal architecture: dependencies
 * point inwards (Infrastructure -> Application -> Domain) and the inner layers
 * stay framework-agnostic.
 */
final class HexagonalArchitectureRules
{
    public function testDomainDoesNotDependOnOuterLayersOrTheFramework(): Rule
    {
        return PHPat::rule()
            ->classes(Selector::inNamespace('Cms\Authentication\Domain'))
            ->shouldNot()
            ->dependOn()
            ->classes(
                Selector::inNamespace('Symfony'),
                Selector::inNamespace('Cms\Authentication\Application'),
                Selector::inNamespace('Cms\Authentication\Infrastructure'),
            )
            ->because('the domain is the innermost layer and must stay framework-agnostic.');
    }

    public function testApplicationDoesNotDependOnInfrastructureOrTheFramework(): Rule
    {
        return PHPat::rule()
            ->classes(Selector::inNamespace('Cms\Authentication\Application'))
            ->shouldNot()
            ->dependOn()
            ->classes(
                Selector::inNamespace('Symfony'),
                Selector::inNamespace('Cms\Authentication\Infrastructure'),
            )
            ->because('use cases may only depend on the domain ports they orchestrate.');
    }
}
