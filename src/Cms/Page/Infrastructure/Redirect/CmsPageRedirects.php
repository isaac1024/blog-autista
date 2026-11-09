<?php

declare(strict_types=1);

namespace Cms\Page\Infrastructure\Redirect;

use Cms\Page\Domain\PageId;
use Cms\Page\Domain\PageRedirects;
use Cms\Redirect\Application\TargetChecker;
use Cms\Redirect\Domain\RedirectRepository;
use Cms\Redirect\Domain\ResourceType;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;

/**
 * Outbound adapter that asks the redirects of the CMS whether any leads to a
 * page. It reads them as they are saved, not through events, so a redirect
 * just created already keeps its page on the site.
 */
#[AsAlias(PageRedirects::class)]
final readonly class CmsPageRedirects implements PageRedirects
{
    public function __construct(private RedirectRepository $redirectRepository) {}

    #[\Override]
    public function isTargeted(PageId $id): bool
    {
        return new TargetChecker(ResourceType::Page, $id->value())($this->redirectRepository);
    }
}
