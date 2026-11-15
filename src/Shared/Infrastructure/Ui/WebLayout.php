<?php

declare(strict_types=1);

namespace Shared\Infrastructure\Ui;

use Shared\Infrastructure\Ui\Page\Page;

/**
 * Builds pages that use the layout of the public site.
 *
 * The layout paints the chrome (header and footer) from what the site tells
 * it; pages only declare their main content, in one column of readable lines.
 */
final class WebLayout
{
    public const string TEMPLATE = 'shared/web_layout.html.twig';

    /**
     * The one language of the public site, whatever the browser asks for:
     * its routes, its error pages and their static copies all speak it.
     */
    public const string LOCALE = 'es';

    public const string MAIN_CLASS = 'mx-auto flex w-full max-w-[46rem] flex-col gap-8 px-[clamp(1rem,4vw,2rem)] py-[clamp(2rem,6vw,4rem)]';

    public static function page(string $title, SiteChrome $chrome): Page
    {
        $page = Page::create($title)
            ->withLayout(self::TEMPLATE)
            ->withMainClass(self::MAIN_CLASS)
            ->withHeader('Shared:PageHeader', [
                'brand' => $chrome->brand,
                'homeUrl' => $chrome->homeUrl,
                'links' => $chrome->mainLinks,
            ])
            ->withFooter('Shared:PageFooter', [
                'brand' => $chrome->brand,
                'links' => $chrome->footerLinks,
            ]);

        return null === $chrome->shareImage ? $page : $page->withShareImage(
            $chrome->shareImage['src'],
            $chrome->shareImage['width'],
            $chrome->shareImage['height'],
            $chrome->shareImage['alt'],
        );
    }
}
