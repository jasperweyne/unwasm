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

namespace UnWasm\Compiler\Node\Code\Control;

use UnWasm\Compiler\Node\Code\Instruction;
use UnWasm\Compiler\ExpressionCompiler;
use UnWasm\Compiler\Node\Type\FuncType;
use UnWasm\Compiler\Source;

/**
 * Calls a function.
 */
class IfElse extends Instruction
{
    /** @var Instruction[] The nested instructions */
    private $instructions;

    /** @var ?FuncType The specified functype for this context */
    private $funcType;

    /** @var ?int The type index of this context, when funcType is null */
    private $typeIdx;

    public function __construct(array $inner, ?FuncType $funcType, ?int $typeIdx)
    {
        $this->instructions = $inner;
        $this->funcType = $funcType;
        $this->typeIdx = $typeIdx;
    }

    public function compile(ExpressionCompiler $outerState, Source $src): void
    {
        list($condition) = $outerState->pop();

        // Write the block top source
        // The if/else is wrapped in a do-while block for branching purposes
        $this->funcType = $this->funcType ?? $outerState->module->types[$this->typeIdx];
        $src
            ->write("do if ($condition) { // $this->funcType")
            ->indent()
        ;

        $state = Block::createContext($outerState, $this->funcType);
        foreach ($this->instructions as $instr) {
            $instr->compile($state, $src);
        }

        // write returnvars
        $last = end($this->instructions);
        if (!($last instanceof BranchUncond || $last instanceof BranchIndirect)) {
            $stackVars = $state->pop(count($state->return()));
            Block::compileReturn($src, $state->return(), $stackVars);
        }

        // Close the block
        $src
            ->outdent()
            ->write('} while (0);')
            ->write()
        ;
    }
}
