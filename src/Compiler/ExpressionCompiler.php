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
use UnWasm\Exception\CompilationException;

/**
 * Represents the stack used during webassembly execution for a single context
 */
class ExpressionCompiler
{
    public const I32 = 'i';
    public const I64 = 'j';
    public const F32 = 'f';
    public const F64 = 'd';
    public const FUNCREF = 'r';
    public const EXTREF = 'e';

    /** @var ModuleCompiler Module representation */
    public $module;

    /** @var ?ExpressionCompiler Parent expression context */
    private $parent;

    /** @var array<string, ValueType> Dictionary that maps variable names to return types */
    private $returns;

    /** @var bool Whether this expression compiler should only compile constant expressions (disabling push) */
    private $const;

    private $stack = [];
    private $names = [];
    private $nameCnt = 1;
    private $locals = [];

    public function __construct(ModuleCompiler $module, array $returns, ?ExpressionCompiler $parent, bool $constExpr = false)
    {
        $this->module = $module;
        $this->parent = $parent;
        $this->returns = $returns;
        $this->const = $constExpr;
    }

    /**
     * Pull a local variable value and put it on the stack. This equates to:
     * `$stack_top = $local_m; // m: $local`
     */
    public function get(int $local): void
    {
        // if local has not been assigned, zero initialise
        $rootLocals = $this->root()->locals;
        if (!isset($rootLocals[$local])) {
            throw new CompilationException("Invalid local variable index $local in total of ".count($rootLocals));
        }

        array_push($this->names, "\$local_$local");
        array_push($this->stack, $rootLocals[$local]);
    }

    /**
     * Set a value to a local variable with a given type. This equates to:
     * `$local_m = ...; // m: $local`
     *
     * @return string The variable name of the local.
     */
    public function set(int $local, ValueType $type): string
    {
        $this->root()->locals[$local] = $type;
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
        if ($this->const) {
            throw new CompilationException("Can't call push() when in constant expression mode");
        }

        $newVars = [];
        for ($i = 0; $i < count($type); $i++) {
            $newVars[] = '$stack_'.$this->root()->nameCnt++;
        }

        array_push($this->names, ...$newVars);
        array_push($this->stack, ...$type);

        return $newVars;
    }

    /**
     * Pop one or more elements from the stack, where the top is the last element in the returned array
     *
     * @return string[] The variable names
     */
    public function pop($cnt = 1): ?array
    {
        if ($cnt === 0) return [];
        array_splice($this->stack, -$cnt);
        return array_splice($this->names, -$cnt);
    }

    /**
     * Peek at type of one or more elements from the stack, where the top is the last element in the returned array
     *
     * @return ?ValueType[]
     */
    public function type($cnt): ?array
    {
        if ($cnt === 0) return [];
        return array_slice($this->stack(), -$cnt);
    }

    /**
     * Peek at one or more elements from the stack, where the top is the last element in the returned array
     *
     * @return string[]
     */
    public function peek($cnt): ?array
    {
        if ($cnt === 0) return [];
        return array_slice($this->names(), -$cnt);
    }

    /**
     * Assert that the stack elements have a given type
     *
     * @throws CompilationException
     */
    public function typed(ValueType $type, $cnt = 1): void
    {
        $types = $this->type($cnt);
        foreach ($types as $t) {
            if ($t->type != $type->type) {
                throw new CompilationException('Types ('.$cnt.') mismatch: '.$t->type.' vs '.$type->type);
            }
        }
    }

    /**
     * Get the current stack size
     */
    public function count(): int
    {
        return count($this->stack());
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

    /**
     * Return the current expression context depth
     *
     * @return int
     */
    public function depth(): int
    {
        return $this->parent ? $this->parent->depth() + 1 : 0;
    }

    private function root(): self
    {
        return $this->parent ? $this->parent->root() : $this;
    }

    private function stack(): array
    {
        $top = $this->parent ? $this->parent->stack() : [];
        return array_merge($top, $this->stack);
    }

    private function names(): array
    {
        $top = $this->parent ? $this->parent->names() : [];
        return array_merge($top, $this->names);
    }
}
