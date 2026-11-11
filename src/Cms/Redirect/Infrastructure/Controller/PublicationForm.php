<?php

declare(strict_types=1);

namespace Cms\Redirect\Infrastructure\Controller;

use Cms\Redirect\Application\PublicationOutlook;
use Cms\Redirect\Domain\ResourceType;
use Shared\Infrastructure\Ui\Page\PageComponent;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * The form that confirms publishing a page when it changes the redirects:
 * it tells of the redirect at the address it takes, which goes, and asks
 * what the address it leaves does, leading to the page itself unless the
 * editor says otherwise.
 *
 * It posts the fingerprint of what it showed, so publishing knows it still
 * does that. What it posts is read by PublicationRequest.
 */
final readonly class PublicationForm
{
    public function __construct(
        private TranslatorInterface $translator,
        private RedirectTargetField $redirectTargetField,
        private ReplacedRedirectNotice $replacedRedirectNotice,
        private RedirectNames $redirectNames,
    ) {}

    /**
     * @param string                  $csrfTokenId the name of the route it posts to
     * @param array<array-key, mixed> $posted      what was sent the last time, when it was refused, to choose it again
     *
     * @return list<PageComponent>
     */
    public function __invoke(PublicationOutlook $outlook, string $action, string $csrfTokenId, string $submit, array $posted): array
    {
        $notice = ($this->replacedRedirectNotice)($outlook);

        return [
            new PageComponent('Shared:Form', [
                'action' => $action,
                'method' => 'post',
                'csrfTokenId' => $csrfTokenId,
            ], [
                ...(null === $notice ? [] : [$notice]),
                ...(null === $outlook->previous ? [] : [$this->previousPanel($outlook, \is_array($posted['previous'] ?? null) ? $posted['previous'] : [])]),
                // What was shown, so publishing knows it still does that.
                new PageComponent('Shared:FormInput', ['name' => 'outlook', 'type' => 'hidden', 'value' => $outlook->fingerprint]),
                new PageComponent('Shared:ActionBar', [], [
                    new PageComponent('Shared:FormButton', ['label' => $submit]),
                ]),
            ]),
        ];
    }

    /**
     * @param array<array-key, mixed> $posted
     */
    private function previousPanel(PublicationOutlook $outlook, array $posted): PageComponent
    {
        $values = RedirectTargetField::values($posted);
        $existing = $outlook->previousRedirect;

        return new PageComponent('Shared:Panel', [
            'title' => $this->translator->trans('cms.redirects.publish.previous.title'),
            'description' => $this->translator->trans('cms.redirects.publish.previous.description', [
                '%previous%' => $outlook->previous,
                '%next%' => $outlook->next,
            ]),
        ], [
            ($this->redirectTargetField)(
                'previous',
                $this->translator->trans('cms.redirects.publish.previous.legend', ['%previous%' => $outlook->previous]),
                [ResourceType::Page],
                // Leading to the page itself, unless the editor chose otherwise.
                [...$values, 'action' => '' === $values['action'] ? PublicationRequest::ITSELF : $values['action']],
                [[
                    'value' => PublicationRequest::ITSELF,
                    'label' => $this->translator->trans('cms.redirects.publish.previous.itself.label'),
                    'hint' => $this->translator->trans('cms.redirects.publish.previous.itself.hint'),
                    'fields' => [],
                ]],
                [[
                    'value' => PublicationRequest::KEEP,
                    'label' => $this->translator->trans(null === $existing ? 'cms.redirects.publish.previous.keep_none.label' : 'cms.redirects.publish.previous.keep.label'),
                    'hint' => null === $existing
                        ? $this->translator->trans('cms.redirects.publish.previous.keep_none.hint')
                        : $this->translator->trans('cms.redirects.publish.previous.keep.hint', ['%target%' => ($this->redirectNames)([$existing])[$existing->id]['target']]),
                    'fields' => [],
                ]],
            ),
        ]);
    }
}
