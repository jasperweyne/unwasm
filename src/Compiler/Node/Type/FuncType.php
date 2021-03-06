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

namespace UnWasm\Compiler\Node\Type;

use UnWasm\Compiler\Binary\Token;

/**
 * Describes the in/output parameter types of a function.
 */
class FuncType
{
    /** @var ValueType[] Input value types */
    private $typeIn;

    /** @var ValueType[] Output value types */
    private $typeOut;

    /**
     * @param ValueType[] $in Input variable types
     * @param ValueType[] $out Output variable types
    */
    public function __construct(array $in, array $out)
    {
        $this->typeIn = $in;
        $this->typeOut = $out;
    }

    /**
     * @return ValueType[]
     */
    public function getInput()
    {
        return $this->typeIn;
    }

    /**
     * @return ValueType[]
     */
    public function getOutput()
    {
        return $this->typeOut;
    }

    /**
     * @return string[]
     */
    public function compileOutput(string $prefix = 'x'): array
    {
        $params = [];
        foreach ($this->getOutput() as $i => $type) {
            // populate vars
            $params[] = "\$$prefix"."$i";
        }
        return $params;
    }

    /**
     * @return string[]
     */
    public function compileInput(string $prefix = 'x', bool $typed = false): array
    {
        $params = [];
        foreach ($this->getInput() as $i => $type) {
            // find type string
            $typestr = '';
            switch ($type->type) {
                case Token::INT_TYPE:
                case Token::INT_64_TYPE:
                case Token::UINT_TYPE:
                case Token::UINT_64_TYPE:
                case Token::BYTE_TYPE:
                    $typestr = 'int ';
                    break;
                case Token::FLOAT_TYPE:
                case Token::FLOAT_64_TYPE:
                    $typestr = 'float ';
                    break;
                default:
            }

            // populate vars
            $params[] = ($typed ? $typestr : '')."\$$prefix"."$i";
        }

        return $params;
    }

    public function __toString()
    {
        return implode('', $this->typeIn) . ':' . implode('', $this->typeOut);
    }
}
