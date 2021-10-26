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

namespace UnWasm\Compiler\Node\External\Import;

use UnWasm\Compiler\ModuleCompiler;
use UnWasm\Compiler\Node\External\FuncInterface;
use UnWasm\Compiler\Source;

/**
 * Represents a function import from an external module.
 */
class FuncImport extends Import implements FuncInterface
{
    /** @var int Function type index */
    public $typeIdx;

    public function __construct(string $module, string $name, int $typeIdx)
    {
        parent::__construct($module, $name);

        $this->typeIdx = $typeIdx;
    }

    public function typeIdx(): int
    {
        return $this->typeIdx;
    }

    public function compileSetup(int $index, ModuleCompiler $module, Source $source): void
    {
        $type = strval($module->types[$this->typeIdx]);
        $ref = $module->importRefs[$this->module];
        $source->write("if (\$this->ref_{$ref}->funcs['$this->name'] !== '$type') throw new \UnexpectedValueException('Invalid type');");
    }

    public function compile(int $index, ModuleCompiler $module, Source $src): void
    {
        $ref = $module->importRefs[$this->module];

        /** @var FuncType */
        $functype = $module->types[$this->typeIdx];
        $params = implode(", ", $functype->compileInput('param_', true));
        $vars = implode(", ", $functype->compileInput('param_'));

        // write function header
        $src
            ->write("\$this->fn_$index = function ($params) {")
            ->indent()
            ->write("return \$this->ref_$ref->$this->name($vars);")
            ->outdent()
            ->write('};')
            ->write()
        ;
    }
}
