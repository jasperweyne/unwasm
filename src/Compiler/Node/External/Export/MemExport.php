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

namespace UnWasm\Compiler\Node\External\Export;

use UnWasm\Compiler\ModuleCompiler;
use UnWasm\Compiler\Source;

/**
 * Represents a memory export by the module.
 */
class MemExport extends Export
{
    /** @var int The memory definition index */
    private $memIdx;

    public function __construct(string $name, int $memIdx)
    {
        parent::__construct($name);

        $this->memIdx = $memIdx;
    }

    public function compileSetup(int $index, ModuleCompiler $module, Source $source): void
    {
        $source->write("\$this->mems['$this->name'] = \$this->mem_$this->memIdx;");
    }

    public function compile(ModuleCompiler $module, Source $src): void
    {
        // todo
    }
}
