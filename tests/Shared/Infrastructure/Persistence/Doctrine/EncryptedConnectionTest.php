<?php

declare(strict_types=1);

namespace Shared\Tests\Infrastructure\Persistence\Doctrine;

use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * The app speaks to the database over TLS, as on Hetzner, where the
 * database server is another machine.
 */
final class EncryptedConnectionTest extends KernelTestCase
{
    public function testItSpeaksToTheDatabaseOverTls(): void
    {
        self::bootKernel();

        $cipher = self::getContainer()->get(Connection::class)->fetchOne(
            "SELECT VARIABLE_VALUE FROM information_schema.SESSION_STATUS WHERE VARIABLE_NAME = 'SSL_CIPHER'",
        );

        self::assertIsString($cipher);
        self::assertNotSame('', $cipher);
    }
}
