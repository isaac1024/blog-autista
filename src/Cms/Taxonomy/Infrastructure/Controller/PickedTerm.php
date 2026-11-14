<?php

declare(strict_types=1);

namespace Cms\Taxonomy\Infrastructure\Controller;

use Cms\Taxonomy\Application\TermResponse;
use Cms\Taxonomy\Domain\TermStatus;

/**
 * A term as a field that picks it shows it: its name, as the title of what
 * is picked, and where it stands for the site.
 */
final readonly class PickedTerm
{
    public function __construct(private TermState $termState) {}

    /**
     * @return array{id: string, title: string, status: string, published: bool}
     */
    public function __invoke(TermResponse $term): array
    {
        return [
            'id' => $term->id,
            'title' => $term->name,
            'status' => $this->termState->label($term),
            'published' => TermStatus::Published->value === $term->status,
        ];
    }
}
