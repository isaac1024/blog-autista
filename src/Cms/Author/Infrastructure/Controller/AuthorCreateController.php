<?php

declare(strict_types=1);

namespace Cms\Author\Infrastructure\Controller;

use Cms\Author\Application\AuthorCreator;
use Cms\Author\Domain\AuthorId;
use Cms\Author\Domain\AuthorRepository;
use Cms\Author\Domain\AvatarStorage;
use Shared\Domain\DomainError;
use Shared\Domain\Event\EventBus;
use Shared\Infrastructure\Http\CsrfRequestGuard;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class AuthorCreateController extends AbstractController
{
    #[Route('/authors', name: 'cms_authors_create', methods: ['POST'])]
    public function __invoke(Request $request, AuthorRepository $authorRepository, EventBus $eventBus, AvatarStorage $avatarStorage, CsrfRequestGuard $csrf): Response
    {
        $csrf->assertValid($request);

        $id = AuthorId::random()->value();

        try {
            new AuthorCreator($id, $request->request->getString('name'))($authorRepository, $eventBus, $avatarStorage);
        } catch (DomainError $error) {
            // Told on the page it goes back to.
            $this->addFlash('error', ['key' => $error->key(), 'parameters' => $error->parameters()]);

            return $this->redirectToRoute('cms_authors', status: Response::HTTP_SEE_OTHER);
        }

        $this->addFlash('success', ['key' => 'cms.notices.author.created', 'parameters' => []]);

        // The editor goes on with the author just created.
        return $this->redirectToRoute('cms_authors_edit', ['id' => $id], Response::HTTP_SEE_OTHER);
    }
}
