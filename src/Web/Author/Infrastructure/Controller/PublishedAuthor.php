<?php

declare(strict_types=1);

namespace Web\Author\Infrastructure\Controller;

use Shared\Domain\Redirect\ReferenceResolver;
use Shared\Infrastructure\Ui\Page\Page;
use Shared\Infrastructure\Ui\SiteAuthorPage;
use Shared\Infrastructure\Ui\SiteMedia;
use Shared\Infrastructure\Ui\StructuredData;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Web\Author\Application\AuthorResponse;
use Web\Image\Application\ImageResponse;
use Web\Image\Application\ImagesOnSiteFinder;
use Web\Image\Domain\ImageRepository;
use Web\Post\Infrastructure\Ui\SiteMosaics;
use Web\Redirect\Infrastructure\Resolution\SiteLinkedContents;
use Web\Redirect\Infrastructure\Resolution\SiteRedirectsByOrigin;
use Web\Settings\Infrastructure\Controller\SiteLayout;

/**
 * Composes the page of an author of the site, painted as SiteAuthorPage
 * paints it, in the chrome of the site, presenting itself to search engines
 * with its own address as its canonical URL. Its bio paints only the images
 * and the pages on the site, as the content of a page does.
 *
 * It takes one collaborator for each part of the site the page shows.
 *
 * @SuppressWarnings("PHPMD.ExcessiveParameterList")
 */
final readonly class PublishedAuthor
{
    public function __construct(
        private UrlGeneratorInterface $urlGenerator,
        private SiteAuthorPage $siteAuthorPage,
        private SiteLayout $siteLayout,
        private ImageRepository $imageRepository,
        private SiteRedirectsByOrigin $redirectsByOrigin,
        private SiteLinkedContents $linkedContents,
        private SiteMosaics $mosaics,
    ) {}

    public function __invoke(AuthorResponse $author): Page
    {
        $canonical = $this->urlGenerator->generate('web_author', ['slug' => $author->slug], UrlGeneratorInterface::ABSOLUTE_URL);

        // An image off the site is painted as the one its address now leads
        // to, if any, so the pages its caption links to are those of that one.
        $images = array_map(
            static fn (ImageResponse $image): array => SiteMedia::image($image->path, $image->alt, $image->caption),
            new ReferenceResolver('image', $author->bio->imageIds(), null)(
                fn (array $ids): array => new ImagesOnSiteFinder($ids)($this->imageRepository),
                $this->redirectsByOrigin,
            ),
        );

        $home = $this->urlGenerator->generate('web_home', referenceType: UrlGeneratorInterface::ABSOLUTE_URL);
        $avatar = SiteMedia::avatar($author->avatarName, $author->name);

        // The page of a person: the person, who may be the one who publishes the blog too, is one node.
        return ($this->siteAuthorPage)(
            $this->siteLayout->page($author->name)
                ->withSearchEngineMetadata($author->metaDescription, $author->robots.',max-image-preview:large', $canonical)
                ->withCountedVisits('author', $author->id)
                ->withStructuredData(StructuredData::webPage('ProfilePage', $home, $canonical, $author->name, $author->metaDescription, [
                    'mainEntity' => ['@id' => StructuredData::personId($canonical)],
                ]))
                ->withStructuredData(StructuredData::person($canonical, $author->name, StructuredData::absolute($home, $avatar['src']), $author->metaDescription)),
            $author->name,
            $avatar,
            $author->bio,
            $images,
            ($this->linkedContents)($author->bio, $images, $author->id),
            ($this->mosaics)($author->bio),
        );
    }
}
