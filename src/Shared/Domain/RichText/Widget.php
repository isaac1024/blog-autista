<?php

declare(strict_types=1);

namespace Shared\Domain\RichText;

/**
 * A block that shows something the content points to, rather than text
 * written in it: for now, a page of the blog as a card of related content.
 *
 * The page is kept by its id alone, as a link is: whoever shows it reads its
 * title, and whether it is on the site, where it is kept, so they are always
 * the latest.
 */
final readonly class Widget implements Block
{
    private const string UUID = '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i';

    public function __construct(
        private WidgetKind $kind,
        private string $pageId,
    ) {
        if (1 !== preg_match(self::UUID, $pageId)) {
            throw InvalidRichText::malformed('widget.page_id', 'the id of a page');
        }
    }

    /**
     * @param array<array-key, mixed> $data
     */
    public static function fromArray(array $data, string $path): self
    {
        $kind = WidgetKind::tryFrom(Parse::string($data['kind'] ?? null, $path.'.kind'))
            ?? throw InvalidRichText::malformed($path.'.kind', 'a known kind of widget');
        $pageId = Parse::string($data['page_id'] ?? null, $path.'.page_id');

        // Checked here too, to tell where in the document it fails.
        if (1 !== preg_match(self::UUID, $pageId)) {
            throw InvalidRichText::malformed($path.'.page_id', 'the id of a page');
        }

        return new self($kind, $pageId);
    }

    public function kind(): WidgetKind
    {
        return $this->kind;
    }

    public function pageId(): string
    {
        return $this->pageId;
    }

    #[\Override]
    public function type(): BlockType
    {
        return BlockType::Widget;
    }

    #[\Override]
    public function texts(): array
    {
        return [];
    }

    #[\Override]
    public function toArray(): array
    {
        return ['type' => $this->type()->value, 'kind' => $this->kind->value, 'page_id' => $this->pageId];
    }
}
