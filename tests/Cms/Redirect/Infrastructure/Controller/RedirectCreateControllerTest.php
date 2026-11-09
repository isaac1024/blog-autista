<?php

declare(strict_types=1);

namespace Cms\Tests\Redirect\Infrastructure\Controller;

use Cms\Page\Domain\PageRepository;
use Cms\Redirect\Domain\Redirect;
use Cms\Redirect\Domain\RedirectRepository;
use Cms\Redirect\Domain\Source;
use Cms\Tests\Page\Domain\PageMother;
use Cms\Tests\Shared\Infrastructure\CmsWebTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use Shared\Infrastructure\Event\DomainEventMessage;
use Shared\Tests\Infrastructure\Event\QueuedDomainEvents;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\HttpFoundation\Response;

/**
 * A redirect is created from an absolute address of the site, written one
 * way only, to a resource on the site, or saying the address is gone; never
 * at an address the site answers itself, nor to anything off the site.
 */
final class RedirectCreateControllerTest extends CmsWebTestCase
{
    use QueuedDomainEvents;

    private const string URL = 'http://cms.blogautista.localhost/redirects';
    private const string SITE = 'https://www.blogautista.localhost';
    private const string PAGE = '0196f3a0-0000-7000-8000-0000000000a1';
    private const string OFF_SITE = '0196f3a0-0000-7000-8000-0000000000a2';

    public function testItCreatesARedirectToAPageAndTellsTheSite(): void
    {
        $client = $this->client();

        $this->post($client, 'cms_redirects_create', self::URL, ['source' => self::SITE.'/vieja.html', 'action' => 'page', 'page_id' => self::PAGE]);

        $redirect = $this->redirectAt('/vieja.html');
        self::assertResponseRedirects('/redirects/'.$redirect?->id()->value(), Response::HTTP_SEE_OTHER);
        self::assertSame(self::PAGE, $redirect?->target()->resource()?->id());
        $event = $this->lastEvent();
        self::assertSame('cms.redirect.saved', $event->name);
        self::assertSame(['source' => '/vieja.html', 'target_type' => 'page', 'target_id' => self::PAGE, 'origin_type' => null, 'origin_id' => null, 'revision' => 1], $event->body);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function writtenDifferently(): iterable
    {
        yield 'with accents and capitals' => [self::SITE.'/Guía Antigua.html'];
        yield 'encoded' => [self::SITE.'/gu%C3%ADa%20antigua.html'];
        yield 'through dot segments' => [self::SITE.'/blog/./../guía antigua.html'];
        yield 'with a query and a fragment' => [self::SITE.'/guía antigua.html?ref=boletin#inicio'];
    }

    /**
     * An address is kept as browsers ask for it, so its redirect is found
     * however it was written.
     */
    #[DataProvider('writtenDifferently')]
    public function testItKeepsTheAddressAsBrowsersAskForIt(string $url): void
    {
        $client = $this->client();

        $this->post($client, 'cms_redirects_create', self::URL, ['source' => $url, 'action' => 'gone']);

        self::assertNotNull($this->redirectAt('/gu%c3%ada%20antigua.html'));
    }

    /**
     * @return iterable<string, array{array<string, string>, string}>
     */
    public static function refused(): iterable
    {
        yield 'an address of another site' => [['source' => 'https://otra.web/vieja.html', 'action' => 'gone'], 'errors.site_path.other_host'];
        yield 'the home page' => [['source' => self::SITE.'/', 'action' => 'gone'], 'errors.site_path.root'];
        yield 'a page on the site' => [['source' => self::SITE.'/sobre-mi.html', 'action' => 'gone'], 'errors.redirect.live_source'];
        yield 'a page off the site' => [['source' => self::SITE.'/vieja.html', 'action' => 'page', 'page_id' => self::OFF_SITE], 'errors.redirect.target_off_site'];
        yield 'no page chosen' => [['source' => self::SITE.'/vieja.html', 'action' => 'page', 'page_id' => ''], 'errors.redirect.missing_target'];
        yield 'an image for the address of a page' => [['source' => self::SITE.'/vieja.html', 'action' => 'image', 'image_id' => self::PAGE], 'errors.redirect.other_to_image'];
        yield 'a page for the address of an image' => [['source' => self::SITE.'/media/2026/11/0196f3a0-0000-7000-8000-0000000000c1-800w.webp', 'action' => 'page', 'page_id' => self::PAGE], 'errors.redirect.image_to_other'];
        yield 'an address under /media/ of no image' => [['source' => self::SITE.'/media/logo.png', 'action' => 'gone'], 'errors.redirect.not_an_image_copy'];
    }

    /**
     * @param array<string, string> $form
     */
    #[DataProvider('refused')]
    public function testItGoesBackToTheFormWithTheAddressWrittenToTellWhy(array $form, string $error): void
    {
        $client = $this->client();

        $this->post($client, 'cms_redirects_create', self::URL, $form);

        self::assertResponseStatusCodeSame(Response::HTTP_SEE_OTHER);
        $location = parse_url((string) $client->getResponse()->headers->get('Location'));
        parse_str($location['query'] ?? '', $query);
        self::assertSame(['/redirects/new', $form['source']], [$location['path'] ?? null, $query['source'] ?? null]);
        self::assertSame([$error], self::errorKeys($client));
        self::assertSame([], $this->queuedEvents());
    }

    public function testItRefusesASecondRedirectAtTheSameAddress(): void
    {
        $client = $this->client();
        $this->post($client, 'cms_redirects_create', self::URL, ['source' => self::SITE.'/vieja.html', 'action' => 'gone']);

        $this->post($client, 'cms_redirects_create', self::URL, ['source' => self::SITE.'/Vieja.html', 'action' => 'page', 'page_id' => self::PAGE]);

        self::assertSame(['errors.redirect.source_taken'], self::errorKeys($client));
        self::assertNull($this->redirectAt('/vieja.html')?->target()->resource());
    }

    /**
     * Created at the last address of a page off the site, a redirect knows
     * that page as its origin, so what links to it follows the redirect.
     */
    public function testItKnowsThePageOffTheSiteWhoseLastAddressItAnswers(): void
    {
        $client = $this->client();

        $this->post($client, 'cms_redirects_create', self::URL, ['source' => self::SITE.'/retirada.html', 'action' => 'page', 'page_id' => self::PAGE]);

        self::assertSame(self::OFF_SITE, $this->redirectAt('/retirada.html')?->origin()->resource()?->id());
    }

    /**
     * A client whose kernel holds a page on the site, at /sobre-mi.html, and
     * one off it, last at /retirada.html.
     */
    private function client(): KernelBrowser
    {
        $client = self::createAuthenticatedClient();
        $client->disableReboot();
        $pages = self::getContainer()->get(PageRepository::class);
        $pages->save(PageMother::published(id: self::PAGE, title: 'Sobre mí', slug: 'sobre-mi'));
        $pages->save(PageMother::unpublished(id: self::OFF_SITE, title: 'Retirada', slug: 'retirada'));

        return $client;
    }

    private function redirectAt(string $path): ?Redirect
    {
        return self::getContainer()->get(RedirectRepository::class)->findBySource(Source::fromPath($path));
    }

    private function lastEvent(): DomainEventMessage
    {
        $events = $this->queuedEvents();
        self::assertNotSame([], $events);

        return $events[array_key_last($events)];
    }
}
