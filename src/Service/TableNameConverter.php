<?php

namespace Rikudou\DynamoDbOrm\Service;

final class TableNameConverter
{
    /**
     * @var string|null
     */
    private $prefix;

    public function __construct(?string $prefix)
    {
        $this->prefix = $prefix;
    }

    public function getName(string $name): string
    {
        if ($this->prefix === null) {
            return $name;
        }

        return $this->prefix . '_' . $name;
    }
}
