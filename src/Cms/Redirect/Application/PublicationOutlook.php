<?php

declare(strict_types=1);

namespace Cms\Redirect\Application;

use Cms\Redirect\Domain\Publication;

/**
 * What publishing a resource as it is saved does to the redirects, as the
 * CMS shows it: the one at the address it takes, which goes, and the address
 * it leaves, which an editor says what to do with.
 */
final readonly class PublicationOutlook
{
    /**
     * @param ?string           $next             the address it will have, as shown; null when none a redirect could answer
     * @param ?RedirectResponse $replaced         the redirect at that address, which publishing deletes; null for none, or one of its own former address
     * @param ?string           $previous         the address it leaves for another, as shown; null when it keeps its own, or had none
     * @param ?RedirectResponse $previousRedirect the redirect at the address it leaves, if any
     * @param bool              $asksFirst        whether publishing has something to tell or ask first
     * @param string            $fingerprint      what was told and asked, in short, so a confirmation is known to be of what publishing does now; empty when nothing is
     */
    public function __construct(
        public ?string $next,
        public ?RedirectResponse $replaced,
        public ?string $previous,
        public ?RedirectResponse $previousRedirect,
        public bool $asksFirst,
        public string $fingerprint,
    ) {}

    public static function fromPublication(Publication $publication): self
    {
        $replaced = $publication->replaced();
        $leftRedirect = $publication->leftRedirect();

        return new self(
            $publication->next()?->shown(),
            null === $replaced ? null : RedirectResponse::fromRedirect($replaced),
            $publication->left()?->shown(),
            null === $leftRedirect ? null : RedirectResponse::fromRedirect($leftRedirect),
            $publication->asksFirst(),
            $publication->fingerprint(),
        );
    }
}
