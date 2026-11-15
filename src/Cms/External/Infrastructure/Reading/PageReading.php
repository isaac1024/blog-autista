<?php

declare(strict_types=1);

namespace Cms\External\Infrastructure\Reading;

use Cms\External\Domain\Language;
use Cms\External\Domain\Reading;
use Cms\External\Domain\ResourceUrl;
use Shared\Domain\External\ResourceKind;
use Shared\Infrastructure\Http\RemoteHttp;
use Shared\Infrastructure\Http\RemoteUnreachable;

/**
 * Reads an address no provider embeds: a PDF is a document, told by what
 * the server says it is; anything else is an article, read from the head of
 * its page.
 */
final readonly class PageReading
{
    /**
     * Enough of a page to have read its head, and no more of a document.
     */
    private const int MAX_BYTES = 1_000_000;

    public function __construct(private RemoteHttp $http) {}

    public function read(ResourceUrl $url): Reading
    {
        try {
            $response = $this->http->get($url->value(), self::MAX_BYTES, 'text/html, application/xhtml+xml, application/pdf;q=0.9, */*;q=0.5');
        } catch (RemoteUnreachable) {
            return self::fromAddress($url);
        }

        if ('application/pdf' === $response->contentType) {
            return new Reading(ResourceKind::Document, null, null, self::fileTitle($url), null, null, [
                'publisher' => null,
                'bytes' => $response->contentLength ?? ($response->truncated ? null : \strlen($response->body)),
                'pages' => null,
            ], [], true);
        }

        if (!$response->isSuccessful() || !\in_array($response->contentType, ['text/html', 'application/xhtml+xml'], true)) {
            return self::fromAddress($url);
        }

        $page = PageMetadata::fromHtml($response->body);
        $language = $page->language();
        $image = $page->image($response->url);

        return new Reading(
            ResourceKind::Article,
            null,
            null,
            $page->title(),
            $page->summary(),
            null === $language ? null : Language::tryFrom($language),
            ['publisher' => $page->siteName(), 'published_on' => $page->publishedOn()],
            null === $image ? [] : [$image],
            true,
        );
    }

    /**
     * What the address alone tells: a document when it names a PDF, an
     * article otherwise, titled after its host.
     */
    public static function fromAddress(ResourceUrl $url): Reading
    {
        $document = str_ends_with(strtolower($url->address()->path()), '.pdf');

        return new Reading(
            $document ? ResourceKind::Document : ResourceKind::Article,
            null,
            null,
            $document ? self::fileTitle($url) : null,
            null,
            null,
            [],
            [],
            false,
        );
    }

    /**
     * The name of the file, as words, such as "Informe anual 2025" for
     * informe-anual-2025.pdf.
     */
    private static function fileTitle(ResourceUrl $url): ?string
    {
        $name = rawurldecode(pathinfo($url->address()->path(), \PATHINFO_FILENAME));
        $name = trim((string) preg_replace('/[\s_-]+/u', ' ', $name));

        return '' === $name ? null : mb_strtoupper(mb_substr($name, 0, 1)).mb_substr($name, 1);
    }
}
