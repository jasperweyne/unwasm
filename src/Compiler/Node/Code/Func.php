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
use UnWasm\Compiler\ModuleCompiler;
use UnWasm\Compiler\Node\Code\Control\Block;
use UnWasm\Compiler\Node\Code\Control\BranchIndirect;
use UnWasm\Compiler\Node\Code\Control\BranchUncond;
use UnWasm\Compiler\Node\Code\Control\Unreachable;
use UnWasm\Compiler\Node\External\FuncInterface;
use UnWasm\Compiler\Node\Type\FuncType;
use UnWasm\Compiler\Node\Type\ValueType;
use UnWasm\Compiler\Source;

/**
 * A function representation
 */
class Func implements FuncInterface
{
    /** @var int Function type index */
    public $typeIdx;

    /** @var ValueType[] Local variable value types */
    public $locals;

    /** @var Instruction[] The representation of the func component */
    public $body;

    /** @var int The position in the original webassembly source */
    public $position;

    /**
     * @param ValueType[] $locals Local variable value types
     * @param Instruction[] $body The representation of the func component
     */
    public function __construct(int $type, array $locals, array $body, int $position)
    {
        $this->typeIdx = $type;
        $this->locals = $locals;
        $this->body = $body;
        $this->position = $position;
    }

    public function typeIdx(): int
    {
        return $this->typeIdx;
    }

    public function compile(int $index, ModuleCompiler $module, Source $src): void
    {
        // prepare input/output parameters
        /** @var FuncType */
        $functype = $module->types[$this->typeIdx];
        $input = implode(", ", $functype->compileInput('local_', true));
        $output = array_combine($functype->compileOutput('ret_'), $functype->getOutput());

        // write function header
        $pos = str_pad(dechex($this->position), 8, '0', STR_PAD_LEFT);
        $src
            ->write("\$this->fn_$index = function ($input) { // 0x$pos")
            ->indent()
        ;

        // setup expression compiler
        $expr = new ExpressionCompiler($module, $output, null);
        $localVars = array_merge($functype->getInput(), $this->locals);
        foreach ($localVars as $i => $local) {
            $local = $expr->set($i, $local);

            // zero initialise locals
            if ($i >= count($functype->getInput())) {
                $src->write("$local = 0;");
            }
        }

        // start function content
        $src->write('do {')->indent();

        // write function body
        foreach ($this->body as $instr) {
            $instr->compile($expr, $src);
            $expr->previous = $instr;
        }

        // write returnvars
        if (!($expr->previous instanceof BranchUncond || $expr->previous instanceof BranchIndirect || $expr->previous instanceof Unreachable)) {
            $stackVars = $expr->pop(count($expr->return()));
            Block::compileReturn($src, $expr->return(), $stackVars);
        }

        // write function footer
        $output = implode(", ", array_keys($output));
        $src
            ->outdent()
            ->write('} while (0);')
            ->write()
            ->write("return array($output);")
            ->outdent()
            ->write('};')
            ->write()
        ;
    }

    public function compileSetup(int $index, ModuleCompiler $module, Source $src): void
    {
        // no setup required, empty
    }
}
