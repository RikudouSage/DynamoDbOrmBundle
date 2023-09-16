<?php

namespace Rikudou\DynamoDbOrm\Event;

use AsyncAws\DynamoDb\DynamoDbClient;
use Rikudou\DynamoDbOrm\Enum\BeforeQuerySendEventType;
use Symfony\Contracts\EventDispatcher\Event;

final class BeforeQuerySendEvent extends Event
{
    /**
     * @var array<mixed>|null
     */
    private array|null $result = null;

    /**
     * @param array<string ,mixed> $requestData
     * @param class-string<object> $entityClass
     */
    public function __construct(
        public readonly array $requestData,
        public readonly BeforeQuerySendEventType $type,
        public readonly string $entityClass,
        public readonly DynamoDbClient $dynamoDbClient
    ) {
    }

    /**
     * @return array<mixed>|null
     */
    public function getResult(): ?array
    {
        return $this->result;
    }

    /**
     * @param array<mixed>|null $result
     */
    public function setResult(?array $result): self
    {
        $this->result = $result;

        return $this;
    }
}
