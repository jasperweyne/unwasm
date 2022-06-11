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

namespace UnWasm\Compiler\Text;

use UnWasm\Compiler\ModuleCompiler;
use UnWasm\Compiler\Node\External\Import\FuncImport;
use UnWasm\Compiler\Node\External\Import\GlobalImport;
use UnWasm\Compiler\Node\External\Import\MemImport;
use UnWasm\Compiler\Node\External\Import\TableImport;
use UnWasm\Compiler\TextParser;
use UnWasm\Exception\ParsingException;

/**
 * A factory class for the imports component of a binary-format module.
 */
class ImportsBuilder implements BuilderInterface
{
    public function scan(TextParser $parser, ModuleCompiler $compiler): void
    {
        echo "Started ImportsBuilder\n";

        $compiler->imports[] = $parser->parenthesised(function (TextParser $parser) {
            $parser->expectKeyword('import');
            $module = $parser->expectString();
            $name = $parser->expectString();

            return $parser->parenthesised(function (TextParser $parser) use ($module, $name) {
                $type = $parser->expectKeyword('func', 'table', 'memory', 'global');
                $parser->expectId();

                $import = null;
                switch ($type) {
                    case 'func':
                        $typeIdx = TypesBuilder::typeuse($parser);
                        $import = new FuncImport($module, $name, $typeIdx);
                        break;
                    case 'table':
                        $type = TypesBuilder::tabletype($parser);
                        $import = new TableImport($module, $name, $type);
                        break;
                    case 'memory':
                        $type = TypesBuilder::memtype($parser);
                        $import = new MemImport($module, $name, $type);
                        break;
                    case 'global':
                        $type = TypesBuilder::globaltype($parser);
                        $import = new GlobalImport($module, $name, $type);
                        break;
                    default:
                        throw new ParsingException("Invalid import type");
                }
    
                return $import;
            });
        });

        echo 'Scanned '.count($compiler->imports)." imports\n";
    }
}
