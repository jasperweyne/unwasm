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

namespace UnWasm\Compiler\Node\Code;

use UnWasm\Compiler\ExpressionCompiler;
use UnWasm\Compiler\Source;

/**
 * Represents a single opcode/instruction, to be executed when running the
 * WebAssembly code. Compiles the instruction to native PHP code.
 */
abstract class Instruction
{
    /** @var int Position in the original webassembly code */
    public $position;

    abstract public function compile(ExpressionCompiler $state, Source $src): void;
}
