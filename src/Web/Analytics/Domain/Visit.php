<?php

declare(strict_types=1);

namespace Web\Analytics\Domain;

use Doctrine\ORM\Mapping as ORM;

/**
 * A page of the site a reader saw, such as a post or the page of a section,
 * kept only until its day is counted.
 *
 * Where the reader came from and who they are, as far as the blog knows,
 * are kept as the columns the database counts them by.
 *
 * No event tells of it: there is one for every page seen, and nothing else
 * needs to know.
 *
 * Its constructor takes all of its state, one parameter each, as Doctrine
 * maps it: Doctrine hydrates it without calling it, and record() starts each
 * field.
 *
 * @SuppressWarnings("PHPMD.ExcessiveParameterList")
 */
#[ORM\Entity]
#[ORM\Table(name: 'shared_visit')]
#[ORM\Index(name: 'idx_shared_visit_day', columns: ['day'])]
final class Visit
{
    public function __construct(
        #[ORM\Id]
        #[ORM\Column(type: 'web_visit_id')]
        private readonly VisitId $id,
        #[ORM\Column(name: 'content_type', length: 10, enumType: ContentType::class)]
        private readonly ContentType $contentType,
        #[ORM\Column(name: 'content_id', type: 'uuid_text')]
        private readonly string $contentId,
        #[ORM\Column(type: 'date_immutable')]
        private readonly \DateTimeImmutable $day,
        #[ORM\Column(length: 20, enumType: Channel::class)]
        private readonly Channel $channel,
        #[ORM\Column(length: Arrival::MAX_SOURCE_LENGTH, nullable: true)]
        private readonly ?string $source,
        #[ORM\Column(length: 64, options: ['fixed' => true])]
        private readonly string $reader,
        #[ORM\Column(length: 10, enumType: Device::class)]
        private readonly Device $device,
        #[ORM\Column(length: 2, nullable: true, options: ['fixed' => true])]
        private readonly ?string $country,
    ) {}

    public static function record(VisitId $id, Content $content, Day $day, Arrival $arrival, Reader $reader): self
    {
        return new self(
            $id,
            $content->type(),
            $content->id()->value(),
            $day->date(),
            $arrival->channel(),
            $arrival->source(),
            $reader->hash(),
            $reader->device(),
            $reader->country(),
        );
    }

    public function save(VisitRepository $visitRepository): void
    {
        $visitRepository->save($this);
    }
}
