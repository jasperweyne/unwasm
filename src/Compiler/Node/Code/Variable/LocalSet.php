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

namespace UnWasm\Compiler\Node\Code\Variable;

use UnWasm\Compiler\ExpressionCompiler;
use UnWasm\Compiler\Node\Code\Instruction;
use UnWasm\Compiler\Source;

/**
 * Add a constant value to the stack.
 */
class LocalSet extends Instruction
{
    /** @var int The referenced local variable index */
    private $localIdx;

    /** @var bool Whether the argument should be retained on the stack */
    private $retain;

    public function __construct(int $localIdx, bool $retain = false)
    {
        $this->localIdx = $localIdx;
        $this->retain = $retain;
    }

    public function compile(ExpressionCompiler $state, Source $src): void
    {
        // set type
        list($type) = $state->type(1);
        $local = $state->set($this->localIdx, $type);

        // update stack
        list($x) = $state->peek(1);

        // export code
        $src->write("$local = $x;");

        // remove item from stack if not retaining
        if (!$this->retain) {
            $state->pop();
        }
    }
}
