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

namespace UnWasm\Compiler\Node\Store;

use UnWasm\Compiler\ExpressionCompiler;
use UnWasm\Compiler\ModuleCompiler;
use UnWasm\Compiler\Node\External\GlobalInterface;
use UnWasm\Compiler\Node\Type\GlobalType;
use UnWasm\Compiler\Source;

/**
 * Represents a global, local to the module.
 */
class GlobalData implements GlobalInterface
{
    /** @var GlobalType The type descriptor for the global */
    public $globalType;

    /** @var Instruction[] Value initiation expression */
    private $initExpr;

    public function __construct(GlobalType $type, array $initExpr)
    {
        $this->globalType = $type;
        $this->initExpr = $initExpr;
    }

    public function globalType(): GlobalType
    {
        return $this->globalType;
    }

    public function compileSetup(int $index, ModuleCompiler $module, Source $src): void
    {
        // compile init expression
        $expr = new ExpressionCompiler($module, [], null, true);
        foreach ($this->initExpr as $instr) {
            $instr->compile($expr, new Source());
        }
        // todo: verify stack
        list($initValue) = $expr->pop();

        // setup correct types
        $mutable = $this->globalType->mutable ? 'true' : 'false';
        $type = $this->globalType->valueType;
        switch ($type) {
            case ExpressionCompiler::I32:
            case ExpressionCompiler::I64:
                $compileInit = 'setInt';
                break;
            case ExpressionCompiler::F32:
            case ExpressionCompiler::F64:
                $compileInit = 'setFloat';
                break;
        }

        $src
            ->write("\$this->global_$index = new \UnWasm\Runtime\GlobalInst('$type', $mutable);")
            ->write("\$this->global_$index->$compileInit($initValue);")
        ;
    }
}
