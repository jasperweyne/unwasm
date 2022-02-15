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
use UnWasm\Compiler\Node\External\Export\FuncExport;
use UnWasm\Compiler\Node\External\Export\GlobalExport;
use UnWasm\Compiler\Node\External\Export\MemExport;
use UnWasm\Compiler\Node\External\Export\TableExport;
use UnWasm\Exception\ParsingException;

/**
 * A factory class for the exports component of a binary-format module.
 */
class ExportsBuilder implements BuilderInterface
{
    public function supported(int $sectionId, BinaryParser $parser): bool
    {
        return $sectionId === 7;
    }

    public function scan(BinaryParser $parser, ModuleCompiler $compiler): void
    {
        echo "Started Exportsbuilder\n";
        $compiler->exports = $parser->expectVector(function (BinaryParser $parser) {
            $name = $parser->expectString();
            $type = $parser->expectByte(0x00, 0x03);
            $index = $parser->expectInt(true);

            $export = null;
            switch ($type) {
                case 0x00:
                    $export = new FuncExport($name, $index);
                    break;
                case 0x01:
                    $export = new TableExport($name, $index);
                    break;
                case 0x02:
                    $export = new MemExport($name, $index);
                    break;
                case 0x03:
                    $export = new GlobalExport($name, $index);
                    break;
                default:
                    throw new ParsingException("Invalid export type");
            }

            return $export;
        });

        echo 'Scanned '.count($compiler->exports)." exports\n";
    }
}
