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

namespace UnWasm\Compiler\Node\Code\Numeric\Conversion;

use UnWasm\Compiler\ExpressionCompiler;
use UnWasm\Compiler\Node\Code\Instruction;
use UnWasm\Compiler\Node\Type\ValueType;
use UnWasm\Compiler\Source;

/**
 * Extend an i32 into an i64, possibly not preserving the sign.
 */
class Extend extends Instruction
{
    /** @var bool Whether the operation should be performed unsigned */
    private $unsigned;

    public function __construct(bool $unsigned = false)
    {
        $this->unsigned = $unsigned;
    }

    public function compile(ExpressionCompiler $state, Source $src): void
    {
        // assert type
        $state->typed(new ValueType(ExpressionCompiler::I32));

        // update stack
        list($x) = $state->pop();

        if ($this->unsigned) {
            // export code
            list($var) = $state->push(new ValueType(ExpressionCompiler::I64));
            $src->write("$var = $x & 0xFFFFFFFF;"); // 2 ** 32 - 1
        } else {
            $state->const($x, new ValueType(ExpressionCompiler::I64));
        }
    }
}
