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
use UnWasm\Compiler\ExpressionCompiler;
use UnWasm\Compiler\ModuleCompiler;
use UnWasm\Compiler\Node\Type\FuncType;
use UnWasm\Compiler\Node\Type\GlobalType;
use UnWasm\Compiler\Node\Type\Limits;
use UnWasm\Compiler\Node\Type\MemType;
use UnWasm\Compiler\Node\Type\RefType;
use UnWasm\Compiler\Node\Type\TableType;
use UnWasm\Compiler\Node\Type\ValueType;
use UnWasm\Exception\ParsingException;

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
            $valueType = self::valuetype($parser);
            if (!$valueType) {
                throw new ParsingException('Invalid value type in result type');
            }
            return $valueType;
        });
    }

    public static function valuetype(BinaryParser $parser, ?int $type = null): ?ValueType
    {
        $type = $type ?? $parser->expectInt(false, 7);
        $mapping = [
            Token::INT_TYPE => ExpressionCompiler::I32,
            Token::INT_64_TYPE => ExpressionCompiler::I64,
            Token::FLOAT_TYPE => ExpressionCompiler::F32,
            Token::FLOAT_64_TYPE => ExpressionCompiler::F64,
            Token::FUNCREF_TYPE => ExpressionCompiler::FUNCREF,
            Token::EXTREF_TYPE => ExpressionCompiler::EXTREF,
        ];

        $mapped = $mapping[$type];
        switch ($mapped) {
            case ExpressionCompiler::I32:
            case ExpressionCompiler::I64:
            case ExpressionCompiler::F32:
            case ExpressionCompiler::F64:
                return new ValueType($mapped);
            case ExpressionCompiler::EXTREF:
            case ExpressionCompiler::FUNCREF:
                return new RefType($mapped);
            default:
                return null;
        }
    }

    public static function reftype(BinaryParser $parser): RefType
    {
        $mapping = [
            Token::EXTREF_TYPE => ExpressionCompiler::EXTREF,
            Token::FUNCREF_TYPE => ExpressionCompiler::FUNCREF,
        ];

        $type = $mapping[$parser->expectInt(false, 8)];
        return new RefType($type);
    }

    public static function tabletype(BinaryParser $parser): TableType
    {
        $encoding = self::reftype($parser);
        return new TableType($encoding, self::limits($parser));
    }

    public static function memtype(BinaryParser $parser): MemType
    {
        return new MemType(self::limits($parser));
    }

    public static function globaltype(BinaryParser $parser): GlobalType
    {
        $valType = self::valuetype($parser);
        if (!$valType) {
            throw new \InvalidArgumentException("Unknown value type.");
        }
        $mut = $parser->expectByte(0x00, 0x01) === 0x01;
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
