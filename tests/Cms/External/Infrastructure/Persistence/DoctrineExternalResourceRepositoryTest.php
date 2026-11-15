<?php

declare(strict_types=1);

namespace Cms\Tests\External\Infrastructure\Persistence;

use Cms\External\Application\ExternalResourceSearcher;
use Cms\External\Domain\Availability;
use Cms\External\Domain\ExternalResource;
use Cms\External\Domain\ExternalResourceId;
use Cms\External\Domain\ExternalResourceRepository;
use Cms\External\Domain\ResourceStatus;
use Cms\Tests\External\Domain\ExternalResourceMother;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use Shared\Domain\External\ResourceKind;
use Shared\Tests\Domain\DomainErrorAssertions;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * External resources kept and read back as they were, each kind with its
 * details and both its versions; found by their ids, their addresses and
 * their titles; each address kept by one resource alone.
 */
final class DoctrineExternalResourceRepositoryTest extends KernelTestCase
{
    use DomainErrorAssertions;

    private const string OTHER = '01a1e2a0-0000-7000-8000-000000000002';

    private const string THIRD = '01a1e2a0-0000-7000-8000-000000000003';

    #[DataProvider('resources')]
    public function testItReadsBackTheResourceAsItWasSaved(ExternalResource $resource): void
    {
        $this->repository()->save($resource);
        self::getContainer()->get(EntityManagerInterface::class)->clear();

        self::assertEquals($resource, $this->repository()->find($resource->id()));
    }

    /**
     * @return iterable<string, array{ExternalResource}>
     */
    public static function resources(): iterable
    {
        yield 'a video, as a draft' => [ExternalResourceMother::video()];
        yield 'a video on the site with another version' => [ExternalResourceMother::video(title: 'Editado', status: ResourceStatus::Published, published: ExternalResourceMother::video()->version())];
        yield 'a post, unavailable' => [ExternalResourceMother::post(status: ResourceStatus::Published, availability: Availability::Unavailable)];
        yield 'an article, unpublished' => [ExternalResourceMother::article(image: ExternalResourceMother::IMAGE, status: ResourceStatus::Unpublished)];
        yield 'a study' => [ExternalResourceMother::study()];
        yield 'an audio' => [ExternalResourceMother::resource(ExternalResourceMother::ID, ResourceKind::Audio, 'spotify', ExternalResourceMother::version(ResourceKind::Audio, 'https://open.spotify.com/show/2MAi0BvDc6GTFvKFPXnkCL', 'show/2MAi0BvDc6GTFvKFPXnkCL', 'Un podcast', ['show' => 'El programa'], ExternalResourceMother::IMAGE, null, 'ca'), ResourceStatus::Draft, null, Availability::Available)];
        yield 'a document' => [ExternalResourceMother::resource(ExternalResourceMother::ID, ResourceKind::Document, null, ExternalResourceMother::version(ResourceKind::Document, 'https://www.example.org/informe.pdf', null, 'Informe', ['publisher' => 'Asociación', 'bytes' => 2300000, 'pages' => 14], null), ResourceStatus::Draft, null, Availability::Available)];
    }

    public function testItFindsTheResourcesByTheirIdsAndTheirAddresses(): void
    {
        $this->repository()->save(ExternalResourceMother::video());
        $this->repository()->save(ExternalResourceMother::article(id: self::OTHER));

        $found = $this->repository()->findByIds([new ExternalResourceId(self::OTHER), new ExternalResourceId(self::THIRD)]);

        self::assertSame([self::OTHER], array_map(static fn (ExternalResource $resource): string => $resource->id()->value(), $found));
        self::assertSame([], $this->repository()->findByIds([]));
        self::assertSame(ExternalResourceMother::ID, $this->repository()->findByUrl(ExternalResourceMother::video()->version()->url())?->id()->value());
    }

    public function testItSearchesTheResourcesByTheirTitlesOrTheirAvailability(): void
    {
        $this->repository()->save(ExternalResourceMother::video(status: ResourceStatus::Published, availability: Availability::Unavailable));
        $this->repository()->save(ExternalResourceMother::article(id: self::OTHER, title: 'Un vídeo comentado'));
        $this->repository()->save(ExternalResourceMother::study(id: self::THIRD));

        $titles = static fn (string $title, bool $unavailable): array => array_map(
            static fn ($resource): string => $resource->id,
            new ExternalResourceSearcher($title, $unavailable, 1)(self::getContainer()->get(ExternalResourceRepository::class))->resources,
        );

        self::assertSame([self::OTHER, ExternalResourceMother::ID], $titles('vídeo', false));
        self::assertSame([ExternalResourceMother::ID], $titles('', true));
        self::assertSame(1, $this->repository()->countUnavailable());
    }

    /**
     * Only one resource keeps an address, even when two are saved with it
     * at once, past the check that came before.
     */
    public function testItRefusesASecondResourceWithTheSameAddress(): void
    {
        $this->repository()->save(ExternalResourceMother::video());

        self::assertTells('errors.external.url.taken', ['%id%' => ExternalResourceMother::ID], fn () => $this->repository()->save(ExternalResourceMother::video(id: self::OTHER)));
    }

    public function testItDeletesTheResource(): void
    {
        $this->repository()->save(ExternalResourceMother::video());

        $resource = $this->repository()->find(new ExternalResourceId(ExternalResourceMother::ID));
        self::assertNotNull($resource);

        $this->repository()->delete($resource);

        self::assertNull($this->repository()->find(new ExternalResourceId(ExternalResourceMother::ID)));
    }

    private function repository(): ExternalResourceRepository
    {
        return self::getContainer()->get(ExternalResourceRepository::class);
    }
}
