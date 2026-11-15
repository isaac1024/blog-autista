<?php

declare(strict_types=1);

namespace Web\Tests\Author\Infrastructure\Controller;

use Shared\Domain\RichText\RichText;
use Shared\Tests\Domain\ClockMother;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;
use Web\Author\Domain\Author;
use Web\Author\Domain\AuthorId;
use Web\Author\Domain\AuthorRepository;
use Web\Author\Domain\CmsAuthorPublished;
use Web\Shared\Domain\Robots;

/**
 * Authors are served under /autor/, each only while it is published.
 */
final class AuthorControllerTest extends WebTestCase
{
    private const string HOST = 'http://www.blogautista.localhost';

    public function testItServesThePublishedAuthorAtItsAddress(): void
    {
        $client = self::createClient();
        $this->publish('isaac');

        $client->request('GET', self::HOST.'/autor/isaac.html');

        self::assertResponseIsSuccessful();
        self::assertRouteSame('web_author', ['slug' => 'isaac']);
    }

    public function testItRespondsNotFoundWhereNoAuthorIsPublished(): void
    {
        $client = self::createClient();
        $this->publish('isaac');

        $client->request('GET', self::HOST.'/autor/ana.html');

        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    public function testItOnlyServesTheSiteOnItsHost(): void
    {
        $client = self::createClient();
        $this->publish('isaac');

        $client->request('GET', 'http://cms.blogautista.localhost/autor/isaac.html');

        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    private function publish(string $slug): void
    {
        // Saved as the projection keeps it, without making the copies of the avatar.
        self::getContainer()->get(AuthorRepository::class)->save(Author::publish(new CmsAuthorPublished(
            AuthorId::random()->value(),
            'Isaac Abreu',
            $slug,
            '01a1da50-9e10-7f03-8c5a-1b2c3d4e5f03.webp',
            RichText::fromArray(['version' => 1, 'blocks' => [['type' => 'paragraph', 'text' => [['text' => 'Escribo sobre autismo.']]]]]),
            'Quién escribe este blog.',
            Robots::IndexFollow,
            ClockMother::now(),
            ClockMother::now(),
            1,
        )));
    }
}
