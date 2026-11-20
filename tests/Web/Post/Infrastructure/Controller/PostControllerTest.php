<?php

declare(strict_types=1);

namespace Web\Tests\Post\Infrastructure\Controller;

use PHPUnit\Framework\Attributes\DataProvider;
use Shared\Tests\Domain\ClockMother;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;
use Web\Post\Domain\CmsPostPublished;
use Web\Post\Domain\Post;
use Web\Post\Domain\PostRepository;
use Web\Taxonomy\Domain\CmsTermPublished;
use Web\Taxonomy\Domain\Term;
use Web\Taxonomy\Domain\TermRepository;
use Web\Tests\Post\Domain\CmsPostPublishedMother;
use Web\Tests\Post\Domain\PostIdMother;

final class PostControllerTest extends WebTestCase
{
    private const string HOST = 'http://www.blogautista.localhost';

    private const string ADDRESS = '/vida-diaria/2026/11/07/mi-primer-dia.html';

    #[DataProvider('classified')]
    public function testItServesThePostAtItsAddressWhetherItsSectionAndTagsAreOnTheSiteOrNot(bool $termsOnSite): void
    {
        $client = self::createClient();
        $this->publish();

        if ($termsOnSite) {
            $this->publishTerm('section', '01a1da50-2b7c-7d01-8a3f-5c2e9b1d0a01', 'vida-diaria');
            $this->publishTerm('tag', '01a1da50-2b7c-7d01-8a3f-5c2e9b1d0a02', 'sensorialidad');
        }

        $client->request('GET', self::HOST.self::ADDRESS);

        self::assertResponseIsSuccessful();
        self::assertRouteSame('web_post', ['section' => 'vida-diaria', 'year' => '2026', 'month' => '11', 'day' => '07', 'slug' => 'mi-primer-dia']);
    }

    /**
     * @return iterable<string, array{bool}>
     */
    public static function classified(): iterable
    {
        yield 'with its section and tag on the site' => [true];
        yield 'with its section and tag off it' => [false];
    }

    public function testItServesAPostRepublishedOnALaterDayAtTheAddressOfItsFirstDay(): void
    {
        $client = self::createClient();
        $this->publish(['last_published_at' => ClockMother::now('2026-11-20 10:00:00')->format(\DATE_ATOM)]);

        $client->request('GET', self::HOST.self::ADDRESS);

        self::assertResponseIsSuccessful();
    }

    #[DataProvider('otherAddresses')]
    public function testItFindsNoPostAtAnyOtherAddress(string $path): void
    {
        $client = self::createClient();
        $this->publish();

        $client->request('GET', self::HOST.$path);

        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function otherAddresses(): iterable
    {
        yield 'on another day' => ['/vida-diaria/2026/11/08/mi-primer-dia.html'];
        yield 'with another slug' => ['/vida-diaria/2026/11/07/otro.html'];
        yield 'on a day there is not' => ['/vida-diaria/2026/02/30/mi-primer-dia.html'];
        yield 'with a month of one digit' => ['/vida-diaria/2026/1/07/mi-primer-dia.html'];
    }

    /**
     * The section in front of the day and the slug only tells where a post
     * is filed: any other, such as the slug its section had before, leads to
     * its canonical address, keeping the query, for as long as the other
     * redirects of the site.
     */
    #[DataProvider('otherSections')]
    public function testItLeadsFromAnyOtherSectionToItsCanonicalAddress(bool $sectionOnSite, string $path, string $canonical): void
    {
        $client = self::createClient();
        $this->publish();

        if ($sectionOnSite) {
            $this->publishTerm('section', '01a1da50-2b7c-7d01-8a3f-5c2e9b1d0a01', 'dia-a-dia');
        }

        $client->request('GET', self::HOST.$path.'?utm_source=feed');

        self::assertResponseRedirects($canonical.'?utm_source=feed', Response::HTTP_MOVED_PERMANENTLY);
        self::assertResponseHeaderSame('Cache-Control', 'max-age=3600, public');
    }

    /**
     * @return iterable<string, array{bool, string, string}>
     */
    public static function otherSections(): iterable
    {
        yield 'the slug the section had, now renamed on the site' => [true, self::ADDRESS, '/dia-a-dia/2026/11/07/mi-primer-dia.html'];
        yield 'another section, while its own is on the site' => [true, '/salud/2026/11/07/mi-primer-dia.html', '/dia-a-dia/2026/11/07/mi-primer-dia.html'];
        yield 'another section, while its own is off it' => [false, '/salud/2026/11/07/mi-primer-dia.html', self::ADDRESS];
    }

    public function testItFindsNoPostTheCmsTookOffTheSite(): void
    {
        $client = self::createClient();

        $client->request('GET', self::HOST.self::ADDRESS);

        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    /**
     * @param array<string, mixed> $changes
     */
    private function publish(array $changes = []): void
    {
        self::getContainer()->get(PostRepository::class)->save(Post::publish(CmsPostPublished::fromPrimitives(
            PostIdMother::DEFAULT_VALUE,
            $changes + CmsPostPublishedMother::body() + ['revision' => 1],
        )));
    }

    private function publishTerm(string $taxonomy, string $id, string $slug): void
    {
        self::getContainer()->get(TermRepository::class)->save(Term::publish(CmsTermPublished::fromPrimitives($id, [
            'taxonomy' => $taxonomy,
            'name' => ucfirst(str_replace('-', ' ', $slug)),
            'slug' => $slug,
            'meta_description' => 'Artículos sobre esto.',
            'robots' => 'index,follow',
            'first_published_at' => ClockMother::now()->format(\DATE_ATOM),
            'last_published_at' => ClockMother::now()->format(\DATE_ATOM),
            'revision' => 1,
        ])));
    }
}
