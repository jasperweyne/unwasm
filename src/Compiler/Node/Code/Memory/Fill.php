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

namespace UnWasm\Compiler\Node\Code\Memory;

use UnWasm\Compiler\ExpressionCompiler;
use UnWasm\Compiler\Node\Code\Instruction;
use UnWasm\Compiler\Node\Type\ValueType;
use UnWasm\Compiler\Source;

/**
 * Fill a region of memory data with a repeating value.
 */
class Fill extends Instruction
{
    public function compile(ExpressionCompiler $state, Source $src): void
    {
        // assert type
        $state->typed(new ValueType(ExpressionCompiler::I32), 3);

        // update stack
        list($destOffset, $value, $n) = $state->pop(3);

        // export code
        $src->write("\$this->mem_0->fill($n, $value, $destOffset);");
    }
}
