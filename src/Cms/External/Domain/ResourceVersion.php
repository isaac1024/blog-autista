<?php

declare(strict_types=1);

namespace Cms\External\Domain;

use Shared\Domain\External\EmbedSpace;
use Shared\Domain\External\ImageShape;
use Shared\Domain\External\Provider;
use Shared\Domain\External\ResourceKind;

/**
 * Everything an editor sets of an external resource: its address, how its
 * provider names it, if it embeds it, its title, its summary, its language,
 * its details and its image. A resource holds the version being edited and,
 * once published, the version on the site.
 *
 * It holds everything an editor sets, one parameter each.
 *
 * @SuppressWarnings("PHPMD.ExcessiveParameterList")
 */
final readonly class ResourceVersion
{
    public const int SUMMARY_MAX_LENGTH = 300;

    private ?string $summary;

    /**
     * @param ?string $locator how its provider names it, null for a resource no provider embeds
     */
    public function __construct(
        private ResourceUrl $url,
        private ?string $locator,
        private ResourceTitle $title,
        ?string $summary,
        private Language $language,
        private ResourceDetails $details,
        private ?ResourceImagePath $image,
    ) {
        $summary = trim((string) preg_replace('/\s+/u', ' ', (string) $summary));
        $this->summary = DetailText::line('summary', '' === $summary ? null : $summary, self::SUMMARY_MAX_LENGTH);
    }

    /**
     * The version a reading tells, at the address, in the language it tells
     * or, when it tells none, the one given. It has no image yet.
     */
    public static function read(ResourceUrl $url, Reading $reading, Language $language): self
    {
        return new self(
            $url,
            $reading->locator,
            ResourceTitle::read($reading->title, $url),
            null === $reading->summary ? null : mb_substr($reading->summary, 0, self::SUMMARY_MAX_LENGTH),
            $reading->language ?? $language,
            ResourceDetails::fromPrimitives($reading->kind, $reading->details),
            null,
        );
    }

    /**
     * The shape of the image of a resource of the kind, by the provider
     * given, if any: upright for an upright video, square for an audio,
     * wide for anything else, and none for a post, whose facade is its text.
     */
    public function imageShape(ResourceKind $kind, ?Provider $provider): ?ImageShape
    {
        return match ($kind) {
            ResourceKind::SocialPost => null,
            ResourceKind::Audio => ImageShape::Square,
            ResourceKind::Video => null !== $this->locator && EmbedSpace::Portrait === $provider?->space($this->locator) ? ImageShape::Portrait : ImageShape::Landscape,
            ResourceKind::Article, ResourceKind::Study, ResourceKind::Document => ImageShape::Landscape,
        };
    }

    /**
     * Whether it has everything the site shows of a resource of the kind: the
     * details its card or its facade tells, the image of a video or an
     * audio whose provider tells one, and, for a resource a provider embeds, a locator the provider
     * builds its frame from.
     */
    public function assertPublishable(ResourceKind $kind, ?Provider $provider): void
    {
        $this->details->assertPublishable();

        if (\in_array($kind, [ResourceKind::Video, ResourceKind::Audio], true) && null === $this->image && (null === $provider || $provider->tellsImage())) {
            throw InvalidResourcePublication::withoutImage();
        }

        if (null !== $provider && (null === $this->locator || !$provider->isEmbeddable($this->locator))) {
            throw InvalidResourcePublication::notEmbeddable();
        }
    }

    /**
     * The same version with another image, or none.
     */
    public function withImage(?ResourceImagePath $image): self
    {
        return new self($this->url, $this->locator, $this->title, $this->summary, $this->language, $this->details, $image);
    }

    public function equals(self $other): bool
    {
        return $this->url->equals($other->url)
            && $this->locator === $other->locator
            && $this->title->value() === $other->title->value()
            && $this->summary === $other->summary
            && $this->language === $other->language
            && $this->details->equals($other->details)
            && $this->image?->value() === $other->image?->value();
    }

    public function url(): ResourceUrl
    {
        return $this->url;
    }

    public function locator(): ?string
    {
        return $this->locator;
    }

    public function title(): ResourceTitle
    {
        return $this->title;
    }

    public function summary(): ?string
    {
        return $this->summary;
    }

    public function language(): Language
    {
        return $this->language;
    }

    public function details(): ResourceDetails
    {
        return $this->details;
    }

    public function image(): ?ResourceImagePath
    {
        return $this->image;
    }
}
