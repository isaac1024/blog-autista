<?php

declare(strict_types=1);

namespace Cms\Tests\Page\Infrastructure\Controller;

use Cms\Page\Domain\PageRepository;
use Cms\Tests\Page\Domain\PageIdMother;
use Cms\Tests\Page\Domain\PageMother;
use Cms\Tests\Shared\Infrastructure\CmsWebTestCase;
use Shared\Infrastructure\Http\SignedUrls;
use Symfony\Component\HttpFoundation\Response;

/**
 * The preview is reached through a signed link, by anyone who has it and
 * without signing in, until it expires.
 */
final class PagePreviewControllerTest extends CmsWebTestCase
{
    private const string URL = 'http://cms.blogautista.localhost/preview/%s';

    public function testItShowsThePageToWhoeverHoldsAValidLinkWithoutSigningIn(): void
    {
        $client = self::createClient();
        $id = $this->savePage();

        $client->request('GET', $this->signed($id, '+1 hour'));

        self::assertResponseIsSuccessful();
        self::assertRouteSame('cms_pages_preview', ['id' => $id]);
    }

    /**
     * Neither search engines nor caches along the way keep a preview.
     */
    public function testItKeepsSearchEnginesAndCachesAway(): void
    {
        $client = self::createClient();
        $id = $this->savePage();

        $client->request('GET', $this->signed($id, '+1 hour'));

        self::assertResponseHeaderSame('X-Robots-Tag', 'noindex,nofollow');
        self::assertTrue($client->getResponse()->headers->hasCacheControlDirective('no-store'));
        self::assertTrue($client->getResponse()->headers->hasCacheControlDirective('private'));
    }

    public function testItRefusesAnExpiredLink(): void
    {
        $client = self::createClient();
        $id = $this->savePage();

        $client->request('GET', $this->signed($id, '-1 minute'));

        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    public function testItRefusesALinkWhoseSignatureWasChanged(): void
    {
        $client = self::createClient();
        $id = $this->savePage();

        $client->request('GET', $this->signed($id, '+1 hour').'x');

        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    /**
     * A link signed for a page doesn't open another.
     */
    public function testItRefusesALinkSignedForAnotherPage(): void
    {
        $client = self::createClient();
        $id = $this->savePage();
        $signed = $this->signed(PageIdMother::random()->value(), '+1 hour');

        $client->request('GET', \sprintf(self::URL, $id).substr($signed, (int) strpos($signed, '?')));

        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    public function testItRefusesAnUnsignedLink(): void
    {
        $client = self::createClient();
        $id = $this->savePage();

        $client->request('GET', \sprintf(self::URL, $id));

        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    public function testItRespondsNotFoundForAValidLinkToAPageThatDoesNotExist(): void
    {
        $client = self::createClient();

        $client->request('GET', $this->signed(PageIdMother::random()->value(), '+1 hour'));

        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    private function savePage(): string
    {
        $id = PageIdMother::random()->value();
        self::getContainer()->get(PageRepository::class)->save(PageMother::create(id: $id, title: 'Sobre mí', slug: 'sobre-mi'));

        return $id;
    }

    private function signed(string $id, string $expiresIn): string
    {
        return self::getContainer()->get(SignedUrls::class)->sign(\sprintf(self::URL, $id), new \DateTimeImmutable($expiresIn));
    }
}
