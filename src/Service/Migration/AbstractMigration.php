<?php

namespace Rikudou\DynamoDbOrm\Service\Migration;

use AsyncAws\DynamoDb\DynamoDbClient;
use AsyncAws\DynamoDb\Enum\IndexStatus;
use AsyncAws\DynamoDb\Enum\TableStatus;
use AsyncAws\DynamoDb\Exception\ResourceNotFoundException;
use Rikudou\DynamoDbOrm\Enum\ColumnType;
use Rikudou\DynamoDbOrm\Exception\MigrationException;
use Rikudou\DynamoDbOrm\Service\TableNameConverter;
use Rikudou\DynamoDbOrm\Service\TypeConverter;
use RuntimeException;
use Safe\Exceptions\PcreException;

use function Safe\preg_match;

abstract class AbstractMigration implements Migration
{
    private TypeConverter $typeConverter;

    private DynamoDbClient $dynamoClient;

    private TableNameConverter $tableNameConverter;

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
     * @throws PcreException
     * @throws MigrationException
     */
    public function getVersion(): int
    {
        $class = static::class;
        if (preg_match('@^.*?\\\\(?:Version|Migration)([0-9]+)$@', $class, $matches)) {
            return (int) $matches[1];
        }

        throw new MigrationException('Cannot guess version from the class name');
    }

    /**
     * @param array<string, ColumnType>      $primaryKey
     * @param array<string, ColumnType>|null $sortKey
     */
    protected function createTable(
        string $tableName,
        array $primaryKey,
        ?array $sortKey = null,
    ): void {
        assert(count($primaryKey) > 0);

        $requestArray = [
            'TableName' => $this->tableNameConverter->getName($tableName),
            'AttributeDefinitions' => [
                [
                    'AttributeName' => array_key_first($primaryKey),
                    'AttributeType' => $this->typeConverter->getDynamoType($primaryKey[array_key_first($primaryKey)]),
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
            assert(count($sortKey) > 0);
            $requestArray['AttributeDefinitions'][] = [
                'AttributeName' => array_key_first($sortKey),
                'AttributeType' => $this->typeConverter->getDynamoType($sortKey[array_key_first($sortKey)]),
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

    protected function setTimeToLive(string $tableName, ?string $fieldName): void
    {
        $this->dynamoClient->updateTimeToLive([
            'TableName' => $this->tableNameConverter->getName($tableName),
            'TimeToLiveSpecification' => [
                'Enabled' => $fieldName !== null,
                'AttributeName' => $fieldName,
            ],
        ]);
    }

    /**
     * @param array<string, ColumnType>      $primaryKey
     * @param array<string, ColumnType>|null $sortKey
     */
    protected function createIndex(string $table, string $indexName, array $primaryKey, ?array $sortKey = null): void
    {
        assert(count($primaryKey) > 0);

        $keySchema = [
            [
                'AttributeName' => array_key_first($primaryKey),
                'KeyType' => 'HASH',
            ],
        ];
        $attributeDefinitions = [
            [
                'AttributeName' => array_key_first($primaryKey),
                'AttributeType' => $this->typeConverter->getDynamoType($primaryKey[array_key_first($primaryKey)]),
            ],
        ];

        if ($sortKey !== null) {
            assert(count($sortKey) > 0);

            $keySchema[] = [
                'AttributeName' => array_key_first($sortKey),
                'KeyType' => 'RANGE',
            ];
            $attributeDefinitions[] = [
                'AttributeName' => array_key_first($sortKey),
                'AttributeType' => $this->typeConverter->getDynamoType($sortKey[array_key_first($sortKey)]),
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

    protected function tableExists(string $table): bool
    {
        try {
            $this->dynamoClient->describeTable([
                'TableName' => $this->tableNameConverter->getName($table),
            ]);

            return true;
        } catch (ResourceNotFoundException) {
            return false;
        }
    }

    protected function waitForTable(string $table, int $sleep = 3, int $retries = 150): void
    {
        do {
            try {
                $result = $this->dynamoClient->describeTable([
                    'TableName' => $this->tableNameConverter->getName($table),
                ]);
                if ($result->getTable() === null) {
                    throw new RuntimeException('Table description is null');
                }
                $status = $result->getTable()->getTableStatus() === TableStatus::ACTIVE;
                $indexes = $result->getTable()->getGlobalSecondaryIndexes();

                foreach ($indexes as $index) {
                    if ($index->getIndexStatus() !== IndexStatus::ACTIVE) {
                        $status = false;
                    }
                }
            } catch (ResourceNotFoundException) {
                $status = false;
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
