<?php

declare(strict_types=1);

namespace Web\Page\Infrastructure\Persistence;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;
use Web\Page\Domain\Page;
use Web\Page\Domain\PageId;
use Web\Page\Domain\PageRepository;
use Web\Page\Domain\PageType;

/**
 * Outbound adapter that persists and reads the pages on the site through
 * Doctrine ORM.
 */
#[AsAlias(PageRepository::class)]
final readonly class DoctrinePageRepository implements PageRepository
{
    public function __construct(private EntityManagerInterface $entityManager) {}

    #[\Override]
    public function save(Page $page): void
    {
        $this->entityManager->persist($page);
        $this->entityManager->flush();
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

        /** @var list<Page> $pages */
        $pages = $this->entityManager->createQueryBuilder()
            ->select('page')
            ->from(Page::class, 'page')
            ->where('page.id IN (:ids)')
            ->setParameter('ids', array_map(static fn (PageId $id): string => $id->value(), $ids))
            ->getQuery()
            ->getResult();

        return $pages;
    }

    #[\Override]
    public function findBySlug(string $slug): ?Page
    {
        return $this->entityManager->getRepository(Page::class)->findOneBy(['slug' => $slug]);
    }

    #[\Override]
    public function findHome(): ?Page
    {
        // The CMS keeps the site to one home page.
        return $this->entityManager->getRepository(Page::class)->findOneBy(['type' => PageType::Home]);
    }
}
