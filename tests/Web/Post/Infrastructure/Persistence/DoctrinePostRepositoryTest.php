<?php

declare(strict_types=1);

namespace Web\Tests\Post\Infrastructure\Persistence;

use DAMA\DoctrineTestBundle\PHPUnit\SkipDatabaseRollback;
use Doctrine\ORM\EntityManagerInterface;
use Shared\Tests\Infrastructure\Persistence\Doctrine\MappedTables;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Web\Post\Domain\CmsPostPublished;
use Web\Post\Domain\Post;
use Web\Post\Domain\PostCard;
use Web\Post\Domain\PostId;
use Web\Post\Domain\PostRepository;
use Web\Post\Domain\PostSelection;
use Web\Post\Domain\SearchTerms;
use Web\Post\Domain\SearchTooBroad;
use Web\Post\Infrastructure\Persistence\DoctrinePostRepository;
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
     * tag enough on its own, one alone read from its own rows.
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
        // One tag with a section or an author, ties by id and a page at a time.
        self::assertSame([self::TIED, self::MIDDLE], $this->listed(new PostSelection(self::OTHER_SECTION, null, [self::SOUND]), 0, 10));
        self::assertSame([self::MIDDLE], $this->listed(new PostSelection(self::OTHER_SECTION, null, [self::SOUND]), 1, 1));
        self::assertSame([self::TIED], $this->listed(new PostSelection(null, self::OTHER_AUTHOR, [self::SOUND]), 0, 10));
        self::assertSame([self::MIDDLE, self::OLDEST], $this->listed(new PostSelection(null, self::AUTHOR, [self::SLEEP]), 0, 10));
        self::assertSame([], $this->listed(new PostSelection(self::SECTION, self::OTHER_AUTHOR, [self::SLEEP]), 0, 10));
        self::assertSame([self::TIED], $this->listed(new PostSelection(self::OTHER_SECTION, self::OTHER_AUTHOR, [self::SOUND, self::SLEEP]), 0, 10));
        self::assertSame([], $this->listed(PostSelection::ofTag(self::UNUSED), 0, 10));
        self::assertSame([], $this->listed(new PostSelection(null, null, []), 4, 10));
    }

    /**
     * A card tells what the post saved has, read without its content: its
     * title, address, section, signature, image, summary and dates.
     */
    public function testItReadsTheCardsOfThePostsAsSaved(): void
    {
        $post = Post::publish(CmsPostPublishedMother::create(lastPublishedAt: '2026-11-09T08:30:00+00:00'));
        $this->repository()->save($post);
        $this->entityManager()->clear();

        self::assertEquals([new PostCard(
            $post->id()->value(),
            $post->title(),
            $post->slug(),
            $post->sectionId(),
            $post->sectionName(),
            $post->sectionSlug(),
            $post->authorId(),
            $post->authorName(),
            $post->openingImageId(),
            $post->metaDescription(),
            $post->day(),
            $post->firstPublishedAt(),
            $post->lastPublishedAt(),
        )], $this->repository()->cardsOf([$post->id(), new PostId('01a1b801-ce58-723d-8234-b76d9105ffff')]));
    }

    /**
     * The posts search engines may index, newest first, ties by id, as many
     * as asked for: those that ask not to be indexed are left out.
     */
    public function testItReadsThePostsSearchEnginesMayIndexNewestFirst(): void
    {
        $this->publish(self::OLDEST, '2026-11-01T10:00:00+00:00', self::SECTION, self::AUTHOR, []);
        $this->publish(self::MIDDLE, '2026-11-03T10:00:00+00:00', self::SECTION, self::AUTHOR, [], Robots::NoindexFollow);
        $this->publish(self::NEWEST, '2026-11-05T10:00:00+00:00', self::SECTION, self::AUTHOR, []);
        $this->publish(self::TIED, '2026-11-05T10:00:00+00:00', self::SECTION, self::AUTHOR, [], Robots::NoindexNofollow);
        $this->entityManager()->clear();

        $ids = static fn (array $posts): array => array_map(static fn (mixed $card): string => $card instanceof PostCard ? $card->id : '', $posts);

        self::assertSame([self::NEWEST, self::OLDEST], $ids($this->repository()->indexed(10)));
        self::assertSame([self::NEWEST], $ids($this->repository()->indexed(1)));
    }

    /**
     * The index of the tags follows each post: republished with other tags,
     * it lists under those alone, dated as the post; deleted, under none,
     * while the others keep theirs, their dates included.
     */
    public function testItKeepsTheIndexOfTheTagsWithEachPost(): void
    {
        $post = $this->publish(self::OLDEST, '2026-11-01T10:00:00+00:00', self::SECTION, self::AUTHOR, [self::SLEEP, self::SOUND]);
        $this->publish(self::NEWEST, '2026-11-05T10:00:00+00:00', self::SECTION, self::AUTHOR, [self::SLEEP]);
        $this->publish(self::MIDDLE, '2026-11-07T10:00:00+00:00', self::SECTION, self::AUTHOR, [self::SOUND]);

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

        // Dated as republished, after the one of the 7th, which keeps its date.
        self::assertSame([self::OLDEST, self::MIDDLE], $this->listed(PostSelection::ofTag(self::SOUND), 0, 10));

        $this->repository()->delete($this->repository()->find(new PostId(self::NEWEST)) ?? throw new \LogicException('Saved above.'));
        $this->entityManager()->clear();
        self::assertSame([], $this->listed(PostSelection::ofTag(self::SLEEP), 0, 10));
        // The rows of the others stay.
        self::assertSame([self::OLDEST, self::MIDDLE], $this->listed(PostSelection::ofTag(self::SOUND), 0, 10));
    }

    /**
     * Every word searched must be in the title, the summary or a heading of
     * the content, as the start of a word, whatever its accents; the most
     * relevant come first, the newest first among equals. What only its
     * paragraphs say is not searched.
     */
    #[SkipDatabaseRollback]
    public function testItSearchesThePostsByTheirWords(): void
    {
        $this->publishText(self::OLDEST, '2026-11-01T10:00:00+00:00', 'El ruido del metro', 'Cómo viví la hora punta.', 'Hoy probé los tapones en el metro: cuánto ruido.');
        $this->publishText(self::MIDDLE, '2026-11-03T10:00:00+00:00', 'Dormir con ruido', 'Sobre el sueño.', 'Los tapones me ayudan a dormir.');
        $this->publishText(self::NEWEST, '2026-11-05T10:00:00+00:00', 'Luces fuertes', 'La luz del súper.', 'Nada de ruido aquí, solo sensorialidad visual.');
        $this->publishText(self::TIED, '2026-11-05T10:00:00+00:00', 'Una cita', 'Sin nada que ver.', 'Otra cosa.', 'De pasada, el elefante del cuarto.');
        $this->entityManager()->clear();

        $searched = fn (string $text, int $offset = 0, int $limit = 10): array => array_map(static fn (PostCard $card): string => $card->id, $this->repository()->searched(SearchTerms::fromText($text), $offset, $limit));

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
     * A search that would take longer than it may asks for another word.
     * MariaDB only stops a statement between the rows it reads, so the
     * search has a hundred to read, committed, as full-text search needs:
     * with one, it sometimes ended before it was checked.
     */
    #[SkipDatabaseRollback]
    public function testItRefusesASearchThatWouldTakeLongerThanItMay(): void
    {
        foreach (range(1, 100) as $number) {
            $this->publishText(\sprintf('01a1e000-0000-7000-8000-%012d', $number), '2026-11-01T10:00:00+00:00', 'El ruido del metro', 'Cómo viví la hora punta.', 'Cuánto ruido.');
        }

        $this->entityManager()->clear();
        $repository = new DoctrinePostRepository($this->entityManager(), 0.000001);

        $this->expectException(SearchTooBroad::class);

        $repository->searched(SearchTerms::fromText('ruido'), 0, 10);
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
    private function publish(string $id, string $firstPublishedAt, string $sectionId, string $authorId, array $tagIds, Robots $robots = Robots::IndexFollow): Post
    {
        $post = Post::publish(CmsPostPublished::fromPrimitives($id, [
            'title' => 'Entrada '.$id,
            'slug' => 'entrada-'.substr($id, -4),
            'section_id' => $sectionId,
            'author_id' => $authorId,
            'tag_ids' => $tagIds,
            'first_published_at' => $firstPublishedAt,
            'robots' => $robots->value,
            'revision' => 1,
        ] + CmsPostPublishedMother::body()));
        $this->repository()->save($post);

        return $post;
    }

    /**
     * A post whose content is a heading saying the heading given, then a
     * paragraph saying the text given.
     */
    private function publishText(string $id, string $firstPublishedAt, string $title, string $summary, string $heading, string $text = 'Un párrafo cualquiera.'): void
    {
        $this->repository()->save(Post::publish(CmsPostPublished::fromPrimitives($id, [
            'title' => $title,
            'slug' => 'entrada-'.substr($id, -4),
            'meta_description' => $summary,
            'content' => ['version' => 1, 'blocks' => [
                ['type' => 'heading', 'level' => 2, 'text' => [['text' => $heading]]],
                ['type' => 'paragraph', 'text' => [['text' => $text]]],
            ]],
            'first_published_at' => $firstPublishedAt,
            'revision' => 1,
        ] + CmsPostPublishedMother::body())));
    }

    /**
     * @return list<string>
     */
    private function listed(PostSelection $selection, int $offset, int $limit): array
    {
        return array_map(static fn (PostCard $card): string => $card->id, $this->repository()->listed($selection, $offset, $limit));
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
