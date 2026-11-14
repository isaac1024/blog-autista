<?php

declare(strict_types=1);

namespace Cms\Author\Application;

use Cms\Author\Domain\AuthorId;
use Cms\Author\Domain\AuthorRedirects;
use Cms\Author\Domain\AuthorRepository;
use Cms\Author\Domain\AvatarStorage;
use Shared\Domain\Event\EventBus;

/**
 * Takes a published author off the site.
 */
final readonly class AuthorUnpublisher
{
    public function __construct(private string $id) {}

    public function __invoke(AuthorRepository $authorRepository, EventBus $eventBus, AvatarStorage $avatarStorage, AuthorRedirects $authorRedirects): void
    {
        $author = $authorRepository->find(new AuthorId($this->id)) ?? throw AuthorNotFound::withId($this->id);

        $author->unpublish($authorRedirects);

        $author->save($authorRepository, $eventBus, $avatarStorage);
    }
}
