<?php

declare(strict_types=1);

namespace Cms\Post\Domain;

use Shared\Domain\Criteria\Criteria;

/**
 * Outbound port used to persist and read posts.
 */
interface PostRepository
{
    /**
     * @throws PostAddressTaken when another post is published at the same address meanwhile
     */
    public function save(Post $post): void;

    public function delete(Post $post): void;

    public function find(PostId $id): ?Post;

    /**
     * The title, as edited, of the posts among those given, by id, read
     * without the rest of each; an id of none is left out.
     *
     * @param list<PostId> $ids
     *
     * @return array<string, string>
     */
    public function titlesOf(array $ids): array;

    /**
     * The post on the site at the address.
     */
    public function findOnSiteAt(PostAddress $address): ?Post;

    /**
     * The post off the site whose last address there it is.
     */
    public function findOffSiteByLastAddress(PostAddress $address): ?Post;

    /**
     * @return list<Post>
     */
    public function matching(Criteria $criteria): array;

    /**
     * The latest posts on the site of the selection, newest first by the day
     * they were first published, as many as the limit at most, as a preview
     * lists them.
     *
     * @return list<Post>
     */
    public function latestOnSite(PostSelection $selection, int $limit): array;
}
