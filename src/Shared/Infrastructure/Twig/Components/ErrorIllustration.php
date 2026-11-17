<?php

declare(strict_types=1);

namespace Shared\Infrastructure\Twig\Components;

use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

/**
 * The drawing on top of a page of an error, one for each reason, as a
 * SiteError names it: a path that ends at a blank sign where nothing was
 * found, the mark a picture left on a wall where something was taken off,
 * a steaming cup where the site failed and asks to come back in a while.
 * It only adorns: the title says the same.
 */
#[AsTwigComponent]
final class ErrorIllustration
{
    public string $error = '';

    public function mount(string $error): void
    {
        $this->error = $error;
    }
}
