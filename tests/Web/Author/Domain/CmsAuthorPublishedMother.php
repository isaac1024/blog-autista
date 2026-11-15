<?php

declare(strict_types=1);

namespace Web\Tests\Author\Domain;

use Shared\Domain\RichText\RichText;
use Shared\Tests\Domain\ClockMother;
use Web\Author\Domain\CmsAuthorPublished;
use Web\Shared\Domain\Robots;

/**
 * Builds the event the CMS sends the site when it publishes an author, as
 * the site reads it, or its body as it travels:
 *
 *     CmsAuthorPublishedMother::create(slug: 'otra')
 *     CmsAuthorPublishedMother::body(slug: 'otra')
 *
 * It happens at ClockMother::NOW unless a test says otherwise.
 */
final class CmsAuthorPublishedMother
{
    /**
     * The file of the avatar, as the CMS keeps it.
     */
    public const string AVATAR = '01a1da50-9e10-7f03-8c5a-1b2c3d4e5f03.webp';

    /**
     * Bio as the CMS stores and sends it.
     */
    public const array BIO = [
        'version' => 1,
        'blocks' => [['type' => 'paragraph', 'text' => [['text' => 'Escribo sobre autismo.']]]],
    ];

    /**
     * The body of the event as it travels from the CMS.
     *
     * @return array<string, mixed>
     */
    public static function body(string $name = 'Isaac Abreu', string $slug = 'isaac', string $avatar = self::AVATAR): array
    {
        return [
            'name' => $name,
            'slug' => $slug,
            'avatar' => $avatar,
            'bio' => self::BIO,
            'meta_description' => 'Quién escribe este blog.',
            'robots' => 'index,follow',
            'first_published_at' => ClockMother::now()->format(\DATE_ATOM),
            'last_published_at' => ClockMother::now()->format(\DATE_ATOM),
        ];
    }

    public static function create(
        string $id = AuthorIdMother::DEFAULT_VALUE,
        string $name = 'Isaac Abreu',
        string $slug = 'isaac',
        string $avatar = self::AVATAR,
        ?string $metaDescription = 'Quién escribe este blog.',
        Robots $robots = Robots::IndexFollow,
        string $lastPublishedAt = ClockMother::NOW,
        int $revision = 1,
    ): CmsAuthorPublished {
        return new CmsAuthorPublished(
            $id,
            $name,
            $slug,
            $avatar,
            RichText::fromArray(self::BIO),
            $metaDescription,
            $robots,
            ClockMother::now(),
            ClockMother::now($lastPublishedAt),
            $revision,
        );
    }
}
