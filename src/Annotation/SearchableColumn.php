<?php

namespace Rikudou\DynamoDbOrm\Annotation;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
final class SearchableColumn
{
    public function __construct(
        public ?string $indexName = null,
    ) {
    }
}
