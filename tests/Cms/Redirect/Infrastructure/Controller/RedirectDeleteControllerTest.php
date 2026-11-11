<?php

declare(strict_types=1);

namespace Cms\Tests\Redirect\Infrastructure\Controller;

use Cms\Redirect\Domain\Redirect;
use Cms\Redirect\Domain\RedirectRepository;
use Cms\Tests\Redirect\Domain\RedirectIdMother;
use Cms\Tests\Redirect\Domain\RedirectMother;
use Cms\Tests\Shared\Infrastructure\CmsWebTestCase;
use Shared\Tests\Infrastructure\Event\QueuedDomainEvents;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\HttpFoundation\Response;

/**
 * A redirect is deleted from where it is edited, and the list of redirects
 * opens. The site reads it as saved: nothing is queued for it.
 */
final class RedirectDeleteControllerTest extends CmsWebTestCase
{
    use QueuedDomainEvents;

    private const string URL = 'http://cms.blogautista.localhost/redirects/%s/delete';
    private const string REDIRECT = RedirectIdMother::DEFAULT_VALUE;

    public function testItRedirectsAnonymousVisitorsToTheLoginForm(): void
    {
        $client = self::createClient();

        $this->post($client, 'cms_redirects_delete', \sprintf(self::URL, self::REDIRECT));

        self::assertResponseRedirects('http://cms.blogautista.localhost/login');
    }

    public function testItDeletesTheRedirectAndGoesBackToTheList(): void
    {
        $client = $this->client();

        $this->post($client, 'cms_redirects_delete', \sprintf(self::URL, self::REDIRECT));

        self::assertResponseRedirects('/redirects', Response::HTTP_SEE_OTHER);
        self::assertSame([['key' => 'cms.notices.redirect.deleted', 'parameters' => []]], self::successNotices($client));
        self::assertNull($this->stored());
        self::assertSame([], $this->queuedEvents());
    }

    public function testItRefusesARequestWithoutTheTokenOfItsForm(): void
    {
        $client = $this->client();

        $client->request('POST', \sprintf(self::URL, self::REDIRECT));

        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
        self::assertNotNull($this->stored());
    }

    public function testItRespondsNotFoundWhenTheRedirectDoesNotExist(): void
    {
        $client = self::createAuthenticatedClient();

        $this->post($client, 'cms_redirects_delete', \sprintf(self::URL, RedirectIdMother::random()->value()));

        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    /**
     * A client whose kernel holds a redirect that says its address is gone.
     */
    private function client(): KernelBrowser
    {
        $client = self::createAuthenticatedClient();
        $client->disableReboot();
        self::getContainer()->get(RedirectRepository::class)->save(RedirectMother::gone(id: self::REDIRECT));

        return $client;
    }

    private function stored(): ?Redirect
    {
        return self::getContainer()->get(RedirectRepository::class)->find(RedirectIdMother::create(self::REDIRECT));
    }
}
