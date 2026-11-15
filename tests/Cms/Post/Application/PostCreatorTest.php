<?php

declare(strict_types=1);

namespace Cms\Tests\Post\Application;

use Cms\Post\Application\PostCreator;
use Cms\Post\Domain\PostRepository;
use Cms\Tests\Post\Domain\PostIdMother;
use Cms\Tests\Post\Domain\PostMother;
use PHPUnit\Framework\TestCase;
use Shared\Domain\Event\EventBus;
use Shared\Tests\Domain\ClockMother;
use Shared\Tests\Domain\DomainErrorAssertions;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;

final class PostCreatorTest extends TestCase
{
    use ClockSensitiveTrait;
    use DomainErrorAssertions;

    #[\Override]
    protected function setUp(): void
    {
        self::mockTime(ClockMother::now());
    }

    public function testItCreatesAnUnfiledDraftWithTheSlugOfItsTitle(): void
    {
        $repository = $this->createMock(PostRepository::class);
        $repository->expects(self::once())->method('save')->with(self::equalTo(PostMother::create()));

        new PostCreator(PostIdMother::DEFAULT_VALUE, '  Mi primer   día sin ruido ')($repository, self::createStub(EventBus::class));
    }

    public function testItNeverCreatesAPostWithATitleTooShort(): void
    {
        $repository = $this->createMock(PostRepository::class);
        $repository->expects(self::never())->method('save');

        self::assertTells('errors.post.title.too_short', ['%min%' => 3], static fn () => new PostCreator(PostIdMother::DEFAULT_VALUE, 'No')($repository, self::createStub(EventBus::class)));
    }
}
