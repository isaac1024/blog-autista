<?php

declare(strict_types=1);

namespace Shared\Domain\RichText;

/**
 * Structured content written in the CMS editors and rendered by the site,
 * independent of any editor: a versioned list of blocks, so the stored format
 * can evolve and the editor can be replaced.
 *
 * Headings go down one level at a time from the title of the page (level 1),
 * so the outline screen readers navigate has no gaps.
 */
final readonly class RichText
{
    public const int VERSION = 1;

    /**
     * @param list<Block> $blocks
     */
    public function __construct(private array $blocks)
    {
        $previousLevel = 1;

        foreach ($blocks as $block) {
            if (!$block instanceof Heading) {
                continue;
            }

            if ($block->level() > $previousLevel + 1) {
                throw InvalidRichText::skippedHeadingLevel($block->level(), $previousLevel);
            }

            $previousLevel = $block->level();
        }
    }

    public static function empty(): self
    {
        return new self([]);
    }

    /**
     * @param array<array-key, mixed> $data the stored format, as toArray() returns it
     */
    public static function fromArray(array $data): self
    {
        $data = Parse::object($data, 'document');

        if (self::VERSION !== ($data['version'] ?? null)) {
            throw InvalidRichText::unsupportedVersion($data['version'] ?? null);
        }

        $blocks = [];

        foreach (Parse::list($data['blocks'] ?? null, 'blocks') as $index => $block) {
            $blocks[] = self::block($block, \sprintf('blocks[%d]', $index));
        }

        return new self($blocks);
    }

    /**
     * @return list<Block>
     */
    public function blocks(): array
    {
        return $this->blocks;
    }

    /**
     * @return array{version: int, blocks: list<array<string, mixed>>}
     */
    public function toArray(): array
    {
        return ['version' => self::VERSION, 'blocks' => array_map(static fn (Block $block): array => $block->toArray(), $this->blocks)];
    }

    private static function block(mixed $data, string $path): Block
    {
        $data = Parse::object($data, $path);
        $type = BlockType::tryFrom(Parse::string($data['type'] ?? null, $path.'.type'))
            ?? throw InvalidRichText::malformed($path.'.type', 'a known block type');

        return match ($type) {
            BlockType::Heading => Heading::fromArray($data, $path),
            BlockType::Paragraph => Paragraph::fromArray($data, $path),
            BlockType::Quote => Quote::fromArray($data, $path),
            BlockType::List => ItemList::fromArray($data, $path),
            BlockType::Callout => Callout::fromArray($data, $path),
            BlockType::Table => Table::fromArray($data, $path),
        };
    }
}
