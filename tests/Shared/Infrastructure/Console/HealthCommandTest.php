<?php

declare(strict_types=1);

namespace Shared\Tests\Infrastructure\Console;

use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * As the daily cron job runs it: nothing written when all is well, so
 * konsoleH mails nothing, and a line for each thing gone wrong otherwise.
 * Its day is one no other test writes the log of.
 */
final class HealthCommandTest extends KernelTestCase
{
    use ClockSensitiveTrait;

    /**
     * 09:00 in Madrid, once the nightly count is done.
     */
    private const string NOW = '2031-03-10 08:00:00 UTC';

    private const string LOG_OF_YESTERDAY = '/test-2031-03-09.log';

    #[\Override]
    protected function tearDown(): void
    {
        $log = self::getContainer()->getParameter('kernel.logs_dir').self::LOG_OF_YESTERDAY;
        if (is_file($log)) {
            unlink($log);
        }

        parent::tearDown();
    }

    public function testItSaysNothingWhenAllIsWell(): void
    {
        self::mockTime(self::NOW);
        $this->event('default', '2031-03-10 07:55:00', null);
        $this->event('default', '2031-03-10 07:00:00', '2031-03-10 07:00:30');
        $this->visit('2031-03-10');
        $this->log("[2031-03-09T10:00:00.000000+00:00] request.INFO: Matched route \"web_home\". [] []\n");

        [$exitCode, $output] = $this->health();

        self::assertSame(Command::SUCCESS, $exitCode);
        self::assertSame('', $output);
    }

    public function testItTellsEachThingGoneWrongOnALineOfItsOwn(): void
    {
        self::mockTime(self::NOW);
        $this->event('failed', '2031-03-08 12:00:00', null);
        $this->event('failed', '2031-03-09 12:00:00', null);
        $this->event('default', '2031-03-10 07:40:00', null);
        $this->visit('2031-03-09');
        $this->log(
            "[2031-03-09T10:00:00.000000+00:00] request.INFO: Matched route \"web_home\". [] []\n"
            ."[2031-03-09T11:00:00.000000+00:00] request.CRITICAL: Uncaught PHP Exception RuntimeException: \"Broken\" [] []\n"
            ."[2031-03-09T12:00:00.000000+00:00] app.WARNING: Every reader counts as one. [] []\n",
        );

        [$exitCode, $output] = $this->health();

        self::assertSame(Command::FAILURE, $exitCode);
        self::assertSame([
            '2 event(s) failed for good: the site never showed what they told. See them with messenger:failed:show, and publish again from the CMS what they concerned.',
            '1 event(s) have waited more than 10 minutes: the cron job bin/cron/async is not consuming them.',
            '1 error(s) and 1 warning(s) in test-2031-03-09.log; the first: Uncaught PHP Exception RuntimeException: "Broken" [] []',
            'The visits of 2031-03-09 and after are still uncounted: the cron job bin/cron/scheduler is not running the nightly count.',
        ], explode("\n", rtrim($output)));
    }

    /**
     * Before 03:00 in Madrid the night's count may still be on its way, so
     * only the days before yesterday are overdue.
     */
    public function testItWaitsForTheNightlyCountBeforeTellingYesterdayUncounted(): void
    {
        self::mockTime('2031-03-10 00:30:00 UTC');
        $this->visit('2031-03-09');

        [$exitCode] = $this->health();
        self::assertSame(Command::SUCCESS, $exitCode);

        $this->visit('2031-03-08');

        [$exitCode, $output] = $this->health();
        self::assertSame(Command::FAILURE, $exitCode);
        self::assertStringContainsString('The visits of 2031-03-08 and after', $output);
    }

    /**
     * @return array{int, string}
     */
    private function health(): array
    {
        $tester = new CommandTester(new Application(self::$kernel ?? self::bootKernel())->find('app:health'));
        $exitCode = $tester->execute([]);

        return [$exitCode, $tester->getDisplay()];
    }

    private function event(string $queue, string $availableAt, ?string $deliveredAt): void
    {
        self::getContainer()->get(Connection::class)->insert('messenger_messages', [
            'body' => 'O:8:"stdClass":0:{}', 'headers' => '[]', 'queue_name' => $queue,
            'created_at' => $availableAt, 'available_at' => $availableAt, 'delivered_at' => $deliveredAt,
        ]);
    }

    private function visit(string $day): void
    {
        self::getContainer()->get(Connection::class)->insert('shared_visit', [
            'id' => \sprintf('0196f3a0-0000-7000-8000-%012d', random_int(1, 999_999_999_999)),
            'content_id' => '0196f3a0-0000-7000-8000-0000000000a1',
            'day' => $day, 'channel' => 'direct', 'source' => null, 'reader' => str_repeat('a', 64),
            'device' => 'desktop', 'country' => null, 'content_type' => 'page',
        ]);
    }

    private function log(string $contents): void
    {
        file_put_contents(self::getContainer()->getParameter('kernel.logs_dir').self::LOG_OF_YESTERDAY, $contents);
    }
}
