<?php

declare(strict_types=1);

namespace Cms\Author\Infrastructure\Redirect;

use Cms\Author\Domain\AuthorId;
use Cms\Author\Domain\AuthorRedirects;
use Cms\Redirect\Application\TargetChecker;
use Cms\Redirect\Domain\RedirectRepository;
use Cms\Redirect\Domain\ResourceType;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;

/**
 * Outbound adapter that asks the redirects of the CMS whether any leads to
 * an author. It reads them as they are saved, not through events, so a redirect
 * just created already keeps its author on the site.
 */
#[AsAlias(AuthorRedirects::class)]
final readonly class CmsAuthorRedirects implements AuthorRedirects
{
    public function __construct(private RedirectRepository $redirectRepository) {}

    #[\Override]
    public function isTargeted(AuthorId $id): bool
    {
        return new TargetChecker(ResourceType::Author, $id->value())($this->redirectRepository);
    }
}
