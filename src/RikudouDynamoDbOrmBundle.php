<?php

namespace Rikudou\DynamoDbOrm;

use Rikudou\DynamoDbOrm\DependencyInjection\Compiler\RegisterEntitiesCompilerPass;
use Rikudou\DynamoDbOrm\DependencyInjection\Compiler\RegisterGeneratorsCompilerPass;
use Rikudou\DynamoDbOrm\DependencyInjection\Compiler\RegisterMigrations;
use Rikudou\DynamoDbOrm\DependencyInjection\Compiler\RegisterRepositoriesCompilerPass;
use Rikudou\DynamoDbOrm\Service\IdGenerator\IdGeneratorInterface;
use Rikudou\DynamoDbOrm\Service\Migration\MigrationInterface;
use Rikudou\DynamoDbOrm\Service\Repository\RepositoryInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\Bundle;

final class RikudouDynamoDbOrmBundle extends Bundle
{
    public function build(ContainerBuilder $container): void
    {
        $container->registerForAutoconfiguration(IdGeneratorInterface::class)
            ->addTag('rikudou.dynamo_orm.id_generator');
        $container->registerForAutoconfiguration(RepositoryInterface::class)
            ->addTag('rikudou.dynamo_orm.repository');
        $container->registerForAutoconfiguration(MigrationInterface::class)
            ->addTag('rikudou.dynamo_orm.migration');

        $container->addCompilerPass(new RegisterGeneratorsCompilerPass());
        $container->addCompilerPass(new RegisterEntitiesCompilerPass());
        $container->addCompilerPass(new RegisterRepositoriesCompilerPass());
        $container->addCompilerPass(new RegisterMigrations());
    }
}
