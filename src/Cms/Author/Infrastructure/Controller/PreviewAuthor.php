<?php

declare(strict_types=1);

namespace Cms\Author\Infrastructure\Controller;

use Cms\Author\Application\AuthorResponse;
use Cms\Author\Domain\AvatarFile;
use Cms\Image\Application\ImageOnSiteResponse;
use Cms\Image\Application\ImagesOnSiteFinder;
use Cms\Image\Domain\ImageRepository;
use Cms\Shared\Infrastructure\Http\AvatarUrl;
use Cms\Shared\Infrastructure\Resolution\CmsLinkedContents;
use Cms\Shared\Infrastructure\Resolution\CmsRedirectsByOrigin;
use Cms\Shared\Infrastructure\Ui\CmsMosaics;
use Cms\Shared\Infrastructure\Ui\PreviewLayout;
use Shared\Domain\Clock;
use Shared\Domain\Redirect\ReferenceResolver;
use Shared\Domain\RichText\RichText;
use Shared\Infrastructure\Ui\Page\Page;
use Shared\Infrastructure\Ui\SiteAuthorPage;
use Shared\Infrastructure\Ui\SiteMedia;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Composes the version of an author last saved as the site would serve it
 * once published, in the chrome the site shows now: its avatar comes from
 * the CMS, behind a signed link, and its bio paints only the images and the
 * pages on the site, as the site would. Links lead to the site.
 *
 * Search engines never index a preview nor follow its links, whatever the
 * author asks of them: its markup says so, and so does every response that
 * serves it, in the X-Robots-Tag header.
 *
 * It reaches the images and the pages the bio uses, as the site does.
 *
 * @SuppressWarnings("PHPMD.CouplingBetweenObjects")
 */
final readonly class PreviewAuthor
{
    public const string ROBOTS = 'noindex,nofollow';

    /**
     * How long the link to the avatar painted lasts, from when the preview
     * is painted.
     */
    private const string AVATAR_VALIDITY = 'P1D';

    /**
     * It paints from the settings, the images and the pages, as the site does.
     *
     * @SuppressWarnings("PHPMD.ExcessiveParameterList")
     */
    public function __construct(
        private UrlGeneratorInterface $urlGenerator,
        private PreviewLayout $previewLayout,
        private SiteAuthorPage $siteAuthorPage,
        private AvatarUrl $avatarUrl,
        private ImageRepository $imageRepository,
        private CmsRedirectsByOrigin $redirectsByOrigin,
        private CmsLinkedContents $linkedContents,
        private CmsMosaics $mosaics,
    ) {}

    public function __invoke(AuthorResponse $author): Page
    {
        $bio = RichText::fromArray($author->bio);
        $images = array_map(
            static fn (ImageOnSiteResponse $image): array => SiteMedia::image($image->path, $image->alt, $image->caption),
            new ReferenceResolver('image', $bio->imageIds(), null)(
                fn (array $ids): array => new ImagesOnSiteFinder($ids)($this->imageRepository),
                $this->redirectsByOrigin,
            ),
        );

        $canonical = null === $author->slug
            ? $this->urlGenerator->generate('web_home', referenceType: UrlGeneratorInterface::ABSOLUTE_URL)
            : $this->urlGenerator->generate('web_author', ['slug' => $author->slug], UrlGeneratorInterface::ABSOLUTE_URL);

        return ($this->siteAuthorPage)(
            $this->previewLayout->page($author->name)->withSearchEngineMetadata(
                '' === $author->metaDescription ? null : $author->metaDescription,
                self::ROBOTS,
                $canonical,
            ),
            $author->name,
            null === $author->avatar ? null : [
                'src' => ($this->avatarUrl)($author->id, $author->avatar, Clock::now()->add(new \DateInterval(self::AVATAR_VALIDITY))),
                'srcset' => '',
                'size' => AvatarFile::SIZE,
                'alt' => $author->name,
            ],
            $bio,
            $images,
            ($this->linkedContents)($bio, $images, $author->id),
            ($this->mosaics)($bio),
        );
    }
}
