<?php

declare(strict_types=1);

namespace Web\Tests\Post\Domain;

use Shared\Domain\RichText\RichText;
use Shared\Tests\Domain\ClockMother;
use Web\Post\Domain\CmsPostPublished;
use Web\Shared\Domain\Robots;
use Web\Tests\Image\Domain\ImageIdMother;

/**
 * Builds the event the CMS sends the site when it publishes a post, as the
 * site reads it, or its body as it travels:
 *
 *     CmsPostPublishedMother::create(title: 'Otro título')
 *     CmsPostPublishedMother::body(title: 'Otro título')
 *
 * The post is filed under a section and a tag, and it happens at
 * ClockMother::NOW unless a test says otherwise.
 */
final class CmsPostPublishedMother
{
    /**
     * The image the post opens with.
     */
    public const string OPENING_IMAGE = ImageIdMother::DEFAULT_VALUE;

    public const string SECTION = '01a1da50-2b7c-7d01-8a3f-5c2e9b1d0a01';

    public const string TAG = '01a1da50-2b7c-7d01-8a3f-5c2e9b1d0a02';

    public const string AUTHOR = '01a1da50-7c41-7e02-9b6d-3f8a2c4e1b02';

    /**
     * Content as the CMS stores and sends it.
     */
    public const array CONTENT = [
        'version' => 1,
        'blocks' => [['type' => 'paragraph', 'text' => [['text' => 'Hoy probé los tapones.']]]],
    ];

    /**
     * The body of the event as it travels from the CMS.
     *
     * @param list<string> $tagIds
     * @param string       $openingImageId the image it opens with, by id
     *
     * @return array<string, mixed>
     */
    public static function body(
        string $title = 'Mi primer día sin ruido',
        string $sectionSlug = 'vida-diaria',
        string $day = '2026-11-07',
        array $tagIds = [self::TAG],
        string $openingImageId = self::OPENING_IMAGE,
    ): array {
        return [
            'title' => $title,
            'slug' => 'mi-primer-dia',
            'section_id' => self::SECTION,
            'section_name' => 'Vida diaria',
            'section_slug' => $sectionSlug,
            'author_id' => self::AUTHOR,
            'author_name' => 'Isaac Abreu',
            'tag_ids' => $tagIds,
            'opening_image_id' => $openingImageId,
            'content' => self::CONTENT,
            'meta_description' => 'Qué cambió el primer día con tapones.',
            'robots' => 'index,follow',
            'day' => $day,
            'first_published_at' => ClockMother::now()->format(\DATE_ATOM),
            'last_published_at' => ClockMother::now()->format(\DATE_ATOM),
        ];
    }

    /**
     * @param list<string>                                            $tagIds
     * @param array{version: int, blocks: list<array<string, mixed>>} $content as the CMS stores and sends it
     */
    public static function create(
        string $id = PostIdMother::DEFAULT_VALUE,
        string $title = 'Mi primer día sin ruido',
        string $slug = 'mi-primer-dia',
        string $sectionSlug = 'vida-diaria',
        string $day = '2026-11-07',
        array $tagIds = [self::TAG],
        ?string $metaDescription = 'Qué cambió el primer día con tapones.',
        Robots $robots = Robots::IndexFollow,
        string $lastPublishedAt = ClockMother::NOW,
        int $revision = 1,
        array $content = self::CONTENT,
    ): CmsPostPublished {
        return new CmsPostPublished(
            $id,
            $title,
            $slug,
            self::SECTION,
            'Vida diaria',
            $sectionSlug,
            self::AUTHOR,
            'Isaac Abreu',
            $tagIds,
            self::OPENING_IMAGE,
            RichText::fromArray($content),
            $metaDescription,
            $robots,
            $day,
            ClockMother::now(),
            ClockMother::now($lastPublishedAt),
            $revision,
        );
    }
}
