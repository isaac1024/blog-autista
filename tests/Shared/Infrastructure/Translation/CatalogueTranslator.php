<?php

declare(strict_types=1);

namespace Shared\Tests\Infrastructure\Translation;

use PHPUnit\Framework\Assert;
use Symfony\Component\Translation\IdentityTranslator;
use Symfony\Component\Translation\Loader\YamlFileLoader;
use Symfony\Component\Translation\MessageCatalogue;
use Symfony\Contracts\Translation\LocaleAwareInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * A translator for unit tests that fails on any key the catalogues of the
 * site don't define, in every language it is in, or that is given other
 * parameters than its placeholders, and otherwise answers the key itself: a
 * test tells which text is chosen without reading the text.
 */
final class CatalogueTranslator implements LocaleAwareInterface, TranslatorInterface
{
    private const string TRANSLATIONS = __DIR__.'/../../../../translations/';

    private const string DOMAIN = 'messages';

    private const string PLACEHOLDER = '/%[a-z_]+%/';

    /**
     * Keyed by locale, one for each language the site is translated to.
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
            // Every placeholder of the message is given its value, and nothing else is.
            preg_match_all(self::PLACEHOLDER, $catalogue->get($id, $domain ?? self::DOMAIN), $matches);
            $placeholders = array_values(array_unique($matches[0]));
            sort($placeholders);
            Assert::assertSame($placeholders, $given, \sprintf('"%s" in the "%s" catalogue takes other parameters than those given.', $id, $catalogueLocale));
        }

        return $this->identity->trans($id, $parameters, $domain, $locale);
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
            $files = glob(self::TRANSLATIONS.self::DOMAIN.'.*.yaml');
            Assert::assertIsArray($files, 'The catalogues of the site could not be listed.');

            foreach ($files as $file) {
                $locale = explode('.', basename($file))[1];
                self::$catalogues[$locale] = $loader->load($file, $locale, self::DOMAIN);
            }

            Assert::assertNotEmpty(self::$catalogues, 'No catalogue of the site was found.');
        }

        return self::$catalogues;
    }
}
