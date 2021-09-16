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

namespace UnWasm\Compiler\Node\External\Import;

use UnWasm\Compiler\ModuleCompiler;
use UnWasm\Compiler\Node\External\GlobalInterface;
use UnWasm\Compiler\Node\Type\GlobalType;
use UnWasm\Compiler\Source;

/**
 * Represents a global import from an external module.
 */
class GlobalImport extends Import implements GlobalInterface
{
    /** @var GlobalType The type descriptor for the global */
    public $globalType;

    public function __construct(string $module, string $name, GlobalType $globalType)
    {
        parent::__construct($module, $name);

        $this->globalType = $globalType;
    }

    public function globalType(): GlobalType
    {
        return $this->globalType;
    }

    public function compileSetup(int $index, ModuleCompiler $module, Source $source): void
    {
        // todo: validate types
        $ref = $module->importRefs[$this->module];
        $source->write("\$this->global_$index = \$this->ref_$ref"."->global('$this->name');");
    }
}
