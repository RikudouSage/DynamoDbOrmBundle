<?php

namespace Rikudou\DynamoDbOrm;

use Rikudou\DynamoDbOrm\DependencyInjection\Compiler\RegisterEntitiesCompilerPass;
use Rikudou\DynamoDbOrm\DependencyInjection\Compiler\RegisterGeneratorsCompilerPass;
use Rikudou\DynamoDbOrm\DependencyInjection\Compiler\RegisterMigrations;
use Rikudou\DynamoDbOrm\DependencyInjection\Compiler\RegisterRepositoriesCompilerPass;
use Rikudou\DynamoDbOrm\Service\IdGenerator\IdGenerator;
use Rikudou\DynamoDbOrm\Service\Migration\Migration;
use Rikudou\DynamoDbOrm\Service\Repository\Repository;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\Bundle;

final class RikudouDynamoDbOrmBundle extends Bundle
{
    public function build(ContainerBuilder $container): void
    {
        $container->registerForAutoconfiguration(IdGenerator::class)
            ->addTag('rikudou.dynamo_orm.id_generator');
        $container->registerForAutoconfiguration(Repository::class)
            ->addTag('rikudou.dynamo_orm.repository');
        $container->registerForAutoconfiguration(Migration::class)
            ->addTag('rikudou.dynamo_orm.migration');

        $container->addCompilerPass(new RegisterGeneratorsCompilerPass());
        $container->addCompilerPass(new RegisterEntitiesCompilerPass());
        $container->addCompilerPass(new RegisterRepositoriesCompilerPass());
        $container->addCompilerPass(new RegisterMigrations());
    }
}
