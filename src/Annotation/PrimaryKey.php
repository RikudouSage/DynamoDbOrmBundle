<?php

namespace Rikudou\DynamoDbOrm\Annotation;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
final readonly class PrimaryKey
{
}
