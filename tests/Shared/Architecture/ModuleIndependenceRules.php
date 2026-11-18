<?php

declare(strict_types=1);

namespace Shared\Tests\Architecture;

use PHPat\Selector\Selector;
use PHPat\Selector\SelectorInterface;
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
     * What a module of the CMS offers the others in its domain: the resources
     * redirects lead to, which every module that publishes tells about, with
     * what an editor decides of the redirects that lead to one taken off the
     * site, and the kinds of resource relations count the uses of. Named as
     * text: this test belongs to no context.
     */
    private const array CONTRACTS = [
        'Cms\\Redirect\\Domain\\SiteResources',
        'Cms\\Redirect\\Domain\\Resource',
        'Cms\\Redirect\\Domain\\ResourceType',
        'Cms\\Redirect\\Domain\\RedirectId',
        'Cms\\Redirect\\Domain\\InvalidRedirect',
        'Cms\\Redirect\\Domain\\IncomingDecision',
        'Cms\\Relation\\Domain\\ResourceType',
    ];

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
     * The infrastructure of a module composes others through their
     * application, their finders and responses, never through their domain,
     * whose rules are theirs: it reaches a domain of another module only for
     * its ports, such as its repository, which its use cases take, and for
     * the contracts a module offers the others, documented in AGENTS.md.
     *
     * @return iterable<Rule>
     */
    public function testInfrastructureDoesNotDependOnTheDomainOfAnotherModule(): iterable
    {
        foreach (['Cms', 'Web'] as $context) {
            foreach (self::modules($context) as $module) {
                yield PHPat::rule()
                    ->classes(Selector::inNamespace(\sprintf('/^%s\\\\%s\\\\Infrastructure(\\\\|$)/', $context, $module), true))
                    ->shouldNot()
                    ->dependOn()
                    ->classes(Selector::inNamespace(\sprintf('/^%s\\\\(?!%s\\\\|Shared\\\\)[A-Za-z]+\\\\Domain(\\\\|$)/', $context, $module), true))
                    ->excluding(
                        Selector::isInterface(),
                        ...array_map(static fn (string $contract): SelectorInterface => Selector::classname($contract), self::CONTRACTS),
                    )
                    ->because(\sprintf('a module of %s reaches another through its application, never its domain, but for its ports and its contracts.', $context));
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
