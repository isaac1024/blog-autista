<?php

declare(strict_types=1);

namespace Cms\Tests\Post\Infrastructure\Controller;

use Cms\Page\Domain\PageRepository;
use Cms\Post\Domain\Classifiers;
use Cms\Redirect\Application\PublicationOutlookFinder;
use Cms\Redirect\Domain\Redirect;
use Cms\Redirect\Domain\RedirectRepository;
use Cms\Redirect\Domain\Resource;
use Cms\Redirect\Domain\SiteResources;
use Cms\Redirect\Domain\Source;
use Cms\Redirect\Domain\Target;
use Cms\Tests\Page\Domain\PageMother;
use Cms\Tests\Post\Domain\PostIdMother;
use Cms\Tests\Post\Domain\PostMother;
use Cms\Tests\Redirect\Domain\RedirectMother;
use Shared\Tests\Domain\ClockMother;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;
use Symfony\Component\HttpFoundation\Response;

/**
 * What publishing a post and taking it off the site do to the redirects, as
 * an editor does it: the address it leaves is asked about first, and its
 * own once off the site leads where it is told.
 */
final class PostRedirectsTest extends PostControllerTestCase
{
    use ClockSensitiveTrait;

    /**
     * Where PostMother::published() is on the site.
     */
    private const string PATH = '/vida-diaria/2026/11/07/'.PostMother::SLUG.'.html';

    private const string MOVED_PATH = '/vida-diaria/2026/11/07/otro-dia.html';

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();
        self::mockTime(ClockMother::NOW);
    }

    public function testItAsksFirstWhenPublishingLeavesAnAddress(): void
    {
        $client = self::createAuthenticatedClient();
        $this->saveClassifiers();
        $id = $this->saveMovedPost();

        $this->post($client, 'cms_posts_publish', self::HOST.'/posts/'.$id.'/publish');

        self::assertResponseRedirects(self::HOST.'/posts/'.$id.'/publish', Response::HTTP_SEE_OTHER);
        $client->request('GET', self::HOST.'/posts/'.$id.'/publish');
        self::assertResponseIsSuccessful();
        self::assertSame(self::PATH, $this->publishedPath($id));
    }

    public function testItLeadsTheAddressItLeavesToThePostOnceConfirmed(): void
    {
        $client = self::createAuthenticatedClient();
        $this->saveClassifiers();
        $id = $this->saveMovedPost();

        $this->post($client, 'cms_posts_publish', self::HOST.'/posts/'.$id.'/publish', [
            'outlook' => $this->outlook(Resource::post($id)),
            'previous' => ['action' => 'itself'],
        ]);

        self::assertResponseRedirects(self::HOST.'/posts/'.$id, Response::HTTP_SEE_OTHER);
        self::assertSame(self::MOVED_PATH, $this->publishedPath($id));
        self::assertEquals(Target::leadingTo(Resource::post($id)), $this->redirectAt(self::PATH)?->target());
    }

    /**
     * Straight from its editor, a post whose address doesn't move publishes
     * at once.
     */
    public function testItGoesBackToTheEditorFromAConfirmationWithNothingToAsk(): void
    {
        $client = self::createAuthenticatedClient();
        $this->saveClassifiers();
        $id = PostIdMother::random()->value();
        $this->savePost(PostMother::published(id: $id, publishedTitle: 'Antes'));

        $client->request('GET', self::HOST.'/posts/'.$id.'/publish');

        self::assertResponseRedirects(self::HOST.'/posts/'.$id, Response::HTTP_SEE_OTHER);
    }

    public function testItLeadsItsAddressWhereChosenAsItGoesOffTheSite(): void
    {
        $client = self::createAuthenticatedClient();
        $this->saveClassifiers();
        $id = PostIdMother::random()->value();
        $this->savePost(PostMother::published(id: $id));
        self::getContainer()->get(PageRepository::class)->save(PageMother::published(id: RedirectMother::PAGE, title: 'Otra', slug: 'otra'));

        $client->request('GET', self::HOST.'/posts/'.$id.'/unpublish');
        self::assertResponseIsSuccessful();
        $this->post($client, 'cms_posts_unpublish', self::HOST.'/posts/'.$id.'/unpublish', ['address' => ['action' => 'page', 'page_id' => RedirectMother::PAGE]]);

        self::assertResponseRedirects(self::HOST.'/posts/'.$id, Response::HTTP_SEE_OTHER);
        $redirect = $this->redirectAt(self::PATH);
        self::assertEquals(Target::leadingTo(Resource::page(RedirectMother::PAGE)), $redirect?->target());
        self::assertEquals(Resource::post($id), $redirect?->origin()->resource());
    }

    /**
     * A redirect that leads to the post is decided as it goes off the site,
     * since none may lead to a post off it.
     */
    public function testItLeadsTheRedirectsToItWhereItsAddressLeads(): void
    {
        $client = self::createAuthenticatedClient();
        $this->saveClassifiers();
        $id = PostIdMother::random()->value();
        $this->savePost(PostMother::published(id: $id));
        self::getContainer()->get(PageRepository::class)->save(PageMother::published(id: RedirectMother::PAGE, title: 'Otra', slug: 'otra'));
        self::getContainer()->get(RedirectRepository::class)->save(RedirectMother::create(target: Resource::post($id)));

        $this->post($client, 'cms_posts_unpublish', self::HOST.'/posts/'.$id.'/unpublish', [
            'address' => ['action' => 'page', 'page_id' => RedirectMother::PAGE],
            'incoming' => [RedirectMother::create()->id()->value() => ['action' => 'same']],
        ]);

        self::assertResponseRedirects(self::HOST.'/posts/'.$id, Response::HTTP_SEE_OTHER);
        self::assertEquals(Target::leadingTo(Resource::page(RedirectMother::PAGE)), $this->redirectAt(RedirectMother::SOURCE)?->target());
    }

    /**
     * Published at the address a post off the site last had, the post takes
     * it: the other has no last address any longer.
     */
    public function testItTakesTheLastAddressOfThePostOffTheSiteThatHadIt(): void
    {
        $client = self::createAuthenticatedClient();
        $this->saveClassifiers();
        $former = $this->savePost(PostMother::unpublished(id: PostIdMother::random()->value()));
        $id = PostIdMother::random()->value();
        $this->savePost(PostMother::publishable(id: $id));

        $this->post($client, 'cms_posts_publish', self::HOST.'/posts/'.$id.'/publish');

        self::assertResponseRedirects(self::HOST.'/posts/'.$id, Response::HTTP_SEE_OTHER);
        self::assertSame(self::PATH, $this->publishedPath($id));
        self::assertNull($this->storedPost($former)?->lastAddress());
    }

    /**
     * A post on the site saved with another slug, so publishing it moves it
     * to MOVED_PATH.
     */
    private function saveMovedPost(): string
    {
        $id = PostIdMother::random()->value();
        $post = PostMother::published(id: $id);
        $post->edit(PostMother::TITLE, 'otro-dia', PostMother::SECTION, PostMother::AUTHOR, [], PostMother::OPENING_IMAGE, PostMother::CONTENT, PostMother::META_DESCRIPTION, 'index,follow', self::getContainer()->get(Classifiers::class));
        $this->savePost($post);

        return $id;
    }

    private function outlook(Resource $resource): string
    {
        return new PublicationOutlookFinder($resource)(self::getContainer()->get(RedirectRepository::class), self::getContainer()->get(SiteResources::class))->fingerprint;
    }

    private function publishedPath(string $id): ?string
    {
        $post = $this->storedPost(PostMother::create(id: $id));
        $address = $post?->publishedAddress();

        return null === $address ? null : \sprintf('/%s/%s/%s.html', $address->section()->value(), str_replace('-', '/', $address->day()), $address->slug()->value());
    }

    private function redirectAt(string $path): ?Redirect
    {
        return self::getContainer()->get(RedirectRepository::class)->findBySource(Source::fromPath($path));
    }
}
