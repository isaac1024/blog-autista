<?php

declare(strict_types=1);

namespace Web\Author\Domain;

use Doctrine\ORM\Mapping as ORM;
use Shared\Domain\AvatarCopy;
use Shared\Domain\RichText\RichText;
use Web\Shared\Domain\Robots;

/**
 * An author on the site, as the CMS last published it: only published
 * authors are here, each served at its own address with the copies of its
 * avatar, which the site makes from the file the CMS keeps.
 *
 * Its constructor takes all of its state, one parameter each, as Doctrine maps it.
 *
 * @SuppressWarnings("PHPMD.ExcessiveParameterList")
 */
#[ORM\Entity]
#[ORM\Table(name: 'web_author')]
#[ORM\UniqueConstraint(name: 'uniq_web_author_slug', columns: ['slug'])]
final class Author
{
    /**
     * The name of the copies the site served before the version it now
     * takes, removed once that version is saved. Not persisted.
     */
    private ?string $replacedAvatar = null;

    /**
     * Whether the copies of its avatar are new to the site, so a failed save
     * removes them again. Not persisted.
     */
    private bool $newCopies = false;

    /**
     * @param string $avatar     where the CMS keeps the file of the avatar, relative to its avatar storage
     * @param string $avatarName the name its copies share
     */
    public function __construct(
        #[ORM\Id]
        #[ORM\Column(type: 'web_author_id')]
        private readonly AuthorId $id,
        #[ORM\Column(length: 100)]
        private string $name,
        #[ORM\Column(length: 80)]
        private string $slug,
        #[ORM\Column(length: 64)]
        private string $avatar,
        #[ORM\Column(length: 100)]
        private string $avatarName,
        #[ORM\Column(type: 'rich_text')]
        private RichText $bio,
        #[ORM\Column(length: 200, nullable: true)]
        private ?string $metaDescription,
        #[ORM\Column(length: 20, enumType: Robots::class)]
        private Robots $robots,
        #[ORM\Column]
        private \DateTimeImmutable $firstPublishedAt,
        #[ORM\Column]
        private \DateTimeImmutable $lastPublishedAt,
    ) {}

    public static function publish(CmsAuthorPublished $event): self
    {
        $author = new self(
            new AuthorId($event->aggregateId()),
            $event->name(),
            $event->slug(),
            $event->avatar(),
            AvatarCopy::named($event->slug(), $event->avatar()),
            $event->bio(),
            $event->metaDescription(),
            $event->robots(),
            $event->firstPublishedAt(),
            $event->lastPublishedAt(),
        );
        $author->newCopies = true;

        return $author;
    }

    public function id(): AuthorId
    {
        return $this->id;
    }

    /**
     * Takes the version the CMS published, in place of the one on the site.
     * A new avatar, or a new address, names new copies; those of the version
     * replaced go once it is.
     */
    public function republish(CmsAuthorPublished $event): void
    {
        $avatarName = AvatarCopy::named($event->slug(), $event->avatar());

        if ($avatarName !== $this->avatarName) {
            $this->replacedAvatar = $this->avatarName;
            $this->newCopies = true;
        }

        $this->name = $event->name();
        $this->slug = $event->slug();
        $this->avatar = $event->avatar();
        $this->avatarName = $avatarName;
        $this->bio = $event->bio();
        $this->metaDescription = $event->metaDescription();
        $this->robots = $event->robots();
        $this->firstPublishedAt = $event->firstPublishedAt();
        $this->lastPublishedAt = $event->lastPublishedAt();
    }

    /**
     * Makes the copies of its avatar and keeps the author, as long as no
     * other author on the site is at its address. When saving fails, the
     * copies made are removed, unless the site served them already; once it
     * is saved, the copies of the version replaced are.
     *
     * @throws SlugTaken while the event that frees the address has yet to arrive
     */
    public function save(AuthorRepository $authorRepository, AvatarCopies $avatarCopies): void
    {
        $holder = $authorRepository->findBySlug($this->slug);

        if (null !== $holder && !$holder->id->equals($this->id)) {
            throw SlugTaken::withSlug($this->slug);
        }

        $avatarCopies->publish($this->avatar, $this->avatarName);

        try {
            $authorRepository->save($this);
        } catch (\Throwable $exception) {
            if ($this->newCopies) {
                $avatarCopies->remove($this->avatarName);
            }

            throw $exception;
        }

        if (null !== $this->replacedAvatar) {
            $avatarCopies->remove($this->replacedAvatar);
        }

        $this->replacedAvatar = null;
        $this->newCopies = false;
    }

    /**
     * Takes the author off the site. The copies of its avatar go first, so
     * the site stops serving them even if removing the author fails.
     */
    public function unpublish(AuthorRepository $authorRepository, AvatarCopies $avatarCopies): void
    {
        $avatarCopies->remove($this->avatarName);
        $authorRepository->delete($this);
    }

    public function name(): string
    {
        return $this->name;
    }

    public function slug(): string
    {
        return $this->slug;
    }

    /**
     * The name the copies of its avatar share.
     */
    public function avatarName(): string
    {
        return $this->avatarName;
    }

    public function bio(): RichText
    {
        return $this->bio;
    }

    public function metaDescription(): ?string
    {
        return $this->metaDescription;
    }

    public function robots(): Robots
    {
        return $this->robots;
    }
}
