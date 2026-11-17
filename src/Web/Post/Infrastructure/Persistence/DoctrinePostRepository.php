<?php

declare(strict_types=1);

namespace Web\Post\Infrastructure\Persistence;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;
use Web\Post\Domain\Post;
use Web\Post\Domain\PostId;
use Web\Post\Domain\PostRepository;
use Web\Post\Domain\PostSelection;
use Web\Post\Domain\SearchTerms;
use Web\Shared\Domain\Robots;

/**
 * Outbound adapter that persists and reads the posts on the site through
 * Doctrine ORM. Lists are read in two steps: the ids of a page of them, in
 * plain SQL, each through the index that orders it, then those posts.
 */
#[AsAlias(PostRepository::class)]
final readonly class DoctrinePostRepository implements PostRepository
{
    public function __construct(private EntityManagerInterface $entityManager) {}

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
    public function findAt(string $section, \DateTimeImmutable $day, string $slug): ?Post
    {
        return $this->entityManager->getRepository(Post::class)->findOneBy(['sectionSlug' => $section, 'day' => $day, 'slug' => $slug]);
    }

    #[\Override]
    public function listed(PostSelection $selection, int $offset, int $limit): array
    {
        return $this->postsOf($this->listedIds($selection, $offset, $limit));
    }

    #[\Override]
    public function indexed(int $limit): array
    {
        return $this->postsOf(array_map(self::id(...), $this->entityManager->getConnection()->fetchFirstColumn(
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

        return $this->postsOf(array_map(self::id(...), $this->entityManager->getConnection()->fetchFirstColumn(
            \sprintf('SELECT p.id FROM web_post p WHERE %1$s ORDER BY %1$s DESC, p.first_published_at DESC, p.id DESC LIMIT :limit OFFSET :offset', $match),
            ['words' => implode(' ', array_map(static fn (string $word): string => '+'.$word.'*', $terms->words)), 'limit' => $limit, 'offset' => $offset],
            ['limit' => ParameterType::INTEGER, 'offset' => ParameterType::INTEGER],
        )));
    }

    /**
     * The posts of the ids, in their order.
     *
     * @param list<string> $ids
     *
     * @return list<Post>
     */
    private function postsOf(array $ids): array
    {
        if ([] === $ids) {
            return [];
        }

        $posts = [];

        /** @var list<Post> $found */
        $found = $this->entityManager->createQueryBuilder()
            ->select('p')
            ->from(Post::class, 'p')
            ->where('p.id IN (:ids)')
            ->setParameter('ids', $ids, ArrayParameterType::STRING)
            ->getQuery()
            ->getResult();

        foreach ($found as $post) {
            $posts[$post->id()->value()] = $post;
        }

        // In the order the ids were listed.
        return array_values(array_filter(array_map(static fn (string $id): ?Post => $posts[$id] ?? null, $ids)));
    }

    /**
     * Replaces the rows of the index of the tags of the post with those of
     * the tags it has now, dated by the day it was first published.
     */
    private function indexTags(Post $post): void
    {
        $connection = $this->entityManager->getConnection();
        $connection->delete('web_post_tag', ['post_id' => $post->id()->value()]);

        foreach (array_unique($post->tagIds()) as $termId) {
            $connection->insert('web_post_tag', [
                'post_id' => $post->id()->value(),
                'term_id' => $termId,
                'first_published_at' => $post->firstPublishedAt(),
            ], ['first_published_at' => Types::DATETIME_IMMUTABLE]);
        }
    }

    /**
     * The ids of a page of the selection, newest first. Only a tag lists
     * them from the rows of the tags, which keep the day for that; anything
     * else, from the posts, through the index of a section or an author, or
     * that of every post.
     *
     * @return list<string>
     */
    private function listedIds(PostSelection $selection, int $offset, int $limit): array
    {
        $connection = $this->entityManager->getConnection();

        if ($selection->isOnlyATag()) {
            return array_map(self::id(...), $connection->fetchFirstColumn(
                'SELECT post_id FROM web_post_tag WHERE term_id = :tag ORDER BY first_published_at DESC, post_id DESC LIMIT :limit OFFSET :offset',
                ['tag' => $selection->tagIds[0], 'limit' => $limit, 'offset' => $offset],
                ['limit' => ParameterType::INTEGER, 'offset' => ParameterType::INTEGER],
            ));
        }

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

    private static function id(mixed $value): string
    {
        return \is_string($value) ? $value : throw new \UnexpectedValueException('A listed post has no id.');
    }
}
