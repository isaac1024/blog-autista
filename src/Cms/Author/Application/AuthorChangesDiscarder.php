<?php

declare(strict_types=1);

namespace Cms\Author\Application;

use Cms\Author\Domain\AuthorId;
use Cms\Author\Domain\AuthorRepository;
use Cms\Author\Domain\AvatarStorage;
use Shared\Domain\Event\EventBus;

/**
 * Throws away what was edited on a published author since it was published,
 * back to the version on the site.
 */
final readonly class AuthorChangesDiscarder
{
    public function __construct(private string $id) {}

    public function __invoke(AuthorRepository $authorRepository, EventBus $eventBus, AvatarStorage $avatarStorage): void
    {
        $author = $authorRepository->find(new AuthorId($this->id)) ?? throw AuthorNotFound::withId($this->id);

        $author->discardChanges();

        $author->save($authorRepository, $eventBus, $avatarStorage);
    }
}
