<?php

declare(strict_types=1);

namespace Shared\Infrastructure\Ui;

use Shared\Infrastructure\Ui\Page\Page;

/**
 * Paints the page of a term of a taxonomy, such as a section or a tag, as the
 * site does, in the layout it is given: its name as the main heading. The
 * site serves it this way, and the CMS previews it the same.
 */
final readonly class SiteTermPage
{
    public function __invoke(Page $layout, string $name): Page
    {
        return $layout->withMain('Shared:PageTitle', ['title' => $name]);
    }
}
