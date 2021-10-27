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

namespace UnWasm\Compiler\Node\Code\Control;

use UnWasm\Compiler\Node\Code\Instruction;
use UnWasm\Compiler\ExpressionCompiler;
use UnWasm\Compiler\Node\Type\ValueType;
use UnWasm\Compiler\Source;

/**
 * Branch indirectly through multiple provided options.
 */
class BranchIndirect extends Instruction
{
    /** @var int[] The branching depth options */
    private $options;
    
    /** @var int The default branching depth */
    private $default;

    public function __construct(array $options, int $default)
    {
        $this->options = $options;
        $this->default = $default;
    }

    public function compile(ExpressionCompiler $state, Source $src): void
    {
        $state->typed(new ValueType(ExpressionCompiler::I32));
        list($index) = $state->pop();

        // start branch
        $src
            ->write("switch ($index) {")
            ->indent()
        ;

        // write label options
        foreach ($this->options as $i => $option) {
            $depth = $option + 2;
            $return = $state->return($option);
            $stackVars = $state->peek(count($return));

            $src->write("case $i:")->indent();
            Block::compileReturn($src, $return, $stackVars);
            $src->write("continue $depth;")->outdent();
        }

        // write default option
        $defaultDepth = $this->default + 2;
        $return = $state->return($this->default);
        $stackVars = $state->peek(count($return));
        
        $src->write("default:")->indent();
        Block::compileReturn($src, $return, $stackVars);
        $src->write("continue $defaultDepth;")->outdent();

        // close branch
        $src->outdent()->write('}');
    }
}
