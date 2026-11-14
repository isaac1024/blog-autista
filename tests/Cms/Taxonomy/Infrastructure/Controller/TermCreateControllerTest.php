<?php

declare(strict_types=1);

namespace Cms\Tests\Taxonomy\Infrastructure\Controller;

use Cms\Taxonomy\Domain\Taxonomy;
use Cms\Taxonomy\Domain\TermId;
use Cms\Taxonomy\Domain\TermRepository;
use Cms\Tests\Taxonomy\Domain\TermIdMother;
use Cms\Tests\Taxonomy\Domain\TermMother;
use Symfony\Component\HttpFoundation\Response;

final class TermCreateControllerTest extends TermControllerTestCase
{
    public function testItRedirectsAnonymousVisitorsToTheLoginForm(): void
    {
        $client = self::createClient();

        $this->post($client, 'cms_terms_create', self::HOST.'/sections', ['name' => 'Vida diaria']);

        self::assertResponseRedirects(self::HOST.'/login');
    }

    public function testItCreatesTheTermAndOpensItInTheEditor(): void
    {
        $client = self::createAuthenticatedClient();

        $this->post($client, 'cms_terms_create', self::HOST.'/tags', ['name' => 'Sensorialidad']);

        self::assertResponseStatusCodeSame(Response::HTTP_SEE_OTHER);
        $location = (string) $client->getResponse()->headers->get('Location');
        self::assertMatchesRegularExpression('#^/tags/[0-9a-f-]{36}$#', $location);
        self::assertSame([['key' => 'cms.tags.notices.created', 'parameters' => []]], self::successNotices($client));
        $term = self::getContainer()->get(TermRepository::class)->find(new TermId(basename($location)));
        self::assertSame(Taxonomy::Tag, $term?->taxonomy());
        self::assertSame('sensorialidad', $term->slug()?->value());
    }

    /**
     * What is refused is told on the list it goes back to, and nothing is kept.
     */
    public function testItGoesBackToTheListWhenTheTermIsRefused(): void
    {
        $client = self::createAuthenticatedClient();
        $this->saveTerm(TermMother::create(id: TermIdMother::random()->value()));

        $this->post($client, 'cms_terms_create', self::HOST.'/sections', ['name' => 'Vida diaria']);

        self::assertResponseRedirects('/sections', Response::HTTP_SEE_OTHER);
        self::assertSame([['key' => 'errors.term.slug.taken', 'parameters' => ['%slug%' => 'vida-diaria']]], self::errorNotices($client));
    }

    public function testItRefusesASectionAtAnAddressTheSiteReserves(): void
    {
        $client = self::createAuthenticatedClient();

        $this->post($client, 'cms_terms_create', self::HOST.'/sections', ['name' => 'Autor']);

        self::assertResponseRedirects('/sections', Response::HTTP_SEE_OTHER);
        self::assertSame([['key' => 'errors.term.slug.reserved', 'parameters' => ['%slug%' => 'autor']]], self::errorNotices($client));
    }
}
