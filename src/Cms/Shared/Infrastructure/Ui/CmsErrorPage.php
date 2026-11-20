<?php

declare(strict_types=1);

namespace Cms\Shared\Infrastructure\Ui;

use Shared\Infrastructure\Ui\Page\Page;
use Shared\Infrastructure\Ui\Page\PageComponent;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Composes the page of an error the CMS answers a request with, alone on the
 * screen, as the login is, since whoever opens a preview link may not be
 * signed in:
 *
 * - what isn't there was perhaps deleted, and the page leads back to the
 *   list of the module its address belongs to, or to the CMS;
 * - a preview link refused has expired or was changed: only whoever sent it
 *   can send another, so the page leads nowhere;
 * - any other request asked wrong leads back to the page of the CMS it came
 *   from, or to the CMS.
 */
final readonly class CmsErrorPage
{
    /**
     * Where the addresses of a preview start: none leads into the CMS.
     */
    private const string PREVIEWS = '/preview/';

    /**
     * The list of each module, by the first segment of its addresses: its
     * route, the parameters of the route and the name the menu gives it.
     */
    private const array LISTS = [
        'posts' => ['cms_posts', [], 'cms.nav.posts'],
        'pages' => ['cms_pages', [], 'cms.nav.pages'],
        'images' => ['cms_images', [], 'cms.nav.images'],
        'sections' => ['cms_terms', ['taxonomy' => 'sections'], 'cms.nav.sections'],
        'tags' => ['cms_terms', ['taxonomy' => 'tags'], 'cms.nav.tags'],
        'authors' => ['cms_authors', [], 'cms.nav.authors'],
        'externals' => ['cms_externals', [], 'cms.nav.externals'],
        'redirects' => ['cms_redirects', [], 'cms.nav.redirects'],
    ];

    public function __construct(
        private UrlGeneratorInterface $urlGenerator,
        private TranslatorInterface $translator,
    ) {}

    /**
     * @param string  $path    the address asked for, from its first slash
     * @param ?string $referer the page of the CMS the request came from, if any
     */
    public function __invoke(int $status, string $path, ?string $referer): Page
    {
        $preview = str_starts_with($path, self::PREVIEWS);
        $error = match (true) {
            Response::HTTP_NOT_FOUND === $status => 'not_found',
            Response::HTTP_FORBIDDEN === $status && $preview => 'preview',
            default => 'invalid',
        };
        $title = $this->translator->trans('cms.error.'.$error.'.title');

        return Page::create(\sprintf('%s · %s', $title, $this->translator->trans('cms.login.brand')))
            ->withMainClass('grid min-h-dvh content-center justify-items-center bg-bg p-[clamp(1rem,5vw,3rem)] [&>*]:w-[min(100%,32rem)]')
            ->withMain('Shared:Panel', [], [
                new PageComponent('Shared:Brand', [
                    'brand' => $this->translator->trans('cms.login.brand'),
                    'attributes' => ['class' => 'text-lg text-accent'],
                ]),
                new PageComponent('Shared:PageTitle', [
                    'title' => $title,
                    'subtitle' => $this->translator->trans('cms.error.'.$error.'.message'),
                    'variant' => 'login',
                ]),
                ...($preview ? [] : [$this->way($error, $path, $referer)]),
            ]);
    }

    private function way(string $error, string $path, ?string $referer): PageComponent
    {
        $list = self::LISTS[explode('/', $path)[1] ?? ''] ?? null;

        [$label, $url] = match (true) {
            'not_found' === $error && null !== $list => [
                $this->translator->trans('cms.error.back_to', ['%list%' => $this->translator->trans($list[2])]),
                $this->urlGenerator->generate($list[0], $list[1]),
            ],
            'invalid' === $error && null !== $referer => [$this->translator->trans('cms.error.go_back'), $referer],
            default => [$this->translator->trans('cms.error.home'), $this->urlGenerator->generate('cms_home')],
        };

        return new PageComponent('Shared:ButtonLink', ['label' => $label, 'url' => $url]);
    }
}
