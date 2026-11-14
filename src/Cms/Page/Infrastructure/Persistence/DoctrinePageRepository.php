<?php

declare(strict_types=1);

namespace Cms\Page\Infrastructure\Persistence;

use Cms\Page\Domain\Page;
use Cms\Page\Domain\PageId;
use Cms\Page\Domain\PageRepository;
use Cms\Page\Domain\PageStatus;
use Cms\Page\Domain\PageType;
use Cms\Page\Domain\SlugAlreadyTaken;
use Cms\Page\Domain\UniquePageAlreadyExists;
use Cms\Shared\Domain\Slug;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query;
use Shared\Domain\Criteria\Criteria;
use Shared\Infrastructure\Persistence\Doctrine\DoctrineCriteriaConverter;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;

/**
 * Outbound adapter that persists and reads pages through Doctrine ORM.
 */
#[AsAlias(PageRepository::class)]
final readonly class DoctrinePageRepository implements PageRepository
{
    /**
     * Criteria field names mapped onto the Page properties, embeddables included.
     */
    private const array CRITERIA_FIELDS = [
        'id' => 'id',
        'title' => 'version.title.value',
        'slug' => 'version.slug',
    ];

    public function __construct(private EntityManagerInterface $entityManager) {}

    #[\Override]
    public function save(Page $page): void
    {
        $this->entityManager->persist($page);

        try {
            $this->entityManager->flush();
        } catch (UniqueConstraintViolationException $exception) {
            throw self::taken($page, $exception);
        }
    }

    #[\Override]
    public function delete(Page $page): void
    {
        $this->entityManager->remove($page);
        $this->entityManager->flush();
    }

    #[\Override]
    public function find(PageId $id): ?Page
    {
        return $this->entityManager->find(Page::class, $id);
    }

    #[\Override]
    public function findByIds(array $ids): array
    {
        if ([] === $ids) {
            return [];
        }

        /** @var Query<null, Page> $query */
        $query = $this->entityManager->createQueryBuilder()
            ->select('page')
            ->from(Page::class, 'page')
            ->where('page.id IN (:ids)')
            ->setParameter('ids', array_map(static fn (PageId $id): string => $id->value(), $ids))
            ->getQuery();

        return $query->getResult();
    }

    #[\Override]
    public function findBySlug(Slug $slug): ?Page
    {
        // Pages never share a slug, being edited or on the site, so at most
        // one page has it.
        /** @var Query<null, Page> $query */
        $query = $this->entityManager->createQueryBuilder()
            ->select('page')
            ->from(Page::class, 'page')
            ->where('page.version.slug = :slug OR page.publishedSlug = :slug')
            ->setParameter('slug', $slug, 'cms_slug')
            ->setMaxResults(1)
            ->getQuery();

        return $query->getResult()[0] ?? null;
    }

    #[\Override]
    public function findOnSiteBySlug(Slug $slug): ?Page
    {
        return $this->entityManager->getRepository(Page::class)->findOneBy(['publishedSlug' => $slug]);
    }

    #[\Override]
    public function findOffSiteByLastSiteSlug(Slug $slug): ?Page
    {
        // A page published at an address takes it from any other, so one
        // page off the site at most last had it; the newest wins otherwise.
        return $this->entityManager->getRepository(Page::class)->findOneBy(
            ['lastSiteSlug' => $slug, 'status' => PageStatus::Unpublished],
            ['id' => 'DESC'],
        );
    }

    #[\Override]
    public function findByType(PageType $type): ?Page
    {
        return $this->entityManager->getRepository(Page::class)->findOneBy(['type' => $type]);
    }

    #[\Override]
    public function matching(Criteria $criteria): array
    {
        $queryBuilder = $this->entityManager->createQueryBuilder()
            ->select('page')
            ->from(Page::class, 'page');

        /** @var Query<null, Page> $query */
        $query = new DoctrineCriteriaConverter(self::CRITERIA_FIELDS)
            ->apply($criteria, $queryBuilder, 'page')
            ->getQuery();

        return $query->getResult();
    }

    /**
     * Besides the time-ordered id, the slugs and the unique type are the only
     * unique columns, so a violation means another page took one of them
     * since it was checked. The index it names tells which.
     */
    private static function taken(Page $page, UniqueConstraintViolationException $exception): \Throwable
    {
        $message = $exception->getMessage();

        if (str_contains($message, 'uniq_cms_page_unique_type')) {
            return UniquePageAlreadyExists::ofType($page->type(), $exception);
        }

        $slug = str_contains($message, 'uniq_cms_page_published_slug') ? $page->publishedSlug() : $page->slug();

        return null === $slug ? $exception : SlugAlreadyTaken::bySlug($slug, $exception);
    }
}
