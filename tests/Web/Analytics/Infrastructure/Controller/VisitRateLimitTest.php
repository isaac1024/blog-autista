<?php

declare(strict_types=1);

namespace Web\Tests\Analytics\Infrastructure\Controller;

use PHPUnit\Framework\TestCase;
use Shared\Tests\Domain\ClockMother;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;
use Symfony\Component\RateLimiter\LimiterInterface;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\RateLimiter\Storage\InMemoryStorage;
use Web\Analytics\Domain\DailySalt;
use Web\Analytics\Infrastructure\Controller\VisitRateLimit;
use Web\Tests\Analytics\Domain\VisitMother;

/**
 * A reader may send so many visits a while, told by their address, which
 * the limiter never keeps. The limit here is two a minute.
 */
final class VisitRateLimitTest extends TestCase
{
    use ClockSensitiveTrait;

    private const string ADDRESS = '203.0.113.7';

    #[\Override]
    protected function setUp(): void
    {
        self::mockTime(ClockMother::now());
    }

    public function testItLimitsTheVisitsOfOneReaderAndNoOther(): void
    {
        $limit = new VisitRateLimit(new RateLimiterFactory(['id' => 'web_visit', 'policy' => 'fixed_window', 'limit' => 2, 'interval' => '1 minute'], new InMemoryStorage()), self::salt());

        self::assertSame(
            [true, true, false, true],
            [$limit->accepts(self::ADDRESS), $limit->accepts(self::ADDRESS), $limit->accepts(self::ADDRESS), $limit->accepts('203.0.113.8')],
        );
    }

    /**
     * Its key is the address hashed with the salt of the day: never the
     * address itself.
     */
    public function testItKeysTheReaderByTheAddressHashedWithTheSaltOfTheDay(): void
    {
        $limiter = self::createStub(LimiterInterface::class);
        $limiter->method('consume')->willReturn(new \Symfony\Component\RateLimiter\RateLimit(1, new \DateTimeImmutable(), true, 2));
        $factory = $this->createMock(RateLimiterFactoryInterface::class);
        $factory->expects(self::once())
            ->method('create')
            ->with(hash_hmac('sha256', self::ADDRESS, 'salt-'.VisitMother::DAY))
            ->willReturn($limiter);

        self::assertTrue(new VisitRateLimit($factory, self::salt())->accepts(self::ADDRESS));
    }

    private static function salt(): DailySalt
    {
        $salt = self::createStub(DailySalt::class);
        $salt->method('forDay')->willReturnCallback(VisitMother::salt(...));

        return $salt;
    }
}
