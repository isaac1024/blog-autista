<?php

declare(strict_types=1);

namespace Web\Tests\Author\Infrastructure;

use Doctrine\ORM\EntityManagerInterface;
use Shared\Domain\AvatarCopy;
use Shared\Infrastructure\Event\DomainEventMessage;
use Shared\Infrastructure\Event\DomainEventMessageHandler;
use Shared\Infrastructure\Event\EventRetry;
use Shared\Tests\Domain\ClockMother;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Filesystem\Filesystem;
use Web\Author\Domain\Author;
use Web\Author\Domain\AuthorId;
use Web\Author\Domain\AuthorRepository;

/**
 * The site takes the authors the CMS publishes and unpublishes, as the
 * worker hands it their events, with the copies of their avatars.
 */
final class CmsAuthorSubscribersTest extends KernelTestCase
{
    private const string AUTHOR = '01a1da50-7c41-7e02-9b6d-3f8a2c4e1b02';

    private const string OTHER_AUTHOR = '01a1da50-7c41-7e02-9b6d-3f8a2c4e1b03';

    private const string AVATAR = '01a1da50-9e10-7f03-8c5a-1b2c3d4e5f03.webp';

    private const string OTHER_AVATAR = '01a1da50-9e10-7f03-8c5a-1b2c3d4e5f04.webp';

    #[\Override]
    protected function setUp(): void
    {
        $filesystem = new Filesystem();
        $filesystem->copy(__DIR__.'/../../../Cms/Author/Domain/Files/avatar.webp', self::originals().'/'.self::AVATAR);
        $filesystem->copy(__DIR__.'/../../../Cms/Author/Domain/Files/other.webp', self::originals().'/'.self::OTHER_AVATAR);
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
        $this->handle(self::body());

        $author = $this->storedAuthor();
        self::assertNotNull($author);
        self::assertSame(['Isaac Abreu', 'isaac'], [$author->name(), $author->slug()]);
        self::assertSame(AvatarCopy::named('isaac', self::AVATAR), $author->avatarName());

        foreach (AvatarCopy::SIZES as $size) {
            $copy = getimagesize(self::copies().'/'.new AvatarCopy($author->avatarName(), $size)->file());
            self::assertNotFalse($copy);
            self::assertSame(['image/webp', $size, $size], [$copy['mime'], $copy[0], $copy[1]], 'every copy is square, at its size');
        }

        self::assertFileEquals(self::originals().'/'.self::AVATAR, self::copies().'/'.new AvatarCopy($author->avatarName(), AvatarCopy::LARGEST)->file());
    }

    /**
     * A new avatar, or a new address, names new copies; those of the version
     * replaced go.
     */
    public function testItReplacesTheCopiesWhenTheAvatarOrTheAddressChanges(): void
    {
        $this->handle(self::body());
        $first = AvatarCopy::named('isaac', self::AVATAR);

        $this->handle(self::body(avatar: self::OTHER_AVATAR), 2);
        $second = AvatarCopy::named('isaac', self::OTHER_AVATAR);
        $this->handle(self::body(slug: 'isaac-abreu', avatar: self::OTHER_AVATAR), 3);
        $third = AvatarCopy::named('isaac-abreu', self::OTHER_AVATAR);

        self::assertSame($third, $this->storedAuthor()?->avatarName());
        self::assertSame([$third], self::namesKept());
        self::assertNotSame($first, $second);
    }

    public function testItKeepsTheCopiesWhenNeitherTheAvatarNorTheAddressChanges(): void
    {
        $this->handle(self::body());

        $this->handle(self::body(name: 'Isaac'), 2);

        self::assertSame('Isaac', $this->storedAuthor()?->name());
        self::assertSame([AvatarCopy::named('isaac', self::AVATAR)], self::namesKept());
    }

    public function testItKeepsTheAuthorWhenAnEarlierVersionArrivesLate(): void
    {
        $this->handle(self::body(name: 'Isaac'), 2);

        $this->handle(self::body(name: 'Isaac Abreu'));

        self::assertSame('Isaac', $this->storedAuthor()?->name());
    }

    public function testItWaitsForAnotherAuthorToFreeTheAddressBeforeTakingIt(): void
    {
        $this->handle(self::body(avatar: self::OTHER_AVATAR), id: self::OTHER_AUTHOR);

        try {
            $this->handle(self::body());
            self::fail('An author was put at the address of another one.');
        } catch (EventRetry $retry) {
            self::assertSame(60_000, $retry->getRetryDelay());
            self::assertNull($this->storedAuthor());
        }

        $this->dispatch('cms.author.unpublished', self::OTHER_AUTHOR, ['revision' => 2]);
        $this->handle(self::body());

        self::assertSame('isaac', $this->storedAuthor()?->slug());
    }

    public function testItTakesTheAuthorOffTheSiteWithTheCopiesOfItsAvatar(): void
    {
        $this->handle(self::body());

        $this->dispatch('cms.author.unpublished', self::AUTHOR, ['revision' => 2]);

        self::assertNull($this->storedAuthor());
        self::assertSame([], self::namesKept());
        self::assertFileExists(self::originals().'/'.self::AVATAR, 'the file of the CMS stays');
    }

    public function testItTakesAnUnpublicationOfAnAuthorNotOnTheSite(): void
    {
        $this->dispatch('cms.author.unpublished', self::AUTHOR, ['revision' => 2]);

        self::assertNull($this->storedAuthor());
    }

    /**
     * @return array<string, mixed>
     */
    private static function body(string $name = 'Isaac Abreu', string $slug = 'isaac', string $avatar = self::AVATAR): array
    {
        return [
            'name' => $name,
            'slug' => $slug,
            'avatar' => $avatar,
            'bio' => ['version' => 1, 'blocks' => [['type' => 'paragraph', 'text' => [['text' => 'Escribo sobre autismo.']]]]],
            'meta_description' => 'Quién escribe este blog.',
            'robots' => 'index,follow',
            'first_published_at' => ClockMother::now()->format(\DATE_ATOM),
            'last_published_at' => ClockMother::now()->format(\DATE_ATOM),
        ];
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
    private function handle(array $body, int $revision = 1, string $id = self::AUTHOR): void
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

        return self::getContainer()->get(AuthorRepository::class)->find(new AuthorId(self::AUTHOR));
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
