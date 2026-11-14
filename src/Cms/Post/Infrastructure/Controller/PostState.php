<?php

declare(strict_types=1);

namespace Cms\Post\Infrastructure\Controller;

use Cms\Post\Application\PostResponse;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Tells editors where a post stands: whether it is on the site, and
 * whether the site shows what they last saved.
 */
final readonly class PostState
{
    public function __construct(private TranslatorInterface $translator) {}

    /**
     * Short name of the state, for a badge.
     */
    public function label(PostResponse $post): string
    {
        return $this->translator->trans(\sprintf('cms.posts.status.%s.label', $this->key($post)));
    }

    /**
     * What the state means for the site, next to the actions that change it.
     */
    public function description(PostResponse $post): string
    {
        return $this->translator->trans(\sprintf('cms.posts.status.%s.description', $this->key($post)));
    }

    /**
     * When the post was last changed, in the editor's language.
     */
    public function lastUpdate(PostResponse $post): string
    {
        $formatter = new \IntlDateFormatter($this->translator->getLocale(), \IntlDateFormatter::MEDIUM, \IntlDateFormatter::NONE);

        return $this->translator->trans('cms.posts.list.updated_at', ['%date%' => $formatter->format($post->updatedAt)]);
    }

    private function key(PostResponse $post): string
    {
        return $post->changed ? 'changed' : $post->status;
    }
}
