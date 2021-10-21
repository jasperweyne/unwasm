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

namespace UnWasm\Compiler\Node\Code\Memory;

use UnWasm\Compiler\Binary\Token;
use UnWasm\Compiler\ExpressionCompiler;
use UnWasm\Compiler\Node\Code\Instruction;
use UnWasm\Compiler\Node\Type\ValueType;
use UnWasm\Compiler\Source;

/**
 * Load a value from memory.
 */
class Load extends Instruction
{
    /** @var ValueType The type of the returned value */
    protected $type;

    /** @var ?int The static offset in memory */
    protected $offset;

    /** @var ?int The amount of bits read from memory */
    protected $memBits;

    /** @var ?bool Whether the raw value is interpreted as signed or unsigned */
    protected $signed;

    public function __construct(ValueType $type, int $offset, ?int $memBits = null, ?bool $signed = null)
    {
        $this->type = $type;
        $this->offset = $offset;
        $this->signed = $signed ?? true;

        if ($memBits !== null) {
            $this->memBits = $memBits;
        } else {
            switch ($type->type) {
                case Token::INT_TYPE:
                case Token::FLOAT_TYPE:
                    $this->memBits = 32;
                    break;
                case Token::INT_64_TYPE:
                case Token::FLOAT_64_TYPE:
                    $this->memBits = 64;
                    break;
                default:
                    throw new \InvalidArgumentException('Invalid value type provided');
            }
        }
    }

    public function compile(ExpressionCompiler $state, Source $src): void
    {
        // assert type
        $state->typed(new ValueType(Token::INT_TYPE));

        // update stack
        list($dynOffset) = $state->pop();
        list($value) = $state->push($this->type);

        // export code
        switch ($this->type) {
            case Token::INT_TYPE:
            case Token::INT_64_TYPE:
                $src->write("$value = \$this->mem_0->loadInt($this->offset + $dynOffset, $this->memBits, $this->signed);");
                break;
            case Token::FLOAT_TYPE:
            case Token::FLOAT_64_TYPE:
                $src->write("$value = \$this->mem_0->loadFloat($this->offset + $dynOffset, $this->memBits);");
                break;
            default:
                throw new \InvalidArgumentException('Invalid value type provided');
        }
    }
}
