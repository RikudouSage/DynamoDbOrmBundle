<?php

namespace Rikudou\DynamoDbOrm\Service;

final readonly class TableNameConverter
{
    public function __construct(
        private ?string $prefix
    ) {
    }

    public function getName(string $name): string
    {
        if ($this->prefix === null) {
            return $name;
        }

        return $this->prefix . '_' . $name;
    }
}
