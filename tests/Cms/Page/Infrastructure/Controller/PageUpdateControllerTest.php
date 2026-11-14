<?php

declare(strict_types=1);

namespace Cms\Tests\Page\Infrastructure\Controller;

use Cms\Page\Domain\Page;
use Cms\Page\Domain\PageRepository;
use Cms\Relation\Domain\RelationRepository;
use Cms\Relation\Domain\ResourceType;
use Cms\Shared\Domain\Robots;
use Cms\Tests\Page\Domain\PageIdMother;
use Cms\Tests\Page\Domain\PageMother;
use Cms\Tests\Shared\Infrastructure\CmsWebTestCase;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use Shared\Tests\Domain\ClockMother;
use Shared\Tests\Domain\RichText\RichTextMother as Text;
use Shared\Tests\Infrastructure\Event\QueuedDomainEvents;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;
use Symfony\Component\HttpFoundation\Response;

/**
 * What a page is saved with, and why an edit is refused, is told by
 * PageUpdaterTest, and how its content is read by TiptapRichTextTest; here,
 * how the editor is answered: what it reads of the request, where it goes
 * once saved or refused, and what it tells.
 */
final class PageUpdateControllerTest extends CmsWebTestCase
{
    use ClockSensitiveTrait;
    use QueuedDomainEvents;
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

        $this->post($client, 'cms_pages_update', \sprintf(self::URL, $page->id()->value()), self::fields());

        self::assertResponseRedirects('http://cms.blogautista.localhost/login');
        self::assertEquals($page, $this->storedPage($page));
    }

    public function testItSavesTheChangesAndStaysInTheEditor(): void
    {
        $client = self::createAuthenticatedClient();
        $page = $this->savePage();

        $this->post($client, 'cms_pages_update', \sprintf(self::URL, $page->id()->value()), self::fields());

        self::assertResponseRedirects('/pages/'.$page->id()->value(), Response::HTTP_SEE_OTHER);
        self::assertSame([['key' => 'cms.notices.page.saved', 'parameters' => []]], self::successNotices($client));
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

        $this->post($client, 'cms_pages_update', \sprintf(self::URL, $page->id()->value()), self::fields([
            'content' => json_encode($document, \JSON_THROW_ON_ERROR),
            'meta_description' => 'Quién escribe este blog y por qué.',
            'robots' => 'noindex,follow',
        ]));

        self::assertResponseRedirects('/pages/'.$page->id()->value(), Response::HTTP_SEE_OTHER);
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

        $this->post($client, 'cms_pages_update', \sprintf(self::URL, $page->id()->value()), $fields);

        self::assertResponseRedirects('http://cms.blogautista.localhost/pages/'.$page->id()->value(), Response::HTTP_SEE_OTHER);
        self::assertEquals($page, $this->storedPage($page));
    }

    /**
     * @return iterable<string, array{array<string, string>}>
     */
    public static function invalidFields(): iterable
    {
        yield 'invalid title' => [self::fields(['title' => ' '])];
        yield 'missing title' => [self::fields(without: ['title'])];
        yield 'content that is not JSON' => [self::fields(['content' => '<p>Hola</p>'])];
    }

    /**
     * The page tells the images it embeds at once, not through the queue, so
     * by the time it is saved the image is in use.
     */
    public function testItKeepsTheImagesThePageEmbedsInUseAsItSaves(): void
    {
        $client = self::createAuthenticatedClient();
        $page = $this->savePage();
        $document = ['type' => 'doc', 'content' => [['type' => 'image', 'attrs' => ['id' => '01a1b801-ce58-723d-8234-b76d9105d001']]]];

        $this->post($client, 'cms_pages_update', \sprintf(self::URL, $page->id()->value()), self::fields([
            'content' => json_encode($document, \JSON_THROW_ON_ERROR),
        ]));

        self::assertResponseRedirects('/pages/'.$page->id()->value(), Response::HTTP_SEE_OTHER);
        self::assertTrue(self::getContainer()->get(RelationRepository::class)->isUsed(ResourceType::Image, '01a1b801-ce58-723d-8234-b76d9105d001'));
        self::assertSame([], $this->queuedEvents());
    }

    public function testItRespondsNotFoundWhenThePageDoesNotExist(): void
    {
        $client = self::createAuthenticatedClient();

        $this->post($client, 'cms_pages_update', \sprintf(self::URL, PageIdMother::random()->value()), self::fields());

        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    public function testItRespondsNotFoundWhenTheIdIsNotAUuid(): void
    {
        $client = self::createAuthenticatedClient();

        $this->post($client, 'cms_pages_update', \sprintf(self::URL, 'sobre-mi'), self::fields());

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
