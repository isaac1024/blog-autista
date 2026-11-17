<?php

declare(strict_types=1);

namespace Cms\Author\Infrastructure\Persistence;

use Cms\Author\Domain\Author;
use Cms\Author\Domain\AuthorId;
use Cms\Author\Domain\AuthorRepository;
use Cms\Author\Domain\AuthorSlugAlreadyTaken;
use Cms\Author\Domain\AuthorStatus;
use Cms\Shared\Domain\Slug;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query;
use Shared\Domain\Criteria\Criteria;
use Shared\Infrastructure\Persistence\Doctrine\DoctrineCriteriaConverter;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;

/**
 * Outbound adapter that persists and reads authors through Doctrine ORM.
 */
#[AsAlias(AuthorRepository::class)]
final readonly class DoctrineAuthorRepository implements AuthorRepository
{
    /**
     * Criteria field names mapped onto the Author properties, embeddables included.
     */
    private const array CRITERIA_FIELDS = [
        'id' => 'id',
        'name' => 'version.name.value',
    ];

    public function __construct(private EntityManagerInterface $entityManager) {}

    #[\Override]
    public function save(Author $author): void
    {
        $this->entityManager->persist($author);

        try {
            $this->entityManager->flush();
        } catch (UniqueConstraintViolationException $exception) {
            throw self::taken($author, $exception);
        }
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
    public function findByIds(array $ids): array
    {
        if ([] === $ids) {
            return [];
        }

        /** @var Query<null, Author> $query */
        $query = $this->entityManager->createQueryBuilder()
            ->select('author')
            ->from(Author::class, 'author')
            ->where('author.id IN (:ids)')
            ->setParameter('ids', array_map(static fn (AuthorId $id): string => $id->value(), $ids))
            ->getQuery();

        return $query->getResult();
    }

    #[\Override]
    public function findBySlug(Slug $slug): ?Author
    {
        // Authors never share a slug, being edited or on the site, so at
        // most one author has it.
        /** @var Query<null, Author> $query */
        $query = $this->entityManager->createQueryBuilder()
            ->select('author')
            ->from(Author::class, 'author')
            ->where('author.version.slug = :slug OR author.publishedSlug = :slug')
            ->setParameter('slug', $slug, 'cms_slug')
            ->setMaxResults(1)
            ->getQuery();

        return $query->getResult()[0] ?? null;
    }

    #[\Override]
    public function findOnSiteBySlug(Slug $slug): ?Author
    {
        return $this->entityManager->getRepository(Author::class)->findOneBy(['publishedSlug' => $slug]);
    }

    #[\Override]
    public function findOffSiteByLastSiteSlug(Slug $slug): ?Author
    {
        // An author published at an address takes it from any other, so one
        // author off the site at most last had it; the newest wins otherwise.
        return $this->entityManager->getRepository(Author::class)->findOneBy(
            ['lastSiteSlug' => $slug, 'status' => AuthorStatus::Unpublished],
            ['id' => 'DESC'],
        );
    }

    #[\Override]
    public function matching(Criteria $criteria): array
    {
        return new DoctrineCriteriaConverter(self::CRITERIA_FIELDS)->matching($this->entityManager, Author::class, 'author', $criteria);
    }

    /**
     * Besides the time-ordered id, the slugs are the only unique columns, so
     * a violation means another author took one of them since it was
     * checked. The index it names tells which.
     */
    private static function taken(Author $author, UniqueConstraintViolationException $exception): \Throwable
    {
        $slug = str_contains($exception->getMessage(), 'uniq_cms_author_published_slug') ? $author->publishedSlug() : $author->slug();

        return null === $slug ? $exception : AuthorSlugAlreadyTaken::bySlug($slug, $exception);
    }
}
