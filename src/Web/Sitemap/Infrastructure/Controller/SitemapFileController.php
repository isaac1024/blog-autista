<?php

declare(strict_types=1);

namespace Web\Sitemap\Infrastructure\Controller;

use Shared\Domain\ImageCopy;
use Shared\Domain\Redirect\ReferenceResolver;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Web\Image\Application\ImageResponse;
use Web\Image\Application\ImagesOnSiteFinder;
use Web\Image\Domain\ImageRepository;
use Web\Redirect\Infrastructure\Resolution\SiteRedirectsByOrigin;
use Web\Sitemap\Application\SitemapFileNotFound;
use Web\Sitemap\Application\SitemapLinesReader;
use Web\Sitemap\Domain\SitemapRepository;

/**
 * Serves a file of the sitemap, written out as its lines are read, never
 * held whole: each address with when it last changed, and a post with the
 * largest copy of the image it opens with. An image off the site is named
 * as the one its address now leads to, if any, as the post shows it.
 */
final class SitemapFileController extends AbstractController
{
    /**
     * Writes what is written so far every so many addresses.
     */
    private const int FLUSH_EVERY = 500;

    #[Route('/sitemap-{name}.xml', name: 'web_sitemap_file', requirements: ['name' => '[a-z]+(?:-[0-9]{4})?'], methods: ['GET'])]
    public function __invoke(string $name, SitemapRepository $repository, ImageRepository $imageRepository, SiteRedirectsByOrigin $redirectsByOrigin): StreamedResponse
    {
        try {
            $lines = new SitemapLinesReader($name)($repository);
        } catch (SitemapFileNotFound $exception) {
            throw $this->createNotFoundException($exception->getMessage(), $exception);
        }

        // Only the images off the site are looked up again, all at once.
        $redirected = array_map(
            static fn (ImageResponse $image): string => $image->path,
            new ReferenceResolver('image', $repository->imagesOffSite($name), null)(
                static fn (array $ids): array => new ImagesOnSiteFinder($ids)($imageRepository),
                $redirectsByOrigin,
            ),
        );

        $site = rtrim($this->generateUrl('web_home', referenceType: UrlGeneratorInterface::ABSOLUTE_URL), '/');

        return new StreamedResponse(static function () use ($lines, $site, $redirected): void {
            $xml = SitemapXml::open();
            $xml->startElement('urlset');
            $xml->writeAttribute('xmlns', SitemapXml::NAMESPACE);
            $xml->writeAttribute('xmlns:image', SitemapXml::IMAGE_NAMESPACE);
            $written = 0;

            foreach ($lines as $line) {
                $xml->startElement('url');
                $xml->writeElement('loc', $site.$line->path);
                $xml->writeElement('lastmod', $line->lastmod->format(\DATE_ATOM));

                $imagePath = $line->imagePath ?? (null === $line->imageId ? null : $redirected[$line->imageId] ?? null);

                if (null !== $imagePath) {
                    $xml->startElement('image:image');
                    $xml->writeElement('image:loc', $site.new ImageCopy($imagePath, ImageCopy::LARGEST)->url());
                    $xml->endElement();
                }

                $xml->endElement();

                if (0 === ++$written % self::FLUSH_EVERY) {
                    echo $xml->flush();
                    flush();
                }
            }

            $xml->endElement();
            SitemapXml::close($xml);
        }, 200, ['Content-Type' => SitemapXml::CONTENT_TYPE]);
    }
}
