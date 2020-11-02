<?php

namespace Rikudou\DynamoDbOrm\Service\Migration;

use Rikudou\DynamoDbOrm\Enums\Types;
use function Safe\sleep;

final class CreateMigrationTableMigration extends AbstractMigration
{
    /**
     * @var string
     */
    private $migrationTable;

    public function __construct(string $migrationTable)
    {
        $this->migrationTable = $migrationTable;
    }

    public function up(): void
    {
        if (!$this->tableExists($this->migrationTable)) {
            $this->createTable($this->migrationTable, ['version' => Types::NUMBER]);
            $this->waitForTable($this->migrationTable);
            sleep(5);
        }
    }

    public function down(): void
    {
        $this->dropTable($this->migrationTable);
    }

    public function getVersion(): int
    {
        return 0;
    }
}
