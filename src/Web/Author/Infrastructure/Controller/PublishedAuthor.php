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
use Web\Shared\Infrastructure\Resolution\SiteLinkedContents;
use Web\Shared\Infrastructure\Resolution\SiteRedirectsByOrigin;
use Web\Shared\Infrastructure\Ui\AuthorPerson;
use Web\Shared\Infrastructure\Ui\SiteLayout;
use Web\Shared\Infrastructure\Ui\SiteMosaics;
use Web\Shared\Infrastructure\Ui\SiteRobots;

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
        $person = AuthorPerson::node($author, $this->urlGenerator);
        $canonical = $person['url'];

        // The page of a person: the person, who may be the one who publishes the blog too, is one node.
        return ($this->siteAuthorPage)(
            $this->siteLayout->page($author->name)
                ->withSearchEngineMetadata($author->metaDescription, SiteRobots::meta($author->robots), $canonical)
                ->withCountedVisits('author', $author->id)
                ->withStructuredData(StructuredData::webPage('ProfilePage', $home, $canonical, $author->name, $author->metaDescription, [
                    'mainEntity' => ['@id' => StructuredData::personId($canonical)],
                    ...StructuredData::breadcrumbOf($canonical),
                ]))
                ->withStructuredData(StructuredData::breadcrumbs($home, $canonical, [['name' => $author->name, 'url' => null]]))
                ->withStructuredData($person['node']),
            $author->name,
            $avatar,
            $author->bio,
            $images,
            ($this->linkedContents)($author->bio, $images, $author->id),
            ($this->mosaics)($author->bio),
        );
    }
}
