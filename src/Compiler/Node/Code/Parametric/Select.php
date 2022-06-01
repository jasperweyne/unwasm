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

namespace UnWasm\Compiler\Node\Code\Parametric;

use UnWasm\Compiler\ExpressionCompiler;
use UnWasm\Compiler\Node\Code\Instruction;
use UnWasm\Compiler\Node\Type\ValueType;
use UnWasm\Compiler\Source;

/**
 * Select a value from multiple on the stack.
 */
class Select extends Instruction
{
    /** @var ?ValueType[] The return type of the operation */
    private $type;

    /** @param ?ValueType[] $type The return type of the operation */
    public function __construct(?array $type = null)
    {
        $this->type = $type;
    }

    public function compile(ExpressionCompiler $state, Source $src): void
    {
        // get constant from stack
        $state->typed(new ValueType(ExpressionCompiler::I32));
        list($c) = $state->pop();

        // get options from the stack
        // todo: validate that stack is numeric if $this->type is null
        $this->type = $this->type ?? $state->type(1);
        $state->typed($this->type[0], 2);
        list($val1, $val2) = $state->pop(2);
        list($ret) = $state->push($this->type[0]);

        // export code
        $src->write("$ret = $c ? $val1 : $val2;");
    }
}
