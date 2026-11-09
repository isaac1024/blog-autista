<?php

declare(strict_types=1);

namespace Web\Tests\Analytics\Infrastructure\Controller;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\ErrorHandler\BufferingLogger;
use Web\Analytics\Infrastructure\Controller\ClientAddressWarning;

final class ClientAddressWarningTest extends TestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function proxyAddresses(): iterable
    {
        yield 'loopback' => ['127.0.0.1'];
        yield 'a private network' => ['10.0.0.5'];
        yield 'another private network' => ['192.168.1.20'];
        yield 'IPv6 loopback' => ['::1'];
        yield 'an IPv6 private network' => ['fd00::1'];
    }

    #[DataProvider('proxyAddresses')]
    public function testItWarnsInProductionWhenAVisitComesFromAProxy(string $address): void
    {
        $logger = new BufferingLogger();

        new ClientAddressWarning($logger, 'prod')($address);

        $logs = $logger->cleanLogs();
        self::assertCount(1, $logs);
        $log = $logs[0];
        self::assertIsArray($log);
        self::assertSame('warning', $log[0] ?? null);
        $message = $log[1] ?? null;
        self::assertIsString($message);
        self::assertStringNotContainsString($address, $message);
    }

    public function testItSaysNothingOfAReaderOnTheInternet(): void
    {
        $logger = new BufferingLogger();

        new ClientAddressWarning($logger, 'prod')('203.0.113.7');
        new ClientAddressWarning($logger, 'prod')('2a01:4f8::1');
        new ClientAddressWarning($logger, 'prod')(null);

        self::assertSame([], $logger->cleanLogs());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function quietEnvironments(): iterable
    {
        yield 'development' => ['dev'];
        yield 'tests' => ['test'];
    }

    /**
     * Locally every visit comes through the proxies of Docker.
     */
    #[DataProvider('quietEnvironments')]
    public function testItSaysNothingOutOfProduction(string $environment): void
    {
        $logger = new BufferingLogger();

        new ClientAddressWarning($logger, $environment)('127.0.0.1');

        self::assertSame([], $logger->cleanLogs());
    }
}
