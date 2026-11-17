<?php

declare(strict_types=1);

namespace Cms\Tests\Shared\Infrastructure\Ui;

use Cms\External\Domain\Availability;
use Cms\External\Domain\ExternalResourceRepository;
use Cms\External\Domain\ResourceStatus;
use Cms\Shared\Infrastructure\Resolution\CmsLinkedContents;
use Cms\Tests\External\Domain\ExternalResourceMother;
use Shared\Domain\RichText\RichText;
use Shared\Infrastructure\Ui\ExternalComponents;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * What a preview of the CMS paints of the external resources a text shows,
 * as the site would: those published and available, in their version on
 * the site, the image served by the CMS behind a signed link.
 */
final class CmsExternalViewsTest extends KernelTestCase
{
    private const string OTHER = '01a1e2a0-0000-7000-8000-000000000002';

    private const string THIRD = '01a1e2a0-0000-7000-8000-000000000003';

    public function testItPaintsTheResourcesOnTheSiteAsTheSiteDoes(): void
    {
        $repository = self::getContainer()->get(ExternalResourceRepository::class);
        $repository->save(ExternalResourceMother::video(title: 'Editado', status: ResourceStatus::Published, published: ExternalResourceMother::video()->version()));
        $repository->save(ExternalResourceMother::article(id: self::OTHER, status: ResourceStatus::Published, availability: Availability::Unavailable));
        $repository->save(ExternalResourceMother::article(id: self::THIRD, url: 'https://www.example.org/borrador.html'));

        $linked = self::getContainer()->get(CmsLinkedContents::class)(RichText::fromArray(['version' => 1, 'blocks' => [
            ['type' => 'widget', 'kind' => 'related_page', 'external_id' => ExternalResourceMother::ID],
            ['type' => 'widget', 'kind' => 'related_page', 'external_id' => self::OTHER],
            ['type' => 'paragraph', 'text' => [['text' => 'borrador', 'marks' => ['link'], 'external_id' => self::THIRD]]],
        ]]), [], null);

        self::assertSame([ExternalResourceMother::ID], array_keys($linked));
        $external = $linked[ExternalResourceMother::ID]['external'] ?? null;
        self::assertNotNull($external);
        $video = ExternalComponents::component($external, false);
        self::assertSame(['Shared:ExternalEmbed', 'Un vídeo'], [$video->name, $video->props['title']]);
        $image = $video->props['image'];
        self::assertIsArray($image);
        self::assertIsString($image['src']);
        self::assertStringContainsString('/preview/externals/'.ExternalResourceMother::ID.'/image/'.ExternalResourceMother::IMAGE.'?_expiration=', $image['src']);
    }

    public function testItLeadsTheMenusToTheResourcesOnTheSite(): void
    {
        self::getContainer()->get(ExternalResourceRepository::class)->save(ExternalResourceMother::article(status: ResourceStatus::Published));

        $targets = self::getContainer()->get(CmsLinkedContents::class)->menuTargets([
            ['type' => 'external', 'id' => ExternalResourceMother::ID, 'label' => null],
            ['type' => 'external', 'id' => self::OTHER, 'label' => 'Ninguno'],
        ]);

        self::assertSame([ExternalResourceMother::ID => ['url' => ExternalResourceMother::ARTICLE_URL, 'title' => 'Una noticia']], array_map(static fn (array $target): array => ['url' => $target['url'], 'title' => $target['title']], $targets));
    }
}
