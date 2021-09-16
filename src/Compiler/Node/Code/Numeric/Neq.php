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

namespace UnWasm\Compiler\Node\Code\Numeric;

use UnWasm\Compiler\ExpressionCompiler;
use UnWasm\Compiler\Source;

/**
 * Add a constant value to the stack.
 */
class Neq extends Numeric
{
    public function compile(ExpressionCompiler $state, Source $src): void
    {
        // assert type
        $state->typed($this->type, 2);

        // update stack
        list($x, $y) = $state->pop(2);
        list($var) = $state->push($this->type);

        // export code
        $src->write("$var = (int)($x !== $y);");
    }
}
