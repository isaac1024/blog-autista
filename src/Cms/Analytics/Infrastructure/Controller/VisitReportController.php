<?php

declare(strict_types=1);

namespace Cms\Analytics\Infrastructure\Controller;

use Cms\Analytics\Application\DayViews;
use Cms\Analytics\Application\ReportRow;
use Cms\Analytics\Application\VisitReport;
use Cms\Analytics\Application\VisitReportFinder;
use Cms\Analytics\Application\VisitSummary;
use Cms\Analytics\Domain\PageTitles;
use Cms\Analytics\Domain\Period;
use Cms\Analytics\Domain\VisitCounts;
use Shared\Infrastructure\Intl\CountryNames;
use Shared\Infrastructure\Ui\CmsLayout;
use Shared\Infrastructure\Ui\Page\Page;
use Shared\Infrastructure\Ui\Page\PageComponent;
use Shared\Infrastructure\Ui\Page\PageRenderer;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * What the site counted of its visits over the last days, up to yesterday,
 * and today so far.
 */
final class VisitReportController extends AbstractController
{
    public function __construct(
        private readonly PageRenderer $pageRenderer,
        private readonly TranslatorInterface $translator,
        private readonly CountryNames $countryNames,
    ) {}

    #[Route('/analytics', name: 'cms_analytics', methods: ['GET'])]
    public function __invoke(
        VisitCounts $visitCounts,
        PageTitles $pageTitles,
        #[MapQueryParameter]
        int $days = Period::Month->value,
    ): Response {
        $period = Period::tryFrom($days) ?? throw $this->createNotFoundException(\sprintf('The report covers no period of %d days.', $days));

        return $this->pageRenderer->render($this->page($period, new VisitReportFinder($period)($visitCounts, $pageTitles)));
    }

    /**
     * Composes the whole page from Twig components, so it is long by design.
     *
     * @SuppressWarnings("PHPMD.ExcessiveMethodLength")
     */
    private function page(Period $current, VisitReport $report): Page
    {
        $page = CmsLayout::page(\sprintf('%s · CMS', $this->trans('cms.analytics.title')))
            ->withMain('Shared:PageTitle', [
                'title' => $this->trans('cms.analytics.title'),
                'subtitle' => $this->trans('cms.analytics.subtitle'),
            ])
            ->withMain('Shared:SegmentedNav', [
                'label' => $this->trans('cms.analytics.periods.label'),
                'links' => array_map(fn (Period $period): array => [
                    'label' => $this->trans('cms.analytics.periods.days', ['%days%' => $period->days()]),
                    'url' => $this->generateUrl('cms_analytics', ['days' => $period->days()]),
                    'current' => $period === $current,
                ], Period::cases()),
            ]);

        if (0 === $report->period->views && 0 === $report->today->views) {
            return $page->withMain('Shared:EmptyState', [
                'title' => $this->trans('cms.analytics.empty.title'),
                'message' => $this->trans('cms.analytics.empty.message'),
            ]);
        }

        return $page
            ->withMain('Shared:Panel', ['title' => $this->trans('cms.analytics.today.title')], [
                $this->stats($report->today, 'cms.analytics.visitors'),
                ...$this->breakdowns($report->today, false),
            ])
            ->withMain('Shared:Panel', ['title' => $this->trans('cms.analytics.period.title', ['%days%' => $current->days()])], [
                $this->stats($report->period, 'cms.analytics.daily_visitors', $this->trans('cms.analytics.daily_visitors_hint')),
                $this->chart($report->days),
                ...$this->breakdowns($report->period, true),
            ]);
    }

    private function stats(VisitSummary $summary, string $visitorsLabel, ?string $visitorsHint = null): PageComponent
    {
        return new PageComponent('Shared:Stats', ['items' => [
            ['label' => $this->trans('cms.analytics.views'), 'value' => $this->figure($summary->views)],
            ['label' => $this->trans($visitorsLabel), 'value' => $this->figure($summary->visitors)] + (null === $visitorsHint ? [] : ['hint' => $visitorsHint]),
        ]]);
    }

    /**
     * @param list<DayViews> $days
     */
    private function chart(array $days): PageComponent
    {
        return new PageComponent('Shared:ColumnChart', [
            'title' => $this->trans('cms.analytics.chart.title'),
            'tableLabel' => $this->trans('cms.analytics.chart.table'),
            'columnLabel' => $this->trans('cms.analytics.chart.day'),
            'valueLabel' => $this->trans('cms.analytics.chart.unit'),
            'points' => array_map(fn (DayViews $day): array => [
                'label' => new \DateTimeImmutable($day->day)->format('j/n/Y'),
                'value' => $day->views,
                'shown' => $this->figure($day->views),
            ], $days),
        ]);
    }

    /**
     * The tables of a summary that have any row: today only its pages and
     * channels, the period every one.
     *
     * @return list<PageComponent>
     */
    private function breakdowns(VisitSummary $summary, bool $all): array
    {
        $tables = [
            $this->table('pages', $summary->pages, fn (ReportRow $row): string => $row->name ?? $this->trans('cms.analytics.pages.deleted')),
            $this->table('channels', $summary->channels, fn (ReportRow $row): string => $this->trans('cms.analytics.channel.'.$row->value)),
        ];

        if ($all) {
            $tables[] = $this->table('sources', $summary->sources, fn (ReportRow $row): string => $row->value ?? $this->trans('cms.analytics.sources.others'));
            $tables[] = $this->table('devices', $summary->devices, fn (ReportRow $row): string => $this->trans('cms.analytics.device.'.$row->value));
            $tables[] = $this->table('countries', $summary->countries, fn (ReportRow $row): string => null === $row->value
                ? $this->trans('cms.analytics.countries.unknown')
                : $this->countryNames->name($row->value, $this->translator->getLocale()));
        }

        return array_values(array_filter($tables));
    }

    /**
     * @param list<ReportRow>             $rows
     * @param \Closure(ReportRow): string $name
     */
    private function table(string $breakdown, array $rows, \Closure $name): ?PageComponent
    {
        if ([] === $rows) {
            return null;
        }

        $largest = max(1, ...array_map(static fn (ReportRow $row): int => $row->views, $rows));

        return new PageComponent('Shared:DataTable', [
            'caption' => $this->trans('cms.analytics.'.$breakdown.'.caption'),
            'columns' => [
                $this->trans('cms.analytics.'.$breakdown.'.name'),
                $this->trans('cms.analytics.'.$breakdown.'.views'),
                $this->trans('cms.analytics.visitors'),
            ],
            'rows' => array_map(fn (ReportRow $row): array => [
                'name' => $name($row),
                'figures' => [$this->figure($row->views), $this->figure($row->visitors)],
                'share' => $row->views / $largest,
            ], $rows),
        ]);
    }

    /**
     * A figure with the thousands separator of the language.
     */
    private function figure(int $figure): string
    {
        return 'es' === $this->translator->getLocale() ? number_format($figure, 0, ',', '.') : number_format($figure);
    }

    /**
     * @param array<string, int|string> $parameters
     */
    private function trans(string $key, array $parameters = []): string
    {
        return $this->translator->trans($key, $parameters);
    }
}
