<?php

declare(strict_types=1);

namespace Cms\Tests\Page\Infrastructure\Controller;

use Cms\Page\Domain\PageRepository;
use Cms\Page\Domain\Slug;
use Cms\Tests\Page\Domain\PageIdMother;
use Cms\Tests\Page\Domain\PageMother;
use Cms\Tests\Shared\Infrastructure\CmsWebTestCase;
use Symfony\Component\HttpFoundation\Response;

final class PageCreateControllerTest extends CmsWebTestCase
{
    public function testItCreatesThePageAndOpensItInTheEditor(): void
    {
        $client = self::createAuthenticatedClient();

        $this->post($client, 'cms_pages_create', 'http://cms.blogautista.localhost/pages', ['title' => 'Posts']);

        $page = self::getContainer()->get(PageRepository::class)->findBySlug(new Slug('posts'));
        self::assertNotNull($page);
        self::assertResponseRedirects('/pages/'.$page->id()->value(), Response::HTTP_SEE_OTHER);
        self::assertSame([['key' => 'cms.notices.page.created', 'parameters' => []]], self::successNotices($client));
    }

    public function testItGoesBackWhenTheTitleIsInvalid(): void
    {
        $client = self::createAuthenticatedClient();

        $this->post($client, 'cms_pages_create', 'http://cms.blogautista.localhost/pages', ['title' => ' ']);

        self::assertResponseRedirects('http://cms.blogautista.localhost/pages', Response::HTTP_SEE_OTHER);
    }

    public function testItGoesBackWhenAnotherPageHasTheSlugDerivedFromTheTitle(): void
    {
        $client = self::createAuthenticatedClient();
        self::getContainer()->get(PageRepository::class)->save(
            PageMother::create(id: PageIdMother::random()->value(), title: 'Sobre mí', slug: 'sobre-mi'),
        );

        $this->post($client, 'cms_pages_create', 'http://cms.blogautista.localhost/pages', ['title' => 'Sobre mí']);

        self::assertResponseRedirects('http://cms.blogautista.localhost/pages', Response::HTTP_SEE_OTHER);
    }
}
