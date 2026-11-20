<?php

declare(strict_types=1);

namespace Cms\Tests\Shared\Infrastructure;

use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/*
 * The sessions of the CMS are swept by the app, not by the hosting, and
 * outlive writing a post: a form sent once its session is gone is lost.
 */
final class SessionLifetimeTest extends KernelTestCase
{
    public function testAnIdleSessionLastsEightHours(): void
    {
        self::bootKernel();

        /** @var array<string, mixed> $options */
        $options = self::getContainer()->getParameter('session.storage.options');

        self::assertSame(8 * 3600, $options['gc_maxlifetime']);
    }
}
