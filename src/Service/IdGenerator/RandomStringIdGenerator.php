<?php

namespace Rikudou\DynamoDbOrm\Service\IdGenerator;

use Rikudou\DynamoDbOrm\Exception\InvalidLengthException;

final class RandomStringIdGenerator implements IdGenerator
{
    public function generateId(int $length = 0): string
    {
        if ($length <= 1 || $length % 2 !== 0) {
            throw new InvalidLengthException('The id length must be greater than zero and divisible by 2');
        }

        return bin2hex(random_bytes($length / 2)); // @phpstan-ignore-line
    }
}
