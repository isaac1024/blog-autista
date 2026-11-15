<?php

declare(strict_types=1);

namespace Web\Tests\Post\Infrastructure\Persistence;

use Doctrine\ORM\EntityManagerInterface;
use Shared\Tests\Infrastructure\Persistence\Doctrine\MappedTables;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Web\Post\Domain\Post;
use Web\Post\Domain\PostRepository;
use Web\Shared\Domain\Robots;
use Web\Tests\Post\Domain\CmsPostPublishedMother;
use Web\Tests\Post\Domain\PostIdMother;

final class DoctrinePostRepositoryTest extends KernelTestCase
{
    use MappedTables;

    public function testItSavesThePostSoItCanBeReadBack(): void
    {
        $post = Post::publish(CmsPostPublishedMother::create(tagIds: [], metaDescription: null, robots: Robots::NoindexNofollow));

        $this->repository()->save($post);
        $this->entityManager()->clear();

        self::assertEquals($post, $this->repository()->find(PostIdMother::create()));
    }

    public function testItFindsThePostAtItsSectionDayAndSlug(): void
    {
        $this->repository()->save(Post::publish(CmsPostPublishedMother::create()));
        $this->repository()->save(Post::publish(CmsPostPublishedMother::create(id: PostIdMother::random()->value(), title: 'Otro día', day: '2026-11-08')));
        $this->repository()->save(Post::publish(CmsPostPublishedMother::create(id: PostIdMother::random()->value(), title: 'Otra sección', sectionSlug: 'dia-a-dia')));
        $this->entityManager()->clear();

        self::assertSame('Mi primer día sin ruido', $this->repository()->findAt('vida-diaria', new \DateTimeImmutable('2026-11-07'), 'mi-primer-dia')?->title());
        self::assertNull($this->repository()->findAt('vida-diaria', new \DateTimeImmutable('2026-11-09'), 'mi-primer-dia'));
    }

    public function testItDeletesOnlyTheGivenPost(): void
    {
        $kept = Post::publish(CmsPostPublishedMother::create(id: PostIdMother::random()->value(), day: '2026-11-08'));
        $deleted = Post::publish(CmsPostPublishedMother::create());
        $this->repository()->save($kept);
        $this->repository()->save($deleted);

        $this->repository()->delete($deleted);
        $this->entityManager()->clear();

        self::assertNull($this->repository()->find(PostIdMother::create()));
        self::assertNotNull($this->repository()->find($kept->id()));
    }

    /**
     * What the entity maps is what the migrations built.
     */
    public function testItsTableIsTheOneMapped(): void
    {
        self::assertTablesAreMapped('web_post');
    }

    private function repository(): PostRepository
    {
        return self::getContainer()->get(PostRepository::class);
    }

    private function entityManager(): EntityManagerInterface
    {
        return self::getContainer()->get(EntityManagerInterface::class);
    }
}
