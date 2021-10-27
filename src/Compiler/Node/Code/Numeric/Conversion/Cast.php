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
use UnWasm\Compiler\Node\Code\Numeric\Numeric;
use UnWasm\Compiler\Node\Type\ValueType;
use UnWasm\Compiler\Source;

/**
 * Cast (convert/trunc) an inn into an fmm or vice versa, optionally respecting the sign.
 */
class Cast extends Numeric
{
    /** @var ValueType The type of the original value */
    private $origType;

    /** @var bool Whether the operation should be performed unsigned */
    private $unsigned;

    public function __construct(ValueType $type, ValueType $origType, bool $unsigned = false)
    {
        parent::__construct($type);
        $this->origType = $origType;
        $this->unsigned = $unsigned;
    }

    public function compile(ExpressionCompiler $state, Source $src): void
    {
        switch ($this->type->type) {
            case ExpressionCompiler::I32:
            case ExpressionCompiler::I64:
                $this->compileToInt($state, $src);
                break;
            case ExpressionCompiler::F32:
            case ExpressionCompiler::F64:
                $this->compileToFloat($state, $src);
                break;
            default:
                throw new \RuntimeException('Unexpected type');
        }
    }
    
    private function compileToInt(ExpressionCompiler $state, Source $src): void
    {
        // assert type
        $state->typed($this->origType);
        switch ($this->origType->type) {
            case ExpressionCompiler::F32:
            case ExpressionCompiler::F64:
                break;
            default:
                throw new \RuntimeException('Unexpected type');
        }

        // update stack
        list($x) = $state->pop();
        list($var) = $state->push($this->type);

        // execute code
        if ($this->unsigned) {
            $src->write("$var = abs((int)($x));");
        } else {
            $src->write("$var = (int)($x);");
        }
    }

    private function compileToFloat(ExpressionCompiler $state, Source $src): void
    {
        // assert type
        $state->typed($this->origType);
        switch ($this->origType->type) {
            case ExpressionCompiler::I32:
                $bits = 31;
                break;
            case ExpressionCompiler::I64:
                $bits = 63;
                break;
            default:
                throw new \RuntimeException('Unexpected type');
        }

        // update stack
        list($x) = $state->pop();
        list($var) = $state->push($this->type);

        // execute code
        if ($this->unsigned) {
            $src->write("$var = $x < 0 ? 2 ** $bits - (float)($x) : (float)($x);");
        } else {
            $src->write("$var = (float)($x);");
        }
    }
}
