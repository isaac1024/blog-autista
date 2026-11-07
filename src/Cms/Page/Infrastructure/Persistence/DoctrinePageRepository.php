<?php

declare(strict_types=1);

namespace Cms\Page\Infrastructure\Persistence;

use Cms\Page\Domain\Page;
use Cms\Page\Domain\PageId;
use Cms\Page\Domain\PageRepository;
use Cms\Page\Domain\PageSlug;
use Cms\Page\Domain\PageType;
use Cms\Page\Domain\SlugAlreadyTaken;
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

        // The slugs are the only unique columns besides the time-ordered id, so
        // a violation means another page took the slug since it was checked.
        try {
            $this->entityManager->flush();
        } catch (UniqueConstraintViolationException $exception) {
            throw SlugAlreadyTaken::bySlug($page->slug(), $exception);
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
    public function findBySlug(PageSlug $slug): ?Page
    {
        // Pages never share a slug, being edited or on the site, so at most
        // one page has it.
        /** @var Query<null, Page> $query */
        $query = $this->entityManager->createQueryBuilder()
            ->select('page')
            ->from(Page::class, 'page')
            ->where('page.version.slug = :slug OR page.publishedSlug = :slug')
            ->setParameter('slug', $slug, 'cms_page_slug')
            ->setMaxResults(1)
            ->getQuery();

        return $query->getResult()[0] ?? null;
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
}
