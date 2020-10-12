<?php

namespace Rikudou\DynamoDbOrm\Service;

use Rikudou\DynamoDbOrm\Exception\UnknownTypeException;

final class TypeConverter
{
    public function getDynamoType(string $type): string
    {
        switch ($type) {
            case 'array':
            case 'json':
            case 'string':
                return 'S';
            case 'number':
                return 'N';
            case 'binary':
                return 'B';
            case 'boolean':
                return 'BOOL';
        }

        throw new UnknownTypeException("The type '{$type}' is not a valid type");
    }
}
