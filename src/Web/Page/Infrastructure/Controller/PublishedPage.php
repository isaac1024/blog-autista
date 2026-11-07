<?php

declare(strict_types=1);

namespace Web\Page\Infrastructure\Controller;

use Shared\Infrastructure\Ui\Page\Page;
use Shared\Infrastructure\Ui\RichTextComponents;
use Shared\Infrastructure\Ui\WebLayout;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Web\Page\Application\PageResponse;

/**
 * Composes a page of the site from Twig components: its title as the main
 * heading, its content below, and how it presents itself to search engines,
 * with its own address as its canonical URL.
 */
final readonly class PublishedPage
{
    public function __construct(
        private UrlGeneratorInterface $urlGenerator,
        private RichTextComponents $richTextComponents,
    ) {}

    public function __invoke(PageResponse $page): Page
    {
        $canonical = '' === $page->slug
            ? $this->urlGenerator->generate('web_home', referenceType: UrlGeneratorInterface::ABSOLUTE_URL)
            : $this->urlGenerator->generate('web_page', ['slug' => $page->slug], UrlGeneratorInterface::ABSOLUTE_URL);

        return WebLayout::page($page->title)
            ->withSearchEngineMetadata($page->metaDescription, $page->robots, $canonical)
            ->withMain('Shared:PageTitle', ['title' => $page->title])
            ->withMain('Shared:Prose', [], ($this->richTextComponents)($page->content));
    }
}
