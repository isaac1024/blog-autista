<?php

declare(strict_types=1);

namespace Cms\Tests\Image\Application;

use Cms\Image\Application\ImageResponse;
use Cms\Image\Application\ImageSearcher;
use Cms\Image\Domain\AltFilter;
use Cms\Image\Domain\ImageRepository;
use Cms\Tests\Image\Domain\ImageIdMother;
use Cms\Tests\Image\Domain\ImageMother;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shared\Domain\Criteria\Criteria;
use Shared\Domain\Criteria\InvalidCriteria;
use Shared\Domain\Criteria\Order;
use Shared\Domain\Criteria\OrderDirection;
use Shared\Tests\Domain\ClockMother;
use Shared\Tests\Domain\DomainErrorAssertions;

final class ImageSearcherTest extends TestCase
{
    use DomainErrorAssertions;

    #[DataProvider('searches')]
    public function testItAsksTheRepositoryForTheRequestedSliceOfImages(ImageSearcher $searcher, Criteria $expected): void
    {
        $repository = $this->createMock(ImageRepository::class);
        $repository->expects(self::once())
            ->method('matching')
            ->with(self::equalTo($expected))
            ->willReturn([]);

        $searcher($repository);
    }

    /**
     * @return iterable<string, array{ImageSearcher, Criteria}>
     */
    public static function searches(): iterable
    {
        $newestFirst = new Order('id', OrderDirection::Descending);

        yield 'no text, first page' => [
            new ImageSearcher('', 1),
            new Criteria(limit: 20, filters: [], order: $newestFirst, offset: 0),
        ];
        yield 'blank text does not filter' => [
            new ImageSearcher('   ', 1),
            new Criteria(limit: 20, filters: [], order: $newestFirst, offset: 0),
        ];
        yield 'text filters by its words' => [
            new ImageSearcher('círculo azul', 1),
            new Criteria(limit: 20, filters: [AltFilter::matches('círculo azul')], order: $newestFirst, offset: 0),
        ];
        yield 'text is trimmed before filtering' => [
            new ImageSearcher('  círculo  ', 1),
            new Criteria(limit: 20, filters: [AltFilter::matches('círculo')], order: $newestFirst, offset: 0),
        ];
        yield 'second page skips the first one' => [
            new ImageSearcher('', 2),
            new Criteria(limit: 20, filters: [], order: $newestFirst, offset: 20),
        ];
        yield 'later pages skip every previous one' => [
            new ImageSearcher('', 5),
            new Criteria(limit: 20, filters: [], order: $newestFirst, offset: 80),
        ];
        yield 'text and page together' => [
            new ImageSearcher('círculo', 3),
            new Criteria(limit: 20, filters: [AltFilter::matches('círculo')], order: $newestFirst, offset: 40),
        ];
    }

    public function testItReturnsTheFoundImagesInTheRepositoryOrder(): void
    {
        $circleId = ImageIdMother::random()->value();
        $squareId = ImageIdMother::random()->value();
        $repository = self::createStub(ImageRepository::class);
        $repository->method('matching')->willReturn([
            ImageMother::create(id: $circleId, alt: 'Círculo naranja sobre fondo azul'),
            ImageMother::create(id: $squareId, alt: 'Cuadrado blanco sobre fondo verde', createdAt: '2027-01-31 23:59:59'),
        ]);

        self::assertEquals(
            [
                new ImageResponse('Círculo naranja sobre fondo azul', '2026/11/'.$circleId.'.webp', ClockMother::now()),
                new ImageResponse('Cuadrado blanco sobre fondo verde', '2027/01/'.$squareId.'.webp', ClockMother::now('2027-01-31 23:59:59')),
            ],
            (new ImageSearcher('', 1))($repository),
        );
    }

    #[DataProvider('invalidPages')]
    public function testItRejectsPageNumbersBelowOne(int $page): void
    {
        $repository = $this->createMock(ImageRepository::class);
        $repository->expects(self::never())->method('matching');

        $this->expectExceptionObject(InvalidCriteria::nonPositivePage($page));

        (new ImageSearcher('', $page))($repository);
    }

    /**
     * @return iterable<string, array{int}>
     */
    public static function invalidPages(): iterable
    {
        yield 'zero' => [0];
        yield 'negative' => [-1];
    }

    public function testItTellsWhichPageNumberIsRefused(): void
    {
        self::assertTells('errors.criteria.non_positive_page', ['%page%' => 0], static fn () => (new ImageSearcher('', 0))(
            self::createStub(ImageRepository::class),
        ));
    }
}
