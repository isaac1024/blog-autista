<?php

declare(strict_types=1);

namespace Cms\Tests\Post\Infrastructure\Controller;

use Cms\Post\Infrastructure\Controller\PostUnpublishFormController;
use Cms\Tests\Post\Domain\PostIdMother;
use Cms\Tests\Post\Domain\PostMother;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\HttpFoundation\Response;

/**
 * Where a post is taken off the site, when it is served: only for a post on
 * the site, with what was refused chosen again once.
 */
final class PostUnpublishFormControllerTest extends PostControllerTestCase
{
    private const string URL = self::HOST.'/posts/%s/unpublish';
    private const string ID = PostIdMother::DEFAULT_VALUE;

    public function testItOpensTheFormThatTakesItOff(): void
    {
        $client = $this->client(onSite: true);

        $client->request('GET', \sprintf(self::URL, self::ID));

        self::assertResponseIsSuccessful();
        self::assertRouteSame('cms_posts_unpublish_form', ['id' => self::ID]);
    }

    /**
     * What was refused waits for the form once: it is chosen again, then
     * forgotten.
     */
    public function testItTakesWhatWasRefusedOnce(): void
    {
        $client = $this->client(onSite: true);
        $this->post($client, 'cms_posts_unpublish', \sprintf(self::URL, self::ID), ['address' => ['action' => '']]);
        self::assertNotNull($client->getRequest()->getSession()->get(\sprintf(PostUnpublishFormController::SAVED, self::ID)));

        $client->request('GET', \sprintf(self::URL, self::ID));

        self::assertResponseIsSuccessful();
        self::assertNull($client->getRequest()->getSession()->get(\sprintf(PostUnpublishFormController::SAVED, self::ID)));
    }

    public function testItGoesBackToTheEditorToTellWhyWhenItIsNotOnTheSite(): void
    {
        $client = $this->client(onSite: false);

        $client->request('GET', \sprintf(self::URL, self::ID));

        self::assertResponseRedirects('/posts/'.self::ID, Response::HTTP_SEE_OTHER);
        self::assertSame([['key' => 'errors.post.publication.not_published', 'parameters' => []]], self::errorNotices($client));
    }

    public function testItRespondsNotFoundWhenThePostDoesNotExist(): void
    {
        $client = self::createAuthenticatedClient();

        $client->request('GET', \sprintf(self::URL, PostIdMother::random()->value()));

        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    private function client(bool $onSite): KernelBrowser
    {
        $client = self::createAuthenticatedClient();
        $client->disableReboot();
        $this->saveClassifiers();
        $this->savePost($onSite ? PostMother::published() : PostMother::create());

        return $client;
    }
}
