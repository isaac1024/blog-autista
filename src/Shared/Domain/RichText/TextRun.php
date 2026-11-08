<?php

declare(strict_types=1);

namespace Shared\Domain\RichText;

/**
 * A stretch of text that shares the same marks.
 */
final readonly class TextRun
{
    private const string UUID = '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i';

    /**
     * @param list<Mark> $marks
     * @param ?string    $pageId the page linked by the link mark, null without one
     */
    public function __construct(
        private string $text,
        private array $marks,
        private ?string $pageId,
    ) {
        if ('' === $text) {
            throw InvalidRichText::empty('text');
        }

        self::assertLinksAPage($marks, $pageId, 'text');
    }

    public static function fromArray(mixed $data, string $path): self
    {
        $data = Parse::object($data, $path);
        $marks = [];

        foreach (Parse::list($data['marks'] ?? [], $path.'.marks') as $index => $mark) {
            $marks[] = Mark::tryFrom(Parse::string($mark, \sprintf('%s.marks[%d]', $path, $index)))
                ?? throw InvalidRichText::malformed(\sprintf('%s.marks[%d]', $path, $index), 'a known mark');
        }

        $marks = array_values(array_unique($marks, \SORT_REGULAR));
        $pageId = null === ($data['page_id'] ?? null) ? null : Parse::string($data['page_id'], $path.'.page_id');
        // Checked here too, to tell where in the document it fails.
        self::assertLinksAPage($marks, $pageId, $path);

        return new self(Parse::string($data['text'] ?? null, $path.'.text'), $marks, $pageId);
    }

    public function text(): string
    {
        return $this->text;
    }

    /**
     * @return list<Mark>
     */
    public function marks(): array
    {
        return $this->marks;
    }

    public function pageId(): ?string
    {
        return $this->pageId;
    }

    /**
     * @return array{text: string, marks?: list<string>, page_id?: string}
     */
    public function toArray(): array
    {
        $data = ['text' => $this->text];

        if ([] !== $this->marks) {
            $data['marks'] = array_map(static fn (Mark $mark): string => $mark->value, $this->marks);
        }

        if (null !== $this->pageId) {
            $data['page_id'] = $this->pageId;
        }

        return $data;
    }

    /**
     * A text has the id of a page exactly when it has the link mark.
     *
     * @param list<Mark> $marks
     */
    private static function assertLinksAPage(array $marks, ?string $pageId, string $path): void
    {
        if (\in_array(Mark::Link, $marks, true) !== (null !== $pageId)) {
            throw InvalidRichText::malformed($path.'.page_id', 'a page id exactly when the text has the link mark');
        }

        if (null !== $pageId && 1 !== preg_match(self::UUID, $pageId)) {
            throw InvalidRichText::malformed($path.'.page_id', 'the id of a page');
        }
    }
}
