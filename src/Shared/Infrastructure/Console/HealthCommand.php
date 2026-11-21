<?php

declare(strict_types=1);

namespace Shared\Infrastructure\Console;

use Doctrine\DBAL\Connection;
use Shared\Domain\Clock;
use Shared\Domain\SiteTimeZone;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Tells what went wrong that nobody would see otherwise, one line each, and
 * nothing when all is well: a daily cron job runs it, and konsoleH mails
 * what a job writes, so silence means no mail.
 *
 * - events that failed for good, which the site never showed;
 * - events waiting for long, as the cron that consumes them stopped;
 * - errors and warnings in the log of yesterday;
 * - visits of days already over still uncounted once the nightly count is
 *   done, as the cron of the schedule stopped.
 */
#[AsCommand(name: 'app:health', description: 'Tells what went wrong that nobody would see otherwise; nothing when all is well')]
final readonly class HealthCommand
{
    /**
     * How long an event may wait to be consumed, in minutes: the cron
     * consumes every minute, and a retry waits a few.
     */
    private const int WAITING_LIMIT = 10;

    /**
     * The hour of Madrid by which the nightly count of visits is done: it
     * runs between 00:10 and 02:10, as summer time moves it.
     */
    private const int NIGHTLY_COUNT_DONE_BY = 3;

    private const string LEVELS = '/^\[[^]]+\] [a-z_]+\.(EMERGENCY|ALERT|CRITICAL|ERROR|WARNING): (.*)$/';

    public function __construct(
        private Connection $connection,
        #[Autowire('%kernel.logs_dir%')]
        private string $logsDirectory,
        #[Autowire('%kernel.environment%')]
        private string $environment,
    ) {}

    public function __invoke(OutputInterface $output): int
    {
        $problems = [...$this->failedEvents(), ...$this->waitingEvents(), ...$this->loggedProblems(), ...$this->uncountedVisits()];

        foreach ($problems as $problem) {
            $output->writeln($problem);
        }

        return [] === $problems ? Command::SUCCESS : Command::FAILURE;
    }

    /**
     * @return list<string>
     */
    private function failedEvents(): array
    {
        $failed = self::count($this->connection->fetchOne("SELECT COUNT(*) FROM messenger_messages WHERE queue_name = 'failed'"));

        return 0 === $failed ? [] : [\sprintf('%d event(s) failed for good: the site never showed what they told. See them with messenger:failed:show, and publish again from the CMS what they concerned.', $failed)];
    }

    /**
     * @return list<string>
     */
    private function waitingEvents(): array
    {
        $waiting = self::count($this->connection->fetchOne(
            "SELECT COUNT(*) FROM messenger_messages WHERE queue_name = 'default' AND delivered_at IS NULL AND available_at < :since",
            ['since' => Clock::now()->setTimezone(new \DateTimeZone('UTC'))->modify(\sprintf('-%d minutes', self::WAITING_LIMIT))->format('Y-m-d H:i:s')],
        ));

        return 0 === $waiting ? [] : [\sprintf('%d event(s) have waited more than %d minutes: the cron job bin/cron/async is not consuming them.', $waiting, self::WAITING_LIMIT)];
    }

    /**
     * The errors and warnings of the log of yesterday, the day the log
     * files are named by, with the first of them.
     *
     * @return list<string>
     */
    private function loggedProblems(): array
    {
        $file = \sprintf('%s/%s-%s.log', $this->logsDirectory, $this->environment, Clock::now()->setTimezone(new \DateTimeZone('UTC'))->modify('-1 day')->format('Y-m-d'));
        $lines = is_file($file) ? file($file, \FILE_IGNORE_NEW_LINES) : [];
        $counts = ['errors' => 0, 'warnings' => 0];
        $first = null;

        foreach (false === $lines ? [] : $lines as $line) {
            if (1 !== preg_match(self::LEVELS, $line, $matches)) {
                continue;
            }

            ++$counts['WARNING' === $matches[1] ? 'warnings' : 'errors'];
            $first ??= mb_strimwidth($matches[2], 0, 200, '…');
        }

        if (null === $first) {
            return [];
        }

        return [\sprintf('%d error(s) and %d warning(s) in %s; the first: %s', $counts['errors'], $counts['warnings'], basename($file), $first)];
    }

    /**
     * @return list<string>
     */
    private function uncountedVisits(): array
    {
        $now = Clock::now()->setTimezone(SiteTimeZone::zone());
        $today = $now->format('Y-m-d');
        $due = $now >= $now->setTime(self::NIGHTLY_COUNT_DONE_BY, 0) ? $today : $now->modify('-1 day')->format('Y-m-d');
        $oldest = $this->connection->fetchOne('SELECT MIN(day) FROM shared_visit WHERE day < :due', ['due' => $due]);

        return \is_string($oldest) ? [\sprintf('The visits of %s and after are still uncounted: the cron job bin/cron/scheduler is not running the nightly count.', $oldest)] : [];
    }

    /**
     * A count the database answered, as a number.
     */
    private static function count(mixed $value): int
    {
        return is_numeric($value) ? (int) $value : throw new \UnexpectedValueException('The database answered no count.');
    }
}
