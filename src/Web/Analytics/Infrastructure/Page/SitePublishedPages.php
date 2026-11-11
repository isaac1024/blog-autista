<?php

declare(strict_types=1);

namespace Web\Analytics\Infrastructure\Page;

use Symfony\Component\DependencyInjection\Attribute\AsAlias;
use Web\Analytics\Domain\PageId;
use Web\Analytics\Domain\PublishedPages;
use Web\Page\Application\PublishedPagesFinder;
use Web\Page\Domain\PageRepository;

/**
 * Outbound adapter that asks the pages the site serves whether a page is
 * on it.
 */
#[AsAlias(PublishedPages::class)]
final readonly class SitePublishedPages implements PublishedPages
{
    public function __construct(private PageRepository $pageRepository) {}

    #[\Override]
    public function contains(PageId $pageId): bool
    {
        return [] !== new PublishedPagesFinder([$pageId->value()])($this->pageRepository);
    }
}
