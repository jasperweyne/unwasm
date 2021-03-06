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

namespace UnWasm\Compiler\Node\Code\Control;

use UnWasm\Compiler\Node\External\FuncInterface;
use UnWasm\Compiler\Node\Type\FuncType;
use UnWasm\Compiler\Node\Code\Instruction;
use UnWasm\Compiler\ExpressionCompiler;
use UnWasm\Compiler\Source;

/**
 * Calls a function.
 */
class Call extends Instruction
{
    /** @var int The called function index */
    private $funcIdx;

    public function __construct(int $funcIdx)
    {
        $this->funcIdx = $funcIdx;
    }

    public function compile(ExpressionCompiler $state, Source $src): void
    {
        // Find the type signature for the function
        /** @var FuncInterface */
        $func = $state->module->func($this->funcIdx);

        /** @var FuncType */
        $type = $state->module->types[$func->typeIdx()];

        // Replace the stack input with output
        $input = implode(", ", $state->pop(count($type->getInput())));
        $output = implode(", ", $state->push(...$type->getOutput()));

        // Write the function call to the source
        $func = $this->funcIdx;
        $assign = $output ? "list($output) = " : '';
        $src->write($assign."(\$this->fn_$func)($input);");
    }
}
