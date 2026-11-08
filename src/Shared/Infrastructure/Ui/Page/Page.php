<?php

declare(strict_types=1);

namespace Shared\Infrastructure\Ui\Page;

/**
 * Immutable description of a page.
 *
 * Controllers compose the regions with Twig components and pick the layout
 * that paints them, so every context can provide its own chrome.
 *
 * Its private constructor takes all of its state, one parameter each, for
 * the methods that copy it with one part changed.
 *
 * @SuppressWarnings("PHPMD.ExcessiveParameterList")
 */
final readonly class Page
{
    public const string DEFAULT_LAYOUT = 'page.html.twig';

    private const string DEFAULT_MAIN_CLASS = 'mx-auto w-[min(100%-2rem,72rem)] py-[clamp(2rem,6vw,4rem)]';

    /**
     * @param list<PageComponent>                                       $header
     * @param list<PageComponent>                                       $main
     * @param list<PageComponent>                                       $footer
     * @param ?array{src: string, width: int, height: int, alt: string} $image  the image it is shared with, null for none
     */
    private function __construct(
        public string $title,
        public array $header,
        public array $main,
        public array $footer,
        public string $mainClass,
        public string $layout,
        public ?string $description,
        public ?string $robots,
        public ?string $canonical,
        public ?array $image,
    ) {}

    public static function create(string $title): self
    {
        return new self($title, [], [], [], self::DEFAULT_MAIN_CLASS, self::DEFAULT_LAYOUT, null, null, null, null);
    }

    /**
     * @param array<string, mixed> $props
     * @param list<PageComponent>  $children
     */
    public function withHeader(string $component, array $props = [], array $children = []): self
    {
        return $this->copy(header: [...$this->header, new PageComponent($component, $props, $children)]);
    }

    /**
     * @param array<string, mixed> $props
     * @param list<PageComponent>  $children
     */
    public function withMain(string $component, array $props = [], array $children = []): self
    {
        return $this->copy(main: [...$this->main, new PageComponent($component, $props, $children)]);
    }

    /**
     * @param array<string, mixed> $props
     * @param list<PageComponent>  $children
     */
    public function withFooter(string $component, array $props = [], array $children = []): self
    {
        return $this->copy(footer: [...$this->footer, new PageComponent($component, $props, $children)]);
    }

    public function withMainClass(string $mainClass): self
    {
        return $this->copy(mainClass: $mainClass);
    }

    public function withLayout(string $layout): self
    {
        return $this->copy(layout: $layout);
    }

    /**
     * How the page presents itself to search engines: its summary in search
     * results, whether they may list it and follow its links, and the address
     * they should know it by.
     */
    public function withSearchEngineMetadata(?string $description, string $robots, string $canonical): self
    {
        return new self(
            $this->title,
            $this->header,
            $this->main,
            $this->footer,
            $this->mainClass,
            $this->layout,
            $description,
            $robots,
            $canonical,
            $this->image,
        );
    }

    /**
     * The image search engines and readers show when the page is shared or
     * listed, given by its address on the site.
     */
    public function withShareImage(string $src, int $width, int $height, string $alt): self
    {
        return new self(
            $this->title,
            $this->header,
            $this->main,
            $this->footer,
            $this->mainClass,
            $this->layout,
            $this->description,
            $this->robots,
            $this->canonical,
            ['src' => $src, 'width' => $width, 'height' => $height, 'alt' => $alt],
        );
    }

    /**
     * @param null|list<PageComponent> $header
     * @param null|list<PageComponent> $main
     * @param null|list<PageComponent> $footer
     */
    private function copy(
        ?array $header = null,
        ?array $main = null,
        ?array $footer = null,
        ?string $mainClass = null,
        ?string $layout = null,
    ): self {
        return new self(
            $this->title,
            $header ?? $this->header,
            $main ?? $this->main,
            $footer ?? $this->footer,
            $mainClass ?? $this->mainClass,
            $layout ?? $this->layout,
            $this->description,
            $this->robots,
            $this->canonical,
            $this->image,
        );
    }
}
