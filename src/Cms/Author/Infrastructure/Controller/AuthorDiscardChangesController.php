<?php

declare(strict_types=1);

namespace Cms\Author\Infrastructure\Controller;

use Cms\Author\Application\AuthorChangesDiscarder;
use Cms\Author\Application\AuthorNotFound;
use Cms\Author\Domain\AuthorRepository;
use Cms\Author\Domain\AvatarStorage;
use Shared\Domain\DomainError;
use Shared\Domain\Event\EventBus;
use Shared\Domain\Transaction;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Security\Http\Attribute\IsCsrfTokenValid;

/**
 * Goes back to the editor, which shows the author as it is on the site again.
 */
final class AuthorDiscardChangesController extends AbstractController
{
    #[Route('/authors/{id}/discard', name: 'cms_authors_discard', requirements: ['id' => Requirement::UUID], methods: ['POST'])]
    #[IsCsrfTokenValid('cms_authors_discard')]
    public function __invoke(string $id, AuthorRepository $authorRepository, EventBus $eventBus, Transaction $transaction, AvatarStorage $avatarStorage): Response
    {
        try {
            new AuthorChangesDiscarder($id)($authorRepository, $eventBus, $transaction, $avatarStorage);
        } catch (AuthorNotFound $exception) {
            throw $this->createNotFoundException($exception->getMessage(), $exception);
        } catch (DomainError $error) {
            // Told on the page it goes back to.
            $this->addFlash('error', ['key' => $error->key(), 'parameters' => $error->parameters()]);

            return $this->redirectToRoute('cms_authors_edit', ['id' => $id], Response::HTTP_SEE_OTHER);
        }

        $this->addFlash('success', ['key' => 'cms.notices.author.discarded', 'parameters' => []]);

        return $this->redirectToRoute('cms_authors_edit', ['id' => $id], status: Response::HTTP_SEE_OTHER);
    }
}
