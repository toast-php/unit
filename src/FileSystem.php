<?php

namespace Toast\Runner;

use stdClass;
use RecursiveIteratorIterator;
use RecursiveDirectoryIterator;
use ReflectionClass;
use ReflectionException;
use zpt\anno\Annotations;
use Generator;

/**
 * Helper class to find all existing tests in specified directory.
 */
class FileSystem
{
    /**
     * Constructor.
     *
     * @param string $directory
     */
    public function findTests(string $directory) : Generator
    {
        $tests = [];
        foreach (new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory),
            RecursiveIteratorIterator::LEAVES_ONLY
        ) as $file) {
            if ($file->isFile() && substr($file->getFilename(), -4) == '.php') {
                $filename = realpath($file);
                yield $filename;
            }
        }
    }
}

