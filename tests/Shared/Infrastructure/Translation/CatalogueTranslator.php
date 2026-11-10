<?php

declare(strict_types=1);

namespace Shared\Tests\Infrastructure\Translation;

use PHPUnit\Framework\Assert;
use Symfony\Component\Translation\IdentityTranslator;
use Symfony\Component\Translation\Loader\XliffFileLoader;
use Symfony\Component\Translation\Loader\YamlFileLoader;
use Symfony\Component\Translation\MessageCatalogue;
use Symfony\Contracts\Translation\LocaleAwareInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * A translator for unit tests that fails on any key the catalogues of the
 * site don't define, in every language it is in, or that is given other
 * parameters than its placeholders, and otherwise answers the key itself,
 * followed by what its placeholders are given: a test tells which text is
 * chosen, and with what, without reading the text.
 */
final class CatalogueTranslator implements LocaleAwareInterface, TranslatorInterface
{
    private const string TRANSLATIONS = __DIR__.'/../../../../translations/';

    private const string DOMAIN = 'messages';

    /**
     * Domains the site takes from its libraries rather than translating
     * itself, with where each keeps its catalogue of a locale.
     */
    private const array LIBRARY_DOMAINS = [
        'security' => __DIR__.'/../../../../vendor/symfony/security-core/Resources/translations/security.%s.xlf',
    ];

    private const string PLACEHOLDER = '/%[a-z_]+%/';

    /**
     * The parameter the translator picks the plural form of a message by.
     */
    private const string PLURAL_COUNT = '%count%';

    /**
     * Keyed by locale, one for each language the site is translated to,
     * holding its own domain and those of its libraries.
     *
     * @var ?array<string, MessageCatalogue>
     */
    private static ?array $catalogues = null;

    private readonly IdentityTranslator $identity;

    public function __construct()
    {
        $this->identity = new IdentityTranslator();
    }

    /**
     * @param array<array-key, mixed> $parameters
     */
    #[\Override]
    public function trans(string $id, array $parameters = [], ?string $domain = null, ?string $locale = null): string
    {
        $given = array_keys($parameters);
        sort($given);

        foreach (self::catalogues() as $catalogueLocale => $catalogue) {
            Assert::assertTrue(
                $catalogue->defines($id, $domain ?? self::DOMAIN),
                \sprintf('The "%s" catalogue doesn\'t define "%s".', $catalogueLocale, $id),
            );
            // Every placeholder of the message is given its value, and nothing
            // else is, but the count that picks its plural, painted or not.
            preg_match_all(self::PLACEHOLDER, $catalogue->get($id, $domain ?? self::DOMAIN), $matches);
            $placeholders = array_values(array_unique($matches[0]));
            sort($placeholders);
            Assert::assertSame($placeholders, array_values(array_filter(
                $given,
                static fn (int|string $placeholder): bool => self::PLURAL_COUNT !== $placeholder || \in_array(self::PLURAL_COUNT, $placeholders, true),
            )), \sprintf('"%s" in the "%s" catalogue takes other parameters than those given.', $id, $catalogueLocale));
        }

        if ([] === $parameters) {
            return $id;
        }

        // What each placeholder is given shows next to the key, in order.
        ksort($parameters);

        return \sprintf('%s(%s)', $id, implode(', ', array_map(
            static fn (string $placeholder, mixed $value): string => \sprintf('%s=%s', $placeholder, \is_scalar($value) || $value instanceof \Stringable ? (string) $value : get_debug_type($value)),
            array_keys($parameters),
            $parameters,
        )));
    }

    #[\Override]
    public function getLocale(): string
    {
        return $this->identity->getLocale();
    }

    #[\Override]
    public function setLocale(string $locale): void
    {
        $this->identity->setLocale($locale);
    }

    /**
     * @return array<string, MessageCatalogue>
     */
    private static function catalogues(): array
    {
        if (null === self::$catalogues) {
            self::$catalogues = [];
            $loader = new YamlFileLoader();
            $libraryLoader = new XliffFileLoader();
            $files = glob(self::TRANSLATIONS.self::DOMAIN.'.*.yaml');
            Assert::assertIsArray($files, 'The catalogues of the site could not be listed.');

            foreach ($files as $file) {
                $locale = explode('.', basename($file))[1];
                $catalogue = $loader->load($file, $locale, self::DOMAIN);

                foreach (self::LIBRARY_DOMAINS as $domain => $path) {
                    $catalogue->addCatalogue($libraryLoader->load(\sprintf($path, $locale), $locale, $domain));
                }

                self::$catalogues[$locale] = $catalogue;
            }

            Assert::assertNotEmpty(self::$catalogues, 'No catalogue of the site was found.');
        }

        return self::$catalogues;
    }
}
