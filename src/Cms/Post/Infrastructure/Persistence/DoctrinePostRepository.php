<?php

declare(strict_types=1);

namespace Cms\Post\Infrastructure\Persistence;

use Cms\Post\Domain\Post;
use Cms\Post\Domain\PostAddress;
use Cms\Post\Domain\PostAddressTaken;
use Cms\Post\Domain\PostId;
use Cms\Post\Domain\PostRepository;
use Cms\Post\Domain\PostStatus;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
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
        $queryBuilder = $this->entityManager->createQueryBuilder()
            ->select('post')
            ->from(Post::class, 'post');

        /** @var Query<null, Post> $query */
        $query = new DoctrineCriteriaConverter(self::CRITERIA_FIELDS)
            ->apply($criteria, $queryBuilder, 'post')
            ->getQuery();

        return $query->getResult();
    }
}
