<?php

declare(strict_types=1);

namespace Cms\External\Domain;

use Shared\Domain\External\WebAddress;

/**
 * How a study is referred to by its address: a DOI, the id of PubMed, or the
 * id of arXiv, each of which tells where to read what it is.
 */
final readonly class StudyReference
{
    private function __construct(
        private StudyRegistry $registry,
        private string $id,
    ) {}

    /**
     * The study the address names, or null when it names none.
     */
    public static function fromAddress(WebAddress $address): ?self
    {
        $path = rawurldecode(ltrim($address->path(), '/'));

        return match (true) {
            \in_array($address->host(), ['doi.org', 'dx.doi.org'], true) && 1 === preg_match('#^(10\.[0-9]{4,9}/\S+)$#', $path, $matches) => new self(StudyRegistry::Doi, $matches[1]),
            'pubmed.ncbi.nlm.nih.gov' === $address->host() && 1 === preg_match('#^([0-9]{1,9})/?$#', $path, $matches) => new self(StudyRegistry::PubMed, $matches[1]),
            'arxiv.org' === $address->host() && 1 === preg_match('#^(?:abs|pdf)/([0-9]{4}\.[0-9]{4,5})(?:v[0-9]+)?(?:\.pdf)?$#', $path, $matches) => new self(StudyRegistry::Arxiv, $matches[1]),
            default => null,
        };
    }

    public function registry(): StudyRegistry
    {
        return $this->registry;
    }

    public function id(): string
    {
        return $this->id;
    }
}
