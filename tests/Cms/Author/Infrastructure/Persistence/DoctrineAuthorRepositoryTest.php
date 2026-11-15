<?php

declare(strict_types=1);

namespace Cms\Tests\Author\Infrastructure\Persistence;

use Cms\Author\Domain\Author;
use Cms\Author\Domain\AuthorRepository;
use Cms\Author\Domain\AuthorSlugAlreadyTaken;
use Cms\Author\Domain\NameFilter;
use Cms\Tests\Author\Domain\AuthorIdMother;
use Cms\Tests\Author\Domain\AuthorMother;
use Cms\Tests\Shared\Domain\SlugMother;
use Doctrine\DBAL\Types\Exception\ValueNotConvertible;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use Shared\Domain\Criteria\Criteria;
use Shared\Domain\Criteria\Order;
use Shared\Domain\Criteria\OrderDirection;
use Shared\Tests\Infrastructure\Persistence\Doctrine\MappedTables;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class DoctrineAuthorRepositoryTest extends KernelTestCase
{
    use MappedTables;

    #[DataProvider('authors')]
    public function testItSavesTheAuthorSoItCanBeReadBack(Author $author): void
    {
        $this->repository()->save($author);
        $this->entityManager()->clear();

        self::assertEquals($author, $this->repository()->find($author->id()));
    }

    /**
     * @return iterable<string, array{Author}>
     */
    public static function authors(): iterable
    {
        yield 'a draft without a slug nor an avatar' => [AuthorMother::create(id: AuthorIdMother::random()->value(), slug: '')];
        yield 'a draft with everything' => [AuthorMother::publishable(id: AuthorIdMother::random()->value())];
        yield 'a published author with another avatar and bio on the site' => [AuthorMother::published(
            id: AuthorIdMother::random()->value(),
            avatar: AuthorMother::OTHER_AVATAR,
            publishedName: 'Isaac',
            publishedAvatar: AuthorMother::AVATAR,
            publishedBio: ['version' => 1, 'blocks' => [['type' => 'paragraph', 'text' => [['text' => 'Antes.']]]]],
        )];
        yield 'an unpublished author' => [AuthorMother::unpublished(id: AuthorIdMother::random()->value())];
    }

    public function testItFindsTheAuthorEditedOrOnTheSiteWithTheSlug(): void
    {
        $id = AuthorIdMother::random()->value();
        $author = AuthorMother::published(id: $id, slug: 'isaac');
        $author->edit(AuthorMother::NAME, 'isaac-abreu', AuthorMother::BIO, AuthorMother::META_DESCRIPTION, 'index,follow');
        $this->repository()->save($author);
        $this->entityManager()->clear();

        self::assertSame($id, $this->repository()->findBySlug(SlugMother::create('isaac'))?->id()->value());
        self::assertSame($id, $this->repository()->findBySlug(SlugMother::create('isaac-abreu'))?->id()->value());
        self::assertNull($this->repository()->findBySlug(SlugMother::create('ana')));
    }

    public function testItFindsTheAuthorOnTheSiteWithTheSlugOnlyThere(): void
    {
        $id = AuthorIdMother::random()->value();
        $author = AuthorMother::published(id: $id);
        $author->edit(AuthorMother::NAME, 'isaac-abreu', AuthorMother::BIO, AuthorMother::META_DESCRIPTION, 'index,follow');
        $this->repository()->save($author);
        $this->entityManager()->clear();

        self::assertSame($id, $this->repository()->findOnSiteBySlug(SlugMother::create(AuthorMother::SLUG))?->id()->value());
        self::assertNull($this->repository()->findOnSiteBySlug(SlugMother::create('isaac-abreu')));
    }

    /**
     * Off the site, an author is found by the slug it last had there, and
     * the newest one when two did; one on the site, never.
     */
    public function testItFindsTheAuthorOffTheSiteThatLastHadASlug(): void
    {
        $older = AuthorMother::unpublished(id: '01a1da50-2b7c-7d01-8a3f-5c2e9b1d0e01');
        $older->edit(AuthorMother::NAME, 'antiguo', AuthorMother::BIO, AuthorMother::META_DESCRIPTION, 'index,follow');
        $this->repository()->save($older);
        $this->repository()->save(AuthorMother::unpublished(id: '01a1da50-2b7c-7d01-8a3f-5c2e9b1d0e02'));
        $this->repository()->save(AuthorMother::published(id: AuthorIdMother::random()->value(), slug: 'ana'));
        $this->entityManager()->clear();

        self::assertSame('01a1da50-2b7c-7d01-8a3f-5c2e9b1d0e02', $this->repository()->findOffSiteByLastSiteSlug(SlugMother::create(AuthorMother::SLUG))?->id()->value());
        self::assertNull($this->repository()->findOffSiteByLastSiteSlug(SlugMother::create('ana')));
    }

    public function testItRejectsSavingAnAuthorWithTheSlugOfAnotherOne(): void
    {
        $this->repository()->save(AuthorMother::create(id: AuthorIdMother::random()->value()));

        $this->expectExceptionObject(AuthorSlugAlreadyTaken::bySlug(SlugMother::create(AuthorMother::SLUG)));

        $this->repository()->save(AuthorMother::create(id: AuthorIdMother::random()->value(), name: 'Otro'));
    }

    public function testItRejectsSavingAnAuthorWithTheSlugAnotherHasOnTheSite(): void
    {
        $author = AuthorMother::published(id: AuthorIdMother::random()->value());
        $author->edit(AuthorMother::NAME, 'otro', AuthorMother::BIO, AuthorMother::META_DESCRIPTION, 'index,follow');
        $this->repository()->save($author);

        $this->expectExceptionObject(AuthorSlugAlreadyTaken::bySlug(SlugMother::create(AuthorMother::SLUG)));

        $taker = AuthorMother::published(id: AuthorIdMother::random()->value());
        $taker->edit(AuthorMother::NAME, 'otro-mas', AuthorMother::BIO, AuthorMother::META_DESCRIPTION, 'index,follow');
        $this->repository()->save($taker);
    }

    public function testItFindsTheAuthorsWithTheGivenIds(): void
    {
        $id = AuthorIdMother::random()->value();
        $this->repository()->save(AuthorMother::create(id: $id));

        self::assertSame([$id], array_map(static fn (Author $author): string => $author->id()->value(), $this->repository()->findByIds([AuthorIdMother::create($id), AuthorIdMother::random()])));
        self::assertSame([], $this->repository()->findByIds([]));
    }

    /**
     * Newest first, and narrowed down to those whose name has the text, in
     * any case.
     */
    public function testItFindsTheAuthorsMatchingTheCriteria(): void
    {
        $older = AuthorIdMother::random()->value();
        $newer = AuthorIdMother::random()->value();
        $this->repository()->save(AuthorMother::create(id: $older, name: 'Isaac Abreu', slug: 'isaac'));
        $this->repository()->save(AuthorMother::create(id: $newer, name: 'Ana Ruiz', slug: 'ana'));
        $this->entityManager()->clear();
        $newestFirst = new Order('id', OrderDirection::Descending);

        self::assertSame([$newer, $older], self::ids($this->repository()->matching(new Criteria(limit: Criteria::MAX_LIMIT, filters: [], order: $newestFirst, offset: 0))));
        self::assertSame([$older], self::ids($this->repository()->matching(new Criteria(limit: Criteria::MAX_LIMIT, filters: [NameFilter::contains('abreu')], order: $newestFirst, offset: 0))));
        self::assertSame([$older], self::ids($this->repository()->matching(new Criteria(limit: 1, filters: [], order: $newestFirst, offset: 1))));
    }

    public function testItDeletesTheAuthor(): void
    {
        $author = AuthorMother::create(id: AuthorIdMother::random()->value());
        $this->repository()->save($author);

        $this->repository()->delete($author);
        $this->entityManager()->clear();

        self::assertNull($this->repository()->find($author->id()));
    }

    /**
     * A column the database holds corrupt is refused, never read as
     * something else.
     */
    #[DataProvider('corruptColumns')]
    public function testItRefusesToReadAColumnTheDatabaseHoldsCorrupt(string $column, string $value): void
    {
        $author = AuthorMother::published(id: AuthorIdMother::random()->value());
        $this->repository()->save($author);
        $this->entityManager()->getConnection()->executeStatement(\sprintf('UPDATE cms_author SET %s = ? WHERE id = ?', $column), [$value, $author->id()->value()]);
        $this->entityManager()->clear();

        $this->expectException(\Throwable::class);

        $this->repository()->find($author->id());
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function corruptColumns(): iterable
    {
        yield 'an avatar that names no file of an avatar' => ['avatar', '../secreto.webp'];
        yield 'a version on the site that is no object' => ['published_version', '"texto"'];
        yield 'a version on the site without its name' => ['published_version', '{"robots": "index,follow"}'];
    }

    public function testItRefusesAVersionOnTheSiteWithoutItsRobots(): void
    {
        $author = AuthorMother::published(id: AuthorIdMother::random()->value());
        $this->repository()->save($author);
        $this->entityManager()->getConnection()->executeStatement('UPDATE cms_author SET published_version = ? WHERE id = ?', ['{"name": "Isaac", "bio": {"version": 1, "blocks": []}}', $author->id()->value()]);
        $this->entityManager()->clear();

        $this->expectException(ValueNotConvertible::class);

        $this->repository()->find($author->id());
    }

    /**
     * What the entity maps is what the migrations built.
     */
    public function testItsTableIsTheOneMapped(): void
    {
        self::assertTablesAreMapped('cms_author');
    }

    /**
     * @param list<Author> $authors
     *
     * @return list<string>
     */
    private static function ids(array $authors): array
    {
        return array_map(static fn (Author $author): string => $author->id()->value(), $authors);
    }

    private function repository(): AuthorRepository
    {
        return self::getContainer()->get(AuthorRepository::class);
    }

    private function entityManager(): EntityManagerInterface
    {
        return self::getContainer()->get(EntityManagerInterface::class);
    }
}
