<?php

namespace Rikudou\DynamoDbOrm\Annotation;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
final readonly class OneToMany
{
    /**
     * @param class-string $entity
     */
    public function __construct(
        public string $entity,
        public string $targetField,
    ) {
    }
}
