<?php

declare(strict_types=1);

namespace Cms\Author\Infrastructure\Controller;

use Cms\Author\Application\AuthorDeleter;
use Cms\Author\Application\AuthorNotFound;
use Cms\Author\Domain\AuthorRepository;
use Cms\Author\Domain\AuthorUsage;
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
 * Deletes an author off the site, with its avatars, back to the list.
 */
final class AuthorDeleteController extends AbstractController
{
    #[Route('/authors/{id}/delete', name: 'cms_authors_delete', requirements: ['id' => Requirement::UUID], methods: ['POST'])]
    public function __invoke(string $id, Request $request, AuthorRepository $authorRepository, EventBus $eventBus, AvatarStorage $avatarStorage, AuthorUsage $authorUsage, CsrfRequestGuard $csrf): Response
    {
        $csrf->assertValid($request);

        try {
            new AuthorDeleter($id)($authorRepository, $eventBus, $avatarStorage, $authorUsage);
        } catch (AuthorNotFound $exception) {
            throw $this->createNotFoundException($exception->getMessage(), $exception);
        } catch (DomainError $error) {
            // Told on the page it goes back to.
            $this->addFlash('error', ['key' => $error->key(), 'parameters' => $error->parameters()]);

            return $this->redirectToRoute('cms_authors_edit', ['id' => $id], Response::HTTP_SEE_OTHER);
        }

        $this->addFlash('success', ['key' => 'cms.notices.author.deleted', 'parameters' => []]);

        return $this->redirectToRoute('cms_authors', status: Response::HTTP_SEE_OTHER);
    }
}
