<?php

declare(strict_types=1);

namespace Cms\Redirect\Infrastructure\Controller;

use Shared\Domain\SitePath;
use Shared\Infrastructure\Ui\CmsLayout;
use Shared\Infrastructure\Ui\Page\Page;
use Shared\Infrastructure\Ui\Page\PageComponent;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Composes where a redirect is created: the address of the site it answers,
 * and where it leads. The address may come written already, such as when
 * the creation was refused and the editor comes back to it, or given, as the
 * last address of a resource off the site, which isn't changed then.
 */
final readonly class NewRedirectForm
{
    public function __construct(
        private UrlGeneratorInterface $urlGenerator,
        private TranslatorInterface $translator,
        private RedirectTargetField $redirectTargetField,
    ) {}

    /**
     * Composes the whole page from Twig components, so it is long by design.
     *
     * @param ?bool $fixed whether the address is asked to come given, as the last of a resource off the site, and not be written, null when it isn't asked; only an address written already can
     *
     * @SuppressWarnings("PHPMD.BooleanArgumentFlag")
     * @SuppressWarnings("PHPMD.ExcessiveMethodLength")
     */
    public function __invoke(string $source, ?bool $fixed): Page
    {
        $fixed = true === $fixed && '' !== $source;
        $siteUrl = $this->urlGenerator->generate('web_home', referenceType: UrlGeneratorInterface::ABSOLUTE_URL);

        return CmsLayout::page(\sprintf('%s · CMS', $this->translator->trans('cms.redirects.new.title')))
            ->withMain('Shared:PageTitle', [
                'title' => $this->translator->trans('cms.redirects.new.title'),
                'subtitle' => $this->translator->trans('cms.redirects.new.subtitle'),
            ], [
                new PageComponent('Shared:ButtonLink', [
                    'url' => $this->urlGenerator->generate('cms_redirects'),
                    'label' => $this->translator->trans('cms.redirects.back'),
                ]),
            ])
            ->withMain('Shared:Form', [
                'action' => $this->urlGenerator->generate('cms_redirects_create'),
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
                    [...RedirectTargetField::OTHER_KINDS, ...RedirectTargetField::IMAGE_KINDS],
                    RedirectTargetField::leadingTo(RedirectTargetField::PAGE, ''),
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
