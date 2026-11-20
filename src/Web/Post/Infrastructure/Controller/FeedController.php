<?php

declare(strict_types=1);

namespace Web\Post\Infrastructure\Controller;

use Shared\Domain\Clock;
use Shared\Infrastructure\Ui\WebLayout;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Web\Settings\Application\SettingsOnSiteFinder;
use Web\Settings\Domain\SettingsRepository;
use Web\Shared\Infrastructure\Http\StreamedXml;

/**
 * Serves the feed of the site, in Atom: its latest posts search engines may
 * index (FeedEntries), each with its title, address, dates, author, summary
 * and the image it opens with, so readers follow the blog without any account of
 * another site. It tells no text in full: each entry leads to its post.
 */
final class FeedController extends AbstractController
{
    public const string TYPE = 'application/atom+xml';

    public const string CONTENT_TYPE = self::TYPE.'; charset=UTF-8';

    /**
     * How many of the latest posts it tells.
     */
    public const int LATEST = 20;

    private const string NAMESPACE = 'http://www.w3.org/2005/Atom';

    #[Route('/feed.xml', name: 'web_feed', methods: ['GET'])]
    public function __invoke(Request $request, UrlGeneratorInterface $urlGenerator, FeedEntries $feedEntries, SettingsRepository $settingsRepository): StreamedResponse
    {
        $entries = $feedEntries(self::LATEST);
        // Named as the blog, or as its host until its settings are published.
        $name = new SettingsOnSiteFinder()($settingsRepository)->name ?? $request->getHost();
        $home = $urlGenerator->generate('web_home', referenceType: UrlGeneratorInterface::ABSOLUTE_URL);
        $updated = [] === $entries ? Clock::now() : max(array_column($entries, 'updated'));

        return new StreamedResponse(static function () use ($urlGenerator, $entries, $name, $home, $updated): void {
            $xml = StreamedXml::open();
            $xml->startElement('feed');
            $xml->writeAttribute('xmlns', self::NAMESPACE);
            $xml->writeAttribute('xml:lang', WebLayout::LOCALE);
            $xml->writeElement('id', $home);
            $xml->writeElement('title', $name);
            $xml->writeElement('updated', $updated->format(\DATE_ATOM));
            self::link($xml, 'self', $urlGenerator->generate('web_feed', referenceType: UrlGeneratorInterface::ABSOLUTE_URL), self::TYPE);
            self::link($xml, 'alternate', $home, 'text/html');

            foreach ($entries as $entry) {
                $xml->startElement('entry');
                $xml->writeElement('id', $entry['url']);
                $xml->writeElement('title', $entry['title']);
                self::link($xml, 'alternate', $entry['url'], 'text/html');
                $xml->writeElement('published', $entry['published']->format(\DATE_ATOM));
                $xml->writeElement('updated', $entry['updated']->format(\DATE_ATOM));
                $xml->startElement('author');
                $xml->writeElement('name', $entry['author']);
                $xml->endElement();

                if (null !== $entry['summary']) {
                    $xml->writeElement('summary', $entry['summary']);
                }

                if (null !== $entry['image']) {
                    self::link($xml, 'enclosure', $entry['image'], 'image/webp');
                }

                $xml->endElement();
            }

            $xml->endElement();
            StreamedXml::close($xml);
        }, 200, ['Content-Type' => self::CONTENT_TYPE]);
    }

    private static function link(\XMLWriter $xml, string $rel, string $href, string $type): void
    {
        $xml->startElement('link');
        $xml->writeAttribute('rel', $rel);
        $xml->writeAttribute('type', $type);
        $xml->writeAttribute('href', $href);
        $xml->endElement();
    }
}
