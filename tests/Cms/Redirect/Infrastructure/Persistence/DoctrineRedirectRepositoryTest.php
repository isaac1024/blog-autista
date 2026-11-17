<?php

declare(strict_types=1);

namespace Cms\Tests\Redirect\Infrastructure\Persistence;

use Cms\Redirect\Domain\InvalidRedirect;
use Cms\Redirect\Domain\Redirect;
use Cms\Redirect\Domain\RedirectRepository;
use Cms\Redirect\Domain\Resource;
use Cms\Redirect\Domain\ResourceType;
use Cms\Redirect\Domain\SiteResources;
use Cms\Redirect\Domain\Source;
use Cms\Redirect\Domain\SourceFilter;
use Cms\Redirect\Domain\Target;
use Cms\Redirect\Domain\TargetFilter;
use Cms\Tests\Redirect\Domain\RedirectIdMother;
use Cms\Tests\Redirect\Domain\RedirectMother;
use Doctrine\ORM\EntityManagerInterface;
use Shared\Domain\Criteria\Criteria;
use Shared\Domain\Criteria\Order;
use Shared\Domain\Criteria\OrderDirection;
use Shared\Tests\Domain\ClockMother;
use Shared\Tests\Domain\DomainErrorAssertions;
use Shared\Tests\Infrastructure\Persistence\Doctrine\MappedTables;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Every redirect it reads comes from the database as it is now, even one
 * Doctrine holds already, and one saved from what was read before another
 * change saved it is refused.
 */
final class DoctrineRedirectRepositoryTest extends KernelTestCase
{
    use MappedTables;
    use DomainErrorAssertions;

    private const string FIRST = '0196f3a0-0000-7000-8000-0000000000e1';
    private const string SECOND = '0196f3a0-0000-7000-8000-0000000000e2';
    private const string THIRD = '0196f3a0-0000-7000-8000-0000000000e3';
    private const string OTHER_PAGE = '0196f3a0-0000-7000-8000-0000000000b1';

    /**
     * Every field comes back as saved: its address at its longest, where it
     * leads, its origin and its dates.
     */
    public function testItSavesTheRedirectSoItCanBeReadBack(): void
    {
        $longest = '/'.str_repeat('a', 249).'.html';
        $redirect = RedirectMother::create(id: self::FIRST, source: $longest, origin: Resource::page(RedirectMother::OFF_SITE), createdAt: '2027-01-31 23:59:59', updatedAt: '2027-02-01 00:00:00');

        $this->repository()->save($redirect);
        $this->entityManager()->clear();

        self::assertEquals(self::state($redirect), self::state($this->repository()->find(RedirectIdMother::create(self::FIRST))));
    }

    public function testItSavesARedirectThatSaysItsAddressIsGone(): void
    {
        $redirect = RedirectMother::gone(id: self::FIRST);

        $this->repository()->save($redirect);
        $this->entityManager()->clear();

        self::assertEquals(self::state($redirect), self::state($this->repository()->find(RedirectIdMother::create(self::FIRST))));
    }

    public function testItFindsNoRedirectWhenTheIdDoesNotExist(): void
    {
        self::assertNull($this->repository()->find(RedirectIdMother::create(self::FIRST)));
        self::assertNull($this->repository()->sourceOf(RedirectIdMother::create(self::FIRST)));
    }

    public function testItReadsTheAddressOfARedirectAlone(): void
    {
        $this->repository()->save(RedirectMother::create(id: self::FIRST, source: '/vieja.html'));

        self::assertEquals(Source::fromStored('/vieja.html'), $this->repository()->sourceOf(RedirectIdMother::create(self::FIRST)));
    }

    public function testItFindsTheRedirectAtAnAddress(): void
    {
        $this->saveRedirects();

        self::assertSame(self::SECOND, $this->repository()->findBySource(Source::fromStored('/segunda.html'))?->id()->value());
        self::assertNull($this->repository()->findBySource(Source::fromStored('/ninguna.html')));
    }

    public function testItRefusesASecondRedirectAtTheSameAddress(): void
    {
        $this->repository()->save(RedirectMother::create(id: self::FIRST, source: '/vieja.html'));

        $this->expectExceptionObject(InvalidRedirect::sourceTaken(Source::fromStored('/vieja.html')));

        $this->repository()->save(RedirectMother::gone(id: self::SECOND, source: '/vieja.html'));
    }

    public function testItDeletesOnlyTheGivenRedirect(): void
    {
        $this->saveRedirects();

        $this->repository()->delete($this->repository()->find(RedirectIdMother::create(self::FIRST)) ?? self::fail('No redirect to delete.'));

        self::assertNull($this->repository()->find(RedirectIdMother::create(self::FIRST)));
        self::assertNotNull($this->repository()->find(RedirectIdMother::create(self::SECOND)));
    }

    public function testItFindsTheRedirectsThatLeadToAResource(): void
    {
        $this->saveRedirects();
        $page = Resource::page(RedirectMother::PAGE);

        self::assertTrue($this->repository()->isTargeted($page));
        self::assertFalse($this->repository()->isTargeted(Resource::page(self::OTHER_PAGE)));
        // The type of a resource counts as much as its id.
        self::assertFalse($this->repository()->isTargeted(Resource::image(RedirectMother::PAGE)));
        self::assertSame([], $this->repository()->findTargeting(Resource::image(RedirectMother::PAGE)));
        self::assertEqualsCanonicalizing([self::FIRST, self::SECOND], self::ids($this->repository()->findTargeting($page)));
        self::assertEqualsCanonicalizing(['/primera.html', '/segunda.html'], array_map(static fn (Source $source): string => $source->value(), $this->repository()->sourcesTargeting($page)));
    }

    public function testItFindsTheRedirectsThatKnowAResourceAsTheirOrigin(): void
    {
        $this->saveRedirects();

        self::assertSame([self::SECOND], self::ids($this->repository()->findByOrigin(Resource::page(RedirectMother::OFF_SITE))));
        self::assertSame([], $this->repository()->findByOrigin(Resource::image(RedirectMother::OFF_SITE)));
        self::assertSame([self::SECOND], self::ids($this->repository()->findByOrigins(ResourceType::Page, [RedirectMother::OFF_SITE, self::OTHER_PAGE])));
        self::assertSame([], $this->repository()->findByOrigins(ResourceType::Image, [RedirectMother::OFF_SITE]));
        self::assertSame([], $this->repository()->findByOrigins(ResourceType::Page, []));
    }

    public function testItFindsTheRedirectsMatchingTheCriteria(): void
    {
        $this->saveRedirects();
        $newestFirst = new Order('id', OrderDirection::Descending);

        self::assertSame([self::THIRD, self::SECOND, self::FIRST], self::ids($this->repository()->matching(new Criteria(limit: 10, filters: [], order: $newestFirst, offset: 0))));
        self::assertSame([self::SECOND], self::ids($this->repository()->matching(new Criteria(limit: 1, filters: [], order: $newestFirst, offset: 1))));
        self::assertSame([self::SECOND], self::ids($this->repository()->matching(new Criteria(limit: 10, filters: [SourceFilter::contains('SEGUNDA')], order: $newestFirst, offset: 0))));
        self::assertSame([self::SECOND, self::FIRST], self::ids($this->repository()->matching(new Criteria(
            limit: 10,
            filters: [TargetFilter::type(ResourceType::Page), TargetFilter::id(RedirectMother::PAGE)],
            order: $newestFirst,
            offset: 0,
        ))));
    }

    /**
     * Another request saved the redirect since Doctrine read it: reading it
     * again gives what that one saved, not what Doctrine holds.
     */
    public function testItReadsWhatAnotherChangeSavedMeanwhile(): void
    {
        $this->repository()->save(RedirectMother::gone(id: self::FIRST, source: '/vieja.html'));
        $this->changedMeanwhile(version: 2, target: self::OTHER_PAGE);

        $read = $this->repository()->find(RedirectIdMother::create(self::FIRST));

        self::assertEquals(Target::leadingTo(Resource::page(self::OTHER_PAGE)), $read?->target());
    }

    /**
     * A change that saves a redirect it read before another change saved it
     * is refused, rather than overwriting what that one saved.
     */
    public function testItRefusesToSaveARedirectReadBeforeAnotherChangeSavedIt(): void
    {
        $this->repository()->save(RedirectMother::gone(id: self::FIRST, source: '/vieja.html'));
        $this->entityManager()->clear();
        $stale = $this->entityManager()->find(Redirect::class, RedirectIdMother::create(self::FIRST)) ?? self::fail('No redirect to read.');
        $this->changedMeanwhile(version: 2, target: self::OTHER_PAGE);
        $stale->retarget(Target::leadingTo(Resource::page(RedirectMother::PAGE)), self::onSite());

        self::assertTells('errors.redirect.changed_meanwhile', ['%source%' => '/vieja.html'], fn () => $this->repository()->save($stale));
    }

    /**
     * What the entity maps is what the migrations built.
     */
    public function testItsTableIsTheOneMapped(): void
    {
        self::assertTablesAreMapped('shared_redirect');
    }

    /**
     * Three redirects, oldest first: /primera.html and /segunda.html to the
     * page, the second knowing the page off the site as its origin, and
     * /tercera.html gone.
     */
    private function saveRedirects(): void
    {
        $this->repository()->save(RedirectMother::create(id: self::FIRST, source: '/primera.html'));
        $this->repository()->save(RedirectMother::create(id: self::SECOND, source: '/segunda.html', origin: Resource::page(RedirectMother::OFF_SITE)));
        $this->repository()->save(RedirectMother::gone(id: self::THIRD, source: '/tercera.html'));
    }

    /**
     * What another request saving the redirect leaves in the database.
     */
    private function changedMeanwhile(int $version, string $target): void
    {
        $this->entityManager()->getConnection()->executeStatement(
            "UPDATE shared_redirect SET version = ?, target_type = 'page', target_id = ?, updated_at = ? WHERE id = ?",
            [$version, $target, ClockMother::NOW, self::FIRST],
        );
    }

    private static function onSite(): SiteResources
    {
        $site = self::createStub(SiteResources::class);
        $site->method('isOnSite')->willReturn(true);

        return $site;
    }

    /**
     * What a redirect is, without the count of its saves Doctrine keeps.
     *
     * @return ?list<mixed>
     */
    private static function state(?Redirect $redirect): ?array
    {
        return null === $redirect ? null : [$redirect->id(), $redirect->source(), $redirect->target(), $redirect->origin(), $redirect->timeline()];
    }

    /**
     * @param list<Redirect> $redirects
     *
     * @return list<string>
     */
    private static function ids(array $redirects): array
    {
        return array_map(static fn (Redirect $redirect): string => $redirect->id()->value(), $redirects);
    }

    private function repository(): RedirectRepository
    {
        return self::getContainer()->get(RedirectRepository::class);
    }

    private function entityManager(): EntityManagerInterface
    {
        return self::getContainer()->get(EntityManagerInterface::class);
    }
}
