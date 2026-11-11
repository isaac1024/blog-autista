<?php

declare(strict_types=1);

namespace Cms\Tests\Page\Infrastructure\Controller;

use Cms\Page\Domain\PageRepository;
use Cms\Page\Infrastructure\Controller\PageUnpublishFormController;
use Cms\Tests\Page\Domain\PageIdMother;
use Cms\Tests\Page\Domain\PageMother;
use Cms\Tests\Shared\Infrastructure\CmsWebTestCase;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\HttpFoundation\Response;

/**
 * Where a page is taken off the site. What it shows is told by
 * PageUnpublishFormTest; here, when it is served.
 */
final class PageUnpublishFormControllerTest extends CmsWebTestCase
{
    private const string URL = 'http://cms.blogautista.localhost/pages/%s/unpublish';
    private const string ID = PageIdMother::DEFAULT_VALUE;

    public function testItRedirectsAnonymousVisitorsToTheLoginForm(): void
    {
        $client = self::createClient();

        $client->request('GET', \sprintf(self::URL, self::ID));

        self::assertResponseRedirects('http://cms.blogautista.localhost/login');
    }

    public function testItOpensTheFormThatTakesItOff(): void
    {
        $client = $this->client(onSite: true);

        $client->request('GET', \sprintf(self::URL, self::ID));

        self::assertResponseIsSuccessful();
        self::assertRouteSame('cms_pages_unpublish_form', ['id' => self::ID]);
    }

    /**
     * What was refused waits for the form once: it is chosen again, then
     * forgotten.
     */
    public function testItTakesWhatWasRefusedOnce(): void
    {
        $client = $this->client(onSite: true);
        $this->post($client, 'cms_pages_unpublish', \sprintf(self::URL, self::ID), ['address' => ['action' => '']]);
        self::assertNotNull($client->getRequest()->getSession()->get(\sprintf(PageUnpublishFormController::SAVED, self::ID)));

        $client->request('GET', \sprintf(self::URL, self::ID));

        self::assertResponseIsSuccessful();
        self::assertNull($client->getRequest()->getSession()->get(\sprintf(PageUnpublishFormController::SAVED, self::ID)));
    }

    public function testItGoesBackToTheEditorToTellWhyWhenItIsNotOnTheSite(): void
    {
        $client = $this->client(onSite: false);

        $client->request('GET', \sprintf(self::URL, self::ID));

        self::assertResponseRedirects('/pages/'.self::ID, Response::HTTP_SEE_OTHER);
        self::assertSame([['key' => 'errors.page.publication.not_published', 'parameters' => []]], self::errorNotices($client));
    }

    public function testItRespondsNotFoundWhenTheResourceDoesNotExist(): void
    {
        $client = self::createAuthenticatedClient();

        $client->request('GET', \sprintf(self::URL, PageIdMother::random()->value()));

        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    private function client(bool $onSite): KernelBrowser
    {
        $client = self::createAuthenticatedClient();
        $client->disableReboot();
        self::getContainer()->get(PageRepository::class)->save($onSite ? PageMother::published(id: self::ID, title: 'Sobre mí', slug: 'sobre-mi') : PageMother::create(id: self::ID));

        return $client;
    }
}
