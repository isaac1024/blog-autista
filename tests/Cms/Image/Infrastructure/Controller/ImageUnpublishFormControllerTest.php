<?php

declare(strict_types=1);

namespace Cms\Tests\Image\Infrastructure\Controller;

use Cms\Image\Domain\ImageRepository;
use Cms\Image\Infrastructure\Controller\ImageUnpublishFormController;
use Cms\Tests\Image\Domain\ImageIdMother;
use Cms\Tests\Image\Domain\ImageMother;
use Cms\Tests\Shared\Infrastructure\CmsWebTestCase;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\HttpFoundation\Response;

/**
 * Where an image is taken off the site. What it shows is told by
 * ImageUnpublishFormTest; here, when it is served.
 */
final class ImageUnpublishFormControllerTest extends CmsWebTestCase
{
    private const string URL = 'http://cms.blogautista.localhost/images/%s/unpublish';
    private const string ID = ImageIdMother::DEFAULT_VALUE;

    public function testItRedirectsAnonymousVisitorsToTheLoginForm(): void
    {
        $client = self::createClient();

        $client->request('GET', \sprintf(self::URL, self::ID));

        self::assertResponseRedirects('http://cms.blogautista.localhost/login');
    }

    public function testItOpensTheFormThatTakesItOff(): void
    {
        $client = $this->client(onSite: true);

        $client->request('GET', \sprintf(self::URL, self::ID));

        self::assertResponseIsSuccessful();
        self::assertRouteSame('cms_images_unpublish_form', ['id' => self::ID]);
    }

    /**
     * What was refused waits for the form once: it is chosen again, then
     * forgotten.
     */
    public function testItTakesWhatWasRefusedOnce(): void
    {
        $client = $this->client(onSite: true);
        $this->post($client, 'cms_images_unpublish', \sprintf(self::URL, self::ID), ['address' => ['action' => '']]);
        self::assertNotNull($client->getRequest()->getSession()->get(\sprintf(ImageUnpublishFormController::SAVED, self::ID)));

        $client->request('GET', \sprintf(self::URL, self::ID));

        self::assertResponseIsSuccessful();
        self::assertNull($client->getRequest()->getSession()->get(\sprintf(ImageUnpublishFormController::SAVED, self::ID)));
    }

    public function testItGoesBackToTheEditorToTellWhyWhenItIsNotOnTheSite(): void
    {
        $client = $this->client(onSite: false);

        $client->request('GET', \sprintf(self::URL, self::ID));

        self::assertResponseRedirects('/images/'.self::ID, Response::HTTP_SEE_OTHER);
        self::assertSame([['key' => 'errors.image.publication.not_published', 'parameters' => []]], self::errorNotices($client));
    }

    public function testItRespondsNotFoundWhenTheResourceDoesNotExist(): void
    {
        $client = self::createAuthenticatedClient();

        $client->request('GET', \sprintf(self::URL, ImageIdMother::random()->value()));

        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    private function client(bool $onSite): KernelBrowser
    {
        $client = self::createAuthenticatedClient();
        $client->disableReboot();
        self::getContainer()->get(ImageRepository::class)->save($onSite ? ImageMother::published(id: self::ID) : ImageMother::create(id: self::ID));

        return $client;
    }
}
