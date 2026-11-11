<?php

declare(strict_types=1);

namespace Cms\Tests\Redirect\Application;

use Cms\Redirect\Application\RedirectDeleter;
use Cms\Redirect\Application\RedirectNotFound;
use Cms\Redirect\Domain\Redirect;
use Cms\Redirect\Domain\RedirectRepository;
use Cms\Redirect\Domain\Source;
use Cms\Tests\Redirect\Domain\RedirectIdMother;
use Cms\Tests\Redirect\Domain\RedirectMother;
use PHPUnit\Framework\TestCase;
use Shared\Domain\InvalidUuid;
use Shared\Tests\Domain\RecordingMutualExclusion;

/**
 * A redirect is deleted holding its address, as every change to one is, and
 * read only then, so it deletes what the last change left.
 */
final class RedirectDeleterTest extends TestCase
{
    public function testItDeletesTheRedirectWithTheGivenIdAsItIsOnceItHoldsItsAddress(): void
    {
        $id = RedirectIdMother::DEFAULT_VALUE;
        $mutualExclusion = new RecordingMutualExclusion();
        $redirect = RedirectMother::create(id: $id);
        $repository = $this->createMock(RedirectRepository::class);
        $repository->expects(self::once())->method('sourceOf')->with(self::equalTo(RedirectIdMother::create($id)))->willReturn(Source::fromStored('/vieja.html'));
        $repository->expects(self::once())
            ->method('find')
            ->with(self::equalTo(RedirectIdMother::create($id)))
            ->willReturnCallback(static fn (): Redirect => $mutualExclusion->isHolding() ? $redirect : self::fail('Read before holding its address.'));
        $repository->expects(self::once())->method('delete')->with(self::identicalTo($redirect));

        new RedirectDeleter($id)($repository, $mutualExclusion);

        self::assertSame(['address:/vieja.html'], $mutualExclusion->lastHeld());
    }

    public function testItThrowsWhenTheRedirectDoesNotExist(): void
    {
        $id = RedirectIdMother::DEFAULT_VALUE;
        $repository = $this->createMock(RedirectRepository::class);
        $repository->method('sourceOf')->willReturn(null);
        $repository->expects(self::never())->method('delete');

        $this->expectExceptionObject(RedirectNotFound::withId($id));

        new RedirectDeleter($id)($repository, new RecordingMutualExclusion());
    }

    /**
     * Another change deleted it while this one waited for its address.
     */
    public function testItThrowsWhenTheRedirectIsGoneOnceItHoldsItsAddress(): void
    {
        $id = RedirectIdMother::DEFAULT_VALUE;
        $repository = $this->createMock(RedirectRepository::class);
        $repository->method('sourceOf')->willReturn(Source::fromStored('/vieja.html'));
        $repository->method('find')->willReturn(null);
        $repository->expects(self::never())->method('delete');

        $this->expectExceptionObject(RedirectNotFound::withId($id));

        new RedirectDeleter($id)($repository, new RecordingMutualExclusion());
    }

    public function testItRejectsAnIdThatIsNotAUuid(): void
    {
        $repository = $this->createMock(RedirectRepository::class);
        $repository->expects(self::never())->method('sourceOf');

        $this->expectExceptionObject(InvalidUuid::withValue('redirect-id'));

        new RedirectDeleter('redirect-id')($repository, new RecordingMutualExclusion());
    }
}
