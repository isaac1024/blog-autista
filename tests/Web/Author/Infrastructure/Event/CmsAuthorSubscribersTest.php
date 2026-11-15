<?php

declare(strict_types=1);

namespace Web\Tests\Author\Infrastructure\Event;

use Doctrine\ORM\EntityManagerInterface;
use Shared\Domain\AvatarCopy;
use Shared\Infrastructure\Event\DomainEventMessage;
use Shared\Infrastructure\Event\DomainEventMessageHandler;
use Shared\Infrastructure\Event\EventRetry;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Filesystem\Filesystem;
use Web\Author\Domain\Author;
use Web\Author\Domain\AuthorId;
use Web\Author\Domain\AuthorRepository;
use Web\Tests\Author\Domain\AuthorIdMother;
use Web\Tests\Author\Domain\CmsAuthorPublishedMother;

/**
 * The site takes the authors the CMS publishes and unpublishes, as the
 * worker hands it their events, with the copies of their avatars.
 */
final class CmsAuthorSubscribersTest extends KernelTestCase
{
    private const string OTHER_AUTHOR = '01a1da50-7c41-7e02-9b6d-3f8a2c4e1b03';

    private const string OTHER_AVATAR = '01a1da50-9e10-7f03-8c5a-1b2c3d4e5f04.webp';

    #[\Override]
    protected function setUp(): void
    {
        $filesystem = new Filesystem();
        $filesystem->copy(__DIR__.'/../../../../Cms/Author/Domain/Files/avatar.webp', self::originals().'/'.CmsAuthorPublishedMother::AVATAR);
        $filesystem->copy(__DIR__.'/../../../../Cms/Author/Domain/Files/other.webp', self::originals().'/'.self::OTHER_AVATAR);
    }

    #[\Override]
    protected function tearDown(): void
    {
        foreach ([self::originals(), self::copies()] as $directory) {
            if (str_contains($directory, '/var/test/')) {
                new Filesystem()->remove($directory);
            }
        }

        parent::tearDown();
    }

    public function testItPutsTheAuthorOnTheSiteWithTheCopiesOfItsAvatar(): void
    {
        $this->handle(CmsAuthorPublishedMother::body());

        $author = $this->storedAuthor();
        self::assertNotNull($author);
        self::assertSame(['Isaac Abreu', 'isaac'], [$author->name(), $author->slug()]);
        self::assertSame(AvatarCopy::named('isaac', CmsAuthorPublishedMother::AVATAR), $author->avatarName());

        foreach (AvatarCopy::SIZES as $size) {
            $copy = getimagesize(self::copies().'/'.new AvatarCopy($author->avatarName(), $size)->file());
            self::assertNotFalse($copy);
            self::assertSame(['image/webp', $size, $size], [$copy['mime'], $copy[0], $copy[1]], 'every copy is square, at its size');
        }

        self::assertFileEquals(self::originals().'/'.CmsAuthorPublishedMother::AVATAR, self::copies().'/'.new AvatarCopy($author->avatarName(), AvatarCopy::LARGEST)->file());
    }

    /**
     * A new avatar, or a new address, names new copies; those of the version
     * replaced go.
     */
    public function testItReplacesTheCopiesWhenTheAvatarOrTheAddressChanges(): void
    {
        $this->handle(CmsAuthorPublishedMother::body());
        $first = AvatarCopy::named('isaac', CmsAuthorPublishedMother::AVATAR);

        $this->handle(CmsAuthorPublishedMother::body(avatar: self::OTHER_AVATAR), 2);
        $second = AvatarCopy::named('isaac', self::OTHER_AVATAR);
        $this->handle(CmsAuthorPublishedMother::body(slug: 'isaac-abreu', avatar: self::OTHER_AVATAR), 3);
        $third = AvatarCopy::named('isaac-abreu', self::OTHER_AVATAR);

        self::assertSame($third, $this->storedAuthor()?->avatarName());
        self::assertSame([$third], self::namesKept());
        self::assertNotSame($first, $second);
    }

    public function testItKeepsTheCopiesWhenNeitherTheAvatarNorTheAddressChanges(): void
    {
        $this->handle(CmsAuthorPublishedMother::body());

        $this->handle(CmsAuthorPublishedMother::body(name: 'Isaac'), 2);

        self::assertSame('Isaac', $this->storedAuthor()?->name());
        self::assertSame([AvatarCopy::named('isaac', CmsAuthorPublishedMother::AVATAR)], self::namesKept());
    }

    public function testItKeepsTheAuthorWhenAnEarlierVersionArrivesLate(): void
    {
        $this->handle(CmsAuthorPublishedMother::body(name: 'Isaac'), 2);

        $this->handle(CmsAuthorPublishedMother::body(name: 'Isaac Abreu'));

        self::assertSame('Isaac', $this->storedAuthor()?->name());
    }

    public function testItWaitsForAnotherAuthorToFreeTheAddressBeforeTakingIt(): void
    {
        $this->handle(CmsAuthorPublishedMother::body(avatar: self::OTHER_AVATAR), id: self::OTHER_AUTHOR);

        try {
            $this->handle(CmsAuthorPublishedMother::body());
            self::fail('An author was put at the address of another one.');
        } catch (EventRetry $retry) {
            self::assertSame(60_000, $retry->getRetryDelay());
            self::assertNull($this->storedAuthor());
        }

        $this->dispatch('cms.author.unpublished', self::OTHER_AUTHOR, ['revision' => 2]);
        $this->handle(CmsAuthorPublishedMother::body());

        self::assertSame('isaac', $this->storedAuthor()?->slug());
    }

    public function testItTakesTheAuthorOffTheSiteWithTheCopiesOfItsAvatar(): void
    {
        $this->handle(CmsAuthorPublishedMother::body());

        $this->dispatch('cms.author.unpublished', AuthorIdMother::DEFAULT_VALUE, ['revision' => 2]);

        self::assertNull($this->storedAuthor());
        self::assertSame([], self::namesKept());
        self::assertFileExists(self::originals().'/'.CmsAuthorPublishedMother::AVATAR, 'the file of the CMS stays');
    }

    public function testItTakesAnUnpublicationOfAnAuthorNotOnTheSite(): void
    {
        $this->dispatch('cms.author.unpublished', AuthorIdMother::DEFAULT_VALUE, ['revision' => 2]);

        self::assertNull($this->storedAuthor());
    }

    /**
     * The names the copies kept share, each once.
     *
     * @return list<string>
     */
    private static function namesKept(): array
    {
        $files = glob(self::copies().'/*.webp');
        $names = array_map(static fn (string $file): string => (string) preg_replace('/-[0-9]+w\.webp$/', '', basename($file)), false === $files ? [] : $files);

        return array_values(array_unique($names));
    }

    /**
     * @param array<string, mixed> $body
     */
    private function handle(array $body, int $revision = 1, string $id = AuthorIdMother::DEFAULT_VALUE): void
    {
        $body['revision'] = $revision;

        $this->dispatch('cms.author.published', $id, $body);
    }

    /**
     * @param array<string, mixed> $body
     */
    private function dispatch(string $name, string $id, array $body): void
    {
        self::getContainer()->get(EntityManagerInterface::class)->clear();
        self::getContainer()->get(DomainEventMessageHandler::class)(new DomainEventMessage($name, $id, $body));
    }

    private function storedAuthor(): ?Author
    {
        self::getContainer()->get(EntityManagerInterface::class)->clear();

        return self::getContainer()->get(AuthorRepository::class)->find(new AuthorId(AuthorIdMother::DEFAULT_VALUE));
    }

    private static function originals(): string
    {
        return self::getContainer()->getParameter('web.author.originals_directory');
    }

    private static function copies(): string
    {
        return self::getContainer()->getParameter('web.author.public_directory');
    }
}
