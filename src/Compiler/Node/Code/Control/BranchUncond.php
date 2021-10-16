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
class BranchUncond extends Instruction
{
    /** @var int The break depth */
    private $depth;

    public function __construct(int $depth)
    {
        $this->depth = $depth;
    }

    public function compile(ExpressionCompiler $state, Source $src): void
    {
        // write return values
        $return = $state->return($this->depth);
        $stackVars = $state->pop(count($return));
        foreach ($return as $to => $type) {
            // todo: validate types
            $from = array_shift($stackVars);
            $src->write("$to = $from;");
        }

        // branch
        $src->write('continue ', $this->depth + 1, ';');
    }
}
