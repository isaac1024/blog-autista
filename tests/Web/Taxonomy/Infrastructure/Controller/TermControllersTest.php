<?php

declare(strict_types=1);

namespace Web\Tests\Taxonomy\Infrastructure\Controller;

use PHPUnit\Framework\Attributes\DataProvider;
use Shared\Tests\Domain\ClockMother;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;
use Web\Taxonomy\Domain\CmsTermPublished;
use Web\Taxonomy\Domain\Term;
use Web\Taxonomy\Domain\TermId;
use Web\Taxonomy\Domain\TermRepository;

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

    private function publish(string $taxonomy, string $slug): void
    {
        self::getContainer()->get(TermRepository::class)->save(Term::publish(CmsTermPublished::fromPrimitives(
            TermId::random()->value(),
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
    }
}
