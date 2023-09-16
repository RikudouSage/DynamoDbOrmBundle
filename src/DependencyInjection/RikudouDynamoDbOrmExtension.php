<?php

namespace Rikudou\DynamoDbOrm\DependencyInjection;

use AsyncAws\DynamoDb\DynamoDbClient;
use Exception;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;

/**
 * @phpstan-type ConfigArray array{
 *  dynamodb: array{
 *     service: string | null,
 *     region: string,
 *     version: string,
 *  },
 *  table_prefix: string | null,
 *  migrations_table: string,
 *  directories: string[],
 *  table_mapping: array<string, string> | null
 * }
 */
final class RikudouDynamoDbOrmExtension extends Extension
{
    /**
     * @param array<string,mixed> $configs
     *
     * @throws Exception
     */
    public function load(array $configs, ContainerBuilder $container): void
    {
        $loader = new YamlFileLoader($container, new FileLocator(__DIR__ . '/../Resources/config'));
        $loader->load('services.yaml');
        $loader->load('commands.yaml');
        $loader->load('aliases.yaml');

        /**
         * @var ConfigArray $configs
         */
        $configs = $this->processConfiguration(new Configuration(), $configs);
        $this->createDynamoDbService($configs, $container);
        $this->createTableNameConverter($configs, $container);
        $this->createParameters($configs, $container);
    }

    /**
     * @param ConfigArray $configs
     */
    private function createDynamoDbService(array $configs, ContainerBuilder $container): void
    {
        $serviceName = 'rikudou.internal.dynamo_orm.dynamo_client';

        if ($configs['dynamodb']['service'] !== null) {
            $serviceToAlias = $configs['dynamodb']['service'];
            $container->setAlias($serviceName, $serviceToAlias);
        } else {
            $definition = new Definition(DynamoDbClient::class);
            $definition->addArgument([
                'region' => $configs['dynamodb']['region'],
            ]);
            $container->setDefinition($serviceName, $definition);
        }
    }

    /**
     * @param ConfigArray $configs
     */
    private function createParameters(array $configs, ContainerBuilder $container): void
    {
        $container->setParameter('rikudou.dynamo_orm.scan_directories', $configs['directories']);
        $container->setParameter('rikudou.internal.dynamo_orm.migrations_table', $configs['migrations_table']);
        $container->setParameter('rikudou.internal.dynamo_orm.table_mapping', $configs['table_mapping'] ?? []);
    }

    /**
     * @param ConfigArray $configs
     */
    private function createTableNameConverter(array $configs, ContainerBuilder $container): void
    {
        $definition = $container->getDefinition('rikudou.dynamo_orm.table_name_converter');
        $definition->addArgument($configs['table_prefix']);
    }
}
