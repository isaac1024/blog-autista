<?php

declare(strict_types=1);

namespace Shared\Ui\Page;

/**
 * Immutable description of a page.
 *
 * Controllers compose the regions with Twig components; the generic
 * `page.html.twig` template is the only place that renders them.
 */
final readonly class Page
{
    private const string DEFAULT_MAIN_CLASS = 'mx-auto w-[min(100%-2rem,72rem)] py-[clamp(2rem,6vw,4rem)]';

    /**
     * @param list<PageComponent> $header
     * @param list<PageComponent> $main
     * @param list<PageComponent> $footer
     */
    private function __construct(
        public string $title,
        public array $header,
        public array $main,
        public array $footer,
        public string $mainClass,
    ) {}

    public static function create(string $title): self
    {
        return new self($title, [], [], [], self::DEFAULT_MAIN_CLASS);
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
    ): self {
        return new self(
            $this->title,
            $header ?? $this->header,
            $main ?? $this->main,
            $footer ?? $this->footer,
            $mainClass ?? $this->mainClass,
        );
    }
}
