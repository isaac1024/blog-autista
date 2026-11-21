<?php

declare(strict_types=1);

namespace Cms\Tests\Author\Application;

use Cms\Author\Application\AuthorNamesFinder;
use Cms\Author\Domain\AuthorId;
use Cms\Author\Domain\AuthorRepository;
use PHPUnit\Framework\TestCase;

final class AuthorNamesFinderTest extends TestCase
{
    /**
     * It asks for what can be the id of one, at once, and leaves out the
     * rest without asking: an address or a typo names none.
     */
    public function testItAsksOnlyForWhatCanBeTheIdOfOne(): void
    {
        $id = '01a1da50-2b7c-7d01-8a3f-5c2e9b1d0a01';
        $repository = $this->createMock(AuthorRepository::class);
        $repository->expects(self::once())->method('namesOf')->with([new AuthorId($id)])->willReturn([$id => 'Isaac']);

        self::assertSame([$id => 'Isaac'], new AuthorNamesFinder([$id, 'no-es-un-id', ''])($repository));
    }
}
