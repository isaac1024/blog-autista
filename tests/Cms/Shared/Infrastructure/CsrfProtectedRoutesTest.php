<?php

declare(strict_types=1);

namespace Cms\Tests\Shared\Infrastructure;

use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Http\Attribute\IsCsrfTokenValid;

/**
 * Every form the CMS takes is checked against the token of its route: an
 * action that changes something and forgets the attribute fails here, not
 * in production.
 */
final class CsrfProtectedRoutesTest extends KernelTestCase
{
    /**
     * Signing in and out are the firewall's, which checks their tokens itself.
     */
    private const array CHECKED_BY_THE_FIREWALL = ['cms_login_check', 'cms_logout'];

    public function testEveryFormOfTheCmsIsCheckedAgainstTheTokenOfItsRoute(): void
    {
        $checked = 0;

        foreach (self::getContainer()->get(RouterInterface::class)->getRouteCollection() as $name => $route) {
            if (!str_starts_with($name, 'cms_') || !\in_array('POST', $route->getMethods(), true) || \in_array($name, self::CHECKED_BY_THE_FIREWALL, true)) {
                continue;
            }

            $controller = $route->getDefault('_controller');
            self::assertIsString($controller, $name);
            $method = \ReflectionMethod::createFromMethodName(str_contains($controller, '::') ? $controller : $controller.'::__invoke');
            $ids = array_map(static fn (\ReflectionAttribute $attribute): mixed => $attribute->newInstance()->id, $method->getAttributes(IsCsrfTokenValid::class));

            self::assertSame([$name], $ids, \sprintf('The route "%s" is checked against its own token.', $name));
            ++$checked;
        }

        self::assertGreaterThan(40, $checked);
    }
}
