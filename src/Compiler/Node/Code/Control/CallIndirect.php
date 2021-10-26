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

namespace UnWasm\Compiler\Node\Code\Control;

use UnWasm\Compiler\Binary\Token;
use UnWasm\Compiler\Node\Code\Instruction;
use UnWasm\Compiler\ExpressionCompiler;
use UnWasm\Compiler\Node\Type\ValueType;
use UnWasm\Compiler\Source;

/**
 * Calls a function indirectly through a table.
 */
class CallIndirect extends Instruction
{
    /** @var int The called function index */
    private $tableIdx;

    /** @var int The called function index */
    private $typeIdx;

    public function __construct(int $tableIdx, int $typeIdx)
    {
        $this->tableIdx = $tableIdx;
        $this->typeIdx = $typeIdx;
    }

    public function compile(ExpressionCompiler $state, Source $src): void
    {
        // Find the type signature for the function
        $type = $state->module->types[$this->typeIdx];

        // get offset
        $state->typed(new ValueType(ExpressionCompiler::I32));
        list($offset) = $state->pop();

        // Replace the stack input with output
        $input = implode(", ", $state->pop(count($type->getInput())));
        $output = implode(", ", $state->push(...$type->getOutput()));

        // Write the function call to the source
        // todo: dynamically verify signature
        $assign = $output ? "list($output) = " : '';
        $src
            ->write("\$indirect = \$this->table_$this->tableIdx[$offset];")
            ->write("if (\$indirect === null) throw new \RuntimeException('Problem occurred');")
            ->write($assign."(\$indirect)($input);")
        ;
    }
}
