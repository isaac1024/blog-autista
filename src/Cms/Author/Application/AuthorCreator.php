<?php

declare(strict_types=1);

namespace Cms\Author\Application;

use Cms\Author\Domain\Author;
use Cms\Author\Domain\AuthorRepository;
use Cms\Author\Domain\AuthorSlugAvailability;
use Cms\Author\Domain\AvatarStorage;
use Shared\Domain\Event\EventBus;

/**
 * Creates an author from the submitted name, deriving its slug from it.
 */
final readonly class AuthorCreator
{
    public function __construct(
        private string $id,
        private string $name,
    ) {}

    public function __invoke(AuthorRepository $authorRepository, EventBus $eventBus, AvatarStorage $avatarStorage): void
    {
        $author = Author::create($this->id, $this->name);

        new AuthorSlugAvailability($author)($authorRepository);
        $author->save($authorRepository, $eventBus, $avatarStorage);
    }
}
