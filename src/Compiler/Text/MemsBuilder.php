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

namespace UnWasm\Compiler\Text;

use UnWasm\Compiler\TextParser;
use UnWasm\Compiler\ModuleCompiler;
use UnWasm\Compiler\Node\Store\Memory;

/**
 * A factory class for the mems component of a binary-format module.
 */
class MemsBuilder implements BuilderInterface
{
    public function supported(): string
    {
        return 'memory';
    }

    public function scan(TextParser $parser, ModuleCompiler $compiler): void
    {
        echo "Started MemsBuilder\n";

        // parse section contents
        $parser->expectId();
        // todo: add abbreviations
        $compiler->mems[] = $this->mem($parser);

        echo 'Scanned '.count($compiler->mems)." mems\n";
    }

    private function mem(TextParser $parser): Memory
    {
        $type = TypesBuilder::memtype($parser);
        return new Memory($type);
    }
}
