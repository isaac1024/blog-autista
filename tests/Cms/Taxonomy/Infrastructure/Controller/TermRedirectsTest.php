<?php

declare(strict_types=1);

namespace Cms\Tests\Taxonomy\Infrastructure\Controller;

use Cms\Page\Domain\PageRepository;
use Cms\Redirect\Application\PublicationOutlookFinder;
use Cms\Redirect\Domain\Redirect;
use Cms\Redirect\Domain\RedirectRepository;
use Cms\Redirect\Domain\Resource;
use Cms\Redirect\Domain\SiteResources;
use Cms\Redirect\Domain\Source;
use Cms\Redirect\Domain\Target;
use Cms\Taxonomy\Domain\Taxonomy;
use Cms\Tests\Page\Domain\PageMother;
use Cms\Tests\Redirect\Domain\RedirectMother;
use Cms\Tests\Taxonomy\Domain\TermIdMother;
use Cms\Tests\Taxonomy\Domain\TermMother;
use Shared\Tests\Domain\ClockMother;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;
use Symfony\Component\HttpFoundation\Response;

/**
 * What publishing a section or a tag and taking it off the site do to the
 * redirects, as an editor does it: the address it leaves is asked about
 * first, and its own once off the site leads where it is told.
 */
final class TermRedirectsTest extends TermControllerTestCase
{
    use ClockSensitiveTrait;

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();
        self::mockTime(ClockMother::NOW);
    }

    public function testItAsksFirstWhenPublishingLeavesAnAddress(): void
    {
        $client = self::createAuthenticatedClient();
        $id = TermIdMother::random()->value();
        $this->saveTerm(TermMother::published(id: $id, slug: 'dia-a-dia', publishedSlug: TermMother::SLUG));

        $this->post($client, 'cms_terms_publish', self::HOST.'/sections/'.$id.'/publish');

        self::assertResponseRedirects(self::HOST.'/sections/'.$id.'/publish', Response::HTTP_SEE_OTHER);
        $client->request('GET', self::HOST.'/sections/'.$id.'/publish');
        self::assertResponseIsSuccessful();
    }

    public function testItLeadsTheAddressItLeavesToTheTermOnceConfirmed(): void
    {
        $client = self::createAuthenticatedClient();
        $id = TermIdMother::random()->value();
        $term = $this->saveTerm(TermMother::published(id: $id, slug: 'dia-a-dia', publishedSlug: TermMother::SLUG));

        $this->post($client, 'cms_terms_publish', self::HOST.'/sections/'.$id.'/publish', [
            'outlook' => $this->outlook(Resource::term($id)),
            'previous' => ['action' => 'itself'],
        ]);

        self::assertResponseRedirects(self::HOST.'/sections/'.$id, Response::HTTP_SEE_OTHER);
        self::assertSame('dia-a-dia', $this->storedTerm($term)?->publishedSlug()?->value());
        self::assertEquals(Target::leadingTo(Resource::term($id)), $this->redirectAt('/vida-diaria/')?->target());
    }

    public function testItGoesBackToTheEditorFromAConfirmationWithNothingToAsk(): void
    {
        $client = self::createAuthenticatedClient();
        $id = TermIdMother::random()->value();
        $this->saveTerm(TermMother::published(id: $id, publishedName: 'Antes'));

        $client->request('GET', self::HOST.'/sections/'.$id.'/publish');

        self::assertResponseRedirects(self::HOST.'/sections/'.$id, Response::HTTP_SEE_OTHER);
    }

    public function testItLeadsItsAddressWhereChosenAsItGoesOffTheSite(): void
    {
        $client = self::createAuthenticatedClient();
        $id = TermIdMother::random()->value();
        $this->saveTerm(TermMother::published(id: $id, taxonomy: Taxonomy::Tag, name: 'Sueño', slug: 'sueno'));
        self::getContainer()->get(PageRepository::class)->save(PageMother::published(id: RedirectMother::PAGE, title: 'Otra', slug: 'otra'));

        $client->request('GET', self::HOST.'/tags/'.$id.'/unpublish');
        self::assertResponseIsSuccessful();
        $this->post($client, 'cms_terms_unpublish', self::HOST.'/tags/'.$id.'/unpublish', ['address' => ['action' => 'page', 'page_id' => RedirectMother::PAGE]]);

        self::assertResponseRedirects(self::HOST.'/tags/'.$id, Response::HTTP_SEE_OTHER);
        $redirect = $this->redirectAt('/tag/sueno.html');
        self::assertEquals(Target::leadingTo(Resource::page(RedirectMother::PAGE)), $redirect?->target());
        self::assertEquals(Resource::term($id), $redirect?->origin()->resource());
    }

    /**
     * A section taken off the site goes back to its editor with what can't
     * be done told there.
     */
    public function testItTellsOnTheEditorThatATermOffTheSiteCannotBeTakenOff(): void
    {
        $client = self::createAuthenticatedClient();
        $id = TermIdMother::random()->value();
        $this->saveTerm(TermMother::unpublished(id: $id));

        $client->request('GET', self::HOST.'/sections/'.$id.'/unpublish');

        self::assertResponseRedirects(self::HOST.'/sections/'.$id, Response::HTTP_SEE_OTHER);
        self::assertSame([['key' => 'errors.term.publication.not_published', 'parameters' => []]], self::errorNotices($client));
    }

    /**
     * Published at the address a term of its taxonomy off the site last had,
     * the term takes it: the other has no last address any longer.
     */
    public function testItTakesTheLastAddressOfTheTermOffTheSiteThatHadIt(): void
    {
        $client = self::createAuthenticatedClient();
        $former = TermMother::unpublished(id: TermIdMother::random()->value());
        $former->edit(TermMother::NAME, 'antigua', TermMother::META_DESCRIPTION, 'index,follow');
        $this->saveTerm($former);
        $id = TermIdMother::random()->value();
        $this->saveTerm(TermMother::create(id: $id, metaDescription: TermMother::META_DESCRIPTION));

        $this->post($client, 'cms_terms_publish', self::HOST.'/sections/'.$id.'/publish');

        self::assertResponseRedirects(self::HOST.'/sections/'.$id, Response::HTTP_SEE_OTHER);
        self::assertNull($this->storedTerm($former)?->lastSiteSlug());
    }

    private function outlook(Resource $resource): string
    {
        return new PublicationOutlookFinder($resource)(self::getContainer()->get(RedirectRepository::class), self::getContainer()->get(SiteResources::class))->fingerprint;
    }

    private function redirectAt(string $path): ?Redirect
    {
        return self::getContainer()->get(RedirectRepository::class)->findBySource(Source::fromPath($path));
    }
}
