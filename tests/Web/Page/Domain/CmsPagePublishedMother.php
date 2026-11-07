<?php

declare(strict_types=1);

namespace Web\Tests\Page\Domain;

use Shared\Domain\Event\EventId;
use Shared\Domain\RichText\RichText;
use Shared\Tests\Domain\ClockMother;
use Web\Page\Domain\CmsPagePublished;
use Web\Page\Domain\PageType;
use Web\Page\Domain\Robots;

/**
 * Builds the event the CMS sends the site when it publishes a page, as the
 * site reads it, or its body as it travels:
 *
 *     CmsPagePublishedMother::create(title: 'Sobre mí', slug: 'sobre-mi')
 *     CmsPagePublishedMother::body(title: 'Sobre mí', slug: 'sobre-mi')
 *
 * It happens at ClockMother::NOW unless a test says otherwise.
 */
final class CmsPagePublishedMother
{
    /**
     * Content as the CMS stores and sends it.
     */
    public const array CONTENT = [
        'version' => 1,
        'blocks' => [['type' => 'paragraph', 'text' => [['text' => 'Escribo sobre autismo en primera persona.']]]],
    ];

    /**
     * The body of the event as it travels from the CMS.
     *
     * @return array<string, mixed>
     */
    public static function body(string $title = 'Sobre mí', string $slug = 'sobre-mi', string $type = 'standard'): array
    {
        return [
            'type' => $type,
            'title' => $title,
            'slug' => $slug,
            'content' => self::CONTENT,
            'meta_description' => 'Quién escribe este blog sobre autismo y por qué.',
            'robots' => 'index,follow',
            'first_published_at' => ClockMother::now()->format(\DATE_ATOM),
            'last_published_at' => ClockMother::now()->format(\DATE_ATOM),
        ];
    }

    public static function create(
        string $id = PageIdMother::DEFAULT_VALUE,
        string $title = 'Sobre mí',
        string $slug = 'sobre-mi',
        PageType $type = PageType::Standard,
        ?string $metaDescription = 'Quién escribe este blog sobre autismo y por qué.',
        Robots $robots = Robots::IndexFollow,
        string $occurredOn = ClockMother::NOW,
    ): CmsPagePublished {
        return new CmsPagePublished(
            $id,
            $type,
            $title,
            $slug,
            RichText::fromArray(self::CONTENT),
            $metaDescription,
            $robots,
            ClockMother::now(),
            ClockMother::now($occurredOn),
            EventId::random()->value(),
            ClockMother::now($occurredOn),
        );
    }
}
