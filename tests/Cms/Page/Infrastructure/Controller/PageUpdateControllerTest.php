<?php

declare(strict_types=1);

namespace Cms\Tests\Page\Infrastructure\Controller;

use Cms\Page\Domain\Page;
use Cms\Page\Domain\PageRepository;
use Cms\Page\Domain\Robots;
use Cms\Tests\Page\Domain\PageIdMother;
use Cms\Tests\Page\Domain\PageMother;
use Cms\Tests\Shared\Infrastructure\CmsWebTestCase;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use Shared\Tests\Domain\ClockMother;
use Shared\Tests\Domain\RichText\RichTextMother as Text;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;
use Symfony\Component\HttpFoundation\Response;

final class PageUpdateControllerTest extends CmsWebTestCase
{
    use ClockSensitiveTrait;
    private const string URL = 'http://cms.blogautista.localhost/pages/%s';

    /**
     * The document the editor posts for blank content.
     */
    private const string BLANK_DOCUMENT = '{"type":"doc","content":[{"type":"paragraph"}]}';

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();
        // Edits are dated, so they are dated as the pages they are compared with.
        self::mockTime(ClockMother::NOW);
    }

    public function testItRedirectsAnonymousVisitorsToTheLoginForm(): void
    {
        $client = self::createClient();
        $page = $this->savePage();

        $client->request('POST', \sprintf(self::URL, $page->id()->value()), self::fields());

        self::assertResponseRedirects('http://cms.blogautista.localhost/login');
        self::assertEquals($page, $this->storedPage($page));
    }

    public function testItSavesTheChangesAndGoesBackToTheList(): void
    {
        $client = self::createAuthenticatedClient();
        $page = $this->savePage();

        $client->request('POST', \sprintf(self::URL, $page->id()->value()), self::fields());

        self::assertResponseRedirects('/pages', Response::HTTP_SEE_OTHER);
        self::assertEquals(
            PageMother::create(id: $page->id()->value(), title: 'Quién soy', slug: 'quien-soy'),
            $this->storedPage($page),
        );
    }

    public function testItSavesTheContentAndTheSearchEngineSettings(): void
    {
        $client = self::createAuthenticatedClient();
        $page = $this->savePage();
        // What the editor posts: a Tiptap document, translated into the stored format.
        $document = ['type' => 'doc', 'content' => [
            ['type' => 'heading', 'attrs' => ['level' => 2], 'content' => [['type' => 'text', 'text' => 'Quién soy']]],
            ['type' => 'paragraph', 'content' => [
                ['type' => 'text', 'text' => 'Escribo sobre '],
                ['type' => 'text', 'text' => 'autismo', 'marks' => [['type' => 'bold']]],
            ]],
            ['type' => 'paragraph'],
        ]];

        $client->request('POST', \sprintf(self::URL, $page->id()->value()), self::fields([
            'content' => json_encode($document, \JSON_THROW_ON_ERROR),
            'meta_description' => 'Quién escribe este blog y por qué.',
            'robots' => 'noindex,follow',
        ]));

        self::assertResponseRedirects('/pages', Response::HTTP_SEE_OTHER);
        self::assertEquals(
            PageMother::create(
                id: $page->id()->value(),
                title: 'Quién soy',
                slug: 'quien-soy',
                content: Text::stored(Text::heading(2, 'Quién soy'), Text::paragraph('Escribo sobre ', '**autismo**')),
                metaDescription: 'Quién escribe este blog y por qué.',
                robots: Robots::NoindexFollow,
            ),
            $this->storedPage($page),
        );
    }

    /**
     * @param array<string, string> $fields
     */
    #[DataProvider('invalidFields')]
    public function testItGoesBackAndKeepsThePageWhenTheInputIsInvalid(array $fields): void
    {
        $client = self::createAuthenticatedClient();
        $page = $this->savePage();

        $client->request('POST', \sprintf(self::URL, $page->id()->value()), $fields);

        self::assertResponseRedirects('http://cms.blogautista.localhost/pages/'.$page->id()->value(), Response::HTTP_SEE_OTHER);
        self::assertEquals($page, $this->storedPage($page));
    }

    /**
     * @return iterable<string, array{array<string, string>}>
     */
    public static function invalidFields(): iterable
    {
        yield 'invalid title' => [self::fields(['title' => ' '])];
        yield 'invalid slug' => [self::fields(['slug' => 'Quién Soy'])];
        yield 'missing title' => [self::fields(without: ['title'])];
        yield 'missing content' => [self::fields(without: ['content'])];
        yield 'content that is not JSON' => [self::fields(['content' => '<p>Hola</p>'])];
        yield 'content with a block the editor does not offer' => [self::fields(['content' => '{"type":"doc","content":[{"type":"video","attrs":{"src":"x.mp4"}}]}'])];
        yield 'content with a heading level a page does not allow' => [self::fields(['content' => json_encode(['type' => 'doc', 'content' => [
            ['type' => 'heading', 'attrs' => ['level' => 2], 'content' => [['type' => 'text', 'text' => 'A']]],
            ['type' => 'heading', 'attrs' => ['level' => 3], 'content' => [['type' => 'text', 'text' => 'B']]],
            ['type' => 'heading', 'attrs' => ['level' => 4], 'content' => [['type' => 'text', 'text' => 'C']]],
        ]], \JSON_THROW_ON_ERROR)])];
        yield 'meta description too long' => [self::fields(['meta_description' => str_repeat('a', 201)])];
        yield 'unknown robots directive' => [self::fields(['robots' => 'noindex'])];
        yield 'missing robots directive' => [self::fields(without: ['robots'])];
    }

    public function testItGoesBackAndKeepsThePageWhenAnotherPageHasTheSlug(): void
    {
        $client = self::createAuthenticatedClient();
        $page = $this->savePage();
        self::getContainer()->get(PageRepository::class)->save(
            PageMother::create(id: PageIdMother::random()->value(), title: 'Contacto', slug: 'contacto'),
        );

        $client->request('POST', \sprintf(self::URL, $page->id()->value()), self::fields(['slug' => 'contacto']));

        // The request edits the very instance saved above before the slug check
        // fails, so the stored page is compared against a fresh copy instead.
        self::assertResponseRedirects('http://cms.blogautista.localhost/pages/'.$page->id()->value(), Response::HTTP_SEE_OTHER);
        self::assertEquals(
            PageMother::create(id: $page->id()->value(), title: 'Sobre mí', slug: 'sobre-mi'),
            $this->storedPage($page),
        );
    }

    public function testItSavesTheHomePageWithoutASlug(): void
    {
        $client = self::createAuthenticatedClient();
        $home = PageMother::home(id: PageIdMother::random()->value(), title: 'Portada');
        self::getContainer()->get(PageRepository::class)->save($home);

        // The editor of the home page has no slug field, so none is posted.
        $client->request('POST', \sprintf(self::URL, $home->id()->value()), self::fields(['title' => 'Inicio'], without: ['slug']));

        self::assertResponseRedirects('/pages', Response::HTTP_SEE_OTHER);
        self::assertEquals(PageMother::home(id: $home->id()->value(), title: 'Inicio'), $this->storedPage($home));
    }

    public function testItGoesBackAndKeepsTheHomePageWhenItIsGivenASlug(): void
    {
        $client = self::createAuthenticatedClient();
        $home = PageMother::home(id: PageIdMother::random()->value(), title: 'Portada');
        self::getContainer()->get(PageRepository::class)->save($home);

        $client->request('POST', \sprintf(self::URL, $home->id()->value()), self::fields(['title' => 'Inicio', 'slug' => 'inicio']));

        self::assertResponseRedirects('http://cms.blogautista.localhost/pages/'.$home->id()->value(), Response::HTTP_SEE_OTHER);
        self::assertEquals(PageMother::home(id: $home->id()->value(), title: 'Portada'), $this->storedPage($home));
    }

    public function testItRespondsNotFoundWhenThePageDoesNotExist(): void
    {
        $client = self::createAuthenticatedClient();

        $client->request('POST', \sprintf(self::URL, PageIdMother::random()->value()), self::fields());

        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    public function testItRespondsNotFoundWhenTheIdIsNotAUuid(): void
    {
        $client = self::createAuthenticatedClient();

        $client->request('POST', \sprintf(self::URL, 'sobre-mi'), self::fields());

        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    /**
     * Keeps the client's kernel between requests so the fixture, the request
     * and the assertions share one database connection.
     */
    #[\Override]
    protected static function createAuthenticatedClient(): KernelBrowser
    {
        $client = parent::createAuthenticatedClient();
        $client->disableReboot();

        return $client;
    }

    /**
     * The fields the editor posts: a valid edit, unless a test overrides some
     * or leaves some out.
     *
     * @param array<string, string> $overrides
     * @param list<string>          $without
     *
     * @return array<string, string>
     */
    private static function fields(array $overrides = [], array $without = []): array
    {
        $fields = array_merge([
            'title' => 'Quién soy',
            'slug' => 'quien-soy',
            'content' => self::BLANK_DOCUMENT,
            'meta_description' => '',
            'robots' => 'index,follow',
        ], $overrides);

        return array_diff_key($fields, array_flip($without));
    }

    private function savePage(): Page
    {
        $page = PageMother::create(id: PageIdMother::random()->value(), title: 'Sobre mí', slug: 'sobre-mi');
        self::getContainer()->get(PageRepository::class)->save($page);

        return $page;
    }

    /**
     * Reads the page back from the database, not from Doctrine's identity map.
     */
    private function storedPage(Page $page): ?Page
    {
        self::getContainer()->get(EntityManagerInterface::class)->clear();

        return self::getContainer()->get(PageRepository::class)->find($page->id());
    }
}
