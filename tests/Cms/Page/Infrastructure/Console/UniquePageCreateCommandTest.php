<?php

declare(strict_types=1);

namespace Cms\Tests\Page\Infrastructure\Console;

use Cms\Page\Domain\Page;
use Cms\Page\Domain\PageRepository;
use Cms\Page\Domain\PageType;
use Cms\Page\Domain\Slug;
use Cms\Page\Domain\Title;
use Cms\Tests\Page\Domain\PageIdMother;
use Cms\Tests\Page\Domain\PageMother;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use Shared\Domain\Criteria\Criteria;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandCompletionTester;
use Symfony\Component\Console\Tester\CommandTester;

final class UniquePageCreateCommandTest extends KernelTestCase
{
    private const string NAME = 'cms:page:create-unique';

    public function testItCreatesTheHomePageWithoutASlug(): void
    {
        $exitCode = $this->command()->execute(['type' => 'home', 'title' => 'Portada']);

        self::assertSame(Command::SUCCESS, $exitCode);
        $home = $this->storedPage(PageType::Home);
        self::assertNotNull($home);
        self::assertEquals(new Title('Portada'), $home->title());
        self::assertNull($home->slug());
    }

    public function testItCreatesAUniquePageWithTheSlugDerivedFromTheTitle(): void
    {
        $exitCode = $this->command()->execute(['type' => 'cookie_policy', 'title' => 'Política de cookies']);

        self::assertSame(Command::SUCCESS, $exitCode);
        $page = $this->storedPage(PageType::CookiePolicy);
        self::assertEquals(new Title('Política de cookies'), $page?->title());
        self::assertEquals(new Slug('politica-cookies'), $page?->slug());
    }

    /**
     * @param array{type: string, title: string} $input
     */
    #[DataProvider('invalidInputs')]
    public function testItFailsAndCreatesNothingWhenTheInputIsInvalid(array $input): void
    {
        $exitCode = $this->command()->execute($input);

        self::assertSame(Command::FAILURE, $exitCode);
        self::assertSame(0, $this->pageCount());
    }

    /**
     * @return iterable<string, array{array{type: string, title: string}}>
     */
    public static function invalidInputs(): iterable
    {
        yield 'generic pages are created from the CMS' => [['type' => 'standard', 'title' => 'Sobre mí']];
        yield 'unknown type' => [['type' => 'about', 'title' => 'Sobre mí']];
        yield 'invalid title' => [['type' => 'contact', 'title' => 'Sí']];
    }

    public function testItFailsAndKeepsTheExistingPageWhenItAlreadyExists(): void
    {
        $existing = PageMother::create(id: PageIdMother::random()->value(), title: 'Contacto', slug: 'contacto', type: PageType::Contact);
        self::getContainer()->get(PageRepository::class)->save($existing);

        $exitCode = $this->command()->execute(['type' => 'contact', 'title' => 'Escríbeme']);

        self::assertSame(Command::FAILURE, $exitCode);
        self::assertSame(1, $this->pageCount());
        self::assertEquals($existing, $this->storedPage(PageType::Contact));
    }

    public function testItSuggestsOnlyTheUniqueTypes(): void
    {
        $tester = new CommandCompletionTester($this->application()->find(self::NAME));

        self::assertSame(
            ['home', 'contact', 'search', 'legal_notice', 'privacy_policy', 'cookie_policy'],
            $tester->complete(['']),
        );
    }

    private function command(): CommandTester
    {
        return new CommandTester($this->application()->find(self::NAME));
    }

    /**
     * Reuses the booted kernel, so fixtures, command and assertions share one
     * database connection.
     */
    private function application(): Application
    {
        return new Application(self::$kernel ?? self::bootKernel());
    }

    /**
     * Reads the page back from the database, not from Doctrine's identity map.
     */
    private function storedPage(PageType $type): ?Page
    {
        self::getContainer()->get(EntityManagerInterface::class)->clear();

        return self::getContainer()->get(PageRepository::class)->findByType($type);
    }

    private function pageCount(): int
    {
        return \count(self::getContainer()->get(PageRepository::class)->matching(
            new Criteria(limit: Criteria::MAX_LIMIT, filters: [], order: null, offset: 0),
        ));
    }
}
