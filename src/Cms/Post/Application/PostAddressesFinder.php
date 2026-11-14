<?php

declare(strict_types=1);

namespace Cms\Post\Application;

use Cms\Post\Domain\Classifiers;
use Cms\Post\Domain\Post;
use Cms\Post\Domain\PostId;
use Cms\Post\Domain\PostRepository;

/**
 * Finds the addresses of a post on the site: the one it has there, or last
 * had there once off it, and the one it would have were it published now
 * as it is saved. Either is null when it has none, such as the last of a
 * post never published, or the next of one without a section on the site.
 */
final readonly class PostAddressesFinder
{
    public function __construct(private string $id) {}

    public function last(PostRepository $postRepository): ?PostAddressResponse
    {
        $address = $this->post($postRepository)->lastAddress();

        return null === $address ? null : PostAddressResponse::fromAddress($address);
    }

    public function next(PostRepository $postRepository, Classifiers $classifiers): ?PostAddressResponse
    {
        $address = $this->post($postRepository)->nextAddress($classifiers);

        return null === $address ? null : PostAddressResponse::fromAddress($address);
    }

    private function post(PostRepository $postRepository): Post
    {
        return $postRepository->find(new PostId($this->id)) ?? throw PostNotFound::withId($this->id);
    }
}
