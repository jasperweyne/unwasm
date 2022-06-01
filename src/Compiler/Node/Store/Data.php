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
use UnWasm\Compiler\Node\Code\Instruction;
use UnWasm\Compiler\Source;

/**
 * Represents a data segment for memory initialization.
 */
class Data
{
    /** @var int The memory index the data segment refers to */
    public $memIdx;

    /** @var ?Instruction[] Value initiation expression */
    private $initExpr;

    /** @var string Value initialization data */
    private $initData;

    /** @param ?Instruction[] $initExpr */
    public function __construct(string $initData, int $memIdx = 0, ?array $initExpr = null)
    {
        $this->memIdx = $memIdx;
        $this->initExpr = $initExpr;
        $this->initData = $initData;
    }

    public function compileSetup(int $index, ModuleCompiler $module, Source $src): void
    {
        $encoded = base64_encode($this->initData);
        if ($this->initExpr) {
            // setup expression compiler
            $expr = new ExpressionCompiler($module, [], null, true);
            foreach ($this->initExpr as $instr) {
                $instr->compile($expr, new Source());
            }
            // todo: verify stack
            list($offset) = $expr->pop();
            $src->write("\$this->mem_$this->memIdx->write(base64_decode('$encoded'), $offset); // datas[$index]");
        } else {
            $src->write("\$this->datas[$index] = base64_decode('$encoded');");
        }
    }
}
