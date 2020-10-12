<?php

namespace Rikudou\DynamoDbOrm\Service\Migration;

use Aws\DynamoDb\DynamoDbClient;
use Aws\DynamoDb\Exception\DynamoDbException;
use Rikudou\DynamoDbOrm\Exception\MigrationException;
use Rikudou\DynamoDbOrm\Service\TableNameConverter;
use Safe\DateTime;
use Safe\Exceptions\ArrayException;
use function Safe\sprintf;
use function Safe\usort;

final class MigrationManager
{
    /**
     * @var MigrationInterface[]
     */
    private $migrations;

    /**
     * @var DynamoDbClient
     */
    private $client;

    /**
     * @var string
     */
    private $migrationsTable;

    public function __construct(
        DynamoDbClient $client,
        string $migrationsTable,
        TableNameConverter $tableNameConverter,
        MigrationInterface ...$migrations
    ) {
        $this->migrations = $migrations;
        usort($this->migrations, function (MigrationInterface $migration1, MigrationInterface $migration2) {
            if ($migration1->getVersion() === $migration2->getVersion()) {
                throw new MigrationException(sprintf(
                    'There cannot be two migrations with the same version. Version: (%s). Migrations: %s and %s',
                    $migration1->getVersion(),
                    get_class($migration1),
                    get_class($migration2)
                ));
            }

            return $migration1->getVersion() < $migration2->getVersion() ? -1 : 1;
        });

        $this->client = $client;
        $this->migrationsTable = $tableNameConverter->getName($migrationsTable);
    }

    /**
     * @param int|null $target
     *
     * @throws ArrayException
     *
     * @return MigrationInterface[][]
     */
    public function getMigrationsToApply(?int $target = null): array
    {
        $applied = $this->getAppliedMigrations();
        $migrations = $this->migrations;

        if ($target === null) {
            $target = $migrations[array_key_last($migrations)]->getVersion();
        }

        $result = [
            'up' => [],
            'down' => [],
        ];
        foreach ($migrations as $key => $migration) {
            if ($migration->getVersion() > $target && in_array($migration->getVersion(), $applied, true)) {
                $result['down'][] = $migration;
            } elseif ($migration->getVersion() <= $target && !in_array($migration->getVersion(), $applied, true)) {
                $result['up'][] = $migration;
            }
        }

        usort($result['down'], function (MigrationInterface $migration1, MigrationInterface $migration2) {
            return $migration1->getVersion() > $migration2->getVersion() ? -1 : 1;
        });

        return $result;
    }

    public function markMigrationAsDone(MigrationInterface $migration): void
    {
        $this->client->putItem([
            'TableName' => $this->migrationsTable,
            'Item' => [
                'version' => [
                    'N' => (string) $migration->getVersion(),
                ],
                'applied' => [
                    'S' => (new DateTime())->format('c'),
                ],
            ],
        ]);
    }

    public function markMigrationAsUndone(MigrationInterface $migration): void
    {
        $this->client->deleteItem([
            'TableName' => $this->migrationsTable,
            'Key' => [
                'version' => ['N' => (string) $migration->getVersion()],
            ],
        ]);
    }

    /**
     * @return int[]
     */
    private function getAppliedMigrations(): array
    {
        try {
            $result = $this->client->scan([
                'TableName' => $this->migrationsTable,
            ]);

            return array_map(function (array $item) {
                return (int) $item['version']['N'];
            }, $result->get('Items'));
        } catch (DynamoDbException $e) {
            if ($e->getAwsErrorCode() !== 'ResourceNotFoundException') {
                throw $e;
            }

            return [];
        }
    }
}
