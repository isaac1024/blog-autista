<?php

declare(strict_types=1);

namespace Cms\Redirect\Infrastructure\Controller;

use Shared\Infrastructure\Ui\Page\PageRenderer;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Where a redirect is created, as NewRedirectForm composes it, with the
 * address it may come with.
 */
final class RedirectNewController extends AbstractController
{
    public function __construct(
        private readonly PageRenderer $pageRenderer,
        private readonly NewRedirectForm $newRedirectForm,
    ) {}

    #[Route('/redirects/new', name: 'cms_redirects_new', methods: ['GET'])]
    public function __invoke(
        #[MapQueryParameter]
        string $source = '',
        #[MapQueryParameter]
        ?bool $fixed = null,
    ): Response {
        return $this->pageRenderer->render(($this->newRedirectForm)($source, $fixed));
    }
}
