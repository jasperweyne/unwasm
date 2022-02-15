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

namespace UnWasm\Compiler\Node\Code\Numeric\Conversion;

use UnWasm\Compiler\ExpressionCompiler;
use UnWasm\Compiler\Node\Code\Numeric\Numeric;
use UnWasm\Compiler\Node\Type\ValueType;
use UnWasm\Compiler\Source;
use UnWasm\Exception\CompilationException;

/**
 * Change an fnn into an fmm.
 */
class Promote extends Numeric
{
    public function compile(ExpressionCompiler $state, Source $src): void
    {
        // prepare conversion type
        switch ($this->type) {
            case ExpressionCompiler::F32:
                $from = ExpressionCompiler::F64;
                break;
            case ExpressionCompiler::F64:
                $from = ExpressionCompiler::F32;
                break;
            default:
                throw new CompilationException('Unsupported type given');
        }

        // assert type
        $state->typed(new ValueType($from));

        // update stack
        list($x) = $state->pop();
        $state->const($x, $this->type);
    }
}
