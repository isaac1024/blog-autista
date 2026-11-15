<?php

declare(strict_types=1);

namespace Cms\Tests\Post\Application;

use Cms\Post\Application\PostAddressesFinder;
use Cms\Post\Application\PostAddressResponse;
use Cms\Post\Application\PostNotFound;
use Cms\Post\Domain\PostRepository;
use Cms\Tests\Post\Domain\InMemoryClassifiers;
use Cms\Tests\Post\Domain\PostIdMother;
use Cms\Tests\Post\Domain\PostMother;
use Cms\Tests\Taxonomy\Domain\TermMother;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;

final class PostAddressesFinderTest extends TestCase
{
    use ClockSensitiveTrait;

    public function testItFindsTheLastAddressThePostHadOnTheSite(): void
    {
        $repository = $this->createMock(PostRepository::class);
        $repository->expects(self::once())->method('find')->with(self::equalTo(PostIdMother::create()))->willReturn(PostMother::unpublished(firstPublishedAt: '2026-10-01 10:00:00'));

        self::assertEquals(new PostAddressResponse(TermMother::SLUG, '2026-10-01', PostMother::SLUG), new PostAddressesFinder(PostIdMother::DEFAULT_VALUE)->last($repository));
    }

    public function testItFindsNoLastAddressForAPostNeverOnTheSite(): void
    {
        $repository = self::createStub(PostRepository::class);
        $repository->method('find')->willReturn(PostMother::publishable());

        self::assertNull(new PostAddressesFinder(PostIdMother::DEFAULT_VALUE)->last($repository));
    }

    /**
     * Published now, a draft goes under the slug its section has on the
     * site, on today in Madrid.
     */
    public function testItFindsTheAddressThePostWouldHaveWerePublishedNow(): void
    {
        self::mockTime('2026-11-14 23:30:00 UTC');
        $repository = self::createStub(PostRepository::class);
        $repository->method('find')->willReturn(PostMother::publishable());
        $classifiers = new InMemoryClassifiers(sections: [PostMother::SECTION => ['name' => 'Día a día', 'slug' => 'dia-a-dia']]);

        self::assertEquals(new PostAddressResponse('dia-a-dia', '2026-11-15', PostMother::SLUG), new PostAddressesFinder(PostIdMother::DEFAULT_VALUE)->next($repository, $classifiers));
    }

    public function testItFindsNoNextAddressWhileTheSectionIsOffTheSite(): void
    {
        $repository = self::createStub(PostRepository::class);
        $repository->method('find')->willReturn(PostMother::publishable());

        self::assertNull(new PostAddressesFinder(PostIdMother::DEFAULT_VALUE)->next($repository, new InMemoryClassifiers(sections: [PostMother::SECTION => null])));
    }

    public function testItFindsNoNextAddressWithoutASlug(): void
    {
        $repository = self::createStub(PostRepository::class);
        $repository->method('find')->willReturn(PostMother::publishable(slug: ''));

        self::assertNull(new PostAddressesFinder(PostIdMother::DEFAULT_VALUE)->next($repository, new InMemoryClassifiers()));
    }

    public function testItThrowsWhenThePostDoesNotExist(): void
    {
        $repository = self::createStub(PostRepository::class);
        $repository->method('find')->willReturn(null);

        $this->expectExceptionObject(PostNotFound::withId(PostIdMother::DEFAULT_VALUE));

        new PostAddressesFinder(PostIdMother::DEFAULT_VALUE)->last($repository);
    }
}
