<?php

declare(strict_types=1);

namespace Cms\Settings\Domain;

use Shared\Domain\RichText\Link;

/**
 * One entry of a menu: what it leads to, a page, a post, a section or a tag,
 * an author or an external resource, named by its own label or, without
 * one, by the name it has on the site. The label is a single line, with its
 * whitespace collapsed; a blank one means none.
 */
final readonly class MenuItem
{
    public const int LABEL_MAX_LENGTH = 40;

    private ?string $label;

    public function __construct(
        private Link $target,
        ?string $label,
    ) {
        $label = trim((string) preg_replace('/\s+/u', ' ', (string) $label));

        if (mb_strlen($label) > self::LABEL_MAX_LENGTH) {
            throw InvalidMenu::labelTooLong(self::LABEL_MAX_LENGTH);
        }

        $this->label = '' === $label ? null : $label;
    }

    public function target(): Link
    {
        return $this->target;
    }

    /**
     * What it leads to, under the key of its kind, as a text links it, such
     * as "page_id", and its label.
     *
     * @return array<string, ?string>
     */
    public function toArray(): array
    {
        return [...$this->target->toArray(), 'label' => $this->label];
    }

    /**
     * What it leads to, by its kind and its id, and its label.
     *
     * @return array{type: string, id: string, label: ?string}
     */
    public function view(): array
    {
        return ['type' => $this->target->type()->value, 'id' => $this->target->id(), 'label' => $this->label];
    }
}
