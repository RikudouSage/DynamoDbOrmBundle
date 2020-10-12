<?php

namespace Rikudou\DynamoDbOrm\DependencyInjection\Compiler;

use Doctrine\Common\Annotations\AnnotationReader;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Rikudou\DynamoDbOrm\Annotation\Entity;
use Rikudou\DynamoDbOrm\Exception\InvalidDirectoryException;
use Rikudou\DynamoDbOrm\Exception\InvalidParameterException;
use Rikudou\DynamoDbOrm\Service\EntityMetadata\EntityClassMetadata;
use Rikudou\ReflectionFile;
use SplFileInfo;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;
use Throwable;

final class RegisterEntitiesCompilerPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        $metadataRegistryDefinition = $container->getDefinition('rikudou.dynamo_orm.entity_metadata.registry');

        $directories = $container->getParameter('rikudou.dynamo_orm.scan_directories');
        $directories = array_map(function (string $directory) use ($container) {
            return [
                'original' => $directory,
                'resolved' => preg_replace_callback('@%(.+?)%@', function ($matches) use ($container) {
                    if (!$container->hasParameter($matches[1])) {
                        throw new InvalidParameterException("Trying to load unknown parameter '{$matches[1]}'");
                    }

                    return $container->getParameter($matches[1]);
                }, $directory),
            ];
        }, $directories);

        foreach ($directories as $directory) {
            if (!is_dir($directory['resolved'])) {
                throw new InvalidDirectoryException("Unknown directory '{$directory['resolved']}' (expanded from '{$directory['original']}')");
            }
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator(
                    $directory['resolved']
                )
            );

            $annotationReader = new AnnotationReader();

            /** @var SplFileInfo $file */
            foreach ($iterator as $file) {
                if ($file->isDir()) {
                    continue;
                }
                if ($file->getExtension() !== 'php') {
                    continue;
                }

                try {
                    $reflection = new ReflectionFile($file->getPathname());
                } catch (Throwable $e) {
                    continue;
                }

                $classReflection = $reflection->getClass();
                if (!$annotationReader->getClassAnnotation($classReflection, Entity::class)) {
                    continue;
                }

                $definition = new Definition(EntityClassMetadata::class);
                $definition->addArgument($classReflection->getName());
                $definition->addArgument(new Reference('rikudou.dynamo_orm.name_converter'));
                $definition->addArgument(new Reference('rikudou.dynamo_orm.id.registry'));
                $definition->addArgument(new Reference('rikudou.dynamo_orm.type_converter'));
                $definition->addArgument(new Reference('rikudou.dynamo_orm.table_name_converter'));

                $serviceName = 'rikudou.dynamo_orm.class_metadata.'
                    . str_replace('\\', '', $classReflection->getName());

                $container->setDefinition($serviceName, $definition);
                $metadataRegistryDefinition->addArgument(new Reference($serviceName));
            }
        }
    }
}
