<?php

declare(strict_types=1);

namespace Web\Sitemap\Infrastructure\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Web\Sitemap\Application\SitemapFilesFinder;
use Web\Sitemap\Domain\SitemapRepository;

/**
 * Serves the index of the sitemap: each of its files, with when it last
 * changed, so search engines fetch again only those that did.
 */
final class SitemapIndexController extends AbstractController
{
    #[Route('/sitemap.xml', name: 'web_sitemap_index', methods: ['GET'])]
    public function __invoke(SitemapRepository $repository): StreamedResponse
    {
        $files = new SitemapFilesFinder()($repository);

        return new StreamedResponse(function () use ($files): void {
            $xml = SitemapXml::open();
            $xml->startElement('sitemapindex');
            $xml->writeAttribute('xmlns', SitemapXml::NAMESPACE);

            foreach ($files as $name => $lastmod) {
                $xml->startElement('sitemap');
                $xml->writeElement('loc', $this->generateUrl('web_sitemap_file', ['name' => $name], UrlGeneratorInterface::ABSOLUTE_URL));
                $xml->writeElement('lastmod', $lastmod->format(\DATE_ATOM));
                $xml->endElement();
            }

            $xml->endElement();
            SitemapXml::close($xml);
        }, 200, ['Content-Type' => SitemapXml::CONTENT_TYPE]);
    }
}
