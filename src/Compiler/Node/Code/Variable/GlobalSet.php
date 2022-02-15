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

namespace UnWasm\Compiler\Node\Code\Variable;

use UnWasm\Compiler\ExpressionCompiler;
use UnWasm\Compiler\Node\Code\Instruction;
use UnWasm\Compiler\Source;

/**
 * Add a constant value to the stack.
 */
class GlobalSet extends Instruction
{
    /** @var int The referenced global variable index */
    private $globalIdx;

    public function __construct(int $globalIdx)
    {
        $this->globalIdx = $globalIdx;
    }

    public function compile(ExpressionCompiler $state, Source $src): void
    {
        // set type
        list($type) = $state->type(1);
        switch ($type) {
            case ExpressionCompiler::I32:
            case ExpressionCompiler::I64:
                $compileFn = 'setInt';
                break;
            case ExpressionCompiler::F32:
            case ExpressionCompiler::F64:
                $compileFn = 'setFloat';
                break;
        }

        // update stack
        list($x) = $state->pop();

        // export code
        $src->write("\$this->global_$this->globalIdx->$compileFn($x);");
    }
}
