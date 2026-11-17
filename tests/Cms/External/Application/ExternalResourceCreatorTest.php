<?php

declare(strict_types=1);

namespace Cms\Tests\External\Application;

use Cms\External\Application\ExternalResourceCreator;
use Cms\External\Application\ExternalResourcePublisher;
use Cms\External\Domain\ExternalResource;
use Cms\External\Domain\ExternalResourcePublished;
use Cms\External\Domain\ExternalResourceRepository;
use Cms\External\Domain\InvalidResourceUrl;
use Cms\External\Domain\Language;
use Cms\External\Domain\Reading;
use Cms\External\Domain\ResourceImageFetcher;
use Cms\External\Domain\ResourceImagePath;
use Cms\External\Domain\ResourceImageStorage;
use Cms\External\Domain\ResourceReader;
use Cms\External\Domain\ResourceStatus;
use Cms\External\Domain\ResourceUrl;
use Cms\Tests\External\Domain\ExternalResourceMother;
use Cms\Tests\External\Domain\ReadingMother;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shared\Domain\Event\EventBus;
use Shared\Domain\External\ImageShape;
use Shared\Domain\External\ResourceKind;
use Shared\Tests\Domain\ClockMother;
use Shared\Tests\Domain\DomainErrorAssertions;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;

final class ExternalResourceCreatorTest extends TestCase
{
    use ClockSensitiveTrait;
    use DomainErrorAssertions;
    use ExternalResourceImageFile;

    private const string OWN_HOST = 'www.blogautista.es';

    #[\Override]
    protected function setUp(): void
    {
        self::mockTime(ClockMother::now());
    }

    /**
     * The image read is kept under a name of its own, as the image of the
     * draft.
     */
    public function testItStartsADraftFromWhatReadingItsAddressTells(): void
    {
        $file = $this->landscapeImageFile();
        $kept = null;
        $repository = $this->createMock(ExternalResourceRepository::class);
        $repository->method('findByUrl')->willReturn(null);
        $repository->expects(self::once())
            ->method('save')
            ->with(self::callback(static function (ExternalResource $resource) use (&$kept): bool {
                return [ExternalResourceMother::ID, ResourceKind::Video, 'youtube', ResourceStatus::Draft, 'Un vídeo leído', ExternalResourceMother::VIDEO, $kept]
                    === [$resource->id()->value(), $resource->kind(), $resource->provider(), $resource->status(), $resource->version()->title()->value(), $resource->version()->locator(), $resource->version()->image()?->value()];
            }));
        $reader = $this->createMock(ResourceReader::class);
        $reader->expects(self::once())->method('read')->with(self::equalTo(new ResourceUrl(ExternalResourceMother::VIDEO_URL)))->willReturn(ReadingMother::video());
        $fetcher = $this->createMock(ResourceImageFetcher::class);
        $fetcher->expects(self::once())->method('fetch')->with(ReadingMother::IMAGE_URL, ImageShape::Landscape)->willReturn($file);
        $storage = $this->createMock(ResourceImageStorage::class);
        $storage->expects(self::once())->method('save')->with(self::callback(static function (ResourceImagePath $image) use (&$kept): bool {
            $kept = $image->value();

            return true;
        }), self::identicalTo($file));
        $storage->expects(self::never())->method('delete');

        self::assertTrue(new ExternalResourceCreator(ExternalResourceMother::ID, ExternalResourceMother::VIDEO_URL, self::OWN_HOST)($repository, self::createStub(EventBus::class), $reader, $fetcher, $storage));
    }

    /**
     * The address is read written in one way: its scheme and host in
     * lowercase, without the default port of its scheme, its fragment, nor
     * the parameters that only track who shared it.
     */
    #[DataProvider('writtenAddresses')]
    public function testItReadsTheAddressWrittenInOneWay(string $written, string $read): void
    {
        $repository = self::createStub(ExternalResourceRepository::class);
        $repository->method('findByUrl')->willReturn(null);
        $asked = null;
        $reader = self::createStub(ResourceReader::class);
        $reader->method('read')->willReturnCallback(static function (ResourceUrl $url) use (&$asked): Reading {
            $asked = $url->value();

            return ReadingMother::video();
        });

        new ExternalResourceCreator(ExternalResourceMother::ID, $written, self::OWN_HOST)($repository, self::createStub(EventBus::class), $reader, self::createStub(ResourceImageFetcher::class), self::createStub(ResourceImageStorage::class));

        self::assertSame($read, $asked);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function writtenAddresses(): iterable
    {
        yield 'in capitals, on the port of HTTP' => ['HTTP://Example.ORG:80/Una-Noticia#arriba', 'http://example.org/Una-Noticia'];
        yield 'on the port of HTTPS' => ['https://example.org:443/', 'https://example.org/'];
        yield 'over HTTP on the port of HTTPS' => ['http://example.org:443/', 'http://example.org:443/'];
        yield 'over HTTPS on the port of HTTP' => ['https://example.org:80/', 'https://example.org:80/'];
        yield 'with what tracks who shared it' => ['https://example.org/?id=7&utm_source=red&fbclid=x', 'https://example.org/?id=7'];
    }

    /**
     * What the resource tells is kept as a title can be: cut with an
     * ellipsis when too long, the host of its address when it tells none
     * long enough; its summary cut to what one can hold, and its language
     * Spanish unless it tells one.
     */
    #[DataProvider('readings')]
    public function testItKeepsWhatTheResourceTellsAsItCanBeKept(Reading $reading, string $title, ?string $summary, Language $language): void
    {
        $repository = $this->createMock(ExternalResourceRepository::class);
        $repository->method('findByUrl')->willReturn(null);
        $repository->expects(self::once())->method('save')->with(self::callback(static fn (ExternalResource $resource): bool => [$title, $summary, $language]
            === [$resource->version()->title()->value(), $resource->version()->summary(), $resource->version()->language()]));
        $reader = self::createStub(ResourceReader::class);
        $reader->method('read')->willReturn($reading);

        new ExternalResourceCreator(ExternalResourceMother::ID, ExternalResourceMother::ARTICLE_URL, self::OWN_HOST)($repository, self::createStub(EventBus::class), $reader, self::createStub(ResourceImageFetcher::class), self::createStub(ResourceImageStorage::class));
    }

    /**
     * @return iterable<string, array{Reading, string, ?string, Language}>
     */
    public static function readings(): iterable
    {
        $article = static fn (?string $title, ?string $summary = null, ?Language $language = null): Reading => new Reading(ResourceKind::Article, null, null, $title, $summary, $language, [], [], true);

        yield 'a title too long, cut before the space it ends in' => [$article(str_repeat('á', 198).' bcd'), str_repeat('á', 198).'…', null, Language::Spanish];
        yield 'a title as long as one can be' => [$article(str_repeat('á', 200)), str_repeat('á', 200), null, Language::Spanish];
        yield 'a title written with spaces' => [$article("  Una \n noticia "), 'Una noticia', null, Language::Spanish];
        yield 'a title of three letters' => [$article('ñúa'), 'ñúa', null, Language::Spanish];
        yield 'a title too short' => [$article('ñú'), 'example.org', null, Language::Spanish];
        yield 'no title' => [$article(null), 'example.org', null, Language::Spanish];
        yield 'a summary too long' => [$article('Una noticia', str_repeat('é', 400)), 'Una noticia', str_repeat('é', 300), Language::Spanish];
        yield 'a language of its own' => [$article('Una noticia', null, Language::English), 'Una noticia', null, Language::English];
    }

    public function testItRefusesAnAddressOfTheBlogWrittenInCapitals(): void
    {
        $reader = $this->createMock(ResourceReader::class);
        $reader->expects(self::never())->method('read');

        $this->expectExceptionObject(InvalidResourceUrl::ownSite());

        new ExternalResourceCreator(ExternalResourceMother::ID, 'https://blogautista.es/sobre-mi.html', 'WWW.BlogAutista.ES')(self::createStub(ExternalResourceRepository::class), self::createStub(EventBus::class), $reader, self::createStub(ResourceImageFetcher::class), self::createStub(ResourceImageStorage::class));
    }

    public function testItKeepsAnAddressAsLongAsOneCanBe(): void
    {
        $url = 'https://example.org/'.str_repeat('a', 1980);
        $repository = $this->createMock(ExternalResourceRepository::class);
        $repository->method('findByUrl')->willReturn(null);
        $repository->expects(self::once())->method('save')->with(self::callback(static fn (ExternalResource $resource): bool => $url === $resource->version()->url()->value()));
        $reader = self::createStub(ResourceReader::class);
        $reader->method('read')->willReturn(new Reading(ResourceKind::Article, null, null, 'Una noticia', null, null, [], [], true));

        new ExternalResourceCreator(ExternalResourceMother::ID, $url, self::OWN_HOST)($repository, self::createStub(EventBus::class), $reader, self::createStub(ResourceImageFetcher::class), self::createStub(ResourceImageStorage::class));
    }

    /**
     * A new resource has never been on the site: publishing it is its
     * first revision there.
     */
    public function testItStartsAResourceTheSiteHasNeverHad(): void
    {
        $saved = null;
        $repository = self::createStub(ExternalResourceRepository::class);
        $repository->method('findByUrl')->willReturn(null);
        $repository->method('save')->willReturnCallback(static function (ExternalResource $resource) use (&$saved): void {
            $saved = $resource;
        });
        $reader = self::createStub(ResourceReader::class);
        $reader->method('read')->willReturn(new Reading(ResourceKind::Article, null, null, 'Una noticia', null, null, ['publisher' => 'El Medio'], [], true));

        new ExternalResourceCreator(ExternalResourceMother::ID, ExternalResourceMother::ARTICLE_URL, self::OWN_HOST)($repository, self::createStub(EventBus::class), $reader, self::createStub(ResourceImageFetcher::class), self::createStub(ResourceImageStorage::class));

        self::assertInstanceOf(ExternalResource::class, $saved);
        $published = self::createStub(ExternalResourceRepository::class);
        $published->method('find')->willReturn($saved);
        $eventBus = $this->createMock(EventBus::class);
        $eventBus->expects(self::once())->method('publish')->with(self::callback(static fn (ExternalResourcePublished $event): bool => 1 === $event->revision()));

        new ExternalResourcePublisher(ExternalResourceMother::ID)($published, $eventBus, self::createStub(ResourceImageStorage::class));
    }

    public function testItTellsWhenTheAddressCouldNotBeReached(): void
    {
        $repository = $this->createMock(ExternalResourceRepository::class);
        $repository->method('findByUrl')->willReturn(null);
        $repository->expects(self::once())->method('save');
        $reader = self::createStub(ResourceReader::class);
        $reader->method('read')->willReturn(ReadingMother::video(images: [], reached: false));

        self::assertFalse(new ExternalResourceCreator(ExternalResourceMother::ID, ExternalResourceMother::VIDEO_URL, self::OWN_HOST)($repository, self::createStub(EventBus::class), $reader, self::createStub(ResourceImageFetcher::class), self::createStub(ResourceImageStorage::class)));
    }

    /**
     * Saving fails, so the image kept for it is left behind by no one.
     */
    public function testItRemovesTheImageKeptWhenTheResourceCannotBeSaved(): void
    {
        $failure = new \RuntimeException('The database is gone.');
        $repository = self::createStub(ExternalResourceRepository::class);
        $repository->method('findByUrl')->willReturn(null);
        $repository->method('save')->willThrowException($failure);
        $reader = self::createStub(ResourceReader::class);
        $reader->method('read')->willReturn(ReadingMother::video());
        $fetcher = self::createStub(ResourceImageFetcher::class);
        $fetcher->method('fetch')->willReturn($this->landscapeImageFile());
        $storage = $this->createMock(ResourceImageStorage::class);
        $storage->expects(self::once())->method('save');
        $storage->expects(self::once())->method('delete');

        $this->expectExceptionObject($failure);

        new ExternalResourceCreator(ExternalResourceMother::ID, ExternalResourceMother::VIDEO_URL, self::OWN_HOST)($repository, self::createStub(EventBus::class), $reader, $fetcher, $storage);
    }

    /**
     * @param array<string, int|string> $parameters
     */
    #[DataProvider('refusedAddresses')]
    public function testItRefusesWhatCannotBeTheAddressOfAResourceWithoutReadingIt(string $url, string $key, array $parameters): void
    {
        $repository = $this->createMock(ExternalResourceRepository::class);
        $repository->expects(self::never())->method('save');
        $reader = $this->createMock(ResourceReader::class);
        $reader->expects(self::never())->method('read');

        self::assertTells($key, $parameters, static fn () => new ExternalResourceCreator(ExternalResourceMother::ID, $url, self::OWN_HOST)($repository, self::createStub(EventBus::class), $reader, self::createStub(ResourceImageFetcher::class), self::createStub(ResourceImageStorage::class)));
    }

    /**
     * @return iterable<string, array{string, string, array<string, int|string>}>
     */
    public static function refusedAddresses(): iterable
    {
        yield 'none' => ['vídeo de YouTube', 'errors.external.url.not_an_address', []];
        yield 'one with a user' => ['https://yo:secreto@example.org/', 'errors.external.url.not_an_address', []];
        yield 'one of another scheme' => ['ftp://example.org/informe.pdf', 'errors.external.url.not_an_address', []];
        yield 'one too long' => ['https://example.org/'.str_repeat('a', 2000), 'errors.external.url.too_long', ['%max%' => 2000]];
        yield 'one with a user and no password' => ['https://yo@example.org/', 'errors.external.url.not_an_address', []];
        yield 'one with a password and no user' => ['https://:secreto@example.org/', 'errors.external.url.not_an_address', []];
        yield 'one with spaces within' => ['https://example.org/una noticia', 'errors.external.url.not_an_address', []];
        yield 'one of a host without a domain' => ['https://localhost/', 'errors.external.url.not_an_address', []];
        yield 'one of a host no domain can be' => ['https://exa_mple.org/', 'errors.external.url.not_an_address', []];
        yield 'one of a domain followed by what no host can have' => ['https://example.org_x/', 'errors.external.url.not_an_address', []];
        yield 'one with a space of another script within' => ["https://example.org/una\u{00A0}noticia", 'errors.external.url.not_an_address', []];
    }

    public function testItRefusesAnAddressOfTheBlogItselfWithoutReadingIt(): void
    {
        $repository = $this->createMock(ExternalResourceRepository::class);
        $repository->expects(self::never())->method('save');
        $reader = $this->createMock(ResourceReader::class);
        $reader->expects(self::never())->method('read');

        $this->expectExceptionObject(InvalidResourceUrl::ownSite());

        new ExternalResourceCreator(ExternalResourceMother::ID, 'https://blogautista.es/sobre-mi.html', self::OWN_HOST)($repository, self::createStub(EventBus::class), $reader, self::createStub(ResourceImageFetcher::class), self::createStub(ResourceImageStorage::class));
    }

    public function testItRefusesAnAddressAnotherResourceHasWithoutReadingIt(): void
    {
        $holder = '01a1e2a0-0000-7000-8000-000000000002';
        $repository = $this->createMock(ExternalResourceRepository::class);
        $repository->expects(self::once())->method('findByUrl')->with(self::equalTo(new ResourceUrl(ExternalResourceMother::VIDEO_URL)))->willReturn(ExternalResourceMother::video(id: $holder));
        $repository->expects(self::never())->method('save');
        $reader = $this->createMock(ResourceReader::class);
        $reader->expects(self::never())->method('read');

        $this->expectExceptionObject(InvalidResourceUrl::taken($holder));

        new ExternalResourceCreator(ExternalResourceMother::ID, ExternalResourceMother::VIDEO_URL, self::OWN_HOST)($repository, self::createStub(EventBus::class), $reader, self::createStub(ResourceImageFetcher::class), self::createStub(ResourceImageStorage::class));
    }
}
