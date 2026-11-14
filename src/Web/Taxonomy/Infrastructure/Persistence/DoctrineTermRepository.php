<?php

declare(strict_types=1);

namespace Web\Taxonomy\Infrastructure\Persistence;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;
use Web\Taxonomy\Domain\Taxonomy;
use Web\Taxonomy\Domain\Term;
use Web\Taxonomy\Domain\TermId;
use Web\Taxonomy\Domain\TermRepository;

/**
 * Outbound adapter that persists and reads the terms on the site through
 * Doctrine ORM.
 */
#[AsAlias(TermRepository::class)]
final readonly class DoctrineTermRepository implements TermRepository
{
    public function __construct(private EntityManagerInterface $entityManager) {}

    #[\Override]
    public function save(Term $term): void
    {
        $this->entityManager->persist($term);
        $this->entityManager->flush();
    }

    #[\Override]
    public function delete(Term $term): void
    {
        $this->entityManager->remove($term);
        $this->entityManager->flush();
    }

    #[\Override]
    public function find(TermId $id): ?Term
    {
        return $this->entityManager->find(Term::class, $id);
    }

    #[\Override]
    public function findBySlug(Taxonomy $taxonomy, string $slug): ?Term
    {
        return $this->entityManager->getRepository(Term::class)->findOneBy(['taxonomy' => $taxonomy, 'slug' => $slug]);
    }
}
