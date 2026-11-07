<?php

declare(strict_types=1);

namespace Shared\Infrastructure\Twig\Components;

use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

/**
 * How a page could show in search results, kept up to date by the
 * search-preview controller as its fields change. Long titles and
 * descriptions are cut where search engines tend to cut them, so the preview
 * shows what readers would miss.
 *
 * Its props are declared in mount(), as for every component, so it takes as
 * many parameters as it has props.
 *
 * @SuppressWarnings("PHPMD.ExcessiveParameterList")
 */
#[AsTwigComponent]
final class SearchPreview
{
    /**
     * Around where search engines cut titles and descriptions; they measure in
     * pixels, so these are approximations.
     */
    public const int TITLE_LIMIT = 60;
    public const int DESCRIPTION_LIMIT = 160;

    public string $label = '';

    public string $title = '';

    public string $url = '';

    /**
     * Address of the site, which the path of the page follows.
     */
    public string $baseUrl = '';

    public string $description = '';

    public string $emptyDescription = '';

    /**
     * Ids of the fields that feed the preview; a missing slug means the page
     * lives at the root.
     *
     * @var array{title: string, description: string, slug?: string}
     */
    public array $sources = ['title' => '', 'description' => ''];

    /**
     * @param array{title: string, description: string, slug?: string} $sources
     */
    public function mount(
        string $label,
        string $title,
        string $url,
        string $baseUrl,
        string $description,
        string $emptyDescription,
        array $sources,
    ): void {
        $this->label = $label;
        $this->title = $title;
        $this->url = $url;
        $this->baseUrl = $baseUrl;
        $this->description = $description;
        $this->emptyDescription = $emptyDescription;
        $this->sources = $sources;
    }

    public function shownTitle(): string
    {
        return self::cut($this->title, self::TITLE_LIMIT);
    }

    public function shownDescription(): string
    {
        return '' === $this->description ? $this->emptyDescription : self::cut($this->description, self::DESCRIPTION_LIMIT);
    }

    private static function cut(string $text, int $limit): string
    {
        return mb_strlen($text) > $limit ? rtrim(mb_substr($text, 0, $limit - 1)).'…' : $text;
    }
}
