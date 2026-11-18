<?php

declare(strict_types=1);

namespace Cms\Tests\Post\Infrastructure\Persistence;

use Cms\Post\Domain\Post;
use Cms\Post\Domain\PostAddress;
use Cms\Post\Domain\PostAddressTaken;
use Cms\Post\Domain\PostRepository;
use Cms\Post\Domain\PostSelection;
use Cms\Post\Domain\TitleFilter;
use Cms\Shared\Domain\Slug;
use Cms\Tests\Post\Domain\InMemoryClassifiers;
use Cms\Tests\Post\Domain\InMemoryPostImages;
use Cms\Tests\Post\Domain\PostIdMother;
use Cms\Tests\Post\Domain\PostMother;
use Cms\Tests\Shared\Domain\SlugMother;
use Cms\Tests\Taxonomy\Domain\TermMother;
use DAMA\DoctrineTestBundle\PHPUnit\SkipDatabaseRollback;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use Shared\Domain\Criteria\Criteria;
use Shared\Domain\Criteria\Order;
use Shared\Domain\Criteria\OrderDirection;
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

    /**
     * Only the posts on the site under the slug count, by the section each
     * is under there, whatever section it is under as edited.
     */
    public function testItCountsThePostsOnTheSiteUnderASectionSlugBySection(): void
    {
        $other = '01a1b801-ce58-723d-8234-b76d9105f0aa';
        $this->repository()->save(PostMother::published(id: PostIdMother::random()->value(), slug: 'uno', sectionSlug: 'vida-diaria'));
        $this->repository()->save(PostMother::published(id: PostIdMother::random()->value(), slug: 'dos', sectionSlug: 'vida-diaria'));
        $this->repository()->save(PostMother::published(id: PostIdMother::random()->value(), slug: 'tres', section: $other, sectionSlug: 'vida-diaria'));
        $this->repository()->save(PostMother::published(id: PostIdMother::random()->value(), slug: 'cuatro', sectionSlug: 'otra'));
        $this->repository()->save(PostMother::create(id: PostIdMother::random()->value(), slug: 'borrador'));

        $counted = $this->repository()->onSiteUnderSectionSlug(new Slug('vida-diaria'));
        ksort($counted);
        $expected = [PostMother::SECTION => 2, $other => 1];
        ksort($expected);
        self::assertSame($expected, $counted);
        self::assertSame([], $this->repository()->onSiteUnderSectionSlug(new Slug('nadie')));
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

    /**
     * Each page of a list holds exactly the posts of its place in the order,
     * the last page those that are left.
     */
    public function testItFindsAPageOfThePostsInTheirOrder(): void
    {
        $ids = array_map(static fn (int $index): string => \sprintf('01a1e000-0000-7000-8000-%012d', $index), range(1, 5));

        foreach ($ids as $id) {
            $this->repository()->save(PostMother::create(id: $id, slug: 'entrada-'.substr($id, -1)));
        }

        $this->entityManager()->clear();
        $page = fn (int $offset): array => array_map(
            static fn (Post $post): string => $post->id()->value(),
            $this->repository()->matching(new Criteria(limit: 2, filters: [], order: new Order('id', OrderDirection::Descending), offset: $offset)),
        );

        self::assertSame([$ids[4], $ids[3]], $page(0));
        self::assertSame([$ids[2], $ids[1]], $page(2));
        self::assertSame([$ids[0]], $page(4));
        self::assertSame([], $page(6));
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

    /**
     * A preview lists the posts on the site as their version there files
     * them, newest first by first publication: a draft, a post off the site
     * and what a post on the site only has in its draft count for nothing.
     */
    public function testItListsTheLatestPostsOnTheSiteOfASelection(): void
    {
        $otherSection = '01a1e100-0000-7000-8000-000000000002';
        $otherAuthor = '01a1e200-0000-7000-8000-000000000002';
        $oldest = PostMother::published(id: '01a1e000-0000-7000-8000-000000000001', slug: 'primera', tags: [PostMother::TAG], firstPublishedAt: '2026-11-01 10:00:00');
        $middle = PostMother::published(id: '01a1e000-0000-7000-8000-000000000002', slug: 'segunda', section: $otherSection, tags: [PostMother::OTHER_TAG, PostMother::TAG], firstPublishedAt: '2026-11-03 10:00:00');
        $newest = PostMother::published(id: '01a1e000-0000-7000-8000-000000000003', slug: 'tercera', author: $otherAuthor, firstPublishedAt: '2026-11-05 10:00:00');
        // On the site with no tag: the one its draft has doesn't count.
        $edited = PostMother::published(id: '01a1e000-0000-7000-8000-000000000004', slug: 'cuarta', firstPublishedAt: '2026-11-02 10:00:00');
        $edited->edit(PostMother::TITLE, 'cuarta', PostMother::SECTION, PostMother::AUTHOR, [PostMother::OTHER_TAG], PostMother::OPENING_IMAGE, PostMother::CONTENT, PostMother::META_DESCRIPTION, 'index,follow', new InMemoryClassifiers(tags: [PostMother::TAG, PostMother::OTHER_TAG]), new InMemoryPostImages());

        foreach ([$oldest, $middle, $newest, $edited, PostMother::publishable(id: PostIdMother::random()->value(), slug: 'borrador', tags: [PostMother::TAG]), PostMother::unpublished(id: PostIdMother::random()->value())] as $post) {
            $this->repository()->save($post);
        }

        $this->entityManager()->clear();
        $listed = fn (PostSelection $selection, int $limit): array => array_map(static fn (Post $post): string => $post->id()->value(), $this->repository()->latestOnSite($selection, $limit));

        self::assertSame([$newest->id()->value(), $middle->id()->value(), $edited->id()->value(), $oldest->id()->value()], $listed(new PostSelection(null, null, []), 10));
        self::assertSame([$newest->id()->value(), $middle->id()->value()], $listed(new PostSelection(null, null, []), 2));
        self::assertSame([$middle->id()->value()], $listed(new PostSelection($otherSection, null, []), 10));
        self::assertSame([$newest->id()->value()], $listed(new PostSelection(null, $otherAuthor, []), 10));
        self::assertSame([$middle->id()->value()], $listed(new PostSelection(null, null, [PostMother::OTHER_TAG]), 10));
        self::assertSame([$middle->id()->value(), $oldest->id()->value()], $listed(new PostSelection(null, null, [PostMother::OTHER_TAG, PostMother::TAG]), 10));
        self::assertSame([$oldest->id()->value()], $listed(new PostSelection(PostMother::SECTION, PostMother::AUTHOR, [PostMother::TAG]), 10));
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
