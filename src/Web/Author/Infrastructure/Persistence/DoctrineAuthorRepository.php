<?php

declare(strict_types=1);

namespace Web\Author\Infrastructure\Persistence;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;
use Web\Author\Domain\Author;
use Web\Author\Domain\AuthorId;
use Web\Author\Domain\AuthorRepository;

/**
 * Outbound adapter that persists and reads the authors on the site through
 * Doctrine ORM.
 */
#[AsAlias(AuthorRepository::class)]
final readonly class DoctrineAuthorRepository implements AuthorRepository
{
    public function __construct(private EntityManagerInterface $entityManager) {}

    #[\Override]
    public function save(Author $author): void
    {
        $this->entityManager->persist($author);
        $this->entityManager->flush();
    }

    #[\Override]
    public function delete(Author $author): void
    {
        $this->entityManager->remove($author);
        $this->entityManager->flush();
    }

    #[\Override]
    public function find(AuthorId $id): ?Author
    {
        return $this->entityManager->find(Author::class, $id);
    }

    #[\Override]
    public function findBySlug(string $slug): ?Author
    {
        return $this->entityManager->getRepository(Author::class)->findOneBy(['slug' => $slug]);
    }
}
