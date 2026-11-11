<?php

declare(strict_types=1);

namespace Cms\Tests\Redirect\Infrastructure\Controller;

use Cms\Page\Domain\PageRepository;
use Cms\Redirect\Domain\Redirect;
use Cms\Redirect\Domain\RedirectRepository;
use Cms\Redirect\Domain\Resource;
use Cms\Tests\Page\Domain\PageMother;
use Cms\Tests\Redirect\Domain\RedirectIdMother;
use Cms\Tests\Redirect\Domain\RedirectMother;
use Cms\Tests\Shared\Infrastructure\CmsWebTestCase;
use Shared\Tests\Infrastructure\Event\QueuedDomainEvents;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\HttpFoundation\Response;

/**
 * A redirect leads somewhere else from where it is edited. The site reads it
 * as saved: nothing is queued for it. What it refuses is told by
 * RedirectRetargeterTest.
 */
final class RedirectUpdateControllerTest extends CmsWebTestCase
{
    use QueuedDomainEvents;

    private const string URL = 'http://cms.blogautista.localhost/redirects/%s';
    private const string REDIRECT = RedirectIdMother::DEFAULT_VALUE;
    private const string OTHER = '0196f3a0-0000-7000-8000-0000000000b1';

    public function testItRedirectsAnonymousVisitorsToTheLoginForm(): void
    {
        $client = self::createClient();

        $this->post($client, 'cms_redirects_update', \sprintf(self::URL, self::REDIRECT), ['action' => 'gone']);

        self::assertResponseRedirects('http://cms.blogautista.localhost/login');
    }

    public function testItLeadsTheRedirectSomewhereElseAndStaysInTheEditor(): void
    {
        $client = $this->client();

        $this->post($client, 'cms_redirects_update', \sprintf(self::URL, self::REDIRECT), ['action' => 'page', 'page_id' => self::OTHER]);

        self::assertResponseRedirects('/redirects/'.self::REDIRECT, Response::HTTP_SEE_OTHER);
        self::assertSame([['key' => 'cms.notices.redirect.saved', 'parameters' => []]], self::successNotices($client));
        self::assertSame(self::OTHER, $this->stored()?->target()->resource()?->id());
        self::assertSame([], $this->queuedEvents());
    }

    public function testItGoesBackToTheEditorToTellWhyAndChangesNothing(): void
    {
        $client = $this->client();

        $this->post($client, 'cms_redirects_update', \sprintf(self::URL, self::REDIRECT), ['action' => 'page', 'page_id' => RedirectMother::OFF_SITE]);

        self::assertResponseRedirects('http://cms.blogautista.localhost/redirects/'.self::REDIRECT, Response::HTTP_SEE_OTHER);
        self::assertSame([['key' => 'errors.redirect.target_off_site', 'parameters' => []]], self::errorNotices($client));
        self::assertSame([], self::successNotices($client));
        self::assertSame(RedirectMother::PAGE, $this->stored()?->target()->resource()?->id());
    }

    public function testItRefusesARequestWithoutTheTokenOfItsForm(): void
    {
        $client = $this->client();

        $client->request('POST', \sprintf(self::URL, self::REDIRECT), ['action' => 'gone']);

        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
        self::assertSame(RedirectMother::PAGE, $this->stored()?->target()->resource()?->id());
    }

    public function testItRespondsNotFoundWhenTheRedirectDoesNotExist(): void
    {
        $client = self::createAuthenticatedClient();

        $this->post($client, 'cms_redirects_update', \sprintf(self::URL, RedirectIdMother::random()->value()), ['action' => 'gone']);

        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    /**
     * A client whose kernel holds two pages on the site, one off it, and the
     * redirect from /vieja.html to the first.
     */
    private function client(): KernelBrowser
    {
        $client = self::createAuthenticatedClient();
        $client->disableReboot();
        $pages = self::getContainer()->get(PageRepository::class);
        $pages->save(PageMother::published(id: RedirectMother::PAGE, title: 'Una', slug: 'una'));
        $pages->save(PageMother::published(id: self::OTHER, title: 'Otra', slug: 'otra'));
        $pages->save(PageMother::unpublished(id: RedirectMother::OFF_SITE, title: 'Retirada', slug: 'retirada'));
        self::getContainer()->get(RedirectRepository::class)->save(RedirectMother::create(id: self::REDIRECT, target: Resource::page(RedirectMother::PAGE)));

        return $client;
    }

    private function stored(): ?Redirect
    {
        return self::getContainer()->get(RedirectRepository::class)->find(RedirectIdMother::create(self::REDIRECT));
    }
}
