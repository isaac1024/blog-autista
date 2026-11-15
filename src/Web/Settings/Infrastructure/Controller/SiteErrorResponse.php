<?php

declare(strict_types=1);

namespace Web\Settings\Infrastructure\Controller;

use Shared\Infrastructure\Ui\Page\PageRenderer;
use Shared\Infrastructure\Ui\SiteError;
use Shared\Infrastructure\Ui\SiteErrorPage;
use Shared\Infrastructure\Ui\WebLayout;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Translation\LocaleSwitcher;

/**
 * Paints the page of an error of the site, in its chrome and in its one
 * language, whatever the browser asked for, since an address no route
 * answers was never told it.
 */
final readonly class SiteErrorResponse
{
    public function __construct(
        private SiteLayout $siteLayout,
        private SiteErrorPage $siteErrorPage,
        private PageRenderer $pageRenderer,
        private UrlGeneratorInterface $urlGenerator,
        private LocaleSwitcher $localeSwitcher,
    ) {}

    public function __invoke(SiteError $error): Response
    {
        return $this->localeSwitcher->runWithLocale(WebLayout::LOCALE, function () use ($error): Response {
            $layout = $this->siteLayout->page($this->siteErrorPage->title($error));
            $homeUrl = $this->urlGenerator->generate('web_home');

            return $this->pageRenderer->render($this->siteErrorPage->paint($layout, $error, $homeUrl));
        });
    }
}
