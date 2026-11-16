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
use Web\External\Domain\ExternalResource;
use Web\External\Domain\ExternalResourceRepository;
use Web\External\Domain\ImageCopies;
use Web\Shared\Domain\Robots;
use Web\Tests\External\Domain\CmsExternalResourcePublishedMother;
use Web\Tests\External\Domain\ExternalResourceIdMother;

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

    /**
     * A video in the bio plays behind its facade, as in a page or a post:
     * the page lets its provider frame it.
     */
    public function testItLetsTheProvidersOfTheResourcesTheBioEmbedsFrameThem(): void
    {
        $client = self::createClient();
        ExternalResource::publish(CmsExternalResourcePublishedMother::create(image: null, imageShape: null))
            ->save(self::getContainer()->get(ExternalResourceRepository::class), self::createStub(ImageCopies::class));
        $this->publish('isaac', ['type' => 'widget', 'kind' => 'related_page', 'external_id' => ExternalResourceIdMother::DEFAULT_VALUE]);

        $client->request('GET', self::HOST.'/autor/isaac.html');

        self::assertResponseIsSuccessful();
        self::assertStringContainsString('frame-src https://www.youtube-nocookie.com', (string) $client->getResponse()->headers->get('Content-Security-Policy'));
    }

    /**
     * @param array<string, mixed> $block the block of its bio
     */
    private function publish(string $slug, array $block = ['type' => 'paragraph', 'text' => [['text' => 'Escribo sobre autismo.']]]): void
    {
        // Saved as the projection keeps it, without making the copies of the avatar.
        self::getContainer()->get(AuthorRepository::class)->save(Author::publish(new CmsAuthorPublished(
            AuthorId::random()->value(),
            'Isaac Abreu',
            $slug,
            '01a1da50-9e10-7f03-8c5a-1b2c3d4e5f03.webp',
            RichText::fromArray(['version' => 1, 'blocks' => [$block]]),
            'Quién escribe este blog.',
            Robots::IndexFollow,
            ClockMother::now(),
            ClockMother::now(),
            1,
        )));
    }
}
