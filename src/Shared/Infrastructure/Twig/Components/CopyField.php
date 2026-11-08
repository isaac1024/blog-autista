<?php

declare(strict_types=1);

namespace Shared\Infrastructure\Twig\Components;

use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

/**
 * A value to take elsewhere, such as a link to send: shown read-only, with a
 * button that copies it and says so.
 *
 * Its props are declared in mount(), as for every component, so it takes as
 * many parameters as it has props.
 *
 * @SuppressWarnings("PHPMD.ExcessiveParameterList")
 */
#[AsTwigComponent]
final class CopyField
{
    public string $id = '';

    public string $label = '';

    public string $hint = '';

    public string $value = '';

    public string $copyLabel = '';

    public string $copiedMessage = '';

    /**
     * Told when the browser doesn't let it copy, with the value left
     * selected for the editor to copy it.
     */
    public string $failedMessage = '';

    public function mount(
        string $id,
        string $label,
        string $hint,
        string $value,
        string $copyLabel,
        string $copiedMessage,
        string $failedMessage,
    ): void {
        $this->id = $id;
        $this->label = $label;
        $this->hint = $hint;
        $this->value = $value;
        $this->copyLabel = $copyLabel;
        $this->copiedMessage = $copiedMessage;
        $this->failedMessage = $failedMessage;
    }
}
