<?php

namespace Rikudou\DynamoDbOrm\Service\IdGenerator;

use Rikudou\DynamoDbOrm\Enum\GeneratedIdType;
use Rikudou\DynamoDbOrm\Exception\RegistryLockedException;
use Rikudou\DynamoDbOrm\Exception\UnknownGeneratorException;

final class IdGeneratorRegistry
{
    /**
     * @var array<string, IdGenerator>
     */
    private array $generators;

    private bool $locked = false;

    public function register(string $name, IdGenerator $generator): void
    {
        if ($this->locked) {
            throw new RegistryLockedException('Cannot register new ID generator after container is compiled');
        }
        $this->generators[$name] = $generator;
    }

    public function get(string|GeneratedIdType $name): IdGenerator
    {
        if (!is_string($name)) {
            $name = $name->value;
        }

        if (!isset($this->generators[$name])) {
            throw new UnknownGeneratorException("The generator service '{$name}' does not exist");
        }

        return $this->generators[$name];
    }

    public function lock(): void
    {
        $this->locked = true;
    }
}
