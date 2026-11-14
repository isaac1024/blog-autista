<?php

declare(strict_types=1);

namespace Cms\Author\Application;

use Cms\Author\Domain\AuthorId;
use Cms\Author\Domain\AuthorRepository;
use Cms\Author\Domain\AuthorUsage;
use Cms\Author\Domain\AvatarStorage;
use Shared\Domain\Event\EventBus;

/**
 * Deletes an existing author, failing when it doesn't exist.
 */
final readonly class AuthorDeleter
{
    public function __construct(private string $id) {}

    public function __invoke(AuthorRepository $authorRepository, EventBus $eventBus, AvatarStorage $avatarStorage, AuthorUsage $authorUsage): void
    {
        $author = $authorRepository->find(new AuthorId($this->id)) ?? throw AuthorNotFound::withId($this->id);

        $author->delete($authorRepository, $eventBus, $avatarStorage, $authorUsage);
    }
}
