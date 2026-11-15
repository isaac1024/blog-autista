<?php

declare(strict_types=1);

namespace Web\Tests\Taxonomy\Application;

use PHPUnit\Framework\TestCase;
use Web\Taxonomy\Application\TermRemover;
use Web\Taxonomy\Domain\Term;
use Web\Taxonomy\Domain\TermRepository;
use Web\Tests\Taxonomy\Domain\CmsTermPublishedMother;
use Web\Tests\Taxonomy\Domain\CmsTermUnpublishedMother;
use Web\Tests\Taxonomy\Domain\TermIdMother;

final class TermRemoverTest extends TestCase
{
    public function testItTakesTheTermOffTheSite(): void
    {
        $term = Term::publish(CmsTermPublishedMother::create());
        $repository = $this->createMock(TermRepository::class);
        $repository->expects(self::once())
            ->method('find')
            ->with(self::equalTo(TermIdMother::create()))
            ->willReturn($term);
        $repository->expects(self::once())->method('delete')->with(self::identicalTo($term));

        (new TermRemover(CmsTermUnpublishedMother::create()))($repository);
    }

    public function testItDoesNothingWhenTheTermIsNotOnTheSite(): void
    {
        $repository = $this->createMock(TermRepository::class);
        $repository->method('find')->willReturn(null);
        $repository->expects(self::never())->method('delete');

        (new TermRemover(CmsTermUnpublishedMother::create()))($repository);
    }
}
