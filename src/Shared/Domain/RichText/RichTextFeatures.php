<?php

declare(strict_types=1);

namespace Shared\Domain\RichText;

/**
 * What a module lets its content use, out of everything rich text supports:
 * every module declares its own, and its editor only offers that.
 */
final readonly class RichTextFeatures
{
    /**
     * @param list<BlockType>      $blocks
     * @param list<int>            $headingLevels
     * @param list<CalloutVariant> $calloutVariants
     * @param list<Mark>           $marks
     */
    public function __construct(
        private array $blocks,
        private array $headingLevels,
        private array $calloutVariants,
        private array $marks,
    ) {}

    public function assertAllows(RichText $content): void
    {
        foreach ($content->blocks() as $block) {
            if (!\in_array($block->type(), $this->blocks, true)) {
                throw InvalidRichText::notAllowed(\sprintf('A "%s" block', $block->type()->value));
            }

            if ($block instanceof Heading && !\in_array($block->level(), $this->headingLevels, true)) {
                throw InvalidRichText::notAllowed(\sprintf('A level %d heading', $block->level()));
            }

            if ($block instanceof Callout && !\in_array($block->variant(), $this->calloutVariants, true)) {
                throw InvalidRichText::notAllowed(\sprintf('A "%s" callout', $block->variant()->value));
            }

            foreach ($block->texts() as $text) {
                foreach ($text->marks() as $mark) {
                    if (!\in_array($mark, $this->marks, true)) {
                        throw InvalidRichText::notAllowed(\sprintf('The "%s" mark', $mark->value));
                    }
                }
            }
        }
    }

    /**
     * What the editor needs to offer exactly these features.
     *
     * @return array{blocks: list<string>, headingLevels: list<int>, calloutVariants: list<string>, marks: list<string>}
     */
    public function toArray(): array
    {
        return [
            'blocks' => array_map(static fn (BlockType $type): string => $type->value, $this->blocks),
            'headingLevels' => $this->headingLevels,
            'calloutVariants' => array_map(static fn (CalloutVariant $variant): string => $variant->value, $this->calloutVariants),
            'marks' => array_map(static fn (Mark $mark): string => $mark->value, $this->marks),
        ];
    }
}
