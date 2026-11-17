<?php

declare(strict_types=1);

namespace Cms\Shared\Infrastructure\Ui;

use Cms\Taxonomy\Application\TermResponse;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Tells editors where a term stands: whether it is on the site, and whether
 * the site shows what they last saved.
 */
final readonly class TermState
{
    public function __construct(private TranslatorInterface $translator) {}

    /**
     * Short name of the state, for a badge.
     */
    public function label(TermResponse $term): string
    {
        return $this->translator->trans(\sprintf('cms.terms.status.%s.label', $this->key($term)));
    }

    /**
     * What the state means for the site, next to the actions that change it.
     */
    public function description(TermResponse $term): string
    {
        return $this->translator->trans(\sprintf('cms.terms.status.%s.description', $this->key($term)));
    }

    private function key(TermResponse $term): string
    {
        return $term->changed ? 'changed' : $term->status;
    }
}
