<?php

declare(strict_types=1);

namespace Web\Tests\Sitemap;

use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Shared\Infrastructure\Event\DomainEventSubscriber;
use Shared\Tests\Domain\ClockMother;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;
use Symfony\Component\HttpFoundation\Response;
use Web\Image\Domain\Image;
use Web\Image\Domain\ImageId;
use Web\Image\Domain\ImageRepository;
use Web\Post\Infrastructure\Event\CmsPostPublishedSubscriber;
use Web\Post\Infrastructure\Event\CmsPostUnpublishedSubscriber;
use Web\Sitemap\Infrastructure\Controller\SitemapXml;
use Web\Sitemap\Infrastructure\Event\SitemapAuthorsSubscriber;
use Web\Sitemap\Infrastructure\Event\SitemapImagesSubscriber;
use Web\Sitemap\Infrastructure\Event\SitemapPagesSubscriber;
use Web\Sitemap\Infrastructure\Event\SitemapPostsSubscriber;
use Web\Sitemap\Infrastructure\Event\SitemapTermsSubscriber;
use Web\Taxonomy\Infrastructure\Event\CmsTermPublishedSubscriber;
use Web\Taxonomy\Infrastructure\Event\CmsTermUnpublishedSubscriber;
use Web\Tests\Author\Domain\CmsAuthorPublishedMother;
use Web\Tests\Image\Domain\CmsImagePublishedMother;
use Web\Tests\Page\Domain\CmsPagePublishedMother;
use Web\Tests\Post\Domain\CmsPostPublishedMother;
use Web\Tests\Taxonomy\Domain\CmsTermPublishedMother;

/**
 * The sitemap of the site follows what the CMS publishes: each address
 * search engines may index, in its file, with when it last changed; the
 * index tells each file with when any of its addresses did. It checks the
 * wiring, from the events to the XML served; the rules of the files and
 * their entries are told by the tests of Web\Sitemap\Application.
 */
final class SitemapTest extends WebTestCase
{
    use ClockSensitiveTrait;

    private const string SITE = 'http://www.blogautista.localhost';

    private const string PAGE = '01a1e400-0000-7000-8000-000000000001';

    private const string HOME = '01a1e400-0000-7000-8000-000000000002';

    private const string HIDDEN_PAGE = '01a1e400-0000-7000-8000-000000000003';

    private const string SECTION = '01a1e500-0000-7000-8000-000000000001';

    private const string EMPTY_SECTION = '01a1e500-0000-7000-8000-000000000002';

    private const string SLEEP = '01a1e500-0000-7000-8000-000000000003';

    private const string SOUND = '01a1e500-0000-7000-8000-000000000004';

    private const string AUTHOR = '01a1e600-0000-7000-8000-000000000001';

    private const string POST = '01a1e700-0000-7000-8000-000000000001';

    private const string OLD_POST = '01a1e700-0000-7000-8000-000000000002';

    private const string IMAGE = '01a1e800-0000-7000-8000-000000000001';

    private const string MISSING_IMAGE = '01a1e800-0000-7000-8000-000000000002';

    private const string OTHER_IMAGE = '01a1e800-0000-7000-8000-000000000003';

    /**
     * What may be indexed goes in its file, the home page at the root; what
     * asks not to be, and sections and tags listing no post, never shows.
     */
    public function testItListsWhatSearchEnginesMayIndexInItsFile(): void
    {
        $client = self::createClient();
        $this->publishSite();

        self::assertSame([
            '/sitemap-autores.xml' => '2026-11-07T10:00:00+00:00',
            '/sitemap-etiquetas.xml' => '2026-11-07T10:00:00+00:00',
            '/sitemap-paginas.xml' => '2026-11-07T10:00:00+00:00',
            '/sitemap-posts-2025.xml' => '2026-11-07T10:00:00+00:00',
            '/sitemap-posts-2026.xml' => '2026-11-07T10:00:00+00:00',
            '/sitemap-secciones.xml' => '2026-11-07T10:00:00+00:00',
        ], $this->index($client));
        self::assertSame(['/' => '2026-11-07T10:00:00+00:00', '/sobre-mi.html' => '2026-11-07T10:00:00+00:00'], $this->file($client, 'paginas'));
        self::assertSame(['/vida-diaria/' => '2026-11-07T10:00:00+00:00'], $this->file($client, 'secciones'));
        self::assertSame(['/tag/sensorialidad.html' => '2026-11-07T10:00:00+00:00'], $this->file($client, 'etiquetas'));
        self::assertSame(['/autor/isaac.html' => '2026-11-07T10:00:00+00:00'], $this->file($client, 'autores'));
        self::assertSame(['/vida-diaria/2026/11/07/mi-primer-dia.html' => '2026-11-07T10:00:00+00:00'], $this->file($client, 'posts-2026'));
        self::assertSame(['/vida-diaria/2025/03/02/un-dia-de-2025.html' => '2026-11-07T10:00:00+00:00'], $this->file($client, 'posts-2025'));
        // The page cache keeps it an hour, as it does a post.
        self::assertSame('max-age=0, public, s-maxage=300', $client->getResponse()->headers->get('Cache-Control'));
        self::assertSame('application/xml; charset=UTF-8', $client->getResponse()->headers->get('Content-Type'));
    }

    /**
     * A post names the largest copy of the image it opens with while it is
     * on the site.
     */
    public function testItNamesTheImageAPostOpensWithWhileItIsOnTheSite(): void
    {
        $client = self::createClient();
        $this->publishSite();

        self::assertSame([self::SITE.'/media/2026/11/'.self::IMAGE.'-1200w.webp'], $this->images($client, 'posts-2026'));
        self::assertSame([], $this->images($client, 'posts-2025'));
    }

    /**
     * Taken off the site, an image is named no longer, and the posts that
     * open with it, and their file, changed then; put on it again, it is
     * named again.
     */
    public function testAnImageOffTheSiteIsNamedNoLonger(): void
    {
        $client = self::createClient();
        $this->publishSite();
        $image = self::getContainer()->get(ImageRepository::class)->find(new ImageId(self::IMAGE)) ?? self::fail('No image.');

        self::mockTime(ClockMother::now('2026-11-10 08:00:00'));
        self::getContainer()->get(ImageRepository::class)->delete($image);
        $this->dispatch('cms.image.unpublished', self::IMAGE, ['revision' => 2]);

        self::assertSame([], $this->images($client, 'posts-2026'));
        self::assertSame(['/vida-diaria/2026/11/07/mi-primer-dia.html' => '2026-11-10T08:00:00+00:00'], $this->file($client, 'posts-2026'));
        self::assertSame('2026-11-10T08:00:00+00:00', $this->index($client)['/sitemap-posts-2026.xml']);
        // A post that opens with another image doesn't change.
        self::assertSame('2026-11-07T10:00:00+00:00', $this->index($client)['/sitemap-posts-2025.xml']);

        self::mockTime(ClockMother::now('2026-11-11 08:00:00'));
        self::getContainer()->get(ImageRepository::class)->save(Image::publish(CmsImagePublishedMother::create(id: self::IMAGE, path: '2026/11/'.self::IMAGE.'.webp')));
        $this->dispatch('cms.image.published', self::IMAGE, ['revision' => 3] + CmsImagePublishedMother::body());

        self::assertSame([self::SITE.'/media/2026/11/'.self::IMAGE.'-1200w.webp'], $this->images($client, 'posts-2026'));
        self::assertSame('2026-11-11T08:00:00+00:00', $this->index($client)['/sitemap-posts-2026.xml']);
    }

    /**
     * An image taken off the site whose address now leads to another is named
     * as that one, as the post shows it.
     */
    public function testAnImageOffTheSiteIsNamedAsTheOneItsAddressLeadsTo(): void
    {
        $client = self::createClient();
        $this->publishSite();
        $images = self::getContainer()->get(ImageRepository::class);
        $images->delete($images->find(new ImageId(self::IMAGE)) ?? self::fail('No image.'));
        $images->save(Image::publish(CmsImagePublishedMother::create(id: self::OTHER_IMAGE, path: '2026/11/'.self::OTHER_IMAGE.'.webp')));
        self::getContainer()->get(Connection::class)->insert('shared_redirect', [
            'id' => '01a1e900-0000-7000-8000-000000000001', 'source' => '/media/2026/11/'.self::IMAGE, 'target_type' => 'image', 'target_id' => self::OTHER_IMAGE,
            'origin_type' => 'image', 'origin_id' => self::IMAGE,
            'created_at' => '2026-11-09 09:00:00', 'updated_at' => '2026-11-09 09:00:00', 'version' => 1,
        ]);

        self::assertSame([self::SITE.'/media/2026/11/'.self::OTHER_IMAGE.'-1200w.webp'], $this->images($client, 'posts-2026'));
    }

    /**
     * Republished with other tags, a post changes the lists of the tags it
     * had and of those it has; taken off the site, those of its last ones.
     * A tag that lists no post any longer leaves the sitemap.
     */
    public function testAPostChangesTheListsOfItsSectionAndTags(): void
    {
        $client = self::createClient();
        $this->publishSite();

        $this->dispatch('cms.post.published', self::POST, ['tag_ids' => [self::SOUND], 'last_published_at' => '2026-11-09T10:00:00+00:00', 'revision' => 2] + $this->postBody('mi-primer-dia', '2026-11-07'));

        self::assertSame(['/tag/ruido.html' => '2026-11-09T10:00:00+00:00'], $this->file($client, 'etiquetas'));
        self::assertSame(['/vida-diaria/' => '2026-11-09T10:00:00+00:00'], $this->file($client, 'secciones'));
        self::assertSame('2026-11-09T10:00:00+00:00', $this->index($client)['/sitemap-etiquetas.xml']);

        self::mockTime(ClockMother::now('2026-11-10 08:00:00'));
        $this->dispatch('cms.post.unpublished', self::POST, ['revision' => 3]);

        self::assertSame([], $this->file($client, 'etiquetas'));
        self::assertSame('2026-11-10T08:00:00+00:00', $this->index($client)['/sitemap-etiquetas.xml']);
        self::assertArrayNotHasKey('/sitemap-posts-2026.xml', $this->index($client));
    }

    /**
     * A post names its section by the slug the section was last published
     * with: renamed, the files of its posts tell the new addresses, and
     * changed then, though no post did; once off the site, the same slug.
     */
    public function testAPostNamesItsSectionByItsSlugOnTheSite(): void
    {
        $client = self::createClient();
        $this->publishSite();

        $this->dispatch('cms.term.published', self::SECTION, ['last_published_at' => '2026-11-09T10:00:00+00:00', 'revision' => 2] + CmsTermPublishedMother::body('section', 'Día a día', 'dia-a-dia'));

        self::assertSame(['/dia-a-dia/2026/11/07/mi-primer-dia.html' => '2026-11-07T10:00:00+00:00'], $this->file($client, 'posts-2026'));
        self::assertSame(['/dia-a-dia/2025/03/02/un-dia-de-2025.html' => '2026-11-07T10:00:00+00:00'], $this->file($client, 'posts-2025'));
        self::assertSame(['2026-11-09T10:00:00+00:00', '2026-11-07T10:00:00+00:00'], [$this->index($client)['/sitemap-posts-2026.xml'], $this->index($client)['/sitemap-autores.xml']]);

        self::mockTime(ClockMother::now('2026-11-10 08:00:00'));
        $this->dispatch('cms.term.unpublished', self::SECTION, ['revision' => 3]);

        self::assertSame(['/dia-a-dia/2026/11/07/mi-primer-dia.html' => '2026-11-07T10:00:00+00:00'], $this->file($client, 'posts-2026'));
        self::assertSame('2026-11-10T08:00:00+00:00', $this->index($client)['/sitemap-posts-2026.xml']);
    }

    /**
     * What leaves the site, or asks not to be indexed any longer, leaves the
     * sitemap; a file with nothing left isn't there.
     */
    public function testWhatLeavesTheSiteLeavesTheSitemap(): void
    {
        $client = self::createClient();
        $this->publishSite();

        $this->dispatch('cms.author.unpublished', self::AUTHOR, ['revision' => 2]);
        $this->dispatch('cms.page.published', self::PAGE, ['robots' => 'noindex,follow', 'revision' => 2] + CmsPagePublishedMother::body());

        self::assertArrayNotHasKey('/sitemap-autores.xml', $this->index($client));
        self::assertSame(['/' => '2026-11-07T10:00:00+00:00'], $this->file($client, 'paginas'));
        $client->request('GET', self::SITE.'/sitemap-autores.xml');
        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    /**
     * An event the projection has already taken changes nothing again.
     */
    public function testItTakesNoEventTwice(): void
    {
        $client = self::createClient();
        $this->publishSite();

        $this->dispatch('cms.page.published', self::PAGE, ['robots' => 'noindex,follow', 'revision' => 1] + CmsPagePublishedMother::body());

        self::assertArrayHasKey('/sobre-mi.html', $this->file($client, 'paginas'));
    }

    public function testItHasNoFileOfANameItDoesNotKnow(): void
    {
        $client = self::createClient();

        foreach (['/sitemap-paginas.xml', '/sitemap-posts-1999.xml', '/sitemap-PAGINAS.xml'] as $path) {
            $client->request('GET', self::SITE.$path);
            self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
        }

        $client->request('GET', self::SITE.'/sitemap.xml');
        self::assertResponseIsSuccessful();
        self::assertSame([], $this->index($client));
    }

    /**
     * Search engines may crawl the whole site and are told where its sitemap
     * is; they may crawl nothing of the CMS, which tells so to anyone.
     */
    public function testItTellsSearchEnginesWhatTheyMayCrawl(): void
    {
        $client = self::createClient();

        $client->request('GET', self::SITE.'/robots.txt');
        self::assertResponseIsSuccessful();
        self::assertSame("User-agent: *\nAllow: /\n\nSitemap: ".self::SITE."/sitemap.xml\n", $client->getResponse()->getContent());
        self::assertSame('max-age=0, public, s-maxage=300', $client->getResponse()->headers->get('Cache-Control'));

        $client->request('GET', 'http://cms.blogautista.localhost/robots.txt');
        self::assertResponseIsSuccessful();
        self::assertSame("User-agent: *\nDisallow: /\n", $client->getResponse()->getContent());
    }

    private function publishSite(): void
    {
        self::getContainer()->get(ImageRepository::class)->save(Image::publish(CmsImagePublishedMother::create(id: self::IMAGE, path: '2026/11/'.self::IMAGE.'.webp')));
        $this->dispatch('cms.page.published', self::PAGE, ['revision' => 1] + CmsPagePublishedMother::body());
        $this->dispatch('cms.page.published', self::HOME, ['revision' => 1] + CmsPagePublishedMother::body('Inicio', null, 'home'));
        $this->dispatch('cms.page.published', self::HIDDEN_PAGE, ['robots' => 'noindex,follow', 'revision' => 1] + CmsPagePublishedMother::body('Buscar', 'buscar', 'search'));
        $this->dispatch('cms.term.published', self::SECTION, ['revision' => 1] + CmsTermPublishedMother::body());
        $this->dispatch('cms.term.published', self::EMPTY_SECTION, ['revision' => 1] + CmsTermPublishedMother::body('section', 'Trabajo', 'trabajo'));
        $this->dispatch('cms.term.published', self::SLEEP, ['revision' => 1] + CmsTermPublishedMother::body('tag', 'Sensorialidad', 'sensorialidad'));
        $this->dispatch('cms.term.published', self::SOUND, ['revision' => 1] + CmsTermPublishedMother::body('tag', 'Ruido', 'ruido'));
        $this->dispatch('cms.author.published', self::AUTHOR, ['revision' => 1] + CmsAuthorPublishedMother::body());
        $this->dispatch('cms.post.published', self::POST, ['tag_ids' => [self::SLEEP], 'opening_image_id' => self::IMAGE, 'revision' => 1] + $this->postBody('mi-primer-dia', '2026-11-07'));
        $this->dispatch('cms.post.published', self::OLD_POST, ['tag_ids' => [], 'opening_image_id' => self::MISSING_IMAGE, 'revision' => 1] + $this->postBody('un-dia-de-2025', '2025-03-02'));
    }

    /**
     * @return array<string, mixed>
     */
    private function postBody(string $slug, string $day): array
    {
        return ['slug' => $slug, 'section_id' => self::SECTION, 'author_id' => self::AUTHOR, 'day' => $day] + CmsPostPublishedMother::body();
    }

    /**
     * @param array<string, mixed> $body
     */
    private function dispatch(string $name, string $id, array $body): void
    {
        // As the worker does after each message.
        self::getContainer()->get(EntityManagerInterface::class)->clear();

        // The subscribers of the sitemap, and those that put posts, sections and tags on the
        // site, which the sitemap reads: none of them needs the file of an image or an avatar.
        foreach ([CmsPostPublishedSubscriber::class, CmsPostUnpublishedSubscriber::class, CmsTermPublishedSubscriber::class, CmsTermUnpublishedSubscriber::class, SitemapPagesSubscriber::class, SitemapTermsSubscriber::class, SitemapAuthorsSubscriber::class, SitemapPostsSubscriber::class, SitemapImagesSubscriber::class] as $class) {
            $subscriber = self::getContainer()->get($class);
            self::assertInstanceOf(DomainEventSubscriber::class, $subscriber);

            foreach ($subscriber::subscribedTo() as $event) {
                if ($event::eventName() === $name) {
                    $subscriber->handle($event::fromPrimitives($id, $body));
                }
            }
        }
    }

    /**
     * The files the index tells, with when each last changed, by path.
     *
     * @return array<string, string>
     */
    private function index(KernelBrowser $client): array
    {
        $client->request('GET', self::SITE.'/sitemap.xml');
        self::assertResponseIsSuccessful();

        return self::locations($client->getInternalResponse()->getContent(), 'sitemap');
    }

    /**
     * The addresses a file tells, with when each last changed, by path.
     *
     * @return array<string, string>
     */
    private function file(KernelBrowser $client, string $name): array
    {
        $client->request('GET', self::SITE.'/sitemap-'.$name.'.xml');
        self::assertResponseIsSuccessful();

        return self::locations($client->getInternalResponse()->getContent(), 'url');
    }

    /**
     * The images a file names.
     *
     * @return list<string>
     */
    private function images(KernelBrowser $client, string $name): array
    {
        $client->request('GET', self::SITE.'/sitemap-'.$name.'.xml');
        self::assertResponseIsSuccessful();
        $images = new \SimpleXMLElement($client->getInternalResponse()->getContent())->xpath('//*[local-name()="image"]/*[local-name()="loc"]');

        return array_values(array_map(strval(...), \is_array($images) ? $images : []));
    }

    /**
     * @return array<string, string>
     */
    private static function locations(string $xml, string $element): array
    {
        $locations = [];

        $nodes = new \SimpleXMLElement($xml)->xpath(\sprintf('/*/*[local-name()="%s"]', $element));

        foreach (\is_array($nodes) ? $nodes : [] as $node) {
            $children = $node->children(SitemapXml::NAMESPACE);
            $locations[substr((string) $children->loc, \strlen(self::SITE))] = (string) $children->lastmod;
        }

        return $locations;
    }
}
