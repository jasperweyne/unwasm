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
 * Represents the stack used during webassembly execution for a single context
 */
class ExpressionCompiler
{
    /** @var ModuleCompiler Module representation */
    public $module;

    /** @var ?ExpressionCompiler Parent expression context */
    private $parent;

    /** @var array<string, ValueType> Dictionary that maps variable names to return types */
    private $returns;

    private $stack = [];
    private $names = [];
    private $nameCnt = 1;
    private $locals = [];

    public function __construct(ModuleCompiler $module, array $returns, ?ExpressionCompiler $parent)
    {
        $this->module = $module;
        $this->parent = $parent;
        $this->returns = $returns;
    }

    /**
     * Pull a local variable value and put it on the stack. This equates to:
     * `$stack_top = $local_m; // m: $local`
     */
    public function get(int $local): void
    {
        // if local has not been assigned, zero initialise
        if (!isset($this->locals[$local])) {
            throw new \UnexpectedValueException("Invalid local variable index $local in total of ".count($this->locals));
        }

        array_push($this->names, "\$local_$local");
        array_push($this->stack, $this->locals[$local]);
    }

    /**
     * Set a value to a local variable with a given type. This equates to:
     * `$local_m = ...; // m: $local`
     * 
     * @return string The variable name of the local.
     */
    public function set(int $local, ValueType $type): string
    {
        $this->locals[$local] = $type;
        return "\$local_$local";
    }

    /**
     * Push a expression constant to the stack. This is similar to push(), but
     * avoids the need for a compiled variable assigment, and instead defers it
     * to a later point.
     */
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

    /**
     * Return the expected stack names and types as a dictionary
     *
     * @return array<string, ValueType>
     */
    public function return(int $depth = 0): array
    {
        if ($depth > 0) {
            return $this->parent->return($depth - 1);
        }
        
        return $this->returns;
    }
}
