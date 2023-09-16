<?php

namespace Rikudou\DynamoDbOrm\Service\Migration;

interface Migration
{
    public function up(): void;

    public function down(): void;

    public function getVersion(): int;
}
