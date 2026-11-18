<?php

declare(strict_types=1);

namespace Cms\Page\Infrastructure\Controller;

use Cms\Page\Application\PageFinder;
use Cms\Page\Application\PageNotFound;
use Cms\Page\Domain\PageRepository;
use Cms\Shared\Infrastructure\Ui\KeptEdit;
use Shared\Domain\Clock;
use Shared\Infrastructure\Http\SignedUrls;
use Shared\Infrastructure\Ui\Page\PageRenderer;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\Requirement\Requirement;

/**
 * The editor of a page, with a link to its preview that anyone can open
 * for a while.
 */
final class PageEditController extends AbstractController
{
    /**
     * How long a shared preview link lets anyone see the page.
     */
    private const string SHARED_PREVIEW_VALIDITY = 'P7D';

    public function __construct(
        private readonly PageRenderer $pageRenderer,
        private readonly PageEditor $pageEditor,
        private readonly KeptEdit $keptEdit,
    ) {}

    #[Route('/pages/{id}', name: 'cms_pages_edit', requirements: ['id' => Requirement::UUID], methods: ['GET'])]
    public function __invoke(string $id, PageRepository $pageRepository, SignedUrls $signedUrls, Request $request): Response
    {
        try {
            $page = new PageFinder($id)($pageRepository);
        } catch (PageNotFound $exception) {
            throw $this->createNotFoundException($exception->getMessage(), $exception);
        }

        // Signed anew each time the editor loads, valid for a while from now.
        $expiresAt = Clock::now()->add(new \DateInterval(self::SHARED_PREVIEW_VALIDITY));
        $sharedPreview = new SharedPreviewLink(
            $signedUrls->sign($this->generateUrl('cms_pages_preview', ['id' => $page->id], UrlGeneratorInterface::ABSOLUTE_URL), $expiresAt),
            $expiresAt,
        );

        return $this->pageRenderer->render(KeptEdit::onto(($this->pageEditor)($page, $sharedPreview), $this->keptEdit->taken($request)));
    }
}
