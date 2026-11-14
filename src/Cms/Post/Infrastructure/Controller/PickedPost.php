<?php

declare(strict_types=1);

namespace Cms\Post\Infrastructure\Controller;

use Cms\Post\Application\PostResponse;
use Cms\Post\Domain\PostStatus;

/**
 * A post as a field that picks one shows it: its title and where it stands
 * for the site.
 */
final readonly class PickedPost
{
    public function __construct(private PostState $postState) {}

    /**
     * @return array{id: string, title: string, status: string, published: bool}
     */
    public function __invoke(PostResponse $post): array
    {
        return [
            'id' => $post->id,
            'title' => $post->title,
            'status' => $this->postState->label($post),
            'published' => PostStatus::Published->value === $post->status,
        ];
    }
}
