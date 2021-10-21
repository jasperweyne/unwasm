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

namespace UnWasm\Compiler\Node\Code\Reference;

use UnWasm\Compiler\Binary\Token;
use UnWasm\Compiler\ExpressionCompiler;
use UnWasm\Compiler\Node\Code\Instruction;
use UnWasm\Compiler\Node\Type\RefType;
use UnWasm\Compiler\Node\Type\ValueType;
use UnWasm\Compiler\Source;

/**
 * Checks whether the top of the stack is a null reference.
 */
class IsNull extends Instruction
{
    public function compile(ExpressionCompiler $state, Source $src): void
    {
        // assert type
        $state->typed(new RefType(RefType::FUNCREF));

        // update stack
        $i32 = new ValueType(Token::INT_TYPE);
        list($x) = $state->pop();
        list($var) = $state->push($i32);

        // export code
        $src->write("$var = (int)($x === null);");
    }
}
