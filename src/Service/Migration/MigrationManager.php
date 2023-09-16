<?php

namespace Rikudou\DynamoDbOrm\Service\Migration;

use AsyncAws\DynamoDb\DynamoDbClient;
use AsyncAws\DynamoDb\Exception\ResourceNotFoundException;
use AsyncAws\DynamoDb\ValueObject\AttributeValue;
use Rikudou\DynamoDbOrm\Exception\MigrationException;
use Rikudou\DynamoDbOrm\Service\TableNameConverter;
use Safe\DateTime;
use Safe\Exceptions\ArrayException;

final class MigrationManager
{
    /**
     * @var Migration[]
     */
    private array $migrations;

    private string $migrationsTable;

    public function __construct(
        private DynamoDbClient $client,
        string $migrationsTable,
        TableNameConverter $tableNameConverter,
        Migration ...$migrations
    ) {
        $this->migrations = $migrations;
        usort($this->migrations, static function (Migration $migration1, Migration $migration2) {
            if ($migration1->getVersion() === $migration2->getVersion()) {
                throw new MigrationException(sprintf(
                    'There cannot be two migrations with the same version. Version: (%s). Migrations: %s and %s',
                    $migration1->getVersion(),
                    $migration1::class,
                    $migration2::class
                ));
            }

            return $migration1->getVersion() < $migration2->getVersion() ? -1 : 1;
        });

        $this->migrationsTable = $tableNameConverter->getName($migrationsTable);
    }

    /**
     * @throws ArrayException
     *
     * @return Migration[][]
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

        usort($result['down'], static function (Migration $migration1, Migration $migration2) {
            return $migration1->getVersion() > $migration2->getVersion() ? -1 : 1;
        });

        return $result;
    }

    public function markMigrationAsDone(Migration $migration): void
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

    public function markMigrationAsUndone(Migration $migration): void
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

            return array_map(static function (array $item) {
                $version = $item['version'];
                assert($version instanceof AttributeValue);
                assert($version->getN() !== null);

                return (int) $version->getN();
            }, [...$result->getItems()]);
        } catch (ResourceNotFoundException $e) {
            return [];
        }
    }
}
