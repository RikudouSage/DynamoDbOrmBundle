<?php

namespace Rikudou\DynamoDbOrm\Annotation;

use Attribute;
use Rikudou\DynamoDbOrm\Enum\ColumnType;

#[Attribute(Attribute::TARGET_PROPERTY)]
final class Column
{
    public function __construct(
        public readonly ColumnType $type,
        public ?string $name = null,
    ) {
    }
}
