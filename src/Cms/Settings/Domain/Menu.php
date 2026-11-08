<?php

declare(strict_types=1);

namespace Cms\Settings\Domain;

/**
 * A menu of the site: the pages it leads to, in order, each once, and few
 * enough to read at a glance. It may be empty, and its pages needn't be on
 * the site: the site paints only those that are.
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

        $pageIds = $this->pageIds();

        if (\count($pageIds) !== \count(array_unique($pageIds))) {
            throw InvalidMenu::repeatedPage();
        }
    }

    public static function empty(): self
    {
        return new self([]);
    }

    /**
     * Builds it from what a form submits or what is stored: a list of
     * entries, each with the id of its page and an optional label.
     *
     * @param array<array-key, mixed> $items
     */
    public static function fromArray(array $items): self
    {
        $menu = [];

        foreach ($items as $item) {
            if (!\is_array($item) || !\is_string($item['page_id'] ?? null)) {
                throw InvalidMenu::malformed();
            }

            $label = $item['label'] ?? null;

            if (null !== $label && !\is_string($label)) {
                throw InvalidMenu::malformed();
            }

            $menu[] = new MenuItem(new PageId($item['page_id']), $label);
        }

        return new self($menu);
    }

    /**
     * @return list<string>
     */
    public function pageIds(): array
    {
        return array_map(static fn (MenuItem $item): string => $item->pageId()->value(), $this->items);
    }

    /**
     * @return list<array{page_id: string, label: ?string}>
     */
    public function toArray(): array
    {
        return array_map(static fn (MenuItem $item): array => $item->toArray(), $this->items);
    }

    public function equals(self $other): bool
    {
        return $this->toArray() === $other->toArray();
    }
}
