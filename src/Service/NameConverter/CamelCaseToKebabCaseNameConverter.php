<?php

namespace Rikudou\DynamoDbOrm\Service\NameConverter;

use RuntimeException;

final class CamelCaseToKebabCaseNameConverter implements NameConverterInterface
{
    public function convertForDynamoDb(string $name): string
    {
        $result = preg_replace_callback('@[A-Z]@', function ($matches) {
            return '_' . strtolower($matches[0]);
        }, $name);

        if (!is_string($result)) {
            throw new RuntimeException('Error converting the name to kebab case');
        }

        return $result;
    }

    public function convertFromDynamoDb(string $name): string
    {
        $result = preg_replace_callback('@_([a-z])@', function ($matches) {
            return strtoupper($matches[1]);
        }, $name);

        if (!is_string($result)) {
            throw new RuntimeException('Error converting the name to camel case');
        }

        return $result;
    }
}
