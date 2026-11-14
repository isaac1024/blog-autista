<?php

declare(strict_types=1);

namespace Cms\Post\Application;

use Cms\Post\Domain\AddressAvailability;
use Cms\Post\Domain\Classifiers;
use Cms\Post\Domain\PostId;
use Cms\Post\Domain\PostRepository;
use Shared\Domain\Event\EventBus;
use Shared\Domain\MutualExclusion;

/**
 * Puts the version last saved of a post on the site, for the first time or
 * in place of the version there. What the editor hasn't saved yet stays out.
 *
 * Its address there is only known as it is published, so that is when it is
 * checked that no other post on the site has it, holding the address while
 * it is checked and saved, so two posts published at once can't both take
 * it. A post off the site that last had that address there loses it: it
 * is this one's now.
 */
final readonly class PostPublisher
{
    public function __construct(private string $id) {}

    public function __invoke(
        PostRepository $postRepository,
        EventBus $eventBus,
        Classifiers $classifiers,
        MutualExclusion $mutualExclusion,
    ): void {
        $post = $postRepository->find(new PostId($this->id)) ?? throw PostNotFound::withId($this->id);

        $post->publish($classifiers);
        $address = $post->publishedAddress();
        $keys = null === $address ? [] : ['post-address:'.$address->key()];

        $mutualExclusion->run($keys, static function () use ($post, $address, $postRepository, $eventBus): void {
            new AddressAvailability($post)($postRepository);
            $post->save($postRepository, $eventBus);

            // Its address is its last from now on, not that of a post off
            // the site that last had it.
            $former = null === $address ? null : $postRepository->findOffSiteByLastAddress($address);

            if (null !== $former && !$former->id()->equals($post->id())) {
                $former->loseLastAddress();
                $former->save($postRepository, $eventBus);
            }
        });
    }
}
