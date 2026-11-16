<?php

declare(strict_types=1);

namespace Cms\Tests\Post\Application;

use Cms\Post\Application\PostAddressChecker;
use Cms\Post\Application\PostNotFound;
use Cms\Post\Domain\Post;
use Cms\Post\Domain\PostAddress;
use Cms\Post\Domain\PostRepository;
use Cms\Tests\Post\Domain\InMemoryClassifiers;
use Cms\Tests\Post\Domain\PostIdMother;
use Cms\Tests\Post\Domain\PostMother;
use Cms\Tests\Taxonomy\Domain\TermMother;
use PHPUnit\Framework\TestCase;
use Shared\Tests\Domain\ClockMother;
use Shared\Tests\Domain\DomainErrorAssertions;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;

/**
 * Before an editor is asked anything about publishing a post, the address
 * it would take is checked against the posts on the site.
 */
final class PostAddressCheckerTest extends TestCase
{
    use ClockSensitiveTrait;
    use DomainErrorAssertions;

    #[\Override]
    protected function setUp(): void
    {
        self::mockTime(ClockMother::now());
    }

    public function testItTellsAnotherPostOnTheSiteHasTheAddressItWouldTake(): void
    {
        $repository = self::createStub(PostRepository::class);
        $repository->method('find')->willReturn(PostMother::publishable());
        $repository->method('findOnSiteAt')->willReturnCallback(static fn (PostAddress $address): ?Post => TermMother::SLUG.'/2026-11-07/'.PostMother::SLUG === $address->key()
            ? PostMother::published(id: PostIdMother::random()->value())
            : null);

        self::assertTells(
            'errors.post.address.taken',
            ['%section%' => TermMother::SLUG, '%date%' => '2026/11/07', '%slug%' => PostMother::SLUG],
            static fn () => new PostAddressChecker(PostIdMother::DEFAULT_VALUE)($repository, new InMemoryClassifiers()),
        );
    }

    public function testItLetsAPostKeepItsOwnAddress(): void
    {
        $repository = self::createStub(PostRepository::class);
        $post = PostMother::published();
        $repository->method('find')->willReturn($post);
        $repository->method('findOnSiteAt')->willReturn($post);

        new PostAddressChecker(PostIdMother::DEFAULT_VALUE)($repository, new InMemoryClassifiers());

        $this->addToAssertionCount(1);
    }

    public function testItThrowsWhenThePostDoesNotExist(): void
    {
        $repository = self::createStub(PostRepository::class);
        $repository->method('find')->willReturn(null);

        $this->expectExceptionObject(PostNotFound::withId(PostIdMother::DEFAULT_VALUE));

        new PostAddressChecker(PostIdMother::DEFAULT_VALUE)($repository, new InMemoryClassifiers());
    }
}
