<?php

namespace Rikudou\DynamoDbOrm\Service\IdGenerator;

use Ramsey\Uuid\Uuid;

final class UuidIdGenerator implements IdGeneratorInterface
{
    public function generateId()
    {
        return Uuid::uuid4()->toString();
    }
}
