<?php

namespace Rikudou\DynamoDbOrm\Annotation;

use Rikudou\DynamoDbOrm\Enum\GeneratedIdType;
use Rikudou\DynamoDbOrm\Service\IdGenerator\IdGenerator;
use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
final readonly class GeneratedId
{
    /**
     * @param class-string<IdGenerator>|null $customGenerator
     */
    public function __construct(
        public GeneratedIdType $type,
        public int $randomStringLength = 20,
        public ?string $customGenerator = null,
    ) {
    }
}
