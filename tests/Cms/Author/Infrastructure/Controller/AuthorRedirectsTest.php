<?php

declare(strict_types=1);

namespace Cms\Tests\Author\Infrastructure\Controller;

use Cms\Page\Domain\PageRepository;
use Cms\Redirect\Application\PublicationOutlookFinder;
use Cms\Redirect\Domain\Redirect;
use Cms\Redirect\Domain\RedirectRepository;
use Cms\Redirect\Domain\Resource;
use Cms\Redirect\Domain\SiteResources;
use Cms\Redirect\Domain\Source;
use Cms\Redirect\Domain\Target;
use Cms\Tests\Author\Domain\AuthorIdMother;
use Cms\Tests\Author\Domain\AuthorMother;
use Cms\Tests\Page\Domain\PageMother;
use Cms\Tests\Redirect\Domain\RedirectMother;
use Shared\Tests\Domain\ClockMother;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;
use Symfony\Component\HttpFoundation\Response;

/**
 * What publishing an author and taking it off the site do to the redirects,
 * as an editor does it: the address it leaves is asked about first, and its
 * own once off the site leads where it is told.
 */
final class AuthorRedirectsTest extends AuthorControllerTestCase
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
        $id = $this->saveMovedAuthor();

        $this->post($client, 'cms_authors_publish', self::HOST.'/authors/'.$id.'/publish');

        self::assertResponseRedirects(self::HOST.'/authors/'.$id.'/publish', Response::HTTP_SEE_OTHER);
        $client->request('GET', self::HOST.'/authors/'.$id.'/publish');
        self::assertResponseIsSuccessful();
    }

    public function testItLeadsTheAddressItLeavesToTheAuthorOnceConfirmed(): void
    {
        $client = self::createAuthenticatedClient();
        $id = $this->saveMovedAuthor();

        $this->post($client, 'cms_authors_publish', self::HOST.'/authors/'.$id.'/publish', [
            'outlook' => $this->outlook(Resource::author($id)),
            'previous' => ['action' => 'itself'],
        ]);

        self::assertResponseRedirects(self::HOST.'/authors/'.$id, Response::HTTP_SEE_OTHER);
        self::assertSame('isaac-abreu', $this->storedAuthor(AuthorMother::create(id: $id))?->publishedSlug()?->value());
        self::assertEquals(Target::leadingTo(Resource::author($id)), $this->redirectAt('/autor/isaac.html')?->target());
    }

    public function testItGoesBackToTheEditorFromAConfirmationWithNothingToAsk(): void
    {
        $client = self::createAuthenticatedClient();
        $id = AuthorIdMother::random()->value();
        $this->saveAuthor(AuthorMother::published(id: $id, publishedName: 'Antes'));

        $client->request('GET', self::HOST.'/authors/'.$id.'/publish');

        self::assertResponseRedirects(self::HOST.'/authors/'.$id, Response::HTTP_SEE_OTHER);
    }

    public function testItLeadsItsAddressWhereChosenAsItGoesOffTheSite(): void
    {
        $client = self::createAuthenticatedClient();
        $id = AuthorIdMother::random()->value();
        $this->saveAuthor(AuthorMother::published(id: $id));
        self::getContainer()->get(PageRepository::class)->save(PageMother::published(id: RedirectMother::PAGE, title: 'Otra', slug: 'otra'));

        $client->request('GET', self::HOST.'/authors/'.$id.'/unpublish');
        self::assertResponseIsSuccessful();
        $this->post($client, 'cms_authors_unpublish', self::HOST.'/authors/'.$id.'/unpublish', ['address' => ['action' => 'gone']]);

        self::assertResponseRedirects(self::HOST.'/authors/'.$id, Response::HTTP_SEE_OTHER);
        $redirect = $this->redirectAt('/autor/isaac.html');
        self::assertEquals(Target::gone(), $redirect?->target());
        self::assertEquals(Resource::author($id), $redirect?->origin()->resource());
    }

    public function testItTellsOnTheEditorThatAnAuthorOffTheSiteCannotBeTakenOff(): void
    {
        $client = self::createAuthenticatedClient();
        $id = AuthorIdMother::random()->value();
        $this->saveAuthor(AuthorMother::unpublished(id: $id));

        $client->request('GET', self::HOST.'/authors/'.$id.'/unpublish');

        self::assertResponseRedirects(self::HOST.'/authors/'.$id, Response::HTTP_SEE_OTHER);
        self::assertSame([['key' => 'errors.author.publication.not_published', 'parameters' => []]], self::errorNotices($client));
    }

    /**
     * Published at the address an author off the site last had, the author
     * takes it: the other has no last address any longer.
     */
    public function testItTakesTheLastAddressOfTheAuthorOffTheSiteThatHadIt(): void
    {
        $client = self::createAuthenticatedClient();
        $former = AuthorMother::unpublished(id: AuthorIdMother::random()->value());
        $former->edit(AuthorMother::NAME, 'antiguo', AuthorMother::BIO, AuthorMother::META_DESCRIPTION, 'index,follow');
        $this->saveAuthor($former);
        $id = AuthorIdMother::random()->value();
        $this->saveAuthor(AuthorMother::publishable(id: $id));

        $this->post($client, 'cms_authors_publish', self::HOST.'/authors/'.$id.'/publish');

        self::assertResponseRedirects(self::HOST.'/authors/'.$id, Response::HTTP_SEE_OTHER);
        self::assertNull($this->storedAuthor($former)?->lastSiteSlug());
    }

    /**
     * An author on the site saved with another slug, so publishing it moves
     * it to /autor/isaac-abreu.html.
     */
    private function saveMovedAuthor(): string
    {
        $id = AuthorIdMother::random()->value();
        $author = AuthorMother::published(id: $id);
        $author->edit(AuthorMother::NAME, 'isaac-abreu', AuthorMother::BIO, AuthorMother::META_DESCRIPTION, 'index,follow');
        $this->saveAuthor($author);

        return $id;
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
