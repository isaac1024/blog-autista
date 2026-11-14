<?php

declare(strict_types=1);

namespace Cms\Tests\Author\Application;

use Cms\Author\Application\AuthorCreator;
use Cms\Author\Domain\Author;
use Cms\Author\Domain\AuthorRepository;
use Cms\Author\Domain\AvatarStorage;
use Cms\Tests\Author\Domain\AuthorIdMother;
use Cms\Tests\Author\Domain\AuthorMother;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shared\Domain\Event\EventBus;
use Shared\Tests\Domain\ClockMother;
use Shared\Tests\Domain\DomainErrorAssertions;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;

final class AuthorCreatorTest extends TestCase
{
    use ClockSensitiveTrait;
    use DomainErrorAssertions;

    #[\Override]
    protected function setUp(): void
    {
        self::mockTime(ClockMother::now());
    }

    #[DataProvider('validNames')]
    public function testItPersistsTheAuthorBuiltFromItsName(string $name, Author $expected): void
    {
        $repository = $this->createMock(AuthorRepository::class);
        $repository->expects(self::once())->method('save')->with(self::equalTo($expected));

        new AuthorCreator(AuthorIdMother::DEFAULT_VALUE, $name)($repository, self::createStub(EventBus::class), self::createStub(AvatarStorage::class));
    }

    /**
     * @return iterable<string, array{string, Author}>
     */
    public static function validNames(): iterable
    {
        yield 'a name, its slug derived from it' => ['Isaac Abreu', AuthorMother::create(slug: 'isaac-abreu')];
        yield 'surrounding whitespace is trimmed' => ['  Ana  ', AuthorMother::create(name: 'Ana', slug: 'ana')];

        // 76 letters plus " y" twelve times: exactly 100 characters, and the
        // stop word "y" keeps the slug within its own maximum.
        $longest = str_repeat('a', 76).' '.rtrim(str_repeat('y ', 12));
        yield 'longest name' => [$longest, AuthorMother::create(name: $longest, slug: str_repeat('a', 76))];
    }

    /**
     * @param array<string, int|string> $parameters
     */
    #[DataProvider('invalidNames')]
    public function testItPersistsNothingWhenTheNameIsInvalid(string $name, string $key, array $parameters): void
    {
        $repository = $this->createMock(AuthorRepository::class);
        $repository->expects(self::never())->method('save');

        self::assertTells($key, $parameters, static fn () => new AuthorCreator(AuthorIdMother::DEFAULT_VALUE, $name)($repository, self::createStub(EventBus::class), self::createStub(AvatarStorage::class)));
    }

    /**
     * @return iterable<string, array{string, string, array<string, int|string>}>
     */
    public static function invalidNames(): iterable
    {
        yield 'too short' => ['Al', 'errors.author.name.too_short', ['%min%' => 3]];
        yield 'too long' => [str_repeat('a', 101), 'errors.author.name.too_long', ['%max%' => 100]];
        yield 'a slug derived too long' => [str_repeat('a', 81), 'errors.slug.too_long', ['%max%' => 80]];
    }

    public function testItRefusesTheSlugOfAnotherAuthor(): void
    {
        $repository = $this->createMock(AuthorRepository::class);
        $repository->method('findBySlug')->willReturn(AuthorMother::create(id: AuthorIdMother::random()->value(), slug: 'isaac-abreu'));
        $repository->expects(self::never())->method('save');

        self::assertTells('errors.author.slug.taken', ['%slug%' => 'isaac-abreu'], static fn () => new AuthorCreator(AuthorIdMother::DEFAULT_VALUE, 'Isaac Abreu')($repository, self::createStub(EventBus::class), self::createStub(AvatarStorage::class)));
    }
}
