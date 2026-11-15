<?php

declare(strict_types=1);

namespace Web\Author\Domain;

use Shared\Domain\Event\EventBody;
use Shared\Domain\Event\RevisedReceivedEvent;
use Shared\Domain\RichText\RichText;
use Web\Shared\Domain\Robots;

/**
 * The CMS put an author on the site, or replaced its version there, with all
 * the site needs to serve its page. The site declares it on its own, by the
 * name the CMS publishes it under.
 *
 * It carries what the site shows, one parameter each.
 *
 * @SuppressWarnings("PHPMD.ExcessiveParameterList")
 */
final readonly class CmsAuthorPublished extends RevisedReceivedEvent
{
    /**
     * @param string $avatar where the CMS keeps the file of the avatar, relative to its avatar storage
     */
    public function __construct(
        string $aggregateId,
        private string $name,
        private string $slug,
        private string $avatar,
        private RichText $bio,
        private ?string $metaDescription,
        private Robots $robots,
        private \DateTimeImmutable $firstPublishedAt,
        private \DateTimeImmutable $lastPublishedAt,
        int $revision,
    ) {
        parent::__construct($aggregateId, $revision);
    }

    #[\Override]
    public static function eventName(): string
    {
        return 'cms.author.published';
    }

    #[\Override]
    public static function fromPrimitives(string $aggregateId, array $body): static
    {
        $body = new EventBody($body);
        $metaDescription = $body->string('meta_description');

        return new static(
            $aggregateId,
            $body->string('name'),
            $body->string('slug'),
            $body->string('avatar'),
            RichText::fromArray($body->array('bio')),
            '' === $metaDescription ? null : $metaDescription,
            Robots::from($body->string('robots')),
            new \DateTimeImmutable($body->string('first_published_at')),
            new \DateTimeImmutable($body->string('last_published_at')),
            $body->integer('revision'),
        );
    }

    public function name(): string
    {
        return $this->name;
    }

    public function slug(): string
    {
        return $this->slug;
    }

    public function avatar(): string
    {
        return $this->avatar;
    }

    public function bio(): RichText
    {
        return $this->bio;
    }

    public function metaDescription(): ?string
    {
        return $this->metaDescription;
    }

    public function robots(): Robots
    {
        return $this->robots;
    }

    public function firstPublishedAt(): \DateTimeImmutable
    {
        return $this->firstPublishedAt;
    }

    public function lastPublishedAt(): \DateTimeImmutable
    {
        return $this->lastPublishedAt;
    }
}
