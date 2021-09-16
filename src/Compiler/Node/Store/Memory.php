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

namespace UnWasm\Compiler\Node\Store;

use UnWasm\Compiler\ModuleCompiler;
use UnWasm\Compiler\Node\External\MemInterface;
use UnWasm\Compiler\Node\Type\MemType;
use UnWasm\Compiler\Source;

/**
 * Represents a memory instance, local to the module.
 */
class Memory implements MemInterface
{
    /** @var MemType The type descriptor for the memory */
    public $memType;

    public function __construct(MemType $type)
    {
        $this->memType = $type;
    }

    public function memType(): MemType
    {
        return $this->memType;
    }

    public function compileSetup(int $index, ModuleCompiler $module, Source $src): void
    {
        $limits = $this->memType->limits;
        $min = strval($limits->minimum);
        $max = $limits->maximum !== null ? strval($limits->maximum) : 'null';
        $src->write("\$this->mem_$index = new \UnWasm\Store\MemoryInst($min, $max);");
    }
}
