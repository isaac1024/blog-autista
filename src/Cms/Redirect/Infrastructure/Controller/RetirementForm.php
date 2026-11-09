<?php

declare(strict_types=1);

namespace Cms\Redirect\Infrastructure\Controller;

use Cms\Redirect\Application\IncomingDecision;
use Cms\Redirect\Application\RedirectResponse;
use Cms\Redirect\Application\TargetInput;
use Cms\Redirect\Domain\InvalidRedirect;
use Cms\Redirect\Domain\RedirectRepository;
use Cms\Redirect\Domain\Resource;
use Cms\Redirect\Domain\ResourceType;
use Cms\Redirect\Domain\SiteResources;
use Shared\Infrastructure\Ui\Page\PageComponent;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * The form that takes a page or an image off the site, saying what becomes
 * of its addresses: where its own leads once it is off, if anywhere, and
 * what each redirect that leads to it does instead, since none may lead to a
 * resource off the site. Nothing is chosen for the editor: each choice is
 * made, and confirmed by sending the form.
 *
 * It posts the choice for its own address under "address", and that for
 * each redirect under "incoming[<id>]".
 */
final readonly class RetirementForm
{
    /**
     * The choice that leaves its address with nothing, so it isn't found.
     */
    private const string NOT_FOUND = 'none';

    /**
     * The choice that leads a redirect where the address of the resource will.
     */
    private const string SAME = 'same';

    private const string DELETE = 'delete';

    public function __construct(
        private TranslatorInterface $translator,
        private RedirectTargetField $redirectTargetField,
        private RedirectRepository $redirectRepository,
        private SiteResources $siteResources,
        private RedirectNames $redirectNames,
    ) {}

    /**
     * @param string                  $action      where the form posts
     * @param string                  $csrfTokenId the name of the route it posts to
     * @param array<array-key, mixed> $posted      what was sent the last time, when it was refused, to choose it again
     *
     * @return list<PageComponent>
     *
     * PHPMD measures it over the limits only when it analyses the whole
     * source tree; on its own, it is within them.
     *
     * @SuppressWarnings("PHPMD.NPathComplexity")
     */
    public function __invoke(Resource $resource, string $action, string $csrfTokenId, string $submit, array $posted): array
    {
        $incoming = array_map(RedirectResponse::fromRedirect(...), $this->redirectRepository->findTargeting($resource));

        return [
            new PageComponent('Shared:Form', [
                'action' => $action,
                'method' => 'post',
                'csrfTokenId' => $csrfTokenId,
            ], [
                $this->addressPanel($resource, self::part($posted, 'address')),
                ...([] === $incoming ? [] : [$this->incomingPanel($incoming, self::part($posted, 'incoming'))]),
                new PageComponent('Shared:ActionBar', [], [
                    new PageComponent('Shared:FormButton', [
                        'label' => $submit,
                        'variant' => 'danger',
                    ]),
                ]),
            ]),
        ];
    }

    /**
     * Where the request says the address of the resource leads once it is
     * off the site, or null to leave it not found.
     *
     * @throws InvalidRedirect when nothing is chosen for it
     */
    public static function address(Request $request): ?TargetInput
    {
        $values = RedirectTargetField::fromRequest($request, 'address');

        if (self::NOT_FOUND === $values['action']) {
            return null;
        }

        if ('' === $values['action']) {
            throw InvalidRedirect::undecidedAddress();
        }

        return RedirectTargetField::target($values);
    }

    /**
     * What the request says of each redirect that leads to the resource, by
     * its id; one with nothing chosen is left out.
     *
     * @return array<string, IncomingDecision>
     */
    public static function incoming(Request $request): array
    {
        $decisions = [];
        $posted = $request->request->all('incoming');

        foreach ($posted as $id => $fields) {
            $values = RedirectTargetField::values(\is_array($fields) ? $fields : []);

            $decision = match ($values['action']) {
                '' => null,
                self::SAME => IncomingDecision::sameAsAddress(),
                self::DELETE => IncomingDecision::delete(),
                default => IncomingDecision::leadingTo(RedirectTargetField::target($values)),
            };

            if (null !== $decision) {
                $decisions[(string) $id] = $decision;
            }
        }

        return $decisions;
    }

    /**
     * What its own address does once it is off the site, if it has one.
     *
     * @param array<array-key, mixed> $posted
     */
    private function addressPanel(Resource $resource, array $posted): PageComponent
    {
        $address = $this->siteResources->addressOf($resource);

        if (null === $address) {
            return new PageComponent('Shared:Panel', [
                'title' => $this->translator->trans('cms.redirects.retire.address.title'),
                'description' => $this->translator->trans('cms.redirects.retire.address.none'),
            ]);
        }

        return new PageComponent('Shared:Panel', [
            'title' => $this->translator->trans('cms.redirects.retire.address.title'),
            'description' => $this->translator->trans('cms.redirects.retire.address.description', ['%address%' => $address->shown()]),
        ], [
            ($this->redirectTargetField)(
                'address',
                $this->translator->trans('cms.redirects.retire.address.legend'),
                [$resource->type()->isImage() ? ResourceType::Image : ResourceType::Page],
                RedirectTargetField::values($posted),
                [],
                [$this->option(self::NOT_FOUND, 'cms.redirects.retire.address.not_found')],
            ),
        ]);
    }

    /**
     * What each redirect that leads to it does instead, telling of each one
     * what else follows it: what links to the resource that was there.
     *
     * @param non-empty-list<RedirectResponse> $incoming
     * @param array<array-key, mixed>          $posted   by the id of each redirect
     */
    private function incomingPanel(array $incoming, array $posted): PageComponent
    {
        $names = ($this->redirectNames)($incoming);

        return new PageComponent('Shared:Panel', [
            'title' => $this->translator->trans('cms.redirects.retire.incoming.title'),
            'description' => $this->translator->trans('cms.redirects.retire.incoming.description', ['%count%' => \count($incoming)]),
        ], array_map(fn (RedirectResponse $redirect): PageComponent => ($this->redirectTargetField)(
            \sprintf('incoming[%s]', $redirect->id),
            null === $names[$redirect->id]['origin']
                ? $this->translator->trans('cms.redirects.retire.incoming.legend', ['%source%' => $redirect->source])
                : $this->translator->trans('cms.redirects.retire.incoming.legend_origin', ['%source%' => $redirect->source, '%origin%' => $names[$redirect->id]['origin']]),
            $redirect->image ? [ResourceType::Image] : [ResourceType::Page],
            RedirectTargetField::values(self::part($posted, $redirect->id)),
            [$this->option(self::SAME, 'cms.redirects.retire.incoming.same')],
            [$this->option(self::DELETE, 'cms.redirects.retire.incoming.delete')],
        ), $incoming));
    }

    /**
     * What was sent under a key, or nothing.
     *
     * @param array<array-key, mixed> $posted
     *
     * @return array<array-key, mixed>
     */
    private static function part(array $posted, string $key): array
    {
        return \is_array($posted[$key] ?? null) ? $posted[$key] : [];
    }

    /**
     * @return array{value: string, label: string, hint: string, fields: list<PageComponent>}
     */
    private function option(string $value, string $texts): array
    {
        return [
            'value' => $value,
            'label' => $this->translator->trans($texts.'.label'),
            'hint' => $this->translator->trans($texts.'.hint'),
            'fields' => [],
        ];
    }
}
