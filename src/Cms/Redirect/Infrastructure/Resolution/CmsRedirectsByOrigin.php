<?php

declare(strict_types=1);

namespace Cms\Redirect\Infrastructure\Resolution;

use Cms\Redirect\Application\OriginTargetsFinder;
use Cms\Redirect\Domain\RedirectRepository;
use Cms\Redirect\Domain\ResourceType;
use Shared\Domain\Redirect\RedirectsByOrigin;

/**
 * Outbound adapter that answers from the redirects of the CMS, so a preview
 * follows them as they are saved, as the site does.
 */
final readonly class CmsRedirectsByOrigin implements RedirectsByOrigin
{
    public function __construct(private RedirectRepository $redirectRepository) {}

    #[\Override]
    public function targetsOf(string $type, array $ids): array
    {
        $resourceType = ResourceType::tryFrom($type);

        return null === $resourceType ? [] : new OriginTargetsFinder($resourceType, $ids)($this->redirectRepository);
    }
}
