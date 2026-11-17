<?php

declare(strict_types=1);

namespace Cms\Tests\Shared\Infrastructure\Ui;

use Cms\Page\Application\PageResponse;
use Cms\Page\Domain\PageType;
use Cms\Shared\Infrastructure\Ui\PageRole;
use Cms\Tests\Page\Domain\PageMother;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\Translation\TranslatorInterface;

final class PageRoleTest extends TestCase
{
    /**
     * A unique page is named by its role, unless its title already says it,
     * in whatever case and with whatever accents; a generic page has none.
     */
    #[DataProvider('titles')]
    public function testItNamesTheRoleUnlessTheTitleSaysIt(PageType $type, string $title, string $translation, ?string $role): void
    {
        $translator = self::createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturnMap([['cms.pages.types.privacy_policy', [], null, null, $translation]]);

        $page = PageMother::create(title: $title, slug: 'privacidad', type: $type);

        self::assertSame($role, new PageRole($translator)(PageResponse::fromPage($page)));
    }

    /**
     * @return iterable<string, array{PageType, string, string, ?string}>
     */
    public static function titles(): iterable
    {
        yield 'a title of its own' => [PageType::PrivacyPolicy, 'Tus datos', 'Política de privacidad', 'Política de privacidad'];
        yield 'a title that says it' => [PageType::PrivacyPolicy, 'Política de privacidad', 'Política de privacidad', null];
        yield 'a title that says it in capitals' => [PageType::PrivacyPolicy, 'POLÍTICA DE PRIVACIDAD', 'Política de privacidad', null];
        yield 'a role named in capitals' => [PageType::PrivacyPolicy, 'Política de privacidad', 'POLÍTICA DE PRIVACIDAD', null];
        yield 'a generic page' => [PageType::Standard, 'Política de privacidad', 'Política de privacidad', null];
    }
}
