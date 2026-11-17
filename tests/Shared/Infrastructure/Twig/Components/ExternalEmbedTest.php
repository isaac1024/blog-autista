<?php

declare(strict_types=1);

namespace Shared\Tests\Infrastructure\Twig\Components;

use PHPUnit\Framework\TestCase;
use Shared\Infrastructure\Twig\Components\ExternalEmbed;
use Shared\Tests\Infrastructure\Translation\CatalogueTranslator;

/**
 * What the facade of an embedded resource shows before it loads: a post
 * as its text, who wrote it and when; an audio by its show.
 */
final class ExternalEmbedTest extends TestCase
{
    public function testItShowsAPostAsItsTextWhoWroteItAndWhen(): void
    {
        $embed = self::embed('social_post', ['author' => 'Bluesky', 'handle' => '@bsky.app', 'posted_on' => '2024-10-17', 'text' => "Hola.\nQué tal.\n\nAdiós.", 'with_media' => true]);

        self::assertSame([
            'author' => 'Bluesky',
            'handle' => '@bsky.app',
            'date' => '17 de octubre de 2024',
            'day' => '2024-10-17',
            'paragraphs' => [['Hola.', 'Qué tal.'], ['Adiós.']],
            'withMedia' => true,
        ], $embed->post);
        self::assertNull($embed->show);
    }

    /**
     * What a post doesn't tell, it shows as nothing.
     */
    public function testItShowsNothingOfWhatAPostDoesNotTell(): void
    {
        $embed = self::embed('social_post', ['author' => null, 'handle' => null, 'posted_on' => 'ayer', 'text' => '']);

        self::assertSame(['author' => '', 'handle' => '', 'date' => null, 'day' => null, 'paragraphs' => [], 'withMedia' => false], $embed->post);
    }

    public function testItShowsAnAudioByItsShowAndNoPost(): void
    {
        $embed = self::embed('audio', ['show' => 'Un programa']);

        self::assertSame(['Un programa', null], [$embed->show, $embed->post]);
    }

    /**
     * Each facade has an id of its own, after its frame and how many came
     * before it on the page, so two of the same resource never share one.
     */
    public function testItGivesEachFacadeAnIdOfItsOwn(): void
    {
        $first = self::embed('video', []);
        $second = self::embed('video', []);

        self::assertMatchesRegularExpression('/^external-'.hash('crc32b', 'https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ').'-\d+$/', $first->id);
        self::assertNotSame($first->id, $second->id);
        self::assertSame(
            ['video', 'youtube', 'YouTube', 'https://youtu.be/dQw4w9WgXcQ', 'Un vídeo', 'es', 'https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ', 'https://www.youtube-nocookie.com', 'landscape', 'https://policies.google.com/privacy', '/cookies.html', null],
            [$first->kind, $first->provider, $first->providerLabel, $first->url, $first->title, $first->language, $first->embedUrl, $first->frameOrigin, $first->space, $first->privacyUrl, $first->cookiesUrl, $first->image],
        );
    }

    /**
     * @param array<string, mixed> $details
     */
    private static function embed(string $kind, array $details): ExternalEmbed
    {
        $translator = new CatalogueTranslator();
        $translator->setLocale('es');
        $embed = new ExternalEmbed($translator);
        $embed->mount($kind, 'youtube', 'YouTube', 'https://youtu.be/dQw4w9WgXcQ', 'Un vídeo', 'es', 'https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ', 'https://www.youtube-nocookie.com', 'landscape', 'https://policies.google.com/privacy', '/cookies.html', $details, null, false);

        return $embed;
    }
}
