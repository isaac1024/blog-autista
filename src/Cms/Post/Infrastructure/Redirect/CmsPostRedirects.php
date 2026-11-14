<?php

declare(strict_types=1);

namespace Cms\Post\Infrastructure\Redirect;

use Cms\Post\Domain\PostId;
use Cms\Post\Domain\PostRedirects;
use Cms\Redirect\Application\TargetChecker;
use Cms\Redirect\Domain\RedirectRepository;
use Cms\Redirect\Domain\ResourceType;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;

/**
 * Outbound adapter that asks the redirects of the CMS whether any leads to
 * a post. It reads them as they are saved, not through events, so a redirect
 * just created already keeps its post on the site.
 */
#[AsAlias(PostRedirects::class)]
final readonly class CmsPostRedirects implements PostRedirects
{
    public function __construct(private RedirectRepository $redirectRepository) {}

    #[\Override]
    public function isTargeted(PostId $id): bool
    {
        return new TargetChecker(ResourceType::Post, $id->value())($this->redirectRepository);
    }
}
