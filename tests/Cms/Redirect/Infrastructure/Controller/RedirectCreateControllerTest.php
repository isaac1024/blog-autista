<?php

declare(strict_types=1);

namespace Cms\Tests\Redirect\Infrastructure\Controller;

use Cms\Image\Domain\ImageRepository;
use Cms\Page\Domain\PageRepository;
use Cms\Redirect\Domain\Redirect;
use Cms\Redirect\Domain\RedirectRepository;
use Cms\Redirect\Domain\Source;
use Cms\Tests\Image\Domain\ImageMother;
use Cms\Tests\Page\Domain\PageMother;
use Cms\Tests\Shared\Infrastructure\CmsWebTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use Shared\Tests\Infrastructure\Event\QueuedDomainEvents;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\HttpFoundation\Response;

/**
 * What a redirect is created with, and why one is refused, is told by
 * RedirectCreatorTest; here, how the form is answered: where it goes once
 * created or refused, with what it tells, and only with its token.
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
     * Why each redirect is refused is told by RedirectCreatorTest; here, one
     * refused for its address and one for where it leads, as each goes back.
     *
     * @return iterable<string, array{array<string, string>, array{key: string, parameters: array<string, int|string>}}>
     */
    public static function refused(): iterable
    {
        yield 'a page on the site' => [['source' => self::SITE.'/sobre-mi.html', 'action' => 'gone'], ['key' => 'errors.redirect.live_source', 'parameters' => ['%source%' => '/sobre-mi.html']]];
        yield 'no choice at all' => [['source' => self::SITE.'/vieja.html'], ['key' => 'errors.redirect.missing_target', 'parameters' => []]];
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
