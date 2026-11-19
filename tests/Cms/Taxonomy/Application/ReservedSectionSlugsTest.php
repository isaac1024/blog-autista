<?php

declare(strict_types=1);

namespace Cms\Tests\Taxonomy\Application;

use Cms\Taxonomy\Application\TermCreator;
use Cms\Taxonomy\Domain\TermRepository;
use Cms\Tests\Taxonomy\Domain\TermIdMother;
use Shared\Domain\Event\EventBus;
use Shared\Tests\Domain\DomainErrorAssertions;
use Shared\Tests\Domain\RecordingTransaction;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Routing\RouterInterface;

/**
 * A section is served at /{slug}/, with its posts under it: one named as
 * the first part of an address the site serves itself, such as /tag/…,
 * would take it. Each such part, as the router of the site has it now, is a
 * slug no section may have, so a route added there can't be shadowed
 * unnoticed.
 */
final class ReservedSectionSlugsTest extends KernelTestCase
{
    use DomainErrorAssertions;

    public function testNoSectionTakesTheFirstPartOfAnAddressTheSiteServesItself(): void
    {
        $repository = self::createStub(TermRepository::class);
        $parts = self::firstPartsOfTheSite();

        self::assertNotEmpty($parts);

        foreach ($parts as $part) {
            self::assertTells('errors.term.slug.reserved', ['%slug%' => $part], static fn () => new TermCreator(TermIdMother::DEFAULT_VALUE, 'section', ucfirst($part))($repository, self::createStub(EventBus::class), new RecordingTransaction()));
        }
    }

    /**
     * The fixed first parts of the routes of the site that go on past them,
     * as /autor/{slug}.html does.
     *
     * @return list<string>
     */
    private static function firstPartsOfTheSite(): array
    {
        $parts = [];

        foreach (self::getContainer()->get(RouterInterface::class)->getRouteCollection() as $name => $route) {
            if (str_starts_with($name, 'web_') && 1 === preg_match('#^/([a-z0-9-]+)/#', $route->getPath(), $match)) {
                $parts[] = $match[1];
            }
        }

        return array_values(array_unique($parts));
    }
}
