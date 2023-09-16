<?php

namespace Rikudou\DynamoDbOrm\Service;

use ReflectionAttribute;
use ReflectionClass;
use ReflectionProperty;

final readonly class AttributeReader
{
    /**
     * @template T of object
     *
     * @param ReflectionClass<T> $reflection
     * @param class-string<T>    $attribute
     *
     * @return T|null
     */
    public function getClassAnnotation(ReflectionClass $reflection, string $attribute): ?object
    {
        $attributes = $reflection->getAttributes($attribute);
        if (!count($attributes)) {
            return null;
        }

        return $attributes[array_key_first($attributes)]->newInstance();
    }

    /**
     * @template T of object
     *
     * @param class-string<T> $attribute
     *
     * @return T|null
     */
    public function getPropertyAnnotation(ReflectionProperty $reflection, string $attribute): ?object
    {
        $attributes = $reflection->getAttributes($attribute);
        if (!count($attributes)) {
            return null;
        }

        return $attributes[array_key_first($attributes)]->newInstance();
    }

    /**
     * @template TAttribute of object
     *
     * @param class-string<TAttribute>|null $attribute
     *
     * @return ($attribute is null ? iterable<object> : iterable<TAttribute>)
     */
    public function getPropertyAnnotations(ReflectionProperty $propertyReflection, ?string $attribute = null): iterable
    {
        $attributes = $propertyReflection->getAttributes($attribute);

        return array_map(static fn (ReflectionAttribute $attribute) => $attribute->newInstance(), $attributes);
    }
}
