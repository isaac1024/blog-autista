<?php

declare(strict_types=1);

namespace Cms\Tests\Post\Domain;

use Cms\Post\Domain\Classification;
use Cms\Post\Domain\ImageId;
use Cms\Post\Domain\Post;
use Cms\Post\Domain\PostAddress;
use Cms\Post\Domain\PostStatus;
use Cms\Post\Domain\PostTitle;
use Cms\Post\Domain\PostVersion;
use Cms\Post\Domain\Timeline;
use Cms\Shared\Domain\Robots;
use Cms\Shared\Domain\Seo;
use Cms\Shared\Domain\Slug;
use Cms\Tests\Author\Domain\AuthorIdMother;
use Cms\Tests\Image\Domain\ImageIdMother;
use Cms\Tests\Taxonomy\Domain\TermIdMother;
use Cms\Tests\Taxonomy\Domain\TermMother;
use Shared\Domain\RichText\RichText;
use Shared\Tests\Domain\ClockMother;
use Shared\Tests\Domain\RichText\RichTextMother;

final class PostMother
{
    public const string TITLE = 'Mi primer día sin ruido';

    public const string SLUG = 'mi-primer-dia-sin-ruido';

    public const string SECTION = TermIdMother::DEFAULT_VALUE;

    public const string AUTHOR = AuthorIdMother::DEFAULT_VALUE;

    public const string TAG = '01a1da50-2b7c-7d01-8a3f-5c2e9b1d0a02';

    public const string OTHER_TAG = '01a1da50-2b7c-7d01-8a3f-5c2e9b1d0a03';

    /**
     * The image a post enough to publish opens with.
     */
    public const string OPENING_IMAGE = ImageIdMother::DEFAULT_VALUE;

    /**
     * Content enough to publish a post.
     */
    public const array CONTENT = [
        'version' => 1,
        'blocks' => [['type' => 'paragraph', 'text' => [['text' => 'Hoy probé los tapones en el metro.']]]],
    ];

    /**
     * A meta description enough to publish a post.
     */
    public const string META_DESCRIPTION = 'Qué cambió el primer día que salí con tapones para los oídos.';

    /**
     * A draft, unfiled, unsigned and without an opening image unless told otherwise.
     *
     * @param list<string>            $tags
     * @param array<array-key, mixed> $content in the stored rich text format
     */
    public static function create(
        string $id = PostIdMother::DEFAULT_VALUE,
        string $title = self::TITLE,
        string $slug = self::SLUG,
        string $section = '',
        string $author = '',
        array $tags = [],
        array $content = RichTextMother::EMPTY,
        ?string $metaDescription = null,
        string $updatedAt = ClockMother::NOW,
        string $openingImage = '',
    ): Post {
        return new Post(
            PostIdMother::create($id),
            self::version($title, $slug, $section, $author, $tags, $openingImage, $content, $metaDescription),
            PostStatus::Draft,
            null,
            new Timeline(ClockMother::now(), ClockMother::now($updatedAt), null, null),
            0,
            null,
        );
    }

    /**
     * A draft with everything it needs to be published, filed under the
     * section, signed by the author and opening with the image given.
     *
     * @param list<string> $tags
     */
    public static function publishable(
        string $id = PostIdMother::DEFAULT_VALUE,
        string $slug = self::SLUG,
        string $section = self::SECTION,
        string $author = self::AUTHOR,
        array $tags = [],
        string $openingImage = self::OPENING_IMAGE,
    ): Post {
        return self::create(id: $id, slug: $slug, section: $section, author: $author, tags: $tags, content: self::CONTENT, metaDescription: self::META_DESCRIPTION, openingImage: $openingImage);
    }

    /**
     * A post on the site, at the address its section slug and the day it was
     * first published give. With another published title, it is published
     * with changes: the site has the post with that title, the rest as edited.
     *
     * @param list<string> $tags
     *
     * @SuppressWarnings("PHPMD.ExcessiveParameterList")
     */
    public static function published(
        string $id = PostIdMother::DEFAULT_VALUE,
        string $title = self::TITLE,
        string $slug = self::SLUG,
        string $section = self::SECTION,
        string $author = self::AUTHOR,
        array $tags = [],
        ?string $publishedTitle = null,
        string $sectionSlug = TermMother::SLUG,
        string $updatedAt = ClockMother::NOW,
        string $firstPublishedAt = ClockMother::NOW,
        string $lastPublishedAt = ClockMother::NOW,
        int $publicationRevision = 1,
    ): Post {
        return new Post(
            PostIdMother::create($id),
            self::version($title, $slug, $section, $author, $tags, self::OPENING_IMAGE, self::CONTENT, self::META_DESCRIPTION),
            PostStatus::Published,
            self::version($publishedTitle ?? $title, $slug, $section, $author, $tags, self::OPENING_IMAGE, self::CONTENT, self::META_DESCRIPTION),
            new Timeline(
                ClockMother::now(),
                ClockMother::now($updatedAt),
                ClockMother::now($firstPublishedAt),
                ClockMother::now($lastPublishedAt),
            ),
            $publicationRevision,
            new PostAddress(new Slug($sectionSlug), ClockMother::now($firstPublishedAt), new Slug($slug)),
        );
    }

    /**
     * A post taken off the site, which keeps the version and the address it
     * last had there; with no last address, another post took it since.
     */
    public static function unpublished(
        string $id = PostIdMother::DEFAULT_VALUE,
        string $firstPublishedAt = ClockMother::NOW,
        int $publicationRevision = 2,
        bool $lastAddress = true,
    ): Post {
        $version = self::version(self::TITLE, self::SLUG, self::SECTION, self::AUTHOR, [], self::OPENING_IMAGE, self::CONTENT, self::META_DESCRIPTION);

        return new Post(
            PostIdMother::create($id),
            $version,
            PostStatus::Unpublished,
            $version,
            new Timeline(ClockMother::now(), ClockMother::now(), ClockMother::now($firstPublishedAt), ClockMother::now($firstPublishedAt)),
            $publicationRevision,
            $lastAddress ? new PostAddress(new Slug(TermMother::SLUG), ClockMother::now($firstPublishedAt), new Slug(self::SLUG)) : null,
        );
    }

    /**
     * @param list<string>            $tags
     * @param array<array-key, mixed> $content
     */
    private static function version(
        string $title,
        string $slug,
        string $section,
        string $author,
        array $tags,
        string $openingImage,
        array $content,
        ?string $metaDescription,
    ): PostVersion {
        return new PostVersion(
            new PostTitle($title),
            '' === $slug ? null : new Slug($slug),
            Classification::fromPrimitives($section, $author, $tags),
            '' === $openingImage ? null : new ImageId($openingImage),
            RichText::fromArray($content),
            new Seo($metaDescription, Robots::IndexFollow),
        );
    }
}
