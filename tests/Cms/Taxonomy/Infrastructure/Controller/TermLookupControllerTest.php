<?php

declare(strict_types=1);

namespace Cms\Tests\Taxonomy\Infrastructure\Controller;

use Cms\Taxonomy\Domain\Taxonomy;
use Cms\Tests\Taxonomy\Domain\TermIdMother;
use Cms\Tests\Taxonomy\Domain\TermMother;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * The terms of a taxonomy a field holds, looked up by their ids as JSON,
 * each named as the title of what is picked, as their search names them.
 */
final class TermLookupControllerTest extends TermControllerTestCase
{
    private const string SECTION = '01a1da50-2b7c-7d01-8a3f-5c2e9b1d0f11';
    private const string TAG = '01a1da50-2b7c-7d01-8a3f-5c2e9b1d0f12';

    public function testItRedirectsAnonymousVisitorsToTheLoginForm(): void
    {
        $client = self::createClient();

        $client->request('GET', self::HOST.'/tags/lookup');

        self::assertResponseRedirects(self::HOST.'/login');
    }

    /**
     * Only those of the taxonomy asked; an id of no term of it, or no id at
     * all, is left out.
     */
    public function testItLooksUpTheTermsOfTheTaxonomyWithTheIdsAsked(): void
    {
        $client = $this->clientWithTerms();

        $client->request('GET', self::HOST.'/tags/lookup?ids='.implode(',', [self::TAG, self::SECTION, TermIdMother::random()->value(), 'etiqueta', '']));

        self::assertResponseIsSuccessful();
        self::assertSame(['terms' => [['id' => self::TAG, 'title' => 'Sueño', 'status' => self::label('published'), 'published' => true]]], self::json($client));
    }

    /**
     * Sections and tags a text links to are looked up together, whatever
     * their taxonomy.
     */
    public function testItLooksUpTermsOfEveryTaxonomyTogether(): void
    {
        $client = $this->clientWithTerms();

        $client->request('GET', self::HOST.'/terms/lookup?ids='.implode(',', [self::TAG, self::SECTION, 'etiqueta', '']));

        self::assertResponseIsSuccessful();
        self::assertSame(['terms' => [
            ['id' => self::TAG, 'title' => 'Sueño', 'status' => self::label('published'), 'published' => true],
            ['id' => self::SECTION, 'title' => TermMother::NAME, 'status' => self::label('draft'), 'published' => false],
        ]], self::json($client));
    }

    public function testItSearchesTheTermsNamedAsTheTitleOfWhatIsPicked(): void
    {
        $client = $this->clientWithTerms();

        $client->request('GET', self::HOST.'/sections/search?name=vida');

        self::assertResponseIsSuccessful();
        self::assertSame(['terms' => [['id' => self::SECTION, 'title' => TermMother::NAME, 'status' => self::label('draft'), 'published' => false]], 'more' => false], self::json($client));
    }

    private function clientWithTerms(): KernelBrowser
    {
        $client = self::createAuthenticatedClient();
        $client->disableReboot();
        $this->saveTerm(TermMother::create(id: self::SECTION));
        $this->saveTerm(TermMother::published(id: self::TAG, taxonomy: Taxonomy::Tag, name: 'Sueño', slug: 'sueno'));

        return $client;
    }

    private static function label(string $status): string
    {
        return self::getContainer()->get(TranslatorInterface::class)->trans(\sprintf('cms.terms.status.%s.label', $status));
    }

    /**
     * @return array<mixed>
     */
    private static function json(KernelBrowser $client): array
    {
        $data = json_decode((string) $client->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);
        self::assertIsArray($data);

        return $data;
    }
}
