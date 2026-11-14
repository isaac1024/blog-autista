<?php

declare(strict_types=1);

namespace Cms\Tests\Author\Infrastructure\Persistence;

use Cms\Author\Domain\Author;
use Cms\Author\Domain\AuthorRepository;
use Cms\Author\Domain\AuthorSlugAlreadyTaken;
use Cms\Tests\Author\Domain\AuthorIdMother;
use Cms\Tests\Author\Domain\AuthorMother;
use Cms\Tests\Shared\Domain\SlugMother;
use Doctrine\DBAL\Types\Exception\ValueNotConvertible;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
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

    private function repository(): AuthorRepository
    {
        return self::getContainer()->get(AuthorRepository::class);
    }

    private function entityManager(): EntityManagerInterface
    {
        return self::getContainer()->get(EntityManagerInterface::class);
    }
}
