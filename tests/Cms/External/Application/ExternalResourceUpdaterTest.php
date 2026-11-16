<?php

declare(strict_types=1);

namespace Cms\Tests\External\Application;

use Cms\External\Application\ExternalResourceNotFound;
use Cms\External\Application\ExternalResourceUpdater;
use Cms\External\Domain\Availability;
use Cms\External\Domain\ExternalResource;
use Cms\External\Domain\ExternalResourceRepository;
use Cms\External\Domain\InvalidResourceUrl;
use Cms\External\Domain\ResourceImageStorage;
use Cms\External\Domain\ResourceStatus;
use Cms\External\Domain\ResourceUrl;
use Cms\Tests\External\Domain\ExternalResourceMother;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shared\Domain\Event\EventBus;
use Shared\Domain\External\ResourceKind;
use Shared\Tests\Domain\ClockMother;
use Shared\Tests\Domain\DomainErrorAssertions;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;

final class ExternalResourceUpdaterTest extends TestCase
{
    use ClockSensitiveTrait;
    use DomainErrorAssertions;

    private const string OTHER_VIDEO_URL = 'https://www.youtube.com/watch?v=aaaaaaaaaaa';

    private const string SAVED_AT = '2026-11-15 10:00:00';

    #[\Override]
    protected function setUp(): void
    {
        self::mockTime(ClockMother::now(self::SAVED_AT));
    }

    /**
     * Another address of the provider is another video of it, which the
     * provider names anew; the image stays.
     */
    public function testItSavesWhatTheEditorSetsInTheVersionBeingEdited(): void
    {
        $repository = $this->createMock(ExternalResourceRepository::class);
        $repository->method('find')->willReturn(ExternalResourceMother::video(status: ResourceStatus::Published));
        $repository->expects(self::once())->method('findByUrl')->with(self::equalTo(new ResourceUrl(self::OTHER_VIDEO_URL)))->willReturn(null);
        $repository->expects(self::once())->method('save')->with(self::callback(static fn (ExternalResource $resource): bool => $resource->version()->equals(ExternalResourceMother::version(ResourceKind::Video, self::OTHER_VIDEO_URL, 'aaaaaaaaaaa', 'Otro título', [], ExternalResourceMother::IMAGE, ''))
            && $resource->checked()->equals(ExternalResourceMother::version(ResourceKind::Video, ExternalResourceMother::VIDEO_URL, ExternalResourceMother::VIDEO, 'Un vídeo', [], ExternalResourceMother::IMAGE))
            && ClockMother::now(self::SAVED_AT)->format(\DATE_ATOM) === $resource->timeline()->updatedAt()->format(\DATE_ATOM)));

        new ExternalResourceUpdater(ExternalResourceMother::ID, self::OTHER_VIDEO_URL, 'Otro título', '', 'es', [])($repository, self::createStub(EventBus::class), self::createStub(ResourceImageStorage::class));
    }

    /**
     * What was found of the address of a draft tells nothing of another one,
     * which is available until it is checked; a resource on the site keeps
     * the availability of the address there, whatever the draft says.
     */
    #[DataProvider('checkedAddresses')]
    public function testItKeepsTheAvailabilityOfTheAddressChecked(ResourceStatus $status, string $url, Availability $availability): void
    {
        $repository = $this->createMock(ExternalResourceRepository::class);
        $repository->method('find')->willReturn(ExternalResourceMother::video(status: $status, availability: Availability::Unavailable));
        $repository->method('findByUrl')->willReturn(null);
        $repository->expects(self::once())->method('save')->with(self::callback(static fn (ExternalResource $resource): bool => $availability === $resource->availability()));

        new ExternalResourceUpdater(ExternalResourceMother::ID, $url, 'Un vídeo', '', 'es', [])($repository, self::createStub(EventBus::class), self::createStub(ResourceImageStorage::class));
    }

    /**
     * @return iterable<string, array{ResourceStatus, string, Availability}>
     */
    public static function checkedAddresses(): iterable
    {
        yield 'a draft at another address' => [ResourceStatus::Draft, self::OTHER_VIDEO_URL, Availability::Available];
        yield 'a draft at the address checked' => [ResourceStatus::Draft, ExternalResourceMother::VIDEO_URL, Availability::Unavailable];
        yield 'a resource on the site, edited at another address' => [ResourceStatus::Published, self::OTHER_VIDEO_URL, Availability::Unavailable];
        yield 'a resource off the site, edited at another address' => [ResourceStatus::Unpublished, self::OTHER_VIDEO_URL, Availability::Unavailable];
    }

    public function testItKeepsTheAddressItHasAlready(): void
    {
        $repository = $this->createMock(ExternalResourceRepository::class);
        $repository->method('find')->willReturn(ExternalResourceMother::video());
        $repository->method('findByUrl')->willReturn(ExternalResourceMother::video());
        $repository->expects(self::once())->method('save');

        new ExternalResourceUpdater(ExternalResourceMother::ID, ExternalResourceMother::VIDEO_URL, 'Otro título', '', 'es', [])($repository, self::createStub(EventBus::class), self::createStub(ResourceImageStorage::class));
    }

    public function testItRefusesAnAddressAnotherResourceHas(): void
    {
        $holder = '01a1e2a0-0000-7000-8000-000000000002';
        $repository = $this->createMock(ExternalResourceRepository::class);
        $repository->method('find')->willReturn(ExternalResourceMother::video());
        $repository->method('findByUrl')->willReturn(ExternalResourceMother::video(id: $holder, url: self::OTHER_VIDEO_URL));
        $repository->expects(self::never())->method('save');

        $this->expectExceptionObject(InvalidResourceUrl::taken($holder));

        new ExternalResourceUpdater(ExternalResourceMother::ID, self::OTHER_VIDEO_URL, 'Un vídeo', '', 'es', [])($repository, self::createStub(EventBus::class), self::createStub(ResourceImageStorage::class));
    }

    public function testItRefusesAnAddressOfAnotherKindOfResource(): void
    {
        $repository = $this->createMock(ExternalResourceRepository::class);
        $repository->method('find')->willReturn(ExternalResourceMother::video());
        $repository->expects(self::never())->method('save');

        $this->expectExceptionObject(InvalidResourceUrl::anotherKind());

        new ExternalResourceUpdater(ExternalResourceMother::ID, ExternalResourceMother::ARTICLE_URL, 'Un vídeo', '', 'es', [])($repository, self::createStub(EventBus::class), self::createStub(ResourceImageStorage::class));
    }

    /**
     * @param array{url?: string, title?: string, summary?: string, details?: array<string, string>} $fields
     * @param array<string, int|string>                                                              $parameters
     */
    #[DataProvider('invalidFields')]
    public function testItSavesNothingInvalidAndTellsWhy(ExternalResource $resource, array $fields, string $key, array $parameters): void
    {
        $repository = $this->createMock(ExternalResourceRepository::class);
        $repository->method('find')->willReturn($resource);
        $repository->expects(self::never())->method('save');
        $fields += ['url' => $resource->version()->url()->value(), 'title' => 'Un título', 'summary' => '', 'details' => []];

        self::assertTells($key, $parameters, static fn () => new ExternalResourceUpdater(ExternalResourceMother::ID, $fields['url'], $fields['title'], $fields['summary'], 'es', $fields['details'])($repository, self::createStub(EventBus::class), self::createStub(ResourceImageStorage::class)));
    }

    /**
     * @return iterable<string, array{ExternalResource, array<string, mixed>, string, array<string, int|string>}>
     */
    public static function invalidFields(): iterable
    {
        $study = ExternalResourceMother::study();

        yield 'a title too short' => [$study, ['title' => 'Un'], 'errors.external.title.too_short', ['%min%' => 3]];
        yield 'a title too long' => [$study, ['title' => str_repeat('a', 201)], 'errors.external.title.too_long', ['%max%' => 200]];
        yield 'a summary too long' => [$study, ['summary' => str_repeat('a', 301)], 'errors.external.details.too_long', ['%detail%' => 'summary', '%max%' => 300]];
        yield 'a year that is no number' => [$study, ['details' => ['year' => 'dos mil']], 'errors.external.details.not_a_number', ['%detail%' => 'year']];
        yield 'a year out of range' => [$study, ['details' => ['year' => '1200']], 'errors.external.details.year_out_of_range', ['%min%' => 1600, '%max%' => 2100]];
        yield 'authors on several lines' => [$study, ['details' => ['authors' => "Una\nOtra"]], 'errors.external.details.single_line', ['%detail%' => 'authors']];
        yield 'a journal too long' => [$study, ['details' => ['journal' => str_repeat('a', 201)]], 'errors.external.details.too_long', ['%detail%' => 'journal', '%max%' => 200]];
        yield 'an address of another kind' => [$study, ['url' => 'https://pubmed.example.org/1'], 'errors.external.url.another_kind', []];
        yield 'a day that is no day' => [ExternalResourceMother::article(), ['details' => ['published_on' => '2026-02-30']], 'errors.external.details.not_a_date', ['%detail%' => 'published_on']];
        yield 'a title of two letters, one of them accented' => [$study, ['title' => 'ñú'], 'errors.external.title.too_short', ['%min%' => 3]];
        yield 'a year with letters after it' => [$study, ['details' => ['year' => '2013a']], 'errors.external.details.not_a_number', ['%detail%' => 'year']];
        yield 'a year with letters before it' => [$study, ['details' => ['year' => 'a2013']], 'errors.external.details.not_a_number', ['%detail%' => 'year']];
        yield 'a year before the first' => [$study, ['details' => ['year' => '1599']], 'errors.external.details.year_out_of_range', ['%min%' => 1600, '%max%' => 2100]];
        yield 'a year after the last' => [$study, ['details' => ['year' => '2101']], 'errors.external.details.year_out_of_range', ['%min%' => 1600, '%max%' => 2100]];
        yield 'a journal one accented letter too long' => [$study, ['details' => ['journal' => str_repeat('á', 201)]], 'errors.external.details.too_long', ['%detail%' => 'journal', '%max%' => 200]];
        yield 'an address of the same kind by another provider' => [ExternalResourceMother::video(), ['url' => 'https://www.tiktok.com/@scout2015/video/6718335390845095173'], 'errors.external.url.another_kind', []];
    }

    /**
     * What is written as it is meant: a title and a summary without the
     * spaces around them, details on one line collapsed, numbers without
     * spaces, and what is just within its limits, kept.
     *
     * @param array{title?: string, summary?: string, details?: array<string, string>} $fields
     * @param array<string, mixed>                                                     $saved  what the version being edited holds once saved
     */
    #[DataProvider('validFields')]
    public function testItSavesWhatIsWithinItsLimitsAsItIsMeant(array $fields, array $saved): void
    {
        $repository = $this->createMock(ExternalResourceRepository::class);
        $repository->method('find')->willReturn(ExternalResourceMother::study());
        $repository->expects(self::once())->method('save')->with(self::callback(static fn (ExternalResource $resource): bool => $saved === array_intersect_key(['title' => $resource->version()->title()->value(), 'summary' => $resource->version()->summary()] + $resource->version()->details()->toArray(), $saved)));
        $fields += ['title' => 'Un estudio', 'summary' => '', 'details' => []];

        new ExternalResourceUpdater(ExternalResourceMother::ID, ExternalResourceMother::STUDY_URL, $fields['title'], $fields['summary'], 'en', $fields['details'])($repository, self::createStub(EventBus::class), self::createStub(ResourceImageStorage::class));
    }

    /**
     * @return iterable<string, array{array<string, mixed>, array<string, mixed>}>
     */
    public static function validFields(): iterable
    {
        yield 'a title and a summary written with spaces around' => [['title' => '  Un  estudio ', 'summary' => "  Un\n resumen  "], ['title' => 'Un estudio', 'summary' => 'Un resumen']];
        yield 'a title of three letters' => [['title' => 'ñúa'], ['title' => 'ñúa']];
        yield 'a title as long as it can be' => [['title' => str_repeat('á', 200)], ['title' => str_repeat('á', 200)]];
        yield 'authors written with spaces between' => [['details' => ['authors' => "Ana  \t Ruiz"]], ['authors' => 'Ana Ruiz']];
        yield 'a journal as long as it can be' => [['details' => ['journal' => str_repeat('á', 200)]], ['journal' => str_repeat('á', 200)]];
        yield 'a year written with spaces around' => [['details' => ['year' => ' 2013 ']], ['year' => 2013]];
        yield 'the first year' => [['details' => ['year' => '1600']], ['year' => 1600]];
        yield 'the last year' => [['details' => ['year' => '2100']], ['year' => 2100]];
    }

    public function testItRefusesALanguageTheResourcesAreNotIn(): void
    {
        $repository = $this->createMock(ExternalResourceRepository::class);
        $repository->method('find')->willReturn(ExternalResourceMother::study());
        $repository->expects(self::never())->method('save');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('"xx" is not a language of the resources.');

        new ExternalResourceUpdater(ExternalResourceMother::ID, ExternalResourceMother::STUDY_URL, 'Un estudio', '', 'xx', [])($repository, self::createStub(EventBus::class), self::createStub(ResourceImageStorage::class));
    }

    public function testItThrowsWhenTheResourceDoesNotExist(): void
    {
        $repository = self::createStub(ExternalResourceRepository::class);
        $repository->method('find')->willReturn(null);

        $this->expectExceptionObject(ExternalResourceNotFound::withId(ExternalResourceMother::ID));

        new ExternalResourceUpdater(ExternalResourceMother::ID, ExternalResourceMother::VIDEO_URL, 'Un vídeo', '', 'es', [])($repository, self::createStub(EventBus::class), self::createStub(ResourceImageStorage::class));
    }
}
