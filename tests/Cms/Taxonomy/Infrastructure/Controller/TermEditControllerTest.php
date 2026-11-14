<?php

declare(strict_types=1);

namespace Cms\Tests\Taxonomy\Infrastructure\Controller;

use Cms\Taxonomy\Domain\Taxonomy;
use Cms\Taxonomy\Domain\Term;
use Cms\Tests\Taxonomy\Domain\TermIdMother;
use Cms\Tests\Taxonomy\Domain\TermMother;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpFoundation\Response;

final class TermEditControllerTest extends TermControllerTestCase
{
    public function testItRedirectsAnonymousVisitorsToTheLoginForm(): void
    {
        $client = self::createClient();
        $term = $this->saveTerm(TermMother::create(id: TermIdMother::random()->value()));

        $client->request('GET', self::HOST.'/sections/'.$term->id()->value());

        self::assertResponseRedirects(self::HOST.'/login');
    }

    /**
     * Every state of a term opens its editor, with the actions it allows.
     */
    #[DataProvider('terms')]
    public function testItOpensTheEditorOfTheTerm(string $segment, Term $term): void
    {
        $client = self::createAuthenticatedClient();
        $this->saveTerm($term);

        $client->request('GET', self::HOST.'/'.$segment.'/'.$term->id()->value());

        self::assertResponseIsSuccessful();
        self::assertRouteSame('cms_terms_edit', ['taxonomy' => $segment, 'id' => $term->id()->value()]);
    }

    /**
     * @return iterable<string, array{string, Term}>
     */
    public static function terms(): iterable
    {
        yield 'a draft section' => ['sections', TermMother::create(id: TermIdMother::random()->value())];
        yield 'a section without a slug' => ['sections', TermMother::create(id: TermIdMother::random()->value(), slug: '')];
        yield 'a published tag' => ['tags', TermMother::published(id: TermIdMother::random()->value(), taxonomy: Taxonomy::Tag)];
        yield 'a tag published with changes' => ['tags', TermMother::published(id: TermIdMother::random()->value(), taxonomy: Taxonomy::Tag, publishedName: 'Antes')];
        yield 'an unpublished section' => ['sections', TermMother::unpublished(id: TermIdMother::random()->value())];
    }

    /**
     * A tag is never edited as a section, nor the other way round.
     */
    public function testItRespondsNotFoundForATermOfAnotherTaxonomy(): void
    {
        $client = self::createAuthenticatedClient();
        $term = $this->saveTerm(TermMother::create(id: TermIdMother::random()->value(), taxonomy: Taxonomy::Tag));

        $client->request('GET', self::HOST.'/sections/'.$term->id()->value());

        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    public function testItRespondsNotFoundWhenTheTermDoesNotExist(): void
    {
        $client = self::createAuthenticatedClient();

        $client->request('GET', self::HOST.'/tags/'.TermIdMother::random()->value());

        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }
}
