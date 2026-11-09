<?php

declare(strict_types=1);

namespace Shared\Infrastructure\Ui;

use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * The props of the Shared:Pagination of a list of the CMS: the navigation
 * between its pages, which paints only while there is a page before or after
 * the one shown.
 */
final readonly class ListPagination
{
    public function __construct(
        private TranslatorInterface $translator,
        private UrlGeneratorInterface $urlGenerator,
    ) {}

    /**
     * @param array<string, int|string> $parameters of the route of the list, such as its search
     *
     * @return array{label: string, current: string, previousUrl: ?string, previousLabel: string, nextUrl: ?string, nextLabel: string}
     */
    public function __invoke(string $route, array $parameters, int $page, bool $hasMore): array
    {
        return [
            'label' => $this->translator->trans('cms.pagination.label'),
            'current' => $this->translator->trans('cms.pagination.current', ['%page%' => $page]),
            'previousUrl' => $page > 1 ? $this->url($route, $parameters, $page - 1) : null,
            'previousLabel' => $this->translator->trans('cms.pagination.previous'),
            'nextUrl' => $hasMore ? $this->url($route, $parameters, $page + 1) : null,
            'nextLabel' => $this->translator->trans('cms.pagination.next'),
        ];
    }

    /**
     * The first page goes without a number, as links to the list lead there.
     *
     * @param array<string, int|string> $parameters
     */
    private function url(string $route, array $parameters, int $page): string
    {
        return $this->urlGenerator->generate($route, 1 === $page ? $parameters : [...$parameters, 'page' => $page]);
    }
}
