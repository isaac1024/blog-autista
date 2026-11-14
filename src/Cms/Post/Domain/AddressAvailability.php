<?php

declare(strict_types=1);

namespace Cms\Post\Domain;

/**
 * Checks that no other post on the site is addressed as the given one is
 * there: two posts of the same section, first published on the same day,
 * never share a slug. Posts off the site take no address, so a draft is
 * never checked; it is once it is published, and each time again.
 *
 * The unique index on the address columns still guards against two
 * publications racing.
 */
final readonly class AddressAvailability
{
    public function __construct(private Post $post) {}

    public function __invoke(PostRepository $postRepository): void
    {
        $address = $this->post->publishedAddress();

        if (null === $address) {
            return;
        }

        $owner = $postRepository->findOnSiteAt($address);

        if (null !== $owner && !$owner->id()->equals($this->post->id())) {
            throw PostAddressTaken::atAddress($address);
        }
    }
}
