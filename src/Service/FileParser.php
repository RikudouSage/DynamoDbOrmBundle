<?php

namespace Rikudou\DynamoDbOrm\Service;

use PhpParser\Node\Stmt\Class_;
use PhpParser\NodeTraverser;
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
        $statements = $this->parser->parse($content);
        if ($statements === null) {
            return null;
        }
        $statements = $this->nodeTraverser->traverse($statements);

        foreach ($statements as $statement) {
            if ($statement instanceof Class_) {
                $class = (string) $statement->name;
                assert(class_exists($class));

                return $class;
            }
        }

        return null;
    }
}
