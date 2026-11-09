<?php

declare(strict_types=1);

namespace Cms\Redirect\Infrastructure\Controller;

use Cms\Redirect\Application\RedirectNotFound;
use Cms\Redirect\Application\RedirectRetargeter;
use Cms\Redirect\Domain\RedirectRepository;
use Cms\Redirect\Domain\SiteResources;
use Shared\Domain\DomainError;
use Shared\Domain\MutualExclusion;
use Shared\Infrastructure\Http\CsrfRequestGuard;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;

final class RedirectUpdateController extends AbstractController
{
    public function __construct(private readonly CsrfRequestGuard $csrf) {}

    #[Route('/redirects/{id}', name: 'cms_redirects_update', requirements: ['id' => Requirement::UUID], methods: ['POST'])]
    public function __invoke(string $id, Request $request, RedirectRepository $redirectRepository, SiteResources $siteResources, MutualExclusion $mutualExclusion): Response
    {
        $this->csrf->assertValid($request);

        try {
            new RedirectRetargeter($id, RedirectTargetField::target(RedirectTargetField::fromRequest($request, '')))($redirectRepository, $siteResources, $mutualExclusion);
        } catch (RedirectNotFound $exception) {
            throw $this->createNotFoundException($exception->getMessage(), $exception);
        } catch (DomainError $error) {
            // Told on the page it goes back to.
            $this->addFlash('error', ['key' => $error->key(), 'parameters' => $error->parameters()]);

            return $this->redirectToRoute('cms_redirects_edit', ['id' => $id], Response::HTTP_SEE_OTHER);
        }

        $this->addFlash('success', ['key' => 'cms.notices.redirect.saved', 'parameters' => []]);

        // The editor stays on the redirect, to go on with it.
        return $this->redirectToRoute('cms_redirects_edit', ['id' => $id], Response::HTTP_SEE_OTHER);
    }
}
