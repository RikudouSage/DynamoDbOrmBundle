<?php

namespace Rikudou\DynamoDbOrm\Enum;

enum ColumnType: string
{
    case String = 'string';
    case Number = 'number';
    case Binary = 'binary';
    case Bool = 'boolean';
    case Array = 'array';
    case Json = 'json';
    case DateTime = 'datetime';
}
