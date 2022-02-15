<?php

/*
 * Copyright 2021-2022 Jasper Weyne
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *     http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
*/

declare(strict_types=1);

namespace Tests;

use Symfony\Component\Finder\Finder;
use PHPUnit\Framework\TestCase;
use UnWasm\Compiler\BinaryParser;
use UnWasm\Compiler\Source;
use UnWasm\Compiler\TextParser;
use UnWasm\Wasm;

/**
 * Class WebAssemblyTest.
 */

final class WebAssemblyTest extends TestCase
{
    /**
     * @dataProvider wastProvider
     */
    public function testRun(callable $test): void
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
            $tests[$file->getFilename()] = [$this->parseTestScript($file)];
        }

        // sort by key and return the tests
        \ksort($tests);
        return $tests;
    }

    private function parseTestScript(\SplFileInfo $file): \Closure
    {
        // open .wast file
        $stream = fopen($file->getRealPath(), 'r');
        $p = new TextParser($stream);

        // build the tests
        $commands = $p->vec(function () use ($p) {
            return $p->oneOf(
                [$this, 'parseModule'],
                [$this, 'parseRegister'],
                [$this, 'parseAction'],
                [$this, 'parseAssert'],
                [$this, 'parseMeta']
            );
        });

        // cleanup and return
        fclose($stream);
        return function () use ($commands) {
            $module = null;
            foreach ($commands as $cmd) {
                $ret = ($cmd)($module);
                if ($ret) {
                    $module = $ret;
                }
            }
        };
    }

    public function parseModule(TextParser $p): \Closure
    {
        return $p->oneOf(
            [$p, 'scan'],
            function () use ($p) {
                return $p->parenthesised(function () use ($p) {
                    $p->expectKeyword("module");
                    $p->expectId(true);

                    return $p->oneOf(
                        function () use ($p) {
                            $p->expectKeyword("binary");
                            $module = implode($p->vec([$p, 'expectString']));

                            // return function that tries to parse/compile binary module
                            return function () use ($module) {
                                $compiler = Wasm::asStream($module, function ($stream) {
                                    $parser = new BinaryParser($stream);
                                    return $parser->scan();
                                });
                                $source = new Source();
                                $compiler->compile('module', $source);
                            };
                        },
                        function () use ($p) {
                            $p->expectKeyword("quote");
                            $module = implode("\n", $p->vec([$p, 'expectString']));

                            // return function that tries to parse/compile text module
                            return function () use ($module) {
                                $compiler = Wasm::asStream($module, function ($stream) {
                                    $parser = new TextParser($stream);
                                    return $parser->scan();
                                });
                                $source = new Source();
                                $compiler->compile('module', $source);
                            };
                        }
                    );
                });
            }
        );
    }
    
    public function parseRegister(TextParser $p)
    {
        return $p->parenthesised(function ($module) use ($p) {
            $p->expectKeyword("register");
            $str = $p->expectString();
            $mod = $p->expectId(true);

            // todo: add $mod (or $module otherwise) to $env with name $str
            return function () {};
        });
    }
    
    public function parseAction(TextParser $p)
    {
        return $p->parenthesised(function () use ($p) {
            $p->oneOf(
                function () use ($p) {
                    $p->expectKeyword("invoke");
                    $p->expectId(true);
                    $p->expectString();
                    $p->vec(function () use ($p) {
                        // todo: parse const
                    });
        
                    return function () {
                        // todo: call function with consts
                    };
                },
                function () use ($p) {
                    $p->expectKeyword("get");
                    $p->expectId(true);
                    $p->expectString();
        
                    return function () {
                        // todo: get global
                    };
                }
            );
        });
    }
    
    public function parseAssert(TextParser $p)
    {
        // todo: implement asserts
        return $p->parenthesised(function () use ($p) {
            return $p->oneOf(
                function () use ($p) {
                    $p->expectKeyword("assert_return");
                    $action = $this->parseAction($p);
                    $p->vec(function () use ($p) {
                        // todo parse result
                    });

                    return function () use ($action) {
                        $this->assertEquals($action, '');
                    };
                },
                function () use ($p) {
                    $p->expectKeyword("assert_trap");
                    $action = $this->parseAction($p);
                    $failure = $p->expectString();

                    return function () {};
                },
                function () use ($p) {
                    $p->expectKeyword("assert_exhaustion");
                    $action = $this->parseAction($p);
                    $failure = $p->expectString();

                    return function () {};
                },
                function () use ($p) {
                    $p->expectKeyword("assert_malformed");
                    $module = $this->parseModule($p);
                    $failure = $p->expectString();

                    return function () {};
                },
                function () use ($p) {
                    $p->expectKeyword("assert_invalid");
                    $module = $this->parseModule($p);
                    $failure = $p->expectString();

                    return function () {};
                },
                function () use ($p) {
                    $p->expectKeyword("assert_unlinkable");
                    $module = $this->parseModule($p);
                    $failure = $p->expectString();

                    return function () {};
                },
                function () use ($p) {
                    $p->expectKeyword("assert_trap");
                    $module = $this->parseModule($p);
                    $failure = $p->expectString();

                    return function () {};
                }
            );
        });
    }
    
    public function parseMeta(TextParser $p)
    {
        return $p->parenthesised(function () use ($p) {
            $meta = $p->expectKeyword(
                "script",
                "input",
                "output"
            );
            $p->expectId(true);

            // todo: return
            return function () {};
        });
    }
}
