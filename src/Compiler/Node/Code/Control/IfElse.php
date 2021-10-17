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
use UnWasm\Compiler\Source;

/**
 * Calls a function.
 */
class IfElse extends Instruction
{
    /** @var Instruction[] The nested instructions */
    private $instructions;

    public function __construct(array $inner)
    {
        $this->instructions = $inner; // else is encoded as an independent instruction
    }

    public function compile(ExpressionCompiler $outerState, Source $src): void
    {
        list($condition) = $outerState->pop();

        // Write the block top source
        // The if/else is wrapped in a do-while block for branching purposes
        $src
            ->write('do {')
            ->indent()
            ->write("if ($condition) {")
            ->indent()
        ;
        
        $state = new ExpressionCompiler($outerState->module, [], $outerState);
        foreach ($this->instructions as $instr) {
            $instr->compile($state, $src);
        }
        
        // write returnvars
        $stackVars = $state->pop(count($state->return()));
        Block::compileReturn($src, $state->return(), $stackVars);

        // Close the block
        $src
            ->outdent()
            ->write('}')
            ->outdent()
            ->write('} while (0);')
            ->write()
        ;
    }
}
