<?php

declare(strict_types=1);

namespace Cms\Redirect\Infrastructure\Controller;

use Cms\Redirect\Application\PublicationOutlook;
use Cms\Redirect\Application\TargetInput;
use Cms\Redirect\Domain\InvalidRedirect;
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
 * It posts "confirmed", so publishing knows it was asked, and the choice for
 * the address it leaves under "previous".
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
                new PageComponent('Shared:FormInput', ['name' => 'confirmed', 'type' => 'hidden', 'value' => '1']),
                new PageComponent('Shared:ActionBar', [], [
                    new PageComponent('Shared:FormButton', ['label' => $submit]),
                ]),
            ]),
        ];
    }

    /**
     * Whether the request comes from this form, so publishing was asked
     * about first.
     */
    public static function confirmed(Request $request): bool
    {
        return $request->request->getBoolean('confirmed');
    }

    /**
     * Where the request says the address the page leaves leads, or null to
     * leave it as it is.
     *
     * @throws InvalidRedirect when it was asked and nothing is chosen
     */
    public static function previous(Request $request, Resource $resource): ?TargetInput
    {
        $values = RedirectTargetField::fromRequest($request, 'previous');

        return match ($values['action']) {
            self::KEEP => null,
            self::ITSELF => new TargetInput($resource->type()->value, $resource->id()),
            '' => self::confirmed($request) ? throw InvalidRedirect::undecidedPrevious() : null,
            default => RedirectTargetField::target($values),
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
