<?php

declare(strict_types=1);

namespace Cms\Redirect\Infrastructure\Controller;

use Cms\Redirect\Application\RedirectDeleter;
use Cms\Redirect\Application\RedirectNotFound;
use Cms\Redirect\Domain\RedirectRepository;
use Shared\Domain\MutualExclusion;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Security\Http\Attribute\IsCsrfTokenValid;

final class RedirectDeleteController extends AbstractController
{
    #[Route('/redirects/{id}/delete', name: 'cms_redirects_delete', requirements: ['id' => Requirement::UUID], methods: ['POST'])]
    #[IsCsrfTokenValid('cms_redirects_delete')]
    public function __invoke(string $id, RedirectRepository $redirectRepository, MutualExclusion $mutualExclusion): Response
    {
        try {
            new RedirectDeleter($id)($redirectRepository, $mutualExclusion);
        } catch (RedirectNotFound $exception) {
            throw $this->createNotFoundException($exception->getMessage(), $exception);
        }

        $this->addFlash('success', ['key' => 'cms.notices.redirect.deleted', 'parameters' => []]);

        return $this->redirectToRoute('cms_redirects', status: Response::HTTP_SEE_OTHER);
    }
}
