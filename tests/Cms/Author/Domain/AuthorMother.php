<?php

declare(strict_types=1);

namespace Cms\Tests\Author\Domain;

use Cms\Author\Domain\Author;
use Cms\Author\Domain\AuthorName;
use Cms\Author\Domain\AuthorStatus;
use Cms\Author\Domain\AuthorVersion;
use Cms\Author\Domain\AvatarPath;
use Cms\Author\Domain\Timeline;
use Cms\Shared\Domain\Robots;
use Cms\Shared\Domain\Seo;
use Cms\Shared\Domain\Slug;
use Shared\Domain\RichText\RichText;
use Shared\Tests\Domain\ClockMother;
use Shared\Tests\Domain\RichText\RichTextMother;

/**
 * Builds Author aggregates for tests, with sensible defaults that can be
 * overridden through named arguments:
 *
 *     AuthorMother::create()
 *     AuthorMother::create(avatar: AuthorMother::AVATAR, bio: AuthorMother::BIO)
 *     AuthorMother::published(name: 'Isaac', publishedName: 'Isaac Abreu')
 *     AuthorMother::published(avatar: AuthorMother::OTHER_AVATAR, publishedAvatar: AuthorMother::AVATAR)
 *     AuthorMother::unpublished()
 *
 * Authors are created at ClockMother::NOW, and changed and published then
 * unless a test says otherwise. An author given a blank slug has none.
 */
final class AuthorMother
{
    public const string NAME = 'Isaac Abreu';

    public const string SLUG = 'isaac';

    public const string AVATAR = '01a1da50-9e10-7f03-8c5a-1b2c3d4e5f03.webp';

    public const string OTHER_AVATAR = '01a1da50-9e10-7f03-8c5a-1b2c3d4e5f04.webp';

    /**
     * A bio enough to publish an author.
     */
    public const array BIO = [
        'version' => 1,
        'blocks' => [['type' => 'paragraph', 'text' => [['text' => 'Escribo sobre autismo en primera persona.']]]],
    ];

    /**
     * A meta description enough to publish an author.
     */
    public const string META_DESCRIPTION = 'Quién escribe este blog sobre autismo y de qué escribe.';

    /**
     * A draft.
     *
     * @param array<array-key, mixed> $bio in the stored rich text format
     */
    public static function create(
        string $id = AuthorIdMother::DEFAULT_VALUE,
        string $name = self::NAME,
        string $slug = self::SLUG,
        ?string $avatar = null,
        array $bio = RichTextMother::EMPTY,
        ?string $metaDescription = null,
        string $updatedAt = ClockMother::NOW,
    ): Author {
        return new Author(
            AuthorIdMother::create($id),
            self::version($name, $slug, $avatar, $bio, $metaDescription),
            AuthorStatus::Draft,
            null,
            new Timeline(ClockMother::now(), ClockMother::now($updatedAt), null, null),
            0,
        );
    }

    /**
     * A draft with everything it needs to be published.
     */
    public static function publishable(string $id = AuthorIdMother::DEFAULT_VALUE, string $slug = self::SLUG): Author
    {
        return self::create(id: $id, slug: $slug, avatar: self::AVATAR, bio: self::BIO, metaDescription: self::META_DESCRIPTION);
    }

    /**
     * An author on the site. With another published name, avatar or bio, it
     * is published with changes: the site has the author with those, and the
     * rest as edited.
     *
     * @param array<array-key, mixed>      $bio          in the stored rich text format
     * @param null|array<array-key, mixed> $publishedBio in the stored rich text format
     */
    public static function published(
        string $id = AuthorIdMother::DEFAULT_VALUE,
        string $name = self::NAME,
        string $slug = self::SLUG,
        string $avatar = self::AVATAR,
        array $bio = self::BIO,
        ?string $metaDescription = self::META_DESCRIPTION,
        ?string $publishedName = null,
        ?string $publishedAvatar = null,
        ?array $publishedBio = null,
        string $updatedAt = ClockMother::NOW,
        string $firstPublishedAt = ClockMother::NOW,
        string $lastPublishedAt = ClockMother::NOW,
        int $publicationRevision = 1,
    ): Author {
        return new Author(
            AuthorIdMother::create($id),
            self::version($name, $slug, $avatar, $bio, $metaDescription),
            AuthorStatus::Published,
            self::version($publishedName ?? $name, $slug, $publishedAvatar ?? $avatar, $publishedBio ?? $bio, $metaDescription),
            new Timeline(
                ClockMother::now(),
                ClockMother::now($updatedAt),
                ClockMother::now($firstPublishedAt),
                ClockMother::now($lastPublishedAt),
            ),
            $publicationRevision,
        );
    }

    /**
     * An author taken off the site, which keeps the version it had there.
     */
    public static function unpublished(
        string $id = AuthorIdMother::DEFAULT_VALUE,
        string $slug = self::SLUG,
        int $publicationRevision = 2,
    ): Author {
        return new Author(
            AuthorIdMother::create($id),
            self::version(self::NAME, $slug, self::AVATAR, self::BIO, self::META_DESCRIPTION),
            AuthorStatus::Unpublished,
            self::version(self::NAME, $slug, self::AVATAR, self::BIO, self::META_DESCRIPTION),
            new Timeline(ClockMother::now(), ClockMother::now(), ClockMother::now(), ClockMother::now()),
            $publicationRevision,
        );
    }

    /**
     * @param array<array-key, mixed> $bio
     */
    private static function version(string $name, string $slug, ?string $avatar, array $bio, ?string $metaDescription): AuthorVersion
    {
        return new AuthorVersion(
            new AuthorName($name),
            '' === $slug ? null : new Slug($slug),
            null === $avatar ? null : new AvatarPath($avatar),
            RichText::fromArray($bio),
            new Seo($metaDescription, Robots::IndexFollow),
        );
    }
}
