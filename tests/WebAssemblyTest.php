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
use UnWasm\Runtime\Store;
use UnWasm\Wasm;

/**
 * Class WebAssemblyTest.
 */

final class WebAssemblyTest extends TestCase
{
    const DEFAULT_MODULE = 0;

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
            $store = new Store();
            foreach ($commands as $cmd) {
                ($cmd)($store);
            }
        };
    }

    public function parseModule(TextParser $p): \Closure
    {
        // return function, compiles a module and adds it to the store
        // accepts a function that returns a ModuleCompiler inst
        $returnFactory = function ($id, callable $compilerGen) {
            return function (Store $store) use ($id, $compilerGen) {
                // generate random classname to avoid collisions
                $characters = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
                $classname = '';
                for ($i = 0; $i < 12; $i++) {
                    $index = rand(0, strlen($characters) - 1);
                    $classname .= $characters[$index];
                }

                // compile module
                $compiler = ($compilerGen)();
                $source = new Source();
                $compiler->compile($classname, $source);

                // load the compiled module
                // this adds a class
                eval($source->read());
                new $classname($store, $id); // expect it to register itself to the store
            };
        };

        return $p->oneOf(
            function () use ($p, $returnFactory) {
                // parses during script interpretation
                $module = $p->scan();
                return ($returnFactory)(self::DEFAULT_MODULE, function () use ($module) {
                    return $module;
                });
            },
            function () use ($p, $returnFactory) {
                // parses during testing
                return $p->parenthesised(function () use ($p, $returnFactory) {
                    $p->expectKeyword("module");
                    $id = $p->expectId(true) ?? self::DEFAULT_MODULE;

                    return $p->oneOf(
                        function () use ($p, $id, $returnFactory) {
                            $p->expectKeyword("binary");
                            $module = implode($p->vec([$p, 'expectString']));

                            // return function that tries to parse/compile binary module
                            return ($returnFactory)($id, function () use ($module) {
                                return Wasm::asStream($module, function ($stream) {
                                    $parser = new BinaryParser($stream);
                                    return $parser->scan();
                                });
                            });
                        },
                        function () use ($p, $id, $returnFactory) {
                            $p->expectKeyword("quote");
                            $module = implode("\n", $p->vec([$p, 'expectString']));

                            // return function that tries to parse/compile text module
                            return ($returnFactory)($id, function () use ($module) {
                                return Wasm::asStream($module, function ($stream) {
                                    $parser = new TextParser($stream);
                                    return $parser->scan();
                                });
                            });
                        }
                    );
                });
            }
        );
    }
    
    public function parseRegister(TextParser $p)
    {
        return $p->parenthesised(function () use ($p) {
            $p->expectKeyword("register");
            $name = $p->expectString();
            $module = $p->expectId(true) ?? self::DEFAULT_MODULE;

            return function (Store $store) use ($name, $module) {
                // todo: add $mod (or $module otherwise) to $env with name $str
            };
        });
    }
    
    public function parseAction(TextParser $p): \Closure
    {
        return $p->parenthesised(function () use ($p) {
            return $p->oneOf(
                function () use ($p) {
                    $p->expectKeyword("invoke");
                    $module = $p->expectId(true) ?? self::DEFAULT_MODULE;
                    $func = $p->expectString();
                    $args = $p->vec([$this, 'parseConst']);
        
                    return function (Store $store) use ($module, $func, $args) {
                        // todo: call function with consts
                        return ($store->funcs[$module][$func])(...$args);
                    };
                },
                function () use ($p) {
                    $p->expectKeyword("get");
                    $module = $p->expectId(true) ?? self::DEFAULT_MODULE;
                    $global = $p->expectString();
        
                    return function (Store $store) use ($module, $global) {
                        // todo: get global
                        return ($store->globals[$module][$global]);
                    };
                }
            );
        });
    }
    
    public function parseConst(TextParser $p)
    {
        return $p->parenthesised(function () use ($p) {
            return $p->oneOf(
                function () use ($p) {
                    // ( <num_type>.const <num> )                 ;; number value
                },
                function () use ($p) {
                    // ( <vec_type> <vec_shape> <num>+ )          ;; vector value
                },
                function () use ($p) {
                    // ( ref.null <ref_kind> )                    ;; null reference
                    $p->expectKeyword("ref.null");
                },
                function () use ($p) {
                    // ( ref.extern <nat> )                       ;; host reference
                    $p->expectKeyword("ref.extern");
                }
            );
        });
    }

    public function withException(string $failure, callable $action, ?string $type = \Exception::class)
    {
        return function (Store $store) use ($failure, $action, $type) {
            $thrown = null;
            try {
                ($action)($store);
            } catch (\Exception $e) {
                $thrown = $e;
            }
            $this->assertNotNull($thrown, 'No exception was thrown.');
            $this->assertInstanceOf($type, $thrown);
            $this->assertEquals($failure, $thrown->getMessage());
        };
    }
    
    public function parseAssert(TextParser $p)
    {
        return $p->parenthesised(function () use ($p) {
            return $p->oneOf(
                function () use ($p) {
                    $p->expectKeyword("assert_return");
                    $action = $this->parseAction($p);
                    $expected = $p->vec([$this, 'parseResult']);

                    return function (Store $store) use ($action, $expected) {
                        $result = ($action)($store);
                        $this->assertEqualsCanonicalizing($expected, $result);
                    };
                },
                function () use ($p) {
                    $p->expectKeyword("assert_trap");
                    $action = $this->parseAction($p);
                    $failure = $p->expectString();
                    return $this->withException($failure, $action);
                },
                function () use ($p) {
                    $p->expectKeyword("assert_exhaustion");
                    $action = $this->parseAction($p);
                    $failure = $p->expectString();
                    return $this->withException($failure, $action);
                },
                function () use ($p) {
                    $p->expectKeyword("assert_malformed");
                    $module = $this->parseModule($p);
                    $failure = $p->expectString();
                    return $this->withException($failure, $module);
                },
                function () use ($p) {
                    $p->expectKeyword("assert_invalid");
                    $module = $this->parseModule($p);
                    $failure = $p->expectString();
                    return $this->withException($failure, $module);
                },
                function () use ($p) {
                    $p->expectKeyword("assert_unlinkable");
                    $module = $this->parseModule($p);
                    $failure = $p->expectString();
                    return $this->withException($failure, $module);
                },
                function () use ($p) {
                    $p->expectKeyword("assert_trap");
                    $module = $this->parseModule($p);
                    $failure = $p->expectString();
                    return $this->withException($failure, $module);
                }
            );
        });
    }
    
    public function parseResult(TextParser $p)
    {
        throw new \Exception("Not implemented");
        // todo
    }
    
    public function parseMeta(TextParser $p)
    {
        return $p->parenthesised(function () use ($p) {
            $meta = $p->expectKeyword(
                "script",
                "input",
                "output"
            );
            $p->expectId(true) ?? self::DEFAULT_MODULE;

            // todo: return
            return function (Store $store) {};
        });
    }
}
