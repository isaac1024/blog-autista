<?php

declare(strict_types=1);

namespace Web\Settings\Domain;

use Shared\Domain\RichText\InvalidRichText;
use Shared\Domain\RichText\Link;

/**
 * Reads the entries of a menu as the CMS publishes them: each with the id of
 * what it leads to under the key of its kind, as a text links it, such as
 * "page_id", and its label. Those kept by the kind and the id of what they
 * lead to read as they are, so menus kept before either way stay readable.
 */
final readonly class MenuEntries
{
    /**
     * @param array<array-key, mixed> $items
     *
     * @return list<array{type: string, id: string, label: ?string}>
     */
    public static function read(array $items): array
    {
        $entries = [];

        foreach ($items as $item) {
            $label = \is_array($item) ? $item['label'] ?? null : null;

            if (!\is_array($item) || (null !== $label && !\is_string($label))) {
                throw new \UnexpectedValueException('A menu entry has no "label" string or null.');
            }

            if (\is_string($item['type'] ?? null) && \is_string($item['id'] ?? null)) {
                $entries[] = ['type' => $item['type'], 'id' => $item['id'], 'label' => $label];

                continue;
            }

            try {
                $target = Link::fromArray($item, 'menu');
            } catch (InvalidRichText $exception) {
                throw new \UnexpectedValueException('A menu entry leads nowhere it can.', 0, $exception);
            }

            if (null === $target) {
                throw new \UnexpectedValueException('A menu entry leads nowhere.');
            }

            $entries[] = ['type' => $target->type()->value, 'id' => $target->id(), 'label' => $label];
        }

        return $entries;
    }
}
