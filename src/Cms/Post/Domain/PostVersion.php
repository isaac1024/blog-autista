<?php

declare(strict_types=1);

namespace Cms\Post\Domain;

use Cms\Shared\Domain\Seo;
use Cms\Shared\Domain\Slug;
use Doctrine\ORM\Mapping as ORM;
use Shared\Domain\RichText\RichText;

/**
 * Everything an editor writes on a post: its title, slug, where it is filed
 * and who signs it, the image it opens with, its content and how it presents
 * itself to search engines. A post holds the version being edited and, once published, the
 * version on the site.
 */
#[ORM\Embeddable]
final readonly class PostVersion
{
    public function __construct(
        #[ORM\Embedded(class: PostTitle::class, columnPrefix: false)]
        private PostTitle $title,
        #[ORM\Column(name: 'slug', type: 'cms_slug', length: Slug::MAX_LENGTH, nullable: true)]
        private ?Slug $slug,
        #[ORM\Embedded(class: Classification::class, columnPrefix: false)]
        private Classification $classification,
        /*
         * The image of the library the post opens with, which it is also
         * shared with; null while it is left without one.
         */
        #[ORM\Column(name: 'opening_image_id', type: 'cms_post_image_id', nullable: true)]
        private ?ImageId $openingImage,
        #[ORM\Column(name: 'content', type: 'rich_text')]
        private RichText $content,
        #[ORM\Embedded(class: Seo::class, columnPrefix: false)]
        private Seo $seo,
    ) {}

    /**
     * Whether it has everything the site shows of a post: a slug, a section
     * and an author, an opening image, content, and a meta description.
     * Whether the section, the author and the image are on the site is only
     * known by publishing it.
     */
    public function assertPublishable(): void
    {
        if (null === $this->slug) {
            throw InvalidPostPublication::withoutSlug();
        }

        if (null === $this->classification->section()) {
            throw InvalidPostPublication::withoutSection();
        }

        if (null === $this->classification->author()) {
            throw InvalidPostPublication::withoutAuthor();
        }

        if (null === $this->openingImage) {
            throw InvalidPostPublication::withoutOpeningImage();
        }

        if ([] === $this->content->blocks()) {
            throw InvalidPostPublication::withoutContent();
        }

        if (null === $this->seo->description()) {
            throw InvalidPostPublication::withoutMetaDescription();
        }
    }

    public function equals(self $other): bool
    {
        return $this->title->value() === $other->title->value()
            && $this->slug?->value() === $other->slug?->value()
            && $this->classification->equals($other->classification)
            && $this->openingImage?->value() === $other->openingImage?->value()
            && $this->content->toArray() === $other->content->toArray()
            && $this->seo->description() === $other->seo->description()
            && $this->seo->robots() === $other->seo->robots();
    }

    public function title(): PostTitle
    {
        return $this->title;
    }

    /**
     * Null while the post is left without one.
     */
    public function slug(): ?Slug
    {
        return $this->slug;
    }

    public function classification(): Classification
    {
        return $this->classification;
    }

    /**
     * Null while the post is left without one.
     */
    public function openingImage(): ?ImageId
    {
        return $this->openingImage;
    }

    /**
     * The images it uses: the one it opens with, if any, and those its
     * content embeds.
     *
     * @return list<string>
     */
    public function imageIds(): array
    {
        $ids = $this->content->imageIds();

        if (null !== $this->openingImage) {
            $ids[] = $this->openingImage->value();
        }

        return $ids;
    }

    public function content(): RichText
    {
        return $this->content;
    }

    public function seo(): Seo
    {
        return $this->seo;
    }
}
