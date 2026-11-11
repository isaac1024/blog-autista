<?php

declare(strict_types=1);

namespace Cms\Redirect\Domain;

/**
 * What publishing a resource as it is saved does to the redirects: the one
 * at the address it takes, which goes, and the address it leaves, which an
 * editor says what to do with.
 *
 * - The redirect of its own former address goes without a word: it is back
 *   where it was.
 * - Publishing goes ahead only on what the editor was shown, by its
 *   fingerprint, and only with the address it leaves decided, if any.
 */
final readonly class Publication
{
    private function __construct(
        private ?Source $next,
        private ?Redirect $replaced,
        private ?Source $left,
        private ?Redirect $leftRedirect,
    ) {}

    /**
     * What publishing the resource would do now.
     */
    public static function now(Resource $resource, RedirectRepository $redirectRepository, SiteResources $siteResources): self
    {
        $next = $siteResources->nextAddressOf($resource);
        $current = $siteResources->addressOf($resource);
        $replaced = null === $next ? null : $redirectRepository->findBySource($next);

        if (true === $replaced?->origin()->resource()?->equals($resource)) {
            $replaced = null;
        }

        $left = null === $current || null === $next || $current->equals($next) ? null : $current;

        return new self($next, $replaced, $left, null === $left ? null : $redirectRepository->findBySource($left));
    }

    /**
     * The address it will have; null when none a redirect could answer.
     */
    public function next(): ?Source
    {
        return $this->next;
    }

    /**
     * The redirect at that address, which publishing deletes; null for none,
     * or one of its own former address.
     */
    public function replaced(): ?Redirect
    {
        return $this->replaced;
    }

    /**
     * The address it leaves for another; null when it keeps its own, or had none.
     */
    public function left(): ?Source
    {
        return $this->left;
    }

    /**
     * The redirect at the address it leaves, if any.
     */
    public function leftRedirect(): ?Redirect
    {
        return $this->leftRedirect;
    }

    /**
     * Whether publishing has something to tell or ask first.
     */
    public function asksFirst(): bool
    {
        return null !== $this->replaced || null !== $this->left;
    }

    /**
     * What is told and asked, in short: empty when nothing is, and different
     * once a redirect it tells of, or an address it asks about, changed.
     */
    public function fingerprint(): string
    {
        if (!$this->asksFirst()) {
            return '';
        }

        return hash('sha256', json_encode([
            $this->next?->shown(),
            $this->left?->shown(),
            self::fingerprintOf($this->replaced),
            self::fingerprintOf($this->leftRedirect),
        ], \JSON_THROW_ON_ERROR));
    }

    /**
     * Whether publishing now is what the editor confirmed.
     *
     * @param string $fingerprint     that of what the editor was shown, empty when nothing
     * @param bool   $previousDecided whether the editor said what the address it leaves does
     *
     * @throws InvalidRedirect when it changed since, or the address it leaves wasn't decided
     */
    public function assertConfirmed(string $fingerprint, bool $previousDecided): void
    {
        if ($this->fingerprint() !== $fingerprint) {
            throw InvalidRedirect::outlookChanged();
        }

        if (null !== $this->left && !$previousDecided) {
            throw InvalidRedirect::undecidedPrevious();
        }
    }

    /**
     * @return ?list<?string>
     */
    private static function fingerprintOf(?Redirect $redirect): ?array
    {
        if (null === $redirect) {
            return null;
        }

        $target = $redirect->target()->resource();
        $origin = $redirect->origin()->resource();

        return [$redirect->id()->value(), $target?->type()->value, $target?->id(), $origin?->type()->value, $origin?->id()];
    }
}
