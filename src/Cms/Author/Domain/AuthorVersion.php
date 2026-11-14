<?php

declare(strict_types=1);

namespace Cms\Author\Domain;

use Cms\Shared\Domain\Seo;
use Cms\Shared\Domain\Slug;
use Doctrine\ORM\Mapping as ORM;
use Shared\Domain\RichText\RichText;

/**
 * Everything an editor writes of an author: the name, the address, the
 * avatar, the bio and how the author's page presents itself to search
 * engines. An author holds the version being edited and, once published,
 * the version on the site.
 */
#[ORM\Embeddable]
final readonly class AuthorVersion
{
    public function __construct(
        #[ORM\Embedded(class: AuthorName::class, columnPrefix: false)]
        private AuthorName $name,
        #[ORM\Column(name: 'slug', type: 'cms_slug', length: Slug::MAX_LENGTH, nullable: true)]
        private ?Slug $slug,
        #[ORM\Column(name: 'avatar', type: 'cms_author_avatar', length: AvatarPath::MAX_LENGTH, nullable: true)]
        private ?AvatarPath $avatar,
        #[ORM\Column(name: 'bio', type: 'rich_text')]
        private RichText $bio,
        #[ORM\Embedded(class: Seo::class, columnPrefix: false)]
        private Seo $seo,
    ) {}

    /**
     * Whether it has everything the site shows of an author: a slug to be
     * addressed by, an avatar, a bio, and a meta description to summarise
     * the page in search results. The name and the robots directive always
     * have a value.
     */
    public function assertPublishable(): void
    {
        if (null === $this->slug) {
            throw InvalidAuthorPublication::withoutSlug();
        }

        if (null === $this->avatar) {
            throw InvalidAuthorPublication::withoutAvatar();
        }

        if ([] === $this->bio->blocks()) {
            throw InvalidAuthorPublication::withoutBio();
        }

        if (null === $this->seo->description()) {
            throw InvalidAuthorPublication::withoutMetaDescription();
        }
    }

    /**
     * The same version with another avatar.
     */
    public function withAvatar(AvatarPath $avatar): self
    {
        return new self($this->name, $this->slug, $avatar, $this->bio, $this->seo);
    }

    public function equals(self $other): bool
    {
        return $this->name->value() === $other->name->value()
            && $this->slug?->value() === $other->slug?->value()
            && $this->avatar?->value() === $other->avatar?->value()
            && $this->bio->toArray() === $other->bio->toArray()
            && $this->seo->description() === $other->seo->description()
            && $this->seo->robots() === $other->seo->robots();
    }

    public function name(): AuthorName
    {
        return $this->name;
    }

    /**
     * Null while the author is left without one.
     */
    public function slug(): ?Slug
    {
        return $this->slug;
    }

    /**
     * Null until one is uploaded.
     */
    public function avatar(): ?AvatarPath
    {
        return $this->avatar;
    }

    public function bio(): RichText
    {
        return $this->bio;
    }

    public function seo(): Seo
    {
        return $this->seo;
    }
}
