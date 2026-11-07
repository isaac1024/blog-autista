<?php

declare(strict_types=1);

namespace Shared\Infrastructure\Twig\Components;

use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

/**
 * A table within written content, with a header row. On a narrow screen it
 * scrolls sideways, and its scrolling region can be reached by keyboard.
 */
#[AsTwigComponent]
final class ContentTable
{
    /**
     * @var list<list<array{text: string, bold: bool}>>
     */
    public array $header = [];

    /**
     * @var list<list<list<array{text: string, bold: bool}>>>
     */
    public array $rows = [];

    /**
     * @param list<list<array{text: string, bold: bool}>>       $header runs of text of each header cell
     * @param list<list<list<array{text: string, bold: bool}>>> $rows   runs of text of each cell, row by row
     */
    public function mount(array $header, array $rows): void
    {
        $this->header = $header;
        $this->rows = $rows;
    }
}
