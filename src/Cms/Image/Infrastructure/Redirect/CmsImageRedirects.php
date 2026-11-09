<?php

declare(strict_types=1);

namespace Cms\Image\Infrastructure\Redirect;

use Cms\Image\Domain\ImageId;
use Cms\Image\Domain\ImageRedirects;
use Cms\Redirect\Application\TargetChecker;
use Cms\Redirect\Domain\RedirectRepository;
use Cms\Redirect\Domain\ResourceType;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;

/**
 * Outbound adapter that asks the redirects of the CMS whether any leads to
 * an image. It reads them as they are saved, not through events, so a
 * redirect just created already keeps its image on the site.
 */
#[AsAlias(ImageRedirects::class)]
final readonly class CmsImageRedirects implements ImageRedirects
{
    public function __construct(private RedirectRepository $redirectRepository) {}

    #[\Override]
    public function isTargeted(ImageId $id): bool
    {
        return new TargetChecker(ResourceType::Image, $id->value())($this->redirectRepository);
    }
}
