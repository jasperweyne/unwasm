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

namespace UnWasm\Compiler\Binary;

use UnWasm\Compiler\BinaryParser;
use UnWasm\Compiler\ModuleCompiler;
use UnWasm\Compiler\Node\Code\Reference\Func;
use UnWasm\Compiler\Node\Store\Element;

/**
 * A factory class for the element segments component of a binary-format module.
 */
class ElemsBuilder implements BuilderInterface
{
    public function supported(int $sectionId, BinaryParser $parser): bool
    {
        return $sectionId === 9;
    }

    public function scan(BinaryParser $parser, ModuleCompiler $compiler): void
    {
        echo "Started ElemssBuilder\n";

        // parse section contents
        $compiler->elems = $parser->expectVector(function (BinaryParser $parser) {
            return $this->elem($parser);
        });

        echo 'Scanned '.count($compiler->globals)." element segs\n";
    }

    private function elem(BinaryParser $parser): Element
    {
        $bitfield = $parser->expectByte(0x00, 0x07);
        $tableIdx = 0;
        $offsetExpr = null;
        $initExpr = null;

        // decompose bitfields
        $isActive = !($bitfield & 0x01);
        $indexExplicit = $bitfield & 0x02;
        $hasExpression = $bitfield & 0x04;

        // if active and has explicit table index, populate it
        if ($isActive && $indexExplicit) { 
            $tableIdx = $parser->expectInt(true);  
        }

        // if active, populate offset expression
        if ($isActive) {
            $offsetExpr = FuncsBuilder::expression($parser);
        }

        // if element kind explicit, populate
        if (!$isActive && !$indexExplicit) {
            $parser->expectByte(0x00); // wasm 1.1: always funcref
        }
        
        // populate initialization expression array
        if ($hasExpression) { 
            $initExpr = $parser->expectVector(function (BinaryParser $parser) {
                return FuncsBuilder::expression($parser);
            });
        } else {
            $initExpr = $parser->expectVector(function (BinaryParser $parser) {
                return [new Func($parser->expectInt(true))];
            });
        }

        // differ modes
        if ($isActive) {
            return Element::active($initExpr, $tableIdx, $offsetExpr);
        } else if ($indexExplicit) {
            return Element::declarative($initExpr);
        } else {
            return Element::passive($initExpr);
        }
    }
}
