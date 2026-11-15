<?php

declare(strict_types=1);

namespace Shared\Tests\Infrastructure\Ui;

use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\ErrorHandler\Exception\FlattenException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Twig\Environment;

/**
 * The error template, which answers when the site can't paint its own page
 * and which `error:dump` copies for Apache to serve when PHP doesn't answer:
 * it paints any error with no request to answer, as the command renders it,
 * and the kernel of the tests, which debugs, would never hand it one.
 */
final class StaticErrorPagesTest extends KernelTestCase
{
    #[DataProvider('statusCodes')]
    public function testItPaintsAnErrorWithNoRequest(int $statusCode): void
    {
        $exception = FlattenException::createFromThrowable(new HttpException($statusCode));

        $html = self::getContainer()->get(Environment::class)->render('@Twig/Exception/error.html.twig', [
            'exception' => $exception,
            'status_code' => $exception->getStatusCode(),
            'status_text' => $exception->getStatusText(),
        ]);

        self::assertNotSame('', $html);
    }

    /**
     * @return iterable<string, array{int}>
     */
    public static function statusCodes(): iterable
    {
        yield 'a failure Apache answers' => [503];
        yield 'a failure PHP answers' => [500];
        yield 'an address with nothing' => [404];
        yield 'an address no longer answered' => [410];
    }
}
