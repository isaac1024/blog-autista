<?php

declare(strict_types=1);

namespace Cms\External\Infrastructure\Controller;

use Cms\External\Application\ExternalResourceResponse;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * An external resource as a field that picks one shows it: its title, what
 * it is, its address, so two resources of the same title are told apart,
 * and where it stands for the site, which only paints it while it is
 * published and available.
 */
final readonly class PickedExternalResource
{
    public function __construct(
        private ExternalResourceState $resourceState,
        private TranslatorInterface $translator,
    ) {}

    /**
     * @return array{id: string, title: string, status: string, published: bool}
     */
    public function __invoke(ExternalResourceResponse $resource): array
    {
        return [
            'id' => $resource->id,
            'title' => $this->translator->trans('cms.externals.picked', [
                '%title%' => $resource->title,
                '%kind%' => $this->resourceState->kind($resource),
                '%address%' => self::address($resource->url),
            ]),
            'status' => $this->resourceState->label($resource),
            'published' => $resource->isPublished() && $resource->available,
        ];
    }

    /**
     * The address as it reads, without its scheme nor "www.", such as
     * youtube.com/shorts/jNQXAC9IVRw.
     */
    private static function address(string $url): string
    {
        return (string) preg_replace('#^https?://(?:www\.)?#i', '', $url);
    }
}
