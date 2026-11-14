<?php

declare(strict_types=1);

namespace Cms\Tests\Author\Infrastructure\Controller;

use Cms\Author\Domain\Author;
use Cms\Tests\Author\Domain\AuthorIdMother;
use Cms\Tests\Author\Domain\AuthorMother;
use Cms\Tests\Author\Domain\AvatarFileMother;
use PHPUnit\Framework\Attributes\DataProvider;
use Shared\Infrastructure\Http\SignedUrls;
use Symfony\Component\HttpFoundation\Response;

/**
 * The preview of an author and the files of its avatars, both shown only to
 * whoever holds a signed link, without signing in.
 */
final class AuthorPreviewAndAvatarControllersTest extends AuthorControllerTestCase
{
    #[DataProvider('authors')]
    public function testItShowsThePreviewToWhoeverHoldsAValidLink(Author $author): void
    {
        $client = self::createClient();
        $this->saveAuthor($author);

        $client->request('GET', $this->signed('/preview/authors/'.$author->id()->value(), '+1 hour'));

        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('X-Robots-Tag', 'noindex,nofollow');
        self::assertTrue($client->getResponse()->headers->hasCacheControlDirective('no-store'));
    }

    /**
     * @return iterable<string, array{Author}>
     */
    public static function authors(): iterable
    {
        yield 'a draft without an avatar, a slug nor a meta description' => [AuthorMother::create(id: AuthorIdMother::random()->value(), slug: '')];
        yield 'an author with everything' => [AuthorMother::publishable(id: AuthorIdMother::random()->value())];
    }

    #[DataProvider('invalidLinks')]
    public function testItRefusesAPreviewWithoutAValidLink(string $expiresIn, string $tail): void
    {
        $client = self::createClient();
        $author = $this->saveAuthor(AuthorMother::create(id: AuthorIdMother::random()->value()));

        $client->request('GET', $this->signed('/preview/authors/'.$author->id()->value(), $expiresIn).$tail);

        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function invalidLinks(): iterable
    {
        yield 'expired' => ['-1 minute', ''];
        yield 'with its signature changed' => ['+1 hour', 'x'];
    }

    /**
     * Either version's avatar is served, and browsers keep it for good, as
     * its file never changes.
     */
    #[DataProvider('avatars')]
    public function testItServesAnAvatarOfTheAuthor(string $avatar): void
    {
        $client = self::createClient();
        $author = $this->saveAuthor(AuthorMother::published(id: AuthorIdMother::random()->value(), avatar: AuthorMother::OTHER_AVATAR, publishedAvatar: AuthorMother::AVATAR));
        self::keepAvatar($avatar);

        $client->request('GET', $this->signed(\sprintf('/preview/authors/%s/avatar/%s', $author->id()->value(), $avatar), '+1 hour'));

        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('Content-Type', 'image/webp');
        self::assertResponseHeaderSame('X-Content-Type-Options', 'nosniff');
        self::assertTrue($client->getResponse()->headers->hasCacheControlDirective('immutable'));
        self::assertTrue($client->getResponse()->headers->hasCacheControlDirective('private'));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function avatars(): iterable
    {
        yield 'the one being edited' => [AuthorMother::OTHER_AVATAR];
        yield 'the one on the site' => [AuthorMother::AVATAR];
    }

    public function testItRefusesAnAvatarWithoutAValidLink(): void
    {
        $client = self::createClient();
        $author = $this->saveAuthor(AuthorMother::publishable(id: AuthorIdMother::random()->value()));
        self::keepAvatar(AuthorMother::AVATAR);

        $client->request('GET', \sprintf('%s/preview/authors/%s/avatar/%s', self::HOST, $author->id()->value(), AuthorMother::AVATAR));

        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    /**
     * A signed link only opens the files of the author it names: the file of
     * another author, or one missing, is not found.
     */
    #[DataProvider('filesOfNoAvatar')]
    public function testItRespondsNotFoundForAFileThatIsNoAvatarOfTheAuthor(string $avatar, bool $kept): void
    {
        $client = self::createClient();
        $author = $this->saveAuthor(AuthorMother::publishable(id: AuthorIdMother::random()->value()));

        if ($kept) {
            self::keepAvatar($avatar, AvatarFileMother::OTHER);
        }

        $client->request('GET', $this->signed(\sprintf('/preview/authors/%s/avatar/%s', $author->id()->value(), $avatar), '+1 hour'));

        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    /**
     * @return iterable<string, array{string, bool}>
     */
    public static function filesOfNoAvatar(): iterable
    {
        yield 'the avatar of another author' => [AuthorMother::OTHER_AVATAR, true];
        yield 'its avatar, whose file is gone' => [AuthorMother::AVATAR, false];
    }

    private function signed(string $path, string $expiresIn): string
    {
        return self::getContainer()->get(SignedUrls::class)->sign(self::HOST.$path, new \DateTimeImmutable($expiresIn));
    }
}
