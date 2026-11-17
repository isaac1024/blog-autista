<?php

declare(strict_types=1);

namespace Shared\Domain\RichText;

/**
 * A stretch of text that shares the same marks.
 */
final readonly class TextRun
{
    /**
     * Only read from its stored form, which checks it links exactly when it
     * has the link mark, and tells where in the document it doesn't.
     *
     * @param list<Mark> $marks
     * @param ?Link      $link  what the link mark links to, null without one
     */
    private function __construct(
        private string $text,
        private array $marks,
        private ?Link $link,
    ) {
        if ('' === $text) {
            throw InvalidRichText::empty('text');
        }
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
        $link = Link::fromArray($data, $path);
        self::assertLinks($marks, $link, $path);

        return new self(Parse::string($data['text'] ?? null, $path.'.text'), $marks, $link);
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

    public function link(): ?Link
    {
        return $this->link;
    }

    /**
     * @return array{text: string, marks?: list<string>, page_id?: string, post_id?: string, term_id?: string, author_id?: string, external_id?: string}
     */
    public function toArray(): array
    {
        $data = [] === $this->marks
            ? ['text' => $this->text]
            : ['text' => $this->text, 'marks' => array_map(static fn (Mark $mark): string => $mark->value, $this->marks)];
        $link = $this->link;

        return match ($link?->type()) {
            null => $data,
            LinkType::Page => [...$data, 'page_id' => $link->id()],
            LinkType::Post => [...$data, 'post_id' => $link->id()],
            LinkType::Term => [...$data, 'term_id' => $link->id()],
            LinkType::Author => [...$data, 'author_id' => $link->id()],
            LinkType::External => [...$data, 'external_id' => $link->id()],
        };
    }

    /**
     * A text links to a content exactly when it has the link mark.
     *
     * @param list<Mark> $marks
     */
    private static function assertLinks(array $marks, ?Link $link, string $path): void
    {
        if (\in_array(Mark::Link, $marks, true) !== (null !== $link)) {
            throw InvalidRichText::malformed($path.'.page_id', 'a link to a content exactly when the text has the link mark');
        }
    }
}
