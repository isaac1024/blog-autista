<?php

declare(strict_types=1);

namespace Shared\Infrastructure\Ui\Page;

use Symfony\Component\HttpFoundation\Response;
use Twig\Environment;

/**
 * Renders a page through the layout it names.
 *
 * Controllers only build a Page; the layout decides how regions and
 * components are painted.
 */
final readonly class PageRenderer
{
    public function __construct(private Environment $twig) {}

    public function render(Page $page): Response
    {
        return new Response($this->twig->render($page->layout, ['page' => $page]));
    }
}
