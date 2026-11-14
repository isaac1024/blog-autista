<?php

declare(strict_types=1);

namespace Cms\Tests\Taxonomy\Infrastructure\Controller;

use Cms\Taxonomy\Domain\Taxonomy;
use Cms\Taxonomy\Domain\Term;
use Cms\Tests\Taxonomy\Domain\TermIdMother;
use Cms\Tests\Taxonomy\Domain\TermMother;
use PHPUnit\Framework\Attributes\DataProvider;
use Shared\Tests\Domain\ClockMother;
use Shared\Tests\Infrastructure\Event\QueuedDomainEvents;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;
use Symfony\Component\HttpFoundation\Response;

/**
 * Publishing, unpublishing, discarding and deleting a term, each from its
 * editor, which it goes back to, but deleting, which goes to the list.
 */
final class TermPublicationControllersTest extends TermControllerTestCase
{
    use ClockSensitiveTrait;
    use QueuedDomainEvents;

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();
        self::mockTime(ClockMother::NOW);
    }

    #[DataProvider('actions')]
    public function testItRedirectsAnonymousVisitorsToTheLoginForm(string $route, string $action): void
    {
        $client = self::createClient();
        $term = $this->saveTerm(TermMother::published(id: TermIdMother::random()->value(), publishedName: 'Antes'));

        $this->post($client, $route, self::HOST.'/sections/'.$term->id()->value().'/'.$action);

        self::assertResponseRedirects(self::HOST.'/login');
        self::assertEquals($term, $this->storedTerm($term));
        self::assertSame([], $this->queuedEvents());
    }

    #[DataProvider('actions')]
    public function testItRespondsNotFoundForATermOfAnotherTaxonomy(string $route, string $action): void
    {
        $client = self::createAuthenticatedClient();
        $term = $this->saveTerm(TermMother::published(id: TermIdMother::random()->value(), publishedName: 'Antes'));

        $this->post($client, $route, self::HOST.'/tags/'.$term->id()->value().'/'.$action);

        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
        self::assertEquals($term, $this->storedTerm($term));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function actions(): iterable
    {
        yield 'publishing' => ['cms_terms_publish', 'publish'];
        yield 'unpublishing' => ['cms_terms_unpublish', 'unpublish'];
        yield 'discarding' => ['cms_terms_discard', 'discard'];
        yield 'deleting' => ['cms_terms_delete', 'delete'];
    }

    public function testItPublishesTheTermAndTellsTheSite(): void
    {
        $client = self::createAuthenticatedClient();
        $id = TermIdMother::random()->value();
        $term = $this->saveTerm(TermMother::create(id: $id, taxonomy: Taxonomy::Tag, metaDescription: TermMother::META_DESCRIPTION));

        $this->post($client, 'cms_terms_publish', self::HOST.'/tags/'.$id.'/publish');

        self::assertResponseRedirects('/tags/'.$id, Response::HTTP_SEE_OTHER);
        self::assertSame([['key' => 'cms.tags.notices.published', 'parameters' => []]], self::successNotices($client));
        self::assertEquals(TermMother::published(id: $id, taxonomy: Taxonomy::Tag), $this->storedTerm($term));
        $events = $this->queuedEvents();
        self::assertCount(1, $events);
        self::assertSame(['cms.term.published', $id, 'tag'], [$events[0]->name, $events[0]->aggregateId, $events[0]->body['taxonomy']]);
    }

    public function testItGoesBackAndTellsWhatTheTermLacksToBePublished(): void
    {
        $client = self::createAuthenticatedClient();
        $id = TermIdMother::random()->value();
        $term = $this->saveTerm(TermMother::create(id: $id));

        $this->post($client, 'cms_terms_publish', self::HOST.'/sections/'.$id.'/publish');

        self::assertResponseRedirects(self::HOST.'/sections/'.$id, Response::HTTP_SEE_OTHER);
        self::assertSame([['key' => 'errors.term.publication.without_meta_description', 'parameters' => []]], self::errorNotices($client));
        self::assertEquals($term, $this->storedTerm($term));
        self::assertSame([], $this->queuedEvents());
    }

    public function testItUnpublishesTheTermAndTellsTheSite(): void
    {
        $client = self::createAuthenticatedClient();
        $id = TermIdMother::random()->value();
        $term = $this->saveTerm(TermMother::published(id: $id));

        $this->post($client, 'cms_terms_unpublish', self::HOST.'/sections/'.$id.'/unpublish');

        self::assertResponseRedirects('/sections/'.$id, Response::HTTP_SEE_OTHER);
        self::assertSame([['key' => 'cms.sections.notices.unpublished', 'parameters' => []]], self::successNotices($client));
        self::assertEquals(TermMother::unpublished(id: $id), $this->storedTerm($term));
        self::assertSame(['cms.term.unpublished'], array_map(static fn ($event): string => $event->name, $this->queuedEvents()));
    }

    public function testItDiscardsTheChangesOfTheTerm(): void
    {
        $client = self::createAuthenticatedClient();
        $id = TermIdMother::random()->value();
        $term = $this->saveTerm(TermMother::published(id: $id, name: 'Día a día', publishedName: TermMother::NAME));

        $this->post($client, 'cms_terms_discard', self::HOST.'/sections/'.$id.'/discard');

        self::assertResponseRedirects('/sections/'.$id, Response::HTTP_SEE_OTHER);
        self::assertSame([['key' => 'cms.sections.notices.discarded', 'parameters' => []]], self::successNotices($client));
        self::assertEquals(TermMother::published(id: $id), $this->storedTerm($term));
    }

    #[DataProvider('termsOffTheSite')]
    public function testItDeletesATermOffTheSiteAndGoesBackToTheList(Term $term): void
    {
        $client = self::createAuthenticatedClient();
        $this->saveTerm($term);

        $this->post($client, 'cms_terms_delete', self::HOST.'/tags/'.$term->id()->value().'/delete');

        self::assertResponseRedirects('/tags', Response::HTTP_SEE_OTHER);
        self::assertSame([['key' => 'cms.tags.notices.deleted', 'parameters' => []]], self::successNotices($client));
        self::assertNull($this->storedTerm($term));
    }

    /**
     * @return iterable<string, array{Term}>
     */
    public static function termsOffTheSite(): iterable
    {
        yield 'a draft' => [TermMother::create(id: TermIdMother::random()->value(), taxonomy: Taxonomy::Tag)];
        yield 'an unpublished term' => [TermMother::unpublished(id: TermIdMother::random()->value(), taxonomy: Taxonomy::Tag)];
    }

    public function testItGoesBackAndKeepsAPublishedTerm(): void
    {
        $client = self::createAuthenticatedClient();
        $id = TermIdMother::random()->value();
        $term = $this->saveTerm(TermMother::published(id: $id));

        $this->post($client, 'cms_terms_delete', self::HOST.'/sections/'.$id.'/delete');

        self::assertResponseRedirects(self::HOST.'/sections/'.$id, Response::HTTP_SEE_OTHER);
        self::assertSame([['key' => 'errors.term.publication.published_term', 'parameters' => []]], self::errorNotices($client));
        self::assertEquals($term, $this->storedTerm($term));
    }

    /**
     * What can't be done to it is told on its editor, which it goes back to.
     */
    #[DataProvider('refusedActions')]
    public function testItGoesBackAndTellsWhatCannotBeDone(string $route, string $action, string $error): void
    {
        $client = self::createAuthenticatedClient();
        $id = TermIdMother::random()->value();
        $record = $this->saveTerm(TermMother::create(id: $id));
        $expected = clone $record;

        $this->post($client, $route, self::HOST.'/sections/'.$id.'/'.$action);

        self::assertResponseRedirects(self::HOST.'/sections/'.$id, Response::HTTP_SEE_OTHER);
        self::assertSame([['key' => 'errors.term.publication.'.$error, 'parameters' => []]], self::errorNotices($client));
        self::assertEquals($expected, $this->storedTerm($record));
    }

    /**
     * @return iterable<string, array{string, string, string}>
     */
    public static function refusedActions(): iterable
    {
        yield 'unpublishing a draft' => ['cms_terms_unpublish', 'unpublish', 'not_published'];
        yield 'discarding the changes of a draft' => ['cms_terms_discard', 'discard', 'no_changes_to_discard'];
    }
}
