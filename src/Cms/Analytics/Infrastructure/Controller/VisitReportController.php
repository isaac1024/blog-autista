<?php

declare(strict_types=1);

namespace Cms\Analytics\Infrastructure\Controller;

use Cms\Analytics\Application\VisitReportFinder;
use Cms\Analytics\Domain\PageTitles;
use Cms\Analytics\Domain\Period;
use Cms\Analytics\Domain\VisitCounts;
use Shared\Infrastructure\Ui\Page\PageRenderer;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\Routing\Attribute\Route;

/**
 * What the site counted of its visits over the last days, up to yesterday,
 * and today so far.
 */
final class VisitReportController extends AbstractController
{
    public function __construct(
        private readonly PageRenderer $pageRenderer,
        private readonly VisitReportPage $visitReportPage,
    ) {}

    #[Route('/analytics', name: 'cms_analytics', methods: ['GET'])]
    public function __invoke(
        VisitCounts $visitCounts,
        PageTitles $pageTitles,
        #[MapQueryParameter]
        int $days = Period::Month->value,
    ): Response {
        $period = Period::tryFrom($days) ?? throw $this->createNotFoundException(\sprintf('The report covers no period of %d days.', $days));

        return $this->pageRenderer->render(($this->visitReportPage)($period, new VisitReportFinder($period)($visitCounts, $pageTitles)));
    }
}
