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

namespace UnWasm\Compiler\Node\Store;

use UnWasm\Compiler\ExpressionCompiler;
use UnWasm\Compiler\ModuleCompiler;
use UnWasm\Compiler\Node\Type\ValueType;
use UnWasm\Compiler\Source;

/**
 * Represents an element segment for table initialization.
 */
class Element
{
    /** @var int Defines the element mode */
    public $mode;

    /** @var Instruction[][] Value initialization data */
    private $initExpressions;

    /** @var ?int The table index the element segment refers to */
    public $tableIdx;

    /** @var ?Instruction[] Table offset location expression */
    private $offsetExpr;

    protected function __construct(int $mode, array $initExpressions, ?int $tableIdx = null, ?array $offsetExpr = null)
    {
        $this->tableIdx = $tableIdx;
        $this->offsetExpr = $offsetExpr;
        $this->initExpressions = $initExpressions;
    }

    public function compileSetup(int $index, ModuleCompiler $module, Source $src): void
    {
        // compile initExpressions data
        $data = [];
        foreach ($this->initExpressions as $initExpr) {
            $expr = new ExpressionCompiler($module, [], null, true);
            foreach ($initExpr as $instr) {
                $instr->compile($expr, new Source());
            }
            // todo: verify stack
            list($const) = $expr->pop();
            $data[] = $const.',';
        }

        // compile source
        if ($this->tableIdx !== null) {
            // get offset constant
            $expr = new ExpressionCompiler($module, [], null, true);
            foreach ($this->offsetExpr as $instr) {
                $instr->compile($expr, new Source());
            }
            $expr->typed(new ValueType(ExpressionCompiler::I32));
            list($offset) = $expr->pop();
            $src
                ->write("\$this->table_$this->tableIdx->overwrite([")
                ->indent()
                ->lines(...$data)
                ->outdent()
                ->write("], $offset); // elems[$index]");
        } else {
            $src
                ->write("\$this->elems[$index] = [")
                ->indent()
                ->lines(...$data)
                ->outdent()
                ->write(";")
            ;
        }
    }

    public static function active(array $initExpr, int $tableIdx = null, array $offsetExpr = null)
    {
        return new self(0, $initExpr, $tableIdx, $offsetExpr);
    }

    public static function passive(array $initExpr)
    {
        return new self(1, $initExpr);
    }

    public static function declarative(array $initExpr)
    {
        return new self(2, $initExpr);
    }
}
