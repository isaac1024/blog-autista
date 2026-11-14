<?php

declare(strict_types=1);

namespace Cms\Author\Infrastructure\Controller;

use Cms\Author\Application\AuthorNotFound;
use Cms\Author\Application\AuthorPublisher;
use Cms\Author\Domain\AuthorRepository;
use Cms\Author\Domain\AvatarStorage;
use Shared\Domain\DomainError;
use Shared\Domain\Event\EventBus;
use Shared\Infrastructure\Http\CsrfRequestGuard;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;

/**
 * Publishes an author as it is saved; what it lacks is told on its editor.
 */
final class AuthorPublishController extends AbstractController
{
    #[Route('/authors/{id}/publish', name: 'cms_authors_publish', requirements: ['id' => Requirement::UUID], methods: ['POST'])]
    public function __invoke(string $id, Request $request, AuthorRepository $authorRepository, EventBus $eventBus, AvatarStorage $avatarStorage, CsrfRequestGuard $csrf): Response
    {
        $csrf->assertValid($request);

        try {
            new AuthorPublisher($id)($authorRepository, $eventBus, $avatarStorage);
        } catch (AuthorNotFound $exception) {
            throw $this->createNotFoundException($exception->getMessage(), $exception);
        } catch (DomainError $error) {
            // Told on the page it goes back to.
            $this->addFlash('error', ['key' => $error->key(), 'parameters' => $error->parameters()]);

            return $this->redirectToRoute('cms_authors_edit', ['id' => $id], Response::HTTP_SEE_OTHER);
        }

        $this->addFlash('success', ['key' => 'cms.notices.author.published', 'parameters' => []]);

        return $this->redirectToRoute('cms_authors_edit', ['id' => $id], status: Response::HTTP_SEE_OTHER);
    }
}
