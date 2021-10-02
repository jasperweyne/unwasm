<?php

/*
 * Copyright 2021 Jasper Weyne
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

namespace UnWasm\Compiler;

use UnWasm\Compiler\Node\Code\Func;
use UnWasm\Compiler\Node\External\Export\Export;
use UnWasm\Compiler\Node\External\Import\FuncImport;
use UnWasm\Compiler\Node\External\Import\GlobalImport;
use UnWasm\Compiler\Node\External\Import\Import;
use UnWasm\Compiler\Node\External\Import\MemImport;
use UnWasm\Compiler\Node\External\Import\TableImport;
use UnWasm\Compiler\Node\Store\Data;
use UnWasm\Compiler\Node\Store\Element;
use UnWasm\Compiler\Node\Store\Table;
use UnWasm\Compiler\Node\Store\Memory;
use UnWasm\Compiler\Node\Store\GlobalData;
use UnWasm\Compiler\Node\Type\FuncType;

/**
 * Stores an internal representation of webassembly code and compiles it
 * ahead-of-time to PHP for later execution.
 */
class ModuleCompiler
{
    /** @var FuncType[] Types */
    public $types = array();

    /** @var Func[] Functions */
    public $funcs = array();

    /** @var Table[] Tables */
    public $tables = array();

    /** @var Memory[] Memories */
    public $mems = array();

    /** @var GlobalData[] Globals */
    public $globals = array();

    /** @var Element[] Elements */
    public $elems = array();

    /** @var Data[] Datas */
    public $datas = array();

    /** @var int Start function */
    public $start = -1;

    /** @var Import[] Imports */
    public $imports = array();

    /** @var Export[] Exports */
    public $exports = array();

    public $importRefs = array();

    public function func(?int $idx = null)
    {
        $funcImports = array_filter($this->imports, function ($import) {
            return $import instanceof FuncImport;
        });

        if ($idx === null) {
            return [...$funcImports, ...$this->funcs];
        }

        $importCnt = count($funcImports);
        return $idx < $importCnt ? $funcImports[$idx] : $this->funcs[$idx - $importCnt];
    }

    public function global(int $idx = null)
    {
        $globalImports = array_filter($this->imports, function ($import) {
            return $import instanceof GlobalImport;
        });

        if ($idx === null) {
            return [...$globalImports, ...$this->globals];
        }

        $importCnt = count($globalImports);
        return $idx < $importCnt ? $globalImports[$idx] : $this->globals[$idx - $importCnt];
    }

    public function mem(int $idx = null)
    {
        $memImports = array_filter($this->imports, function ($import) {
            return $import instanceof MemImport;
        });

        if ($idx === null) {
            return [...$memImports, ...$this->mems];
        }

        $importCnt = count($memImports);
        return $idx < $importCnt ? $memImports[$idx] : $this->mems[$idx - $importCnt];
    }

    public function table(int $idx = null)
    {
        $tableImports = array_filter($this->imports, function ($import) {
            return $import instanceof TableImport;
        });

        if ($idx === null) {
            return [...$tableImports, ...$this->tables];
        }

        $importCnt = count($tableImports);
        return $idx < $importCnt ? $tableImports[$idx] : $this->tables[$idx - $importCnt];
    }

    public function compile(string $fqcn): Source
    {
        // prepare import references
        foreach ($this->imports as $import) {
            $this->importRefs[] = $import->module();
        }
        $this->importRefs = array_flip(array_unique($this->importRefs));

        // begin source compilation
        echo "Begin compiling PHP\n";

        $source = new Source();

        $this->compileHeader($source, $fqcn);

        $this->compileVars($source);

        $this->compileConstruct($source);

        $this->compileExports($source);

        $this->compileFuncs($source);

        $this->compileFooter($source);

        echo "Finished compiling PHP\n";

        return $source;
    }

    private function compileHeader(Source $src, string $fqcn): void
    {
        // Top
        $src->write('<?php')->write('');

        // Strip leading backslash from fqcn
        if (strpos($fqcn, '\\') === 0) {
            $fqcn = substr($fqcn, 1);
        }

        // Include namespace if present
        if ($pos = strrpos($fqcn, '\\')) {
            $namespace = substr($fqcn, 0, $pos);
            $src->write("namespace $namespace;")->write();
            $fqcn = substr($fqcn, $pos + 1);
        }

        // Class start
        $src
            ->write("class $fqcn")
            ->write('{')
            ->indent()
        ;
    }

    private function compileVars(Source $src): void
    {
        // todo: respect imports

        // register imported modules
        if (count($this->importRefs) > 0) {
            $src->write('// imported module refs');
            foreach ($this->importRefs as $module => $i) {
                $src->write("/** @var mixed Module '$module' */ private \$ref_$i;")->write();
            }
        }

        // register memories
        if (count($this->mem()) > 0) {
            $src->write('// memories');
            foreach ($this->mem() as $i => $mem) {
                $src->write("/** @var \UnWasm\Store\MemoryInst */ private \$mem_$i;")->write();
            }
        }

        // register memories
        if (count($this->global()) > 0) {
            $src->write('// globals');
            foreach ($this->global() as $i => $global) {
                $src->write("/** @var mixed */ private \$global_$i;")->write();
            }
        }

        // register tables
        if (count($this->table()) > 0) {
            $src->write('// tables');
            foreach ($this->table() as $i => $tables) {
                $src->write("/** @var mixed */ private \$tables_$i;")->write();
            }
        }
    }

    private function compileConstruct(Source $src): void
    {
        $hasEnv = count($this->imports) + count($this->exports) > 0;
        $param = $hasEnv ? '?\UnWasm\Runtime\Environment $env = null' : '';

        // write constructor header
        $src
            ->write("public function __construct($param)")
            ->write('{')
            ->indent()
        ;

        // resolve import references
        if (count($this->importRefs) > 0) {
            $src->write('// imports');
            foreach ($this->importRefs as $import => $i) {
                $src->write("\$this->ref_$i = \$env->import('$import');");
            }
            $src->write();
        }

        // write func intialisation
        $allFuncs = $this->func();
        if (count($allFuncs) > 0) {
            $src->write('// functions');
            foreach ($allFuncs as $i => $func) {
                $func->compileSetup($i, $this, $src);
            }
            $src->write();
        }

        // write memory intialisation
        $allMems = $this->mem();
        if (count($allMems) > 0) {
            $src->write('// memories');
            foreach ($allMems as $i => $mem) {
                $mem->compileSetup($i, $this, $src);
            }
            $src->write();
        }

        // write global intialisation
        $allGlobals = $this->global();
        if (count($allGlobals) > 0) {
            $src->write('// globals');
            foreach ($allGlobals as $i => $global) {
                $global->compileSetup($i, $this, $src);
            }
            $src->write();
        }

        // write table intialisation
        $allTables = $this->table();
        if (count($allTables) > 0) {
            $src->write('// tables');
            foreach ($this->table() as $i => $table) {
                $table->compileSetup($i, $this, $src);
            }
            $src->write();
        }

        // perform exports
        if (count($this->exports) > 0) {
            $src
                ->write('// exports')
                ->write('if ($env) {')
                ->indent()
                ->write('// todo')
            ;

            foreach ($this->exports as $i => $export) {
                // todo
            }

            $src
                ->outdent()
                ->write('}')
                ->write()
            ;
        }

        // execute start function
        if ($this->start !== -1) {
            // verify that module doesn't have input params
            $func = $this->func($this->start);
            $functype = $this->types[$func->typeIdx()];

            if (count($functype->getInput()) !== 0) {
                throw new \RuntimeException('A function with input parameters cant be start');
            }

            $src
                ->write('// start code')
                ->write("\$this->fn_$this->start();")
                ->write()
            ;
        }

        $src
            ->revert()
            ->outdent()
            ->write('}')
            ->write()
        ;
    }

    private function compileExports(Source $src): void
    {
        // compile every export
        foreach ($this->exports as $export) {
            $export->compile($this, $src);
        }
    }

    private function compileFuncs(Source $src): void
    {
        // compile every func
        foreach ($this->func() as $i => $func) {
            $func->compile($i, $this, $src);
        }
    }

    private function compileFooter(Source $src): void
    {
        $src
            ->revert()
            ->outdent()
            ->write('}')
        ;
    }
}
