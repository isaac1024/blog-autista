<?php

declare(strict_types=1);

namespace Shared\Ui\Page;

use Symfony\Component\HttpFoundation\Response;
use Twig\Environment;

/**
 * Renders every page through the same generic template.
 *
 * Controllers only build a Page; the template decides how regions and
 * components are painted.
 */
final readonly class PageRenderer
{
    public const string TEMPLATE = 'page.html.twig';

    public function __construct(private Environment $twig) {}

    public function render(Page $page): Response
    {
        return new Response($this->twig->render(self::TEMPLATE, ['page' => $page]));
    }
}
