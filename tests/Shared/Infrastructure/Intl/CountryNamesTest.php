<?php

declare(strict_types=1);

namespace Shared\Tests\Infrastructure\Intl;

use PHPUnit\Framework\TestCase;
use Shared\Infrastructure\Intl\CountryNames;

final class CountryNamesTest extends TestCase
{
    public function testItNamesACountryInTheLanguage(): void
    {
        self::assertSame(['Argentina', 'Estados Unidos', 'United States'], [
            new CountryNames()->name('AR', 'es'),
            new CountryNames()->name('US', 'es'),
            new CountryNames()->name('US', 'en'),
        ]);
    }

    public function testItGivesTheCodeOfACountryItCannotName(): void
    {
        self::assertSame('ZZ', new CountryNames()->name('ZZ', 'es'));
    }
}
