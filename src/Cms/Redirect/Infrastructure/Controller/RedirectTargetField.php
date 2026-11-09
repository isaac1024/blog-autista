<?php

declare(strict_types=1);

namespace Cms\Redirect\Infrastructure\Controller;

use Cms\Redirect\Application\TargetInput;
use Cms\Redirect\Domain\ResourceType;
use Shared\Infrastructure\Ui\Page\PageComponent;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * The field that says where an address leads: to a page, to an image, or
 * that it is gone, each with the picker of its kind, and whatever other
 * choices the form it sits in offers. It posts the choice as "action", and
 * the page or the image picked apart, so it reads the one the choice names.
 *
 * Several can share a form under prefixes, such as "incoming[<id>]", each
 * posting its fields under its own.
 */
final readonly class RedirectTargetField
{
    public const string GONE = 'gone';

    public function __construct(
        private TranslatorInterface $translator,
        private UrlGeneratorInterface $urlGenerator,
    ) {}

    /**
     * @param string                                                                               $prefix   under which its fields post, empty for none
     * @param list<ResourceType>                                                                   $types    the kinds of resource the address can lead to
     * @param array{action: string, page_id: string, image_id: string}                             $values   as it was chosen, as fromRequest() reads it
     * @param list<array{value: string, label: string, hint: string, fields: list<PageComponent>}> $leading  choices offered before the targets
     * @param list<array{value: string, label: string, hint: string, fields: list<PageComponent>}> $trailing choices offered after saying it is gone
     *
     * @SuppressWarnings("PHPMD.ExcessiveParameterList")
     */
    public function __invoke(string $prefix, string $legend, array $types, array $values, array $leading, array $trailing): PageComponent
    {
        $options = array_map(fn (ResourceType $type): array => [
            'value' => $type->value,
            'label' => $this->translator->trans(\sprintf('cms.redirects.form.action.%s.label', $type->value)),
            'hint' => $this->translator->trans(\sprintf('cms.redirects.form.action.%s.hint', $type->value)),
            'fields' => [ResourceType::Page === $type ? $this->pageField($prefix, $values['page_id']) : $this->imageField($prefix, $values['image_id'])],
        ], $types);

        return new PageComponent('Shared:FormChoice', [
            'name' => self::name($prefix, 'action'),
            'id' => self::id($prefix, 'action'),
            'legend' => $legend,
            'value' => $values['action'],
            'options' => [...$leading, ...$options, [
                'value' => self::GONE,
                'label' => $this->translator->trans('cms.redirects.form.action.gone.label'),
                'hint' => $this->translator->trans('cms.redirects.form.action.gone.hint'),
                'fields' => [],
            ], ...$trailing],
        ]);
    }

    /**
     * What the request posts under the prefix, empty for what it doesn't.
     *
     * @return array{action: string, page_id: string, image_id: string}
     */
    public static function fromRequest(Request $request, string $prefix): array
    {
        return self::values('' === $prefix ? $request->request->all() : $request->request->all($prefix));
    }

    /**
     * The values of the field from what was posted for it.
     *
     * @param array<array-key, mixed> $posted
     *
     * @return array{action: string, page_id: string, image_id: string}
     */
    public static function values(array $posted): array
    {
        $value = static fn (string $key): string => \is_string($posted[$key] ?? null) ? $posted[$key] : '';

        return ['action' => $value('action'), 'page_id' => $value('page_id'), 'image_id' => $value('image_id')];
    }

    /**
     * Where the values say the address leads.
     *
     * @param array{action: string, page_id: string, image_id: string} $values
     */
    public static function target(array $values): TargetInput
    {
        return match ($values['action']) {
            self::GONE => new TargetInput(null, null),
            ResourceType::Page->value => new TargetInput(ResourceType::Page->value, $values['page_id']),
            ResourceType::Image->value => new TargetInput(ResourceType::Image->value, $values['image_id']),
            // No choice at all is no target either, which the redirect refuses.
            default => new TargetInput('' === $values['action'] ? ResourceType::Page->value : $values['action'], null),
        };
    }

    private static function name(string $prefix, string $field): string
    {
        return '' === $prefix ? $field : \sprintf('%s[%s]', $prefix, $field);
    }

    private static function id(string $prefix, string $field): string
    {
        $base = '' === $prefix ? 'redirect' : 'redirect-'.trim((string) preg_replace('/[^a-z0-9]+/', '-', strtolower($prefix)), '-');

        return \sprintf('%s-%s', $base, $field);
    }

    private function pageField(string $prefix, string $pageId): PageComponent
    {
        return new PageComponent('Shared:PageField', [
            'name' => self::name($prefix, 'page_id'),
            'id' => self::id($prefix, 'page'),
            'label' => $this->translator->trans('cms.redirects.form.page.label'),
            'hint' => $this->translator->trans('cms.redirects.form.page.hint'),
            'value' => $pageId,
            'searchUrl' => $this->urlGenerator->generate('cms_pages_search'),
            'lookupUrl' => $this->urlGenerator->generate('cms_pages_lookup'),
            'offSite' => $this->translator->trans('cms.redirects.form.off_site'),
        ]);
    }

    private function imageField(string $prefix, string $imageId): PageComponent
    {
        return new PageComponent('Shared:ImageField', [
            'name' => self::name($prefix, 'image_id'),
            'id' => self::id($prefix, 'image'),
            'label' => $this->translator->trans('cms.redirects.form.image.label'),
            'hint' => $this->translator->trans('cms.redirects.form.image.hint'),
            'value' => $imageId,
            'searchUrl' => $this->urlGenerator->generate('cms_images_search'),
            'lookupUrl' => $this->urlGenerator->generate('cms_images_lookup'),
            'offSite' => $this->translator->trans('cms.redirects.form.off_site'),
        ]);
    }
}
