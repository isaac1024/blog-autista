<?php

declare(strict_types=1);

namespace Shared\Domain\External;

/**
 * What an external resource is, which says how the site paints it: a video,
 * a post of a social network or an audio behind a facade that loads it
 * when the reader agrees; an article, a study or a document as a card of
 * related content.
 */
enum ResourceKind: string
{
    case Video = 'video';
    case SocialPost = 'social_post';
    case Audio = 'audio';
    case Article = 'article';
    case Study = 'study';
    case Document = 'document';

    /**
     * Whether the site embeds it from its provider, behind a facade, rather
     * than painting it as a card.
     */
    public function isEmbedded(): bool
    {
        return \in_array($this, [self::Video, self::SocialPost, self::Audio], true);
    }
}
