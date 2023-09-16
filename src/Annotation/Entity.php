<?php

namespace Rikudou\DynamoDbOrm\Annotation;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
final readonly class Entity
{
    public function __construct(
        public string $table,
    ) {
    }
}
