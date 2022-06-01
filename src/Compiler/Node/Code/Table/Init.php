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

namespace UnWasm\Compiler\Node\Code\Table;

use UnWasm\Compiler\ExpressionCompiler;
use UnWasm\Compiler\Node\Code\Instruction;
use UnWasm\Compiler\Node\Type\ValueType;
use UnWasm\Compiler\Source;

/**
 * Initialise a region of a table with elements.
 */
class Init extends Instruction
{
    /** @var int The table index */
    private $tableIdx;

    /** @var int The element index */
    private $elemIdx;

    public function __construct(int $tableIdx, int $elemIdx)
    {
        $this->tableIdx = $tableIdx;
        $this->elemIdx = $elemIdx;
    }

    public function compile(ExpressionCompiler $state, Source $src): void
    {
        // assert type
        $state->typed(new ValueType(ExpressionCompiler::I32), 3);

        // update stack
        list($destOffset, $srcOffset, $n) = $state->pop(3);

        // export code
        $src->write("\$this->table_$this->tableIdx->overwrite(array_slice(\$this->elems[$this->elemIdx], $srcOffset, $n), $destOffset);");
    }
}
