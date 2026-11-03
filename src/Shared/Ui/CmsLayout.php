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

    public const string MAIN_CLASS = 'flex flex-1 flex-col gap-8 px-[clamp(1rem,3vw,2rem)] py-6';

    public static function page(string $title): Page
    {
        return Page::create($title)
            ->withLayout(self::TEMPLATE)
            ->withMainClass(self::MAIN_CLASS);
    }
}
