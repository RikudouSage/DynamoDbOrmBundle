<?php

namespace Rikudou\DynamoDbOrm\Service;

use PhpParser\Node;
use PhpParser\Node\Stmt\Class_;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitorAbstract;
use PhpParser\Parser;
use Rikudou\DynamoDbOrm\Exception\InvalidFileException;

use function Safe\file_get_contents;

final readonly class FileParser
{
    public function __construct(
        private Parser $parser,
        private NodeTraverser $nodeTraverser,
    ) {
    }

    /**
     * @return class-string<object>|null
     */
    public function getClass(string $filePath): ?string
    {
        if (!file_exists($filePath)) {
            throw new InvalidFileException("The file '{$filePath}' does not exist.");
        }
        $content = file_get_contents($filePath);
        $ast = $this->parser->parse($content);
        if ($ast === null) {
            return null;
        }

        $className = null;
        $this->nodeTraverser->addVisitor(new class($className) extends NodeVisitorAbstract {
            private ?string $className;
            public function __construct(
                ?string &$className
            ) {
                $this->className = &$className;
            }

            public function enterNode(Node $node): void
            {
                if (!$node instanceof Class_) {
                    return;
                }

                $this->className = (string) $node->name;
            }
        });
        $this->nodeTraverser->traverse($ast);

        return $className;
    }
}
