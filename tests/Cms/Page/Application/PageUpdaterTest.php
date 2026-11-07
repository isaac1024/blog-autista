<?php

declare(strict_types=1);

namespace Cms\Tests\Page\Application;

use Cms\Page\Application\PageNotFound;
use Cms\Page\Application\PageUpdater;
use Cms\Page\Domain\InvalidSeo;
use Cms\Page\Domain\InvalidSlug;
use Cms\Page\Domain\InvalidTitle;
use Cms\Page\Domain\Page;
use Cms\Page\Domain\PageRepository;
use Cms\Page\Domain\PageType;
use Cms\Page\Domain\Robots;
use Cms\Page\Domain\Seo;
use Cms\Page\Domain\Slug;
use Cms\Page\Domain\SlugAlreadyTaken;
use Cms\Page\Domain\Title;
use Cms\Tests\Page\Domain\PageIdMother;
use Cms\Tests\Page\Domain\PageMother;
use Cms\Tests\Page\Domain\SlugMother;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shared\Domain\DomainError;
use Shared\Domain\Event\EventBus;
use Shared\Domain\InvalidUuid;
use Shared\Domain\RichText\InvalidRichText;
use Shared\Tests\Domain\ClockMother;
use Shared\Tests\Domain\RichText\RichTextMother as Text;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;

final class PageUpdaterTest extends TestCase
{
    use ClockSensitiveTrait;

    private const string BEFORE = '2026-10-01 09:00:00';

    #[\Override]
    protected function setUp(): void
    {
        // The domain dates what happens, so it is dated as the pages compared with.
        self::mockTime(ClockMother::now());
    }

    #[DataProvider('validEdits')]
    public function testItSavesThePageWithTheEditedTitleAndSlug(string $title, string $slug, Page $expected): void
    {
        $id = PageIdMother::DEFAULT_VALUE;
        $repository = $this->createMock(PageRepository::class);
        $repository->expects(self::once())
            ->method('find')
            ->with(self::equalTo(PageIdMother::create($id)))
            ->willReturn(PageMother::create(id: $id, title: 'Sobre mí', slug: 'sobre-mi'));
        $repository->expects(self::once())
            ->method('save')
            ->with(self::equalTo($expected));

        self::updater($id, $title, $slug)($repository, self::createStub(EventBus::class));
    }

    /**
     * @return iterable<string, array{string, string, Page}>
     */
    public static function validEdits(): iterable
    {
        $id = PageIdMother::DEFAULT_VALUE;

        yield 'new title and slug' => ['Quién soy', 'quien-soy', PageMother::create(id: $id, title: 'Quién soy', slug: 'quien-soy')];
        yield 'the slug is kept, not derived from the new title' => ['Quién soy', 'sobre-mi', PageMother::create(id: $id, title: 'Quién soy', slug: 'sobre-mi')];
        yield 'surrounding whitespace of the title is trimmed' => ['  Quién soy  ', 'quien-soy', PageMother::create(id: $id, title: 'Quién soy', slug: 'quien-soy')];
        yield 'the title is a single line' => ["Quién\n  soy\t yo", 'quien-soy', PageMother::create(id: $id, title: 'Quién soy yo', slug: 'quien-soy')];
        yield 'nothing changed' => ['Sobre mí', 'sobre-mi', PageMother::create(id: $id, title: 'Sobre mí', slug: 'sobre-mi')];
        yield 'shortest title and slug' => ['FAQ', 'faq', PageMother::create(id: $id, title: 'FAQ', slug: 'faq')];
        yield 'longest title and slug' => [
            str_repeat('a', 100),
            str_repeat('a', 80),
            PageMother::create(id: $id, title: str_repeat('a', 100), slug: str_repeat('a', 80)),
        ];
        yield 'longest title with multibyte characters' => [
            str_repeat('á', 100),
            'acentos',
            PageMother::create(id: $id, title: str_repeat('á', 100), slug: 'acentos'),
        ];
        yield 'slug with digits and single hyphens' => ['Guía 2026', 'guia-2026-v2', PageMother::create(id: $id, title: 'Guía 2026', slug: 'guia-2026-v2')];
    }

    #[DataProvider('invalidEdits')]
    public function testItSavesNothingWhenTheEditIsInvalid(string $title, string $slug, DomainError $error): void
    {
        $id = PageIdMother::DEFAULT_VALUE;
        $repository = $this->createMock(PageRepository::class);
        $repository->method('find')->willReturn(PageMother::create(id: $id, title: 'Sobre mí', slug: 'sobre-mi'));
        $repository->expects(self::never())->method('save');

        $this->expectExceptionObject($error);

        self::updater($id, $title, $slug)($repository, self::createStub(EventBus::class));
    }

    /**
     * @return iterable<string, array{string, string, DomainError}>
     */
    public static function invalidEdits(): iterable
    {
        yield 'empty title' => ['   ', 'quien-soy', InvalidTitle::tooShort(Title::MIN_LENGTH)];
        yield 'title one character too short' => ['Sí', 'quien-soy', InvalidTitle::tooShort(Title::MIN_LENGTH)];
        yield 'title one character too long' => [str_repeat('a', 101), 'quien-soy', InvalidTitle::tooLong(Title::MAX_LENGTH)];
        yield 'slug with uppercase letters' => ['Quién soy', 'Quien-Soy', InvalidSlug::malformed('Quien-Soy')];
        yield 'slug with accents' => ['Quién soy', 'quién-soy', InvalidSlug::malformed('quién-soy')];
        yield 'slug with spaces' => ['Quién soy', 'quien soy', InvalidSlug::malformed('quien soy')];
        yield 'slug with a double hyphen' => ['Quién soy', 'quien--soy', InvalidSlug::malformed('quien--soy')];
        yield 'slug starting with a hyphen' => ['Quién soy', '-quien-soy', InvalidSlug::malformed('-quien-soy')];
        yield 'slug ending with a hyphen' => ['Quién soy', 'quien-soy-', InvalidSlug::malformed('quien-soy-')];
        yield 'slug one character too short' => ['Quién soy', 'ab', InvalidSlug::tooShort(Slug::MIN_LENGTH)];
        yield 'slug one character too long' => ['Quién soy', str_repeat('a', 81), InvalidSlug::tooLong(Slug::MAX_LENGTH)];
    }

    /**
     * @param array<array-key, mixed> $content
     */
    #[DataProvider('validContents')]
    public function testItSavesTheEditedContent(array $content): void
    {
        $id = PageIdMother::DEFAULT_VALUE;
        $repository = $this->createMock(PageRepository::class);
        $repository->method('find')->willReturn(PageMother::create(id: $id, title: 'Sobre mí', slug: 'sobre-mi'));
        $repository->expects(self::once())
            ->method('save')
            ->with(self::equalTo(PageMother::create(id: $id, title: 'Sobre mí', slug: 'sobre-mi', content: $content)));

        self::updater($id, 'Sobre mí', 'sobre-mi', $content)($repository, self::createStub(EventBus::class));
    }

    /**
     * Everything a page allows: the blocks of fixed pages, two heading levels
     * and bold.
     *
     * @return iterable<string, array{array<array-key, mixed>}>
     */
    public static function validContents(): iterable
    {
        yield 'no content' => [Text::EMPTY];
        yield 'headings going down one level at a time' => [Text::stored(Text::heading(2, 'Qué datos'), Text::heading(3, 'Datos técnicos'), Text::heading(2, 'Derechos'))];
        yield 'paragraph with bold' => [Text::stored(Text::paragraph('Solo usamos cookies ', '**técnicas**', '.'))];
        yield 'quote' => [Text::stored(['type' => 'quote', 'paragraphs' => [Text::text('Una cita'), Text::text('en dos párrafos')]])];
        yield 'unordered list' => [Text::stored(['type' => 'list', 'style' => 'unordered', 'items' => [Text::text('Sesión'), Text::text('Preferencias')]])];
        yield 'ordered list' => [Text::stored(['type' => 'list', 'style' => 'ordered', 'items' => [Text::text('Primero')]])];
        yield 'note' => [Text::stored(Text::callout('note', 'Puedes borrarlas.'))];
        yield 'important callout' => [Text::stored(Text::callout('important', 'Ojo.'))];
        yield 'table, with an empty body cell' => [Text::stored(Text::table(['Nombre', 'Finalidad'], [['PHPSESSID', ''], ['Idioma', 'Recordar el idioma']]))];
    }

    /**
     * @param array<array-key, mixed> $content
     */
    #[DataProvider('invalidContents')]
    public function testItSavesNothingWhenTheContentIsInvalid(array $content, DomainError $error): void
    {
        $id = PageIdMother::DEFAULT_VALUE;
        $repository = $this->createMock(PageRepository::class);
        $repository->method('find')->willReturn(PageMother::create(id: $id, title: 'Sobre mí', slug: 'sobre-mi'));
        $repository->expects(self::never())->method('save');

        $this->expectExceptionObject($error);

        self::updater($id, 'Quién soy', 'quien-soy', $content)($repository, self::createStub(EventBus::class));
    }

    /**
     * @return iterable<string, array{array<array-key, mixed>, DomainError}>
     */
    public static function invalidContents(): iterable
    {
        yield 'unknown version' => [['version' => 2, 'blocks' => []], InvalidRichText::unsupportedVersion(2)];
        yield 'blocks are not a list' => [['version' => 1, 'blocks' => 'none'], InvalidRichText::malformed('blocks', 'a list')];
        yield 'unknown block' => [Text::stored(['type' => 'image', 'src' => 'x.png']), InvalidRichText::malformed('blocks[0].type', 'a known block type')];
        yield 'level 1 heading, the title of the page' => [Text::stored(Text::heading(1, 'Título')), InvalidRichText::malformed('heading.level', 'between 2 and 6')];
        yield 'heading skipping a level' => [Text::stored(Text::heading(3, 'Detalle')), InvalidRichText::skippedHeadingLevel(3, 1)];
        yield 'heading skipping a level after other blocks' => [
            Text::stored(Text::paragraph('Introducción'), Text::heading(3, 'Detalle')),
            InvalidRichText::skippedHeadingLevel(3, 1),
        ];
        yield 'block that is not an object' => [['version' => 1, 'blocks' => [['paragraph']]], InvalidRichText::malformed('blocks[0]', 'an object')];
        yield 'block without a type' => [Text::stored(['text' => Text::text('Hola')]), InvalidRichText::malformed('blocks[0].type', 'a string')];
        yield 'heading level that is not a number' => [
            Text::stored(['type' => 'heading', 'level' => '2', 'text' => Text::text('Título')]),
            InvalidRichText::malformed('blocks[0].level', 'an integer'),
        ];
        yield 'run without text' => [
            Text::stored(['type' => 'paragraph', 'text' => [['marks' => ['bold']]]]),
            InvalidRichText::malformed('blocks[0].text[0].text', 'a string'),
        ];
        yield 'marks that are not a list' => [
            Text::stored(['type' => 'paragraph', 'text' => [['text' => 'Hola', 'marks' => 'bold']]]),
            InvalidRichText::malformed('blocks[0].text[0].marks', 'a list'),
        ];
        yield 'callout without a variant' => [
            Text::stored(['type' => 'callout', 'paragraphs' => [Text::text('Ojo')]]),
            InvalidRichText::malformed('blocks[0].variant', 'a string'),
        ];
        yield 'list without a style' => [
            Text::stored(['type' => 'list', 'items' => [Text::text('Uno')]]),
            InvalidRichText::malformed('blocks[0].style', 'a string'),
        ];
        yield 'heading past the deepest level' => [Text::stored(Text::heading(7, 'Muy hondo')), InvalidRichText::malformed('heading.level', 'between 2 and 6')];
        yield 'deepest heading level, which a page does not allow' => [
            Text::stored(Text::heading(2, 'A'), Text::heading(3, 'B'), Text::heading(4, 'C'), Text::heading(5, 'D'), Text::heading(6, 'E')),
            InvalidRichText::notAllowed('A level 4 heading'),
        ];
        yield 'heading level a page does not allow' => [
            Text::stored(Text::heading(2, 'A'), Text::heading(3, 'B'), Text::heading(4, 'C')),
            InvalidRichText::notAllowed('A level 4 heading'),
        ];
        yield 'empty heading' => [Text::stored(['type' => 'heading', 'level' => 2, 'text' => []]), InvalidRichText::empty('blocks[0].text')];
        yield 'empty paragraph' => [Text::stored(['type' => 'paragraph', 'text' => []]), InvalidRichText::empty('blocks[0].text')];
        yield 'empty run of text' => [Text::stored(Text::paragraph('')), InvalidRichText::empty('text')];
        yield 'italics, which a page does not allow' => [
            Text::stored(['type' => 'paragraph', 'text' => [['text' => 'cursiva', 'marks' => ['italic']]]]),
            InvalidRichText::malformed('blocks[0].text[0].marks[0]', 'a known mark'),
        ];
        yield 'unknown callout' => [Text::stored(Text::callout('tip', 'Consejo')), InvalidRichText::malformed('blocks[0].variant', 'a known callout variant')];
        yield 'quote with an empty paragraph' => [Text::stored(['type' => 'quote', 'paragraphs' => [[]]]), InvalidRichText::empty('blocks[0].paragraphs[0]')];
        yield 'list without items' => [Text::stored(['type' => 'list', 'style' => 'unordered', 'items' => []]), InvalidRichText::empty('blocks[0].items')];
        yield 'unknown list style' => [Text::stored(['type' => 'list', 'style' => 'checklist', 'items' => [Text::text('a')]]), InvalidRichText::malformed('blocks[0].style', 'a known list style')];
        yield 'table without rows' => [Text::stored(Text::table(['Nombre'], [])), InvalidRichText::empty('blocks[0].rows')];
        yield 'table with an empty header cell' => [Text::stored(Text::table([''], [['a']])), InvalidRichText::empty('blocks[0].header[0]')];
        yield 'table with uneven rows' => [Text::stored(Text::table(['Nombre', 'Finalidad'], [['PHPSESSID']])), InvalidRichText::unevenTable('table.rows', 2)];
    }

    #[DataProvider('validSeo')]
    public function testItSavesTheSearchEngineSettings(string $metaDescription, string $robots, Page $expected): void
    {
        $id = PageIdMother::DEFAULT_VALUE;
        $repository = $this->createMock(PageRepository::class);
        $repository->method('find')->willReturn(PageMother::create(id: $id, title: 'Sobre mí', slug: 'sobre-mi'));
        $repository->expects(self::once())
            ->method('save')
            ->with(self::equalTo($expected));

        self::updater($id, 'Sobre mí', 'sobre-mi', metaDescription: $metaDescription, robots: $robots)($repository, self::createStub(EventBus::class));
    }

    /**
     * @return iterable<string, array{string, string, Page}>
     */
    public static function validSeo(): iterable
    {
        $page = static fn (?string $description, Robots $robots): Page => PageMother::create(
            id: PageIdMother::DEFAULT_VALUE,
            title: 'Sobre mí',
            slug: 'sobre-mi',
            metaDescription: $description,
            robots: $robots,
        );

        yield 'meta description' => ['Quién escribe este blog.', 'index,follow', $page('Quién escribe este blog.', Robots::IndexFollow)];
        yield 'blank meta description, which means none' => ['   ', 'index,follow', $page(null, Robots::IndexFollow)];
        yield 'meta description on one line, without extra spaces' => ["  Quién escribe\n\n  este   blog.  ", 'index,follow', $page('Quién escribe este blog.', Robots::IndexFollow)];
        yield 'longest meta description, counted in characters' => [str_repeat('á', Seo::DESCRIPTION_MAX_LENGTH), 'index,follow', $page(str_repeat('á', Seo::DESCRIPTION_MAX_LENGTH), Robots::IndexFollow)];
        yield 'not listed, but followed' => ['', 'noindex,follow', $page(null, Robots::NoindexFollow)];
        yield 'hidden entirely' => ['', 'noindex,nofollow', $page(null, Robots::NoindexNofollow)];
    }

    #[DataProvider('invalidSeo')]
    public function testItSavesNothingWhenTheSearchEngineSettingsAreInvalid(string $metaDescription, string $robots, DomainError $error): void
    {
        $id = PageIdMother::DEFAULT_VALUE;
        $repository = $this->createMock(PageRepository::class);
        $repository->method('find')->willReturn(PageMother::create(id: $id, title: 'Sobre mí', slug: 'sobre-mi'));
        $repository->expects(self::never())->method('save');

        $this->expectExceptionObject($error);

        self::updater($id, 'Quién soy', 'quien-soy', metaDescription: $metaDescription, robots: $robots)($repository, self::createStub(EventBus::class));
    }

    /**
     * @return iterable<string, array{string, string, DomainError}>
     */
    public static function invalidSeo(): iterable
    {
        yield 'meta description one character too long' => [str_repeat('a', Seo::DESCRIPTION_MAX_LENGTH + 1), 'index,follow', InvalidSeo::descriptionTooLong(Seo::DESCRIPTION_MAX_LENGTH)];
        yield 'unknown robots directive' => ['', 'noindex', InvalidSeo::unknownRobots('noindex')];
        yield 'missing robots directive' => ['', '', InvalidSeo::unknownRobots('')];
    }

    public function testItSavesNothingWhenAnotherPageHasTheSlug(): void
    {
        $id = PageIdMother::DEFAULT_VALUE;
        $repository = $this->createMock(PageRepository::class);
        $repository->method('find')->willReturn(PageMother::create(id: $id, title: 'Sobre mí', slug: 'sobre-mi'));
        $repository->expects(self::once())
            ->method('findBySlug')
            ->with(self::equalTo(SlugMother::create('contacto')))
            ->willReturn(PageMother::create(id: PageIdMother::random()->value(), title: 'Contacto', slug: 'contacto'));
        $repository->expects(self::never())->method('save');

        $this->expectExceptionObject(SlugAlreadyTaken::bySlug(SlugMother::create('contacto')));

        self::updater($id, 'Sobre mí', 'contacto')($repository, self::createStub(EventBus::class));
    }

    public function testItSavesThePageWhenTheSlugIsAlreadyItsOwn(): void
    {
        $id = PageIdMother::DEFAULT_VALUE;
        $repository = $this->createMock(PageRepository::class);
        $repository->method('find')->willReturn(PageMother::create(id: $id, title: 'Sobre mí', slug: 'sobre-mi'));
        // A separate instance with the same id, as the database would return it.
        $repository->method('findBySlug')->willReturn(PageMother::create(id: $id, title: 'Sobre mí', slug: 'sobre-mi'));
        $repository->expects(self::once())
            ->method('save')
            ->with(self::equalTo(PageMother::create(id: $id, title: 'Quién soy', slug: 'sobre-mi')));

        self::updater($id, 'Quién soy', 'sobre-mi')($repository, self::createStub(EventBus::class));
    }

    public function testItSavesTheHomePageWithTheEditedTitleAndStillNoSlug(): void
    {
        $id = PageIdMother::DEFAULT_VALUE;
        $repository = $this->createMock(PageRepository::class);
        $repository->method('find')->willReturn(PageMother::home(id: $id, title: 'Portada'));
        $repository->expects(self::once())
            ->method('save')
            ->with(self::equalTo(PageMother::home(id: $id, title: 'Inicio')));

        self::updater($id, 'Inicio', '')($repository, self::createStub(EventBus::class));
    }

    public function testItSavesNothingWhenTheHomePageIsGivenASlug(): void
    {
        $id = PageIdMother::DEFAULT_VALUE;
        $repository = $this->createMock(PageRepository::class);
        $repository->method('find')->willReturn(PageMother::home(id: $id, title: 'Portada'));
        $repository->expects(self::never())->method('save');

        $this->expectExceptionObject(InvalidSlug::onHome('inicio'));

        self::updater($id, 'Inicio', 'inicio')($repository, self::createStub(EventBus::class));
    }

    public function testItSavesNothingWhenTheHomePageEditIsInvalid(): void
    {
        $id = PageIdMother::DEFAULT_VALUE;
        $repository = $this->createMock(PageRepository::class);
        $repository->method('find')->willReturn(PageMother::home(id: $id, title: 'Portada'));
        $repository->expects(self::never())->method('save');

        $this->expectExceptionObject(InvalidTitle::tooShort(Title::MIN_LENGTH));

        self::updater($id, 'Sí', '')($repository, self::createStub(EventBus::class));
    }

    public function testItSavesAUniquePageWithTheEditedTitleAndSlugKeepingItsType(): void
    {
        $id = PageIdMother::DEFAULT_VALUE;
        $repository = $this->createMock(PageRepository::class);
        $repository->method('find')->willReturn(PageMother::create(id: $id, title: 'Contacto', slug: 'contacto', type: PageType::Contact));
        $repository->expects(self::once())
            ->method('save')
            ->with(self::equalTo(PageMother::create(id: $id, title: 'Escríbeme', slug: 'escribeme', type: PageType::Contact)));

        self::updater($id, 'Escríbeme', 'escribeme')($repository, self::createStub(EventBus::class));
    }

    public function testItSavesAPageLeftWithoutASlug(): void
    {
        $id = PageIdMother::DEFAULT_VALUE;
        $repository = $this->createMock(PageRepository::class);
        $repository->method('find')->willReturn(PageMother::create(id: $id, title: 'Contacto', slug: 'contacto', type: PageType::Contact));
        $repository->expects(self::never())->method('findBySlug');
        $repository->expects(self::once())
            ->method('save')
            ->with(self::equalTo(PageMother::create(id: $id, title: 'Contacto', slug: '', type: PageType::Contact)));

        self::updater($id, 'Contacto', '')($repository, self::createStub(EventBus::class));
    }

    /**
     * @param array<array-key, mixed> $content
     */
    #[DataProvider('datedEdits')]
    public function testItDatesThePageOnlyWhenSomethingChanges(string $title, array $content, string $metaDescription, string $updatedAt): void
    {
        $id = PageIdMother::DEFAULT_VALUE;
        $repository = $this->createMock(PageRepository::class);
        $repository->method('find')->willReturn(PageMother::create(
            id: $id,
            title: 'Sobre mí',
            slug: 'sobre-mi',
            content: PageMother::CONTENT,
            metaDescription: PageMother::META_DESCRIPTION,
            updatedAt: self::BEFORE,
        ));
        $repository->expects(self::once())
            ->method('save')
            ->with(self::equalTo(PageMother::create(
                id: $id,
                title: $title,
                slug: 'sobre-mi',
                content: $content,
                metaDescription: '' === trim($metaDescription) ? null : trim($metaDescription),
                updatedAt: $updatedAt,
            )));

        self::updater($id, $title, 'sobre-mi', $content, $metaDescription)($repository, self::createStub(EventBus::class));
    }

    /**
     * @return iterable<string, array{string, array<array-key, mixed>, string, string}>
     */
    public static function datedEdits(): iterable
    {
        yield 'nothing changed' => ['Sobre mí', PageMother::CONTENT, PageMother::META_DESCRIPTION, self::BEFORE];
        yield 'only whitespace changed' => ['  Sobre   mí ', PageMother::CONTENT, ' '.PageMother::META_DESCRIPTION, self::BEFORE];
        yield 'the title changed' => ['Quién soy', PageMother::CONTENT, PageMother::META_DESCRIPTION, ClockMother::NOW];
        yield 'the content changed' => ['Sobre mí', Text::stored(Text::paragraph('Otro texto.')), PageMother::META_DESCRIPTION, ClockMother::NOW];
        yield 'the meta description changed' => ['Sobre mí', PageMother::CONTENT, '', ClockMother::NOW];
    }

    public function testItLeavesThePublishedVersionOnTheSiteAndMarksThePageAsChanged(): void
    {
        $id = PageIdMother::DEFAULT_VALUE;
        $repository = $this->createMock(PageRepository::class);
        $repository->method('find')->willReturn(PageMother::published(id: $id, title: 'Sobre mí', slug: 'sobre-mi', updatedAt: self::BEFORE));
        $repository->expects(self::once())
            ->method('save')
            ->with(self::equalTo(PageMother::published(id: $id, title: 'Quién soy', slug: 'sobre-mi', publishedTitle: 'Sobre mí')));

        self::updater($id, 'Quién soy', 'sobre-mi', PageMother::CONTENT, PageMother::META_DESCRIPTION)($repository, self::createStub(EventBus::class));
    }

    public function testItSavesAPublishedPageWithoutWhatItNeedsToBePublishedAgain(): void
    {
        $id = PageIdMother::DEFAULT_VALUE;
        $repository = $this->createMock(PageRepository::class);
        $repository->method('find')->willReturn(PageMother::published(id: $id, title: 'Sobre mí', slug: 'sobre-mi', updatedAt: self::BEFORE));
        $repository->expects(self::once())
            ->method('save')
            ->with(self::equalTo(PageMother::published(
                id: $id,
                title: 'Sobre mí',
                slug: 'sobre-mi',
                content: Text::EMPTY,
                metaDescription: null,
                publishedContent: PageMother::CONTENT,
                publishedMetaDescription: PageMother::META_DESCRIPTION,
            )));

        self::updater($id, 'Sobre mí', 'sobre-mi', Text::EMPTY, '')($repository, self::createStub(EventBus::class));
    }

    public function testItThrowsWhenThePageDoesNotExist(): void
    {
        $id = PageIdMother::DEFAULT_VALUE;
        $repository = $this->createMock(PageRepository::class);
        $repository->method('find')->willReturn(null);
        $repository->expects(self::never())->method('save');

        $this->expectExceptionObject(PageNotFound::withId($id));

        self::updater($id, 'Quién soy', 'quien-soy')($repository, self::createStub(EventBus::class));
    }

    public function testItRejectsAnIdThatIsNotAUuid(): void
    {
        $repository = $this->createMock(PageRepository::class);
        $repository->expects(self::never())->method('find');
        $repository->expects(self::never())->method('save');

        $this->expectExceptionObject(InvalidUuid::withValue('page-id'));

        self::updater('page-id', 'Quién soy', 'quien-soy')($repository, self::createStub(EventBus::class));
    }

    /**
     * An edit that leaves the content and search engine settings as a new
     * page has them, unless a test says otherwise.
     *
     * @param array<array-key, mixed> $content
     */
    private static function updater(
        string $id,
        string $title,
        string $slug,
        array $content = Text::EMPTY,
        string $metaDescription = '',
        string $robots = 'index,follow',
    ): PageUpdater {
        return new PageUpdater($id, $title, $slug, $content, $metaDescription, $robots);
    }
}
