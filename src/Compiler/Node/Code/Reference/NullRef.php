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

namespace UnWasm\Compiler\Node\Code\Reference;

use UnWasm\Compiler\ExpressionCompiler;
use UnWasm\Compiler\Node\Code\Instruction;
use UnWasm\Compiler\Node\Type\RefType;
use UnWasm\Compiler\Source;

/**
 * Adds a null reference to the stack.
 */
class NullRef extends Instruction
{
    /** @var RefType The reference type */
    private $type;

    public function __construct(RefType $type)
    {
        $this->type = $type;
    }

    public function compile(ExpressionCompiler $state, Source $src): void
    {
        // update stack
        $state->const(null, $this->type);
    }
}
