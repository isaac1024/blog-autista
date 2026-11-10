<?php

declare(strict_types=1);

namespace Shared\Infrastructure\Ui;

use Shared\Infrastructure\Ui\Page\Page;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Paints, in the layout it is given, why the site has nothing at an address:
 * nothing was ever there, or what was there was taken off for good. Either
 * way it offers the home page, the one place the reader is sure to find.
 */
final readonly class SiteErrorPage
{
    public function __construct(private TranslatorInterface $translator) {}

    /**
     * The page of an address the site doesn't answer, or no longer does,
     * in the layout its title started, leading home.
     */
    public function paint(Page $layout, bool $gone, string $homeUrl): Page
    {
        $reason = self::reason($gone);

        return $layout
            ->withMain('Shared:PageTitle', [
                'title' => $this->translator->trans(\sprintf('site.%s.title', $reason)),
                'subtitle' => $this->translator->trans(\sprintf('site.%s.message', $reason)),
            ])
            ->withMain('Shared:ButtonLink', [
                'url' => $homeUrl,
                'label' => $this->translator->trans('site.home'),
            ]);
    }

    /**
     * The title to start the layout with, so the browser tab says it too.
     */
    public function title(bool $gone): string
    {
        return $this->translator->trans(\sprintf('site.%s.title', self::reason($gone)));
    }

    private static function reason(bool $gone): string
    {
        return $gone ? 'gone' : 'not_found';
    }
}
