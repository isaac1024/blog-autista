<?php

declare(strict_types=1);

namespace Cms\Tests\Relation\Infrastructure\Controller;

use Cms\Image\Domain\ImageRepository;
use Cms\Page\Domain\PageRepository;
use Cms\Relation\Application\UsersFinder;
use Cms\Relation\Domain\RelationRepository;
use Cms\Relation\Domain\ResourceType;
use Cms\Tests\Image\Domain\ImageIdMother;
use Cms\Tests\Image\Domain\ImageMother;
use Cms\Tests\Page\Domain\PageIdMother;
use Cms\Tests\Page\Domain\PageMother;
use Cms\Tests\Shared\Infrastructure\CmsWebTestCase;
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
