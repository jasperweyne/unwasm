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

namespace UnWasm\Compiler\Node\Code\Table;

use UnWasm\Compiler\ExpressionCompiler;
use UnWasm\Compiler\Node\Code\Instruction;
use UnWasm\Compiler\Source;

/**
 * Drop a data instance from memory.
 */
class Drop extends Instruction
{
    /** @var int The element index */
    protected $elemIdx;

    public function __construct(int $elemIdx)
    {
        $this->elemIdx = $elemIdx;
    }

    public function compile(ExpressionCompiler $state, Source $src): void
    {
        // export code
        $src->write("unset(\$this->elems[$this->elemIdx]);");
    }
}
