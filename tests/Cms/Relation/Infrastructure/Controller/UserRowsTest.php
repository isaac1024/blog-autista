<?php

declare(strict_types=1);

namespace Cms\Tests\Relation\Infrastructure\Controller;

use Cms\Image\Domain\ImageRepository;
use Cms\Page\Domain\PageRepository;
use Cms\Relation\Application\UserResponse;
use Cms\Relation\Infrastructure\Controller\UserRows;
use Cms\Settings\Domain\SettingsRepository;
use Cms\Tests\Image\Domain\ImageMother;
use Cms\Tests\Page\Domain\PageMother;
use Cms\Tests\Settings\Domain\SettingsIdMother;
use Cms\Tests\Settings\Domain\SettingsMother;
use Shared\Infrastructure\Ui\Page\PageComponent;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

final class UserRowsTest extends KernelTestCase
{
    private const string PAGE = '01a19e72-b07f-7d78-889c-fa4a14a4fb01';
    private const string OTHER_PAGE = '01a19e72-b07f-7d78-889c-fa4a14a4fb02';
    private const string GONE_PAGE = '01a19e72-b07f-7d78-889c-fa4a14a4fb03';
    private const string IMAGE = '01a1b801-ce58-723d-8234-b76d9105d001';

    /**
     * Links are generated as in a request to the CMS, which serves them
     * under its own host.
     */
    #[\Override]
    protected function setUp(): void
    {
        self::getContainer()->get(UrlGeneratorInterface::class)->getContext()->setHost('cms.blogautista.localhost');
    }

    /**
     * Pages, then images, then the settings, each leading to its editor,
     * whatever order they come in; a resource gone meanwhile is left out.
     */
    public function testItGivesARowLeadingToTheEditorOfEachUser(): void
    {
        $container = self::getContainer();
        $container->get(PageRepository::class)->save(PageMother::create(id: self::PAGE, title: 'Sobre mí', slug: 'sobre-mi'));
        $container->get(PageRepository::class)->save(PageMother::create(id: self::OTHER_PAGE, title: 'Contacto', slug: 'contacto'));
        $container->get(ImageRepository::class)->save(ImageMother::create(id: self::IMAGE, alt: 'Círculo naranja sobre fondo azul'));
        $container->get(SettingsRepository::class)->save(SettingsMother::create());

        $rows = $container->get(UserRows::class)([
            new UserResponse('settings', SettingsIdMother::DEFAULT_VALUE),
            new UserResponse('image', self::IMAGE),
            new UserResponse('page', self::GONE_PAGE),
            new UserResponse('page', self::OTHER_PAGE),
            new UserResponse('page', self::PAGE),
        ]);

        self::assertSame([
            ['Contacto', '/pages/'.self::OTHER_PAGE],
            ['Sobre mí', '/pages/'.self::PAGE],
            ['Círculo naranja sobre fondo azul', '/images/'.self::IMAGE],
            [self::getContainer()->get(TranslatorInterface::class)->trans('cms.settings.title'), '/settings'],
        ], array_map(static fn (PageComponent $row): array => [$row->props['title'], $row->props['url']], $rows));
    }

    /**
     * Each user is looked for among the resources of its type only, though
     * ids of different types may be the same, and the settings only while
     * they use the resource.
     */
    public function testItLooksForEachUserAmongTheResourcesOfItsType(): void
    {
        $container = self::getContainer();
        $container->get(PageRepository::class)->save(PageMother::create(id: self::PAGE, title: 'Sobre mí', slug: 'sobre-mi'));
        $container->get(ImageRepository::class)->save(ImageMother::create(id: self::PAGE, alt: 'Círculo naranja sobre fondo azul'));
        $container->get(SettingsRepository::class)->save(SettingsMother::create());

        $rows = $container->get(UserRows::class)([new UserResponse('page', self::PAGE)]);

        self::assertSame(
            [['Sobre mí', '/pages/'.self::PAGE]],
            array_map(static fn (PageComponent $row): array => [$row->props['title'], $row->props['url']], $rows),
        );
    }

    /**
     * Without them nothing uses a resource through them.
     */
    public function testItLeavesOutTheSettingsWhileThereAreNone(): void
    {
        self::assertSame([], self::getContainer()->get(UserRows::class)([new UserResponse('settings', SettingsIdMother::DEFAULT_VALUE)]));
    }

    public function testItGivesNoRowsWithoutUsers(): void
    {
        self::assertSame([], self::getContainer()->get(UserRows::class)([]));
    }
}
