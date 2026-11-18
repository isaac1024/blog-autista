<?php

declare(strict_types=1);

namespace Cms\Analytics\Domain;

/**
 * The contents seen over some days, of every kind together: the most seen,
 * as many as the report shows apart at most, and what all of them add up
 * to, so those left out are told as one.
 */
final readonly class SeenContents
{
    /**
     * How many contents the report shows apart, the most seen; the rest add
     * up in one line.
     */
    public const int SHOWN_APART = 50;

    /**
     * @param list<ContentTotal> $mostSeen most seen first, by kind and id among equals
     * @param int                $count    how many contents were seen, those shown apart included
     * @param int                $views    the pages seen of every content, those shown apart included
     * @param int                $visitors their readers of each day, summed
     */
    public function __construct(
        public array $mostSeen,
        public int $count,
        public int $views,
        public int $visitors,
    ) {}

    /**
     * What the contents not shown apart add up to, none when every one is.
     */
    public function others(): ?OtherContents
    {
        $count = $this->count - \count($this->mostSeen);

        if ($count <= 0) {
            return null;
        }

        $views = $this->views;
        $visitors = $this->visitors;

        foreach ($this->mostSeen as $content) {
            $views -= $content->views;
            $visitors -= $content->visitors;
        }

        return new OtherContents($count, $views, $visitors);
    }
}
