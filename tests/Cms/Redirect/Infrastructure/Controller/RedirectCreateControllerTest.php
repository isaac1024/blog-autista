<?php

declare(strict_types=1);

namespace Cms\Tests\Redirect\Infrastructure\Controller;

use Cms\Image\Domain\ImageRepository;
use Cms\Page\Domain\PageRepository;
use Cms\Redirect\Application\RedirectCreator;
use Cms\Redirect\Application\TargetInput;
use Cms\Redirect\Domain\Redirect;
use Cms\Redirect\Domain\RedirectId;
use Cms\Redirect\Domain\RedirectRepository;
use Cms\Redirect\Domain\SiteResources;
use Cms\Redirect\Domain\Source;
use Cms\Tests\Image\Domain\ImageMother;
use Cms\Tests\Page\Domain\PageMother;
use Cms\Tests\Shared\Infrastructure\CmsWebTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use Shared\Tests\Domain\RecordingMutualExclusion;
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
    private const string IMAGE = '0196f3a0-0000-7000-8000-0000000000c2';

    public function testItCreatesARedirectToAPage(): void
    {
        $client = $this->client();

        $this->post($client, 'cms_redirects_create', self::URL, ['source' => self::SITE.'/vieja.html', 'action' => 'page', 'page_id' => self::PAGE]);

        $redirect = $this->redirectAt('/vieja.html');
        self::assertResponseRedirects('/redirects/'.$redirect?->id()->value(), Response::HTTP_SEE_OTHER);
        self::assertSame(self::PAGE, $redirect?->target()->resource()?->id());
        self::assertNull($redirect->origin()->resource());
        // The site reads it as saved: nothing is queued for it.
        self::assertSame([], $this->queuedEvents());
        self::assertSame([['key' => 'cms.notices.redirect.created', 'parameters' => []]], self::successNotices($client));
    }

    public function testItRefusesARequestWithoutTheTokenOfItsForm(): void
    {
        $client = $this->client();

        $client->request('POST', self::URL, ['source' => self::SITE.'/vieja.html', 'action' => 'gone']);

        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
        self::assertNull($this->redirectAt('/vieja.html'));
    }

    /**
     * While it creates the redirect, it holds the address and the target, so
     * neither another redirect there nor taking the target off slips in.
     */
    public function testItHoldsTheAddressAndTheTargetWhileItCreatesTheRedirect(): void
    {
        $this->client();
        $mutualExclusion = new RecordingMutualExclusion();

        new RedirectCreator(RedirectId::random()->value(), self::SITE.'/vieja.html', new TargetInput('page', self::PAGE), 'www.blogautista.localhost')(
            self::getContainer()->get(RedirectRepository::class),
            self::getContainer()->get(SiteResources::class),
            $mutualExclusion,
        );

        self::assertSame(['address:/vieja.html', 'page:'.self::PAGE], $mutualExclusion->lastHeld());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function writtenDifferently(): iterable
    {
        yield 'with accents and capitals' => [self::SITE.'/Guía Antigua.html'];
        yield 'all in capitals, accents too' => [self::SITE.'/GUÍA ANTIGUA.html'];
        yield 'encoded' => [self::SITE.'/gu%C3%ADa%20antigua.html'];
        yield 'through dot segments' => [self::SITE.'/blog/./../guía antigua.html'];
        yield 'with a query and a fragment' => [self::SITE.'/guía antigua.html?ref=boletin#inicio'];
        yield 'with spaces around' => ['  '.self::SITE.'/guía antigua.html  '];
        yield 'with a scheme and a host in capitals' => ['HTTPS://WWW.BLOGAUTISTA.LOCALHOST/guía antigua.html'];
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
     * @return iterable<string, array{array<string, string>, array{key: string, parameters: array<string, int|string>}}>
     */
    public static function refused(): iterable
    {
        $longest = '/'.str_repeat('a', 249).'.html';

        yield 'an address of another site' => [['source' => 'https://otra.web/vieja.html', 'action' => 'gone'], ['key' => 'errors.site_path.other_host', 'parameters' => ['%url%' => 'https://otra.web/vieja.html', '%host%' => 'www.blogautista.localhost']]];
        yield 'an address with no host' => [['source' => '/vieja.html', 'action' => 'gone'], ['key' => 'errors.site_path.not_absolute', 'parameters' => ['%url%' => '/vieja.html']]];
        yield 'an address that is not one' => [['source' => 'http:///vieja.html', 'action' => 'gone'], ['key' => 'errors.site_path.not_absolute', 'parameters' => ['%url%' => 'http:///vieja.html']]];
        yield 'an address of no web' => [['source' => 'mailto:hola@blogautista.localhost', 'action' => 'gone'], ['key' => 'errors.site_path.not_absolute', 'parameters' => ['%url%' => 'mailto:hola@blogautista.localhost']]];
        yield 'the home page' => [['source' => self::SITE.'/', 'action' => 'gone'], ['key' => 'errors.site_path.root', 'parameters' => []]];
        yield 'an address too long' => [['source' => self::SITE.$longest.'x', 'action' => 'gone'], ['key' => 'errors.site_path.too_long', 'parameters' => ['%max%' => 255]]];
        yield 'a page on the site' => [['source' => self::SITE.'/sobre-mi.html', 'action' => 'gone'], ['key' => 'errors.redirect.live_source', 'parameters' => ['%source%' => '/sobre-mi.html']]];
        yield 'an image on the site' => [['source' => self::SITE.'/media/2026/11/'.self::IMAGE.'-400w.webp', 'action' => 'gone'], ['key' => 'errors.redirect.live_source', 'parameters' => ['%source%' => '/media/2026/11/'.self::IMAGE.'-1200w.webp']]];
        yield 'a page off the site' => [['source' => self::SITE.'/vieja.html', 'action' => 'page', 'page_id' => self::OFF_SITE], ['key' => 'errors.redirect.target_off_site', 'parameters' => []]];
        yield 'a page that does not exist' => [['source' => self::SITE.'/vieja.html', 'action' => 'page', 'page_id' => '0196f3a0-0000-7000-8000-0000000000ff'], ['key' => 'errors.redirect.target_off_site', 'parameters' => []]];
        yield 'an image that does not exist' => [['source' => self::SITE.'/media/2026/11/0196f3a0-0000-7000-8000-0000000000c1-800w.webp', 'action' => 'image', 'image_id' => '0196f3a0-0000-7000-8000-0000000000ff'], ['key' => 'errors.redirect.target_off_site', 'parameters' => []]];
        yield 'no page chosen' => [['source' => self::SITE.'/vieja.html', 'action' => 'page', 'page_id' => ''], ['key' => 'errors.redirect.missing_target', 'parameters' => []]];
        yield 'no choice at all' => [['source' => self::SITE.'/vieja.html'], ['key' => 'errors.redirect.missing_target', 'parameters' => []]];
        yield 'a kind of target there is none of' => [['source' => self::SITE.'/vieja.html', 'action' => 'script', 'page_id' => self::PAGE], ['key' => 'errors.redirect.unknown_target_type', 'parameters' => ['%type%' => 'script']]];
        yield 'an image for the address of a page' => [['source' => self::SITE.'/vieja.html', 'action' => 'image', 'image_id' => self::IMAGE], ['key' => 'errors.redirect.other_to_image', 'parameters' => []]];
        yield 'a page for the address of an image' => [['source' => self::SITE.'/media/2026/11/0196f3a0-0000-7000-8000-0000000000c1-800w.webp', 'action' => 'page', 'page_id' => self::PAGE], ['key' => 'errors.redirect.image_to_other', 'parameters' => []]];
        yield 'an address under /media/ of no image' => [['source' => self::SITE.'/media/logo.png', 'action' => 'gone'], ['key' => 'errors.redirect.not_an_image_copy', 'parameters' => ['%url%' => self::SITE.'/media/logo.png']]];
    }

    public function testItTakesAnAddressAsLongAsItCanBe(): void
    {
        $client = $this->client();
        $longest = '/'.str_repeat('a', 249).'.html';

        $this->post($client, 'cms_redirects_create', self::URL, ['source' => self::SITE.$longest, 'action' => 'gone']);

        self::assertNotNull($this->redirectAt($longest));
    }

    /**
     * A refused address that was given, as the last of a resource off the
     * site, stays given on the form it goes back to.
     */
    public function testItKeepsAGivenAddressGivenWhenItIsRefused(): void
    {
        $client = $this->client();

        $this->post($client, 'cms_redirects_create', self::URL, ['source' => self::SITE.'/retirada.html', 'fixed' => '1', 'action' => 'page', 'page_id' => '']);

        parse_str(parse_url((string) $client->getResponse()->headers->get('Location'))['query'] ?? '', $query);
        self::assertSame(['source' => self::SITE.'/retirada.html', 'fixed' => '1'], $query);
    }

    /**
     * @param array<string, string>                                     $form
     * @param array{key: string, parameters: array<string, int|string>} $error
     */
    #[DataProvider('refused')]
    public function testItGoesBackToTheFormWithTheAddressWrittenToTellWhy(array $form, array $error): void
    {
        $client = $this->client();

        $this->post($client, 'cms_redirects_create', self::URL, $form);

        self::assertResponseStatusCodeSame(Response::HTTP_SEE_OTHER);
        $location = parse_url((string) $client->getResponse()->headers->get('Location'));
        parse_str($location['query'] ?? '', $query);
        self::assertSame(['/redirects/new', $form['source']], [$location['path'] ?? null, $query['source'] ?? null]);
        self::assertSame([$error], self::errorNotices($client));
        self::assertSame([], $this->queuedEvents());
    }

    public function testItRefusesASecondRedirectAtTheSameAddress(): void
    {
        $client = $this->client();
        $this->post($client, 'cms_redirects_create', self::URL, ['source' => self::SITE.'/vieja.html', 'action' => 'gone']);

        $this->post($client, 'cms_redirects_create', self::URL, ['source' => self::SITE.'/Vieja.html', 'action' => 'page', 'page_id' => self::PAGE]);

        self::assertSame([['key' => 'errors.redirect.source_taken', 'parameters' => ['%source%' => '/vieja.html']]], self::errorNotices($client));
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
     * A client whose kernel holds a page on the site, at /sobre-mi.html, one
     * off it, last at /retirada.html, and an image on the site.
     */
    private function client(): KernelBrowser
    {
        $client = self::createAuthenticatedClient();
        $client->disableReboot();
        $pages = self::getContainer()->get(PageRepository::class);
        $pages->save(PageMother::published(id: self::PAGE, title: 'Sobre mí', slug: 'sobre-mi'));
        $pages->save(PageMother::unpublished(id: self::OFF_SITE, title: 'Retirada', slug: 'retirada'));
        self::getContainer()->get(ImageRepository::class)->save(ImageMother::published(id: self::IMAGE));

        return $client;
    }

    private function redirectAt(string $path): ?Redirect
    {
        return self::getContainer()->get(RedirectRepository::class)->findBySource(Source::fromPath($path));
    }
}
