<?php

declare(strict_types=1);

namespace Cms\Tests\Page\Infrastructure\Controller;

use Cms\Page\Domain\PageRepository;
use Cms\Tests\Page\Domain\PageIdMother;
use Cms\Tests\Page\Domain\PageMother;
use Cms\Tests\Shared\Infrastructure\CmsWebTestCase;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * The pages a rich text links to, as the editor shows them when it opens.
 */
final class PageLookupControllerTest extends CmsWebTestCase
{
    private const string URL = 'http://cms.blogautista.localhost/pages/lookup';

    public function testItRedirectsAnonymousVisitorsToTheLoginForm(): void
    {
        $client = self::createClient();

        $client->request('GET', self::URL);

        self::assertResponseRedirects('http://cms.blogautista.localhost/login');
    }

    /**
     * In the order asked, each once; an id of no page, or no id at all, is
     * left out.
     */
    public function testItGivesThePagesWithTheIdsAsked(): void
    {
        $client = self::createAuthenticatedClient();
        $unpublished = PageIdMother::random()->value();
        $published = PageIdMother::random()->value();
        $repository = self::getContainer()->get(PageRepository::class);
        $repository->save(PageMother::unpublished(id: $unpublished, title: 'Contacto', slug: 'contacto'));
        $repository->save(PageMother::published(id: $published, title: 'Sobre mí', slug: 'sobre-mi'));

        // Newest first, against the order they were saved in, and one twice.
        $client->request('GET', self::URL.'?ids='.implode(',', [$published, PageIdMother::random()->value(), 'pagina', '', $unpublished, $published]));

        self::assertResponseIsSuccessful();
        self::assertSame(['pages' => [
            [
                'id' => $published,
                'title' => 'Sobre mí',
                'status' => self::statusLabel('published'),
                'published' => true,
            ],
            [
                'id' => $unpublished,
                'title' => 'Contacto',
                'status' => self::statusLabel('unpublished'),
                'published' => false,
            ],
        ]], self::json($client));
    }

    public function testItGivesNoPageWhenNoneIsAsked(): void
    {
        $client = self::createAuthenticatedClient();

        $client->request('GET', self::URL);

        self::assertResponseIsSuccessful();
        self::assertSame(['pages' => []], self::json($client));
    }

    #[\Override]
    protected static function createAuthenticatedClient(): KernelBrowser
    {
        $client = parent::createAuthenticatedClient();
        $client->disableReboot();

        return $client;
    }

    /**
     * @return array<mixed>
     */
    private static function json(KernelBrowser $client): array
    {
        $data = json_decode((string) $client->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);
        self::assertIsArray($data);

        return $data;
    }

    private static function statusLabel(string $status): string
    {
        return self::getContainer()->get(TranslatorInterface::class)->trans(\sprintf('cms.pages.status.%s.label', $status));
    }
}
