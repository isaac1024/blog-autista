<?php

declare(strict_types=1);

namespace Cms\Tests\Page\Infrastructure\Controller;

use Cms\Page\Domain\PageRepository;
use Cms\Tests\Page\Domain\PageIdMother;
use Cms\Tests\Page\Domain\PageMother;
use Cms\Tests\Shared\Infrastructure\CmsWebTestCase;
use Symfony\Component\HttpFoundation\Response;

final class PageEditControllerTest extends CmsWebTestCase
{
    private const string URL = 'http://cms.blogautista.localhost/pages/%s';

    public function testItRedirectsAnonymousVisitorsToTheLoginForm(): void
    {
        $client = self::createClient();

        $client->request('GET', \sprintf(self::URL, PageIdMother::DEFAULT_VALUE));

        self::assertResponseRedirects('http://cms.blogautista.localhost/login');
    }

    public function testItOpensTheEditorOfAnExistingPage(): void
    {
        $client = self::createAuthenticatedClient();
        $id = PageIdMother::random()->value();
        self::getContainer()->get(PageRepository::class)->save(PageMother::create(id: $id, title: 'Sobre mí', slug: 'sobre-mi'));

        $client->request('GET', \sprintf(self::URL, $id));

        self::assertResponseIsSuccessful();
        self::assertRouteSame('cms_pages_edit', ['id' => $id]);
    }

    public function testItRespondsNotFoundWhenThePageDoesNotExist(): void
    {
        $client = self::createAuthenticatedClient();

        $client->request('GET', \sprintf(self::URL, PageIdMother::random()->value()));

        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    public function testItRespondsNotFoundWhenTheIdIsNotAUuid(): void
    {
        $client = self::createAuthenticatedClient();

        $client->request('GET', \sprintf(self::URL, 'sobre-mi'));

        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }
}
