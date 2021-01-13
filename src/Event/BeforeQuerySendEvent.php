<?php

namespace Rikudou\DynamoDbOrm\Event;

use Aws\DynamoDb\DynamoDbClient;
use JetBrains\PhpStorm\ExpectedValues;

final class BeforeQuerySendEvent
{
    public const TYPE_FIND = 'find';

    public const TYPE_FIND_BY = 'findBy';

    public const TYPE_FIND_ONE_BY = 'findOneBy';

    public const TYPE_FIND_ALL = 'findAll';

    /**
     * @var array<string,mixed>
     */
    private $requestData;

    /**
     * @var string
     */
    private $type;

    /**
     * @var string
     */
    private $entityClass;

    /**
     * @var array<mixed>|null
     */
    private $result = null;

    /**
     * @var DynamoDbClient
     */
    private $dynamoDbClient;

    /**
     * BeforeQuerySendEvent constructor.
     *
     * @param array<string,mixed> $requestData
     * @param string              $type
     * @param string              $entityClass
     * @param DynamoDbClient      $dynamoDbClient
     */
    public function __construct(
        array $requestData,
        #[ExpectedValues([self::TYPE_FIND, self::TYPE_FIND_BY, self::TYPE_FIND_ONE_BY, self::TYPE_FIND_ALL])]
        string $type,
        string $entityClass,
        DynamoDbClient $dynamoDbClient
    ) {
        $this->requestData = $requestData;
        $this->type = $type;
        $this->entityClass = $entityClass;
        $this->dynamoDbClient = $dynamoDbClient;
    }

    /**
     * @return array<string,mixed>
     */
    public function getRequestData(): array
    {
        return $this->requestData;
    }

    /**
     * @return string
     */
    public function getType(): string
    {
        return $this->type;
    }

    /**
     * @return string
     */
    public function getEntityClass(): string
    {
        return $this->entityClass;
    }

    /**
     * @return array<mixed>|null
     */
    public function getResult(): ?array
    {
        return $this->result;
    }

    /**
     * @return DynamoDbClient
     */
    public function getDynamoDbClient(): DynamoDbClient
    {
        return $this->dynamoDbClient;
    }

    /**
     * @param array<string,mixed> $requestData
     *
     * @return BeforeQuerySendEvent
     */
    public function setRequestData(array $requestData): BeforeQuerySendEvent
    {
        $this->requestData = $requestData;

        return $this;
    }

    /**
     * @param array<mixed>|null $result
     *
     * @return BeforeQuerySendEvent
     */
    public function setResult(?array $result): BeforeQuerySendEvent
    {
        $this->result = $result;

        return $this;
    }
}
