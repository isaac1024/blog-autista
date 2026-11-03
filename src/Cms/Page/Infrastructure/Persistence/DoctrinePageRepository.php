<?php

declare(strict_types=1);

namespace Cms\Page\Infrastructure\Persistence;

use Cms\Page\Domain\Page;
use Cms\Page\Domain\PageRepository;
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
        'title' => 'title.value',
        'slug' => 'slug.value',
    ];

    public function __construct(private EntityManagerInterface $entityManager) {}

    #[\Override]
    public function save(Page $page): void
    {
        $this->entityManager->persist($page);
        $this->entityManager->flush();
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
