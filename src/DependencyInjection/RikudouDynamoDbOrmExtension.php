<?php

namespace Rikudou\DynamoDbOrm\DependencyInjection;

use Aws\DynamoDb\DynamoDbClient;
use Exception;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;

final class RikudouDynamoDbOrmExtension extends Extension
{
    /**
     * @param array<string,mixed> $configs
     * @param ContainerBuilder    $container
     *
     * @throws Exception
     */
    public function load(array $configs, ContainerBuilder $container): void
    {
        $loader = new YamlFileLoader($container, new FileLocator(__DIR__ . '/../Resources/config'));
        $loader->load('services.yaml');
        $loader->load('commands.yaml');
        $loader->load('aliases.yaml');

        $configs = $this->processConfiguration(new Configuration(), $configs);
        $this->createDynamoDbService($configs, $container);
        $this->createTableNameConverter($configs, $container);
        $this->createParameters($configs, $container);
    }

    /**
     * @param array<string, mixed> $configs
     * @param ContainerBuilder     $container
     */
    private function createDynamoDbService(array $configs, ContainerBuilder $container): void
    {
        $serviceName = 'rikudou.internal.dynamo_orm.dynamo_client';

        if ($configs['dynamodb']['service'] !== null) {
            $serviceToAlias = $configs['dynamodb']['service'];
            $container->setAlias($serviceName, $serviceToAlias);
        } else {
            $serviceName = 'rikudou.internal.dynamo_orm.dynamo_client';
            $definition = new Definition(DynamoDbClient::class);
            $definition->addArgument([
                'region' => $configs['dynamodb']['region'],
                'version' => $configs['dynamodb']['version'],
            ]);
            $container->setDefinition($serviceName, $definition);
        }
    }

    /**
     * @param array<string,mixed> $configs
     * @param ContainerBuilder    $container
     */
    private function createParameters(array $configs, ContainerBuilder $container): void
    {
        $container->setParameter('rikudou.dynamo_orm.scan_directories', $configs['directories']);
        $container->setParameter('rikudou.internal.dynamo_orm.migrations_table', $configs['migrations_table']);
    }

    /**
     * @param array<string,mixed> $configs
     * @param ContainerBuilder    $container
     */
    private function createTableNameConverter(array $configs, ContainerBuilder $container): void
    {
        $definition = $container->getDefinition('rikudou.dynamo_orm.table_name_converter');
        $definition->addArgument($configs['table_prefix']);
    }
}
