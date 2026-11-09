<?php

declare(strict_types=1);

namespace Cms\Redirect\Infrastructure\Controller;

use Cms\Redirect\Application\RedirectDeleter;
use Cms\Redirect\Application\RedirectNotFound;
use Cms\Redirect\Domain\RedirectRepository;
use Shared\Domain\MutualExclusion;
use Shared\Infrastructure\Http\CsrfRequestGuard;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;

final class RedirectDeleteController extends AbstractController
{
    public function __construct(private readonly CsrfRequestGuard $csrf) {}

    #[Route('/redirects/{id}/delete', name: 'cms_redirects_delete', requirements: ['id' => Requirement::UUID], methods: ['POST'])]
    public function __invoke(string $id, Request $request, RedirectRepository $redirectRepository, MutualExclusion $mutualExclusion): Response
    {
        $this->csrf->assertValid($request);

        try {
            new RedirectDeleter($id)($redirectRepository, $mutualExclusion);
        } catch (RedirectNotFound $exception) {
            throw $this->createNotFoundException($exception->getMessage(), $exception);
        }

        $this->addFlash('success', ['key' => 'cms.notices.redirect.deleted', 'parameters' => []]);

        return $this->redirectToRoute('cms_redirects', status: Response::HTTP_SEE_OTHER);
    }
}
