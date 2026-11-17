<?php

declare(strict_types=1);

namespace Cms\Shared\Infrastructure\Publication;

use Cms\Redirect\Application\TargetInput;
use Cms\Redirect\Domain\ResourceType;
use Shared\Infrastructure\Ui\Page\PageComponent;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * The field that says where an address leads: to a page, a post, a section,
 * a tag, an author or an image, or that it is gone, each with the picker of
 * its kind, and whatever other choices the form it sits in offers. It posts
 * the choice as "action", and what each picker picked apart, as
 * "<kind>_id", so it reads the one the choice names.
 *
 * Sections and tags are both terms to a redirect; the field tells them
 * apart only to pick each from its own.
 *
 * Several can share a form under prefixes, such as "incoming[<id>]", each
 * posting its fields under its own.
 *
 * @phpstan-type TargetValues array{action: string, page_id: string, image_id: string, post_id: string, section_id: string, tag_id: string, author_id: string}
 */
final readonly class RedirectTargetField
{
    public const string GONE = 'gone';

    public const string PAGE = 'page';
    public const string IMAGE = 'image';
    public const string POST = 'post';
    public const string SECTION = 'section';
    public const string TAG = 'tag';
    public const string AUTHOR = 'author';

    /**
     * What an address that isn't of an image can lead to, in the order the
     * field offers them.
     */
    public const array OTHER_KINDS = [self::PAGE, self::POST, self::SECTION, self::TAG, self::AUTHOR];

    /**
     * What an address of an image can lead to.
     */
    public const array IMAGE_KINDS = [self::IMAGE];

    /**
     * Every kind a target can be picked as, by the type of resource each is.
     */
    private const array TYPES = [
        self::PAGE => ResourceType::Page,
        self::IMAGE => ResourceType::Image,
        self::POST => ResourceType::Post,
        self::SECTION => ResourceType::Term,
        self::TAG => ResourceType::Term,
        self::AUTHOR => ResourceType::Author,
    ];

    public function __construct(
        private TranslatorInterface $translator,
        private UrlGeneratorInterface $urlGenerator,
    ) {}

    /**
     * @param string                                                                               $prefix   under which its fields post, empty for none
     * @param list<string>                                                                         $kinds    the kinds of resource the address can lead to, as kindsFor() gives them
     * @param TargetValues                                                                         $values   as it was chosen, as fromRequest() reads it
     * @param list<array{value: string, label: string, hint: string, fields: list<PageComponent>}> $leading  choices offered before the targets
     * @param list<array{value: string, label: string, hint: string, fields: list<PageComponent>}> $trailing choices offered after saying it is gone
     *
     * @SuppressWarnings("PHPMD.ExcessiveParameterList")
     */
    public function __invoke(string $prefix, string $legend, array $kinds, array $values, array $leading, array $trailing): PageComponent
    {
        $options = array_map(fn (string $kind): array => [
            'value' => $kind,
            'label' => $this->translator->trans(\sprintf('cms.redirects.form.action.%s.label', $kind)),
            'hint' => $this->translator->trans(\sprintf('cms.redirects.form.action.%s.hint', $kind)),
            'fields' => [$this->picker($prefix, $kind, self::idOf($values, $kind))],
        ], $kinds);

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
     * What an address can lead to, as the kinds the field offers.
     *
     * @return list<string>
     */
    public static function kindsFor(bool $image): array
    {
        return $image ? self::IMAGE_KINDS : self::OTHER_KINDS;
    }

    /**
     * What the request posts under the prefix, empty for what it doesn't.
     *
     * @return TargetValues
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
     * @return TargetValues
     */
    public static function values(array $posted): array
    {
        $value = static fn (string $key): string => \is_string($posted[$key] ?? null) ? $posted[$key] : '';

        return [
            'action' => $value('action'),
            'page_id' => $value('page_id'),
            'image_id' => $value('image_id'),
            'post_id' => $value('post_id'),
            'section_id' => $value('section_id'),
            'tag_id' => $value('tag_id'),
            'author_id' => $value('author_id'),
        ];
    }

    /**
     * The values of a field that leads to the resource picked as the kind,
     * or that says it is gone when no kind is given.
     *
     * @param ?string $kind one of the kinds, null for gone
     *
     * @return TargetValues
     */
    public static function leadingTo(?string $kind, string $id): array
    {
        $values = self::values([]);

        if (null === $kind) {
            return [...$values, 'action' => self::GONE];
        }

        return [...$values, 'action' => $kind, ...(\array_key_exists($kind.'_id', $values) ? [$kind.'_id' => $id] : [])];
    }

    /**
     * Where the values say the address leads.
     *
     * @param TargetValues $values
     */
    public static function target(array $values): TargetInput
    {
        $action = $values['action'];

        if (self::GONE === $action) {
            return new TargetInput(null, null);
        }

        $type = self::TYPES[$action] ?? null;

        // No choice at all is no target either, which the redirect refuses;
        // one of no kind is a type the redirect doesn't know.
        return null === $type
            ? new TargetInput('' === $action ? ResourceType::Page->value : $action, null)
            : new TargetInput($type->value, self::idOf($values, $action));
    }

    /**
     * @param TargetValues $values
     */
    private static function idOf(array $values, string $kind): string
    {
        return match ($kind) {
            self::PAGE => $values['page_id'],
            self::IMAGE => $values['image_id'],
            self::POST => $values['post_id'],
            self::SECTION => $values['section_id'],
            self::TAG => $values['tag_id'],
            self::AUTHOR => $values['author_id'],
            default => '',
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

    /**
     * The picker of the kind, searching what the CMS has of it.
     */
    private function picker(string $prefix, string $kind, string $id): PageComponent
    {
        if (self::IMAGE === $kind) {
            return new PageComponent('Shared:ImageField', [
                'name' => self::name($prefix, 'image_id'),
                'id' => self::id($prefix, 'image'),
                'label' => $this->translator->trans('cms.redirects.form.image.label'),
                'hint' => $this->translator->trans('cms.redirects.form.image.hint'),
                'value' => $id,
                'searchUrl' => $this->urlGenerator->generate('cms_images_search'),
                'lookupUrl' => $this->urlGenerator->generate('cms_images_lookup'),
                'offSite' => $this->translator->trans('cms.redirects.form.off_site'),
            ]);
        }

        [$search, $lookup, $key, $param] = match ($kind) {
            self::POST => [$this->urlGenerator->generate('cms_posts_search'), $this->urlGenerator->generate('cms_posts_lookup'), 'posts', 'title'],
            self::SECTION, self::TAG => [
                $this->urlGenerator->generate('cms_terms_search', ['taxonomy' => $kind.'s']),
                $this->urlGenerator->generate('cms_terms_lookup', ['taxonomy' => $kind.'s']),
                'terms',
                'name',
            ],
            self::AUTHOR => [$this->urlGenerator->generate('cms_authors_search'), $this->urlGenerator->generate('cms_authors_lookup'), 'authors', 'name'],
            default => [$this->urlGenerator->generate('cms_pages_search'), $this->urlGenerator->generate('cms_pages_lookup'), 'pages', 'title'],
        };

        return new PageComponent('Shared:ResourceField', [
            'name' => self::name($prefix, $kind.'_id'),
            'id' => self::id($prefix, $kind),
            'label' => $this->translator->trans(\sprintf('cms.redirects.form.%s.label', $kind)),
            'hint' => $this->translator->trans(\sprintf('cms.redirects.form.%s.hint', $kind)),
            'value' => $id,
            'searchUrl' => $search,
            'lookupUrl' => $lookup,
            'key' => $key,
            'param' => $param,
            'texts' => \sprintf('ui.resource_field.%s', $kind),
            'offSite' => $this->translator->trans('cms.redirects.form.off_site'),
        ]);
    }
}
