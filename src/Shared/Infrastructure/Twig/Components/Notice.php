<?php

declare(strict_types=1);

namespace Shared\Infrastructure\Twig\Components;

use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

/**
 * What the last action did, when it went well: the counterpart of Alert.
 */
#[AsTwigComponent]
final class Notice
{
    public string $message = '';

    public function mount(string $message): void
    {
        $this->message = $message;
    }
}
