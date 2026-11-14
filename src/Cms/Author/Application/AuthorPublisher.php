<?php

declare(strict_types=1);

namespace Cms\Author\Application;

use Cms\Author\Domain\AuthorId;
use Cms\Author\Domain\AuthorRepository;
use Cms\Author\Domain\AvatarStorage;
use Shared\Domain\Event\EventBus;

/**
 * Puts the version last saved of an author on the site, for the first time or
 * in place of the version there.
 */
final readonly class AuthorPublisher
{
    public function __construct(private string $id) {}

    public function __invoke(AuthorRepository $authorRepository, EventBus $eventBus, AvatarStorage $avatarStorage): void
    {
        $author = $authorRepository->find(new AuthorId($this->id)) ?? throw AuthorNotFound::withId($this->id);

        $author->publish();

        $author->save($authorRepository, $eventBus, $avatarStorage);
    }
}
