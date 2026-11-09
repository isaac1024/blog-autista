<?php

declare(strict_types=1);

namespace Cms\Redirect\Infrastructure\Controller;

use Cms\Redirect\Application\PreviousAddressDecision;
use Cms\Redirect\Application\PublicationOutlook;
use Cms\Redirect\Application\TargetInput;
use Cms\Redirect\Domain\Resource;
use Cms\Redirect\Domain\ResourceType;
use Shared\Infrastructure\Ui\Page\PageComponent;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * The form that confirms publishing a page when it changes the redirects:
 * it tells of the redirect at the address it takes, which goes, and asks
 * what the address it leaves does, leading to the page itself unless the
 * editor says otherwise.
 *
 * It posts the fingerprint of what it showed as "outlook", so publishing
 * knows it still does that, and the choice for the address it leaves under
 * "previous".
 */
final readonly class PublicationForm
{
    private const string ITSELF = 'itself';

    private const string KEEP = 'keep';

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
                new PageComponent('Shared:FormInput', ['name' => 'outlook', 'type' => 'hidden', 'value' => $outlook->fingerprint()]),
                new PageComponent('Shared:ActionBar', [], [
                    new PageComponent('Shared:FormButton', ['label' => $submit]),
                ]),
            ]),
        ];
    }

    /**
     * The fingerprint of what the form showed, or null when the request
     * doesn't come from it, so the editor was shown nothing to confirm.
     */
    public static function outlook(Request $request): ?string
    {
        return $request->request->has('outlook') ? $request->request->getString('outlook') : null;
    }

    /**
     * What the request says the address the page leaves does; undecided when
     * it says nothing, which publishing only accepts while there is none.
     */
    public static function previous(Request $request, Resource $resource): PreviousAddressDecision
    {
        $values = RedirectTargetField::fromRequest($request, 'previous');

        return match ($values['action']) {
            '' => PreviousAddressDecision::undecided(),
            self::KEEP => PreviousAddressDecision::keep(),
            self::ITSELF => PreviousAddressDecision::leadTo(new TargetInput($resource->type()->value, $resource->id())),
            default => PreviousAddressDecision::leadTo(RedirectTargetField::target($values)),
        };
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
                '%previous%' => (string) $outlook->previous,
                '%next%' => (string) $outlook->next,
            ]),
        ], [
            ($this->redirectTargetField)(
                'previous',
                $this->translator->trans('cms.redirects.publish.previous.legend', ['%previous%' => (string) $outlook->previous]),
                [ResourceType::Page],
                // Leading to the page itself, unless the editor chose otherwise.
                [...$values, 'action' => '' === $values['action'] ? self::ITSELF : $values['action']],
                [[
                    'value' => self::ITSELF,
                    'label' => $this->translator->trans('cms.redirects.publish.previous.itself.label'),
                    'hint' => $this->translator->trans('cms.redirects.publish.previous.itself.hint'),
                    'fields' => [],
                ]],
                [[
                    'value' => self::KEEP,
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
