<?php

declare(strict_types=1);

namespace Shared\Twig\Components;

use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

/**
 * Multi-line field, optionally with a character count against a recommended
 * length, such as a meta description. The count says in words whether the
 * text falls short, fits or runs long, never by colour alone.
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

    public function mount(
        string $name,
        string $id,
        string $value = '',
        int $rows = 3,
        ?int $recommendedMin = null,
        ?int $recommendedMax = null,
    ): void {
        $this->name = $name;
        $this->id = $id;
        $this->value = $value;
        $this->rows = $rows;
        $this->recommendedMin = $recommendedMin;
        $this->recommendedMax = $recommendedMax;
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
