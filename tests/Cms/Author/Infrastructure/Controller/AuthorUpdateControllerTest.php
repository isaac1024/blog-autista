<?php

declare(strict_types=1);

namespace Cms\Tests\Author\Infrastructure\Controller;

use Cms\Relation\Domain\RelationRepository;
use Cms\Relation\Domain\ResourceType;
use Cms\Tests\Author\Domain\AuthorIdMother;
use Cms\Tests\Author\Domain\AuthorMother;
use Cms\Tests\Author\Domain\AvatarFileMother;
use Shared\Tests\Domain\ClockMother;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;
use Symfony\Component\HttpFoundation\Response;

final class AuthorUpdateControllerTest extends AuthorControllerTestCase
{
    use ClockSensitiveTrait;

    private const string BIO = '{"type":"doc","content":[{"type":"paragraph","content":[{"type":"text","text":"Escribo sobre autismo en primera persona."}]}]}';

    private const string PAGE = '01a1b801-ce58-723d-8234-b76d9105e001';

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();
        self::mockTime(ClockMother::NOW);
    }

    public function testItRedirectsAnonymousVisitorsToTheLoginForm(): void
    {
        $client = self::createClient();
        $author = $this->saveAuthor(AuthorMother::create(id: AuthorIdMother::random()->value()));

        $this->post($client, 'cms_authors_update', self::HOST.'/authors/'.$author->id()->value(), self::fields());

        self::assertResponseRedirects(self::HOST.'/login');
        self::assertEquals($author, $this->storedAuthor($author));
    }

    public function testItSavesTheAuthorWithItsNewAvatarAndStaysInTheEditor(): void
    {
        $client = self::createAuthenticatedClient();
        $id = AuthorIdMother::random()->value();
        $author = $this->saveAuthor(AuthorMother::create(id: $id));

        $this->post($client, 'cms_authors_update', self::HOST.'/authors/'.$id, self::fields(), ['avatar' => self::upload()]);

        self::assertResponseRedirects('/authors/'.$id, Response::HTTP_SEE_OTHER);
        self::assertSame([['key' => 'cms.notices.author.saved', 'parameters' => []]], self::successNotices($client));
        $stored = $this->storedAuthor($author);
        self::assertSame(AuthorMother::BIO, $stored?->bio()->toArray());
        self::assertSame(AuthorMother::META_DESCRIPTION, $stored->seo()->description());
        $avatar = $stored->avatar();
        self::assertNotNull($avatar);
        self::assertFileEquals(AvatarFileMother::path(), self::avatarDirectory().'/'.$avatar->value());
    }

    /**
     * Without a new avatar, the one it had stays.
     */
    public function testItKeepsTheAvatarWhenNoneComes(): void
    {
        $client = self::createAuthenticatedClient();
        $id = AuthorIdMother::random()->value();
        $author = $this->saveAuthor(AuthorMother::create(id: $id, avatar: AuthorMother::AVATAR));
        self::keepAvatar(AuthorMother::AVATAR);

        $this->post($client, 'cms_authors_update', self::HOST.'/authors/'.$id, self::fields());

        self::assertSame(AuthorMother::AVATAR, $this->storedAuthor($author)?->avatar()?->value());
        self::assertFileExists(self::avatarDirectory().'/'.AuthorMother::AVATAR);
    }

    public function testItRemovesTheAvatarANewOneReplaces(): void
    {
        $client = self::createAuthenticatedClient();
        $id = AuthorIdMother::random()->value();
        $this->saveAuthor(AuthorMother::create(id: $id, avatar: AuthorMother::AVATAR));
        self::keepAvatar(AuthorMother::AVATAR);

        $this->post($client, 'cms_authors_update', self::HOST.'/authors/'.$id, self::fields(), ['avatar' => self::upload(AvatarFileMother::OTHER)]);

        self::assertFileDoesNotExist(self::avatarDirectory().'/'.AuthorMother::AVATAR);
        self::assertCount(1, self::avatarsKept());
    }

    public function testItGoesBackAndKeepsTheAuthorWhenTheAvatarIsRefused(): void
    {
        $client = self::createAuthenticatedClient();
        $id = AuthorIdMother::random()->value();
        $author = $this->saveAuthor(AuthorMother::create(id: $id));
        $expected = clone $author;

        $this->post($client, 'cms_authors_update', self::HOST.'/authors/'.$id, self::fields(), ['avatar' => self::upload(AvatarFileMother::NARROWER)]);

        self::assertResponseRedirects(self::HOST.'/authors/'.$id, Response::HTTP_SEE_OTHER);
        self::assertSame([['key' => 'errors.author.avatar.wrong_size', 'parameters' => ['%width%' => 400, '%height%' => 400]]], self::errorNotices($client));
        self::assertEquals($expected, $this->storedAuthor($author));
        self::assertSame([], self::avatarsKept());
    }

    /**
     * A page the bio links to is in use, so it can't be deleted meanwhile.
     */
    public function testItKeepsThePagesTheBioLinksToInUse(): void
    {
        $client = self::createAuthenticatedClient();
        $id = AuthorIdMother::random()->value();
        $this->saveAuthor(AuthorMother::create(id: $id));

        $this->post($client, 'cms_authors_update', self::HOST.'/authors/'.$id, self::fields(
            '{"type":"doc","content":[{"type":"paragraph","content":[{"type":"text","text":"Contacto","marks":[{"type":"link","attrs":{"id":"'.self::PAGE.'"}}]}]}]}',
        ));

        self::assertResponseRedirects('/authors/'.$id, Response::HTTP_SEE_OTHER);
        self::assertTrue(self::getContainer()->get(RelationRepository::class)->isUsed(ResourceType::Page, self::PAGE));
    }

    public function testItRespondsNotFoundWhenTheAuthorDoesNotExist(): void
    {
        $client = self::createAuthenticatedClient();

        $this->post($client, 'cms_authors_update', self::HOST.'/authors/'.AuthorIdMother::random()->value(), self::fields());

        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    /**
     * @return array<string, string>
     */
    private static function fields(string $bio = self::BIO): array
    {
        return [
            'name' => AuthorMother::NAME,
            'slug' => AuthorMother::SLUG,
            'bio' => $bio,
            'meta_description' => AuthorMother::META_DESCRIPTION,
            'robots' => 'index,follow',
        ];
    }

    /**
     * @return list<string>
     */
    private static function avatarsKept(): array
    {
        $files = glob(self::avatarDirectory().'/*.webp');

        return false === $files ? [] : $files;
    }
}
