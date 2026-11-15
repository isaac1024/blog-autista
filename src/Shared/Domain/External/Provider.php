<?php

declare(strict_types=1);

namespace Shared\Domain\External;

/**
 * A service whose videos, posts or audios the site embeds behind a facade:
 * which of its addresses name one, the locator it keeps of each, and how to
 * embed it from that locator alone.
 *
 * A locator is what the provider needs to name one of its resources, such
 * as the id of a video, written as a single string the provider reads back.
 */
interface Provider
{
    /**
     * The name it is known by, in lowercase, such as "youtube": the reader
     * agrees to load the resources of each provider apart, by this name.
     */
    public function name(): string;

    /**
     * Its name as people write it, such as "YouTube".
     */
    public function label(): string;

    public function kind(): ResourceKind;

    /**
     * Whether the provider tells an image of its resources, which their
     * facade shows: a resource of one that tells none goes without.
     */
    public function tellsImage(): bool;

    /**
     * The locator of the resource the address names, or null when it names
     * none of this provider's that the site embeds.
     */
    public function locate(WebAddress $address): ?string;

    /**
     * Whether the locator names a resource of this provider, as locate() or
     * the provider itself gives it.
     */
    public function isLocator(string $locator): bool;

    /**
     * Whether the frame can be built from the locator alone. Some locators
     * read from an address need the provider to resolve them first.
     */
    public function isEmbeddable(string $locator): bool;

    /**
     * The address people visit to see the resource on the provider.
     */
    public function url(string $locator): string;

    /**
     * The address of the frame that embeds the resource.
     */
    public function embedUrl(string $locator): string;

    /**
     * The origin the frame is served from, which the page lets frame it and
     * whose messages alone it listens to.
     */
    public function frameOrigin(string $locator): string;

    public function space(string $locator): EmbedSpace;

    /**
     * Where the provider tells what it does with what it learns of the
     * reader, for the notice that asks to load it.
     */
    public function privacyPolicyUrl(string $locator): string;
}
