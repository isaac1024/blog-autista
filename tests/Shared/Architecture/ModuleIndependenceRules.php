<?php

declare(strict_types=1);

namespace Shared\Tests\Architecture;

use PHPat\Selector\Selector;
use PHPat\Test\Builder\Rule;
use PHPat\Test\PHPat;

/**
 * The infrastructure of a module never reaches into that of another module
 * of its context: what several of them use, such as the layout of the
 * pages, the resolution of what a content links to or the confirmation of
 * publishing, lives in the Shared module of the context. Only their
 * application and domain layers are composed across modules, as the pages
 * of the site and of the CMS do.
 */
final class ModuleIndependenceRules
{
    /**
     * One rule per module of each context, Shared included, so a new module
     * is held to it as soon as it exists.
     *
     * @return iterable<Rule>
     */
    public function testInfrastructureDoesNotDependOnThatOfAnotherModule(): iterable
    {
        foreach (['Cms', 'Web'] as $context) {
            foreach (self::modules($context) as $module) {
                yield PHPat::rule()
                    ->classes(Selector::inNamespace(\sprintf('/^%s\\\\%s\\\\Infrastructure(\\\\|$)/', $context, $module), true))
                    ->shouldNot()
                    ->dependOn()
                    ->classes(Selector::inNamespace(\sprintf('/^%s\\\\(?!%s\\\\|Shared\\\\)[A-Za-z]+\\\\Infrastructure(\\\\|$)/', $context, $module), true))
                    ->because(\sprintf('what several modules of %s use belongs to %s\Shared, not to one of them.', $context, $context));
            }
        }
    }

    /**
     * @return list<string>
     */
    private static function modules(string $context): array
    {
        $directories = glob(\dirname(__DIR__, 3).'/src/'.$context.'/*', \GLOB_ONLYDIR);
        $modules = false === $directories ? [] : array_map(basename(...), $directories);
        sort($modules);

        return $modules;
    }
}
