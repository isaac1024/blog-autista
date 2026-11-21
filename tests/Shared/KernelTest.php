<?php

declare(strict_types=1);

namespace Shared\Tests;

use Shared\Infrastructure\Kernel;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class KernelTest extends KernelTestCase
{
    public function testTheApplicationBootsWithTheSharedKernel(): void
    {
        self::bootKernel();

        $kernel = self::$kernel;
        self::assertInstanceOf(Kernel::class, $kernel);
        self::assertSame('test', $kernel->getEnvironment());
    }

    public function testItKeepsTheTimeInUtcWhateverTimeZoneTheServerSays(): void
    {
        date_default_timezone_set('Europe/Berlin');

        self::bootKernel();

        self::assertSame('UTC', date_default_timezone_get());
        self::assertSame('UTC', new \DateTimeImmutable()->getTimezone()->getName());
    }
}
