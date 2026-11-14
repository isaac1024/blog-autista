<?php

declare(strict_types=1);

namespace Cms\Tests\Author\Application;

use Cms\Author\Application\AuthorResponse;
use Cms\Author\Application\AuthorSearcher;
use Cms\Author\Application\AuthorsFinder;
use Cms\Author\Domain\AuthorRepository;
use Cms\Tests\Author\Domain\AuthorIdMother;
use Cms\Tests\Author\Domain\AuthorMother;
use Doctrine\ORM\EntityManagerInterface;
use Shared\Domain\Criteria\InvalidCriteria;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Searches and finds the authors stored, as the CMS lists them.
 */
final class AuthorSearcherTest extends KernelTestCase
{
    #[\Override]
    protected function tearDown(): void
    {
        self::getContainer()->get(EntityManagerInterface::class)->clear();

        parent::tearDown();
    }

    public function testItListsTheAuthorsNewestFirstAndNarrowsThemDownByName(): void
    {
        $older = AuthorIdMother::random()->value();
        $newer = AuthorIdMother::random()->value();
        self::repository()->save(AuthorMother::create(id: $older, name: 'Isaac Abreu', slug: 'isaac'));
        self::repository()->save(AuthorMother::create(id: $newer, name: 'Ana Ruiz', slug: 'ana'));

        self::assertSame([$newer, $older], self::ids(new AuthorSearcher('', 1)(self::repository())->authors));
        self::assertSame([$older], self::ids(new AuthorSearcher(' abreu ', 1)(self::repository())->authors));
    }

    public function testItTellsWhetherMoreAuthorsFollow(): void
    {
        for ($i = 0; $i <= AuthorSearcher::PAGE_SIZE; ++$i) {
            self::repository()->save(AuthorMother::create(id: AuthorIdMother::random()->value(), name: 'Autor '.$i, slug: 'autor-'.$i));
        }

        self::assertTrue(new AuthorSearcher('', 1)(self::repository())->hasMore);
        self::assertCount(1, new AuthorSearcher('', 2)(self::repository())->authors);
    }

    public function testItRefusesAPageBeforeTheFirst(): void
    {
        $this->expectExceptionObject(InvalidCriteria::nonPositivePage(0));

        new AuthorSearcher('', 0)(self::repository());
    }

    public function testItFindsTheAuthorsAmongTheIdsGiven(): void
    {
        $id = AuthorIdMother::random()->value();
        self::repository()->save(AuthorMother::create(id: $id));

        self::assertSame([$id], self::ids(new AuthorsFinder([$id, AuthorIdMother::random()->value()])(self::repository())));
        self::assertSame([], new AuthorsFinder([])(self::repository()));
    }

    /**
     * @param list<AuthorResponse> $authors
     *
     * @return list<string>
     */
    private static function ids(array $authors): array
    {
        return array_map(static fn (AuthorResponse $author): string => $author->id, $authors);
    }

    private static function repository(): AuthorRepository
    {
        return self::getContainer()->get(AuthorRepository::class);
    }
}
