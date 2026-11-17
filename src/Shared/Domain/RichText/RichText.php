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
     * Words an adult reads in a minute, in Spanish, at an unhurried pace.
     */
    public const int WORDS_PER_MINUTE = 200;

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
     * Ids of the images it embeds, in order, as often as it embeds them.
     *
     * @return list<string>
     */
    public function imageIds(): array
    {
        $ids = [];

        foreach ($this->blocks as $block) {
            if ($block instanceof EmbeddedImage) {
                $ids[] = $block->imageId();
            }
        }

        return $ids;
    }

    /**
     * Ids of the pages it links to, from its text and its widgets, in order,
     * as often as it links to them.
     *
     * @return list<string>
     */
    public function pageIds(): array
    {
        return $this->linkedIds(LinkType::Page);
    }

    /**
     * Ids of what of the kind it links to, from its text and its widgets, in
     * order, as often as it links to them.
     *
     * @return list<string>
     */
    public function linkedIds(LinkType $type): array
    {
        $ids = [];

        foreach ($this->blocks as $block) {
            if ($block instanceof Widget && null !== $block->target()->idOf($type)) {
                $ids[] = $block->target()->id();
            }

            foreach ($block->texts() as $text) {
                foreach ($text->runs() as $run) {
                    $id = $run->link()?->idOf($type);

                    if (null !== $id) {
                        $ids[] = $id;
                    }
                }
            }
        }

        return $ids;
    }

    /**
     * Ids of what of the kind its mosaics narrow their posts down to, in
     * order, as often as they do. A mosaic links nothing: what it narrows
     * down to is still used, so it is kept while the text keeps it.
     *
     * @return list<string>
     */
    public function filterIds(LinkType $type): array
    {
        $ids = [];

        foreach ($this->mosaics() as $mosaic) {
            array_push($ids, ...$mosaic->filterIds($type));
        }

        return $ids;
    }

    /**
     * Everything it says, as plain text, a line per piece of text, such as
     * what a search reads: no marks, no links, no images.
     */
    public function plainText(): string
    {
        return self::lines($this->blocks);
    }

    /**
     * What its headings say, as plain text, a line per heading: what a text
     * is about, as a search reads it, without every word it says on the way.
     */
    public function headings(): string
    {
        return self::lines(array_filter($this->blocks, static fn (Block $block): bool => $block instanceof Heading));
    }

    /**
     * The minutes it takes to read, at WORDS_PER_MINUTE, rounded up: one at
     * least, as a text with only images or widgets is still looked at.
     */
    public function readingMinutes(): int
    {
        $words = (int) preg_match_all('/[\p{L}\p{N}]+/u', $this->plainText());

        return max(1, (int) ceil($words / self::WORDS_PER_MINUTE));
    }

    /**
     * Its mosaics, in order.
     *
     * @return list<Mosaic>
     */
    public function mosaics(): array
    {
        return array_values(array_filter($this->blocks, static fn (Block $block): bool => $block instanceof Mosaic));
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
            BlockType::Image => EmbeddedImage::fromArray($data, $path),
            BlockType::Widget => Widget::fromArray($data, $path),
            BlockType::Mosaic => Mosaic::fromArray($data, $path),
        };
    }

    /**
     * @param iterable<Block> $blocks
     */
    private static function lines(iterable $blocks): string
    {
        $lines = [];

        foreach ($blocks as $block) {
            foreach ($block->texts() as $text) {
                $lines[] = implode('', array_map(static fn (TextRun $run): string => $run->text(), $text->runs()));
            }
        }

        return implode("\n", $lines);
    }
}
