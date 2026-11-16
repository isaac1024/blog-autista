<?php

declare(strict_types=1);

namespace Shared\Infrastructure\Ui;

use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * The props of the Shared:Pagination of a list of the CMS, or of the posts
 * of the site: the navigation between its pages, which paints only while
 * there is a page before or after the one shown. The site numbers its pages
 * in Spanish, as its addresses are.
 */
final readonly class ListPagination
{
    /**
     * The page a list opens at, which its links lead to without a number.
     */
    public const int FIRST_PAGE = 1;

    /**
     * The query parameter that numbers the pages of a list of the site.
     */
    public const string SITE_PARAMETER = 'pagina';

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
        return $this->pagination('cms.pagination', 'page', $route, $parameters, $page, $hasMore);
    }

    /**
     * Between the pages of a list of posts of the site, newest first.
     *
     * @param array<string, int|string> $parameters of the route of the list, such as the slug of a section
     *
     * @return array{label: string, current: string, previousUrl: ?string, previousLabel: string, nextUrl: ?string, nextLabel: string}
     */
    public function site(string $route, array $parameters, int $page, bool $hasMore): array
    {
        return $this->pagination('site.posts.pagination', self::SITE_PARAMETER, $route, $parameters, $page, $hasMore);
    }

    /**
     * Where a page of a list of posts of the site is, the first one without
     * a number.
     *
     * @param array<string, int|string> $parameters
     */
    public function siteUrl(string $route, array $parameters, int $page, int $referenceType): string
    {
        return $this->urlGenerator->generate($route, self::FIRST_PAGE === $page ? $parameters : [...$parameters, self::SITE_PARAMETER => $page], $referenceType);
    }

    /**
     * @param array<string, int|string> $parameters
     *
     * @return array{label: string, current: string, previousUrl: ?string, previousLabel: string, nextUrl: ?string, nextLabel: string}
     */
    private function pagination(string $texts, string $parameter, string $route, array $parameters, int $page, bool $hasMore): array
    {
        return [
            'label' => $this->translator->trans($texts.'.label'),
            'current' => $this->translator->trans($texts.'.current', ['%page%' => $page]),
            'previousUrl' => $page > self::FIRST_PAGE ? $this->url($parameter, $route, $parameters, $page - 1) : null,
            'previousLabel' => $this->translator->trans($texts.'.previous'),
            'nextUrl' => $hasMore ? $this->url($parameter, $route, $parameters, $page + 1) : null,
            'nextLabel' => $this->translator->trans($texts.'.next'),
        ];
    }

    /**
     * The first page goes without a number, as links to the list lead there.
     *
     * @param array<string, int|string> $parameters
     */
    private function url(string $parameter, string $route, array $parameters, int $page): string
    {
        return $this->urlGenerator->generate($route, self::FIRST_PAGE === $page ? $parameters : [...$parameters, $parameter => $page]);
    }
}
