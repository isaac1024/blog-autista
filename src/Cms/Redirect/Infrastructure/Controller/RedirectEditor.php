<?php

declare(strict_types=1);

namespace Cms\Redirect\Infrastructure\Controller;

use Cms\Redirect\Application\RedirectResponse;
use Cms\Redirect\Domain\ResourceType;
use Cms\Shared\Infrastructure\Publication\RedirectTargetField;
use Cms\Shared\Infrastructure\Ui\RedirectNames;
use Shared\Infrastructure\Ui\CmsLayout;
use Shared\Infrastructure\Ui\Page\Page;
use Shared\Infrastructure\Ui\Page\PageComponent;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Composes the editor of a redirect: its address stays, and only where it
 * leads changes. When the address was the last of a resource off the site,
 * it says so, since what links to that resource follows the redirect too.
 */
final readonly class RedirectEditor
{
    private const string DELETE_MODAL = 'delete-redirect';

    public function __construct(
        private UrlGeneratorInterface $urlGenerator,
        private TranslatorInterface $translator,
        private RedirectTargetField $redirectTargetField,
        private RedirectNames $redirectNames,
    ) {}

    /**
     * Composes the whole page from Twig components, so it is long by design.
     *
     * @SuppressWarnings("PHPMD.ExcessiveMethodLength")
     */
    public function __invoke(RedirectResponse $redirect): Page
    {
        $formatter = new \IntlDateFormatter($this->translator->getLocale(), \IntlDateFormatter::MEDIUM, \IntlDateFormatter::NONE);
        $names = ($this->redirectNames)([$redirect])[$redirect->id];
        $origin = $names['origin'];

        $page = CmsLayout::page(\sprintf('%s · %s · CMS', $redirect->source, $this->translator->trans('cms.redirects.edit.title')))
            ->withMain('Shared:PageTitle', [
                'title' => $redirect->source,
                'subtitle' => $this->translator->trans('cms.redirects.edit.dates', [
                    '%created%' => $formatter->format($redirect->createdAt),
                    '%updated%' => $formatter->format($redirect->updatedAt),
                ]),
                'status' => $this->translator->trans(null === $redirect->targetType ? 'cms.redirects.status.gone' : 'cms.redirects.status.moved'),
            ], [
                new PageComponent('Shared:ButtonLink', [
                    'url' => $this->urlGenerator->generate('cms_redirects'),
                    'label' => $this->translator->trans('cms.redirects.back'),
                ]),
                $this->deleteModal($redirect, $origin),
            ]);

        if (null !== $origin) {
            $page = $page->withMain('Shared:Notice', [
                'message' => $this->translator->trans('cms.redirects.edit.origin', ['%origin%' => $origin]),
            ]);
        }

        // An address of an image only leads to images; any other, to anything
        // but images. A target deleted since is picked as the kind of its
        // type, a term as a section.
        $kind = null === $redirect->targetType ? null : $names['kind'] ?? (ResourceType::Term->value === $redirect->targetType ? RedirectTargetField::SECTION : $redirect->targetType);

        return $page->withMain('Shared:Form', [
            'action' => $this->urlGenerator->generate('cms_redirects_update', ['id' => $redirect->id]),
            'method' => 'post',
            'csrfTokenId' => 'cms_redirects_update',
        ], [
            ($this->redirectTargetField)(
                '',
                $this->translator->trans('cms.redirects.form.action.legend'),
                RedirectTargetField::kindsFor($redirect->image),
                RedirectTargetField::leadingTo($kind, $redirect->targetId ?? ''),
                [],
                [],
            ),
            new PageComponent('Shared:ActionBar', [], [
                new PageComponent('Shared:FormButton', [
                    'label' => $this->translator->trans('cms.redirects.edit.submit'),
                ]),
            ]),
        ]);
    }

    /**
     * Deleting is confirmed first, telling what follows when the address was
     * the last of a resource off the site.
     */
    private function deleteModal(RedirectResponse $redirect, ?string $origin): PageComponent
    {
        return new PageComponent('Shared:Modal', [
            'id' => self::DELETE_MODAL,
            'trigger' => $this->translator->trans('cms.redirects.delete.trigger'),
            'title' => $this->translator->trans('cms.redirects.delete.title'),
            'description' => null === $origin
                ? $this->translator->trans('cms.redirects.delete.description', ['%source%' => $redirect->source])
                : $this->translator->trans('cms.redirects.delete.description_origin', ['%source%' => $redirect->source, '%origin%' => $origin]),
            'variant' => 'danger',
        ], [
            new PageComponent('Shared:Form', [
                'action' => $this->urlGenerator->generate('cms_redirects_delete', ['id' => $redirect->id]),
                'method' => 'post',
                'csrfTokenId' => 'cms_redirects_delete',
            ], [
                new PageComponent('Shared:FormButton', [
                    'label' => $this->translator->trans('cms.redirects.delete.submit'),
                    'variant' => 'danger',
                ]),
            ]),
        ]);
    }
}
