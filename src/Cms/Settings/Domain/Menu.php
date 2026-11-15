<?php

declare(strict_types=1);

namespace Cms\Settings\Domain;

use Shared\Domain\RichText\InvalidRichText;
use Shared\Domain\RichText\Link;
use Shared\Domain\RichText\LinkType;

/**
 * A menu of the site: what it leads to, pages, posts, sections and tags,
 * authors or external resources, in order, each once, and few enough to
 * read at a glance. It may be empty, and what it leads to needn't be on the
 * site: the site paints only what is.
 */
final readonly class Menu
{
    public const int MAX_ITEMS = 8;

    /**
     * @param list<MenuItem> $items
     */
    public function __construct(private array $items)
    {
        if (\count($items) > self::MAX_ITEMS) {
            throw InvalidMenu::tooManyItems(self::MAX_ITEMS);
        }

        $targets = array_map(static fn (MenuItem $item): string => $item->target()->type()->value.':'.$item->target()->id(), $items);

        if (\count($targets) !== \count(array_unique($targets))) {
            throw InvalidMenu::repeatedEntry();
        }
    }

    public static function empty(): self
    {
        return new self([]);
    }

    /**
     * Builds it from what a form submits or what is stored: a list of
     * entries, each with the id of what it leads to under the key of its
     * kind, as a text links it, such as "page_id", and an optional label.
     *
     * @param array<array-key, mixed> $items
     */
    public static function fromArray(array $items): self
    {
        $menu = [];

        foreach ($items as $item) {
            $label = \is_array($item) ? $item['label'] ?? null : null;

            if (!\is_array($item) || (null !== $label && !\is_string($label))) {
                throw InvalidMenu::malformed();
            }

            try {
                $target = Link::fromArray($item, 'menu');
            } catch (InvalidRichText $exception) {
                throw InvalidMenu::malformed($exception);
            }

            $menu[] = new MenuItem($target ?? throw InvalidMenu::malformed(), $label);
        }

        return new self($menu);
    }

    /**
     * The ids of what it leads to of the kind, in order.
     *
     * @return list<string>
     */
    public function linkedIds(LinkType $type): array
    {
        $ids = [];

        foreach ($this->items as $item) {
            $id = $item->target()->idOf($type);

            if (null !== $id) {
                $ids[] = $id;
            }
        }

        return $ids;
    }

    /**
     * @return list<array<string, ?string>>
     */
    public function toArray(): array
    {
        return array_map(static fn (MenuItem $item): array => $item->toArray(), $this->items);
    }

    /**
     * Its entries, by the kind and the id of what each leads to.
     *
     * @return list<array{type: string, id: string, label: ?string}>
     */
    public function view(): array
    {
        return array_map(static fn (MenuItem $item): array => $item->view(), $this->items);
    }

    public function equals(self $other): bool
    {
        return $this->toArray() === $other->toArray();
    }
}
