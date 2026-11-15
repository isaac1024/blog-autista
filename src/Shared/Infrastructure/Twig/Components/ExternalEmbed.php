<?php

declare(strict_types=1);

namespace Shared\Infrastructure\Twig\Components;

use Symfony\Contracts\Translation\TranslatorInterface;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

/**
 * A video, a post or an audio of another site, behind a facade that holds
 * its room and loads nothing of its provider until the reader agrees: the
 * facade shows its image, or what a post says, a notice tells what loading
 * it means before it loads, and a line below it tells whose it is and how
 * to load it, or to stop loading it on its own. Without scripts, it leads
 * to the original.
 *
 * Its props are declared in mount(), as for every component, so it takes as
 * many parameters as it has props.
 *
 * @SuppressWarnings("PHPMD.ExcessiveParameterList")
 * @SuppressWarnings("PHPMD.TooManyFields")
 */
#[AsTwigComponent]
final class ExternalEmbed
{
    /**
     * What its notice is known by on the page, unique even when a page
     * embeds the same resource twice.
     */
    public string $id = '';

    public string $kind = '';

    public string $provider = '';

    public string $providerLabel = '';

    public string $url = '';

    public string $title = '';

    public string $language = '';

    public string $embedUrl = '';

    public string $frameOrigin = '';

    /**
     * One of the EmbedSpace values, which says the room it keeps.
     */
    public string $space = '';

    public string $privacyUrl = '';

    public ?string $cookiesUrl = null;

    /**
     * @var ?array{src: string, srcset: string, width: int, height: int}
     */
    public ?array $image = null;

    /**
     * What a post says, a paragraph each, with who wrote it, the account and
     * the day, as the reader reads them; null for anything but a post.
     *
     * @var ?array{author: string, handle: string, date: ?string, day: ?string, paragraphs: list<list<string>>, withMedia: bool}
     */
    public ?array $post = null;

    public ?string $show = null;

    /**
     * How many facades were painted so far, so each has an id of its own.
     */
    private static int $painted = 0;

    public function __construct(private readonly TranslatorInterface $translator) {}

    /**
     * @param ?array{src: string, srcset: string, width: int, height: int} $image
     * @param array<array-key, mixed>                                      $details as the resource tells them
     */
    public function mount(
        string $kind,
        string $provider,
        string $providerLabel,
        string $url,
        string $title,
        string $language,
        string $embedUrl,
        string $frameOrigin,
        string $space,
        string $privacyUrl,
        ?string $cookiesUrl,
        array $details,
        ?array $image,
    ): void {
        $this->id = \sprintf('external-%s-%d', hash('crc32b', $embedUrl), ++self::$painted);
        $this->kind = $kind;
        $this->provider = $provider;
        $this->providerLabel = $providerLabel;
        $this->url = $url;
        $this->title = $title;
        $this->language = $language;
        $this->embedUrl = $embedUrl;
        $this->frameOrigin = $frameOrigin;
        $this->space = $space;
        $this->privacyUrl = $privacyUrl;
        $this->cookiesUrl = $cookiesUrl;
        $this->image = $image;
        $this->show = \is_string($details['show'] ?? null) ? $details['show'] : null;

        $this->post = 'social_post' === $kind ? $this->post($details) : null;
    }

    /**
     * What a post says, and who wrote it when, as the reader reads them.
     *
     * @param array<array-key, mixed> $details
     *
     * @return array{author: string, handle: string, date: ?string, day: ?string, paragraphs: list<list<string>>, withMedia: bool}
     */
    private function post(array $details): array
    {
        $day = \is_string($details['posted_on'] ?? null) ? \DateTimeImmutable::createFromFormat('!Y-m-d', $details['posted_on']) : false;
        $text = \is_string($details['text'] ?? null) ? $details['text'] : '';
        $paragraphs = '' === $text ? false : preg_split("/\n{2,}/", $text);

        return [
            'author' => \is_string($details['author'] ?? null) ? $details['author'] : '',
            'handle' => \is_string($details['handle'] ?? null) ? $details['handle'] : '',
            'date' => false === $day ? null : (string) new \IntlDateFormatter($this->translator->getLocale(), \IntlDateFormatter::LONG, \IntlDateFormatter::NONE)->format($day),
            'day' => false === $day ? null : $day->format('Y-m-d'),
            // Paragraphs apart by a blank line, lines apart within them.
            'paragraphs' => array_map(static fn (string $paragraph): array => explode("\n", $paragraph), false === $paragraphs ? [] : $paragraphs),
            'withMedia' => true === ($details['with_media'] ?? false),
        ];
    }
}
