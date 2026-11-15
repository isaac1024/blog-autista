<?php

declare(strict_types=1);

namespace Shared\Infrastructure\Ui\Page;

use Shared\Infrastructure\Http\FrameSources;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Twig\Environment;

/**
 * Renders a page through the layout it names, letting it frame the
 * resources it embeds.
 *
 * Controllers only build a Page; the layout decides how regions and
 * components are painted.
 */
final readonly class PageRenderer
{
    public function __construct(
        private Environment $twig,
        private RequestStack $requestStack,
    ) {}

    public function render(Page $page): Response
    {
        $request = $this->requestStack->getCurrentRequest();

        if (null !== $request && [] !== $page->frames) {
            FrameSources::allow($request, $page->frames);
        }

        return new Response($this->twig->render($page->layout, ['page' => $page]));
    }
}
