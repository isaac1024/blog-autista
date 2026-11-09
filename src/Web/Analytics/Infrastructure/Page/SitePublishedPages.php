<?php

declare(strict_types=1);

namespace Web\Analytics\Infrastructure\Page;

use Symfony\Component\DependencyInjection\Attribute\AsAlias;
use Web\Analytics\Domain\PageId;
use Web\Analytics\Domain\PublishedPages;
use Web\Page\Domain\PageId as SitePageId;
use Web\Page\Domain\PageRepository;

/**
 * Outbound adapter that tells the pages on the site by the pages the site serves.
 */
#[AsAlias(PublishedPages::class)]
final readonly class SitePublishedPages implements PublishedPages
{
    public function __construct(private PageRepository $pageRepository) {}

    #[\Override]
    public function contains(PageId $pageId): bool
    {
        return null !== $this->pageRepository->find(new SitePageId($pageId->value()));
    }
}
