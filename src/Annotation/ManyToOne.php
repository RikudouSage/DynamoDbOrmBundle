<?php

namespace Rikudou\DynamoDbOrm\Annotation;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
final class ManyToOne
{
    /**
     * @param class-string $entity
     */
    public function __construct(
        public readonly string $entity,
        public ?string $joinColumn = null,
        public ?string $indexName = null,
    ) {
    }
}
