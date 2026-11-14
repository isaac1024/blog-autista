<?php

declare(strict_types=1);

namespace Cms\Shared\Domain;

use Doctrine\ORM\Mapping as ORM;

/**
 * How something the site serves at an address presents itself to search
 * engines. Its title in the results is its own title or name; the rest lives
 * here.
 *
 * - The meta description is the summary under that title. It is optional
 *   until it is published, though search engines show it best between 70
 *   and 160 characters.
 * - Robots say whether search engines may list it and follow its links.
 *
 * Its canonical URL is always its own address, so it isn't stored.
 */
#[ORM\Embeddable]
final readonly class Seo
{
    public const int DESCRIPTION_MAX_LENGTH = 200;
    public const int DESCRIPTION_RECOMMENDED_MIN_LENGTH = 70;
    public const int DESCRIPTION_RECOMMENDED_MAX_LENGTH = 160;

    public function __construct(
        #[ORM\Column(name: 'meta_description', length: self::DESCRIPTION_MAX_LENGTH, nullable: true)]
        private ?string $description,
        #[ORM\Column(name: 'robots', length: 20, enumType: Robots::class)]
        private Robots $robots,
    ) {
        if (null !== $description && '' === $description) {
            throw InvalidSeo::emptyDescription();
        }

        if (null !== $description && mb_strlen($description) > self::DESCRIPTION_MAX_LENGTH) {
            throw InvalidSeo::descriptionTooLong(self::DESCRIPTION_MAX_LENGTH);
        }
    }

    /**
     * Builds it from what a form submits: a blank description means none,
     * and it becomes one line, the way search engines show it.
     */
    public static function fromPrimitives(string $description, string $robots): self
    {
        $description = trim((string) preg_replace('/\s+/u', ' ', $description));

        return new self(
            '' === $description ? null : $description,
            Robots::tryFrom($robots) ?? throw InvalidSeo::unknownRobots($robots),
        );
    }

    /**
     * Where something new starts: without a meta description, and with the
     * robots directive given.
     */
    public static function initial(Robots $robots): self
    {
        return new self(null, $robots);
    }

    public function description(): ?string
    {
        return $this->description;
    }

    public function robots(): Robots
    {
        return $this->robots;
    }
}
