<?php

declare(strict_types=1);

namespace Cms\Tests\External\Infrastructure\Controller;

use Cms\External\Application\ExternalResourceFinder;
use Cms\External\Domain\Availability;
use Cms\External\Domain\ExternalResourceRepository;
use Cms\External\Domain\ResourceStatus;
use Cms\Tests\External\Domain\ExternalResourceMother;
use PHPUnit\Framework\Attributes\DataProvider;
use Shared\Infrastructure\Http\SignedUrls;
use Shared\Tests\Domain\ClockMother;
use Shared\Tests\Infrastructure\Event\QueuedDomainEvents;
use Shared\Tests\Infrastructure\Http\RecordedSites;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;
use Symfony\Component\HttpFoundation\Response;

/**
 * Editing an external resource: saving what an editor sets, held to what
 * the resource is; filling it again from its address; checking whether its
 * address still leads to it, which the site learns at once; and seeing its
 * image, listing, searching and looking resources up.
 */
final class ExternalResourceEditingControllersTest extends ExternalResourceControllerTestCase
{
    use ClockSensitiveTrait;
    use QueuedDomainEvents;

    private const string OTHER = '01a1e2a0-0000-7000-8000-000000000002';

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();
        self::mockTime(ClockMother::NOW);
    }

    public function testItShowsTheEditorOfTheResource(): void
    {
        $client = self::createAuthenticatedClient();
        $this->saveResource(ExternalResourceMother::post());

        $client->request('GET', self::HOST.'/externals/'.ExternalResourceMother::ID);

        self::assertResponseIsSuccessful();
    }

    public function testItRespondsNotFoundForAResourceThatDoesNotExist(): void
    {
        $client = self::createAuthenticatedClient();

        $client->request('GET', self::HOST.'/externals/'.self::OTHER);

        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    public function testItSavesWhatTheEditorSetsOfAPost(): void
    {
        $client = self::createAuthenticatedClient();
        $this->saveResource(ExternalResourceMother::post());

        $this->post($client, 'cms_externals_update', self::HOST.'/externals/'.ExternalResourceMother::ID, [
            'url' => ExternalResourceMother::POST_URL,
            'title' => '  Lo que dijo   Bluesky ',
            'summary' => 'No se pinta en una publicación, pero se guarda.',
            'language' => 'en',
            'details' => ['author' => 'Bluesky', 'handle' => '@bsky.app', 'posted_on' => '2024-10-18', 'text' => "Primera línea\r\nSegunda", 'with_media' => '1'],
        ]);

        self::assertResponseRedirects('/externals/'.ExternalResourceMother::ID, Response::HTTP_SEE_OTHER);
        self::assertSame([['key' => 'cms.notices.external.saved', 'parameters' => []]], self::successNotices($client));
        $resource = $this->found();
        self::assertSame(
            ['Lo que dijo Bluesky', 'en', ['author' => 'Bluesky', 'handle' => '@bsky.app', 'posted_on' => '2024-10-18', 'text' => "Primera línea\nSegunda", 'with_media' => true]],
            [$resource->title, $resource->language, $resource->details],
        );
    }

    /**
     * Its address changes to another of its kind and provider, which names
     * it anew, and to nothing else.
     */
    public function testItChangesTheAddressOnlyToAnotherOfTheSameKindAndProvider(): void
    {
        $client = self::createAuthenticatedClient();
        $this->saveResource(ExternalResourceMother::video());

        $this->update($client, ['url' => 'https://www.youtube.com/shorts/aqz-KE-bpKQ']);
        self::assertSame(['https://www.youtube.com/shorts/aqz-KE-bpKQ', 'portrait'], [$this->found()->url, $this->found()->imageShape]);

        $this->update($client, ['url' => 'https://vimeo.com/1']);
        self::assertSame([['key' => 'errors.external.url.another_kind', 'parameters' => []]], self::errorNotices($client));
        self::assertSame('https://www.youtube.com/shorts/aqz-KE-bpKQ', $this->found()->url);
    }

    public function testItKeepsAnAddressOfAnotherResourceForThatOne(): void
    {
        $client = self::createAuthenticatedClient();
        $this->saveResource(ExternalResourceMother::article());
        $this->saveResource(ExternalResourceMother::article(id: self::OTHER, url: 'https://www.example.org/otra.html'));

        $this->update($client, ['url' => 'https://www.example.org/otra.html', 'details' => []]);

        self::assertSame([['key' => 'errors.external.url.taken', 'parameters' => ['%id%' => self::OTHER]]], self::errorNotices($client));
        self::assertSame(ExternalResourceMother::ARTICLE_URL, $this->found()->url);
    }

    /**
     * @param array<string, mixed>      $fields
     * @param array<string, int|string> $parameters
     */
    #[DataProvider('invalidFields')]
    public function testItSavesNothingInvalidAndTellsWhy(array $fields, string $key, array $parameters): void
    {
        $client = self::createAuthenticatedClient();
        $this->saveResource(ExternalResourceMother::study());

        $this->update($client, $fields + ['url' => ExternalResourceMother::STUDY_URL]);

        self::assertResponseRedirects('/externals/'.ExternalResourceMother::ID, Response::HTTP_SEE_OTHER);
        self::assertSame([['key' => $key, 'parameters' => $parameters]], self::errorNotices($client));
        self::assertEquals(ExternalResourceMother::study(), $this->storedResource(ExternalResourceMother::ID));
    }

    /**
     * @return iterable<string, array{array<string, mixed>, string, array<string, int|string>}>
     */
    public static function invalidFields(): iterable
    {
        yield 'a title too short' => [['title' => 'Un'], 'errors.external.title.too_short', ['%min%' => 3]];
        yield 'a title too long' => [['title' => str_repeat('a', 201)], 'errors.external.title.too_long', ['%max%' => 200]];
        yield 'a summary too long' => [['summary' => str_repeat('a', 301)], 'errors.external.details.too_long', ['%detail%' => 'summary', '%max%' => 300]];
        yield 'a year that is no number' => [['details' => ['year' => 'dos mil']], 'errors.external.details.not_a_number', ['%detail%' => 'year']];
        yield 'a year out of range' => [['details' => ['year' => '1200']], 'errors.external.details.year_out_of_range', ['%min%' => 1600, '%max%' => 2100]];
        yield 'authors on several lines' => [['details' => ['authors' => "Una\nOtra"]], 'errors.external.details.single_line', ['%detail%' => 'authors']];
        yield 'a journal too long' => [['details' => ['journal' => str_repeat('a', 201)]], 'errors.external.details.too_long', ['%detail%' => 'journal', '%max%' => 200]];
        yield 'an address of another kind' => [['url' => 'https://pubmed.example.org/1'], 'errors.external.url.another_kind', []];
    }

    public function testItRefusesADayThatIsNoDay(): void
    {
        $client = self::createAuthenticatedClient();
        $this->saveResource(ExternalResourceMother::article());

        $this->update($client, ['url' => ExternalResourceMother::ARTICLE_URL, 'details' => ['published_on' => '2026-02-30']]);

        self::assertSame([['key' => 'errors.external.details.not_a_date', 'parameters' => ['%detail%' => 'published_on']]], self::errorNotices($client));
    }

    /**
     * Filling reads the address anew: its title, its details and its image,
     * whose former file goes.
     */
    public function testItFillsTheResourceAgainFromItsAddress(): void
    {
        $client = self::createAuthenticatedClient();
        $this->saveResource(ExternalResourceMother::video(title: 'Título viejo'));
        self::keepImage(ExternalResourceMother::IMAGE);
        RecordedSites::answerJson('https://www.youtube.com/oembed?format=json&url='.rawurlencode(ExternalResourceMother::VIDEO_URL), ['title' => 'Título nuevo', 'thumbnail_url' => 'https://i.ytimg.com/vi/dQw4w9WgXcQ/hqdefault.jpg']);
        RecordedSites::answerFile('https://i.ytimg.com/vi/dQw4w9WgXcQ/hqdefault.jpg', self::PNG, 'image/png');

        $this->post($client, 'cms_externals_fill', self::HOST.'/externals/'.ExternalResourceMother::ID.'/fill');

        self::assertSame([['key' => 'cms.notices.external.filled', 'parameters' => []]], self::successNotices($client));
        $resource = $this->found();
        self::assertSame('Título nuevo', $resource->title);
        self::assertNotSame(ExternalResourceMother::IMAGE, $resource->image);
        self::assertFileExists(self::imageDirectory().'/'.$resource->image);
        self::assertFileDoesNotExist(self::imageDirectory().'/'.ExternalResourceMother::IMAGE);
    }

    public function testItChangesNothingWhenTheAddressCannotBeReadAgain(): void
    {
        $client = self::createAuthenticatedClient();
        $this->saveResource(ExternalResourceMother::video(title: 'Título viejo'));

        $this->post($client, 'cms_externals_fill', self::HOST.'/externals/'.ExternalResourceMother::ID.'/fill');

        self::assertSame([['key' => 'cms.notices.external.unread', 'parameters' => []]], self::errorNotices($client));
        self::assertEquals(ExternalResourceMother::video(title: 'Título viejo'), $this->storedResource(ExternalResourceMother::ID));
    }

    /**
     * @param array{status?: int, landed?: string} $answer     what the address answers: its status, or where it landed
     * @param array<string, int|string>            $parameters
     */
    #[DataProvider('checks')]
    public function testItChecksTheAddressAndKeepsWhatItFound(string $kind, array $answer, string $flash, string $notice, array $parameters, Availability $availability): void
    {
        $client = self::createAuthenticatedClient();
        $resource = 'video' === $kind ? ExternalResourceMother::video() : ExternalResourceMother::article();
        $this->saveResource($resource);
        $checked = 'video' === $kind ? 'https://www.youtube.com/oembed?format=json&url='.rawurlencode(ExternalResourceMother::VIDEO_URL) : ExternalResourceMother::ARTICLE_URL;

        if (isset($answer['landed'])) {
            RecordedSites::answerRedirected($checked, $answer['landed'], '<html></html>', 'text/html');
        } elseif (isset($answer['status'])) {
            RecordedSites::answer($checked, '{}', $answer['status'], ['content-type' => 'application/json']);
        } else {
            RecordedSites::failToReach($checked);
        }

        $this->post($client, 'cms_externals_check', self::HOST.'/externals/'.ExternalResourceMother::ID.'/check');

        self::assertResponseRedirects('/externals/'.ExternalResourceMother::ID, Response::HTTP_SEE_OTHER);
        self::assertSame([['key' => $notice, 'parameters' => $parameters]], 'success' === $flash ? self::successNotices($client) : self::errorNotices($client));
        $stored = $this->storedResource(ExternalResourceMother::ID);
        self::assertNotNull($stored);
        self::assertSame([$availability, ClockMother::NOW], [$stored->availability(), $stored->checkedAt()?->format('Y-m-d H:i:s')]);
        // A draft tells the site nothing.
        self::assertSame([], $this->queuedEvents());
    }

    /**
     * @return iterable<string, array{string, array{status?: int, landed?: string}, string, string, array<string, int|string>, Availability}>
     */
    public static function checks(): iterable
    {
        yield 'a video its provider answers for' => ['video', ['status' => 200], 'success', 'cms.notices.external.checked.available', ['%status%' => 200, '%url%' => ''], Availability::Available];
        yield 'a video its provider says is private' => ['video', ['status' => 401], 'success', 'cms.notices.external.checked.unavailable', ['%status%' => 401, '%url%' => ''], Availability::Unavailable];
        yield 'a video its provider no longer has' => ['video', ['status' => 404], 'success', 'cms.notices.external.checked.unavailable', ['%status%' => 404, '%url%' => ''], Availability::Unavailable];
        yield 'a page that is gone' => ['article', ['status' => 410], 'success', 'cms.notices.external.checked.unavailable', ['%status%' => 410, '%url%' => ''], Availability::Unavailable];
        yield 'a page that turns robots away' => ['article', ['status' => 403], 'error', 'cms.notices.external.checked.doubtful', ['%status%' => 403, '%url%' => ''], Availability::Available];
        yield 'a page whose server fails' => ['article', ['status' => 503], 'error', 'cms.notices.external.checked.doubtful', ['%status%' => 503, '%url%' => ''], Availability::Available];
        yield 'a page whose server never answers' => ['article', [], 'error', 'cms.notices.external.checked.doubtful', ['%status%' => '—', '%url%' => ''], Availability::Available];
        yield 'a page that moved' => ['article', ['landed' => 'https://www.example.org/2026/noticia.html'], 'error', 'cms.notices.external.checked.moved', ['%status%' => 200, '%url%' => 'https://www.example.org/2026/noticia.html'], Availability::Available];
        yield 'a page that only leads home now' => ['article', ['landed' => 'https://www.example.org/'], 'success', 'cms.notices.external.checked.unavailable', ['%status%' => 200, '%url%' => ''], Availability::Unavailable];
        yield 'a page rewritten only as sites do' => ['article', ['landed' => 'https://example.org/noticia.html/'], 'success', 'cms.notices.external.checked.available', ['%status%' => 200, '%url%' => ''], Availability::Available];
    }

    /**
     * A published resource tells the site at once when it stops being
     * available, and again when it is back, with no new version.
     */
    public function testItTellsTheSiteWhenAPublishedResourceStopsOrStartsBeingAvailable(): void
    {
        $client = self::createAuthenticatedClient();
        // One kernel, so the events of every request stay queued.
        $client->disableReboot();
        $this->saveResource(ExternalResourceMother::video(status: ResourceStatus::Published));
        $checked = 'https://www.youtube.com/oembed?format=json&url='.rawurlencode(ExternalResourceMother::VIDEO_URL);

        $told = function () use ($client): array {
            $this->post($client, 'cms_externals_check', self::HOST.'/externals/'.ExternalResourceMother::ID.'/check');

            // Each request tells its own, as the queue is emptied after it.
            return array_map(static fn ($event): array => [$event->name, $event->body], $this->queuedEvents());
        };

        RecordedSites::answer($checked, '', 404, []);
        self::assertSame([['cms.external.availability_changed', ['available' => false, 'revision' => 2]]], $told());
        // The same again changes nothing, and tells nothing.
        self::assertSame([], $told());
        RecordedSites::answer($checked, '{}', 200, []);
        self::assertSame([['cms.external.availability_changed', ['available' => true, 'revision' => 3]]], $told());
    }

    /**
     * A resource on the site is checked at the address it has there, which
     * may differ from the one being edited.
     */
    public function testItChecksTheAddressOnTheSite(): void
    {
        $client = self::createAuthenticatedClient();
        $onSite = ExternalResourceMother::version(\Shared\Domain\External\ResourceKind::Article, 'https://www.example.org/en-la-web.html', null, 'Una noticia', [], null);
        $this->saveResource(ExternalResourceMother::resource(ExternalResourceMother::ID, \Shared\Domain\External\ResourceKind::Article, null, ExternalResourceMother::article()->version(), ResourceStatus::Published, $onSite, Availability::Available));
        RecordedSites::answer('https://www.example.org/en-la-web.html', '', 404, []);

        $this->post($client, 'cms_externals_check', self::HOST.'/externals/'.ExternalResourceMother::ID.'/check');

        self::assertSame(['https://www.example.org/en-la-web.html'], RecordedSites::asked());
        self::assertSame([false, 'https://www.example.org/en-la-web.html'], [$this->found()->available, $this->found()->checkedUrl]);
    }

    public function testItServesTheImageToWhoeverHoldsASignedLink(): void
    {
        $client = self::createClient();
        $this->saveResource(ExternalResourceMother::video());
        self::keepImage(ExternalResourceMother::IMAGE);
        $url = self::HOST.'/preview/externals/'.ExternalResourceMother::ID.'/image/'.ExternalResourceMother::IMAGE;
        $signed = self::getContainer()->get(SignedUrls::class)->sign($url, ClockMother::now()->modify('+1 day'));

        $client->request('GET', $signed);
        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('Content-Type', 'image/webp');

        $client->request('GET', $url);
        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    public function testItServesNoImageTheResourceDoesNotHave(): void
    {
        $client = self::createClient();
        $this->saveResource(ExternalResourceMother::video());
        $url = self::HOST.'/preview/externals/'.ExternalResourceMother::ID.'/image/'.ExternalResourceMother::OTHER_IMAGE;

        $client->request('GET', self::getContainer()->get(SignedUrls::class)->sign($url, ClockMother::now()->modify('+1 day')));

        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    #[DataProvider('lists')]
    public function testItListsTheResourcesSearchedAllOrThoseUnavailable(string $query): void
    {
        $client = self::createAuthenticatedClient();
        $this->saveResource(ExternalResourceMother::video(status: ResourceStatus::Published, availability: Availability::Unavailable));
        $this->saveResource(ExternalResourceMother::article(id: self::OTHER));

        $client->request('GET', self::HOST.'/externals'.$query);

        self::assertResponseIsSuccessful();
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function lists(): iterable
    {
        yield 'all' => [''];
        yield 'those unavailable' => ['?filter=unavailable'];
        yield 'searched' => ['?title=vídeo'];
        yield 'none found' => ['?title=nada'];
        yield 'a later page' => ['?page=2'];
    }

    public function testItRespondsNotFoundForAPageOfTheListThatCannotBe(): void
    {
        $client = self::createAuthenticatedClient();

        $client->request('GET', self::HOST.'/externals?page=0');

        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    public function testItSearchesAndLooksUpResourcesForTheFieldsThatPickThem(): void
    {
        $client = self::createAuthenticatedClient();
        $this->saveResource(ExternalResourceMother::video(status: ResourceStatus::Published));
        $this->saveResource(ExternalResourceMother::article(id: self::OTHER, status: ResourceStatus::Published, availability: Availability::Unavailable));

        $client->request('GET', self::HOST.'/externals/search?title=vídeo');
        self::assertSame(['externals' => [['id' => ExternalResourceMother::ID, 'title' => 'Un vídeo (Video of YouTube)', 'status' => 'Published', 'published' => true]], 'more' => false], $this->json($client));

        $client->request('GET', self::HOST.'/externals/lookup?ids='.self::OTHER.',no-es-un-id');
        self::assertSame(['externals' => [['id' => self::OTHER, 'title' => 'Una noticia (Article)', 'status' => 'Unavailable', 'published' => false]]], $this->json($client));

        $client->request('GET', self::HOST.'/externals/search?page=0');
        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    public function testItTellsWhatUsesTheResource(): void
    {
        $client = self::createAuthenticatedClient();
        $this->saveResource(ExternalResourceMother::video());

        $client->request('GET', self::HOST.'/externals/'.ExternalResourceMother::ID.'/relations');
        self::assertResponseIsSuccessful();

        $client->request('GET', self::HOST.'/externals/'.self::OTHER.'/relations');
        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    /**
     * @param array<string, mixed> $fields
     */
    private function update(\Symfony\Bundle\FrameworkBundle\KernelBrowser $client, array $fields): void
    {
        $this->post($client, 'cms_externals_update', self::HOST.'/externals/'.ExternalResourceMother::ID, $fields + ['title' => 'Un título', 'summary' => '', 'language' => 'es', 'details' => []]);
    }

    private function found(): \Cms\External\Application\ExternalResourceResponse
    {
        self::getContainer()->get(\Doctrine\ORM\EntityManagerInterface::class)->clear();

        return new ExternalResourceFinder(ExternalResourceMother::ID)(self::getContainer()->get(ExternalResourceRepository::class));
    }

    /**
     * @return array<mixed>
     */
    private function json(\Symfony\Bundle\FrameworkBundle\KernelBrowser $client): array
    {
        $data = json_decode((string) $client->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);
        self::assertIsArray($data);

        return $data;
    }
}
