<?php

declare(strict_types=1);

namespace Cms\Author\Infrastructure\Controller;

use Cms\Author\Application\AuthorResponse;
use Cms\Author\Domain\AuthorStatus;

/**
 * An author as a field that picks one shows it: its name, as the title of
 * what is picked, and where it stands for the site.
 */
final readonly class PickedAuthor
{
    public function __construct(private AuthorState $authorState) {}

    /**
     * @return array{id: string, title: string, status: string, published: bool}
     */
    public function __invoke(AuthorResponse $author): array
    {
        return [
            'id' => $author->id,
            'title' => $author->name,
            'status' => $this->authorState->label($author),
            'published' => AuthorStatus::Published->value === $author->status,
        ];
    }
}
