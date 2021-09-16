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

namespace UnWasm\Compiler;

use UnWasm\Compiler\Node\Type\ValueType;

/**
 * Represents the stack used during webassembly execution
 */
class ExpressionCompiler
{
    /** @var ModuleCompiler Module representation */
    public $module;

    private $stack = [];
    private $names = [];
    private $nameCnt = 1;
    private $locals = [];

    public function __construct(ModuleCompiler $module)
    {
        $this->module = $module;
    }

    public function get(int $local): void
    {
        array_push($this->names, "\$local_$local");
        array_push($this->stack, $this->locals[$local]);
    }

    public function set(int $local, ValueType $type): string
    {
        $this->locals[$local] = $type;
        return "\$local_$local";
    }

    public function const($value, ValueType $type): void
    {
        array_push($this->names, strval($value));
        array_push($this->stack, $type);
    }

    /**
     * Adds one or more new element to the stack
     *
     * @return string[] The new variable names
     */
    public function push(ValueType ...$type): array
    {
        $newVars = [];
        for ($i = 0; $i < count($type); $i++) {
            $newVars[] = '$stack_'.$this->nameCnt++;
        }

        array_push($this->names, ...$newVars);
        array_push($this->stack, ...$type);

        return $newVars;
    }

    /**
     * Pop one or more elements from the stack
     *
     * @return string[] The variable names
     */
    public function pop($cnt = 1): ?array
    {
        array_splice($this->stack, -$cnt);
        return array_splice($this->names, -$cnt);
    }

    /**
     * Peek at type of one or more elements from the stack
     *
     * @return ?ValueType[]
     */
    public function type($cnt): ?array
    {
        return array_slice($this->stack, -$cnt);
    }

    /**
     * Peek at one or more elements from the stack
     *
     * @return string[]
     */
    public function peek($cnt): ?array
    {
        return array_slice($this->names, -$cnt);
    }

    /**
     * Assert that the stack elements have a given type
     *
     * @throws \InvalidArgumentException
     */
    public function typed(ValueType $type, $cnt = 1): void
    {
        $types = $this->type($cnt);
        foreach ($types as $t) {
            if ($t->type != $type->type) {
                throw new \InvalidArgumentException('Types ('.$cnt.') mismatch: '.$t->type.' vs '.$type->type);
            }
        }
    }

    /**
     * Get the current stack size
     */
    public function count(): int
    {
        return count($this->stack);
    }
}
