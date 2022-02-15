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

namespace UnWasm\Compiler\Binary;

use UnWasm\Compiler\BinaryParser;
use UnWasm\Compiler\ModuleCompiler;
use UnWasm\Compiler\Node\External\Import\FuncImport;
use UnWasm\Compiler\Node\External\Import\GlobalImport;
use UnWasm\Compiler\Node\External\Import\MemImport;
use UnWasm\Compiler\Node\External\Import\TableImport;
use UnWasm\Exception\ParsingException;

/**
 * A factory class for the imports component of a binary-format module.
 */
class ImportsBuilder implements BuilderInterface
{
    public function supported(int $sectionId, BinaryParser $parser): bool
    {
        return $sectionId === 2;
    }

    public function scan(BinaryParser $parser, ModuleCompiler $compiler): void
    {
        echo "Started ImportsBuilder\n";
        $compiler->imports = $parser->expectVector(function (BinaryParser $parser) {
            $module = $parser->expectString();
            $name = $parser->expectString();
            $type = $parser->expectByte(0x00, 0x03);

            $import = null;
            switch ($type) {
                case 0x00:
                    $typeIdx = $parser->expectInt(true);
                    $import = new FuncImport($module, $name, $typeIdx);
                    break;
                case 0x01:
                    $type = TypesBuilder::tabletype($parser);
                    $import = new TableImport($module, $name, $type);
                    break;
                case 0x02:
                    $type = TypesBuilder::memtype($parser);
                    $import = new MemImport($module, $name, $type);
                    break;
                case 0x03:
                    $type = TypesBuilder::globaltype($parser);
                    $import = new GlobalImport($module, $name, $type);
                    break;
                default:
                    throw new ParsingException("Invalid import type");
            }

            return $import;
        });

        echo 'Scanned '.count($compiler->imports)." imports\n";
    }
}
