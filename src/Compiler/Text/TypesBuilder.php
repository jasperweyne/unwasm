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

namespace UnWasm\Compiler\Text;

use UnWasm\Compiler\ExpressionCompiler;
use UnWasm\Compiler\TextParser;
use UnWasm\Compiler\ModuleCompiler;
use UnWasm\Compiler\Node\Type\FuncType;
use UnWasm\Compiler\Node\Type\Limits;
use UnWasm\Compiler\Node\Type\MemType;
use UnWasm\Compiler\Node\Type\ValueType;

/**
 * A factory class for the types component of a text-format module.
 */
class TypesBuilder implements BuilderInterface
{
    public function supported(): string
    {
        return 'type';
    }

    public function scan(TextParser $parser, ModuleCompiler $compiler): void
    {
        echo "Started TypesBuilder\n";

        // parse section contents
        $parser->expectId();
        $compiler->types[] = self::functype($parser);

        echo 'Scanned '.count($compiler->types)." types\n";
    }

    public static function functype(TextParser $parser): FuncType
    {
        $parser->expectOpen();
        $parser->expectKeyword('func');
        $in = self::resulttype($parser, true);
        $out = self::resulttype($parser, false);
        $parser->expectClose();
        return new FuncType($in, $out);
    }

    /**
     * @return ValueType[]
     */
    public static function resulttype(TextParser $parser, bool $param): array
    {
        return array_merge([], $parser->vec(function (TextParser $parser) use ($param) {
            $parser->expectOpen();
            $parser->expectKeyword($param ? 'param' : 'result');
            if ($param) {
                $parser->expectId();
            }
            $types = $parser->vec([self::class, 'valuetype']);
            $parser->expectClose();
            return $types;
        }));
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

    public static function memtype(TextParser $parser): MemType
    {
        return new MemType(self::limits($parser));
    }

    private static function limits(TextParser $parser): Limits
    {
        $minimum = $parser->expectInt(true);
        $maximum = null;
        $parser->maybe(function (TextParser $parser) use (&$maximum) {
            $maximum = $parser->expectInt(true);
        });
        return new Limits($minimum, $maximum);
    }
}
