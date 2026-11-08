<?php

declare(strict_types=1);

namespace Shared\Infrastructure\Twig\Components;

use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

/**
 * Multi-line field, optionally with a character count against a recommended
 * length, such as a meta description. The count says in words whether the
 * text falls short, fits or runs long, never by colour alone.
 *
 * It can also hold a single line, such as a title, shown whole however long.
 *
 * Its props are declared in mount(), as for every component, so it takes as
 * many parameters as it has props. A flag here is a presentation option of a
 * prop, not a sign of a method doing two jobs.
 *
 * @SuppressWarnings("PHPMD.ExcessiveParameterList")
 * @SuppressWarnings("PHPMD.BooleanArgumentFlag")
 */
#[AsTwigComponent]
final class FormTextarea
{
    public string $name = '';

    public string $id = '';

    public string $value = '';

    public int $rows = 3;

    public ?int $recommendedMin = null;

    public ?int $recommendedMax = null;

    /**
     * Whether it holds a single line, such as a title: it grows to show the
     * whole text, and line breaks become spaces.
     */
    public bool $singleLine = false;

    public function mount(
        string $name,
        string $id,
        string $value = '',
        int $rows = 3,
        ?int $recommendedMin = null,
        ?int $recommendedMax = null,
        bool $singleLine = false,
    ): void {
        $this->name = $name;
        $this->id = $id;
        $this->value = $value;
        $this->rows = $rows;
        $this->recommendedMin = $recommendedMin;
        $this->recommendedMax = $recommendedMax;
        $this->singleLine = $singleLine;
    }

    /**
     * Counted when there is a recommended maximum; the minimum is optional.
     */
    public function counted(): bool
    {
        return null !== $this->recommendedMax;
    }

    public function length(): int
    {
        return mb_strlen($this->value);
    }
}
