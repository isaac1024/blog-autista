<?php

declare(strict_types=1);

namespace Shared\Infrastructure\Ui;

use Shared\Infrastructure\Twig\Components\PostCards;
use Shared\Infrastructure\Ui\Page\PageComponent;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Paints posts as cards, wherever the site lists them, and as the CMS
 * previews them: each dated by the day it was first published, the day of
 * Madrid the site keeps, which is what lists order them by.
 *
 * @phpstan-import-type PostCard from PostCards
 *
 * @phpstan-type CardPost array{url: string, title: string, excerpt: ?string, section: string, firstPublishedAt: \DateTimeImmutable, image: ?array{src: string, srcset: string, width: int, height: int}}
 */
final readonly class PostCardsComponent
{
    public function __construct(private TranslatorInterface $translator) {}

    /**
     * @param list<CardPost> $posts
     */
    public function __invoke(array $posts, int $headingLevel, bool $wide): PageComponent
    {
        return new PageComponent('Shared:PostCards', [
            'cards' => $this->cards($posts),
            'headingLevel' => $headingLevel,
            'wide' => $wide,
        ]);
    }

    /**
     * The cards of the posts, as Shared:PostCards takes them, such as those
     * of a mosaic, which the text it sits in paints.
     *
     * @param list<CardPost> $posts
     *
     * @return list<PostCard>
     */
    public function cards(array $posts): array
    {
        $zone = new \DateTimeZone(SitePostPage::TIME_ZONE);
        $formatter = new \IntlDateFormatter($this->translator->getLocale(), \IntlDateFormatter::LONG, \IntlDateFormatter::NONE, SitePostPage::TIME_ZONE);

        return array_map(static function (array $post) use ($zone, $formatter): array {
            $published = $post['firstPublishedAt']->setTimezone($zone);

            return [
                'url' => $post['url'],
                'title' => $post['title'],
                'excerpt' => $post['excerpt'],
                'section' => $post['section'],
                'date' => (string) $formatter->format($published),
                'datetime' => $published->format(\DATE_ATOM),
                'image' => $post['image'],
            ];
        }, $posts);
    }
}
