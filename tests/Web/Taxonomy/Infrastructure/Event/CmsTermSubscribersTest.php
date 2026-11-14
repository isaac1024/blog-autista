<?php

declare(strict_types=1);

namespace Web\Tests\Taxonomy\Infrastructure\Event;

use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use Shared\Infrastructure\Event\DomainEventMessage;
use Shared\Infrastructure\Event\DomainEventMessageHandler;
use Shared\Infrastructure\Event\EventRetry;
use Shared\Tests\Domain\ClockMother;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Web\Taxonomy\Domain\CmsTermPublished;
use Web\Taxonomy\Domain\Term;
use Web\Taxonomy\Domain\TermId;
use Web\Taxonomy\Domain\TermRepository;

/**
 * The site takes the terms the CMS publishes and unpublishes, as the worker
 * hands it their events.
 */
final class CmsTermSubscribersTest extends KernelTestCase
{
    private const string TERM = '01a1da50-2b7c-7d01-8a3f-5c2e9b1d0a01';

    private const string OTHER_TERM = '01a1da50-2b7c-7d01-8a3f-5c2e9b1d0a02';

    #[DataProvider('taxonomies')]
    public function testItPutsOnTheSiteTheTermTheCmsPublishes(string $taxonomy): void
    {
        $body = self::body(taxonomy: $taxonomy);

        $this->handle($body);

        self::assertEquals(Term::publish(CmsTermPublished::fromPrimitives(self::TERM, $body + ['revision' => 1])), $this->storedTerm());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function taxonomies(): iterable
    {
        yield 'a section' => ['section'];
        yield 'a tag' => ['tag'];
    }

    public function testItReplacesTheTermWithTheVersionTheCmsPublishesLater(): void
    {
        $this->handle(self::body());

        $this->handle(self::body(name: 'Día a día', slug: 'dia-a-dia', metaDescription: ''), 2);

        self::assertSame(['Día a día', 'dia-a-dia', null], [$this->storedTerm()?->name(), $this->storedTerm()?->slug(), $this->storedTerm()?->metaDescription()]);
    }

    public function testItKeepsTheTermWhenAnEarlierVersionArrivesLate(): void
    {
        $this->handle(self::body(name: 'Día a día'), 2);

        $this->handle(self::body(name: 'Vida diaria'));

        self::assertSame('Día a día', $this->storedTerm()?->name());
    }

    public function testItWaitsForAnotherTermOfTheTaxonomyToFreeTheAddressBeforeTakingIt(): void
    {
        $this->handle(self::body(), id: self::OTHER_TERM);

        try {
            $this->handle(self::body());
            self::fail('A term was put at the address of another one.');
        } catch (EventRetry $retry) {
            self::assertFalse($retry->forceRetry(), 'the retries stay within those of the transport');
            self::assertSame(60_000, $retry->getRetryDelay(), 'it waits a minute, for a cron run to take the event that frees the address');
            self::assertNull($this->storedTerm());
        }

        $this->dispatch('cms.term.unpublished', self::OTHER_TERM, ['revision' => 2]);
        $this->handle(self::body());

        self::assertSame('vida-diaria', $this->storedTerm()?->slug());
    }

    /**
     * Each taxonomy serves its terms at addresses of its own.
     */
    public function testItTakesAnAddressATermOfAnotherTaxonomyHas(): void
    {
        $this->handle(self::body(taxonomy: 'tag'), id: self::OTHER_TERM);

        $this->handle(self::body(taxonomy: 'section'));

        self::assertSame('vida-diaria', $this->storedTerm()?->slug());
    }

    public function testItTakesOffTheSiteTheTermTheCmsUnpublishes(): void
    {
        $this->handle(self::body());

        $this->dispatch('cms.term.unpublished', self::TERM, ['revision' => 2]);

        self::assertNull($this->storedTerm());
    }

    /**
     * A term already gone stays gone, so the same event can be taken twice.
     */
    public function testItTakesAnUnpublicationOfATermNotOnTheSite(): void
    {
        $this->dispatch('cms.term.unpublished', self::TERM, ['revision' => 2]);

        self::assertNull($this->storedTerm());
    }

    public function testItKeepsTheTermWhenAnEarlierUnpublicationArrivesLate(): void
    {
        $this->handle(self::body(), 3);

        $this->dispatch('cms.term.unpublished', self::TERM, ['revision' => 2]);

        self::assertNotNull($this->storedTerm());
    }

    /**
     * Whatever the body lacks of what the CMS sends, the message is refused.
     */
    #[DataProvider('malformedBodies')]
    public function testItRefusesAPublicationWhoseBodyTheCmsNeverSends(string $key, mixed $value): void
    {
        $body = self::body();
        $body[$key] = $value;

        try {
            $this->handle($body);
            self::fail('A publication the CMS never sends was taken.');
        } catch (\Throwable) {
            self::assertNull($this->storedTerm());
        }
    }

    /**
     * @return iterable<string, array{string, mixed}>
     */
    public static function malformedBodies(): iterable
    {
        yield 'an unknown taxonomy' => ['taxonomy', 'author'];
        yield 'a name that is no text' => ['name', 3];
        yield 'an unknown robots directive' => ['robots', 'noindex'];
        yield 'a date that is no date' => ['first_published_at', 'ayer'];
    }

    /**
     * @return array<string, mixed>
     */
    private static function body(string $taxonomy = 'section', string $name = 'Vida diaria', string $slug = 'vida-diaria', string $metaDescription = 'Artículos sobre la vida diaria.'): array
    {
        return [
            'taxonomy' => $taxonomy,
            'name' => $name,
            'slug' => $slug,
            'meta_description' => $metaDescription,
            'robots' => 'index,follow',
            'first_published_at' => ClockMother::now()->format(\DATE_ATOM),
            'last_published_at' => ClockMother::now()->format(\DATE_ATOM),
        ];
    }

    /**
     * @param array<string, mixed> $body
     */
    private function handle(array $body, int $revision = 1, string $id = self::TERM): void
    {
        $body['revision'] = $revision;

        $this->dispatch('cms.term.published', $id, $body);
    }

    /**
     * @param array<string, mixed> $body
     */
    private function dispatch(string $name, string $id, array $body): void
    {
        // As the worker does after each message, so the projection reads
        // what it keeps from the database, not from Doctrine's memory.
        self::getContainer()->get(EntityManagerInterface::class)->clear();
        self::getContainer()->get(DomainEventMessageHandler::class)(new DomainEventMessage($name, $id, $body));
    }

    private function storedTerm(): ?Term
    {
        self::getContainer()->get(EntityManagerInterface::class)->clear();

        return self::getContainer()->get(TermRepository::class)->find(new TermId(self::TERM));
    }
}
