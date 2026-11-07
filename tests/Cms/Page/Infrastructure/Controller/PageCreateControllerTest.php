<?php

declare(strict_types=1);

namespace Cms\Tests\Page\Infrastructure\Controller;

use Cms\Page\Domain\PageRepository;
use Cms\Tests\Page\Domain\PageIdMother;
use Cms\Tests\Page\Domain\PageMother;
use Cms\Tests\Shared\Infrastructure\CmsWebTestCase;
use Symfony\Component\HttpFoundation\Response;

final class PageCreateControllerTest extends CmsWebTestCase
{
    public function testItRespondsSeeOtherWhenThePageIsCreated(): void
    {
        $client = self::createAuthenticatedClient();

        $client->request('POST', 'http://cms.blogautista.localhost/pages', ['title' => 'Posts']);

        self::assertResponseStatusCodeSame(Response::HTTP_SEE_OTHER);
    }

    public function testItGoesBackWhenTheTitleIsInvalid(): void
    {
        $client = self::createAuthenticatedClient();

        $client->request('POST', 'http://cms.blogautista.localhost/pages', ['title' => ' ']);

        self::assertResponseRedirects('http://cms.blogautista.localhost/pages', Response::HTTP_SEE_OTHER);
    }

    public function testItGoesBackWhenAnotherPageHasTheSlugDerivedFromTheTitle(): void
    {
        $client = self::createAuthenticatedClient();
        self::getContainer()->get(PageRepository::class)->save(
            PageMother::create(id: PageIdMother::random()->value(), title: 'Sobre mí', slug: 'sobre-mi'),
        );

        $client->request('POST', 'http://cms.blogautista.localhost/pages', ['title' => 'Sobre mí']);

        self::assertResponseRedirects('http://cms.blogautista.localhost/pages', Response::HTTP_SEE_OTHER);
    }
}
