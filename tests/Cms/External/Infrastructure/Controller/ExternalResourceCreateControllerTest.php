<?php

declare(strict_types=1);

namespace Cms\Tests\External\Infrastructure\Controller;

use Cms\External\Application\ExternalResourceFinder;
use Cms\External\Application\ExternalResourceResponse;
use Cms\External\Domain\ExternalResourceRepository;
use Cms\Tests\External\Domain\ExternalResourceMother;
use PHPUnit\Framework\Attributes\DataProvider;
use Shared\Tests\Infrastructure\Event\QueuedDomainEvents;
use Shared\Tests\Infrastructure\Http\RecordedSites;
use Symfony\Component\HttpFoundation\Response;

/**
 * Adding an external resource from its address: the CMS keeps what reading
 * it told, with a copy of its image, and goes on to its editor. What each
 * kind of address tells is HttpResourceReaderTest's. An address already
 * kept leads to its resource, and one of the blog itself, or none at all,
 * is refused.
 */
final class ExternalResourceCreateControllerTest extends ExternalResourceControllerTestCase
{
    use QueuedDomainEvents;

    public function testItRedirectsAnonymousVisitorsToTheLoginForm(): void
    {
        $client = self::createClient();

        $this->post($client, 'cms_externals_create', self::HOST.'/externals', ['url' => ExternalResourceMother::VIDEO_URL]);

        self::assertResponseRedirects(self::HOST.'/login');
    }

    public function testItAddsAVideoOfYouTubeWithItsTitleAndACopyOfItsImage(): void
    {
        $client = self::createAuthenticatedClient();
        RecordedSites::answerJson('https://www.youtube.com/oembed?format=json&url='.rawurlencode(ExternalResourceMother::VIDEO_URL), ['title' => 'Never Gonna Give You Up', 'thumbnail_url' => 'https://i.ytimg.com/vi/dQw4w9WgXcQ/hqdefault.jpg']);
        RecordedSites::answerFile('https://i.ytimg.com/vi/dQw4w9WgXcQ/maxresdefault.jpg', self::PNG, 'image/png');

        $resource = $this->create($client, 'https://youtu.be/dQw4w9WgXcQ?si=compartido');

        self::assertSame(['video', 'youtube', 'https://youtu.be/dQw4w9WgXcQ', 'Never Gonna Give You Up', 'landscape'], [$resource->kind, $resource->provider, $resource->url, $resource->title, $resource->imageShape]);
        self::assertSame([['key' => 'cms.notices.external.created', 'parameters' => []]], self::successNotices($client));
        self::assertImageKept($resource, 1200, 675);
        // A draft tells the site nothing.
        self::assertSame([], $this->queuedEvents());
    }

    /**
     * What the address alone tells is kept, and the editor is told to fill
     * the rest: a page of a server that doesn't answer as Mastodon is an
     * article, whatever its address looks like.
     */
    public function testItAddsWhatTheAddressAloneTellsWhenItCannotBeRead(): void
    {
        $client = self::createAuthenticatedClient();

        $resource = $this->create($client, 'https://medium.com/@escritora/12345');

        self::assertSame(['article', null, 'medium.com', ['publisher' => null, 'published_on' => null]], [$resource->kind, $resource->provider, $resource->title, $resource->details]);
        self::assertSame([['key' => 'cms.notices.external.created_unread', 'parameters' => []]], self::errorNotices($client));
    }

    public function testItGoesToTheResourceThatHasTheAddressAlready(): void
    {
        $client = self::createAuthenticatedClient();
        $this->saveResource(ExternalResourceMother::video());

        $this->post($client, 'cms_externals_create', self::HOST.'/externals', ['url' => ExternalResourceMother::VIDEO_URL.'&utm_medium=social']);

        self::assertResponseRedirects('/externals/'.ExternalResourceMother::ID, Response::HTTP_SEE_OTHER);
        self::assertSame([['key' => 'errors.external.url.taken', 'parameters' => ['%id%' => ExternalResourceMother::ID]]], self::errorNotices($client));
        self::assertSame([], RecordedSites::asked());
    }

    /**
     * @param array<string, int|string> $parameters
     */
    #[DataProvider('refusedAddresses')]
    public function testItRefusesAnAddressItCannotKeep(string $url, string $key, array $parameters): void
    {
        $client = self::createAuthenticatedClient();

        $this->post($client, 'cms_externals_create', self::HOST.'/externals', ['url' => $url]);

        self::assertResponseRedirects('/externals', Response::HTTP_SEE_OTHER);
        self::assertSame([['key' => $key, 'parameters' => $parameters]], self::errorNotices($client));
        self::assertSame([], self::getContainer()->get(ExternalResourceRepository::class)->findByIds([]));
    }

    /**
     * @return iterable<string, array{string, string, array<string, int|string>}>
     */
    public static function refusedAddresses(): iterable
    {
        yield 'none' => ['vídeo de YouTube', 'errors.external.url.not_an_address', []];
        yield 'one of the blog itself' => ['https://blogautista.localhost/sobre-mi.html', 'errors.external.url.own_site', []];
    }

    /**
     * Creates the resource of the address, from its editor once created.
     */
    private function create(\Symfony\Bundle\FrameworkBundle\KernelBrowser $client, string $url): ExternalResourceResponse
    {
        $this->post($client, 'cms_externals_create', self::HOST.'/externals', ['url' => $url]);

        self::assertResponseRedirects();
        $location = (string) $client->getResponse()->headers->get('Location');
        self::assertMatchesRegularExpression('#^/externals/[0-9a-f-]{36}$#', $location);

        return new ExternalResourceFinder(substr($location, \strlen('/externals/')))(self::getContainer()->get(ExternalResourceRepository::class));
    }

    private static function assertImageKept(ExternalResourceResponse $resource, int $width, int $height): void
    {
        self::assertNotNull($resource->image);
        $size = getimagesize(self::imageDirectory().'/'.$resource->image);
        self::assertNotFalse($size);
        self::assertSame(['image/webp', $width, $height], [$size['mime'], $size[0], $size[1]]);
    }
}
