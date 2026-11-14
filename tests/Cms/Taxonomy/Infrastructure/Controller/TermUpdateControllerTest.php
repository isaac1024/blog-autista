<?php

declare(strict_types=1);

namespace Cms\Tests\Taxonomy\Infrastructure\Controller;

use Cms\Shared\Domain\Robots;
use Cms\Taxonomy\Domain\Taxonomy;
use Cms\Tests\Taxonomy\Domain\TermIdMother;
use Cms\Tests\Taxonomy\Domain\TermMother;
use Shared\Tests\Domain\ClockMother;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;
use Symfony\Component\HttpFoundation\Response;

final class TermUpdateControllerTest extends TermControllerTestCase
{
    use ClockSensitiveTrait;

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();
        self::mockTime(ClockMother::NOW);
    }

    public function testItRedirectsAnonymousVisitorsToTheLoginForm(): void
    {
        $client = self::createClient();
        $term = $this->saveTerm(TermMother::create(id: TermIdMother::random()->value()));

        $this->post($client, 'cms_terms_update', self::HOST.'/sections/'.$term->id()->value(), self::fields('Otra', 'otra'));

        self::assertResponseRedirects(self::HOST.'/login');
        self::assertEquals($term, $this->storedTerm($term));
    }

    public function testItSavesTheTermAndStaysInTheEditor(): void
    {
        $client = self::createAuthenticatedClient();
        $id = TermIdMother::random()->value();
        $term = $this->saveTerm(TermMother::create(id: $id, taxonomy: Taxonomy::Tag));

        $this->post($client, 'cms_terms_update', self::HOST.'/tags/'.$id, self::fields('Sueño', 'sueno', 'Artículos sobre el sueño', 'noindex,follow'));

        self::assertResponseRedirects('/tags/'.$id, Response::HTTP_SEE_OTHER);
        self::assertSame([['key' => 'cms.tags.notices.saved', 'parameters' => []]], self::successNotices($client));
        self::assertEquals(
            TermMother::create(id: $id, taxonomy: Taxonomy::Tag, name: 'Sueño', slug: 'sueno', metaDescription: 'Artículos sobre el sueño', robots: Robots::NoindexFollow),
            $this->storedTerm($term),
        );
    }

    public function testItGoesBackAndKeepsTheTermWhenAnEditIsRefused(): void
    {
        $client = self::createAuthenticatedClient();
        $id = TermIdMother::random()->value();
        $term = $this->saveTerm(TermMother::create(id: $id));

        $this->post($client, 'cms_terms_update', self::HOST.'/sections/'.$id, self::fields(TermMother::NAME, 'media'));

        self::assertResponseRedirects(self::HOST.'/sections/'.$id, Response::HTTP_SEE_OTHER);
        self::assertSame([['key' => 'errors.term.slug.reserved', 'parameters' => ['%slug%' => 'media']]], self::errorNotices($client));
        self::assertEquals($term, $this->storedTerm($term));
    }

    public function testItRespondsNotFoundForATermOfAnotherTaxonomy(): void
    {
        $client = self::createAuthenticatedClient();
        $term = $this->saveTerm(TermMother::create(id: TermIdMother::random()->value()));

        $this->post($client, 'cms_terms_update', self::HOST.'/tags/'.$term->id()->value(), self::fields('Otra', 'otra'));

        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
        self::assertEquals($term, $this->storedTerm($term));
    }

    /**
     * @return array<string, string>
     */
    private static function fields(string $name, string $slug, string $metaDescription = '', string $robots = 'index,follow'): array
    {
        return ['name' => $name, 'slug' => $slug, 'meta_description' => $metaDescription, 'robots' => $robots];
    }
}
