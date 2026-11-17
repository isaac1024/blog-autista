<?php

declare(strict_types=1);

namespace Cms\Redirect\Infrastructure\Controller;

use Cms\Redirect\Application\RedirectCreator;
use Cms\Redirect\Domain\RedirectId;
use Cms\Redirect\Domain\RedirectRepository;
use Cms\Redirect\Domain\SiteResources;
use Cms\Shared\Infrastructure\Publication\RedirectTargetField;
use Shared\Domain\DomainError;
use Shared\Domain\MutualExclusion;
use Shared\Infrastructure\Http\CsrfRequestGuard;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class RedirectCreateController extends AbstractController
{
    public function __construct(
        private readonly CsrfRequestGuard $csrf,
        #[Autowire(env: 'WEB_HOST')]
        private readonly string $webHost,
    ) {}

    #[Route('/redirects', name: 'cms_redirects_create', methods: ['POST'])]
    public function __invoke(Request $request, RedirectRepository $redirectRepository, SiteResources $siteResources, MutualExclusion $mutualExclusion): Response
    {
        $this->csrf->assertValid($request);

        $id = RedirectId::random()->value();
        $source = $request->request->getString('source');

        try {
            new RedirectCreator($id, $source, RedirectTargetField::target(RedirectTargetField::fromRequest($request, '')), $this->webHost)(
                $redirectRepository,
                $siteResources,
                $mutualExclusion,
            );
        } catch (DomainError $error) {
            // Told on the page it goes back to, with the address written.
            $this->addFlash('error', ['key' => $error->key(), 'parameters' => $error->parameters()]);

            return $this->redirectToRoute('cms_redirects_new', ['source' => $source, 'fixed' => $request->request->getBoolean('fixed') ? 1 : null], Response::HTTP_SEE_OTHER);
        }

        $this->addFlash('success', ['key' => 'cms.notices.redirect.created', 'parameters' => []]);

        return $this->redirectToRoute('cms_redirects_edit', ['id' => $id], Response::HTTP_SEE_OTHER);
    }
}
