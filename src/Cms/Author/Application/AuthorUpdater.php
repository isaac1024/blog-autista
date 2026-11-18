<?php

declare(strict_types=1);

namespace Cms\Author\Application;

use Cms\Author\Domain\AuthorId;
use Cms\Author\Domain\AuthorRepository;
use Cms\Author\Domain\AuthorSlugAvailability;
use Cms\Author\Domain\AvatarFile;
use Cms\Author\Domain\AvatarStorage;
use Shared\Domain\Event\EventBus;
use Shared\Domain\Transaction;

/**
 * Saves everything edited on an existing author: its name, slug, bio and
 * search engine settings, and its avatar when a new one came. A published
 * author stays on the site as it was until it is published again.
 */
final readonly class AuthorUpdater
{
    /**
     * It takes every field the editor posts, one parameter each.
     *
     * @SuppressWarnings("PHPMD.ExcessiveParameterList")
     *
     * @param array<array-key, mixed> $bio    in the stored rich text format
     * @param ?string                 $avatar path of the avatar uploaded, null when none came
     */
    public function __construct(
        private string $id,
        private string $name,
        private string $slug,
        private array $bio,
        private string $metaDescription,
        private string $robots,
        private ?string $avatar,
    ) {}

    public function __invoke(AuthorRepository $authorRepository, EventBus $eventBus, Transaction $transaction, AvatarStorage $avatarStorage): void
    {
        $author = $authorRepository->find(new AuthorId($this->id)) ?? throw AuthorNotFound::withId($this->id);

        $author->edit($this->name, $this->slug, $this->bio, $this->metaDescription, $this->robots);
        new AuthorSlugAvailability($author)($authorRepository);

        // The file is kept last, once everything else holds.
        if (null !== $this->avatar) {
            $author->replaceAvatar(new AvatarFile($this->avatar), $avatarStorage);
        }

        $author->save($authorRepository, $eventBus, $transaction, $avatarStorage);
    }
}
