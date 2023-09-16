<?php

namespace Rikudou\DynamoDbOrm\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

final class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('rikudou_dynamo_db_orm');

        $rootNode = $treeBuilder->getRootNode();
        $rootNode
            ->addDefaultsIfNotSet()
            ->children()
                ->arrayNode('dynamodb')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->scalarNode('service')
                            ->info('The service to use, if not specified one will be created with default settings')
                            ->defaultNull()
                        ->end()
                        ->scalarNode('region')
                            ->info('The region used for default DynamoDB service, ignored if service is specified')
                            ->defaultValue('us-east-1')
                        ->end()
                        ->scalarNode('version')
                            ->info('The version used for default DynamoDB service, ignored if service is specified')
                            ->defaultValue('latest')
                        ->end()
                    ->end()
                ->end()
                ->scalarNode('table_prefix')
                    ->info('The prefix that will be prepended to all table names')
                    ->defaultNull()
                ->end()
                ->scalarNode('migrations_table')
                    ->info('The table that keeps track of migrations')
                    ->defaultValue('migrations')
                ->end()
                ->arrayNode('directories')
                    ->info('The directories to scan for entities')
                    ->addDefaultChildrenIfNoneSet()
                    ->scalarPrototype()
                        ->defaultValue('%kernel.project_dir%/src/Entity')
                    ->end()
                ->end()
                ->arrayNode('table_mapping')
                    ->info('Mapping of entities to tables, takes precedence over tables defined in annotation')
                    ->useAttributeAsKey('entity')
                    ->scalarPrototype()->end()
                ->end()
            ->end();

        return $treeBuilder;
    }
}
