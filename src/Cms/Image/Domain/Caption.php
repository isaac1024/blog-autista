<?php

declare(strict_types=1);

namespace Cms\Image\Domain;

use Shared\Domain\RichText\BlockType;
use Shared\Domain\RichText\Mark;
use Shared\Domain\RichText\Paragraph;
use Shared\Domain\RichText\RichText;
use Shared\Domain\RichText\RichTextFeatures;

/**
 * Caption of an image: context everyone reads under it, such as what it is
 * or where it comes from, which the alternative text doesn't repeat. Search
 * engines take it as context of the image too.
 *
 * A single paragraph of text that may link to pages, such as the one the
 * image comes from: no bold, since emphasis isn't announced to everyone
 * alike. It stays within 200 characters, short enough to read as a caption.
 */
final readonly class Caption
{
    public const int MAX_LENGTH = 200;

    private function __construct(private RichText $text) {}

    /**
     * The caption written, in the stored rich text format; null when nothing
     * is written, since an image may have none until it is published.
     *
     * @param array<array-key, mixed> $stored
     */
    public static function fromStored(array $stored): ?self
    {
        $text = RichText::fromArray($stored);
        $blocks = $text->blocks();

        if ([] === $blocks) {
            return null;
        }

        self::features()->assertAllows($text);

        if (1 !== \count($blocks) || !$blocks[0] instanceof Paragraph) {
            throw InvalidCaption::notASingleParagraph();
        }

        $length = array_sum(array_map(
            static fn ($run): int => mb_strlen($run->text()),
            $blocks[0]->text()->runs(),
        ));

        if ($length > self::MAX_LENGTH) {
            throw InvalidCaption::tooLong(self::MAX_LENGTH);
        }

        return new self($text);
    }

    /**
     * What a caption can use: paragraphs, and links to pages in them.
     */
    public static function features(): RichTextFeatures
    {
        return new RichTextFeatures(
            blocks: [BlockType::Paragraph],
            headingLevels: [],
            calloutVariants: [],
            marks: [Mark::Link],
        );
    }

    public function text(): RichText
    {
        return $this->text;
    }
}
