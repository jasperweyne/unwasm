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

namespace UnWasm\Compiler\Node\External\Import;

use UnWasm\Compiler\ModuleCompiler;
use UnWasm\Compiler\Node\External\TableInterface;
use UnWasm\Compiler\Node\Type\TableType;
use UnWasm\Compiler\Source;

/**
 * Represents a table import from an external module.
 */
class TableImport extends Import implements TableInterface
{
    /** @var TableType The type descriptor for the table */
    public $tableType;

    public function __construct(string $module, string $name, TableType $tableType)
    {
        parent::__construct($module, $name);

        $this->tableType = $tableType;
    }

    public function tableType(): TableType
    {
        return $this->tableType;
    }

    public function compileSetup(int $index, ModuleCompiler $module, Source $source): void
    {
        // todo: validate types
        $ref = $module->importRefs[$this->module];
        $source->write("\$this->table_$index = \$this->ref_$ref"."->tables['$this->name'];");
    }
}
