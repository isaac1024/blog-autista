<?php

declare(strict_types=1);

namespace Web\Settings\Infrastructure\Controller;

use Shared\Infrastructure\Ui\Page\PageRenderer;
use Shared\Infrastructure\Ui\PostCardsComponent;
use Shared\Infrastructure\Ui\SiteError;
use Shared\Infrastructure\Ui\SiteErrorPage;
use Shared\Infrastructure\Ui\WebLayout;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Translation\LocaleSwitcher;
use Web\Page\Application\SearchPageFinder;
use Web\Page\Domain\PageRepository;
use Web\Post\Application\LatestPostsFinder;
use Web\Post\Domain\PostRepository;
use Web\Post\Domain\PostSelection;
use Web\Shared\Infrastructure\Ui\SiteLayout;
use Web\Shared\Infrastructure\Ui\SitePostCards;
use Web\Shared\Infrastructure\Ui\SiteSearch;

/**
 * Paints the page of an error of the site, in its chrome and in its one
 * language, whatever the browser asked for, since an address no route
 * answers was never told it.
 *
 * Where nothing is, it offers the search of the site and its latest posts;
 * where the site failed, it reads nothing more than its chrome, as what
 * failed may be what it would read.
 */
final readonly class SiteErrorResponse
{
    /**
     * How many of the latest posts it offers.
     */
    private const int LATEST = 3;

    /**
     * @SuppressWarnings("PHPMD.ExcessiveParameterList")
     */
    public function __construct(
        private SiteLayout $siteLayout,
        private SiteErrorPage $siteErrorPage,
        private PageRenderer $pageRenderer,
        private UrlGeneratorInterface $urlGenerator,
        private LocaleSwitcher $localeSwitcher,
        private PageRepository $pageRepository,
        private PostRepository $postRepository,
        private SitePostCards $sitePostCards,
        private PostCardsComponent $postCardsComponent,
    ) {}

    /**
     * @param string $path the address asked for, from its first slash
     */
    public function __invoke(SiteError $error, string $path): Response
    {
        return $this->localeSwitcher->runWithLocale(WebLayout::LOCALE, function () use ($error, $path): Response {
            $layout = $this->siteLayout->page($this->siteErrorPage->title($error));
            $homeUrl = $this->urlGenerator->generate('web_home');
            $failed = SiteError::ServerError === $error;
            $search = $failed ? null : new SearchPageFinder()($this->pageRepository);

            return $this->pageRenderer->render($this->siteErrorPage->paint(
                $layout,
                $error,
                $homeUrl,
                $path,
                null === $search?->slug ? null : ['url' => $this->urlGenerator->generate('web_page', ['slug' => $search->slug]), 'parameter' => SiteSearch::PARAMETER],
                $failed ? [] : $this->postCardsComponent->cards(($this->sitePostCards)(
                    new LatestPostsFinder(new PostSelection(null, null, []), self::LATEST)($this->postRepository),
                )),
            ));
        });
    }
}
