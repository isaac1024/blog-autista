<?php

declare(strict_types=1);

namespace Web\Post\Infrastructure\Persistence;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Exception\DriverException;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Types\Type;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Web\Post\Domain\Post;
use Web\Post\Domain\PostCard;
use Web\Post\Domain\PostId;
use Web\Post\Domain\PostRepository;
use Web\Post\Domain\PostSelection;
use Web\Post\Domain\SearchTerms;
use Web\Post\Domain\SearchTooBroad;
use Web\Shared\Domain\Robots;

/**
 * Outbound adapter that persists and reads the posts on the site through
 * Doctrine ORM. Lists are read in two steps: the ids of a page of them, in
 * plain SQL, each through the index that orders it, then the cards of
 * those posts, from the columns a card shows.
 */
#[AsAlias(PostRepository::class)]
final readonly class DoctrinePostRepository implements PostRepository
{
    /**
     * MariaDB's errors when a statement takes longer than it may: stopped
     * between rows, or within the full-text index, which tells it as an
     * interruption.
     */
    private const array STATEMENT_TIME_EXCEEDED = [1969, 188];

    /**
     * @param float $searchTimeLimit the seconds a search may take, which MariaDB stops it after
     */
    public function __construct(
        private EntityManagerInterface $entityManager,
        #[Autowire(param: 'web.post.search_time_limit')]
        private float $searchTimeLimit,
    ) {}

    /**
     * Saves the post and the rows of the index of its tags at once.
     */
    #[\Override]
    public function save(Post $post): void
    {
        $this->entityManager->wrapInTransaction(function () use ($post): void {
            $this->entityManager->persist($post);
            $this->entityManager->flush();
            $this->indexTags($post);
        });
    }

    #[\Override]
    public function delete(Post $post): void
    {
        $this->entityManager->wrapInTransaction(function () use ($post): void {
            $this->entityManager->getConnection()->delete('web_post_tag', ['post_id' => $post->id()->value()]);
            $this->entityManager->remove($post);
            $this->entityManager->flush();
        });
    }

    #[\Override]
    public function find(PostId $id): ?Post
    {
        return $this->entityManager->find(Post::class, $id);
    }

    #[\Override]
    public function cardsOf(array $ids): array
    {
        return $this->cards(array_map(static fn (PostId $id): string => $id->value(), $ids));
    }

    #[\Override]
    public function findAt(\DateTimeImmutable $day, string $slug): ?Post
    {
        return $this->entityManager->getRepository(Post::class)->findOneBy(['day' => $day, 'slug' => $slug]);
    }

    #[\Override]
    public function listed(PostSelection $selection, int $offset, int $limit): array
    {
        return $this->cards($this->idsListed($selection, $offset, $limit));
    }

    #[\Override]
    public function listedIds(PostSelection $selection, int $offset, int $limit): array
    {
        return array_map(static fn (string $id): PostId => new PostId($id), $this->idsListed($selection, $offset, $limit));
    }

    #[\Override]
    public function indexed(int $limit): array
    {
        return $this->cards(array_map(self::id(...), $this->entityManager->getConnection()->fetchFirstColumn(
            'SELECT id FROM web_post WHERE robots = :robots ORDER BY first_published_at DESC, id DESC LIMIT :limit',
            ['robots' => Robots::IndexFollow->value, 'limit' => $limit],
            ['limit' => ParameterType::INTEGER],
        )));
    }

    /**
     * Read through the full-text index of the posts, in boolean mode: every
     * word required, each as a prefix, so "sensor" finds "sensorial".
     */
    #[\Override]
    public function searched(SearchTerms $terms, int $offset, int $limit): array
    {
        if ($terms->isEmpty()) {
            return [];
        }

        $match = 'MATCH (p.title, p.meta_description, p.search_text) AGAINST (:words IN BOOLEAN MODE)';

        // Its time grows with every post it finds, whatever page is asked:
        // MariaDB stops it past the limit, so no word frequent enough holds
        // a process of the site, of the few the hosting has, for seconds.
        try {
            $ids = $this->entityManager->getConnection()->fetchFirstColumn(
                \sprintf('SET STATEMENT max_statement_time = %2$.6F FOR SELECT p.id FROM web_post p WHERE %1$s ORDER BY %1$s DESC, p.first_published_at DESC, p.id DESC LIMIT :limit OFFSET :offset', $match, $this->searchTimeLimit),
                ['words' => implode(' ', array_map(static fn (string $word): string => '+'.$word.'*', $terms->words)), 'limit' => $limit, 'offset' => $offset],
                ['limit' => ParameterType::INTEGER, 'offset' => ParameterType::INTEGER],
            );
        } catch (DriverException $exception) {
            throw \in_array($exception->getCode(), self::STATEMENT_TIME_EXCEEDED, true) ? SearchTooBroad::forTerms($terms, $exception) : $exception;
        }

        return $this->cards(array_map(self::id(...), $ids));
    }

    /**
     * The cards of the posts of the ids, in their order, read from the
     * columns a card shows: never the content, nor the text searched.
     *
     * @param list<string> $ids
     *
     * @return list<PostCard>
     */
    private function cards(array $ids): array
    {
        if ([] === $ids) {
            return [];
        }

        $connection = $this->entityManager->getConnection();
        $cards = [];

        /** @var list<array{id: string, title: string, slug: string, section_id: string, section_name: string, section_slug: string, author_id: string, author_name: string, opening_image_id: string, meta_description: ?string, day: string, first_published_at: string, last_published_at: string}> $rows */
        $rows = $connection->fetchAllAssociative(
            'SELECT id, title, slug, section_id, section_name, section_slug, author_id, author_name, opening_image_id, meta_description, day, first_published_at, last_published_at FROM web_post WHERE id IN (:ids)',
            ['ids' => $ids],
            ['ids' => ArrayParameterType::STRING],
        );

        foreach ($rows as $row) {
            $cards[$row['id']] = new PostCard(
                $row['id'],
                $row['title'],
                $row['slug'],
                $row['section_id'],
                $row['section_name'],
                $row['section_slug'],
                $row['author_id'],
                $row['author_name'],
                $row['opening_image_id'],
                $row['meta_description'],
                $this->moment(Types::DATE_IMMUTABLE, $row['day']),
                $this->moment(Types::DATETIME_IMMUTABLE, $row['first_published_at']),
                $this->moment(Types::DATETIME_IMMUTABLE, $row['last_published_at']),
            );
        }

        // In the order the ids were listed.
        return array_values(array_filter(array_map(static fn (string $id): ?PostCard => $cards[$id] ?? null, $ids)));
    }

    /**
     * Brings the rows of the index of the tags of the post to those of the
     * tags it has now, dated by the day it was first published: removes
     * those it no longer has, dates again those whose day changed and adds
     * the new ones, writing nothing when nothing changed.
     */
    private function indexTags(Post $post): void
    {
        $connection = $this->entityManager->getConnection();
        $postId = $post->id()->value();
        $wanted = array_values(array_unique($post->tagIds()));
        /** @var array<string, string> $kept first published at, by tag */
        $kept = $connection->fetchAllKeyValue('SELECT term_id, first_published_at FROM web_post_tag WHERE post_id = :post', ['post' => $postId]);
        $firstPublishedAt = $connection->convertToDatabaseValue($post->firstPublishedAt(), Types::DATETIME_IMMUTABLE);

        // Each row by its key, so no range is locked: concurrent consumers of
        // the queue then never wait on each other's gaps.
        foreach ($kept as $termId => $date) {
            if (!\in_array($termId, $wanted, true)) {
                $connection->delete('web_post_tag', ['post_id' => $postId, 'term_id' => $termId]);
            } elseif ($date !== $firstPublishedAt) {
                $connection->update('web_post_tag', ['first_published_at' => $firstPublishedAt], ['post_id' => $postId, 'term_id' => $termId]);
            }
        }

        foreach (array_diff($wanted, array_keys($kept)) as $termId) {
            $connection->insert('web_post_tag', ['post_id' => $postId, 'term_id' => $termId, 'first_published_at' => $firstPublishedAt]);
        }
    }

    /**
     * The ids of a page of the selection, newest first. One tag lists them
     * from its rows, which keep the day for that, a section or an author
     * checked on each post; anything else, from the posts, through the index
     * of a section or an author, or that of every post.
     *
     * @return list<string>
     */
    private function idsListed(PostSelection $selection, int $offset, int $limit): array
    {
        $connection = $this->entityManager->getConnection();

        $conditions = [];
        $parameters = ['limit' => $limit, 'offset' => $offset];
        $types = ['limit' => ParameterType::INTEGER, 'offset' => ParameterType::INTEGER];

        if (null !== $selection->sectionId) {
            $conditions[] = 'p.section_id = :section';
            $parameters['section'] = $selection->sectionId;
        }

        if (null !== $selection->authorId) {
            $conditions[] = 'p.author_id = :author';
            $parameters['author'] = $selection->authorId;
        }

        // One tag lists from its own rows, newest first through their index,
        // whatever else narrows it down, reading them in order until the page
        // is full: tens of milliseconds at most, for a tag of thousands.
        // Left to choose, MariaDB starts from the posts of the section or of
        // the author and sorts them all, which takes seconds; hence the
        // straight join.
        if ($selection->hasOneTag()) {
            return array_map(self::id(...), $connection->fetchFirstColumn(
                \sprintf(
                    'SELECT t.post_id FROM web_post_tag t%s WHERE t.term_id = :tag%s ORDER BY t.first_published_at DESC, t.post_id DESC LIMIT :limit OFFSET :offset',
                    [] === $conditions ? '' : ' STRAIGHT_JOIN web_post p ON p.id = t.post_id',
                    implode('', array_map(static fn (string $condition): string => ' AND '.$condition, $conditions)),
                ),
                ['tag' => $selection->tagIds[0]] + $parameters,
                $types,
            ));
        }

        // Two tags or more, any of them: here the plans that read from the
        // posts are the quick ones.
        if ([] !== $selection->tagIds) {
            $conditions[] = 'EXISTS (SELECT 1 FROM web_post_tag t WHERE t.post_id = p.id AND t.term_id IN (:tags))';
            $parameters['tags'] = $selection->tagIds;
            $types['tags'] = ArrayParameterType::STRING;
        }

        return array_map(self::id(...), $connection->fetchFirstColumn(
            \sprintf(
                'SELECT p.id FROM web_post p%s ORDER BY p.first_published_at DESC, p.id DESC LIMIT :limit OFFSET :offset',
                [] === $conditions ? '' : ' WHERE '.implode(' AND ', $conditions),
            ),
            $parameters,
            $types,
        ));
    }

    /**
     * A day or a moment of a card, read as the entity maps it.
     */
    private function moment(string $type, string $value): \DateTimeImmutable
    {
        $moment = Type::getType($type)->convertToPHPValue($value, $this->entityManager->getConnection()->getDatabasePlatform());

        return $moment instanceof \DateTimeImmutable ? $moment : throw new \UnexpectedValueException('A listed post has no date where it should.');
    }

    private static function id(mixed $value): string
    {
        return \is_string($value) ? $value : throw new \UnexpectedValueException('A listed post has no id.');
    }
}
