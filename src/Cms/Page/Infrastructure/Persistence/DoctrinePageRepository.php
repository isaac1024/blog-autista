<?php

declare(strict_types=1);

namespace Cms\Page\Infrastructure\Persistence;

use Cms\Page\Domain\Page;
use Cms\Page\Domain\PageRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;

/**
 * Outbound adapter that persists pages through Doctrine ORM.
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
}
