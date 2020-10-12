<?php

namespace Rikudou\DynamoDbOrm\DependencyInjection\Compiler;

use Rikudou\DynamoDbOrm\Service\Repository\AbstractRepository;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

final class RegisterRepositoriesCompilerPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        $registry = $container->getDefinition('rikudou.dynamo_orm.repository.registry');
        $repositories = array_keys($container->findTaggedServiceIds('rikudou.dynamo_orm.repository'));

        foreach ($repositories as $repository) {
            $definition = $container->getDefinition($repository);
            $class = $definition->getClass();
            assert(is_string($class));
            if (is_a($class, AbstractRepository::class, true)) {
                $definition->addMethodCall('setDependencies', [
                    new Reference('rikudou.dynamo_orm.entity_manager'),
                    new Reference('rikudou.dynamo_orm.entity_metadata.registry'),
                    new Reference('rikudou.dynamo_orm.entity_mapper'),
                ]);
            }
            $registry->addArgument(new Reference($repository));
        }
    }
}
