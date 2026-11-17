<?php

declare(strict_types=1);

namespace Shared\Infrastructure\Twig\Components;

use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

/**
 * Where to go from an address with nothing at it: the search of the site,
 * already filled with the words of that address, while the site has a page
 * that searches; the latest posts, while it has any; and the home page.
 *
 * @phpstan-import-type PostCard from PostCards
 */
#[AsTwigComponent]
final class SiteErrorHelp
{
    public ?string $searchUrl = null;

    /**
     * The name of the query parameter the search reads.
     */
    public string $searchName = '';

    /**
     * The words of the address, to search by.
     */
    public string $words = '';

    /**
     * @var list<PostCard>
     */
    public array $latest = [];

    public string $homeUrl = '';

    /**
     * @param list<PostCard> $latest
     */
    public function mount(?string $searchUrl, string $searchName, string $words, array $latest, string $homeUrl): void
    {
        $this->searchUrl = $searchUrl;
        $this->searchName = $searchName;
        $this->words = $words;
        $this->latest = $latest;
        $this->homeUrl = $homeUrl;
    }
}
