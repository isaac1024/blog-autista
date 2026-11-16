<?php

declare(strict_types=1);

namespace Web\Sitemap\Infrastructure\Controller;

use Shared\Domain\ImageCopy;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Web\Sitemap\Application\SitemapFileNotFound;
use Web\Sitemap\Application\SitemapLinesReader;
use Web\Sitemap\Domain\SitemapRepository;

/**
 * Serves a file of the sitemap, written out as its lines are read, never
 * held whole: each address with when it last changed, and a post with the
 * largest copy of the image it opens with.
 */
final class SitemapFileController extends AbstractController
{
    /**
     * Writes what is written so far every so many addresses.
     */
    private const int FLUSH_EVERY = 500;

    #[Route('/sitemap-{name}.xml', name: 'web_sitemap_file', requirements: ['name' => '[a-z]+(?:-[0-9]{4})?'], methods: ['GET'])]
    public function __invoke(string $name, SitemapRepository $repository): StreamedResponse
    {
        try {
            $lines = new SitemapLinesReader($name)($repository);
        } catch (SitemapFileNotFound $exception) {
            throw $this->createNotFoundException($exception->getMessage(), $exception);
        }

        $site = rtrim($this->generateUrl('web_home', referenceType: UrlGeneratorInterface::ABSOLUTE_URL), '/');

        return new StreamedResponse(static function () use ($lines, $site): void {
            $xml = SitemapXml::open();
            $xml->startElement('urlset');
            $xml->writeAttribute('xmlns', SitemapXml::NAMESPACE);
            $xml->writeAttribute('xmlns:image', SitemapXml::IMAGE_NAMESPACE);
            $written = 0;

            foreach ($lines as $line) {
                $xml->startElement('url');
                $xml->writeElement('loc', $site.$line->path);
                $xml->writeElement('lastmod', $line->lastmod->format(\DATE_ATOM));

                if (null !== $line->imagePath) {
                    $xml->startElement('image:image');
                    $xml->writeElement('image:loc', $site.new ImageCopy($line->imagePath, ImageCopy::LARGEST)->url());
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
