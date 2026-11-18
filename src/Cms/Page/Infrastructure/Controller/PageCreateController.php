<?php

declare(strict_types=1);

namespace Cms\Page\Infrastructure\Controller;

use Cms\Page\Application\PageCreator;
use Cms\Page\Domain\PageId;
use Cms\Page\Domain\PageRepository;
use Shared\Domain\DomainError;
use Shared\Domain\Event\EventBus;
use Shared\Domain\Transaction;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsCsrfTokenValid;

final class PageCreateController extends AbstractController
{
    #[Route('/pages', name: 'cms_pages_create', methods: ['POST'])]
    #[IsCsrfTokenValid('cms_pages_create')]
    public function __invoke(Request $request, PageRepository $pageRepository, EventBus $eventBus, Transaction $transaction): Response
    {
        $id = PageId::random()->value();

        try {
            new PageCreator($id, $request->request->getString('title'))($pageRepository, $eventBus, $transaction);
        } catch (DomainError $error) {
            // Told on the page it goes back to.
            $this->addFlash('error', ['key' => $error->key(), 'parameters' => $error->parameters()]);

            return $this->redirectToRoute('cms_pages', status: Response::HTTP_SEE_OTHER);
        }

        $this->addFlash('success', ['key' => 'cms.notices.page.created', 'parameters' => []]);

        // The editor goes on with the page just created.
        return $this->redirectToRoute('cms_pages_edit', ['id' => $id], Response::HTTP_SEE_OTHER);
    }
}
