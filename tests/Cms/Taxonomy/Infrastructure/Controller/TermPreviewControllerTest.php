<?php

declare(strict_types=1);

namespace Cms\Tests\Taxonomy\Infrastructure\Controller;

use Cms\Taxonomy\Domain\Taxonomy;
use Cms\Taxonomy\Domain\Term;
use Cms\Tests\Taxonomy\Domain\TermIdMother;
use Cms\Tests\Taxonomy\Domain\TermMother;
use PHPUnit\Framework\Attributes\DataProvider;
use Shared\Infrastructure\Http\SignedUrls;
use Symfony\Component\HttpFoundation\Response;

final class TermPreviewControllerTest extends TermControllerTestCase
{
    #[DataProvider('terms')]
    public function testItShowsTheTermToWhoeverHoldsAValidLinkWithoutSigningIn(string $segment, Term $term): void
    {
        $client = self::createClient();
        $this->saveTerm($term);

        $client->request('GET', $this->signed($segment, $term->id()->value(), '+1 hour'));

        self::assertResponseIsSuccessful();
        self::assertRouteSame('cms_terms_preview', ['taxonomy' => $segment, 'id' => $term->id()->value()]);
        self::assertResponseHeaderSame('X-Robots-Tag', 'noindex,nofollow');
        self::assertTrue($client->getResponse()->headers->hasCacheControlDirective('no-store'));
        self::assertTrue($client->getResponse()->headers->hasCacheControlDirective('private'));
    }

    /**
     * @return iterable<string, array{string, Term}>
     */
    public static function terms(): iterable
    {
        yield 'a section' => ['sections', TermMother::create(id: TermIdMother::random()->value(), metaDescription: TermMother::META_DESCRIPTION)];
        yield 'a tag without a slug nor a meta description' => ['tags', TermMother::create(id: TermIdMother::random()->value(), taxonomy: Taxonomy::Tag, slug: '')];
    }

    public function testItRefusesAnExpiredLink(): void
    {
        $client = self::createClient();
        $term = $this->saveTerm(TermMother::create(id: TermIdMother::random()->value()));

        $client->request('GET', $this->signed('sections', $term->id()->value(), '-1 minute'));

        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    public function testItRefusesAnUnsignedLink(): void
    {
        $client = self::createClient();
        $term = $this->saveTerm(TermMother::create(id: TermIdMother::random()->value()));

        $client->request('GET', self::HOST.'/preview/sections/'.$term->id()->value());

        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    public function testItRespondsNotFoundForAValidLinkToATermOfAnotherTaxonomy(): void
    {
        $client = self::createClient();
        $term = $this->saveTerm(TermMother::create(id: TermIdMother::random()->value()));

        $client->request('GET', $this->signed('tags', $term->id()->value(), '+1 hour'));

        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    private function signed(string $segment, string $id, string $expiresIn): string
    {
        return self::getContainer()->get(SignedUrls::class)->sign(\sprintf('%s/preview/%s/%s', self::HOST, $segment, $id), new \DateTimeImmutable($expiresIn));
    }
}
