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

namespace UnWasm\Compiler\Node\Code\Numeric\Int;

use UnWasm\Compiler\ExpressionCompiler;
use UnWasm\Compiler\Node\Code\Numeric\Numeric;
use UnWasm\Compiler\Source;

/**
 * Count the trailing zeroes of the value on top of the stack.
 */
class Ctz extends Numeric
{
    public function compile(ExpressionCompiler $state, Source $src): void
    {
        // assert type
        $state->typed($this->type);

        // update stack
        list($x) = $state->pop();
        list($var) = $state->push($this->type);

        // export code
        $bits = $this->type->type == ExpressionCompiler::I64 ? 64 : 32;
        $src->write("for ($var = 0, \$x = $x; $var < $bits && !(\$x & 1); \$x >>=1) $var++;");
    }
}
