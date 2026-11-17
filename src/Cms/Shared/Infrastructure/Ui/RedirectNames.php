<?php

declare(strict_types=1);

namespace Cms\Shared\Infrastructure\Ui;

use Cms\Author\Application\AuthorsFinder;
use Cms\Author\Domain\AuthorRepository;
use Cms\Image\Application\ImagesFinder;
use Cms\Image\Domain\ImageRepository;
use Cms\Page\Application\PagesFinder;
use Cms\Page\Domain\PageRepository;
use Cms\Post\Application\PostsFinder;
use Cms\Post\Domain\PostRepository;
use Cms\Redirect\Application\RedirectResponse;
use Cms\Redirect\Domain\ResourceType;
use Cms\Shared\Infrastructure\Publication\RedirectTargetField;
use Cms\Taxonomy\Application\TermsFinder;
use Cms\Taxonomy\Domain\TermRepository;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Names where redirects lead and the resources that were at their
 * addresses, as an editor reads them, looking each kind up once for them
 * all, and tells the kind each target is picked as, a section or a tag
 * apart.
 *
 * It reaches every module a redirect can lead to, so its coupling grows with
 * the kinds of resource and says nothing about its design.
 *
 * @SuppressWarnings("PHPMD.CouplingBetweenObjects")
 */
final readonly class RedirectNames
{
    public function __construct(
        private PageRepository $pageRepository,
        private ImageRepository $imageRepository,
        private PostRepository $postRepository,
        private TermRepository $termRepository,
        private AuthorRepository $authorRepository,
        private TranslatorInterface $translator,
    ) {}

    /**
     * @param list<RedirectResponse> $redirects
     *
     * @return array<string, array{target: string, origin: ?string, kind: ?string}> keyed by the id of each redirect; origin null for none, or one deleted; kind, one of the kinds of RedirectTargetField, null when it is gone or its target deleted
     */
    public function __invoke(array $redirects): array
    {
        $names = $this->resourceNames($redirects);
        $named = [];

        foreach ($redirects as $redirect) {
            $target = null === $redirect->targetType || null === $redirect->targetId ? null : $names[$redirect->targetType.':'.$redirect->targetId] ?? null;
            $origin = null === $redirect->originType || null === $redirect->originId ? null : $names[$redirect->originType.':'.$redirect->originId] ?? null;

            $named[$redirect->id] = [
                'target' => null === $redirect->targetType
                    ? $this->translator->trans('cms.redirects.target.gone')
                    : $this->name($target),
                // A resource deleted since has nothing linking to it, so it goes unnamed.
                'origin' => null === $origin ? null : $this->name($origin),
                'kind' => $target['kind'] ?? null,
            ];
        }

        return $named;
    }

    /**
     * What the redirects name, each by its name and the kind it is picked
     * as, keyed by "type:id": the titles of the pages and the posts, the
     * alternative texts of the images, and the names of the sections, the
     * tags and the authors.
     *
     * @param list<RedirectResponse> $redirects
     *
     * @return array<string, array{name: string, kind: string}>
     */
    private function resourceNames(array $redirects): array
    {
        $ids = self::idsByType($redirects);
        $names = [];
        $named = static function (ResourceType $type, string $id, string $name, string $kind) use (&$names): void {
            $names[$type->value.':'.$id] = ['name' => $name, 'kind' => $kind];
        };

        foreach (new PagesFinder($ids[ResourceType::Page->value])($this->pageRepository) as $page) {
            $named(ResourceType::Page, $page->id, $page->title, RedirectTargetField::PAGE);
        }

        foreach (new ImagesFinder($ids[ResourceType::Image->value])($this->imageRepository) as $image) {
            $named(ResourceType::Image, $image->id, $image->alt, RedirectTargetField::IMAGE);
        }

        foreach (new PostsFinder($ids[ResourceType::Post->value])($this->postRepository) as $post) {
            $named(ResourceType::Post, $post->id, $post->title, RedirectTargetField::POST);
        }

        // A term is named by its taxonomy, whose values are the kinds of sections and tags.
        foreach (new TermsFinder($ids[ResourceType::Term->value])($this->termRepository) as $term) {
            $named(ResourceType::Term, $term->id, $term->name, $term->taxonomy);
        }

        foreach (new AuthorsFinder($ids[ResourceType::Author->value])($this->authorRepository) as $author) {
            $named(ResourceType::Author, $author->id, $author->name, RedirectTargetField::AUTHOR);
        }

        return $names;
    }

    /**
     * The ids of what the redirects lead to and of what was at their
     * addresses, by the value of each type of resource; every type is there,
     * with no id when none names one.
     *
     * @param list<RedirectResponse> $redirects
     *
     * @return array<string, list<string>>
     */
    private static function idsByType(array $redirects): array
    {
        $ids = array_fill_keys(array_map(static fn (ResourceType $type): string => $type->value, ResourceType::cases()), []);

        foreach ($redirects as $redirect) {
            foreach ([[$redirect->targetType, $redirect->targetId], [$redirect->originType, $redirect->originId]] as [$type, $id]) {
                if (null !== $type && null !== $id && \array_key_exists($type, $ids)) {
                    $ids[$type][] = $id;
                }
            }
        }

        return $ids;
    }

    /**
     * @param ?array{name: string, kind: string} $resource null when it was deleted
     */
    private function name(?array $resource): string
    {
        return null === $resource
            ? $this->translator->trans('cms.redirects.target.deleted')
            : $this->translator->trans(\sprintf('cms.redirects.target.%s', $resource['kind']), ['%name%' => $resource['name']]);
    }
}
