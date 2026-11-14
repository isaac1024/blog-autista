<?php

declare(strict_types=1);

namespace Web\Author\Application;

use Web\Author\Domain\AuthorId;
use Web\Author\Domain\AuthorRepository;
use Web\Author\Domain\AvatarCopies;
use Web\Author\Domain\CmsAuthorUnpublished;

/**
 * Takes off the site an author the CMS unpublished. An author already gone
 * stays gone, so the same event can be taken twice.
 */
final readonly class AuthorRemover
{
    public function __construct(private CmsAuthorUnpublished $event) {}

    public function __invoke(AuthorRepository $authorRepository, AvatarCopies $avatarCopies): void
    {
        $authorRepository->find(new AuthorId($this->event->aggregateId()))?->unpublish($authorRepository, $avatarCopies);
    }
}
