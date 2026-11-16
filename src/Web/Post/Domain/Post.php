<?php

declare(strict_types=1);

namespace Web\Post\Domain;

use Doctrine\ORM\Mapping as ORM;
use Shared\Domain\RichText\RichText;
use Web\Shared\Domain\Robots;

/**
 * A post on the site, as the CMS last published it: only published posts are
 * here, each served under the slug its section had then, on the day it was
 * first published, by its own slug. It keeps the names its section and its
 * author had then too, which the site shows once either is off it.
 *
 * Its constructor takes all of its state, one parameter each, as Doctrine maps it.
 *
 * @SuppressWarnings("PHPMD.ExcessiveParameterList")
 * @SuppressWarnings("PHPMD.TooManyFields")
 */
#[ORM\Entity]
#[ORM\Table(name: 'web_post')]
#[ORM\UniqueConstraint(name: 'uniq_web_post_address', columns: ['section_slug', 'day', 'slug'])]
#[ORM\Index(name: 'idx_web_post_listing', columns: ['first_published_at', 'id'])]
#[ORM\Index(name: 'idx_web_post_section_listing', columns: ['section_id', 'first_published_at', 'id'])]
#[ORM\Index(name: 'idx_web_post_author_listing', columns: ['author_id', 'first_published_at', 'id'])]
#[ORM\Index(name: 'ft_web_post_search', columns: ['title', 'meta_description', 'search_text'], flags: ['fulltext'])]
final class Post
{
    /**
     * @param list<string> $tagIds in the order the editor gave them
     */
    public function __construct(
        #[ORM\Id]
        #[ORM\Column(type: 'web_post_id')]
        private readonly PostId $id,
        #[ORM\Column(length: 100)]
        private string $title,
        #[ORM\Column(length: 80)]
        private string $slug,
        #[ORM\Column(type: 'guid')]
        private string $sectionId,
        #[ORM\Column(length: 60)]
        private string $sectionName,
        #[ORM\Column(length: 80)]
        private string $sectionSlug,
        #[ORM\Column(type: 'guid')]
        private string $authorId,
        #[ORM\Column(length: 100)]
        private string $authorName,
        #[ORM\Column(type: 'json')]
        private array $tagIds,
        /*
         * The image it opens with, by id: the site shows it while it is on it.
         */
        #[ORM\Column(type: 'guid')]
        private string $openingImageId,
        #[ORM\Column(type: 'rich_text')]
        private RichText $content,
        #[ORM\Column(length: 200, nullable: true)]
        private ?string $metaDescription,
        /*
         * What its content says, as plain text, which the site searches with
         * its title and its summary.
         */
        #[ORM\Column(type: 'text', length: 16777215)]
        private string $searchText,
        #[ORM\Column(length: 20, enumType: Robots::class)]
        private Robots $robots,
        #[ORM\Column(type: 'date_immutable')]
        private \DateTimeImmutable $day,
        #[ORM\Column]
        private \DateTimeImmutable $firstPublishedAt,
        #[ORM\Column]
        private \DateTimeImmutable $lastPublishedAt,
    ) {}

    public static function publish(CmsPostPublished $event): self
    {
        return new self(
            new PostId($event->aggregateId()),
            $event->title(),
            $event->slug(),
            $event->sectionId(),
            $event->sectionName(),
            $event->sectionSlug(),
            $event->authorId(),
            $event->authorName(),
            $event->tagIds(),
            $event->openingImageId(),
            $event->content(),
            $event->metaDescription(),
            $event->content()->plainText(),
            $event->robots(),
            $event->day(),
            $event->firstPublishedAt(),
            $event->lastPublishedAt(),
        );
    }

    public function id(): PostId
    {
        return $this->id;
    }

    /**
     * Takes the version the CMS published.
     */
    public function republish(CmsPostPublished $event): void
    {
        $this->title = $event->title();
        $this->slug = $event->slug();
        $this->sectionId = $event->sectionId();
        $this->sectionName = $event->sectionName();
        $this->sectionSlug = $event->sectionSlug();
        $this->authorId = $event->authorId();
        $this->authorName = $event->authorName();
        $this->tagIds = $event->tagIds();
        $this->openingImageId = $event->openingImageId();
        $this->content = $event->content();
        $this->metaDescription = $event->metaDescription();
        $this->searchText = $event->content()->plainText();
        $this->robots = $event->robots();
        $this->day = $event->day();
        $this->firstPublishedAt = $event->firstPublishedAt();
        $this->lastPublishedAt = $event->lastPublishedAt();
    }

    /**
     * Takes the post off the site.
     */
    public function unpublish(PostRepository $postRepository): void
    {
        $postRepository->delete($this);
    }

    /**
     * Saves the post, once no other post on the site has its address: events
     * may come out of order, and the one that frees it may still be on its
     * way.
     */
    public function save(PostRepository $postRepository): void
    {
        $holder = $postRepository->findAt($this->sectionSlug, $this->day, $this->slug);

        if (null !== $holder && !$holder->id->equals($this->id)) {
            throw AddressTaken::atAddress(\sprintf('%s/%s/%s', $this->sectionSlug, $this->day->format('Y-m-d'), $this->slug));
        }

        $postRepository->save($this);
    }

    public function title(): string
    {
        return $this->title;
    }

    public function slug(): string
    {
        return $this->slug;
    }

    public function sectionId(): string
    {
        return $this->sectionId;
    }

    public function sectionName(): string
    {
        return $this->sectionName;
    }

    public function sectionSlug(): string
    {
        return $this->sectionSlug;
    }

    public function authorId(): string
    {
        return $this->authorId;
    }

    public function authorName(): string
    {
        return $this->authorName;
    }

    /**
     * @return list<string>
     */
    public function tagIds(): array
    {
        return $this->tagIds;
    }

    public function openingImageId(): string
    {
        return $this->openingImageId;
    }

    public function content(): RichText
    {
        return $this->content;
    }

    public function metaDescription(): ?string
    {
        return $this->metaDescription;
    }

    public function robots(): Robots
    {
        return $this->robots;
    }

    public function day(): \DateTimeImmutable
    {
        return $this->day;
    }

    public function firstPublishedAt(): \DateTimeImmutable
    {
        return $this->firstPublishedAt;
    }

    public function lastPublishedAt(): \DateTimeImmutable
    {
        return $this->lastPublishedAt;
    }
}
