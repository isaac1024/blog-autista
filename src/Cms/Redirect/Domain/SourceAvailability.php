<?php

declare(strict_types=1);

namespace Cms\Redirect\Domain;

/**
 * Checks that no redirect answers the address of the given one yet, so an
 * address has one redirect at most.
 *
 * The unique index on the address still guards against two saves racing.
 */
final readonly class SourceAvailability
{
    public function __construct(private Redirect $redirect) {}

    public function __invoke(RedirectRepository $redirectRepository): void
    {
        $owner = $redirectRepository->findBySource($this->redirect->source());

        if (null !== $owner && !$owner->id()->equals($this->redirect->id())) {
            throw InvalidRedirect::sourceTaken($this->redirect->source());
        }
    }
}
