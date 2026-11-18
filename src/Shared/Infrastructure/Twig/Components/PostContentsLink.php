<?php

declare(strict_types=1);

namespace Shared\Infrastructure\Twig\Components;

use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

/**
 * The way back to the contents of a post at the end of each of its parts,
 * on small screens, where the contents are left behind above the text.
 */
#[AsTwigComponent]
final class PostContentsLink {}
