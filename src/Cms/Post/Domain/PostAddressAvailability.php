<?php

declare(strict_types=1);

namespace Cms\Post\Domain;

/**
 * Checks that no other post on the site has the address the given one has
 * there, or would have once published: two posts of the same section,
 * first published on the same day, never share a slug. Posts off the site
 * take no address, so a draft is never checked against another; it is
 * once it is published, and each time again, and before it is, so the
 * editor learns of it first.
 *
 * The unique index on the address columns still guards against two
 * publications racing.
 */
final readonly class PostAddressAvailability
{
    /**
     * @param ?PostAddress $address the one the post has on the site, or would have; null for none
     */
    public function __construct(
        private Post $post,
        private ?PostAddress $address,
    ) {}

    public function __invoke(PostRepository $postRepository): void
    {
        $address = $this->address;

        if (null === $address) {
            return;
        }

        $owner = $postRepository->findOnSiteAt($address);

        if (null !== $owner && !$owner->id()->equals($this->post->id())) {
            throw PostAddressTaken::atAddress($address);
        }
    }
}
