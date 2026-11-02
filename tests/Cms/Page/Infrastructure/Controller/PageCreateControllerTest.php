<?php

declare(strict_types=1);

namespace Cms\Tests\Page\Infrastructure\Controller;

use Cms\Tests\Shared\Infrastructure\CmsWebTestCase;
use Symfony\Component\HttpFoundation\Response;

final class PageCreateControllerTest extends CmsWebTestCase
{
    public function testItRespondsSeeOtherWhenThePageIsCreated(): void
    {
        $client = self::createAuthenticatedClient();

        $client->request('POST', 'http://cms.blogautista.localhost/pages', ['title' => 'Posts']);

        self::assertResponseStatusCodeSame(Response::HTTP_SEE_OTHER);
    }

    public function testItRespondsBadRequestWhenTheTitleIsInvalid(): void
    {
        $client = self::createAuthenticatedClient();

        $client->request('POST', 'http://cms.blogautista.localhost/pages', ['title' => ' ']);

        self::assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
    }
}
