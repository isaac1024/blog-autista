<?php

declare(strict_types=1);

namespace Shared\Ui;

use Shared\Ui\Page\Page;

/**
 * Builds pages that use the CMS administration layout.
 *
 * The layout paints the chrome (sidebar, mobile menu and main content);
 * pages only declare their main content.
 */
final class CmsLayout
{
    public const string TEMPLATE = 'shared/cms_layout.html.twig';

    /**
     * One column next to the sidebar, wide enough for lists and editors but
     * with readable lines; the action bar of a form bleeds to its edges.
     */
    public const string MAIN_CLASS = 'flex w-full max-w-6xl flex-1 flex-col gap-8 px-[clamp(1rem,4vw,2.5rem)] pt-[clamp(1.5rem,4vw,2.5rem)] pb-[clamp(1.5rem,4vw,2.5rem)]';

    public static function page(string $title): Page
    {
        return Page::create($title)
            ->withLayout(self::TEMPLATE)
            ->withMainClass(self::MAIN_CLASS);
    }
}
