<?php

declare(strict_types=1);

namespace Shared\Tests\Architecture;

use PHPat\Selector\Selector;
use PHPat\Test\Builder\Rule;
use PHPat\Test\PHPat;

final class ContextIndependenceRules
{
    public function testWebDoesNotDependOnCms(): Rule
    {
        return PHPat::rule()
            ->classes(Selector::inNamespace('Web'))
            ->shouldNot()
            ->dependOn()
            ->classes(Selector::inNamespace('Cms'))
            ->because('Web and Cms are independent contexts; share code through Shared.');
    }

    public function testCmsDoesNotDependOnWeb(): Rule
    {
        return PHPat::rule()
            ->classes(Selector::inNamespace('Cms'))
            ->shouldNot()
            ->dependOn()
            ->classes(Selector::inNamespace('Web'))
            ->because('Cms and Web are independent contexts; share code through Shared.');
    }

    public function testSharedDoesNotDependOnAnyContext(): Rule
    {
        return PHPat::rule()
            ->classes(Selector::inNamespace('Shared'))
            ->shouldNot()
            ->dependOn()
            ->classes(
                Selector::inNamespace('Web'),
                Selector::inNamespace('Cms'),
            )
            ->because('Shared is the lowest layer and must not know about its consumers.');
    }
}
