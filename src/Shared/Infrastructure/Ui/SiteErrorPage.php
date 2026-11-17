<?php

declare(strict_types=1);

namespace Shared\Infrastructure\Ui;

use Shared\Infrastructure\Ui\Page\Page;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Paints, in the layout it is given, why the site has nothing at an address:
 * nothing was ever there, what was there was taken off for good, or the site
 * failed to answer, under a drawing of it. Where nothing is, it offers to
 * search for it by the words of the address, the latest posts and the home
 * page; where the site failed, to try the same address again, or home.
 *
 * @phpstan-import-type PostCard from \Shared\Infrastructure\Twig\Components\PostCards
 */
final readonly class SiteErrorPage
{
    /**
     * The shortest run of letters taken as a word of an address, as the
     * search of the site takes them.
     */
    private const int WORD_LENGTH = 3;

    public function __construct(private TranslatorInterface $translator) {}

    /**
     * The page of an address the site doesn't answer, no longer does or
     * failed to, in the layout its title started.
     *
     * @param string         $path      the address asked for, from its first slash
     * @param ?string        $searchUrl the page that searches the site, null while it has none
     * @param list<PostCard> $latest    the latest posts of the site, none where it failed
     */
    public function paint(Page $layout, SiteError $error, string $homeUrl, string $path, ?string $searchUrl, string $searchName, array $latest): Page
    {
        $page = $layout
            ->withMain('Shared:ErrorIllustration', ['error' => $error->value])
            ->withMain('Shared:PageTitle', [
                'title' => $this->translator->trans(\sprintf('site.%s.title', $error->value)),
                'subtitle' => $this->translator->trans(\sprintf('site.%s.message', $error->value)),
            ]);

        if (SiteError::ServerError === $error) {
            return $page
                ->withMain('Shared:ButtonLink', ['url' => $path, 'label' => $this->translator->trans('site.retry')])
                ->withMain('Shared:ButtonLink', ['url' => $homeUrl, 'label' => $this->translator->trans('site.home')]);
        }

        return $page->withMain('Shared:SiteErrorHelp', [
            'searchUrl' => $searchUrl,
            'searchName' => $searchName,
            'words' => self::words($path),
            'latest' => $latest,
            'homeUrl' => $homeUrl,
        ]);
    }

    /**
     * The title to start the layout with, so the browser tab says it too.
     */
    public function title(SiteError $error): string
    {
        return $this->translator->trans(\sprintf('site.%s.title', $error->value));
    }

    /**
     * The words of an address, lowercase, each once, in order: runs of
     * letters long enough to search by, but its extension. Numbers, such as
     * the day of a post, find nothing.
     */
    private static function words(string $path): string
    {
        $path = (string) preg_replace('/\.[a-z0-9]+$/i', '', rawurldecode($path));
        preg_match_all(\sprintf('/\p{L}{%d,}/u', self::WORD_LENGTH), mb_strtolower($path), $matches);

        return implode(' ', array_values(array_unique($matches[0])));
    }
}
