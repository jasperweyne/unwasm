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

namespace UnWasm\Compiler\Node\External\Export;

use UnWasm\Compiler\ModuleCompiler;
use UnWasm\Compiler\Source;

/**
 * Represents a function export by the module.
 */
class FuncExport extends Export
{
    /** @var int The function definition index */
    private $funcIdx;

    public function __construct(string $name, int $funcIdx)
    {
        parent::__construct($name);

        $this->funcIdx = $funcIdx;
    }

    public function compile(ModuleCompiler $module, Source $src)
    {
        $func = $module->func($this->funcIdx);
        $type = $module->types[$func->typeIdx()];


        $params = implode(', ', $type->compileInput('x', true));
        $vars = implode(', ', $type->compileInput());

        $src
            ->write("public function $this->name($params): array")
            ->write('{')
            ->indent()
            ->write("return \$this->fn_$this->funcIdx($vars);")
            ->outdent()
            ->write('}')
            ->write('')
        ;
    }
}
