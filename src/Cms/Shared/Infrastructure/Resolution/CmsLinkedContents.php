<?php

declare(strict_types=1);

namespace Cms\Shared\Infrastructure\Resolution;

use Cms\Author\Application\AuthorOnSiteResponse;
use Cms\Author\Application\AuthorsOnSiteFinder;
use Cms\Author\Domain\AuthorRepository;
use Cms\Page\Application\PageOnSiteResponse;
use Cms\Page\Application\PagesOnSiteFinder;
use Cms\Page\Domain\PageRepository;
use Cms\Post\Application\PostsOnSiteFinder;
use Cms\Post\Domain\PostRepository;
use Cms\Shared\Infrastructure\Http\AvatarUrl;
use Cms\Shared\Infrastructure\Ui\CmsExternalViews;
use Cms\Shared\Infrastructure\Ui\CmsPostCards;
use Cms\Taxonomy\Application\TermOnSiteResponse;
use Cms\Taxonomy\Application\TermsOnSiteFinder;
use Cms\Taxonomy\Domain\TermRepository;
use Shared\Domain\Clock;
use Shared\Domain\Redirect\ReferenceResolver;
use Shared\Domain\RichText\LinkType;
use Shared\Domain\RichText\RichText;
use Shared\Infrastructure\Ui\ExternalComponents;
use Shared\Infrastructure\Ui\RichTextComponents;
use Shared\Infrastructure\Ui\SitePage;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * What a rich text previewed links to, its widgets and the captions of its
 * images too, as the site would show it once published: the pages, posts,
 * sections, tags and authors on the site, each with its address and name
 * there, keyed by the id linked; one off the site, as the one of its kind
 * its last address now leads to, as SiteLinkedContents does on the site.
 *
 * It reaches every module a link can lead to, so its coupling and what it
 * takes grow with the kinds of content and say nothing about its design.
 *
 * @SuppressWarnings("PHPMD.CouplingBetweenObjects")
 * @SuppressWarnings("PHPMD.ExcessiveParameterList")
 *
 * @phpstan-import-type ExternalView from ExternalComponents
 * @phpstan-import-type LinkedContent from RichTextComponents
 * @phpstan-import-type RelatedView from RichTextComponents
 */
final readonly class CmsLinkedContents
{
    /**
     * How long the link to the avatar of an author lasts, from when the
     * preview is painted, as on the preview of the author.
     */
    private const string AVATAR_VALIDITY = 'P1D';

    public function __construct(
        private UrlGeneratorInterface $urlGenerator,
        private PageRepository $pageRepository,
        private PostRepository $postRepository,
        private TermRepository $termRepository,
        private AuthorRepository $authorRepository,
        private CmsRedirectsByOrigin $redirectsByOrigin,
        private CmsExternalViews $externalViews,
        private CmsPostCards $postCards,
        private AvatarUrl $avatarUrl,
    ) {}

    /**
     * @param array<string, array{caption: RichText}> $images  as painted, keyed by id
     * @param ?string                                 $painted the id of the content previewed, linked by no text of its own
     *
     * @return array<string, LinkedContent>
     */
    public function __invoke(RichText $content, array $images, ?string $painted): array
    {
        return $this->linked(static fn (LinkType $type): array => SitePage::linkedIds($content, $images, $type), $painted);
    }

    /**
     * What the entries of menus lead to, as for a text: those of each kind
     * on the site, or redirected, with their address and name, keyed by id.
     *
     * @param list<array{type: string, id: string, label: ?string}> $entries
     *
     * @return array<string, LinkedContent>
     */
    public function menuTargets(array $entries): array
    {
        return $this->linked(static fn (LinkType $type): array => array_values(array_map(
            static fn (array $entry): string => $entry['id'],
            array_filter($entries, static fn (array $entry): bool => $type->value === $entry['type']),
        )), null);
    }

    /**
     * @param \Closure(LinkType): list<string> $ids what is linked of each kind
     *
     * @return array<string, LinkedContent>
     */
    private function linked(\Closure $ids, ?string $painted): array
    {
        return [
            ...$this->resolve(LinkType::Page, $ids(LinkType::Page), $painted, fn (array $ids): array => array_map(fn (PageOnSiteResponse $page): array => [
                'url' => null === $page->slug ? $this->urlGenerator->generate('web_home') : $this->urlGenerator->generate('web_page', ['slug' => $page->slug]),
                'title' => $page->title,
                'related' => self::related('page', $page->metaDescription, null, null, null),
            ], new PagesOnSiteFinder($ids)($this->pageRepository))),
            ...$this->resolve(LinkType::Post, $ids(LinkType::Post), $painted, $this->posts(...)),
            ...$this->resolve(LinkType::Term, $ids(LinkType::Term), $painted, fn (array $ids): array => array_map(function (TermOnSiteResponse $term): array {
                $kind = $term->isSection() ? 'section' : 'tag';

                return ['url' => $this->urlGenerator->generate('web_'.$kind, ['slug' => $term->slug]), 'title' => $term->name, 'related' => self::related($kind, $term->metaDescription, null, null, null)];
            }, new TermsOnSiteFinder($ids)($this->termRepository))),
            ...$this->resolve(LinkType::Author, $ids(LinkType::Author), $painted, $this->authors(...)),
            // No address of the site leads to an external resource, so none is redirected.
            ...($this->externalViews)($ids(LinkType::External)),
        ];
    }

    /**
     * The posts on the site among those given, as a link and a card of
     * related content show them, summed up as the preview does everywhere.
     *
     * @param list<string> $ids
     *
     * @return array<string, LinkedContent>
     */
    private function posts(array $ids): array
    {
        $posts = new PostsOnSiteFinder($ids)($this->postRepository);

        return array_combine(array_keys($posts), array_map(static fn (array $card): array => [
            'url' => $card['url'],
            'title' => $card['title'],
            'related' => self::related('post', $card['excerpt'], $card['image'], '' === $card['section'] ? null : $card['section'], $card['firstPublishedAt']),
        ], $this->postCards->cards(array_values($posts))));
    }

    /**
     * The authors on the site among those given, read at once, as a link
     * and a card of related content show them.
     *
     * @param list<string> $ids
     *
     * @return array<string, LinkedContent>
     */
    private function authors(array $ids): array
    {
        return array_map(function (AuthorOnSiteResponse $author): array {
            // Its avatar is the CMS's file, as on the preview of the author.
            $avatar = null === $author->avatar ? null : [
                'src' => ($this->avatarUrl)($author->id, $author->avatar, Clock::now()->add(new \DateInterval(self::AVATAR_VALIDITY))),
                'srcset' => '',
                'width' => AuthorOnSiteResponse::AVATAR_SIZE,
                'height' => AuthorOnSiteResponse::AVATAR_SIZE,
            ];

            return [
                'url' => $this->urlGenerator->generate('web_author', ['slug' => $author->slug]),
                'title' => $author->name,
                'related' => self::related('author', $author->metaDescription, $avatar, null, null),
            ];
        }, new AuthorsOnSiteFinder($ids)($this->authorRepository));
    }

    /**
     * @param list<string>                                         $ids
     * @param \Closure(list<string>): array<string, LinkedContent> $onSite
     *
     * @return array<string, LinkedContent>
     */
    private function resolve(LinkType $type, array $ids, ?string $painted, \Closure $onSite): array
    {
        return new ReferenceResolver($type->value, $ids, $painted)($onSite, $this->redirectsByOrigin);
    }

    /**
     * What a card of related content shows of a content of the blog, beside
     * its name.
     *
     * @param ?array{src: string, srcset: string, width: int, height: int} $image
     *
     * @return RelatedView
     */
    private static function related(string $kind, ?string $summary, ?array $image, ?string $section, ?\DateTimeImmutable $published): array
    {
        return ['kind' => $kind, 'summary' => $summary, 'image' => $image, 'section' => $section, 'published' => $published];
    }
}
