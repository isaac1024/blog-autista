<?php

declare(strict_types=1);

namespace Web\Tests\Post\Infrastructure\Persistence;

use DAMA\DoctrineTestBundle\PHPUnit\SkipDatabaseRollback;
use Doctrine\ORM\EntityManagerInterface;
use Shared\Tests\Infrastructure\Persistence\Doctrine\MappedTables;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Web\Post\Domain\CmsPostPublished;
use Web\Post\Domain\Post;
use Web\Post\Domain\PostId;
use Web\Post\Domain\PostRepository;
use Web\Post\Domain\PostSelection;
use Web\Post\Domain\SearchTerms;
use Web\Shared\Domain\Robots;
use Web\Tests\Post\Domain\CmsPostPublishedMother;
use Web\Tests\Post\Domain\PostIdMother;

/**
 * Every test runs in a transaction rolled back after it, but the one that
 * searches: full-text search only sees what is committed. tearDown()
 * empties the tables instead.
 */
final class DoctrinePostRepositoryTest extends KernelTestCase
{
    use MappedTables;

    private const string OLDEST = '01a1e000-0000-7000-8000-000000000001';

    private const string MIDDLE = '01a1e000-0000-7000-8000-000000000002';

    private const string NEWEST = '01a1e000-0000-7000-8000-000000000003';

    private const string TIED = '01a1e000-0000-7000-8000-000000000004';

    private const string SECTION = '01a1e100-0000-7000-8000-000000000001';

    private const string OTHER_SECTION = '01a1e100-0000-7000-8000-000000000002';

    private const string AUTHOR = '01a1e200-0000-7000-8000-000000000001';

    private const string OTHER_AUTHOR = '01a1e200-0000-7000-8000-000000000002';

    private const string SLEEP = '01a1e300-0000-7000-8000-000000000001';

    private const string SOUND = '01a1e300-0000-7000-8000-000000000002';

    private const string UNUSED = '01a1e300-0000-7000-8000-000000000003';

    #[\Override]
    protected function tearDown(): void
    {
        $connection = $this->entityManager()->getConnection();
        $connection->executeStatement('DELETE FROM web_post_tag');
        $connection->executeStatement('DELETE FROM web_post');

        parent::tearDown();
    }

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
     * Every list goes newest first by the day each post was first published,
     * ties by id; a section, an author and some tags narrow it down, each
     * tag enough on its own.
     */
    public function testItListsThePostsOfASelectionNewestFirst(): void
    {
        $this->publish(self::OLDEST, '2026-11-01T10:00:00+00:00', self::SECTION, self::AUTHOR, [self::SLEEP]);
        $this->publish(self::MIDDLE, '2026-11-03T10:00:00+00:00', self::OTHER_SECTION, self::AUTHOR, [self::SOUND, self::SLEEP]);
        $this->publish(self::NEWEST, '2026-11-05T10:00:00+00:00', self::SECTION, self::OTHER_AUTHOR, []);
        // Published the same moment as the newest, with a later id.
        $this->publish(self::TIED, '2026-11-05T10:00:00+00:00', self::OTHER_SECTION, self::OTHER_AUTHOR, [self::SOUND]);
        $this->entityManager()->clear();

        self::assertSame([self::TIED, self::NEWEST, self::MIDDLE, self::OLDEST], $this->listed(new PostSelection(null, null, []), 0, 10));
        self::assertSame([self::NEWEST, self::MIDDLE], $this->listed(new PostSelection(null, null, []), 1, 2));
        self::assertSame([self::NEWEST, self::OLDEST], $this->listed(PostSelection::ofSection(self::SECTION), 0, 10));
        self::assertSame([self::MIDDLE, self::OLDEST], $this->listed(new PostSelection(null, self::AUTHOR, []), 0, 10));
        self::assertSame([self::MIDDLE, self::OLDEST], $this->listed(PostSelection::ofTag(self::SLEEP), 0, 10));
        self::assertSame([self::OLDEST], $this->listed(PostSelection::ofTag(self::SLEEP), 1, 10));
        self::assertSame([self::TIED, self::MIDDLE], $this->listed(PostSelection::ofTag(self::SOUND), 0, 10));
        self::assertSame([self::TIED, self::MIDDLE, self::OLDEST], $this->listed(new PostSelection(null, null, [self::SOUND, self::SLEEP]), 0, 10));
        self::assertSame([self::MIDDLE], $this->listed(new PostSelection(self::OTHER_SECTION, self::AUTHOR, [self::SLEEP]), 0, 10));
        self::assertSame([self::TIED], $this->listed(new PostSelection(self::OTHER_SECTION, self::OTHER_AUTHOR, [self::SOUND, self::SLEEP]), 0, 10));
        self::assertSame([], $this->listed(PostSelection::ofTag(self::UNUSED), 0, 10));
        self::assertSame([], $this->listed(new PostSelection(null, null, []), 4, 10));
    }

    /**
     * The index of the tags follows each post: republished with other tags,
     * it lists under those alone, dated as the post; deleted, under none.
     */
    public function testItKeepsTheIndexOfTheTagsWithEachPost(): void
    {
        $post = $this->publish(self::OLDEST, '2026-11-01T10:00:00+00:00', self::SECTION, self::AUTHOR, [self::SLEEP, self::SOUND]);
        $this->publish(self::NEWEST, '2026-11-05T10:00:00+00:00', self::SECTION, self::AUTHOR, [self::SLEEP]);

        $post->republish(CmsPostPublished::fromPrimitives(self::OLDEST, [
            'tag_ids' => [self::UNUSED, self::SOUND],
            'first_published_at' => '2026-11-09T10:00:00+00:00',
            'revision' => 2,
        ] + CmsPostPublishedMother::body()));
        $this->repository()->save($post);
        $this->entityManager()->clear();

        self::assertSame([self::NEWEST], $this->listed(PostSelection::ofTag(self::SLEEP), 0, 10));
        self::assertSame([self::OLDEST], $this->listed(PostSelection::ofTag(self::UNUSED), 0, 10));
        // Kept in the order the editor gave them.
        self::assertSame([self::UNUSED, self::SOUND], $this->repository()->find(new PostId(self::OLDEST))?->tagIds());

        $this->publish(self::MIDDLE, '2026-11-07T10:00:00+00:00', self::SECTION, self::AUTHOR, [self::SOUND]);
        $this->entityManager()->clear();
        // Dated as republished: after the one of the 7th.
        self::assertSame([self::OLDEST, self::MIDDLE], $this->listed(PostSelection::ofTag(self::SOUND), 0, 10));

        $this->repository()->delete($this->repository()->find(new PostId(self::NEWEST)) ?? throw new \LogicException('Saved above.'));
        $this->entityManager()->clear();
        self::assertSame([], $this->listed(PostSelection::ofTag(self::SLEEP), 0, 10));
    }

    /**
     * Every word searched must be in the title, the summary or the text of
     * the content, as the start of a word, whatever its accents; the most
     * relevant come first, the newest first among equals.
     */
    #[SkipDatabaseRollback]
    public function testItSearchesThePostsByTheirWords(): void
    {
        $this->publishText(self::OLDEST, '2026-11-01T10:00:00+00:00', 'El ruido del metro', 'Cómo viví la hora punta.', 'Hoy probé los tapones en el metro: cuánto ruido.');
        $this->publishText(self::MIDDLE, '2026-11-03T10:00:00+00:00', 'Dormir con ruido', 'Sobre el sueño.', 'Los tapones me ayudan a dormir.');
        $this->publishText(self::NEWEST, '2026-11-05T10:00:00+00:00', 'Luces fuertes', 'La luz del súper.', 'Nada de ruido aquí, solo sensorialidad visual.');
        $this->publishText(self::TIED, '2026-11-05T10:00:00+00:00', 'Una cita', 'Sin nada que ver.', 'Otra cosa.');
        $this->entityManager()->clear();

        $searched = fn (string $text, int $offset = 0, int $limit = 10): array => array_map(static fn (Post $post): string => $post->id()->value(), $this->repository()->searched(SearchTerms::fromText($text), $offset, $limit));

        // Said twice, it ranks above those that say it once, which go newest first.
        self::assertSame([self::OLDEST, self::NEWEST, self::MIDDLE], $searched('ruido'));
        self::assertSame([self::MIDDLE, self::OLDEST], $searched('tapones'));
        self::assertSame([self::OLDEST], $searched('tapones metro'));
        self::assertSame([self::MIDDLE], $searched('sueno'));
        self::assertSame([self::NEWEST], $searched('sensorial'));
        self::assertSame([self::NEWEST], $searched('SÚPER'));
        self::assertSame([self::NEWEST, self::MIDDLE], $searched('ruido', 1, 2));
        self::assertSame([], $searched('elefante'));
        self::assertSame([], $searched('no y'));
    }

    /**
     * What the entities map is what the migrations built.
     */
    public function testItsTablesAreTheOnesMapped(): void
    {
        self::assertTablesAreMapped('web_post', 'web_post_tag');
    }

    /**
     * @param list<string> $tagIds
     */
    private function publish(string $id, string $firstPublishedAt, string $sectionId, string $authorId, array $tagIds): Post
    {
        $post = Post::publish(CmsPostPublished::fromPrimitives($id, [
            'title' => 'Entrada '.$id,
            'slug' => 'entrada-'.substr($id, -4),
            'section_id' => $sectionId,
            'author_id' => $authorId,
            'tag_ids' => $tagIds,
            'first_published_at' => $firstPublishedAt,
            'revision' => 1,
        ] + CmsPostPublishedMother::body()));
        $this->repository()->save($post);

        return $post;
    }

    private function publishText(string $id, string $firstPublishedAt, string $title, string $summary, string $text): void
    {
        $this->repository()->save(Post::publish(CmsPostPublished::fromPrimitives($id, [
            'title' => $title,
            'slug' => 'entrada-'.substr($id, -4),
            'meta_description' => $summary,
            'content' => ['version' => 1, 'blocks' => [['type' => 'paragraph', 'text' => [['text' => $text]]]]],
            'first_published_at' => $firstPublishedAt,
            'revision' => 1,
        ] + CmsPostPublishedMother::body())));
    }

    /**
     * @return list<string>
     */
    private function listed(PostSelection $selection, int $offset, int $limit): array
    {
        return array_map(static fn (Post $post): string => $post->id()->value(), $this->repository()->listed($selection, $offset, $limit));
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
