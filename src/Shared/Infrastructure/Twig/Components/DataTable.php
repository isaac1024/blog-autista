<?php

declare(strict_types=1);

namespace Shared\Infrastructure\Twig\Components;

use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

/**
 * A breakdown as a table: what each row counts, then its figures, aligned
 * to the right. A share draws a thin bar under the name, in one hue, so the
 * rows compare at a glance; the figures beside it say the same in numbers.
 */
#[AsTwigComponent]
final class DataTable
{
    public string $caption = '';

    /**
     * @var list<string>
     */
    public array $columns = [];

    /**
     * @var list<array{name: string, figures: list<string>, share: float}>
     */
    public array $rows = [];

    /**
     * @param list<string>                                                   $columns the heading of the names, then those of the figures
     * @param list<array{name: string, figures: list<string>, share: float}> $rows    share from 0 to 1, of the largest row
     */
    public function mount(string $caption, array $columns, array $rows): void
    {
        // Each row fills every column: its name, then one figure for each.
        foreach ($rows as $row) {
            if (\count($row['figures']) !== \count($columns) - 1) {
                throw new \InvalidArgumentException(\sprintf('A row of "%s" has %d figures for %d columns.', $caption, \count($row['figures']), \count($columns)));
            }
        }

        $this->caption = $caption;
        $this->columns = $columns;
        $this->rows = $rows;
    }
}
