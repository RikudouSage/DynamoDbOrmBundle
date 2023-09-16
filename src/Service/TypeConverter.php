<?php

namespace Rikudou\DynamoDbOrm\Service;

use Rikudou\DynamoDbOrm\Enum\ColumnType;

final class TypeConverter
{
    public function getDynamoType(ColumnType $type): string
    {
        return match ($type) {
            ColumnType::Array, ColumnType::Json, ColumnType::String => 'S',
            ColumnType::Number, ColumnType::DateTime => 'N',
            ColumnType::Binary => 'B',
            ColumnType::Bool => 'BOOL',
        };
    }
}
