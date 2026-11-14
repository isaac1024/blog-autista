<?php

declare(strict_types=1);

namespace Web\Author\Application;

use Web\Author\Domain\Author;
use Web\Author\Domain\AuthorId;
use Web\Author\Domain\AuthorRepository;
use Web\Author\Domain\AvatarCopies;
use Web\Author\Domain\CmsAuthorPublished;

/**
 * Puts on the site the version of an author the CMS published, in place of
 * the one there, if any.
 */
final readonly class AuthorSaver
{
    public function __construct(private CmsAuthorPublished $event) {}

    public function __invoke(AuthorRepository $authorRepository, AvatarCopies $avatarCopies): void
    {
        $author = $authorRepository->find(new AuthorId($this->event->aggregateId()));

        if (null === $author) {
            $author = Author::publish($this->event);
        } else {
            $author->republish($this->event);
        }

        $author->save($authorRepository, $avatarCopies);
    }
}
