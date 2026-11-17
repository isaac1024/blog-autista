<?php

declare(strict_types=1);

namespace Shared\Infrastructure\Twig\Components;

use Shared\Domain\SiteTimeZone;
use Symfony\Contracts\Translation\TranslatorInterface;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

/**
 * A page, a post, a section, a tag or an author of the blog that written
 * content points to, as a card of related content that leads there, telling
 * what it is: a post by its section and the day it was first published,
 * with its opening image; an author with their avatar; any of them with
 * what it says of itself to search engines.
 *
 * Its props are declared in mount(), as for every component, so it takes as
 * many parameters as it has props.
 *
 * @SuppressWarnings("PHPMD.ExcessiveParameterList")
 */
#[AsTwigComponent]
final class ContentRelated
{
    public string $kind = '';

    public string $url = '';

    public string $title = '';

    public ?string $summary = null;

    /**
     * @var ?array{src: string, srcset: string, width: int, height: int}
     */
    public ?array $image = null;

    /**
     * Where it is in the blog, as the line on top says it: a post's section
     * and day.
     *
     * @var list<string>
     */
    public array $source = [];

    /**
     * Whether its picture is the first of what the page shows, which the
     * browser fetches at once rather than once it nears the screen.
     */
    public bool $lead = false;

    public function __construct(private readonly TranslatorInterface $translator) {}

    /**
     * @param ?array{src: string, srcset: string, width: int, height: int} $image
     * @param ?string                                                      $section   the name of the section of a post
     * @param ?\DateTimeImmutable                                          $published the moment a post was first published
     */
    public function mount(string $kind, string $url, string $title, ?string $summary, ?array $image, ?string $section, ?\DateTimeImmutable $published, bool $lead): void
    {
        $this->kind = $kind;
        $this->url = $url;
        $this->title = $title;
        $this->summary = $summary;
        $this->image = $image;
        $this->lead = $lead;
        $this->source = array_values(array_filter([
            $section,
            null === $published ? null : (string) new \IntlDateFormatter($this->translator->getLocale(), \IntlDateFormatter::LONG, \IntlDateFormatter::NONE, SiteTimeZone::NAME)->format($published),
        ], static fn (?string $part): bool => null !== $part && '' !== $part));
    }
}
