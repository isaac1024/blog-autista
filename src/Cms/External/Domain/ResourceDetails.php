<?php

declare(strict_types=1);

namespace Cms\External\Domain;

use Shared\Domain\External\ResourceKind;

/**
 * What an external resource tells besides its title, which depends on what
 * it is: who wrote a post and what it says, the journal of a study, and so
 * on. Editing saves whatever valid details an editor writes; publishing
 * asks for those its card or its facade needs.
 *
 * Each detail is kept as text, a number, a yes or no, or null for none, the
 * same way it is written and stored.
 */
abstract readonly class ResourceDetails
{
    /**
     * The details of a resource of the kind, from what was written or stored.
     * A detail missing, or blank, is none.
     *
     * @param array<array-key, mixed> $data
     */
    final public static function fromPrimitives(ResourceKind $kind, array $data): self
    {
        $read = new DetailsInput($data);

        return match ($kind) {
            ResourceKind::Video => new VideoDetails(),
            ResourceKind::SocialPost => new SocialPostDetails($read->text('author'), $read->text('handle'), $read->date('posted_on'), $read->text('text'), $read->flag('with_media')),
            ResourceKind::Audio => new AudioDetails($read->text('show')),
            ResourceKind::Article => new ArticleDetails($read->text('publisher'), $read->date('published_on')),
            ResourceKind::Study => new StudyDetails($read->text('authors'), $read->text('journal'), $read->number('year')),
            ResourceKind::Document => new DocumentDetails($read->text('publisher'), $read->number('bytes'), $read->number('pages')),
        };
    }

    /**
     * The kind of resource these details are of.
     */
    abstract public function kind(): ResourceKind;

    /**
     * @throws InvalidResourcePublication when a detail the site shows is missing
     */
    abstract public function assertPublishable(): void;

    /**
     * @return array<string, null|bool|int|string>
     */
    abstract public function toArray(): array;

    final public function equals(self $other): bool
    {
        return static::class === $other::class && $this->toArray() === $other->toArray();
    }
}
