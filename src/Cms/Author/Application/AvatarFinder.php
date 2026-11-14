<?php

declare(strict_types=1);

namespace Cms\Author\Application;

use Cms\Author\Domain\AuthorId;
use Cms\Author\Domain\AuthorRepository;
use Cms\Author\Domain\AvatarPath;

/**
 * Finds the file of an avatar of an author, by its name: one of the version
 * being edited or of the one last published, and no other.
 */
final readonly class AvatarFinder
{
    public function __construct(
        private string $id,
        private string $avatar,
    ) {}

    /**
     * @return string where the file is kept, relative to the avatar storage
     */
    public function __invoke(AuthorRepository $authorRepository): string
    {
        $author = $authorRepository->find(new AuthorId($this->id)) ?? throw AuthorNotFound::withId($this->id);
        $avatar = new AvatarPath($this->avatar);

        if (!$author->hasAvatar($avatar)) {
            throw AuthorNotFound::withId($this->id);
        }

        return $avatar->value();
    }
}
