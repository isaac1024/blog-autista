<?php

declare(strict_types=1);

namespace Web\Post\Infrastructure\Persistence;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;
use Web\Post\Domain\Post;
use Web\Post\Domain\PostId;
use Web\Post\Domain\PostRepository;

/**
 * Outbound adapter that persists and reads the posts on the site through
 * Doctrine ORM.
 */
#[AsAlias(PostRepository::class)]
final readonly class DoctrinePostRepository implements PostRepository
{
    public function __construct(private EntityManagerInterface $entityManager) {}

    #[\Override]
    public function save(Post $post): void
    {
        $this->entityManager->persist($post);
        $this->entityManager->flush();
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
    public function findAt(string $section, \DateTimeImmutable $day, string $slug): ?Post
    {
        return $this->entityManager->getRepository(Post::class)->findOneBy(['sectionSlug' => $section, 'day' => $day, 'slug' => $slug]);
    }
}
