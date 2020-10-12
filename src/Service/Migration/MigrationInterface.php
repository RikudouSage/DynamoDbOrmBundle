<?php

namespace Rikudou\DynamoDbOrm\Service\Migration;

interface MigrationInterface
{
    public function up(): void;

    public function down(): void;

    public function getVersion(): int;
}
