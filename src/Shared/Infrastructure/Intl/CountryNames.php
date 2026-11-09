<?php

declare(strict_types=1);

namespace Shared\Infrastructure\Intl;

use Symfony\Component\Intl\Countries;

/**
 * Names countries by their ISO 3166 code in a language. Wraps symfony/intl,
 * so contexts never depend on it directly.
 */
final readonly class CountryNames
{
    /**
     * The name of the country in the language, or its code when it has none.
     */
    public function name(string $code, string $locale): string
    {
        return Countries::exists($code) ? Countries::getName($code, $locale) : $code;
    }
}
