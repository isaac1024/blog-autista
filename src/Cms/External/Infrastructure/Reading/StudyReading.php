<?php

declare(strict_types=1);

namespace Cms\External\Infrastructure\Reading;

use Cms\External\Domain\Language;
use Cms\External\Domain\Reading;
use Cms\External\Domain\StudyReference;
use Cms\External\Domain\StudyRegistry;
use Shared\Domain\External\ResourceKind;
use Shared\Infrastructure\Http\RemoteHttp;

/**
 * Reads a study from the registry its address names: Crossref for a DOI,
 * the E-utilities of the NCBI for PubMed, and the API of arXiv, all of them
 * open, with no key.
 */
final readonly class StudyReading
{
    private const int MAX_BYTES = 2_000_000;

    /**
     * Authors listed one by one before the rest go as "et al.".
     */
    private const int LISTED_AUTHORS = 6;

    public function __construct(private RemoteHttp $http) {}

    /**
     * What the registry tells of the study, or null when it doesn't answer.
     */
    public function read(StudyReference $reference): ?Reading
    {
        try {
            return match ($reference->registry()) {
                StudyRegistry::Doi => $this->crossref($reference->id()),
                StudyRegistry::PubMed => $this->pubmed($reference->id()),
                StudyRegistry::Arxiv => $this->arxiv($reference->id()),
            };
        } catch (\RuntimeException) {
            return null;
        }
    }

    private function crossref(string $doi): ?Reading
    {
        $response = $this->http->get('https://api.crossref.org/works/'.rawurlencode($doi), self::MAX_BYTES, 'application/json');
        $work = $response->isSuccessful() ? self::dig($response->json(), 'message') : null;

        if (!\is_array($work)) {
            return null;
        }

        $authors = [];

        foreach (\is_array($work['author'] ?? null) ? $work['author'] : [] as $author) {
            if (\is_array($author)) {
                $authors[] = trim(\sprintf('%s %s', self::text($author['given'] ?? null) ?? '', self::text($author['family'] ?? null) ?? self::text($author['name'] ?? null) ?? ''));
            }
        }

        $issued = self::dig($work, 'issued', 'date-parts', 0, 0) ?? self::dig($work, 'published', 'date-parts', 0, 0);
        $abstract = self::text($work['abstract'] ?? null);

        return self::study(
            self::text(self::dig($work, 'title', 0)),
            null === $abstract ? null : PostText::fromHtml($abstract),
            $authors,
            self::text(self::dig($work, 'container-title', 0)) ?? self::text($work['publisher'] ?? null),
            \is_int($issued) ? $issued : null,
            self::text($work['language'] ?? null),
        );
    }

    private function pubmed(string $id): ?Reading
    {
        $response = $this->http->get('https://eutils.ncbi.nlm.nih.gov/entrez/eutils/esummary.fcgi?db=pubmed&retmode=json&id='.$id, self::MAX_BYTES, 'application/json');
        $summary = $response->isSuccessful() ? self::dig($response->json(), 'result', $id) : null;

        if (!\is_array($summary)) {
            return null;
        }

        $authors = [];

        foreach (\is_array($summary['authors'] ?? null) ? $summary['authors'] : [] as $author) {
            $name = \is_array($author) ? self::text($author['name'] ?? null) : null;

            if (null !== $name) {
                $authors[] = $name;
            }
        }

        $year = 1 === preg_match('/^(\d{4})/', (string) self::text($summary['pubdate'] ?? null), $matches) ? (int) $matches[1] : null;

        return self::study(self::text($summary['title'] ?? null), null, $authors, self::text($summary['fulljournalname'] ?? null) ?? self::text($summary['source'] ?? null), $year, null);
    }

    private function arxiv(string $id): ?Reading
    {
        $response = $this->http->get('https://export.arxiv.org/api/query?id_list='.$id, self::MAX_BYTES, 'application/atom+xml');

        if (!$response->isSuccessful() || $response->truncated) {
            return null;
        }

        try {
            $feed = \Dom\XMLDocument::createFromString($response->body, \LIBXML_NONET | \LIBXML_NOERROR | \LIBXML_NOWARNING);
        } catch (\Exception) {
            return null;
        }

        $entry = $feed->getElementsByTagName('entry')->item(0);

        if (null === $entry) {
            return null;
        }

        $authors = [];

        foreach ($entry->getElementsByTagName('name') as $name) {
            $authors[] = trim((string) $name->textContent);
        }

        $published = (string) $entry->getElementsByTagName('published')->item(0)?->textContent;

        return self::study(
            self::text($entry->getElementsByTagName('title')->item(0)?->textContent),
            self::text($entry->getElementsByTagName('summary')->item(0)?->textContent),
            $authors,
            'arXiv',
            1 === preg_match('/^(\d{4})/', $published, $matches) ? (int) $matches[1] : null,
            null,
        );
    }

    /**
     * @param list<string> $authors
     */
    private static function study(?string $title, ?string $summary, array $authors, ?string $journal, ?int $year, ?string $language): ?Reading
    {
        if (null === $title) {
            return null;
        }

        $authors = array_values(array_filter($authors, static fn (string $author): bool => '' !== $author));
        $listed = implode(', ', \array_slice($authors, 0, self::LISTED_AUTHORS)).(\count($authors) > self::LISTED_AUTHORS ? ' et al.' : '');

        return new Reading(
            ResourceKind::Study,
            null,
            null,
            $title,
            null === $summary ? null : mb_substr((string) preg_replace('/\s+/u', ' ', $summary), 0, 300),
            null === $language ? null : Language::tryFrom(strtolower(substr($language, 0, 2))),
            [
                'authors' => '' === $listed ? null : mb_substr($listed, 0, 300),
                'journal' => null === $journal ? null : mb_substr($journal, 0, 200),
                'year' => $year,
            ],
            [],
            true,
        );
    }

    /**
     * What the data holds down the path of keys, or null when any is missing.
     */
    private static function dig(mixed $data, int|string ...$path): mixed
    {
        foreach ($path as $key) {
            if (!\is_array($data) || !\array_key_exists($key, $data)) {
                return null;
            }

            $data = $data[$key];
        }

        return $data;
    }

    private static function text(mixed $value): ?string
    {
        if (!\is_string($value)) {
            return null;
        }

        $value = trim((string) preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags($value), \ENT_QUOTES | \ENT_HTML5, 'UTF-8')));

        return '' === $value ? null : $value;
    }
}
