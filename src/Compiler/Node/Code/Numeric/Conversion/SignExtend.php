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
 * Extend an inn into an imm, reinterpreting the sign.
 */
class SignExtend extends Numeric
{
    /** @var int The amount of bits to be interpreted. */
    private $bits;

    public function __construct(ValueType $type, int $bits)
    {
        parent::__construct($type);
        $this->bits = $bits;
    }

    public function compile(ExpressionCompiler $state, Source $src): void
    {
        // assert type
        $state->typed($this->type);

        // update stack
        list($x) = $state->pop();
        list($var) = $state->push($this->type);

        // export code
        $mov = 64 - $this->bits;
        $src->write("$var = ($x << $mov) >> $mov;");
    }
}
