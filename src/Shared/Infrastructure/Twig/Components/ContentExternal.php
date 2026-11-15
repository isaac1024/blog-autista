<?php

declare(strict_types=1);

namespace Shared\Infrastructure\Twig\Components;

use Symfony\Contracts\Translation\TranslatorInterface;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

/**
 * An article, a study or a document of another site that written content
 * points to, as a card of related content that leads there, telling where
 * it is from: the outlet and the day of an article, the journal, the year
 * and the authors of a study, how heavy a PDF is and how many pages it has.
 *
 * Its props are declared in mount(), as for every component, so it takes as
 * many parameters as it has props.
 *
 * @SuppressWarnings("PHPMD.ExcessiveParameterList")
 */
#[AsTwigComponent]
final class ContentExternal
{
    public string $kind = '';

    public string $url = '';

    public string $title = '';

    public ?string $summary = null;

    public string $language = '';

    /**
     * Where it is from, each part apart, such as the journal and the year.
     *
     * @var list<string>
     */
    public array $source = [];

    /**
     * @var ?array{src: string, srcset: string, width: int, height: int}
     */
    public ?array $image = null;

    public function __construct(private readonly TranslatorInterface $translator) {}

    /**
     * @param array<array-key, mixed>                                      $details as the resource tells them
     * @param ?array{src: string, srcset: string, width: int, height: int} $image
     */
    public function mount(string $kind, string $url, string $title, ?string $summary, string $language, array $details, ?array $image): void
    {
        $this->kind = $kind;
        $this->url = $url;
        $this->title = $title;
        $this->summary = $summary;
        $this->language = $language;
        $this->image = $image;
        $this->source = array_values(array_filter(match ($kind) {
            'study' => [self::text($details, 'journal'), \is_int($details['year'] ?? null) ? (string) $details['year'] : null, self::text($details, 'authors')],
            'document' => [
                self::text($details, 'publisher'),
                \is_int($details['bytes'] ?? null) ? $this->translator->trans('content.external.size', ['%size%' => self::megabytes($details['bytes'], $this->translator->getLocale())]) : null,
                \is_int($details['pages'] ?? null) ? $this->translator->trans('content.external.pages', ['%count%' => $details['pages']]) : null,
            ],
            default => [self::text($details, 'publisher') ?? self::host($url), $this->day($details)],
        }, static fn (?string $part): bool => null !== $part));
    }

    /**
     * @param array<array-key, mixed> $details
     */
    private function day(array $details): ?string
    {
        $day = \is_string($details['published_on'] ?? null) ? \DateTimeImmutable::createFromFormat('!Y-m-d', $details['published_on']) : false;

        return false === $day ? null : (string) new \IntlDateFormatter($this->translator->getLocale(), \IntlDateFormatter::LONG, \IntlDateFormatter::NONE)->format($day);
    }

    /**
     * @param array<array-key, mixed> $details
     */
    private static function text(array $details, string $key): ?string
    {
        return \is_string($details[$key] ?? null) && '' !== $details[$key] ? $details[$key] : null;
    }

    private static function host(string $url): ?string
    {
        $host = parse_url($url, \PHP_URL_HOST);

        return \is_string($host) ? (string) preg_replace('/^www\./', '', $host) : null;
    }

    /**
     * The size in megabytes, with one decimal, as the reader writes numbers.
     */
    private static function megabytes(int $bytes, string $locale): string
    {
        $formatter = new \NumberFormatter($locale, \NumberFormatter::DECIMAL);
        $formatter->setAttribute(\NumberFormatter::MAX_FRACTION_DIGITS, 1);
        $formatter->setAttribute(\NumberFormatter::MIN_FRACTION_DIGITS, 1);

        return (string) $formatter->format(max($bytes, 100_000) / 1_000_000);
    }
}
