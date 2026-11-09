<?php

declare(strict_types=1);

namespace Cms\Redirect\Infrastructure\Controller;

use Cms\Redirect\Domain\ResourceType;
use Shared\Domain\SitePath;
use Shared\Infrastructure\Ui\CmsLayout;
use Shared\Infrastructure\Ui\Page\Page;
use Shared\Infrastructure\Ui\Page\PageComponent;
use Shared\Infrastructure\Ui\Page\PageRenderer;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Where a redirect is created: the address of the site it answers, and
 * where it leads. The address may come written already, such as when the
 * creation was refused and the editor comes back to it, or given, as the
 * last address of a resource off the site, which isn't changed then.
 */
final class RedirectNewController extends AbstractController
{
    public function __construct(
        private readonly PageRenderer $pageRenderer,
        private readonly TranslatorInterface $translator,
        private readonly RedirectTargetField $redirectTargetField,
    ) {}

    #[Route('/redirects/new', name: 'cms_redirects_new', methods: ['GET'])]
    public function __invoke(
        #[MapQueryParameter]
        string $source = '',
        #[MapQueryParameter]
        int $fixed = 0,
    ): Response {
        return $this->pageRenderer->render($this->page($source, 1 === $fixed && '' !== $source));
    }

    /**
     * Composes the whole page from Twig components, so it is long by design.
     *
     * @param bool $fixed whether the address comes given, as the last of a resource off the site, and isn't written
     *
     * @SuppressWarnings("PHPMD.BooleanArgumentFlag")
     * @SuppressWarnings("PHPMD.ExcessiveMethodLength")
     */
    private function page(string $source, bool $fixed): Page
    {
        $siteUrl = $this->generateUrl('web_home', referenceType: UrlGeneratorInterface::ABSOLUTE_URL);

        return CmsLayout::page(\sprintf('%s · CMS', $this->translator->trans('cms.redirects.new.title')))
            ->withMain('Shared:PageTitle', [
                'title' => $this->translator->trans('cms.redirects.new.title'),
                'subtitle' => $this->translator->trans('cms.redirects.new.subtitle'),
            ], [
                new PageComponent('Shared:ButtonLink', [
                    'url' => $this->generateUrl('cms_redirects'),
                    'label' => $this->translator->trans('cms.redirects.back'),
                ]),
            ])
            ->withMain('Shared:Form', [
                'action' => $this->generateUrl('cms_redirects_create'),
                'method' => 'post',
                'csrfTokenId' => 'cms_redirects_create',
            ], [
                new PageComponent('Shared:FormField', [], [
                    new PageComponent('Shared:FormLabel', [
                        'for' => 'redirect-source',
                        'text' => $this->translator->trans('cms.redirects.form.source.label'),
                    ]),
                    new PageComponent('Shared:FormHint', [
                        'id' => 'redirect-source-hint',
                        'text' => $this->translator->trans('cms.redirects.form.source.hint', ['%example%' => $siteUrl.'una-pagina-antigua.html']),
                    ]),
                    new PageComponent('Shared:FormInput', [
                        'name' => 'source',
                        'id' => 'redirect-source',
                        'type' => 'url',
                        'value' => $source,
                        'attributes' => [
                            'required' => true,
                            'readonly' => $fixed,
                            'maxlength' => \strlen($siteUrl) + SitePath::MAX_LENGTH,
                            'aria-describedby' => 'redirect-source-hint',
                        ],
                    ]),
                ]),
                // A given address says why it can't be changed, and stays given if refused.
                ...($fixed ? [
                    new PageComponent('Shared:Notice', [
                        'message' => $this->translator->trans('cms.redirects.new.fixed'),
                    ]),
                    new PageComponent('Shared:FormInput', [
                        'name' => 'fixed',
                        'type' => 'hidden',
                        'value' => '1',
                    ]),
                ] : []),
                ($this->redirectTargetField)(
                    '',
                    $this->translator->trans('cms.redirects.form.action.legend'),
                    [ResourceType::Page, ResourceType::Image],
                    ['action' => ResourceType::Page->value, 'page_id' => '', 'image_id' => ''],
                    [],
                    [],
                ),
                new PageComponent('Shared:ActionBar', [], [
                    new PageComponent('Shared:FormButton', [
                        'label' => $this->translator->trans('cms.redirects.new.submit'),
                    ]),
                ]),
            ]);
    }
}
