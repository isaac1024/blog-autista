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

    public function notFound(Page $layout, string $homeUrl): Page
    {
        return $this->paint($layout, 'not_found', $homeUrl);
    }

    public function gone(Page $layout, string $homeUrl): Page
    {
        return $this->paint($layout, 'gone', $homeUrl);
    }

    /**
     * The title to start the layout with, so the browser tab says it too.
     */
    public function title(bool $gone): string
    {
        return $this->translator->trans(\sprintf('site.%s.title', $gone ? 'gone' : 'not_found'));
    }

    private function paint(Page $layout, string $reason, string $homeUrl): Page
    {
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
}
