<?php

declare(strict_types=1);

namespace Cms\Tests\Image\Infrastructure\Controller;

use Cms\Image\Domain\ImageId;
use Cms\Image\Domain\ImageRepository;
use Cms\Image\Domain\ImageStatus;
use Cms\Image\Infrastructure\Controller\ImageUnpublishFormController;
use Cms\Redirect\Application\RedirectCreator;
use Cms\Redirect\Application\TargetInput;
use Cms\Redirect\Domain\Redirect;
use Cms\Redirect\Domain\RedirectRepository;
use Cms\Redirect\Domain\SiteResources;
use Cms\Redirect\Domain\Source;
use Cms\Tests\Image\Domain\ImageMother;
use Cms\Tests\Shared\Infrastructure\CmsWebTestCase;
use Doctrine\ORM\EntityManagerInterface;
use Shared\Domain\Event\EventBus;
use Shared\Domain\MutualExclusion;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\HttpFoundation\Response;

/**
 * The address of an image is that of its file, shared by every copy, and
 * never changes: taking an image off the site says what that address does,
 * and putting it back answers it again.
 */
final class ImageRedirectsTest extends CmsWebTestCase
{
    private const string IMAGE = '0196f3a0-0000-7000-8000-0000000000c1';
    private const string OTHER = '0196f3a0-0000-7000-8000-0000000000c2';

    public function testItSaysTheAddressOfAnImageTakenOffIsGoneAndKnowsTheImageWasThere(): void
    {
        $client = $this->client();

        $this->post($client, 'cms_images_unpublish', $this->url('unpublish'), ['address' => ['action' => 'gone']]);

        self::assertResponseRedirects('/images/'.self::IMAGE, Response::HTTP_SEE_OTHER);
        $redirect = $this->redirectOfImage();
        self::assertNull($redirect?->target()->resource());
        self::assertSame(self::IMAGE, $redirect?->origin()->resource()?->id());
    }

    public function testItLeadsTheAddressOfAnImageTakenOffToAnotherImage(): void
    {
        $client = $this->client();

        $this->post($client, 'cms_images_unpublish', $this->url('unpublish'), ['address' => ['action' => 'image', 'image_id' => self::OTHER]]);

        self::assertSame(self::OTHER, $this->redirectOfImage()?->target()->resource()?->id());
    }

    public function testItDeletesTheRedirectOfItsAddressWhenTheImageIsBackOnTheSite(): void
    {
        $client = $this->client();
        $this->post($client, 'cms_images_unpublish', $this->url('unpublish'), ['address' => ['action' => 'gone']]);

        $this->post($client, 'cms_images_publish', $this->url('publish'));

        self::assertSame(ImageStatus::Published, $this->storedStatus());
        self::assertNull($this->redirectOfImage());
    }

    public function testItKeepsOnTheSiteAnImageARedirectLeadsTo(): void
    {
        $client = $this->client();
        new RedirectCreator(
            '0196f3a0-0000-7000-8000-0000000000e1',
            'https://www.blogautista.localhost/media/2026/11/0196f3a0-0000-7000-8000-0000000000c9-800w.webp',
            new TargetInput('image', self::IMAGE),
            'www.blogautista.localhost',
        )(
            self::getContainer()->get(RedirectRepository::class),
            self::getContainer()->get(EventBus::class),
            self::getContainer()->get(SiteResources::class),
            self::getContainer()->get(MutualExclusion::class),
        );

        $this->post($client, 'cms_images_unpublish', $this->url('unpublish'), ['address' => ['action' => 'none']]);

        self::assertSame(['errors.redirect.undecided'], self::errorKeys($client));
        self::assertSame(ImageStatus::Published, $this->storedStatus());
        // The form comes back with the choices made.
        $saved = $client->getRequest()->getSession()->get(\sprintf(ImageUnpublishFormController::SAVED, self::IMAGE));
        self::assertIsArray($saved);
        self::assertSame(['action' => 'none'], $saved['address'] ?? null);
    }

    /**
     * A client whose kernel holds two images on the site.
     */
    private function client(): KernelBrowser
    {
        $client = self::createAuthenticatedClient();
        $client->disableReboot();
        $images = self::getContainer()->get(ImageRepository::class);
        $images->save(ImageMother::published(id: self::IMAGE));
        $images->save(ImageMother::published(id: self::OTHER));

        return $client;
    }

    private function url(string $action): string
    {
        return \sprintf('http://cms.blogautista.localhost/images/%s/%s', self::IMAGE, $action);
    }

    private function redirectOfImage(): ?Redirect
    {
        $path = self::getContainer()->get(ImageRepository::class)->find(new ImageId(self::IMAGE))?->path()->value() ?? self::fail('No image.');

        return self::getContainer()->get(RedirectRepository::class)->findBySource(Source::ofImage($path));
    }

    private function storedStatus(): ?ImageStatus
    {
        self::getContainer()->get(EntityManagerInterface::class)->clear();

        return self::getContainer()->get(ImageRepository::class)->find(new ImageId(self::IMAGE))?->status();
    }
}
