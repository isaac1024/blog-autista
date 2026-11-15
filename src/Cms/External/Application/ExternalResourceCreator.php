<?php

declare(strict_types=1);

namespace Cms\External\Application;

use Cms\External\Domain\ExternalResource;
use Cms\External\Domain\ExternalResourceRepository;
use Cms\External\Domain\InvalidResourceUrl;
use Cms\External\Domain\ResourceImageFetcher;
use Cms\External\Domain\ResourceImageStorage;
use Cms\External\Domain\ResourceReader;
use Cms\External\Domain\ResourceUrl;
use Shared\Domain\Event\EventBus;

/**
 * Creates an external resource from its address, reading what it is and
 * what it tells of itself there, unless another resource has that address.
 */
final readonly class ExternalResourceCreator
{
    /**
     * @param string $ownHost the host of the blog, whose addresses are linked as the contents they are
     */
    public function __construct(
        private string $id,
        private string $url,
        private string $ownHost,
    ) {}

    /**
     * Whether what it is and tells could be read, as the reading said.
     */
    public function __invoke(ExternalResourceRepository $resourceRepository, EventBus $eventBus, ResourceReader $reader, ResourceImageFetcher $imageFetcher, ResourceImageStorage $imageStorage): bool
    {
        $url = new ResourceUrl($this->url);

        if ((string) preg_replace('/^www\./', '', strtolower($this->ownHost)) === $url->address()->host()) {
            throw InvalidResourceUrl::ownSite();
        }

        $holder = $resourceRepository->findByUrl($url);

        if (null !== $holder) {
            throw InvalidResourceUrl::taken($holder->id()->value());
        }

        $reading = $reader->read($url);
        $resource = ExternalResource::create($this->id, $url, $reading, $imageFetcher, $imageStorage);
        $resource->save($resourceRepository, $eventBus, $imageStorage);

        return $reading->reached;
    }
}
