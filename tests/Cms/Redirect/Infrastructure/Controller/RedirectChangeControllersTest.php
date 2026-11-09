<?php

declare(strict_types=1);

namespace Cms\Tests\Redirect\Infrastructure\Controller;

use Cms\Page\Domain\PageRepository;
use Cms\Redirect\Application\RedirectCreator;
use Cms\Redirect\Application\RedirectDeleter;
use Cms\Redirect\Application\RedirectRetargeter;
use Cms\Redirect\Application\TargetInput;
use Cms\Redirect\Domain\Redirect;
use Cms\Redirect\Domain\RedirectId;
use Cms\Redirect\Domain\RedirectRepository;
use Cms\Redirect\Domain\SiteResources;
use Cms\Tests\Page\Domain\PageMother;
use Cms\Tests\Shared\Infrastructure\CmsWebTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use Shared\Domain\Event\EventBus;
use Shared\Domain\MutualExclusion;
use Shared\Infrastructure\Event\DomainEventMessage;
use Shared\Tests\Domain\RecordingMutualExclusion;
use Shared\Tests\Infrastructure\Event\QueuedDomainEvents;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\HttpFoundation\Response;

/**
 * A redirect leads somewhere else, or is deleted, from where it is edited;
 * its address stays, and the site is told each change.
 */
final class RedirectChangeControllersTest extends CmsWebTestCase
{
    use QueuedDomainEvents;

    private const string REDIRECT = '0196f3a0-0000-7000-8000-0000000000e1';
    private const string UNKNOWN = '0196f3a0-0000-7000-8000-0000000000ef';
    private const string PAGE = '0196f3a0-0000-7000-8000-0000000000a1';
    private const string OTHER = '0196f3a0-0000-7000-8000-0000000000a2';
    private const string OFF_SITE = '0196f3a0-0000-7000-8000-0000000000a3';
    private const string HOST = 'www.blogautista.localhost';

    public function testItLeadsARedirectSomewhereElseAndTellsTheSite(): void
    {
        $client = $this->client();

        $this->post($client, 'cms_redirects_update', $this->url(self::REDIRECT), ['action' => 'page', 'page_id' => self::OTHER]);

        self::assertResponseRedirects('/redirects/'.self::REDIRECT, Response::HTTP_SEE_OTHER);
        self::assertSame([['key' => 'cms.notices.redirect.saved', 'parameters' => []]], self::successNotices($client));
        self::assertSame(self::OTHER, $this->stored()?->target()->resource()?->id());
        self::assertSame(['cms.redirect.saved', 2, self::OTHER], [$this->lastEvent()->name, $this->lastEvent()->body['revision'], $this->lastEvent()->body['target_id']]);
    }

    public function testItSaysTheAddressIsGoneWhenTold(): void
    {
        $client = $this->client();

        $this->post($client, 'cms_redirects_update', $this->url(self::REDIRECT), ['action' => 'gone']);

        self::assertNull($this->stored()?->target()->resource());
    }

    public function testItGoesBackToTheEditorAndChangesNothingWhenTheTargetIsOffTheSite(): void
    {
        $client = $this->client();

        $this->post($client, 'cms_redirects_update', $this->url(self::REDIRECT), ['action' => 'page', 'page_id' => self::OFF_SITE]);

        self::assertResponseRedirects('http://cms.blogautista.localhost/redirects/'.self::REDIRECT, Response::HTTP_SEE_OTHER);
        self::assertSame([['key' => 'errors.redirect.target_off_site', 'parameters' => []]], self::errorNotices($client));
        self::assertSame(self::PAGE, $this->stored()?->target()->resource()?->id());
    }

    public function testItDeletesARedirectAndTellsTheSite(): void
    {
        $client = $this->client();

        $this->post($client, 'cms_redirects_delete', $this->url(self::REDIRECT).'/delete');

        self::assertResponseRedirects('/redirects', Response::HTTP_SEE_OTHER);
        self::assertSame([['key' => 'cms.notices.redirect.deleted', 'parameters' => []]], self::successNotices($client));
        self::assertNull($this->stored());
        self::assertSame(['cms.redirect.deleted', 2], [$this->lastEvent()->name, $this->lastEvent()->body['revision']]);
    }

    /**
     * @return iterable<string, array{string, string, array<string, string>}>
     */
    public static function changes(): iterable
    {
        yield 'leading it elsewhere' => ['cms_redirects_update', '', ['action' => 'gone']];
        yield 'deleting it' => ['cms_redirects_delete', '/delete', []];
    }

    /**
     * @param array<string, string> $form
     */
    #[DataProvider('changes')]
    public function testItFindsNoRedirectToChangeByAnIdOfNone(string $route, string $suffix, array $form): void
    {
        $client = $this->client();

        $this->post($client, $route, $this->url(self::UNKNOWN).$suffix, $form);

        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    public function testItHoldsTheAddressAndTheNewTargetWhileItLeadsTheRedirectElsewhere(): void
    {
        $this->client();
        $mutualExclusion = new RecordingMutualExclusion();

        new RedirectRetargeter(self::REDIRECT, new TargetInput('page', self::OTHER))(
            self::getContainer()->get(RedirectRepository::class),
            self::getContainer()->get(EventBus::class),
            self::getContainer()->get(SiteResources::class),
            $mutualExclusion,
        );

        self::assertSame(['address:/vieja.html', 'page:'.self::OTHER], $mutualExclusion->lastHeld());
    }

    public function testItHoldsTheAddressWhileItDeletesTheRedirect(): void
    {
        $this->client();
        $mutualExclusion = new RecordingMutualExclusion();

        new RedirectDeleter(self::REDIRECT)(self::getContainer()->get(RedirectRepository::class), self::getContainer()->get(EventBus::class), $mutualExclusion);

        self::assertSame(['address:/vieja.html'], $mutualExclusion->lastHeld());
    }

    /**
     * A client whose kernel holds two pages on the site, one off it, and a
     * redirect from /vieja.html to the first.
     */
    private function client(): KernelBrowser
    {
        $client = self::createAuthenticatedClient();
        $client->disableReboot();
        $pages = self::getContainer()->get(PageRepository::class);
        $pages->save(PageMother::published(id: self::PAGE, title: 'Una', slug: 'una'));
        $pages->save(PageMother::published(id: self::OTHER, title: 'Otra', slug: 'otra'));
        $pages->save(PageMother::unpublished(id: self::OFF_SITE, title: 'Retirada', slug: 'retirada'));
        new RedirectCreator(self::REDIRECT, 'https://'.self::HOST.'/vieja.html', new TargetInput('page', self::PAGE), self::HOST)(
            self::getContainer()->get(RedirectRepository::class),
            self::getContainer()->get(EventBus::class),
            self::getContainer()->get(SiteResources::class),
            self::getContainer()->get(MutualExclusion::class),
        );

        return $client;
    }

    private function url(string $id): string
    {
        return 'http://cms.blogautista.localhost/redirects/'.$id;
    }

    private function stored(): ?Redirect
    {
        return self::getContainer()->get(RedirectRepository::class)->find(new RedirectId(self::REDIRECT));
    }

    private function lastEvent(): DomainEventMessage
    {
        $events = $this->queuedEvents();
        self::assertNotSame([], $events);

        return $events[array_key_last($events)];
    }
}
