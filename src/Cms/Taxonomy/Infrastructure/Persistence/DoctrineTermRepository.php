<?php

declare(strict_types=1);

namespace Cms\Taxonomy\Infrastructure\Persistence;

use Cms\Shared\Domain\Slug;
use Cms\Taxonomy\Domain\Taxonomy;
use Cms\Taxonomy\Domain\Term;
use Cms\Taxonomy\Domain\TermId;
use Cms\Taxonomy\Domain\TermRepository;
use Cms\Taxonomy\Domain\TermSlugAlreadyTaken;
use Cms\Taxonomy\Domain\TermStatus;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query;
use Shared\Domain\Criteria\Criteria;
use Shared\Infrastructure\Persistence\Doctrine\DoctrineCriteriaConverter;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;

/**
 * Outbound adapter that persists and reads terms through Doctrine ORM.
 */
#[AsAlias(TermRepository::class)]
final readonly class DoctrineTermRepository implements TermRepository
{
    /**
     * Criteria field names mapped onto the Term properties, embeddables included.
     */
    private const array CRITERIA_FIELDS = [
        'id' => 'id',
        'taxonomy' => 'taxonomy',
        'name' => 'version.name.value',
    ];

    public function __construct(private EntityManagerInterface $entityManager) {}

    #[\Override]
    public function save(Term $term): void
    {
        $this->entityManager->persist($term);

        try {
            $this->entityManager->flush();
        } catch (UniqueConstraintViolationException $exception) {
            throw self::taken($term, $exception);
        }
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
    public function findBySlug(Taxonomy $taxonomy, Slug $slug): ?Term
    {
        // Terms of a taxonomy never share a slug, being edited or on the
        // site, so at most one term has it.
        /** @var Query<null, Term> $query */
        $query = $this->entityManager->createQueryBuilder()
            ->select('term')
            ->from(Term::class, 'term')
            ->where('term.taxonomy = :taxonomy')
            ->andWhere('term.version.slug = :slug OR term.publishedSlug = :slug')
            ->setParameter('taxonomy', $taxonomy->value)
            ->setParameter('slug', $slug, 'cms_slug')
            ->setMaxResults(1)
            ->getQuery();

        return $query->getResult()[0] ?? null;
    }

    #[\Override]
    public function findOnSiteBySlug(Taxonomy $taxonomy, Slug $slug): ?Term
    {
        return $this->entityManager->getRepository(Term::class)->findOneBy(['taxonomy' => $taxonomy, 'publishedSlug' => $slug]);
    }

    #[\Override]
    public function findOffSiteByLastSiteSlug(Taxonomy $taxonomy, Slug $slug): ?Term
    {
        // A term published at an address takes it from any other, so one
        // term off the site at most last had it; the newest wins otherwise.
        return $this->entityManager->getRepository(Term::class)->findOneBy(
            ['taxonomy' => $taxonomy, 'lastSiteSlug' => $slug, 'status' => TermStatus::Unpublished],
            ['id' => 'DESC'],
        );
    }

    #[\Override]
    public function matching(Criteria $criteria): array
    {
        return new DoctrineCriteriaConverter(self::CRITERIA_FIELDS)->matching($this->entityManager, Term::class, 'term', $criteria);
    }

    /**
     * Besides the time-ordered id, the slugs are the only unique columns, so
     * a violation means another term took one of them since it was checked.
     * The index it names tells which.
     */
    private static function taken(Term $term, UniqueConstraintViolationException $exception): \Throwable
    {
        $slug = str_contains($exception->getMessage(), 'uniq_cms_term_published_slug') ? $term->publishedSlug() : $term->slug();

        return null === $slug ? $exception : TermSlugAlreadyTaken::bySlug($slug, $exception);
    }
}
