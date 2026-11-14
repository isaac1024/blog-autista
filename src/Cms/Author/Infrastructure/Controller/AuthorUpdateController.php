<?php

declare(strict_types=1);

namespace Cms\Author\Infrastructure\Controller;

use Cms\Author\Application\AuthorNotFound;
use Cms\Author\Application\AuthorUpdater;
use Cms\Author\Domain\AuthorRepository;
use Cms\Author\Domain\AvatarStorage;
use Shared\Domain\DomainError;
use Shared\Domain\Event\EventBus;
use Shared\Infrastructure\Http\CsrfRequestGuard;
use Shared\Infrastructure\RichText\TiptapRichText;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;

final class AuthorUpdateController extends AbstractController
{
    public function __construct(private readonly TiptapRichText $tiptapRichText) {}

    #[Route('/authors/{id}', name: 'cms_authors_update', requirements: ['id' => Requirement::UUID], methods: ['POST'])]
    public function __invoke(string $id, Request $request, AuthorRepository $authorRepository, EventBus $eventBus, AvatarStorage $avatarStorage, CsrfRequestGuard $csrf): Response
    {
        $csrf->assertValid($request);
        $avatar = $request->files->get('avatar');

        try {
            new AuthorUpdater(
                $id,
                $request->request->getString('name'),
                $request->request->getString('slug'),
                // The editor posts its own document, which only this adapter knows.
                $this->tiptapRichText->toStored($request->request->getString('bio')),
                $request->request->getString('meta_description'),
                $request->request->getString('robots'),
                // The avatar is only replaced when a new one comes.
                $avatar instanceof UploadedFile && $avatar->isValid() ? $avatar->getPathname() : null,
            )($authorRepository, $eventBus, $avatarStorage);
        } catch (AuthorNotFound $exception) {
            throw $this->createNotFoundException($exception->getMessage(), $exception);
        } catch (DomainError $error) {
            // Told on the page it goes back to.
            $this->addFlash('error', ['key' => $error->key(), 'parameters' => $error->parameters()]);

            return $this->redirectToRoute('cms_authors_edit', ['id' => $id], Response::HTTP_SEE_OTHER);
        }

        $this->addFlash('success', ['key' => 'cms.notices.author.saved', 'parameters' => []]);

        // The editor stays on the author, to go on with it.
        return $this->redirectToRoute('cms_authors_edit', ['id' => $id], Response::HTTP_SEE_OTHER);
    }
}
