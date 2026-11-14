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
}
