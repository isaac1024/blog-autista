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
 * @phpstan-type Article array{publishedTime: string, modifiedTime: string, section: ?string, tags: list<string>}
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
     * @param ?array{src: string, width: int, height: int, alt: string} $image          the image it is shared with, null for none
     * @param ?array{type: string, id: string}                          $countedContent what its visits are counted by, its kind and its id, null for a page whose visits aren't
     * @param list<string>                                              $frames         the origins of the resources it embeds behind facades, which it lets frame them
     * @param list<array<string, mixed>>                                $structuredData the nodes of schema.org it tells search engines it is, as JSON-LD
     * @param ?string                                                   $siteName       the name of the site it is part of, which it is shared as part of, null for none
     * @param ?Article                                                  $article        the post it shows, which it is shared as, null for a page that shows none
     * @param ?array{url: string, title: string}                        $feed           the feed of the site it tells readers of, null for none
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
        public ?array $countedContent,
        public array $frames,
        public array $structuredData,
        public ?string $siteName,
        public ?array $article,
        public ?array $feed,
    ) {}

    public static function create(string $title): self
    {
        return new self($title, [], [], [], self::DEFAULT_MAIN_CLASS, self::DEFAULT_LAYOUT, null, null, null, null, null, [], [], null, null, null);
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
            $this->countedContent,
            $this->frames,
            $this->structuredData,
            $this->siteName,
            $this->article,
            $this->feed,
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
            $this->countedContent,
            $this->frames,
            $this->structuredData,
            $this->siteName,
            $this->article,
            $this->feed,
        );
    }

    /**
     * Counts the visits of the page, by the kind and the id of what it shows,
     * such as "post" and the id of the post, once its reader sees it: only a
     * page on the site, never a preview nor an error.
     */
    public function withCountedVisits(string $type, string $id): self
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
            $this->image,
            ['type' => $type, 'id' => $id],
            $this->frames,
            $this->structuredData,
            $this->siteName,
            $this->article,
            $this->feed,
        );
    }

    /**
     * Lets the page frame the resources it embeds from those origins, such
     * as the videos of a post, besides those it already does.
     *
     * @param list<string> $origins
     */
    public function withFrames(array $origins): self
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
            $this->image,
            $this->countedContent,
            array_values(array_unique([...$this->frames, ...$origins])),
            $this->structuredData,
            $this->siteName,
            $this->article,
            $this->feed,
        );
    }

    /**
     * Tells search engines one more thing it is, as a node of schema.org,
     * such as the post it shows: all of them go in one JSON-LD graph. A node
     * with the @id of one it has already takes its place, so the same thing,
     * such as the author of the blog on their own page, is told once.
     *
     * @param array<string, mixed> $node
     */
    public function withStructuredData(array $node): self
    {
        $others = array_values(array_filter(
            $this->structuredData,
            static fn (array $other): bool => !isset($node['@id']) || ($other['@id'] ?? null) !== $node['@id'],
        ));

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
            $this->image,
            $this->countedContent,
            $this->frames,
            [...$others, $node],
            $this->siteName,
            $this->article,
            $this->feed,
        );
    }

    /**
     * The name of the site the page is part of, which readers see beside it
     * when it is shared.
     */
    public function withSiteName(string $siteName): self
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
            $this->image,
            $this->countedContent,
            $this->frames,
            $this->structuredData,
            $siteName,
            $this->article,
            $this->feed,
        );
    }

    /**
     * Tells readers and their feed readers where the feed of the site is,
     * by its address and the name it goes by.
     */
    public function withFeed(string $url, string $title): self
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
            $this->image,
            $this->countedContent,
            $this->frames,
            $this->structuredData,
            $this->siteName,
            $this->article,
            ['url' => $url, 'title' => $title],
        );
    }

    /**
     * Shares the page as a post, rather than as a page of the site: when it
     * was first and last published, its section, none while unfiled, and the
     * names of its tags.
     *
     * @param list<string> $tags
     */
    public function withArticle(\DateTimeImmutable $publishedAt, \DateTimeImmutable $modifiedAt, ?string $section, array $tags): self
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
            $this->image,
            $this->countedContent,
            $this->frames,
            $this->structuredData,
            $this->siteName,
            [
                'publishedTime' => $publishedAt->format(\DATE_ATOM),
                'modifiedTime' => $modifiedAt->format(\DATE_ATOM),
                'section' => $section,
                'tags' => $tags,
            ],
            $this->feed,
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
            $this->countedContent,
            $this->frames,
            $this->structuredData,
            $this->siteName,
            $this->article,
            $this->feed,
        );
    }
}
