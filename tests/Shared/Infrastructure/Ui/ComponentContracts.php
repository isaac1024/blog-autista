<?php

declare(strict_types=1);

namespace Shared\Tests\Infrastructure\Ui;

use PHPUnit\Framework\Assert;
use Shared\Infrastructure\Ui\Page\Page;
use Shared\Infrastructure\Ui\Page\PageComponent;

/**
 * Checks that a composed page gives every component what it takes, as the
 * Twig component mounts it: each prop is a parameter of its mount(), or else
 * one of its public properties, of a type it accepts, and none it requires
 * is missing, and it mounts with them. Twig would turn any other prop
 * silently into an HTML attribute, so none is allowed but "attributes"
 * itself, and only by a component whose template prints them. Every control
 * that takes no label of its own is named by a label.
 *
 * It checks what a page hands its components, never what they paint.
 */
final class ComponentContracts
{
    private const string PREFIX = 'Shared:';

    private const string NAMESPACE = 'Shared\Infrastructure\Twig\Components\\';

    private const string TEMPLATES = __DIR__.'/../../../../templates/components/shared/';

    private const string ATTRIBUTES = 'attributes';

    private const string CHILDREN = 'children';

    /**
     * Controls that take no label of their own, so a FormLabel names them.
     */
    private const array LABELLED_CONTROLS = ['Shared:FormInput', 'Shared:FormTextarea'];

    public static function assertHonoured(Page $page): void
    {
        foreach ([...$page->header, ...$page->main, ...$page->footer] as $component) {
            self::assertComponent($component, $component->name);
        }

        self::assertControlsLabelled($page);
    }

    /**
     * Every control that takes no label of its own is named by a label
     * pointing at it.
     */
    private static function assertControlsLabelled(Page $page): void
    {
        $labelled = array_map(
            static fn (PageComponent $label): mixed => $label->props['for'] ?? null,
            PageComponents::find($page, 'Shared:FormLabel', []),
        );

        foreach (self::LABELLED_CONTROLS as $name) {
            foreach (PageComponents::find($page, $name, []) as $control) {
                Assert::assertContains($control->props['id'] ?? null, $labelled, \sprintf('%s "%s" has no label pointing at it.', $name, json_encode($control->props['id'] ?? null, \JSON_THROW_ON_ERROR)));
            }
        }
    }

    private static function assertComponent(PageComponent $component, string $path): void
    {
        Assert::assertStringStartsWith(self::PREFIX, $component->name, \sprintf('%s is not a shared component.', $path));
        $class = self::NAMESPACE.substr($component->name, \strlen(self::PREFIX));
        Assert::assertTrue(class_exists($class), \sprintf('%s has no component class.', $path));

        // The renderer hands the children over as one more prop.
        $props = [] === $component->children ? $component->props : [...$component->props, self::CHILDREN => $component->children];
        $reflection = new \ReflectionClass($class);
        $parameters = self::mountParameters($reflection);

        foreach ($parameters as $name => $parameter) {
            Assert::assertTrue(
                \array_key_exists($name, $props) || $parameter->isDefaultValueAvailable(),
                \sprintf('%s misses the required prop "%s".', $path, $name),
            );
        }

        // A prop mutated into a list item has a number for a name, whatever its type says.
        /** @var array<array-key, mixed> $props */
        foreach ($props as $name => $value) {
            Assert::assertIsString($name, \sprintf('%s has a prop without a name.', $path));
            self::assertProp($reflection, $parameters, $name, $value, \sprintf('%s.%s', $path, $name));
        }

        self::assertMounts($reflection, $parameters, $props, $path);

        foreach ($component->children as $index => $child) {
            self::assertComponent($child, \sprintf('%s[%d] %s', $path, $index, $child->name));
        }
    }

    /**
     * @param \ReflectionClass<object>            $reflection
     * @param array<string, \ReflectionParameter> $parameters
     */
    private static function assertProp(\ReflectionClass $reflection, array $parameters, string $name, mixed $value, string $path): void
    {
        if (isset($parameters[$name])) {
            Assert::assertTrue(self::accepts($parameters[$name]->getType(), $value), \sprintf('%s is of a type its mount() refuses.', $path));

            return;
        }

        if ($reflection->hasProperty($name) && self::writable($reflection->getProperty($name))) {
            Assert::assertTrue(self::accepts($reflection->getProperty($name)->getType(), $value), \sprintf('%s is of a type its property refuses.', $path));

            return;
        }

        Assert::assertSame(self::ATTRIBUTES, $name, \sprintf('%s is no prop of the component, so it would become an HTML attribute.', $path));
        Assert::assertStringContainsString(
            '{{ attributes',
            (string) file_get_contents(self::TEMPLATES.$reflection->getShortName().'.html.twig'),
            \sprintf('%s is given to a component that doesn\'t print them.', $path),
        );
        Assert::assertIsArray($value, \sprintf('%s are not a map of attributes.', $path));

        foreach ($value as $attribute => $attributeValue) {
            Assert::assertIsString($attribute, \sprintf('%s has an attribute without a name.', $path));
            Assert::assertTrue(\is_scalar($attributeValue), \sprintf('%s.%s is no attribute value.', $path, $attribute));
        }
    }

    /**
     * Mounts the component as Twig would, so what it refuses of its props,
     * such as a disclosure panel without a title, fails here too.
     *
     * @param \ReflectionClass<object>            $reflection
     * @param array<string, \ReflectionParameter> $parameters
     * @param array<string, mixed>                $props
     */
    private static function assertMounts(\ReflectionClass $reflection, array $parameters, array $props, string $path): void
    {
        if ([] === $parameters) {
            return;
        }

        $arguments = array_map(
            static fn (\ReflectionParameter $parameter): mixed => \array_key_exists($parameter->getName(), $props) ? $props[$parameter->getName()] : $parameter->getDefaultValue(),
            array_values($parameters),
        );

        try {
            $reflection->getMethod('mount')->invokeArgs($reflection->newInstance(), $arguments);
        } catch (\Throwable $exception) {
            Assert::fail(\sprintf('%s refuses its props: %s', $path, $exception->getMessage()));
        }
    }

    /**
     * @param \ReflectionClass<object> $reflection
     *
     * @return array<string, \ReflectionParameter>
     */
    private static function mountParameters(\ReflectionClass $reflection): array
    {
        if (!$reflection->hasMethod('mount')) {
            return [];
        }

        $parameters = [];

        foreach ($reflection->getMethod('mount')->getParameters() as $parameter) {
            $parameters[$parameter->getName()] = $parameter;
        }

        return $parameters;
    }

    private static function writable(\ReflectionProperty $property): bool
    {
        return $property->isPublic() && !$property->isStatic() && !$property->isReadOnly();
    }

    private static function accepts(?\ReflectionType $type, mixed $value): bool
    {
        if (null === $type) {
            return true;
        }

        if ($type instanceof \ReflectionUnionType) {
            foreach ($type->getTypes() as $member) {
                if (self::accepts($member, $value)) {
                    return true;
                }
            }

            return false;
        }

        Assert::assertInstanceOf(\ReflectionNamedType::class, $type);

        if (null === $value) {
            return $type->allowsNull();
        }

        return match ($type->getName()) {
            'mixed' => true,
            'string' => \is_string($value),
            'int' => \is_int($value),
            'float' => \is_float($value) || \is_int($value),
            'bool' => \is_bool($value),
            'true' => true === $value,
            'false' => false === $value,
            'array' => \is_array($value),
            'iterable' => is_iterable($value),
            default => \is_object($value) && is_a($value, $type->getName()),
        };
    }
}
