<?php

declare(strict_types=1);

namespace Cms\Page\Domain;

/**
 * Outbound port used to persist pages.
 */
interface PageRepository
{
    public function save(Page $page): void;
}
