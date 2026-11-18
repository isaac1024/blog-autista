<?php

declare(strict_types=1);

namespace Cms\Post\Infrastructure\Persistence;

use Cms\Post\Domain\Post;
use Cms\Post\Domain\PostAddress;
use Cms\Post\Domain\PostAddressTaken;
use Cms\Post\Domain\PostId;
use Cms\Post\Domain\PostRepository;
use Cms\Post\Domain\PostSelection;
use Cms\Post\Domain\PostStatus;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\DBAL\ParameterType;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query;
use Shared\Domain\Criteria\Criteria;
use Shared\Infrastructure\Persistence\Doctrine\DoctrineCriteriaConverter;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;

/**
 * Outbound adapter that persists and reads posts through Doctrine ORM.
 */
#[AsAlias(PostRepository::class)]
final readonly class DoctrinePostRepository implements PostRepository
{
    /**
     * Criteria field names mapped onto the Post properties, embeddables included.
     */
    private const array CRITERIA_FIELDS = [
        'id' => 'id',
        'title' => 'version.title.value',
    ];

    public function __construct(private EntityManagerInterface $entityManager) {}

    #[\Override]
    public function save(Post $post): void
    {
        $this->entityManager->persist($post);

        try {
            $this->entityManager->flush();
        } catch (UniqueConstraintViolationException $exception) {
            // Besides the time-ordered id, the address on the site is the
            // only unique key, so another post was published there since it
            // was checked.
            $address = $post->publishedAddress();

            throw null === $address ? $exception : PostAddressTaken::atAddress($address, $exception);
        }
    }

    #[\Override]
    public function delete(Post $post): void
    {
        $this->entityManager->remove($post);
        $this->entityManager->flush();
    }

    #[\Override]
    public function find(PostId $id): ?Post
    {
        return $this->entityManager->find(Post::class, $id);
    }

    #[\Override]
    public function findByIds(array $ids): array
    {
        if ([] === $ids) {
            return [];
        }

        /** @var Query<null, Post> $query */
        $query = $this->entityManager->createQueryBuilder()
            ->select('post')
            ->from(Post::class, 'post')
            ->where('post.id IN (:ids)')
            ->setParameter('ids', array_map(static fn (PostId $id): string => $id->value(), $ids))
            ->getQuery();

        return $query->getResult();
    }

    #[\Override]
    public function titlesOf(array $ids): array
    {
        if ([] === $ids) {
            return [];
        }

        /** @var list<array{id: string, title: string}> $rows */
        $rows = $this->entityManager->getConnection()->fetchAllAssociative(
            'SELECT id, title FROM cms_post WHERE id IN (:ids)',
            ['ids' => array_map(static fn (PostId $id): string => $id->value(), $ids)],
            ['ids' => ArrayParameterType::STRING],
        );

        return array_column($rows, 'title', 'id');
    }

    #[\Override]
    public function findOnSiteAt(PostAddress $address): ?Post
    {
        return $this->entityManager->getRepository(Post::class)->findOneBy([
            'publishedSectionSlug' => $address->section(),
            'publishedDay' => new \DateTimeImmutable($address->day()),
            'publishedSlug' => $address->slug(),
        ]);
    }

    #[\Override]
    public function findOffSiteByLastAddress(PostAddress $address): ?Post
    {
        // A post published at an address takes it from any other, so one
        // post off the site at most last had it; the newest wins otherwise.
        return $this->entityManager->getRepository(Post::class)->findOneBy(
            [
                'lastSectionSlug' => $address->section(),
                'lastDay' => new \DateTimeImmutable($address->day()),
                'lastSlug' => $address->slug(),
                'status' => PostStatus::Unpublished,
            ],
            ['id' => 'DESC'],
        );
    }

    #[\Override]
    public function matching(Criteria $criteria): array
    {
        return new DoctrineCriteriaConverter(self::CRITERIA_FIELDS)->matching($this->entityManager, Post::class, 'post', $criteria);
    }

    /**
     * Read from the version on the site of each post, where a post keeps its
     * section, its author and its tags: only previews list posts this way, so
     * they go without an index of their own.
     */
    #[\Override]
    public function latestOnSite(PostSelection $selection, int $limit): array
    {
        $conditions = ["p.status = 'published'"];
        $parameters = ['limit' => $limit];
        $types = ['limit' => ParameterType::INTEGER];

        if (null !== $selection->sectionId) {
            $conditions[] = "JSON_VALUE(p.published_version, '$.section_id') = :section";
            $parameters['section'] = $selection->sectionId;
        }

        if (null !== $selection->authorId) {
            $conditions[] = "JSON_VALUE(p.published_version, '$.author_id') = :author";
            $parameters['author'] = $selection->authorId;
        }

        if ([] !== $selection->tagIds) {
            $conditions[] = "JSON_OVERLAPS(JSON_EXTRACT(p.published_version, '$.tag_ids'), :tags)";
            $parameters['tags'] = json_encode($selection->tagIds, \JSON_THROW_ON_ERROR);
        }

        $ids = array_map(self::id(...), $this->entityManager->getConnection()->fetchFirstColumn(
            'SELECT p.id FROM cms_post p WHERE '.implode(' AND ', $conditions).' ORDER BY p.first_published_at DESC, p.id DESC LIMIT :limit',
            $parameters,
            $types,
        ));
        $posts = [];

        foreach ($this->findByIds(array_map(static fn (string $id): PostId => new PostId($id), $ids)) as $post) {
            $posts[$post->id()->value()] = $post;
        }

        // In the order they were listed.
        return array_values(array_filter(array_map(static fn (string $id): ?Post => $posts[$id] ?? null, $ids)));
    }

    private static function id(mixed $value): string
    {
        return \is_string($value) ? $value : throw new \UnexpectedValueException('A listed post has no id.');
    }
}
