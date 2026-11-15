<?php

declare(strict_types=1);

namespace Cms\External\Domain;

use Shared\Domain\External\ResourceKind;

/**
 * A study, such as a paper of a journal: who wrote it, where and when it was
 * published. Its card tells them all, as a study is cited.
 */
final readonly class StudyDetails extends ResourceDetails
{
    public const int AUTHORS_MAX_LENGTH = 300;

    public const int JOURNAL_MAX_LENGTH = 200;

    public const int MIN_YEAR = 1600;

    public const int MAX_YEAR = 2100;

    private ?string $authors;

    private ?string $journal;

    public function __construct(
        ?string $authors,
        ?string $journal,
        private ?int $year,
    ) {
        $this->authors = DetailText::line('authors', $authors, self::AUTHORS_MAX_LENGTH);
        $this->journal = DetailText::line('journal', $journal, self::JOURNAL_MAX_LENGTH);

        if (null !== $year && ($year < self::MIN_YEAR || $year > self::MAX_YEAR)) {
            throw InvalidResourceDetails::yearOutOfRange(self::MIN_YEAR, self::MAX_YEAR);
        }
    }

    #[\Override]
    public function kind(): ResourceKind
    {
        return ResourceKind::Study;
    }

    #[\Override]
    public function assertPublishable(): void
    {
        if (null === $this->authors) {
            throw InvalidResourcePublication::withoutAuthors();
        }

        if (null === $this->journal) {
            throw InvalidResourcePublication::withoutJournal();
        }

        if (null === $this->year) {
            throw InvalidResourcePublication::withoutYear();
        }
    }

    #[\Override]
    public function toArray(): array
    {
        return ['authors' => $this->authors, 'journal' => $this->journal, 'year' => $this->year];
    }
}
