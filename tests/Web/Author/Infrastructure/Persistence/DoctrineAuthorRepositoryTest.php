<?php

declare(strict_types=1);

namespace Web\Tests\Author\Infrastructure\Persistence;

use Doctrine\ORM\EntityManagerInterface;
use Shared\Tests\Infrastructure\Persistence\Doctrine\MappedTables;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Web\Author\Domain\Author;
use Web\Author\Domain\AuthorRepository;
use Web\Shared\Domain\Robots;
use Web\Tests\Author\Domain\AuthorIdMother;
use Web\Tests\Author\Domain\CmsAuthorPublishedMother;

final class DoctrineAuthorRepositoryTest extends KernelTestCase
{
    use MappedTables;

    public function testItSavesTheAuthorSoItCanBeReadBack(): void
    {
        $this->repository()->save(Author::publish(CmsAuthorPublishedMother::create(metaDescription: null, robots: Robots::NoindexNofollow)));
        $this->entityManager()->clear();

        $author = $this->repository()->find(AuthorIdMother::create());
        self::assertNotNull($author);
        self::assertEquals(
            [AuthorIdMother::create(), 'Isaac Abreu', 'isaac', null, Robots::NoindexNofollow],
            [$author->id(), $author->name(), $author->slug(), $author->metaDescription(), $author->robots()],
        );
    }

    public function testItFindsTheAuthorAtTheSlug(): void
    {
        $this->repository()->save(Author::publish(CmsAuthorPublishedMother::create()));
        $this->repository()->save(Author::publish(CmsAuthorPublishedMother::create(id: AuthorIdMother::random()->value(), name: 'Otra persona', slug: 'otra')));
        $this->entityManager()->clear();

        self::assertSame('Otra persona', $this->repository()->findBySlug('otra')?->name());
        self::assertNull($this->repository()->findBySlug('nadie'));
    }

    public function testItDeletesOnlyTheGivenAuthor(): void
    {
        $kept = Author::publish(CmsAuthorPublishedMother::create(id: AuthorIdMother::random()->value(), slug: 'otra'));
        $deleted = Author::publish(CmsAuthorPublishedMother::create());
        $this->repository()->save($kept);
        $this->repository()->save($deleted);

        $this->repository()->delete($deleted);
        $this->entityManager()->clear();

        self::assertNull($this->repository()->findBySlug('isaac'));
        self::assertNotNull($this->repository()->findBySlug('otra'));
    }

    /**
     * What the entity maps is what the migrations built.
     */
    public function testItsTableIsTheOneMapped(): void
    {
        self::assertTablesAreMapped('web_author');
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
