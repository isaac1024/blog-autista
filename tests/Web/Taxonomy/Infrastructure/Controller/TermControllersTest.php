<?php

declare(strict_types=1);

namespace Web\Tests\Taxonomy\Infrastructure\Controller;

use PHPUnit\Framework\Attributes\DataProvider;
use Shared\Tests\Domain\ClockMother;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;
use Web\Post\Domain\CmsPostPublished;
use Web\Post\Domain\Post;
use Web\Post\Domain\PostId;
use Web\Post\Domain\PostRepository;
use Web\Taxonomy\Domain\CmsTermPublished;
use Web\Taxonomy\Domain\Term;
use Web\Taxonomy\Domain\TermId;
use Web\Taxonomy\Domain\TermRepository;
use Web\Tests\Post\Domain\CmsPostPublishedMother;

/**
 * Sections are served at the first level of the site, /{slug}/, and tags
 * under /tag/, each only while it is published.
 */
final class TermControllersTest extends WebTestCase
{
    private const string HOST = 'http://www.blogautista.localhost';

    #[DataProvider('publishedTerms')]
    public function testItServesThePublishedTermAtItsAddress(string $taxonomy, string $path, string $route): void
    {
        $client = self::createClient();
        $this->publish($taxonomy, 'vida-diaria');

        $client->request('GET', self::HOST.$path);

        self::assertResponseIsSuccessful();
        self::assertRouteSame($route, ['slug' => 'vida-diaria']);
    }

    /**
     * @return iterable<string, array{string, string, string}>
     */
    public static function publishedTerms(): iterable
    {
        yield 'a section' => ['section', '/vida-diaria/', 'web_section'];
        yield 'a tag' => ['tag', '/tag/vida-diaria.html', 'web_tag'];
    }

    #[DataProvider('addressesOfNoTerm')]
    public function testItRespondsNotFoundWhereNoTermIsPublished(string $path): void
    {
        $client = self::createClient();
        $this->publish('section', 'vida-diaria');
        $this->publish('tag', 'sueno');

        $client->request('GET', self::HOST.$path);

        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function addressesOfNoTerm(): iterable
    {
        yield 'a section never published' => ['/trabajo/'];
        yield 'a section at the address of a tag' => ['/sueno/'];
        yield 'a tag at the address of a section' => ['/tag/vida-diaria.html'];
        yield 'a section slug with capitals' => ['/Vida-Diaria/'];
    }

    /**
     * An address of a single segment is taken as that of a section once it
     * ends in a slash, so it goes there, as long as it is no shorter than a
     * slug: shorter paths of the site answer only themselves.
     */
    public function testItLeadsAnAddressWithoutTheTrailingSlashToTheSection(): void
    {
        $client = self::createClient();
        $this->publish('section', 'vida-diaria');

        $client->request('GET', self::HOST.'/vida-diaria');

        self::assertResponseRedirects(self::HOST.'/vida-diaria/', Response::HTTP_MOVED_PERMANENTLY);
    }

    public function testItLeavesPathsShorterThanASlugToThemselves(): void
    {
        $client = self::createClient();

        $client->request('GET', self::HOST.'/ab/');

        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
        self::assertNull($client->getRequest()->attributes->get('_route'));
    }

    public function testItOnlyServesTheSiteOnItsHost(): void
    {
        $client = self::createClient();
        $this->publish('section', 'vida-diaria');

        $client->request('GET', 'http://cms.blogautista.localhost/vida-diaria/');

        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    /**
     * A section or a tag lists its posts twenty at a time, the first page at
     * its own address and the next ones numbered; a page that can't be, or
     * past the last, isn't there. The page cache keeps each a few minutes.
     */
    #[DataProvider('publishedTerms')]
    public function testItListsThePostsOfTheTermAPageAtATime(string $taxonomy, string $path, string $route): void
    {
        $client = self::createClient();
        $term = $this->publish($taxonomy, 'vida-diaria');

        for ($post = 1; $post <= 21; ++$post) {
            self::getContainer()->get(PostRepository::class)->save(Post::publish(CmsPostPublished::fromPrimitives(PostId::random()->value(), [
                'slug' => 'entrada-'.$post,
                'section_id' => 'section' === $taxonomy ? $term : CmsPostPublishedMother::SECTION,
                'tag_ids' => 'tag' === $taxonomy ? [$term] : [],
                'revision' => 1,
            ] + CmsPostPublishedMother::body())));
        }

        foreach (['', '?pagina=1', '?pagina=2'] as $page) {
            $client->request('GET', self::HOST.$path.$page);

            self::assertResponseIsSuccessful();
            self::assertRouteSame($route);
            self::assertSame('max-age=0, public, s-maxage=300', $client->getResponse()->headers->get('Cache-Control'));
        }

        foreach (['?pagina=3', '?pagina=0', '?pagina=-1', '?pagina=dos'] as $page) {
            $client->request('GET', self::HOST.$path.$page);

            self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
        }
    }

    /**
     * A term with no post yet still has its first page, and only that one.
     */
    public function testItServesTheFirstPageOfATermWithoutPosts(): void
    {
        $client = self::createClient();
        $this->publish('tag', 'sueno');

        $client->request('GET', self::HOST.'/tag/sueno.html');
        self::assertResponseIsSuccessful();

        $client->request('GET', self::HOST.'/tag/sueno.html?pagina=2');
        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    private function publish(string $taxonomy, string $slug): string
    {
        $id = TermId::random()->value();
        self::getContainer()->get(TermRepository::class)->save(Term::publish(CmsTermPublished::fromPrimitives(
            $id,
            [
                'taxonomy' => $taxonomy,
                'name' => 'Vida diaria',
                'slug' => $slug,
                'meta_description' => 'Artículos sobre la vida diaria.',
                'robots' => 'index,follow',
                'first_published_at' => ClockMother::now()->format(\DATE_ATOM),
                'last_published_at' => ClockMother::now()->format(\DATE_ATOM),
                'revision' => 1,
            ],
        )));

        return $id;
    }
}
