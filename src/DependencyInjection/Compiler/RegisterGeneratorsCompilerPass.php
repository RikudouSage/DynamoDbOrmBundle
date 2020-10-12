<?php

namespace Rikudou\DynamoDbOrm\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

final class RegisterGeneratorsCompilerPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        $generatorServiceNames = array_keys($container->findTaggedServiceIds('rikudou.dynamo_orm.id_generator'));
        $generatorRegistry = $container->getDefinition('rikudou.dynamo_orm.id.registry');

        foreach ($generatorServiceNames as $generatorServiceName) {
            switch ($generatorServiceName) {
                case 'rikudou.dynamo_orm.id.uuid':
                    $key = 'uuid';
                    break;
                case 'rikudou.dynamo_orm.id.random':
                    $key = 'randomString';
                    break;
                default:
                    $key = $generatorServiceName;
            }

            $generatorRegistry->addMethodCall('register', [
                $key,
                new Reference($generatorServiceName),
            ]);
        }

        $generatorRegistry->addMethodCall('lock');
    }
}
