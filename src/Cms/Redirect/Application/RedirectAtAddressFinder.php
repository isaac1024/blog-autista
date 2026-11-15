<?php

declare(strict_types=1);

namespace Cms\Redirect\Application;

use Cms\Redirect\Domain\RedirectRepository;
use Cms\Redirect\Domain\Source;

/**
 * Finds the redirect that answers an address, if any.
 */
final readonly class RedirectAtAddressFinder
{
    public function __construct(private Source $source) {}

    public function __invoke(RedirectRepository $redirectRepository): ?RedirectResponse
    {
        $redirect = $redirectRepository->findBySource($this->source);

        return null === $redirect ? null : RedirectResponse::fromRedirect($redirect);
    }
}
