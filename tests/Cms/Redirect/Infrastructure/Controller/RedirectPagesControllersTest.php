<?php

declare(strict_types=1);

namespace Cms\Tests\Redirect\Infrastructure\Controller;

use Cms\Page\Domain\PageRepository;
use Cms\Redirect\Application\RedirectCreator;
use Cms\Redirect\Application\TargetInput;
use Cms\Redirect\Domain\RedirectRepository;
use Cms\Redirect\Domain\SiteResources;
use Cms\Tests\Page\Domain\PageMother;
use Cms\Tests\Shared\Infrastructure\CmsWebTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use Shared\Domain\MutualExclusion;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\HttpFoundation\Response;

/**
 * What the list of redirects, the form that creates one and the editor of
 * one show is told by RedirectListTest, NewRedirectFormTest and
 * RedirectEditorTest; here, that each is served, and painted, for every
 * shape it takes.
 */
final class RedirectPagesControllersTest extends CmsWebTestCase
{
    private const string BASE = 'http://cms.blogautista.localhost/redirects';
    private const string HOST = 'www.blogautista.localhost';
    private const string TO_PAGE = '0196f3a0-0000-7000-8000-0000000000e1';
    private const string GONE = '0196f3a0-0000-7000-8000-0000000000e2';
    private const string FROM_ORIGIN = '0196f3a0-0000-7000-8000-0000000000e3';
    private const string UNKNOWN = '0196f3a0-0000-7000-8000-0000000000ef';
    private const string PAGE = '0196f3a0-0000-7000-8000-0000000000a1';
    private const string OFF_SITE = '0196f3a0-0000-7000-8000-0000000000a3';

    /**
     * @return iterable<string, array{string}>
     */
    public static function pages(): iterable
    {
        yield 'the list' => [''];
        yield 'the form that creates one' => ['/new'];
        yield 'the editor of one' => ['/'.self::TO_PAGE];
    }

    #[DataProvider('pages')]
    public function testItRedirectsAnonymousVisitorsToTheLoginForm(string $path): void
    {
        $client = self::createClient();

        $client->request('GET', self::BASE.$path);

        self::assertResponseRedirects('http://cms.blogautista.localhost/login');
    }

    public function testItOpensTheListWithoutAnyRedirect(): void
    {
        $client = self::createAuthenticatedClient();

        $client->request('GET', self::BASE);

        self::assertResponseIsSuccessful();
        self::assertRouteSame('cms_redirects');
    }

    /**
     * Every shape the list takes is painted, narrowed to a resource or not;
     * what isn't a resource leaves it searching.
     */
    #[DataProvider('listQueries')]
    public function testItOpensTheListOfRedirects(string $query): void
    {
        $client = $this->client();

        $client->request('GET', self::BASE.$query);

        self::assertResponseIsSuccessful();
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function listQueries(): iterable
    {
        yield 'every redirect' => [''];
        yield 'a search' => ['?source=%20vieja%20'];
        yield 'a page past the end' => ['?page=9'];
        yield 'those leading to a page' => ['?target=page:'.self::PAGE];
        yield 'a resource without an id' => ['?target=page'];
        yield 'a resource of no kind' => ['?target=video:'.self::PAGE];
        yield 'a resource with no id of one' => ['?target=page:sobre-mi'];
    }

    public function testItAnswersNotFoundForAnInvalidPageNumberOfTheList(): void
    {
        $client = self::createAuthenticatedClient();

        $client->request('GET', self::BASE.'?page=0');

        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    /**
     * The form is painted with no address, one written, and one given.
     */
    #[DataProvider('newQueries')]
    public function testItOpensTheFormThatCreatesARedirect(string $query): void
    {
        $client = self::createAuthenticatedClient();

        $client->request('GET', self::BASE.'/new'.$query);

        self::assertResponseIsSuccessful();
        self::assertRouteSame('cms_redirects_new');
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function newQueries(): iterable
    {
        $source = urlencode('https://'.self::HOST.'/retirada.html');

        yield 'no address' => [''];
        yield 'an address written' => ['?source='.$source];
        yield 'an address given' => ['?source='.$source.'&fixed=1'];
        yield 'no address, asked to be given' => ['?fixed=1'];
    }

    /**
     * The editor is painted for a redirect leading to a page, one gone, and
     * one at the last address of a page off the site.
     */
    #[DataProvider('redirects')]
    public function testItOpensTheEditorOfARedirect(string $id): void
    {
        $client = $this->client();

        $client->request('GET', self::BASE.'/'.$id);

        self::assertResponseIsSuccessful();
        self::assertRouteSame('cms_redirects_edit', ['id' => $id]);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function redirects(): iterable
    {
        yield 'leading to a page' => [self::TO_PAGE];
        yield 'gone' => [self::GONE];
        yield 'at the last address of a page off the site' => [self::FROM_ORIGIN];
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function unknownRedirects(): iterable
    {
        yield 'an id of none' => [self::UNKNOWN];
        yield 'no id at all' => ['vieja'];
    }

    #[DataProvider('unknownRedirects')]
    public function testItFindsNoRedirectToEditByAnIdOfNone(string $id): void
    {
        $client = self::createAuthenticatedClient();

        $client->request('GET', self::BASE.'/'.$id);

        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    /**
     * A client whose kernel holds a page on the site, one off it, and a
     * redirect to the first, one gone, and one at the last address of the
     * second.
     */
    private function client(): KernelBrowser
    {
        $client = self::createAuthenticatedClient();
        $client->disableReboot();
        $pages = self::getContainer()->get(PageRepository::class);
        $pages->save(PageMother::published(id: self::PAGE, title: 'Una', slug: 'una'));
        $pages->save(PageMother::unpublished(id: self::OFF_SITE, title: 'Retirada', slug: 'retirada'));

        foreach ([
            self::TO_PAGE => ['/vieja.html', new TargetInput('page', self::PAGE)],
            self::GONE => ['/borrada.html', new TargetInput(null, null)],
            self::FROM_ORIGIN => ['/retirada.html', new TargetInput('page', self::PAGE)],
        ] as $id => [$path, $target]) {
            new RedirectCreator($id, 'https://'.self::HOST.$path, $target, self::HOST)(
                self::getContainer()->get(RedirectRepository::class),
                self::getContainer()->get(SiteResources::class),
                self::getContainer()->get(MutualExclusion::class),
            );
        }

        return $client;
    }
}
