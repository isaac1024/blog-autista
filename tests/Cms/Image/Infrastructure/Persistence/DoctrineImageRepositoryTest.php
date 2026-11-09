<?php

declare(strict_types=1);

namespace Cms\Tests\Image\Infrastructure\Persistence;

use Cms\Image\Domain\AltFilter;
use Cms\Image\Domain\Image;
use Cms\Image\Domain\ImageRepository;
use Cms\Tests\Image\Domain\ImageIdMother;
use Cms\Tests\Image\Domain\ImageMother;
use DAMA\DoctrineTestBundle\PHPUnit\SkipDatabaseRollback;
use Doctrine\ORM\EntityManagerInterface;
use Shared\Domain\Criteria\Criteria;
use Shared\Domain\Criteria\Order;
use Shared\Domain\Criteria\OrderDirection;
use Shared\Tests\Domain\DomainErrorAssertions;
use Shared\Tests\Domain\RichText\RichTextMother as Text;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * The search test skips the rollback of dama/doctrine-test-bundle: InnoDB only
 * adds rows to a FULLTEXT index when their transaction commits, so a search
 * inside the test transaction would never see them. tearDown() empties the
 * table instead.
 */
final class DoctrineImageRepositoryTest extends KernelTestCase
{
    use DomainErrorAssertions;

    /**
     * The one set of images every search runs against. Alternative texts keyed
     * by id, oldest first: image ids are time-ordered UUIDs. Every text has
     * "fondo", so the search also covers a word found in every image.
     */
    private const array IMAGES = [
        '01a1b801-ce58-723d-8234-b76d9105d001' => 'Círculo naranja sobre fondo azul',
        '01a1b801-ce58-723d-8234-b76d9105d002' => 'Cuadrado blanco sobre fondo verde',
        '01a1b801-ce58-723d-8234-b76d9105d003' => 'Círculo lila y círculo rosa sobre fondo morado',
        '01a1b801-ce58-723d-8234-b76d9105d004' => 'Mano que dibuja con ceras sobre un fondo de papel',
        '01a1b801-ce58-723d-8234-b76d9105d005' => 'Auriculares de cancelación de ruido sobre fondo gris',
    ];

    #[\Override]
    protected function tearDown(): void
    {
        $this->entityManager()->getConnection()->executeStatement('DELETE FROM cms_image');

        parent::tearDown();
    }

    /**
     * Every field comes back as saved: its id, its text at its longest, its
     * path and its dates.
     */
    public function testItSavesTheImageSoItCanBeReadBack(): void
    {
        $id = ImageIdMother::random();
        $image = ImageMother::create(id: $id->value(), alt: str_repeat('á', 125), createdAt: '2027-01-31 23:59:59');
        $entityManager = $this->entityManager();

        $this->repository()->save($image);
        $entityManager->clear();

        self::assertEquals($image, $entityManager->find(Image::class, $id));
    }

    public function testItSavesTheCaptionSoItCanBeReadBack(): void
    {
        $id = ImageIdMother::random();
        $image = ImageMother::create(id: $id->value(), caption: Text::stored(Text::paragraph(str_repeat('é', 200))), updatedAt: '2026-12-01 08:00:00');
        $entityManager = $this->entityManager();

        $this->repository()->save($image);
        $entityManager->clear();

        self::assertEquals($image, $entityManager->find(Image::class, $id));
    }

    /**
     * A published image keeps apart the version on the site, read back with
     * its status.
     */
    public function testItSavesThePublicationSoItCanBeReadBack(): void
    {
        $id = ImageIdMother::random();
        $image = ImageMother::published(id: $id->value(), alt: 'Otro texto', caption: Text::stored(Text::paragraph('Otro pie.')));
        $entityManager = $this->entityManager();

        $this->repository()->save($image);
        $entityManager->clear();

        self::assertEquals($image, $entityManager->find(Image::class, $id));
    }

    public function testItDeletesOnlyTheGivenImage(): void
    {
        $kept = ImageMother::create(id: ImageIdMother::random()->value());
        $deleted = ImageMother::create(id: ImageIdMother::random()->value());
        $repository = $this->repository();
        $repository->save($kept);
        $repository->save($deleted);

        $repository->delete($deleted);
        $this->entityManager()->clear();

        self::assertNull($repository->find($deleted->id()));
        self::assertEquals($kept, $repository->find($kept->id()));
    }

    public function testItFindsTheImageWithTheGivenId(): void
    {
        $id = ImageIdMother::random();
        $image = ImageMother::create(id: $id->value(), alt: 'Círculo naranja sobre fondo azul');
        $repository = $this->repository();
        $repository->save(ImageMother::create(id: ImageIdMother::random()->value(), alt: 'Cuadrado blanco sobre fondo verde'));
        $repository->save($image);
        $this->entityManager()->clear();

        self::assertEquals($image, $repository->find($id));
    }

    public function testItFindsNoImageWhenTheIdDoesNotExist(): void
    {
        $repository = $this->repository();
        $repository->save(ImageMother::create(id: ImageIdMother::random()->value()));

        self::assertNull($repository->find(ImageIdMother::random()));
    }

    /**
     * In whatever order; an id of no image is left out.
     */
    public function testItFindsTheImagesWithTheGivenIds(): void
    {
        $circle = ImageMother::create(id: ImageIdMother::random()->value(), alt: 'Círculo naranja sobre fondo azul');
        $square = ImageMother::create(id: ImageIdMother::random()->value(), alt: 'Cuadrado blanco sobre fondo verde');
        $repository = $this->repository();
        $repository->save($circle);
        $repository->save($square);
        $repository->save(ImageMother::create(id: ImageIdMother::random()->value(), alt: 'Mano que dibuja con ceras'));
        $this->entityManager()->clear();

        $found = $repository->findByIds([$square->id(), ImageIdMother::random(), $circle->id()]);

        self::assertEqualsCanonicalizing([$circle, $square], $found);
    }

    public function testItFindsNoImageWithoutIds(): void
    {
        $this->repository()->save(ImageMother::create(id: ImageIdMother::random()->value()));

        self::assertSame([], $this->repository()->findByIds([]));
    }

    /**
     * The only test that commits its data, which full-text search needs: it
     * saves the images once and checks every kind of criteria against them.
     */
    #[SkipDatabaseRollback]
    public function testItFindsTheImagesMatchingTheCriteria(): void
    {
        $this->saveImages();
        $repository = $this->repository();

        self::assertEqualsCanonicalizing(
            array_values(self::IMAGES),
            $this->alts($repository->matching(new Criteria(limit: Criteria::MAX_LIMIT, filters: [], order: null, offset: 0))),
            'without criteria, every image',
        );

        self::assertEquals(
            [ImageMother::create(id: '01a1b801-ce58-723d-8234-b76d9105d002', alt: 'Cuadrado blanco sobre fondo verde')],
            $repository->matching(new Criteria(limit: Criteria::MAX_LIMIT, filters: [AltFilter::matches('cuadrado')], order: null, offset: 0)),
            'images come back whole',
        );

        foreach (self::criteriaCases() as $case => [$criteria, $expected]) {
            self::assertSame($expected, $this->alts($repository->matching($criteria)), $case);
        }
    }

    public function testItFindsNothingWhenThereAreNoImages(): void
    {
        $repository = $this->repository();

        self::assertSame([], $repository->matching(new Criteria(limit: Criteria::MAX_LIMIT, filters: [], order: null, offset: 0)), 'without criteria');
        self::assertSame([], $repository->matching(new Criteria(limit: Criteria::MAX_LIMIT, filters: [AltFilter::matches('círculo')], order: null, offset: 0)), 'searching the text');
    }

    public function testItRejectsSortingByAFieldItDoesNotExpose(): void
    {
        self::assertTells('errors.criteria.unknown_field', ['%field%' => 'path'], fn () => $this->repository()->matching(
            new Criteria(limit: Criteria::MAX_LIMIT, filters: [], order: new Order('path', OrderDirection::Ascending), offset: 0),
        ));
    }

    /**
     * Expected texts for each criteria, in the order they must come back.
     *
     * @return iterable<string, array{Criteria, list<string>}>
     */
    private static function criteriaCases(): iterable
    {
        $oldestFirst = array_values(self::IMAGES);
        $alphabetical = [
            'Auriculares de cancelación de ruido sobre fondo gris',
            'Círculo lila y círculo rosa sobre fondo morado',
            'Círculo naranja sobre fondo azul',
            'Cuadrado blanco sobre fondo verde',
            'Mano que dibuja con ceras sobre un fondo de papel',
        ];
        $byId = new Order('id', OrderDirection::Ascending);
        $newestFirst = new Order('id', OrderDirection::Descending);

        // Sorting.
        yield 'sorted by id, oldest first' => [new Criteria(limit: Criteria::MAX_LIMIT, filters: [], order: $byId, offset: 0), $oldestFirst];
        yield 'sorted by id, newest first' => [new Criteria(limit: Criteria::MAX_LIMIT, filters: [], order: $newestFirst, offset: 0), array_reverse($oldestFirst)];
        yield 'sorted by text, ignoring accents' => [new Criteria(limit: Criteria::MAX_LIMIT, filters: [], order: new Order('alt', OrderDirection::Ascending), offset: 0), $alphabetical];

        // Pagination.
        yield 'first page' => [new Criteria(limit: 2, filters: [], order: $byId, offset: 0), \array_slice($oldestFirst, 0, 2)];
        yield 'last, incomplete page' => [new Criteria(limit: 2, filters: [], order: $byId, offset: 4), \array_slice($oldestFirst, 4)];
        yield 'page past the end' => [new Criteria(limit: 2, filters: [], order: $byId, offset: 6), []];

        // Full-text search on the text: by relevance first, newest first on ties.
        $search = static fn (string $text, int $limit = Criteria::MAX_LIMIT, int $offset = 0): Criteria => new Criteria(
            $limit,
            [AltFilter::matches($text)],
            $newestFirst,
            $offset,
        );
        $circles = ['Círculo lila y círculo rosa sobre fondo morado', 'Círculo naranja sobre fondo azul'];

        yield 'search: a single word' => [$search('auriculares'), ['Auriculares de cancelación de ruido sobre fondo gris']];
        yield 'search: ignoring case and accents' => [$search('CIRCULO'), $circles];
        yield 'search: every word, in any order' => [$search('azul círculo'), ['Círculo naranja sobre fondo azul']];
        yield 'search: words as prefixes' => [$search('auric cancel'), ['Auriculares de cancelación de ruido sobre fondo gris']];
        yield 'search: every word is required' => [$search('círculo verde'), []];
        yield 'search: no image has the word' => [$search('triángulo'), []];
        yield 'search: more occurrences rank higher' => [$search('círculo'), $circles];
        yield 'search: a word present in every image, newest first' => [$search('fondo'), array_reverse($oldestFirst)];
        yield 'search: paginated' => [$search('círculo', 1, 1), ['Círculo naranja sobre fondo azul']];
    }

    private function saveImages(): void
    {
        $repository = $this->repository();

        foreach (self::IMAGES as $id => $alt) {
            $repository->save(ImageMother::create(id: $id, alt: $alt));
        }

        $this->entityManager()->clear();
    }

    /**
     * @param list<Image> $images
     *
     * @return list<string>
     */
    private function alts(array $images): array
    {
        return array_map(static fn (Image $image): string => $image->alt()->value(), $images);
    }

    private function repository(): ImageRepository
    {
        return self::getContainer()->get(ImageRepository::class);
    }

    private function entityManager(): EntityManagerInterface
    {
        return self::getContainer()->get(EntityManagerInterface::class);
    }
}
