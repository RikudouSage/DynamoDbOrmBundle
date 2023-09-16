<?php

namespace Rikudou\DynamoDbOrm\DependencyInjection\Compiler;

use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\NameResolver;
use PhpParser\ParserFactory;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionClass;
use Rikudou\DynamoDbOrm\Annotation\Entity;
use Rikudou\DynamoDbOrm\Exception\InvalidDirectoryException;
use Rikudou\DynamoDbOrm\Exception\InvalidParameterException;
use Rikudou\DynamoDbOrm\Service\AttributeReader;
use Rikudou\DynamoDbOrm\Service\EntityMetadata\EntityClassMetadata;
use Rikudou\DynamoDbOrm\Service\FileParser;
use SplFileInfo;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

final readonly class RegisterEntitiesCompilerPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        $tableMapping = $container->getParameter('rikudou.internal.dynamo_orm.table_mapping');
        $metadataRegistryDefinition = $container->getDefinition('rikudou.dynamo_orm.entity_metadata.registry');

        $nodeTraverser = new NodeTraverser();
        $fileParser = new FileParser(
            (new ParserFactory())->create(ParserFactory::PREFER_PHP7),
            $nodeTraverser,
        );

        $directories = $container->getParameter('rikudou.dynamo_orm.scan_directories');
        assert(is_array($directories));
        $directories = array_map(static function (string $directory) use ($container) {
            return [
                'original' => $directory,
                'resolved' => preg_replace_callback('@%(.+?)%@', static function ($matches) use ($container) {
                    if (!$container->hasParameter($matches[1])) {
                        throw new InvalidParameterException("Trying to load unknown parameter '{$matches[1]}'");
                    }

                    $result = $container->getParameter($matches[1]);
                    assert(is_string($result));

                    return $result;
                }, $directory),
            ];
        }, $directories);

        foreach ($directories as $directory) {
            assert(is_string($directory['resolved']));
            if (!is_dir($directory['resolved'])) {
                throw new InvalidDirectoryException("Unknown directory '{$directory['resolved']}' (expanded from '{$directory['original']}')");
            }
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator(
                    $directory['resolved']
                )
            );

            $attributeReader = new AttributeReader();

            /** @var SplFileInfo $file */
            foreach ($iterator as $file) {
                if ($file->isDir()) {
                    continue;
                }
                if ($file->getExtension() !== 'php') {
                    continue;
                }

                $className = $fileParser->getClass($file->getPathname());
                if ($className === null) {
                    continue;
                }
                $classReflection = new ReflectionClass($className);

                if (!$attributeReader->getClassAnnotation($classReflection, Entity::class)) {
                    continue;
                }

                $definition = new Definition(EntityClassMetadata::class);
                $definition->addArgument($classReflection->getName());
                $definition->addArgument($tableMapping);
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
