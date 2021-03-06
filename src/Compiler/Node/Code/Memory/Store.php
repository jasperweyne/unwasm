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

namespace UnWasm\Compiler\Node\Code\Memory;

use UnWasm\Compiler\ExpressionCompiler;
use UnWasm\Compiler\Node\Code\Instruction;
use UnWasm\Compiler\Node\Type\ValueType;
use UnWasm\Compiler\Source;
use UnWasm\Exception\CompilationException;
use UnWasm\Exception\ParsingException;

/**
 * Store a value to memory.
 */
class Store extends Instruction
{
    /** @var ValueType The type of the returned value */
    protected $type;

    /** @var ?int The static offset in memory */
    protected $offset;

    /** @var ?int The amount of bits written to memory */
    protected $memBits;

    public function __construct(ValueType $type, int $offset, ?int $memBits = null)
    {
        $this->type = $type;
        $this->offset = $offset;

        if ($memBits !== null) {
            $this->memBits = $memBits;
        } else {
            switch ($type->type) {
                case ExpressionCompiler::I32:
                case ExpressionCompiler::F32:
                    $this->memBits = 32;
                    break;
                case ExpressionCompiler::I64:
                case ExpressionCompiler::F64:
                    $this->memBits = 64;
                    break;
                default:
                    throw new ParsingException('Invalid value type provided');
            }
        }
    }

    public function compile(ExpressionCompiler $state, Source $src): void
    {
        // assert type/update stack of value
        $state->typed($this->type);
        list($value) = $state->pop();

        // assert type/update stack of dynamic offset
        $state->typed(new ValueType(ExpressionCompiler::I32));
        list($dynOffset) = $state->pop();

        // export code
        switch ($this->type) {
            case ExpressionCompiler::I32:
            case ExpressionCompiler::I64:
                $src->write("\$this->mem_0->storeInt($value, $this->offset + $dynOffset, $this->memBits);");
                break;
            case ExpressionCompiler::F32:
            case ExpressionCompiler::F64:
                $src->write("\$this->mem_0->storeFloat($value, $this->offset + $dynOffset, $this->memBits);");
                break;
            default:
                throw new CompilationException('Invalid value type provided');
        }
    }
}
