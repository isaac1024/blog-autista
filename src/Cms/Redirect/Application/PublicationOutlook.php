<?php

declare(strict_types=1);

namespace Cms\Redirect\Application;

/**
 * What publishing a resource as it is saved does to the redirects: the one
 * at the address it takes, which goes, and the address it leaves, which an
 * editor says what to do with.
 */
final readonly class PublicationOutlook
{
    /**
     * @param ?string           $next             the address it will have, as shown; null when none a redirect could answer
     * @param ?RedirectResponse $replaced         the redirect at that address, which publishing deletes; null for none, or one of its own former address
     * @param ?string           $previous         the address it leaves for another, as shown; null when it keeps its own, or had none
     * @param ?RedirectResponse $previousRedirect the redirect at the address it leaves, if any
     */
    public function __construct(
        public ?string $next,
        public ?RedirectResponse $replaced,
        public ?string $previous,
        public ?RedirectResponse $previousRedirect,
    ) {}

    /**
     * Whether publishing has something to tell or ask first.
     */
    public function asksFirst(): bool
    {
        return null !== $this->replaced || null !== $this->previous;
    }

    /**
     * What was told and asked, in short, so a confirmation is known to be of
     * what publishing does now: empty when nothing is, and different once a
     * redirect it tells of, or an address it asks about, changed.
     */
    public function fingerprint(): string
    {
        if (!$this->asksFirst()) {
            return '';
        }

        return hash('sha256', json_encode([
            $this->next,
            $this->previous,
            ...array_map(
                static fn (?RedirectResponse $redirect): ?array => null === $redirect ? null : [$redirect->id, $redirect->targetType, $redirect->targetId, $redirect->originType, $redirect->originId],
                [$this->replaced, $this->previousRedirect],
            ),
        ], \JSON_THROW_ON_ERROR));
    }
}
