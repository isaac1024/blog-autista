<?php

declare(strict_types=1);

namespace Web\Redirect\Infrastructure\Resolution;

use Shared\Domain\Redirect\RedirectsByOrigin;
use Web\Redirect\Application\OriginTargetsFinder;
use Web\Redirect\Domain\RedirectRepository;

/**
 * Outbound adapter that answers from the redirects of the site, so what the
 * site paints follows them as the site has them.
 */
final readonly class SiteRedirectsByOrigin implements RedirectsByOrigin
{
    public function __construct(private RedirectRepository $redirectRepository) {}

    #[\Override]
    public function targetsOf(string $type, array $ids): array
    {
        return new OriginTargetsFinder($type, $ids)($this->redirectRepository);
    }
}
