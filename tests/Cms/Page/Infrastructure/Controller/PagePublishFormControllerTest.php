<?php

declare(strict_types=1);

namespace Cms\Tests\Page\Infrastructure\Controller;

use Cms\Page\Domain\PageRepository;
use Cms\Page\Infrastructure\Controller\PagePublishFormController;
use Cms\Redirect\Domain\RedirectRepository;
use Cms\Tests\Page\Domain\PageIdMother;
use Cms\Tests\Page\Domain\PageMother;
use Cms\Tests\Redirect\Domain\RedirectMother;
use Cms\Tests\Shared\Infrastructure\CmsWebTestCase;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\HttpFoundation\Response;

/**
 * Where publishing a page is confirmed when it changes the redirects. What
 * it shows is told by PagePublishFormTest; here, when it is served.
 */
final class PagePublishFormControllerTest extends CmsWebTestCase
{
    private const string URL = 'http://cms.blogautista.localhost/pages/%s/publish';
    private const string ID = PageIdMother::DEFAULT_VALUE;

    public function testItRedirectsAnonymousVisitorsToTheLoginForm(): void
    {
        $client = self::createClient();

        $client->request('GET', \sprintf(self::URL, self::ID));

        self::assertResponseRedirects('http://cms.blogautista.localhost/login');
    }

    public function testItOpensTheConfirmationWhenPublishingChangesARedirect(): void
    {
        $client = $this->client(withRedirect: true);

        $client->request('GET', \sprintf(self::URL, self::ID));

        self::assertResponseIsSuccessful();
        self::assertRouteSame('cms_pages_publish_form', ['id' => self::ID]);
    }

    /**
     * What was refused waits for the form once: it is chosen again, then
     * forgotten.
     */
    public function testItTakesWhatWasRefusedOnce(): void
    {
        $client = $this->client(withRedirect: true);
        $this->post($client, 'cms_pages_publish', \sprintf(self::URL, self::ID), ['outlook' => 'stale']);
        self::assertNotNull($client->getRequest()->getSession()->get(\sprintf(PagePublishFormController::SAVED, self::ID)));

        $client->request('GET', \sprintf(self::URL, self::ID));

        self::assertResponseIsSuccessful();
        self::assertNull($client->getRequest()->getSession()->get(\sprintf(PagePublishFormController::SAVED, self::ID)));
    }

    /**
     * With nothing to confirm, the editor publishes it at once.
     */
    public function testItGoesBackToTheEditorWhenPublishingChangesNoRedirect(): void
    {
        $client = $this->client(withRedirect: false);

        $client->request('GET', \sprintf(self::URL, self::ID));

        self::assertResponseRedirects('/pages/'.self::ID, Response::HTTP_SEE_OTHER);
    }

    public function testItRespondsNotFoundWhenThePageDoesNotExist(): void
    {
        $client = self::createAuthenticatedClient();

        $client->request('GET', \sprintf(self::URL, PageIdMother::random()->value()));

        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    /**
     * A draft that publishing puts at /sobre-mi.html, where a redirect is,
     * if told.
     */
    private function client(bool $withRedirect): KernelBrowser
    {
        $client = self::createAuthenticatedClient();
        $client->disableReboot();
        $pages = self::getContainer()->get(PageRepository::class);
        $pages->save(PageMother::create(id: self::ID, title: 'Sobre mí', slug: 'sobre-mi', content: PageMother::CONTENT, metaDescription: PageMother::META_DESCRIPTION));

        if ($withRedirect) {
            $pages->save(PageMother::published(id: RedirectMother::PAGE, title: 'Otra', slug: 'otra'));
            self::getContainer()->get(RedirectRepository::class)->save(RedirectMother::create(source: '/sobre-mi.html'));
        }

        return $client;
    }
}
