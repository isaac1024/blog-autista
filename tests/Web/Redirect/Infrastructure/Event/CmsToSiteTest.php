<?php

declare(strict_types=1);

namespace Web\Tests\Redirect\Infrastructure\Event;

use Cms\Page\Domain\PageRepository as CmsPageRepository;
use Cms\Redirect\Application\RedirectCreator;
use Cms\Redirect\Application\RedirectDeleter;
use Cms\Redirect\Application\TargetInput;
use Cms\Redirect\Domain\RedirectRepository as CmsRedirectRepository;
use Cms\Redirect\Domain\SiteResources;
use Cms\Tests\Page\Domain\PageMother;
use Shared\Domain\Event\EventBus;
use Shared\Domain\MutualExclusion;
use Shared\Infrastructure\Event\DomainEventMessageHandler;
use Shared\Tests\Infrastructure\Event\QueuedDomainEvents;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\Transport\InMemory\InMemoryTransport;
use Web\Page\Domain\Page;
use Web\Page\Domain\PageRepository;
use Web\Tests\Page\Domain\CmsPagePublishedMother;

/**
 * A redirect the CMS saves reaches the site through the events it queues,
 * as the worker hands them over, and the site answers its address; once the
 * CMS deletes it, the site answers nothing there.
 */
final class CmsToSiteTest extends WebTestCase
{
    use QueuedDomainEvents;

    private const string PAGE = '0196f3a0-0000-7000-8000-0000000000a1';
    private const string REDIRECT = '0196f3a0-0000-7000-8000-0000000000e1';
    private const string HOST = 'www.blogautista.localhost';

    public function testTheSiteAnswersARedirectTheCmsSavedAndForgetsItOnceDeleted(): void
    {
        $client = $this->client();

        new RedirectCreator(self::REDIRECT, 'https://'.self::HOST.'/vieja.html', new TargetInput('page', self::PAGE), self::HOST)(
            self::getContainer()->get(CmsRedirectRepository::class),
            self::getContainer()->get(EventBus::class),
            self::getContainer()->get(SiteResources::class),
            self::getContainer()->get(MutualExclusion::class),
        );
        $this->deliverQueuedEvents();
        $client->request('GET', 'http://'.self::HOST.'/vieja.html');

        self::assertResponseRedirects('/destino.html', Response::HTTP_MOVED_PERMANENTLY);

        new RedirectDeleter(self::REDIRECT)(
            self::getContainer()->get(CmsRedirectRepository::class),
            self::getContainer()->get(EventBus::class),
            self::getContainer()->get(MutualExclusion::class),
        );
        $this->deliverQueuedEvents();
        $client->request('GET', 'http://'.self::HOST.'/vieja.html');

        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    /**
     * A client whose kernel holds the target page, in the CMS and on the site.
     */
    private function client(): KernelBrowser
    {
        $client = self::createClient();
        $client->disableReboot();
        self::getContainer()->get(CmsPageRepository::class)->save(PageMother::published(id: self::PAGE, title: 'Destino', slug: 'destino'));
        self::getContainer()->get(PageRepository::class)->save(Page::publish(CmsPagePublishedMother::create(id: self::PAGE, title: 'Destino', slug: 'destino')));

        return $client;
    }

    /**
     * Hands the events queued so far to their subscribers, as the worker does,
     * and empties the queue.
     */
    private function deliverQueuedEvents(): void
    {
        $handler = self::getContainer()->get(DomainEventMessageHandler::class);

        foreach ($this->queuedEvents() as $message) {
            $handler($message);
        }

        $transport = self::getContainer()->get('messenger.transport.async');
        self::assertInstanceOf(InMemoryTransport::class, $transport);
        $transport->reset();
    }
}
