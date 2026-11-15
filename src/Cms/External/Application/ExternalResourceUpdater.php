<?php

declare(strict_types=1);

namespace Cms\External\Application;

use Cms\External\Domain\ExternalResourceId;
use Cms\External\Domain\ExternalResourceRepository;
use Cms\External\Domain\InvalidResourceUrl;
use Cms\External\Domain\ResourceImageStorage;
use Shared\Domain\Event\EventBus;

/**
 * Saves everything edited on an existing external resource: its address,
 * title, summary, language and details. A published resource stays on the
 * site as it was until it is published again.
 */
final readonly class ExternalResourceUpdater
{
    /**
     * @param array<array-key, mixed> $details as ResourceDetails reads them
     */
    public function __construct(
        private string $id,
        private string $url,
        private string $title,
        private string $summary,
        private string $language,
        private array $details,
    ) {}

    public function __invoke(ExternalResourceRepository $resourceRepository, EventBus $eventBus, ResourceImageStorage $imageStorage): void
    {
        $resource = $resourceRepository->find(new ExternalResourceId($this->id)) ?? throw ExternalResourceNotFound::withId($this->id);

        $resource->edit($this->url, $this->title, $this->summary, $this->language, $this->details);
        $holder = $resourceRepository->findByUrl($resource->version()->url());

        if (null !== $holder && !$holder->id()->equals($resource->id())) {
            throw InvalidResourceUrl::taken($holder->id()->value());
        }

        $resource->save($resourceRepository, $eventBus, $imageStorage);
    }
}
