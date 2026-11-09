<?php

declare(strict_types=1);

namespace Cms\Redirect\Infrastructure\Controller;

use Cms\Redirect\Application\RedirectResponse;
use Cms\Redirect\Domain\RedirectRepository;
use Cms\Redirect\Domain\Resource;
use Cms\Redirect\Domain\SiteResources;
use Shared\Infrastructure\Ui\Page\Page;
use Shared\Infrastructure\Ui\Page\PageComponent;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * What the last address of a page or an image off the site answers, for its
 * editor: the redirect there, to edit it, or nothing, and a way to create
 * one. A resource on the site, or never on it, has none to show.
 */
final readonly class LastAddressPanel
{
    public function __construct(
        private RedirectRepository $redirectRepository,
        private SiteResources $siteResources,
        private RedirectNames $redirectNames,
        private TranslatorInterface $translator,
        private UrlGeneratorInterface $urlGenerator,
    ) {}

    /**
     * The editor of a page, with what its last address answers right after
     * where it stands, so it is seen before anything else.
     */
    public function onPage(Page $layout, string $pageId): Page
    {
        return $this->onto($layout, Resource::page($pageId));
    }

    /**
     * The editor of an image, with what its last address answers right after
     * where it stands.
     */
    public function onImage(Page $layout, string $imageId): Page
    {
        return $this->onto($layout, Resource::image($imageId));
    }

    private function onto(Page $layout, Resource $resource): Page
    {
        $panel = $this->panel($resource);

        return null === $panel ? $layout : $layout->withMain($panel->name, $panel->props, $panel->children);
    }

    private function panel(Resource $resource): ?PageComponent
    {
        $address = $this->siteResources->isOnSite($resource) ? null : $this->siteResources->addressOf($resource);

        if (null === $address) {
            return null;
        }

        $redirect = $this->redirectRepository->findBySource($address);

        if (null === $redirect) {
            $url = rtrim($this->urlGenerator->generate('web_home', referenceType: UrlGeneratorInterface::ABSOLUTE_URL), '/').$address->shown();

            return new PageComponent('Shared:Panel', [
                'title' => $this->translator->trans('cms.redirects.last_address.title'),
                'description' => $this->translator->trans('cms.redirects.last_address.not_found', ['%address%' => $address->shown()]),
            ], [
                new PageComponent('Shared:ButtonLink', [
                    'url' => $this->urlGenerator->generate('cms_redirects_new', ['source' => $url, 'fixed' => 1]),
                    'label' => $this->translator->trans('cms.redirects.last_address.create'),
                ]),
            ]);
        }

        $response = RedirectResponse::fromRedirect($redirect);

        return new PageComponent('Shared:Panel', [
            'title' => $this->translator->trans('cms.redirects.last_address.title'),
            'description' => $this->translator->trans('cms.redirects.last_address.redirected', [
                '%address%' => $address->shown(),
                '%target%' => ($this->redirectNames)([$response])[$response->id]['target'],
            ]),
        ], [
            new PageComponent('Shared:ButtonLink', [
                'url' => $this->urlGenerator->generate('cms_redirects_edit', ['id' => $response->id]),
                'label' => $this->translator->trans('cms.redirects.last_address.edit'),
            ]),
        ]);
    }
}
