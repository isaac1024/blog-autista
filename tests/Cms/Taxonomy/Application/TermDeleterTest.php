<?php

declare(strict_types=1);

namespace Cms\Tests\Taxonomy\Application;

use Cms\Taxonomy\Application\TermDeleter;
use Cms\Taxonomy\Application\TermNotFound;
use Cms\Taxonomy\Domain\InvalidTermPublication;
use Cms\Taxonomy\Domain\Taxonomy;
use Cms\Taxonomy\Domain\Term;
use Cms\Taxonomy\Domain\TermRepository;
use Cms\Taxonomy\Domain\TermUsage;
use Cms\Tests\Taxonomy\Domain\TermIdMother;
use Cms\Tests\Taxonomy\Domain\TermMother;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class TermDeleterTest extends TestCase
{
    #[DataProvider('termsOffTheSite')]
    public function testItDeletesATermOffTheSite(Term $term): void
    {
        $repository = $this->createMock(TermRepository::class);
        $repository->method('find')->willReturn($term);
        $repository->expects(self::once())->method('delete')->with(self::identicalTo($term));

        new TermDeleter($term->taxonomy()->value, TermIdMother::DEFAULT_VALUE)($repository, self::createStub(TermUsage::class));
    }

    /**
     * @return iterable<string, array{Term}>
     */
    public static function termsOffTheSite(): iterable
    {
        yield 'a draft' => [TermMother::create()];
        yield 'an unpublished tag' => [TermMother::unpublished(taxonomy: Taxonomy::Tag)];
    }

    public function testItNeverDeletesAPublishedTerm(): void
    {
        $repository = $this->createMock(TermRepository::class);
        $repository->method('find')->willReturn(TermMother::published());
        $repository->expects(self::never())->method('delete');

        $this->expectExceptionObject(InvalidTermPublication::publishedTerm());

        new TermDeleter('section', TermIdMother::DEFAULT_VALUE)($repository, self::createStub(TermUsage::class));
    }

    public function testItThrowsWhenTheTermIsOfAnotherTaxonomy(): void
    {
        $repository = $this->createMock(TermRepository::class);
        $repository->method('find')->willReturn(TermMother::create(taxonomy: Taxonomy::Tag));
        $repository->expects(self::never())->method('delete');

        $this->expectExceptionObject(TermNotFound::withId('section', TermIdMother::DEFAULT_VALUE));

        new TermDeleter('section', TermIdMother::DEFAULT_VALUE)($repository, self::createStub(TermUsage::class));
    }

    public function testItNeverDeletesATermPostsAreFiledUnder(): void
    {
        $repository = $this->createMock(TermRepository::class);
        $repository->method('find')->willReturn(TermMother::create());
        $repository->expects(self::never())->method('delete');
        $usage = self::createStub(TermUsage::class);
        $usage->method('isInUse')->willReturn(true);

        $this->expectExceptionObject(InvalidTermPublication::usedTerm());

        new TermDeleter('section', TermIdMother::DEFAULT_VALUE)($repository, $usage);
    }
}
