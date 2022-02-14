<?php

declare(strict_types=1);

namespace Tests;

use Symfony\Component\Finder\Finder;
use PHPUnit\Framework\TestCase;
use UnWasm\Compiler\TextParser;

/**
 * Class WebAssemblyTest.
 */

final class WebAssemblyTest extends TestCase
{
    /**
     * @dataProvider wastProvider
     */
    public function testCoreTestsPass(callable $test): void
    {
        ($test)();
    }
    
    public function wastProvider(): array
    {
        // gather tests
        $suite = __DIR__.'/WebAssembly';
        $finder = new Finder();
        $finder
            ->files()
            ->name('*.wast')
            ->in($suite)
            ->exclude('proposals')
        ;

        // build the return array of test paths
        $tests = [];
        
        /** @var \SplFileInfo $file */
        foreach ($finder as $file) {
            foreach ($this->extractTests($file) as $i => $test) {
                $name = $file->getFilename();
                $tests["$name (test $i)"] = [$test];
            }
        }

        // sort by key and return the tests
        \ksort($tests);
        return $tests;
    }

    private function extractTests(\SplFileInfo $file): array
    {
        // open .wast file
        $stream = fopen($file->getRealPath(), 'r');
        $parser = new TextParser($stream);

        // build the tests
        $tests = $parser->vec(function (TextParser $p) {
            $p->expectOpen();
            $assert = $p->expectKeyword();
            $p->expectClose();

            return function () {
                // todo
                $this->assertTrue(true);
            };
        });

        // cleanup and return
        fclose($stream);
        return $tests;
    }
}
