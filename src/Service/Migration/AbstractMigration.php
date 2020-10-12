<?php

namespace Rikudou\DynamoDbOrm\Service\Migration;

use Aws\DynamoDb\DynamoDbClient;
use Aws\DynamoDb\Exception\DynamoDbException;
use Rikudou\DynamoDbOrm\Exception\MigrationException;
use Rikudou\DynamoDbOrm\Service\TableNameConverter;
use Rikudou\DynamoDbOrm\Service\TypeConverter;
use RuntimeException;
use function Safe\preg_match;
use function Safe\sleep;

abstract class AbstractMigration implements MigrationInterface
{
    /**
     * @var TypeConverter
     */
    private $typeConverter;

    /**
     * @var DynamoDbClient
     */
    private $dynamoClient;

    /**
     * @var TableNameConverter
     */
    private $tableNameConverter;

    public function setDependencies(
        TypeConverter $typeConverter,
        DynamoDbClient $client,
        TableNameConverter $tableNameConverter
    ): void {
        $this->typeConverter = $typeConverter;
        $this->dynamoClient = $client;
        $this->tableNameConverter = $tableNameConverter;
    }

    /**
     * @throws MigrationException
     *
     * @return int
     */
    public function getVersion(): int
    {
        $class = get_class($this);
        if (preg_match('@^.*?\\\\(?:Version|Migration)([0-9]+)$@', $class, $matches)) {
            return (int) $matches[1];
        }

        throw new MigrationException('Cannot guess version from the class name');
    }

    /**
     * @param string                     $tableName
     * @param array<string, string>      $primaryKey
     * @param array<string, string>|null $sortKey
     */
    protected function createTable(
        string $tableName,
        array $primaryKey,
        ?array $sortKey = null
    ): void {
        $requestArray = [
            'TableName' => $this->tableNameConverter->getName($tableName),
            'AttributeDefinitions' => [
                [
                    'AttributeName' => array_key_first($primaryKey),
                    'AttributeType' => $this->typeConverter->getDynamoType((string) reset($primaryKey)),
                ],
            ],
            'BillingMode' => 'PAY_PER_REQUEST',
            'KeySchema' => [
                [
                    'AttributeName' => array_key_first($primaryKey),
                    'KeyType' => 'HASH',
                ],
            ],
        ];

        if ($sortKey !== null) {
            $requestArray['AttributeDefinitions'][] = [
                'AttributeName' => array_key_first($sortKey),
                'AttributeType' => $this->typeConverter->getDynamoType((string) reset($sortKey)),
            ];
            $requestArray['KeySchema'][] = [
                'AttributeName' => array_key_first($sortKey),
                'KeyType' => 'RANGE',
            ];
        }

        $this->dynamoClient->createTable($requestArray);
    }

    protected function dropTable(string $tableName): void
    {
        $this->dynamoClient->deleteTable([
            'TableName' => $this->tableNameConverter->getName($tableName),
        ]);
    }

    /**
     * @param string                    $table
     * @param string                    $indexName
     * @param array<string,string>      $primaryKey
     * @param array<string,string>|null $sortKey
     */
    protected function createIndex(string $table, string $indexName, array $primaryKey, ?array $sortKey = null): void
    {
        $keySchema = [
            [
                'AttributeName' => array_key_first($primaryKey),
                'KeyType' => 'HASH',
            ],
        ];
        $attributeDefinitions = [
            [
                'AttributeName' => array_key_first($primaryKey),
                'AttributeType' => $this->typeConverter->getDynamoType((string) reset($primaryKey)),
            ],
        ];

        if ($sortKey !== null) {
            $keySchema[] = [
                'AttributeName' => array_key_first($sortKey),
                'KeyType' => 'RANGE',
            ];
            $attributeDefinitions[] = [
                'AttributeName' => array_key_first($sortKey),
                'AttributeType' => $this->typeConverter->getDynamoType((string) reset($sortKey)),
            ];
        }

        $requestArray = [
            'TableName' => $this->tableNameConverter->getName($table),
            'AttributeDefinitions' => $attributeDefinitions,
            'GlobalSecondaryIndexUpdates' => [
                [
                    'Create' => [
                        'IndexName' => $indexName,
                        'KeySchema' => $keySchema,
                        'Projection' => [
                            'ProjectionType' => 'ALL',
                        ],
                    ],
                ],
            ],
        ];

        $this->dynamoClient->updateTable($requestArray);
    }

    protected function dropIndex(string $table, string $index): void
    {
        $this->dynamoClient->updateTable([
            'TableName' => $this->tableNameConverter->getName($table),
            'GlobalSecondaryIndexUpdates' => [
                [
                    'Delete' => [
                        'IndexName' => $index,
                    ],
                ],
            ],
        ]);
    }

    protected function waitForTable(string $table, int $sleep = 3, int $retries = 150): void
    {
        do {
            try {
                $result = $this->dynamoClient->describeTable([
                    'TableName' => $this->tableNameConverter->getName($table),
                ]);
                $status = $result->get('Table')['TableStatus'] === 'ACTIVE';

                $indexes = $result->get('Table')['GlobalSecondaryIndexes'] ?? [];
                foreach ($indexes as $index) {
                    if ($index['IndexStatus'] !== 'ACTIVE') {
                        $status = false;
                    }
                }
            } catch (DynamoDbException $e) {
                if ($e->getAwsErrorCode() === 'ResourceNotFoundException') {
                    $status = false;
                } else {
                    throw $e;
                }
            } finally {
                --$retries;
                sleep($sleep);
            }
        } while (!$status && $retries);

        if ($retries === 0) {
            throw new RuntimeException("The table didn't update in the specified wait period");
        }
    }
}
