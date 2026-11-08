<?php

declare(strict_types=1);

namespace Cms\Settings\Domain;

/**
 * One entry of a menu: a page, named by its own label or, without one, by
 * the title the page has on the site. The label is a single line, with its
 * whitespace collapsed; a blank one means none.
 */
final readonly class MenuItem
{
    public const int LABEL_MAX_LENGTH = 40;

    private ?string $label;

    public function __construct(
        private PageId $pageId,
        ?string $label,
    ) {
        $label = trim((string) preg_replace('/\s+/u', ' ', (string) $label));

        if (mb_strlen($label) > self::LABEL_MAX_LENGTH) {
            throw InvalidMenu::labelTooLong(self::LABEL_MAX_LENGTH);
        }

        $this->label = '' === $label ? null : $label;
    }

    public function pageId(): PageId
    {
        return $this->pageId;
    }

    /**
     * @return array{page_id: string, label: ?string}
     */
    public function toArray(): array
    {
        return ['page_id' => $this->pageId->value(), 'label' => $this->label];
    }
}
