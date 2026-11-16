<?php

declare(strict_types=1);

namespace Web\Post\Domain;

/**
 * Outbound port used to persist and read the posts on the site.
 */
interface PostRepository
{
    public function save(Post $post): void;

    public function delete(Post $post): void;

    public function find(PostId $id): ?Post;

    /**
     * The post served under the slug of a section, on the day it was first
     * published, by its slug.
     */
    public function findAt(string $section, \DateTimeImmutable $day, string $slug): ?Post;

    /**
     * The posts of the selection, newest first by the day they were first
     * published, from the offset given, as many as the limit at most.
     *
     * @return list<Post>
     */
    public function listed(PostSelection $selection, int $offset, int $limit): array;
}
