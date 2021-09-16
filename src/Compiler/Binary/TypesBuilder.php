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
use UnWasm\Compiler\Node\Type\FuncType;
use UnWasm\Compiler\Node\Type\GlobalType;
use UnWasm\Compiler\Node\Type\Limits;
use UnWasm\Compiler\Node\Type\MemType;
use UnWasm\Compiler\Node\Type\RefType;
use UnWasm\Compiler\Node\Type\TableType;
use UnWasm\Compiler\Node\Type\ValueType;

/**
 * A factory class for the types component of a binary-format module.
 */
class TypesBuilder implements BuilderInterface
{
    public function supported(int $sectionId, BinaryParser $parser): bool
    {
        return $sectionId === 1;
    }

    public function scan(BinaryParser $parser, ModuleCompiler $compiler): void
    {
        echo "Started TypesBuilder\n";

        // parse section contents
        $compiler->types = $parser->expectVector(function (BinaryParser $parser) {
            return self::functype($parser);
        });

        echo 'Scanned '.count($compiler->types)." types\n";
    }

    public static function functype(BinaryParser $parser): FuncType
    {
        $parser->expectByte(0x60); // magic
        $in = self::resulttype($parser);
        $out = self::resulttype($parser);
        return new FuncType($in, $out);
    }

    /**
     * @return ValueType[]
     */
    public static function resulttype(BinaryParser $parser): array
    {
        return $parser->expectVector(function (BinaryParser $parser) {
            return new ValueType($parser->expectByte()); // todo: this ought to be expectInt
        });
    }

    public static function tabletype(BinaryParser $parser): TableType
    {
        $type = $parser->expectByte();
        $encoding = new RefType($type);
        return new TableType($encoding, self::limits($parser));
    }

    public static function memtype(BinaryParser $parser): MemType
    {
        return new MemType(self::limits($parser));
    }

    public static function globaltype(BinaryParser $parser): GlobalType
    {
        $type = $parser->expectByte();
        $mut = $parser->expectByte(0x00, 0x01) === 0x01;
        $valType = new ValueType($type);
        return new GlobalType($valType, $mut);
    }

    private static function limits(BinaryParser $parser): Limits
    {
        $hasMaximum = $parser->expectByte(0x00, 0x01) === 0x01;
        $minimum = $parser->expectInt(true);
        $maximum = $hasMaximum ? $parser->expectInt(true) : null;
        return new Limits($minimum, $maximum);
    }
}
