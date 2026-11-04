<?php

declare(strict_types=1);

namespace Cms\Page\Domain;

use Doctrine\ORM\Mapping as ORM;

/**
 * How a page presents itself to search engines. Its title in the results is
 * the title of the page; the rest lives here.
 *
 * - The meta description is the summary under that title. It is optional for
 *   now, though search engines show it best between 70 and 160 characters.
 * - Robots say whether search engines may list the page and follow its links.
 * - The canonical URL is only for content published elsewhere first: without
 *   one, the page's own address is canonical.
 */
#[ORM\Embeddable]
final readonly class Seo
{
    public const int DESCRIPTION_MAX_LENGTH = 200;
    public const int DESCRIPTION_RECOMMENDED_MIN_LENGTH = 70;
    public const int DESCRIPTION_RECOMMENDED_MAX_LENGTH = 160;
    public const int CANONICAL_URL_MAX_LENGTH = 2048;

    public function __construct(
        #[ORM\Column(name: 'meta_description', length: self::DESCRIPTION_MAX_LENGTH, nullable: true)]
        private ?string $description,
        #[ORM\Column(name: 'robots', length: 20, enumType: Robots::class)]
        private Robots $robots,
        #[ORM\Column(name: 'canonical_url', length: self::CANONICAL_URL_MAX_LENGTH, nullable: true)]
        private ?string $canonicalUrl,
    ) {
        if (null !== $description && '' === $description) {
            throw InvalidSeo::emptyDescription();
        }

        if (null !== $description && mb_strlen($description) > self::DESCRIPTION_MAX_LENGTH) {
            throw InvalidSeo::descriptionTooLong(self::DESCRIPTION_MAX_LENGTH);
        }

        if (null !== $canonicalUrl) {
            self::assertCanonicalUrl($canonicalUrl);
        }
    }

    /**
     * Builds it from what a form submits: blank fields mean none, and the
     * description becomes one line, the way search engines show it.
     */
    public static function fromPrimitives(string $description, string $robots, string $canonicalUrl): self
    {
        $description = trim((string) preg_replace('/\s+/u', ' ', $description));
        $canonicalUrl = trim($canonicalUrl);

        return new self(
            '' === $description ? null : $description,
            Robots::tryFrom($robots) ?? throw InvalidSeo::unknownRobots($robots),
            '' === $canonicalUrl ? null : $canonicalUrl,
        );
    }

    /**
     * Where a new page starts: listed in search results, except the search
     * page, whose results search engines advise against indexing.
     */
    public static function initialFor(PageType $type): self
    {
        return new self(null, PageType::Search === $type ? Robots::NoindexFollow : Robots::IndexFollow, null);
    }

    public function description(): ?string
    {
        return $this->description;
    }

    public function robots(): Robots
    {
        return $this->robots;
    }

    public function canonicalUrl(): ?string
    {
        return $this->canonicalUrl;
    }

    private static function assertCanonicalUrl(string $url): void
    {
        if (\strlen($url) > self::CANONICAL_URL_MAX_LENGTH) {
            throw InvalidSeo::canonicalUrlTooLong(self::CANONICAL_URL_MAX_LENGTH);
        }

        $scheme = parse_url($url, \PHP_URL_SCHEME);

        if (false === filter_var($url, \FILTER_VALIDATE_URL) || !\in_array($scheme, ['http', 'https'], true)) {
            throw InvalidSeo::malformedCanonicalUrl($url);
        }
    }
}
