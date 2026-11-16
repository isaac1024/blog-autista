<?php

declare(strict_types=1);

namespace Cms\Post\Application;

use Cms\Post\Domain\Classifiers;
use Cms\Post\Domain\PostId;
use Cms\Post\Domain\PostImages;
use Cms\Post\Domain\PostRepository;
use Shared\Domain\Event\EventBus;

/**
 * Saves everything edited on an existing post: its title, slug, section,
 * author and tags, opening image, content and search engine settings. A published post
 * stays on the site as it was until it is published again.
 *
 * It carries what an editor writes, one parameter each.
 *
 * @SuppressWarnings("PHPMD.ExcessiveParameterList")
 */
final readonly class PostUpdater
{
    /**
     * @param string                  $section      blank for none
     * @param string                  $author       blank for none
     * @param list<string>            $tags         ids, in order
     * @param string                  $openingImage id of the image, blank for none
     * @param array<array-key, mixed> $content      in the stored rich text format
     */
    public function __construct(
        private string $id,
        private string $title,
        private string $slug,
        private string $section,
        private string $author,
        private array $tags,
        private string $openingImage,
        private array $content,
        private string $metaDescription,
        private string $robots,
    ) {}

    public function __invoke(PostRepository $postRepository, EventBus $eventBus, Classifiers $classifiers, PostImages $postImages): void
    {
        $post = $postRepository->find(new PostId($this->id)) ?? throw PostNotFound::withId($this->id);

        $post->edit(
            $this->title,
            $this->slug,
            $this->section,
            $this->author,
            $this->tags,
            $this->openingImage,
            $this->content,
            $this->metaDescription,
            $this->robots,
            $classifiers,
            $postImages,
        );

        $post->save($postRepository, $eventBus);
    }
}
