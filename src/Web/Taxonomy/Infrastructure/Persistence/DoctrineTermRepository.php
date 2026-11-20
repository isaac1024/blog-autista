<?php

declare(strict_types=1);

namespace Web\Taxonomy\Infrastructure\Persistence;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;
use Web\Taxonomy\Domain\SectionSlug;
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
    public function findByIds(array $ids): array
    {
        // A term already read in this request, such as the section of the
        // page of a section, is taken as read, as find() takes it: only the
        // others are asked for, if any.
        $unitOfWork = $this->entityManager->getUnitOfWork();
        $loaded = [];
        $missing = [];

        foreach ($ids as $id) {
            $term = $unitOfWork->tryGetById($id->value(), Term::class);

            if ($term instanceof Term) {
                $loaded[] = $term;
            } else {
                $missing[] = $id->value();
            }
        }

        if ([] === $missing) {
            return $loaded;
        }

        /** @var list<Term> $terms */
        $terms = $this->entityManager->createQueryBuilder()
            ->select('term')
            ->from(Term::class, 'term')
            ->where('term.id IN (:ids)')
            ->setParameter('ids', $missing)
            ->getQuery()
            ->getResult();

        return [...$loaded, ...$terms];
    }

    #[\Override]
    public function findBySlug(Taxonomy $taxonomy, string $slug): ?Term
    {
        return $this->entityManager->getRepository(Term::class)->findOneBy(['taxonomy' => $taxonomy, 'slug' => $slug]);
    }

    #[\Override]
    public function saveSectionSlug(SectionSlug $sectionSlug): void
    {
        $this->entityManager->persist($sectionSlug);
        $this->entityManager->flush();
    }

    #[\Override]
    public function findSectionSlug(string $sectionId): ?SectionSlug
    {
        return $this->entityManager->find(SectionSlug::class, $sectionId);
    }

    #[\Override]
    public function sectionSlugs(array $sectionIds): array
    {
        /** @var list<array{section_id: string, slug: string}> $rows */
        $rows = $this->entityManager->getConnection()->fetchAllAssociative(
            'SELECT section_id, slug FROM web_section_slug WHERE section_id IN (:ids)',
            ['ids' => $sectionIds],
            ['ids' => ArrayParameterType::STRING],
        );

        return array_column($rows, 'slug', 'section_id');
    }
}
