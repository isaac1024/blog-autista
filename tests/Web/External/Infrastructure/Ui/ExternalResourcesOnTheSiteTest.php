<?php

declare(strict_types=1);

namespace Web\Tests\External\Infrastructure\Ui;

use Shared\Infrastructure\Event\DomainEventMessage;
use Shared\Infrastructure\Event\DomainEventMessageHandler;
use Shared\Infrastructure\Ui\ExternalComponents;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Web\Page\Domain\Page;
use Web\Page\Domain\PageRepository;
use Web\Page\Domain\PageType;
use Web\Post\Domain\CmsPostPublished;
use Web\Post\Domain\Post;
use Web\Post\Domain\PostRepository;
use Web\Redirect\Infrastructure\Resolution\SiteLinkedContents;
use Web\Settings\Infrastructure\Controller\SiteLayout;
use Web\Tests\External\Domain\CmsExternalResourcePublishedMother;
use Web\Tests\External\Domain\ExternalResourceIdMother;
use Web\Tests\Page\Domain\CmsPagePublishedMother;
use Web\Tests\Post\Domain\CmsPostPublishedMother;
use Web\Tests\Post\Domain\PostIdMother;

/**
 * External resources as readers meet them on the site: a text links them
 * and shows them, the facade of a video lets the page frame its provider
 * alone, and an article is a card; one unavailable, or off the site, is
 * painted nowhere, and its link is plain text.
 *
 * @phpstan-import-type ExternalView from ExternalComponents
 */
final class ExternalResourcesOnTheSiteTest extends WebTestCase
{
    private const string HOST = 'http://www.blogautista.localhost';

    private const string VIDEO = ExternalResourceIdMother::DEFAULT_VALUE;

    private const string ARTICLE = '01a1e2a0-0000-7000-8000-0000000000b2';

    private const string POST = '01a1e2a0-0000-7000-8000-0000000000b3';

    private const string OFF_SITE = '01a1e2a0-0000-7000-8000-0000000000b9';

    #[\Override]
    protected function setUp(): void
    {
        $file = imagecreatetruecolor(1200, 675);
        self::assertNotFalse($file);
        $originals = self::getContainer()->getParameter('web.external.originals_directory');
        new \Symfony\Component\Filesystem\Filesystem()->mkdir($originals);
        imagewebp($file, $originals.'/'.CmsExternalResourcePublishedMother::IMAGE);
        self::ensureKernelShutdown();
    }

    #[\Override]
    protected function tearDown(): void
    {
        foreach (['web.external.originals_directory', 'web.external.public_directory'] as $parameter) {
            $directory = self::getContainer()->getParameter($parameter);

            if (str_contains($directory, '/var/test/')) {
                new \Symfony\Component\Filesystem\Filesystem()->remove($directory);
            }
        }

        parent::tearDown();
    }

    public function testItLetsAPageFrameTheProvidersOfTheResourcesItEmbedsAlone(): void
    {
        $client = self::createClient();
        $this->publish(self::VIDEO, ['image' => null, 'image_shape' => null] + CmsExternalResourcePublishedMother::body());
        $this->publish(self::POST, ['kind' => 'social_post', 'provider' => 'mastodon', 'locator' => 'mastodon.social/Gargron/1', 'url' => 'https://mastodon.social/@Gargron/1', 'image' => null, 'image_shape' => null] + CmsExternalResourcePublishedMother::body());
        $this->publish(self::ARTICLE, self::article());
        $this->page(self::widget(self::VIDEO), self::widget(self::POST), self::widget(self::ARTICLE));

        $client->request('GET', self::HOST.'/sobre-mi.html');

        self::assertResponseIsSuccessful();
        $policy = (string) $client->getResponse()->headers->get('Content-Security-Policy');
        $framing = array_values(array_filter(array_map('trim', explode(';', $policy)), static fn (string $directive): bool => str_contains($directive, 'https://')));
        // Only framed: the page runs, styles and shows nothing of theirs.
        self::assertSame(['frame-src https://www.youtube-nocookie.com https://mastodon.social'], $framing);
    }

    public function testItLetsAPostFrameTheProvidersOfTheResourcesItEmbeds(): void
    {
        $client = self::createClient();
        $this->publish(self::VIDEO, ['image' => null, 'image_shape' => null] + CmsExternalResourcePublishedMother::body());
        self::getContainer()->get(PostRepository::class)->save(Post::publish(CmsPostPublished::fromPrimitives(
            PostIdMother::DEFAULT_VALUE,
            ['content' => ['version' => 1, 'blocks' => [self::widget(self::VIDEO)]]] + CmsPostPublishedMother::body() + ['revision' => 1],
        )));

        $client->request('GET', self::HOST.'/vida-diaria/2026/11/07/mi-primer-dia.html');

        self::assertResponseIsSuccessful();
        self::assertStringContainsString('frame-src https://www.youtube-nocookie.com', (string) $client->getResponse()->headers->get('Content-Security-Policy'));
    }

    public function testItFramesNothingOnAPageThatEmbedsNothing(): void
    {
        $client = self::createClient();
        $this->publish(self::ARTICLE, self::article());
        $this->page(self::widget(self::ARTICLE));

        $client->request('GET', self::HOST.'/sobre-mi.html');

        self::assertResponseIsSuccessful();
        self::assertStringNotContainsString('frame-src', (string) $client->getResponse()->headers->get('Content-Security-Policy'));
    }

    /**
     * What each widget paints, and where each link leads: the facade of a
     * video, with all it needs to load it; the card of an article; and
     * nothing of one unavailable or off the site.
     */
    public function testItPaintsEachResourceAsWhatItIs(): void
    {
        $this->publish(self::VIDEO, CmsExternalResourcePublishedMother::body());
        $this->publish(self::ARTICLE, self::article());
        $this->publish(self::POST, ['kind' => 'social_post', 'provider' => 'bluesky', 'locator' => 'did:plc:abc/3l6', 'url' => 'https://bsky.app/profile/did:plc:abc/post/3l6', 'image' => null, 'image_shape' => null] + CmsExternalResourcePublishedMother::body());
        $this->dispatch('cms.external.availability_changed', self::POST, ['available' => false, 'revision' => 2]);
        $content = \Shared\Domain\RichText\RichText::fromArray(['version' => 1, 'blocks' => [
            self::widget(self::VIDEO),
            self::widget(self::ARTICLE),
            self::widget(self::POST),
            ['type' => 'paragraph', 'text' => [['text' => 'fuera', 'marks' => ['link'], 'external_id' => self::OFF_SITE]]],
        ]]);

        $linked = self::getContainer()->get(SiteLinkedContents::class)($content, [], null);

        self::assertSame([self::VIDEO, self::ARTICLE], array_keys($linked));
        $video = self::component($linked, self::VIDEO);
        self::assertSame('Shared:ExternalEmbed', $video->name);
        self::assertSame([
            'youtube',
            'YouTube',
            'https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ?cc_load_policy=1&cc_lang_pref=es&hl=es&rel=0',
            'https://www.youtube-nocookie.com',
            'landscape',
            null,
            '/media/externos/01a1e2a0-0000-7000-8000-0000000000a1-1200w.webp',
        ], [$video->props['provider'], $video->props['providerLabel'], $video->props['embedUrl'], $video->props['frameOrigin'], $video->props['space'], $video->props['cookiesUrl'], \is_array($video->props['image']) ? $video->props['image']['src'] : null]);
        $article = self::component($linked, self::ARTICLE);
        self::assertSame(['Shared:ContentExternal', 'https://www.example.org/noticia.html', 'Una noticia'], [$article->name, $article->props['url'], $article->props['title']]);
    }

    /**
     * The notice of a facade leads to the cookie policy of the site, while
     * it is on it.
     */
    public function testItLeadsTheNoticeToTheCookiePolicy(): void
    {
        self::getContainer()->get(PageRepository::class)->save(Page::publish(CmsPagePublishedMother::create(id: '01a1e2a0-0000-7000-8000-0000000000c1', title: 'Cookies', slug: 'cookies', type: PageType::CookiePolicy)));
        $this->publish(self::VIDEO, CmsExternalResourcePublishedMother::body());

        $linked = self::getContainer()->get(SiteLinkedContents::class)(\Shared\Domain\RichText\RichText::fromArray(['version' => 1, 'blocks' => [self::widget(self::VIDEO)]]), [], null);

        self::assertSame('/cookies.html', self::component($linked, self::VIDEO)->props['cookiesUrl']);
    }

    /**
     * A menu leads to the resource while it is on the site and available,
     * by its label or else by its title.
     */
    public function testItLeadsTheMenusToTheResourcesOnTheSite(): void
    {
        $this->publish(self::ARTICLE, self::article());
        $this->publish(self::VIDEO, ['image' => null, 'image_shape' => null] + CmsExternalResourcePublishedMother::body());
        $this->dispatch('cms.external.availability_changed', self::VIDEO, ['available' => false, 'revision' => 2]);
        $this->dispatch('cms.settings.published', '01a1e2a0-0000-7000-8000-0000000000e1', [
            'name' => 'Blog autista',
            'default_image_id' => null,
            'main_menu' => [['external_id' => self::ARTICLE, 'label' => null], ['external_id' => self::VIDEO, 'label' => 'Un vídeo']],
            'footer_menu' => [['external_id' => self::ARTICLE, 'label' => 'La noticia'], ['external_id' => self::OFF_SITE, 'label' => null]],
            'revision' => 1,
        ]);

        $page = self::getContainer()->get(SiteLayout::class)->page('Sobre mí');

        self::assertSame(
            [[['label' => 'Una noticia', 'url' => 'https://www.example.org/noticia.html']], [['label' => 'La noticia', 'url' => 'https://www.example.org/noticia.html']]],
            [$page->header[0]->props['links'], $page->footer[0]->props['links']],
        );
    }

    /**
     * What paints the resource of the id, which the texts link to.
     *
     * @param array<string, array{url: string, title: string, external?: ExternalView}> $linked
     */
    private static function component(array $linked, string $id): \Shared\Infrastructure\Ui\Page\PageComponent
    {
        $external = $linked[$id]['external'] ?? null;
        self::assertNotNull($external);

        return ExternalComponents::component($external, false);
    }

    /**
     * @return array<string, mixed>
     */
    private static function article(): array
    {
        return ['kind' => 'article', 'provider' => null, 'locator' => null, 'url' => 'https://www.example.org/noticia.html', 'title' => 'Una noticia', 'image' => null, 'image_shape' => null, 'details' => ['publisher' => 'El Medio', 'published_on' => '2026-11-01']] + CmsExternalResourcePublishedMother::body();
    }

    /**
     * @return array<string, string>
     */
    private static function widget(string $id): array
    {
        return ['type' => 'widget', 'kind' => 'related_page', 'external_id' => $id];
    }

    /**
     * @param array<string, string> ...$blocks
     */
    private function page(array ...$blocks): void
    {
        self::getContainer()->get(PageRepository::class)->save(Page::publish(CmsPagePublishedMother::create(content: ['version' => 1, 'blocks' => $blocks])));
    }

    /**
     * @param array<string, mixed> $body
     */
    private function publish(string $id, array $body): void
    {
        $this->dispatch('cms.external.published', $id, $body);
    }

    /**
     * @param array<string, mixed> $body
     */
    private function dispatch(string $name, string $id, array $body): void
    {
        self::getContainer()->get(DomainEventMessageHandler::class)(new DomainEventMessage($name, $id, $body));
    }
}
