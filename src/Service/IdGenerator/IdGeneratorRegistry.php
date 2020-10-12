<?php

namespace Rikudou\DynamoDbOrm\Service\IdGenerator;

use Rikudou\DynamoDbOrm\Exception\RegistryLockedException;
use Rikudou\DynamoDbOrm\Exception\UnknownGeneratorException;

final class IdGeneratorRegistry
{
    /**
     * @var array<string,IdGeneratorInterface>
     */
    private $generators;

    /**
     * @var bool
     */
    private $locked = false;

    public function register(string $name, IdGeneratorInterface $generator): void
    {
        if ($this->locked) {
            throw new RegistryLockedException('Cannot register new ID generator after container is compiled');
        }
        $this->generators[$name] = $generator;
    }

    public function get(string $name): IdGeneratorInterface
    {
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
