<?php

declare(strict_types=1);

namespace Cms\Tests\Post\Infrastructure\Persistence;

use Cms\Post\Domain\Post;
use Cms\Post\Domain\PostAddress;
use Cms\Post\Domain\PostAddressTaken;
use Cms\Post\Domain\PostRepository;
use Cms\Post\Domain\TitleFilter;
use Cms\Tests\Post\Domain\PostIdMother;
use Cms\Tests\Post\Domain\PostMother;
use Cms\Tests\Shared\Domain\SlugMother;
use Cms\Tests\Taxonomy\Domain\TermMother;
use DAMA\DoctrineTestBundle\PHPUnit\SkipDatabaseRollback;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use Shared\Domain\Criteria\Criteria;
use Shared\Tests\Domain\ClockMother;
use Shared\Tests\Infrastructure\Persistence\Doctrine\MappedTables;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Every test runs in a transaction rolled back after it, but the one that
 * searches titles: full-text search only sees what is committed. tearDown()
 * empties the table instead.
 */
final class DoctrinePostRepositoryTest extends KernelTestCase
{
    use MappedTables;

    #[\Override]
    protected function tearDown(): void
    {
        $this->entityManager()->getConnection()->executeStatement('DELETE FROM cms_post');

        parent::tearDown();
    }

    #[DataProvider('posts')]
    public function testItSavesThePostSoItCanBeReadBack(Post $post): void
    {
        $this->repository()->save($post);
        $this->entityManager()->clear();

        self::assertEquals($post, $this->repository()->find($post->id()));
    }

    /**
     * @return iterable<string, array{Post}>
     */
    public static function posts(): iterable
    {
        yield 'an unfiled draft without a slug' => [PostMother::create(id: PostIdMother::random()->value(), slug: '')];
        yield 'a draft with its section, author and tags' => [PostMother::publishable(id: PostIdMother::random()->value(), tags: [PostMother::OTHER_TAG, PostMother::TAG])];
        yield 'a published post with changes' => [PostMother::published(id: PostIdMother::random()->value(), slug: 'publicado-con-cambios', tags: [PostMother::TAG], publishedTitle: 'Antes')];
        yield 'an unpublished post' => [PostMother::unpublished(id: PostIdMother::random()->value())];
    }

    public function testItFindsNoPostWhenTheIdDoesNotExist(): void
    {
        self::assertNull($this->repository()->find(PostIdMother::random()));
    }

    public function testItFindsThePostOnTheSiteAtAnAddressOnlyThere(): void
    {
        $id = PostIdMother::random()->value();
        $this->repository()->save(PostMother::published(id: $id, slug: 'en-su-direccion'));
        $this->entityManager()->clear();

        self::assertSame($id, $this->repository()->findOnSiteAt(self::address(TermMother::SLUG, ClockMother::NOW, 'en-su-direccion'))?->id()->value());
        self::assertNull($this->repository()->findOnSiteAt(self::address('otra-seccion', ClockMother::NOW, 'en-su-direccion')));
        self::assertNull($this->repository()->findOnSiteAt(self::address(TermMother::SLUG, '2026-11-08 10:00:00', 'en-su-direccion')));
        self::assertNull($this->repository()->findOnSiteAt(self::address(TermMother::SLUG, ClockMother::NOW, 'otro-slug')));
    }

    /**
     * Off the site, a post is found at the address it last had there, and
     * the newest one when two did; one on the site, never.
     */
    public function testItFindsThePostOffTheSiteThatLastHadAnAddress(): void
    {
        $older = '01a1da50-2b7c-7d01-8a3f-5c2e9b1d0c01';
        $newer = '01a1da50-2b7c-7d01-8a3f-5c2e9b1d0c02';
        $this->repository()->save(PostMother::unpublished(id: $older));
        $this->repository()->save(PostMother::unpublished(id: $newer));
        $this->repository()->save(PostMother::published(id: PostIdMother::random()->value(), slug: 'en-el-sitio'));
        $this->entityManager()->clear();

        self::assertSame($newer, $this->repository()->findOffSiteByLastAddress(self::address(TermMother::SLUG, ClockMother::NOW, PostMother::SLUG))?->id()->value());
        self::assertNull($this->repository()->findOffSiteByLastAddress(self::address(TermMother::SLUG, '2026-11-08 10:00:00', PostMother::SLUG)));
        self::assertNull($this->repository()->findOffSiteByLastAddress(self::address(TermMother::SLUG, ClockMother::NOW, 'en-el-sitio')));
    }

    public function testItLetsDraftsShareTheirSlug(): void
    {
        $this->repository()->save(PostMother::publishable(id: PostIdMother::random()->value(), slug: 'borrador-repetido'));
        $this->repository()->save(PostMother::publishable(id: PostIdMother::random()->value(), slug: 'borrador-repetido'));

        $this->expectNotToPerformAssertions();
    }

    public function testItRejectsPublishingAtTheAddressOfAnotherPostOnTheSite(): void
    {
        $this->repository()->save(PostMother::published(id: PostIdMother::random()->value(), slug: 'repetido'));

        $this->expectExceptionObject(PostAddressTaken::atAddress(self::address(TermMother::SLUG, ClockMother::NOW, 'repetido')));

        $this->repository()->save(PostMother::published(id: PostIdMother::random()->value(), slug: 'repetido'));
    }

    public function testItLetsTheSameSlugBeOnTheSiteOnAnotherDay(): void
    {
        $this->repository()->save(PostMother::published(id: PostIdMother::random()->value(), slug: 'otro-dia'));
        $this->repository()->save(PostMother::published(id: PostIdMother::random()->value(), slug: 'otro-dia', firstPublishedAt: '2026-11-08 10:00:00'));

        $this->expectNotToPerformAssertions();
    }

    #[SkipDatabaseRollback]
    public function testItFindsThePostsWhoseTitleHasTheWords(): void
    {
        $id = PostIdMother::random()->value();
        $this->repository()->save(PostMother::create(id: $id, title: 'Sobre ruidos inesperados'));
        $this->repository()->save(PostMother::create(id: PostIdMother::random()->value(), title: 'Sobre luces fuertes'));
        $this->entityManager()->clear();

        $found = $this->repository()->matching(new Criteria(limit: 10, filters: [TitleFilter::matches('inesperados ruidos')], order: null, offset: 0));

        self::assertSame([$id], array_map(static fn (Post $post): string => $post->id()->value(), $found));
    }

    public function testItDeletesThePost(): void
    {
        $post = PostMother::create(id: PostIdMother::random()->value());
        $this->repository()->save($post);

        $this->repository()->delete($post);
        $this->entityManager()->clear();

        self::assertNull($this->repository()->find($post->id()));
    }

    public function testItsTableIsTheOneMapped(): void
    {
        self::assertTablesAreMapped('cms_post');
    }

    private static function address(string $section, string $firstPublishedAt, string $slug): PostAddress
    {
        return new PostAddress(SlugMother::create($section), ClockMother::now($firstPublishedAt), SlugMother::create($slug));
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
