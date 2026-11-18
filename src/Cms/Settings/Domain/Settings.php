<?php

declare(strict_types=1);

namespace Cms\Settings\Domain;

use Cms\Shared\Domain\PublicationTimeline;
use Doctrine\ORM\Mapping as ORM;
use Shared\Domain\AggregateRoot;
use Shared\Domain\Clock;
use Shared\Domain\Event\EventBus;
use Shared\Domain\RichText\LinkType;
use Shared\Domain\Transaction;

/**
 * The settings of the site: the name of the blog, the image it is shared
 * with by default, and the pages its main and footer menus lead to. The site
 * has them once, created from the console, and never deletes them.
 *
 * Editors work on their version in the CMS; once published, the site keeps a
 * copy of it, which only changes when they are published again.
 *
 * - Editing saves whatever valid fields an editor writes; only the name is
 *   required, so publishing needs nothing else.
 * - The default image and the pages of the menus are optional, and needn't be
 *   on the site: the site paints only what is.
 * - They tell which images and pages they use, so neither is deleted while
 *   the settings use it.
 *
 * Its rules reach every value object, error and event of the settings, so its
 * coupling grows with what they are rather than with how they are designed.
 *
 * @SuppressWarnings("PHPMD.CouplingBetweenObjects")
 */
#[ORM\Entity]
#[ORM\Table(name: 'cms_settings')]
#[ORM\UniqueConstraint(name: 'uniq_cms_settings_site', columns: ['site'])]
final class Settings extends AggregateRoot
{
    /**
     * The one site the settings belong to: its unique index keeps the site to
     * one row of settings.
     */
    private const string SITE = 'blog';

    #[ORM\Column(length: 20)]
    private readonly string $site;

    public function __construct(
        #[ORM\Id]
        #[ORM\Column(type: 'cms_settings_id')]
        private readonly SettingsId $id,
        #[ORM\Embedded(class: SettingsVersion::class, columnPrefix: false)]
        private SettingsVersion $version,
        #[ORM\Column(length: 20, enumType: SettingsStatus::class)]
        private SettingsStatus $status,
        /*
         * The version last published.
         */
        #[ORM\Column(name: 'published_version', type: 'cms_settings_version', nullable: true)]
        private ?SettingsVersion $published,
        #[ORM\Embedded(class: PublicationTimeline::class, columnPrefix: false)]
        private PublicationTimeline $timeline,
        #[ORM\Column(options: ['default' => 0])]
        private int $publicationRevision,
    ) {
        $this->site = self::SITE;
    }

    /**
     * Initialises the settings with the name of the blog, as a draft without
     * a default image nor menus.
     */
    public static function create(string $id, string $name): self
    {
        return new self(
            new SettingsId($id),
            new SettingsVersion(new SiteName($name), null, null, Menu::empty(), Menu::empty()),
            SettingsStatus::Draft,
            null,
            PublicationTimeline::start(Clock::now()),
            0,
        );
    }

    /**
     * Replaces everything an editor writes: the name, the default image and
     * the author, blank for none, and both menus.
     *
     * On published settings, it only changes the version being edited: the
     * site keeps its version until they are published again.
     *
     * @param array<array-key, mixed> $mainMenu   entries with a page_id and an optional label
     * @param array<array-key, mixed> $footerMenu entries with a page_id and an optional label
     */
    public function edit(string $name, string $defaultImage, string $author, array $mainMenu, array $footerMenu): void
    {
        $version = SettingsVersion::fromPrimitives($name, $defaultImage, $author, $mainMenu, $footerMenu);

        $this->changeResourcesUsed(fn () => $this->replaceVersion($version));
    }

    /**
     * Puts the version last saved on the site, for the first time or in place
     * of the one there.
     */
    public function publish(): void
    {
        if (!$this->hasSomethingToPublish()) {
            throw InvalidSettingsPublication::nothingToPublish();
        }

        $this->status = SettingsStatus::Published;
        $this->changeResourcesUsed(function (): void {
            $this->published = $this->version;
        });
        $this->timeline = $this->timeline->published(Clock::now());
        ++$this->publicationRevision;

        $this->record(new SettingsPublished(
            $this->id->value(),
            $this->version->name()->value(),
            $this->version->defaultImage()?->value(),
            $this->version->author()?->value(),
            $this->version->mainMenu()->toArray(),
            $this->version->footerMenu()->toArray(),
            $this->publicationRevision,
        ));
    }

    /**
     * Brings the version being edited back to the one on the site.
     */
    public function discardChanges(): void
    {
        if (null === $this->published || !$this->hasUnpublishedChanges()) {
            throw InvalidSettingsPublication::noChangesToDiscard();
        }

        $published = $this->published;
        $this->changeResourcesUsed(fn () => $this->replaceVersion($published));
    }

    /**
     * Whether the settings are on the site with a version other than the one
     * being edited, so their changes can be discarded.
     */
    public function hasUnpublishedChanges(): bool
    {
        // Settings have a version published only once on the site, where they stay.
        return null !== $this->published && !$this->published->equals($this->version);
    }

    /**
     * Whether publishing has something to put on the site: the settings
     * aren't there, or they are with another version.
     */
    public function hasSomethingToPublish(): bool
    {
        return SettingsStatus::Published !== $this->status || $this->hasUnpublishedChanges();
    }

    /**
     * Saves the settings, then tells the rest of the system what happened to
     * them since they were loaded. They take their events before saving, so
     * the saved settings hold none, and none is told twice. Both happen in
     * one transaction: what a subscriber told at once fails to do undoes the
     * save.
     */
    public function save(SettingsRepository $settingsRepository, EventBus $eventBus, Transaction $transaction): void
    {
        $events = $this->pullDomainEvents();
        $transaction->run(function () use ($settingsRepository, $eventBus, $events): void {
            $settingsRepository->save($this);
            $eventBus->publish(...$events);
        });
    }

    public function name(): SiteName
    {
        return $this->version->name();
    }

    public function defaultImage(): ?ImageId
    {
        return $this->version->defaultImage();
    }

    public function author(): ?AuthorId
    {
        return $this->version->author();
    }

    public function mainMenu(): Menu
    {
        return $this->version->mainMenu();
    }

    public function footerMenu(): Menu
    {
        return $this->version->footerMenu();
    }

    public function status(): SettingsStatus
    {
        return $this->status;
    }

    /**
     * The version the site shows, null until they are first published.
     */
    public function onSite(): ?SettingsVersion
    {
        return SettingsStatus::Published === $this->status ? $this->published : null;
    }

    public function timeline(): PublicationTimeline
    {
        return $this->timeline;
    }

    /**
     * Makes a change and, when it changes the images or the pages the
     * settings use, tells which they use now.
     *
     * @param \Closure(): void $change
     */
    private function changeResourcesUsed(\Closure $change): void
    {
        $before = $this->resourcesUsed();
        $change();
        $after = $this->resourcesUsed();

        if ($before['images'] !== $after['images']) {
            $this->record(new SettingsImagesUsed($this->id->value(), $after['images']));
        }

        if ($before['pages'] !== $after['pages']) {
            $this->record(new SettingsPagesLinked($this->id->value(), $after['pages']));
        }

        $contents = static fn (array $used): array => [$used['posts'], $used['terms'], $used['authors'], $used['externals']];

        if ($contents($before) !== $contents($after)) {
            $this->record(new SettingsContentsLinked($this->id->value(), $after['posts'], $after['terms'], $after['authors'], $after['externals']));
        }
    }

    /**
     * The images and what the menus lead to, of the version being edited
     * and of the one last published, each once and in order: the pages apart
     * from the posts, sections and tags, authors and external resources.
     *
     * @return array{images: list<string>, pages: list<string>, posts: list<string>, terms: list<string>, authors: list<string>, externals: list<string>}
     */
    private function resourcesUsed(): array
    {
        $images = [...$this->version->imageIds(), ...($this->published?->imageIds() ?? [])];
        sort($images);
        $linked = fn (LinkType $type): array => self::eachOnce([...$this->version->linkedIds($type), ...($this->published?->linkedIds($type) ?? [])]);

        return [
            'images' => array_values(array_unique($images)),
            'pages' => $linked(LinkType::Page),
            'posts' => $linked(LinkType::Post),
            'terms' => $linked(LinkType::Term),
            'authors' => $linked(LinkType::Author),
            'externals' => $linked(LinkType::External),
        ];
    }

    /**
     * @param list<string> $ids
     *
     * @return list<string>
     */
    private static function eachOnce(array $ids): array
    {
        sort($ids);

        return array_values(array_unique($ids));
    }

    /**
     * The update date only moves when what is written changes.
     */
    private function replaceVersion(SettingsVersion $version): void
    {
        if (!$version->equals($this->version)) {
            $this->timeline = $this->timeline->updated(Clock::now());
        }

        $this->version = $version;
    }
}
