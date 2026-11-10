<?php

declare(strict_types=1);

namespace Shared\Tests\Infrastructure\Ui;

use PHPUnit\Framework\Assert;
use Shared\Infrastructure\Ui\Page\Page;
use Shared\Infrastructure\Ui\Page\PageComponent;

/**
 * Finds the components of a composed page by what they are, not by where
 * they sit, so a test asks whether a page offers something and doesn't break
 * when the page is laid out anew.
 */
final class PageComponents
{
    /**
     * Every component with the name, at any depth of the header, main or
     * footer of a page, or within a component, whose props include those
     * given, in the order they are painted.
     *
     * @param array<string, mixed> $props
     *
     * @return list<PageComponent>
     */
    public static function find(Page|PageComponent $in, string $name, array $props): array
    {
        return array_values(array_filter(
            self::flatten($in instanceof Page ? [...$in->header, ...$in->main, ...$in->footer] : self::within($in)),
            static fn (PageComponent $component): bool => self::matches($component, $name, $props),
        ));
    }

    /**
     * The one component with the name whose props include those given;
     * fails when there is none or more than one.
     *
     * @param array<string, mixed> $props
     */
    public static function one(Page|PageComponent $in, string $name, array $props): PageComponent
    {
        $found = self::find($in, $name, $props);
        Assert::assertCount(1, $found, \sprintf('Expected one %s with %s.', $name, json_encode($props, \JSON_THROW_ON_ERROR)));

        return $found[0];
    }

    /**
     * The components within a component: its children, then those its props
     * hold, such as the fields of each option of a choice, as Twig paints
     * them too.
     *
     * @return list<PageComponent>
     */
    public static function within(PageComponent $component): array
    {
        return [...$component->children, ...self::held($component->props)];
    }

    /**
     * @param array<string, mixed> $props
     */
    private static function matches(PageComponent $component, string $name, array $props): bool
    {
        if ($name !== $component->name) {
            return false;
        }

        foreach ($props as $key => $value) {
            if (!\array_key_exists($key, $component->props) || $component->props[$key] !== $value) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param list<PageComponent> $components
     *
     * @return list<PageComponent>
     */
    private static function flatten(array $components): array
    {
        $flat = [];

        foreach ($components as $component) {
            $flat[] = $component;
            array_push($flat, ...self::flatten(self::within($component)));
        }

        return $flat;
    }

    /**
     * @param array<mixed> $values
     *
     * @return list<PageComponent>
     */
    private static function held(array $values): array
    {
        $held = [];

        foreach ($values as $value) {
            if ($value instanceof PageComponent) {
                $held[] = $value;
            } elseif (\is_array($value)) {
                array_push($held, ...self::held($value));
            }
        }

        return $held;
    }
}
