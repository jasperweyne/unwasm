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

use UnWasm\Compiler\ExpressionCompiler;
use UnWasm\Compiler\TextParser;
use UnWasm\Compiler\ModuleCompiler;
use UnWasm\Compiler\Node\Type\FuncType;
use UnWasm\Compiler\Node\Type\GlobalType;
use UnWasm\Compiler\Node\Type\Limits;
use UnWasm\Compiler\Node\Type\MemType;
use UnWasm\Compiler\Node\Type\RefType;
use UnWasm\Compiler\Node\Type\TableType;
use UnWasm\Compiler\Node\Type\ValueType;

/**
 * A factory class for the types component of a text-format module.
 */
class TypesBuilder implements BuilderInterface
{
    public function scan(TextParser $parser, ModuleCompiler $compiler): void
    {
        echo "Started TypesBuilder\n";

        // parse section contents
        $compiler->types[] = $parser->parenthesised(function (TextParser $parser) {
            $parser->expectKeyword('type');
            /** $name = */ $parser->expectId();
            return self::functype($parser);
        });

        echo 'Scanned '.count($compiler->types)." types\n";
    }

    /**
     * todo: this actually returns an int|string
     */
    public static function typeuse(TextParser $parser): int
    {
        return $parser->parenthesised(function (TextParser $parser) {
            $parser->expectKeyword('type');
            return $parser->oneOf(function (TextParser $parser) {
                return $parser->expectInt();
            }, function (TextParser $parser) {
                return (string) $parser->expectId(false);
            });
        });
    }

    public static function functype(TextParser $parser): FuncType
    {
        return $parser->parenthesised(function (TextParser $parser) {
            $parser->expectKeyword('func');
            $params = $parser->vec([self::class, 'param']);
            $results = $parser->vec([self::class, 'result']);
            return new FuncType($params, $results);
        });
    }

    public static function param(TextParser $parser): ValueType
    {
        return $parser->parenthesised(function (TextParser $parser) {
            $parser->expectKeyword('param');
            $parser->expectId();
            return self::valuetype($parser);
        });
    }

    public static function result(TextParser $parser): ValueType
    {
        return $parser->parenthesised(function (TextParser $parser) {
            $parser->expectKeyword('result');
            return self::valuetype($parser);
        });
    }

    public static function valuetype(TextParser $parser): ValueType
    {
        $type = $parser->expectKeyword('i32', 'i64', 'f32', 'f64');
        switch ($type) {
            case 'i32':
                return new ValueType(ExpressionCompiler::I32);
            case 'i64':
                return new ValueType(ExpressionCompiler::I64);
            case 'f32':
                return new ValueType(ExpressionCompiler::F32);
            case 'f64':
                return new ValueType(ExpressionCompiler::F64);
            default:
                throw new \UnexpectedValueException('Unknown value type given');
        }
    }
    
    public static function reftype(TextParser $parser): RefType
    {
        $mapping = [
            'externref' => ExpressionCompiler::EXTREF,
            'funcref' => ExpressionCompiler::FUNCREF,
        ];

        $type = $mapping[$parser->expectKeyword('funcref', 'externref')];
        return new RefType($type);
    }

    public static function tabletype(TextParser $parser): TableType
    {
        $lim = self::limits($parser);
        $encoding = self::reftype($parser);
        return new TableType($encoding, $lim);
    }

    public static function memtype(TextParser $parser): MemType
    {
        return new MemType(self::limits($parser));
    }
    
    public static function globaltype(TextParser $parser): GlobalType
    {
        return $parser->oneOf(function (TextParser $parser) {
            return new GlobalType(self::valuetype($parser), false);
        }, function (TextParser $parser) {
            return $parser->parenthesised(function (TextParser $parser) {
                $parser->expectKeyword('mut');
                return new GlobalType(self::valuetype($parser), true);
            });
        });
    }

    private static function limits(TextParser $parser): Limits
    {
        $minimum = $parser->expectInt(true);
        $maximum = $parser->maybe(function (TextParser $parser) {
            return $parser->expectInt(true);
        });
        return new Limits($minimum, $maximum);
    }
}
