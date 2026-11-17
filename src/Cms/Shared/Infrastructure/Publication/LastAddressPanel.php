<?php

declare(strict_types=1);

namespace Cms\Shared\Infrastructure\Publication;

use Cms\Redirect\Application\PublicationOutlookFinder;
use Cms\Redirect\Application\RedirectAtAddressFinder;
use Cms\Redirect\Domain\RedirectRepository;
use Cms\Redirect\Domain\Resource;
use Cms\Redirect\Domain\SiteResources;
use Cms\Shared\Infrastructure\Ui\RedirectNames;
use Shared\Infrastructure\Ui\Page\Page;
use Shared\Infrastructure\Ui\Page\PageComponent;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * What the last address of a resource off the site answers, for its editor:
 * the redirect there, to edit it, or nothing, and a way to create one. A
 * resource on the site, or never on it, has none to show. Any but an image
 * also tells when publishing it would delete the redirect at its address.
 */
final readonly class LastAddressPanel
{
    public function __construct(
        private RedirectRepository $redirectRepository,
        private SiteResources $siteResources,
        private RedirectNames $redirectNames,
        private TranslatorInterface $translator,
        private UrlGeneratorInterface $urlGenerator,
        private ReplacedRedirectNotice $replacedRedirectNotice,
    ) {}

    /**
     * The editor of a page, with what its last address answers right after
     * where it stands, so it is seen before anything else, and whether
     * publishing deletes the redirect at the address it is saved with.
     */
    public function onPage(Page $layout, string $pageId): Page
    {
        return $this->onPublishable($layout, Resource::page($pageId));
    }

    /**
     * The editor of a post, as that of a page.
     */
    public function onPost(Page $layout, string $postId): Page
    {
        return $this->onPublishable($layout, Resource::post($postId));
    }

    /**
     * The editor of a section or a tag, as that of a page.
     */
    public function onTerm(Page $layout, string $termId): Page
    {
        return $this->onPublishable($layout, Resource::term($termId));
    }

    /**
     * The editor of an author, as that of a page.
     */
    public function onAuthor(Page $layout, string $authorId): Page
    {
        return $this->onPublishable($layout, Resource::author($authorId));
    }

    /**
     * The editor of an image, with what its last address answers right after
     * where it stands.
     */
    public function onImage(Page $layout, string $imageId): Page
    {
        return $this->onto($layout, Resource::image($imageId));
    }

    /**
     * With what its last address answers, and whether publishing deletes the
     * redirect at the address it is saved with.
     */
    private function onPublishable(Page $layout, Resource $resource): Page
    {
        $layout = $this->onto($layout, $resource);
        $notice = ($this->replacedRedirectNotice)(new PublicationOutlookFinder($resource)($this->redirectRepository, $this->siteResources));

        return null === $notice ? $layout : $layout->withMain($notice->name, $notice->props, $notice->children);
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

        $response = new RedirectAtAddressFinder($address)($this->redirectRepository);

        if (null === $response) {
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
