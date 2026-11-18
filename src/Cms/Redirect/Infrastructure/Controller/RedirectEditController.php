<?php

declare(strict_types=1);

namespace Cms\Redirect\Infrastructure\Controller;

use Cms\Redirect\Application\RedirectFinder;
use Cms\Redirect\Application\RedirectNotFound;
use Cms\Redirect\Domain\RedirectRepository;
use Cms\Shared\Infrastructure\Ui\KeptEdit;
use Shared\Infrastructure\Ui\Page\PageRenderer;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;

/**
 * Where a redirect is edited, as RedirectEditor composes it.
 */
final class RedirectEditController extends AbstractController
{
    public function __construct(
        private readonly PageRenderer $pageRenderer,
        private readonly RedirectEditor $redirectEditor,
        private readonly KeptEdit $keptEdit,
    ) {}

    #[Route('/redirects/{id}', name: 'cms_redirects_edit', requirements: ['id' => Requirement::UUID], methods: ['GET'])]
    public function __invoke(string $id, RedirectRepository $redirectRepository, Request $request): Response
    {
        try {
            $redirect = new RedirectFinder($id)($redirectRepository);
        } catch (RedirectNotFound $exception) {
            throw $this->createNotFoundException($exception->getMessage(), $exception);
        }

        return $this->pageRenderer->render(KeptEdit::onto(($this->redirectEditor)($redirect), $this->keptEdit->taken($request)));
    }
}
