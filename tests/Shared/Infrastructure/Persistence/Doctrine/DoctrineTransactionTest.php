<?php

declare(strict_types=1);

namespace Shared\Tests\Infrastructure\Persistence\Doctrine;

use Doctrine\DBAL\Connection;
use Shared\Domain\Transaction;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * A change made of several saves is kept whole, or not at all. What the
 * work saves here are rows of a table of the test's own, kept for the
 * connection alone.
 */
final class DoctrineTransactionTest extends KernelTestCase
{
    #[\Override]
    protected function setUp(): void
    {
        // The connection outlives each test, and its temporary tables with it.
        $this->connection()->executeStatement('CREATE TEMPORARY TABLE IF NOT EXISTS transaction_test (value VARCHAR(20) NOT NULL)');
        $this->connection()->executeStatement('DELETE FROM transaction_test');
    }

    public function testItKeepsEverythingTheWorkSavedAndGivesWhatItReturns(): void
    {
        $result = $this->transaction()->run(function (): string {
            $this->save('primera');
            $this->save('segunda');

            return 'hecho';
        });

        self::assertSame(['hecho', ['primera', 'segunda']], [$result, $this->saved()]);
    }

    public function testItKeepsNothingTheWorkSavedWhenItThrowsAndLetsTheExceptionGoOn(): void
    {
        $failure = new \RuntimeException('Refused after the first save.');
        $thrown = null;

        try {
            $this->transaction()->run(function () use ($failure): void {
                $this->save('primera');

                throw $failure;
            });
        } catch (\RuntimeException $exception) {
            $thrown = $exception;
        }

        self::assertSame([$failure, []], [$thrown, $this->saved()]);
    }

    private function save(string $value): void
    {
        $this->connection()->executeStatement('INSERT INTO transaction_test (value) VALUES (?)', [$value]);
    }

    /**
     * @return list<string>
     */
    private function saved(): array
    {
        return array_map(
            static fn (mixed $value): string => \is_string($value) ? $value : '',
            $this->connection()->fetchFirstColumn('SELECT value FROM transaction_test ORDER BY value'),
        );
    }

    private function transaction(): Transaction
    {
        return self::getContainer()->get(Transaction::class);
    }

    private function connection(): Connection
    {
        return self::getContainer()->get(Connection::class);
    }
}
