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
     * The posts on the site among those given, in the order given, read at
     * once; an id of none is left out.
     *
     * @param list<PostId> $ids
     *
     * @return list<Post>
     */
    public function findByIds(array $ids): array;

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

    /**
     * The posts search engines may index, newest first by the day they were
     * first published, as many as the limit at most.
     *
     * @return list<Post>
     */
    public function indexed(int $limit): array;

    /**
     * The posts whose title, summary or content have every word searched,
     * as the start of a word, the most relevant first, newest first among
     * equals, from the offset given, as many as the limit at most.
     *
     * @throws SearchTooBroad when it would take longer than a search may
     *
     * @return list<Post>
     */
    public function searched(SearchTerms $terms, int $offset, int $limit): array;
}
