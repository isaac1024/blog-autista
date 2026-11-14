<?php

declare(strict_types=1);

namespace Cms\Tests\Relation\Infrastructure\Controller;

use Cms\Author\Domain\AuthorRepository;
use Cms\Page\Domain\PageRepository;
use Cms\Relation\Domain\RelationRepository;
use Cms\Relation\Domain\ResourceType;
use Cms\Tests\Author\Domain\AuthorIdMother;
use Cms\Tests\Author\Domain\AuthorMother;
use Cms\Tests\Page\Domain\PageIdMother;
use Cms\Tests\Page\Domain\PageMother;
use Cms\Tests\Shared\Infrastructure\CmsWebTestCase;
use Symfony\Component\HttpFoundation\Response;

/**
 * The bios of the authors use pages and images as any other resource does:
 * they are listed among what uses them, and keep them from being deleted.
 */
final class AuthorUsersTest extends CmsWebTestCase
{
    private const string HOST = 'http://cms.blogautista.localhost';

    public function testItListsTheAuthorsWhoseBioUsesAPage(): void
    {
        $client = self::createAuthenticatedClient();
        $page = PageMother::create(id: PageIdMother::random()->value(), title: 'Contacto', slug: 'contacto');
        self::getContainer()->get(PageRepository::class)->save($page);
        $author = AuthorIdMother::random()->value();
        self::getContainer()->get(AuthorRepository::class)->save(AuthorMother::create(id: $author));
        self::getContainer()->get(RelationRepository::class)->replace(ResourceType::Author, $author, ResourceType::Page, [$page->id()->value()]);
        // An author since deleted is left out.
        self::getContainer()->get(RelationRepository::class)->replace(ResourceType::Author, AuthorIdMother::random()->value(), ResourceType::Page, [$page->id()->value()]);

        $client->request('GET', self::HOST.'/pages/'.$page->id()->value().'/relations');

        self::assertResponseIsSuccessful();
    }

    public function testItKeepsAPageTheBioOfAnAuthorLinksTo(): void
    {
        $client = self::createAuthenticatedClient();
        $page = PageMother::create(id: PageIdMother::random()->value(), title: 'Contacto', slug: 'contacto');
        self::getContainer()->get(PageRepository::class)->save($page);
        self::getContainer()->get(RelationRepository::class)->replace(ResourceType::Author, AuthorIdMother::random()->value(), ResourceType::Page, [$page->id()->value()]);

        $this->post($client, 'cms_pages_delete', self::HOST.'/pages/'.$page->id()->value().'/delete');

        self::assertResponseRedirects(self::HOST.'/pages/'.$page->id()->value(), Response::HTTP_SEE_OTHER);
        self::assertSame([['key' => 'errors.page.publication.linked_page', 'parameters' => []]], self::errorNotices($client));
    }

    /**
     * Pages first, then images, then the settings, then the authors.
     */
    public function testItListsTheAuthorsAfterEveryOtherUser(): void
    {
        $page = PageIdMother::random()->value();
        $relations = self::getContainer()->get(RelationRepository::class);
        $relations->replace(ResourceType::Author, AuthorIdMother::random()->value(), ResourceType::Page, [$page]);
        $relations->replace(ResourceType::Settings, '01a1da50-0000-7000-8000-000000000001', ResourceType::Page, [$page]);
        $relations->replace(ResourceType::Page, PageIdMother::random()->value(), ResourceType::Page, [$page]);

        $types = array_map(static fn ($relation): string => $relation->sourceType()->value, $relations->usersOf(ResourceType::Page, $page, 10, 0));

        self::assertSame(['page', 'settings', 'author'], $types);
    }
}
