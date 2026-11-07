<?php

declare(strict_types=1);

namespace Cms\Tests\Page\Application;

use Cms\Page\Application\UniquePageCreator;
use Cms\Page\Domain\InvalidPageType;
use Cms\Page\Domain\InvalidSlug;
use Cms\Page\Domain\InvalidTitle;
use Cms\Page\Domain\Page;
use Cms\Page\Domain\PageRepository;
use Cms\Page\Domain\PageType;
use Cms\Page\Domain\SlugAlreadyTaken;
use Cms\Page\Domain\Title;
use Cms\Page\Domain\UniquePageAlreadyExists;
use Cms\Tests\Page\Domain\PageIdMother;
use Cms\Tests\Page\Domain\PageMother;
use Cms\Tests\Page\Domain\SlugMother;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shared\Domain\DomainError;
use Shared\Domain\InvalidUuid;
use Shared\Tests\Domain\ClockMother;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;

final class UniquePageCreatorTest extends TestCase
{
    use ClockSensitiveTrait;

    #[\Override]
    protected function setUp(): void
    {
        // The domain dates what happens, so it is dated as the pages compared with.
        self::mockTime(ClockMother::now());
    }

    #[DataProvider('uniquePages')]
    public function testItPersistsTheUniquePage(string $type, string $title, Page $expected): void
    {
        $repository = $this->createMock(PageRepository::class);
        $repository->expects(self::once())
            ->method('findByType')
            ->with(self::identicalTo(PageType::from($type)))
            ->willReturn(null);
        // The home page has no slug to check.
        $repository->expects(null === $expected->slug() ? self::never() : self::once())
            ->method('findBySlug')
            ->with(self::equalTo($expected->slug()))
            ->willReturn(null);
        $repository->expects(self::once())
            ->method('save')
            ->with(self::equalTo($expected));

        (new UniquePageCreator(PageIdMother::DEFAULT_VALUE, $type, $title))($repository);
    }

    /**
     * @return iterable<string, array{string, string, Page}>
     */
    public static function uniquePages(): iterable
    {
        $id = PageIdMother::DEFAULT_VALUE;

        yield 'home, without a slug' => ['home', 'Portada', PageMother::home(id: $id, title: 'Portada')];
        yield 'home, whatever its title' => ['home', 'Blog autista: inicio', PageMother::home(id: $id, title: 'Blog autista: inicio')];
        yield 'contact' => ['contact', 'Contacto', PageMother::create(id: $id, title: 'Contacto', slug: 'contacto', type: PageType::Contact)];
        yield 'search' => ['search', 'Buscar en el blog', PageMother::create(id: $id, title: 'Buscar en el blog', slug: 'buscar-blog', type: PageType::Search)];
        yield 'legal notice' => ['legal_notice', 'Aviso legal', PageMother::create(id: $id, title: 'Aviso legal', slug: 'aviso-legal', type: PageType::LegalNotice)];
        yield 'privacy policy' => [
            'privacy_policy',
            'Política de privacidad',
            PageMother::create(id: $id, title: 'Política de privacidad', slug: 'politica-privacidad', type: PageType::PrivacyPolicy),
        ];
        yield 'cookie policy' => [
            'cookie_policy',
            'Política de cookies',
            PageMother::create(id: $id, title: 'Política de cookies', slug: 'politica-cookies', type: PageType::CookiePolicy),
        ];
    }

    #[DataProvider('invalidInputs')]
    public function testItPersistsNothingWhenTheInputIsInvalid(string $id, string $type, string $title, DomainError $error): void
    {
        $repository = $this->createMock(PageRepository::class);
        $repository->expects(self::never())->method('findByType');
        $repository->expects(self::never())->method('save');

        $this->expectExceptionObject($error);

        (new UniquePageCreator($id, $type, $title))($repository);
    }

    /**
     * @return iterable<string, array{string, string, string, DomainError}>
     */
    public static function invalidInputs(): iterable
    {
        $id = PageIdMother::DEFAULT_VALUE;

        yield 'generic pages are created from the CMS' => [$id, 'standard', 'Sobre mí', InvalidPageType::notUnique(PageType::Standard)];
        yield 'unknown type' => [$id, 'about', 'Sobre mí', InvalidPageType::unknown('about')];
        yield 'type in another case' => [$id, 'Home', 'Portada', InvalidPageType::unknown('Home')];
        yield 'empty type' => [$id, '', 'Portada', InvalidPageType::unknown('')];
        yield 'id is not a UUID' => ['page-id', 'contact', 'Contacto', InvalidUuid::withValue('page-id')];
        yield 'title too short' => [$id, 'contact', 'Sí', InvalidTitle::tooShort(Title::MIN_LENGTH)];
        yield 'title too short, also for the home page' => [$id, 'home', 'Sí', InvalidTitle::tooShort(Title::MIN_LENGTH)];
        yield 'title without sluggable characters' => [$id, 'contact', '¡!?', InvalidSlug::malformed('')];
    }

    #[DataProvider('existingUniquePages')]
    public function testItPersistsNothingWhenThePageAlreadyExists(string $type, Page $existing): void
    {
        $repository = $this->createMock(PageRepository::class);
        $repository->expects(self::once())
            ->method('findByType')
            ->with(self::identicalTo(PageType::from($type)))
            ->willReturn($existing);
        $repository->expects(self::never())->method('save');

        $this->expectExceptionObject(UniquePageAlreadyExists::ofType(PageType::from($type)));

        (new UniquePageCreator(PageIdMother::random()->value(), $type, 'Otra página'))($repository);
    }

    /**
     * @return iterable<string, array{string, Page}>
     */
    public static function existingUniquePages(): iterable
    {
        yield 'home' => ['home', PageMother::home()];

        foreach (PageMother::uniqueButHome() as $type => $page) {
            yield $type => [$type, $page];
        }
    }

    public function testItPersistsNothingWhenAnotherPageHasTheSlugDerivedFromTheTitle(): void
    {
        $repository = $this->createMock(PageRepository::class);
        $repository->method('findByType')->willReturn(null);
        $repository->expects(self::once())
            ->method('findBySlug')
            ->with(self::equalTo(SlugMother::create('contacto')))
            ->willReturn(PageMother::create(id: PageIdMother::random()->value(), title: 'Contacto', slug: 'contacto'));
        $repository->expects(self::never())->method('save');

        $this->expectExceptionObject(SlugAlreadyTaken::bySlug(SlugMother::create('contacto')));

        (new UniquePageCreator(PageIdMother::DEFAULT_VALUE, 'contact', 'Contacto'))($repository);
    }
}
