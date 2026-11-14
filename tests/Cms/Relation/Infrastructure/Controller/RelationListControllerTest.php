<?php

declare(strict_types=1);

namespace Cms\Tests\Relation\Infrastructure\Controller;

use Cms\Author\Domain\AuthorRepository;
use Cms\Image\Domain\ImageRepository;
use Cms\Page\Domain\PageRepository;
use Cms\Post\Domain\PostRepository;
use Cms\Relation\Application\UsersFinder;
use Cms\Relation\Domain\RelationRepository;
use Cms\Relation\Domain\ResourceType;
use Cms\Taxonomy\Domain\Taxonomy;
use Cms\Taxonomy\Domain\TermRepository;
use Cms\Tests\Author\Domain\AuthorMother;
use Cms\Tests\Image\Domain\ImageIdMother;
use Cms\Tests\Image\Domain\ImageMother;
use Cms\Tests\Page\Domain\PageIdMother;
use Cms\Tests\Page\Domain\PageMother;
use Cms\Tests\Post\Domain\PostIdMother;
use Cms\Tests\Post\Domain\PostMother;
use Cms\Tests\Shared\Infrastructure\CmsWebTestCase;
use Cms\Tests\Taxonomy\Domain\TermMother;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpFoundation\Response;

final class RelationListControllerTest extends CmsWebTestCase
{
    private const string URL = 'http://cms.blogautista.localhost/%s/%s/relations';

    #[DataProvider('sections')]
    public function testItRedirectsAnonymousVisitorsToTheLoginForm(string $section): void
    {
        $client = self::createClient();

        $client->request('GET', \sprintf(self::URL, $section, PageIdMother::DEFAULT_VALUE));

        self::assertResponseRedirects('http://cms.blogautista.localhost/login');
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function sections(): iterable
    {
        yield 'pages' => ['pages'];
        yield 'images' => ['images'];
        yield 'posts' => ['posts'];
        yield 'sections' => ['sections'];
        yield 'tags' => ['tags'];
        yield 'authors' => ['authors'];
    }

    public function testItListsWhatUsesAPage(): void
    {
        $client = self::createAuthenticatedClient();
        $id = PageIdMother::random()->value();
        $user = PageIdMother::random()->value();
        self::getContainer()->get(PageRepository::class)->save(PageMother::create(id: $id, title: 'Sobre mí', slug: 'sobre-mi'));
        self::getContainer()->get(PageRepository::class)->save(PageMother::create(id: $user, title: 'Contacto', slug: 'contacto'));
        self::getContainer()->get(RelationRepository::class)->replace(ResourceType::Page, $user, ResourceType::Page, [$id]);

        $client->request('GET', \sprintf(self::URL, 'pages', $id));

        self::assertResponseIsSuccessful();
        self::assertRouteSame('cms_relations', ['type' => 'pages', 'id' => $id]);
    }

    public function testItListsWhatUsesAnImage(): void
    {
        $client = self::createAuthenticatedClient();
        $id = ImageIdMother::random()->value();
        $user = PageIdMother::random()->value();
        self::getContainer()->get(ImageRepository::class)->save(ImageMother::create(id: $id));
        self::getContainer()->get(PageRepository::class)->save(PageMother::create(id: $user, title: 'Sobre mí', slug: 'sobre-mi'));
        self::getContainer()->get(RelationRepository::class)->replace(ResourceType::Page, $user, ResourceType::Image, [$id]);

        $client->request('GET', \sprintf(self::URL, 'images', $id));

        self::assertResponseIsSuccessful();
        self::assertRouteSame('cms_relations', ['type' => 'images', 'id' => $id]);
    }

    /**
     * A section or a tag is used by the posts filed under it, an author by
     * those it signs, and a post by nothing yet but the redirects to it.
     */
    #[DataProvider('classifiers')]
    public function testItListsWhatUsesAResourceOfThePosts(string $section, string $id): void
    {
        $client = self::createAuthenticatedClient();
        $terms = self::getContainer()->get(TermRepository::class);
        $terms->save(TermMother::create(id: PostMother::SECTION));
        $terms->save(TermMother::create(id: PostMother::TAG, taxonomy: Taxonomy::Tag, name: 'Sueño', slug: 'sueno'));
        self::getContainer()->get(AuthorRepository::class)->save(AuthorMother::create(id: PostMother::AUTHOR));
        $post = PostIdMother::random()->value();
        self::getContainer()->get(PostRepository::class)->save(PostMother::publishable(id: $post, tags: [PostMother::TAG]));
        $relations = self::getContainer()->get(RelationRepository::class);
        $relations->replace(ResourceType::Post, $post, ResourceType::Term, [PostMother::SECTION, PostMother::TAG]);
        $relations->replace(ResourceType::Post, $post, ResourceType::Author, [PostMother::AUTHOR]);

        $client->request('GET', \sprintf(self::URL, $section, '' === $id ? $post : $id));

        self::assertResponseIsSuccessful();
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function classifiers(): iterable
    {
        yield 'a section' => ['sections', PostMother::SECTION];
        yield 'a tag' => ['tags', PostMother::TAG];
        yield 'an author' => ['authors', PostMother::AUTHOR];
        yield 'a post' => ['posts', ''];
    }

    public function testItOpensTheListOfAPageNothingUses(): void
    {
        $client = self::createAuthenticatedClient();
        $id = PageIdMother::random()->value();
        self::getContainer()->get(PageRepository::class)->save(PageMother::create(id: $id, title: 'Sobre mí', slug: 'sobre-mi'));

        $client->request('GET', \sprintf(self::URL, 'pages', $id));

        self::assertResponseIsSuccessful();
    }

    /**
     * Like every list, a page past the last one is an empty list.
     */
    public function testItOpensEveryPageOfTheList(): void
    {
        $client = self::createAuthenticatedClient();
        $id = PageIdMother::random()->value();
        self::getContainer()->get(PageRepository::class)->save(PageMother::create(id: $id, title: 'Sobre mí', slug: 'sobre-mi'));

        for ($index = 1; $index <= UsersFinder::PAGE_SIZE + 1; ++$index) {
            $user = PageIdMother::random()->value();
            self::getContainer()->get(PageRepository::class)->save(PageMother::create(id: $user, title: \sprintf('Página %d', $index), slug: \sprintf('pagina-%d', $index)));
            self::getContainer()->get(RelationRepository::class)->replace(ResourceType::Page, $user, ResourceType::Page, [$id]);
        }

        foreach (['', '?page=1', '?page=2', '?page=3'] as $query) {
            $client->request('GET', \sprintf(self::URL, 'pages', $id).$query);

            self::assertResponseIsSuccessful($query);
        }
    }

    #[DataProvider('missingResources')]
    public function testItAnswersNotFoundForAResourceThatDoesNotExist(string $section, string $id): void
    {
        $client = self::createAuthenticatedClient();

        $client->request('GET', \sprintf(self::URL, $section, $id));

        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function missingResources(): iterable
    {
        yield 'a page' => ['pages', PageIdMother::DEFAULT_VALUE];
        yield 'an image' => ['images', ImageIdMother::DEFAULT_VALUE];
        yield 'a page with the id of an image' => ['pages', ImageIdMother::DEFAULT_VALUE];
        yield 'a post' => ['posts', PostIdMother::DEFAULT_VALUE];
        yield 'a section' => ['sections', PostMother::SECTION];
        yield 'a tag' => ['tags', PostMother::TAG];
        yield 'an author' => ['authors', PostMother::AUTHOR];
        yield 'not an id' => ['pages', 'sobre-mi'];
        // The settings use pages and images, but nothing uses them.
        yield 'the settings' => ['settings', PageIdMother::DEFAULT_VALUE];
    }

    #[DataProvider('invalidPages')]
    public function testItAnswersNotFoundForAnInvalidPageNumber(string $page): void
    {
        $client = self::createAuthenticatedClient();
        $id = PageIdMother::random()->value();
        self::getContainer()->get(PageRepository::class)->save(PageMother::create(id: $id, title: 'Sobre mí', slug: 'sobre-mi'));

        $client->request('GET', \sprintf(self::URL, 'pages', $id).'?page='.$page);

        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidPages(): iterable
    {
        yield 'zero' => ['0'];
        yield 'negative' => ['-1'];
        yield 'not a number' => ['dos'];
    }
}
