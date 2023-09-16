<?php

namespace Rikudou\DynamoDbOrm\DependencyInjection\Compiler;

use Rikudou\DynamoDbOrm\Service\Migration\AbstractMigration;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

final readonly class RegisterMigrations implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        $migrationManager = $container->getDefinition('rikudou.dynamo_orm.migration.manager');
        $migrations = array_keys($container->findTaggedServiceIds('rikudou.dynamo_orm.migration'));

        foreach ($migrations as $migration) {
            $definition = $container->getDefinition($migration);
            $class = $definition->getClass();
            assert(is_string($class));
            if (is_a($class, AbstractMigration::class, true)) {
                $definition->addMethodCall('setDependencies', [
                    new Reference('rikudou.dynamo_orm.type_converter'),
                    new Reference('rikudou.internal.dynamo_orm.dynamo_client'),
                    new Reference('rikudou.dynamo_orm.table_name_converter'),
                ]);
            }

            $migrationManager->addArgument(new Reference($migration));
        }
    }
}
