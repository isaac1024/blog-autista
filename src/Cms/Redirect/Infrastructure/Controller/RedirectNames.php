<?php

declare(strict_types=1);

namespace Cms\Redirect\Infrastructure\Controller;

use Cms\Image\Application\ImagesFinder;
use Cms\Image\Domain\ImageRepository;
use Cms\Page\Application\PagesFinder;
use Cms\Page\Domain\PageRepository;
use Cms\Redirect\Application\RedirectResponse;
use Cms\Redirect\Domain\ResourceType;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Names where redirects lead and the resources that were at their
 * addresses, as an editor reads them, looking each kind up once for them
 * all.
 */
final readonly class RedirectNames
{
    public function __construct(
        private PageRepository $pageRepository,
        private ImageRepository $imageRepository,
        private TranslatorInterface $translator,
    ) {}

    /**
     * @param list<RedirectResponse> $redirects
     *
     * @return array<string, array{target: string, origin: ?string}> keyed by the id of each redirect
     */
    public function __invoke(array $redirects): array
    {
        $names = $this->resourceNames($redirects);
        $named = [];

        foreach ($redirects as $redirect) {
            $named[$redirect->id] = [
                'target' => null === $redirect->targetType || null === $redirect->targetId
                    ? $this->translator->trans('cms.redirects.target.gone')
                    : $this->name($names, $redirect->targetType, $redirect->targetId),
                'origin' => null === $redirect->originType || null === $redirect->originId
                    ? null
                    : $this->name($names, $redirect->originType, $redirect->originId),
            ];
        }

        return $named;
    }

    /**
     * The titles of the pages and the alternative texts of the images the
     * redirects name, keyed by "type:id".
     *
     * @param list<RedirectResponse> $redirects
     *
     * @return array<string, string>
     */
    private function resourceNames(array $redirects): array
    {
        $ids = [ResourceType::Page->value => [], ResourceType::Image->value => []];

        foreach ($redirects as $redirect) {
            foreach ([[$redirect->targetType, $redirect->targetId], [$redirect->originType, $redirect->originId]] as [$type, $id]) {
                if (null !== $type && null !== $id && \array_key_exists($type, $ids)) {
                    $ids[$type][] = $id;
                }
            }
        }

        $names = [];

        foreach (new PagesFinder($ids[ResourceType::Page->value])($this->pageRepository) as $page) {
            $names[ResourceType::Page->value.':'.$page->id] = $page->title;
        }

        foreach (new ImagesFinder($ids[ResourceType::Image->value])($this->imageRepository) as $image) {
            $names[ResourceType::Image->value.':'.$image->id] = $image->alt;
        }

        return $names;
    }

    /**
     * @param array<string, string> $names
     */
    private function name(array $names, string $type, string $id): string
    {
        $name = $names[$type.':'.$id] ?? null;

        return null === $name
            ? $this->translator->trans('cms.redirects.target.deleted')
            : $this->translator->trans(\sprintf('cms.redirects.target.%s', $type), ['%name%' => $name]);
    }
}
