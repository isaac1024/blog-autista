<?php

declare(strict_types=1);

namespace Cms\Page\Infrastructure\Controller;

use Cms\Page\Application\PageResponse;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Tells editors where a page stands: whether it is on the site, and whether
 * the site shows what they last saved.
 */
final readonly class PageState
{
    public function __construct(private TranslatorInterface $translator) {}

    /**
     * Short name of the state, for a badge.
     */
    public function label(PageResponse $page): string
    {
        return $this->translator->trans(\sprintf('cms.pages.status.%s.label', $this->key($page)));
    }

    /**
     * What the state means for the site, next to the actions that change it.
     */
    public function description(PageResponse $page): string
    {
        return $this->translator->trans(\sprintf('cms.pages.status.%s.description', $this->key($page)));
    }

    /**
     * When the page was last changed, in the editor's language.
     */
    public function lastUpdate(PageResponse $page): string
    {
        $formatter = new \IntlDateFormatter($this->translator->getLocale(), \IntlDateFormatter::MEDIUM, \IntlDateFormatter::NONE);

        return $this->translator->trans('cms.pages.list.updated_at', ['%date%' => $formatter->format($page->updatedAt)]);
    }

    private function key(PageResponse $page): string
    {
        return $page->changed ? 'changed' : $page->status;
    }
}
