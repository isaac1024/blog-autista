<?php

declare(strict_types=1);

namespace Web\Analytics\Domain;

use Doctrine\ORM\Mapping as ORM;

/**
 * How many pages were seen, and by how many readers of the day, on a day, by
 * one value of a dimension: what is left of the visits once counted, with
 * nothing that tells one reader from another.
 *
 * The database counts them, so the site only maps them, to keep the schema.
 * An unknown country is counted under an empty value.
 */
#[ORM\Entity(readOnly: true)]
#[ORM\Table(name: 'web_visit_total')]
final class VisitTotal
{
    #[ORM\Id]
    #[ORM\Column(type: 'date_immutable')]
    private readonly \DateTimeImmutable $day;
    #[ORM\Id]
    #[ORM\Column(length: 20, enumType: Dimension::class)]
    private readonly Dimension $dimension;
    #[ORM\Id]
    #[ORM\Column(length: 253)]
    private readonly string $value;
    #[ORM\Column]
    private readonly int $views;
    #[ORM\Column]
    private readonly int $visitors;
}
