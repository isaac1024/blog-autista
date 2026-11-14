<?php

declare(strict_types=1);

namespace Cms\Author\Infrastructure\Controller;

use Cms\Author\Application\AuthorResponse;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Tells editors where an author stands: whether it is on the site, and
 * whether the site shows what they last saved.
 */
final readonly class AuthorState
{
    public function __construct(private TranslatorInterface $translator) {}

    /**
     * Short name of the state, for a badge.
     */
    public function label(AuthorResponse $author): string
    {
        return $this->translator->trans(\sprintf('cms.authors.status.%s.label', $this->key($author)));
    }

    /**
     * What the state means for the site, next to the actions that change it.
     */
    public function description(AuthorResponse $author): string
    {
        return $this->translator->trans(\sprintf('cms.authors.status.%s.description', $this->key($author)));
    }

    /**
     * When the author was last changed, in the editor's language.
     */
    public function lastUpdate(AuthorResponse $author): string
    {
        $formatter = new \IntlDateFormatter($this->translator->getLocale(), \IntlDateFormatter::MEDIUM, \IntlDateFormatter::NONE);

        return $this->translator->trans('cms.authors.list.updated_at', ['%date%' => $formatter->format($author->updatedAt)]);
    }

    private function key(AuthorResponse $author): string
    {
        return $author->changed ? 'changed' : $author->status;
    }
}
