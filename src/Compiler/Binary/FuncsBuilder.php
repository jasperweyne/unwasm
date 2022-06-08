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
use UnWasm\Compiler\Node\Code;
use UnWasm\Compiler\Node\Type\FuncType;
use UnWasm\Compiler\Node\Type\ValueType;
use UnWasm\Exception\ParsingException;

/**
 * A factory class for the types component of a binary-format module.
 */
class FuncsBuilder implements BuilderInterface
{
    /** @var int[] Types */
    private $funcTypes = null;

    public function supported(int $sectionId, BinaryParser $parser): bool
    {
        return $sectionId === 3 || $sectionId === 10;
    }

    public function scan(BinaryParser $parser, ModuleCompiler $compiler): void
    {
        echo "Started FuncsBuilder\n";
        if ($this->funcTypes === null) {
            // assume function section
            $this->funcTypes = $parser->expectVector(function (BinaryParser $parser) {
                return $parser->expectInt(true);
            });
            echo 'Scanned '.count($this->funcTypes)." functypes\n";
        } else {
            // assume code section
            $compiler->funcs = $parser->expectVector(function (BinaryParser $parser, int $index) {
                $pos = $parser->position();
                return $parser->assertSize(function (BinaryParser $parser) use ($index, $pos) {
                    // create array of arrays of local variables
                    $locals = $parser->expectVector(function (BinaryParser $parser) {
                        $n = $parser->expectInt(true);
                        $type = TypesBuilder::valuetype($parser);
                        return array_fill(0, $n, $type);
                    });

                    $expr = self::expression($parser);

                    return new Code\Func($this->funcTypes[$index], array_merge([], ...$locals), $expr, $pos);
                });
            });
            echo 'Scanned '.count($compiler->funcs)." funcs\n";
        }
    }

    /**
     * @return Code\Instruction[] The (ordered) list of parsed instructions.
     */
    public static function expression(BinaryParser $parser, bool $constExpr = false, int $termOpcode = 0x0B): array
    {
        $instructions = [];
        while (!$parser->eof()) {
            $pos = $parser->position();
            $opcode = $parser->expectByte();
            if ($opcode === $termOpcode) {
                return $instructions;
            }

            $instruction =
                self::controlInstr($opcode, $parser, $constExpr, $termOpcode) ??
                self::varInstr($opcode, $parser) ??
                self::memoryInstr($opcode, $parser) ??
                self::constInstr($opcode, $parser) ??
                self::compInstr($opcode) ??
                self::computeInstr($opcode) ??
                self::convertInstr($opcode) ??
                self::miscInstr($opcode, $parser)
            ;
            $instruction->position = $pos;
            $instructions[] = $instruction;
        }

        throw new \InvalidArgumentException('Missing termination opcode');
    }

    /**
     * @return array{?FuncType, ?int}
     */
    private static function parseBlocktype(BinaryParser $parser): array
    {
        $raw = $parser->expectInt(false, 33);
        if ($raw === -64) { // 0x40
            return [new FuncType([], []), null];
        } elseif ($type = TypesBuilder::valuetype($parser, $raw)) {
            return [new FuncType([], [$type]), null];
        } elseif ($raw >= 0) {
            return [null, $raw];
        }

        throw new \InvalidArgumentException('Invalid block type');
    }

    private static function controlInstr(int $opcode, BinaryParser $parser, bool $constExpr, int $termOpcode): ?Code\Instruction
    {
        $instruction = null;
        switch ($opcode) {
            case 0x00:
                $instruction = new Code\Control\Unreachable();
                break;
            case 0x01:
                $instruction = new Code\Control\Nop($parser->position() - 1);
                break;
            case 0x02:
                list($functype, $typeIdx) = self::parseBlocktype($parser);
                $inner = self::expression($parser, $constExpr, $termOpcode);
                $instruction = new Code\Control\Block($inner, $functype, $typeIdx);
                break;
            case 0x03:
                list($functype, $typeIdx) = self::parseBlocktype($parser);
                $inner = self::expression($parser, $constExpr, $termOpcode);
                $instruction = new Code\Control\Loop($inner, $functype, $typeIdx);
                break;
            case 0x04:
                list($functype, $typeIdx) = self::parseBlocktype($parser);
                $inner = self::expression($parser, $constExpr, $termOpcode);
                $instruction = new Code\Control\IfElse($inner, $functype, $typeIdx);
                break;
            case 0x05:
                $instruction = new Code\Control\ElseStmt();
                break;
            case 0x0C:
                $depth = $parser->expectInt(true);
                $instruction = new Code\Control\BranchUncond($depth);
                break;
            case 0x0D:
                $depth = $parser->expectInt(true);
                $instruction = new Code\Control\BranchCond($depth);
                break;
            case 0x0E:
                $options = $parser->expectVector(function (BinaryParser $parser) {
                    return $parser->expectInt(true);
                });
                $default = $parser->expectInt(true);
                $instruction = new Code\Control\BranchIndirect($options, $default);
                break;
            case 0x0F:
                $instruction = new Code\Control\BranchUncond();
                break;
            case 0x10:
                $funcIdx = $parser->expectInt(true);
                $instruction = new Code\Control\Call($funcIdx);
                break;
            case 0x11:
                $typeIdx = $parser->expectInt(true);
                $tableIdx = $parser->expectInt(true);
                $instruction = new Code\Control\CallIndirect($tableIdx, $typeIdx);
                break;
            case 0x1A:
                $instruction = new Code\Parametric\Drop();
                break;
            case 0x1B:
                $instruction = new Code\Parametric\Select();
                break;
            case 0x1C:
                $type = $parser->expectVector(function (BinaryParser $parser) {
                    return TypesBuilder::valuetype($parser);
                });
                $instruction = new Code\Parametric\Select($type);
                break;
        }

        return $instruction;
    }

    private static function varInstr(int $opcode, BinaryParser $parser): ?Code\Instruction
    {
        $instruction = null;
        switch ($opcode) {
            case 0x20:
                $local = $parser->expectInt(true);
                $instruction = new Code\Variable\LocalGet($local);
                break;
            case 0x21:
                $local = $parser->expectInt(true);
                $instruction = new Code\Variable\LocalSet($local);
                break;
            case 0x22:
                $local = $parser->expectInt(true);
                $instruction = new Code\Variable\LocalSet($local, true);
                break;
            case 0x23:
                $global = $parser->expectInt(true);
                $instruction = new Code\Variable\GlobalGet($global);
                break;
            case 0x24:
                $global = $parser->expectInt(true);
                $instruction = new Code\Variable\GlobalSet($global);
                break;
            case 0x25:
                $table = $parser->expectInt(true);
                $instruction = new Code\Table\Get($table);
                break;
            case 0x26:
                $table = $parser->expectInt(true);
                $instruction = new Code\Table\Set($table);
                break;
        }

        return $instruction;
    }

    private static function memoryInstr(int $opcode, BinaryParser $parser): ?Code\Instruction
    {
        $i32 = new ValueType(ExpressionCompiler::I32);
        $i64 = new ValueType(ExpressionCompiler::I64);
        $f32 = new ValueType(ExpressionCompiler::F32);
        $f64 = new ValueType(ExpressionCompiler::F64);

        $instruction = null;
        switch ($opcode) {
            case 0x28:
                /* $align = */ $parser->expectInt(true);
                $offset = $parser->expectInt(true);
                $instruction = new Code\Memory\Load($i32, $offset);
                break;
            case 0x29:
                /* $align = */ $parser->expectInt(true);
                $offset = $parser->expectInt(true);
                $instruction = new Code\Memory\Load($i64, $offset);
                break;
            case 0x2A:
                /* $align = */ $parser->expectInt(true);
                $offset = $parser->expectInt(true);
                $instruction = new Code\Memory\Load($f32, $offset);
                break;
            case 0x2B:
                /* $align = */ $parser->expectInt(true);
                $offset = $parser->expectInt(true);
                $instruction = new Code\Memory\Load($f64, $offset);
                break;
            case 0x2C:
                /* $align = */ $parser->expectInt(true);
                $offset = $parser->expectInt(true);
                $instruction = new Code\Memory\Load($i32, $offset, 8, true);
                break;
            case 0x2D:
                /* $align = */ $parser->expectInt(true);
                $offset = $parser->expectInt(true);
                $instruction = new Code\Memory\Load($i32, $offset, 8, false);
                break;
            case 0x2E:
                /* $align = */ $parser->expectInt(true);
                $offset = $parser->expectInt(true);
                $instruction = new Code\Memory\Load($i32, $offset, 16, true);
                break;
            case 0x2F:
                /* $align = */ $parser->expectInt(true);
                $offset = $parser->expectInt(true);
                $instruction = new Code\Memory\Load($i32, $offset, 16, false);
                break;
            case 0x30:
                /* $align = */ $parser->expectInt(true);
                $offset = $parser->expectInt(true);
                $instruction = new Code\Memory\Load($i64, $offset, 8, true);
                break;
            case 0x31:
                /* $align = */ $parser->expectInt(true);
                $offset = $parser->expectInt(true);
                $instruction = new Code\Memory\Load($i64, $offset, 8, false);
                break;
            case 0x32:
                /* $align = */ $parser->expectInt(true);
                $offset = $parser->expectInt(true);
                $instruction = new Code\Memory\Load($i64, $offset, 16, true);
                break;
            case 0x33:
                /* $align = */ $parser->expectInt(true);
                $offset = $parser->expectInt(true);
                $instruction = new Code\Memory\Load($i64, $offset, 16, false);
                break;
            case 0x34:
                /* $align = */ $parser->expectInt(true);
                $offset = $parser->expectInt(true);
                $instruction = new Code\Memory\Load($i64, $offset, 32, true);
                break;
            case 0x35:
                /* $align = */ $parser->expectInt(true);
                $offset = $parser->expectInt(true);
                $instruction = new Code\Memory\Load($i64, $offset, 32, false);
                break;
            case 0x36:
                /* $align = */ $parser->expectInt(true);
                $offset = $parser->expectInt(true);
                $instruction = new Code\Memory\Store($i32, $offset);
                break;
            case 0x37:
                /* $align = */ $parser->expectInt(true);
                $offset = $parser->expectInt(true);
                $instruction = new Code\Memory\Store($i64, $offset);
                break;
            case 0x38:
                /* $align = */ $parser->expectInt(true);
                $offset = $parser->expectInt(true);
                $instruction = new Code\Memory\Store($f32, $offset);
                break;
            case 0x39:
                /* $align = */ $parser->expectInt(true);
                $offset = $parser->expectInt(true);
                $instruction = new Code\Memory\Store($f64, $offset);
                break;
            case 0x3A:
                /* $align = */ $parser->expectInt(true);
                $offset = $parser->expectInt(true);
                $instruction = new Code\Memory\Store($i32, $offset, 8);
                break;
            case 0x3B:
                /* $align = */ $parser->expectInt(true);
                $offset = $parser->expectInt(true);
                $instruction = new Code\Memory\Store($i32, $offset, 16);
                break;
            case 0x3C:
                /* $align = */ $parser->expectInt(true);
                $offset = $parser->expectInt(true);
                $instruction = new Code\Memory\Store($i64, $offset, 8);
                break;
            case 0x3D:
                /* $align = */ $parser->expectInt(true);
                $offset = $parser->expectInt(true);
                $instruction = new Code\Memory\Store($i64, $offset, 16);
                break;
            case 0x3E:
                /* $align = */ $parser->expectInt(true);
                $offset = $parser->expectInt(true);
                $instruction = new Code\Memory\Store($i64, $offset, 32);
                break;
            case 0x3F:
                $parser->expectByte(0x00);
                $instruction = new Code\Memory\Size();
                break;
            case 0x40:
                $parser->expectByte(0x00);
                $instruction = new Code\Memory\Grow();
                break;
        }

        return $instruction;
    }

    private static function constInstr(int $opcode, BinaryParser $parser): ?Code\Instruction
    {
        $i32 = new ValueType(ExpressionCompiler::I32);
        $i64 = new ValueType(ExpressionCompiler::I64);
        $f32 = new ValueType(ExpressionCompiler::F32);
        $f64 = new ValueType(ExpressionCompiler::F64);

        $instruction = null;
        switch ($opcode) {
            case 0x41:
                $value = $parser->expectInt();
                $instruction = new Code\Numeric\ConstStmt($i32, $value);
                break;
            case 0x42:
                $value = $parser->expectInt(false, 64);
                $instruction = new Code\Numeric\ConstStmt($i64, $value);
                break;
            case 0x43:
                $value = $parser->expectFloat();
                $instruction = new Code\Numeric\ConstStmt($f32, $value);
                break;
            case 0x44:
                $value = $parser->expectFloat(64);
                $instruction = new Code\Numeric\ConstStmt($f64, $value);
                break;
        }

        return $instruction;
    }

    private static function compInstr(int $opcode): ?Code\Instruction
    {
        $i32 = new ValueType(ExpressionCompiler::I32);
        $i64 = new ValueType(ExpressionCompiler::I64);
        $f32 = new ValueType(ExpressionCompiler::F32);
        $f64 = new ValueType(ExpressionCompiler::F64);

        $instruction = null;
        switch ($opcode) {
            case 0x45:
                $instruction = new Code\Numeric\Eqz($i32);
                break;
            case 0x46:
                $instruction = new Code\Numeric\Eq($i32);
                break;
            case 0x47:
                $instruction = new Code\Numeric\Neq($i32);
                break;
            case 0x48:
            case 0x49: // todo: differ signed/unsigned
                $instruction = new Code\Numeric\Lt($i32);
                break;
            case 0x4A:
            case 0x4B: // todo: differ signed/unsigned
                $instruction = new Code\Numeric\Gt($i32);
                break;
            case 0x4C:
            case 0x4D: // todo: differ signed/unsigned
                $instruction = new Code\Numeric\Le($i32);
                break;
            case 0x4E:
            case 0x4F: // todo: differ signed/unsigned
                $instruction = new Code\Numeric\Ge($i32);
                break;
            case 0x50:
                $instruction = new Code\Numeric\Eqz($i64);
                break;
            case 0x51:
                $instruction = new Code\Numeric\Eq($i64);
                break;
            case 0x52:
                $instruction = new Code\Numeric\Neq($i64);
                break;
            case 0x53:
            case 0x54: // todo: differ signed/unsigned
                $instruction = new Code\Numeric\Lt($i64);
                break;
            case 0x55:
            case 0x56: // todo: differ signed/unsigned
                $instruction = new Code\Numeric\Gt($i64);
                break;
            case 0x57:
            case 0x58: // todo: differ signed/unsigned
                $instruction = new Code\Numeric\Le($i64);
                break;
            case 0x59:
            case 0x5A: // todo: differ signed/unsigned
                $instruction = new Code\Numeric\Ge($i64);
                break;
            case 0x5B:
                $instruction = new Code\Numeric\Eq($f32);
                break;
            case 0x5C:
                $instruction = new Code\Numeric\Neq($f32);
                break;
            case 0x5D:
                $instruction = new Code\Numeric\Lt($f32);
                break;
            case 0x5E:
                $instruction = new Code\Numeric\Gt($f32);
                break;
            case 0x5F:
                $instruction = new Code\Numeric\Le($f32);
                break;
            case 0x60:
                $instruction = new Code\Numeric\Ge($f32);
                break;
            case 0x61:
                $instruction = new Code\Numeric\Eq($f64);
                break;
            case 0x62:
                $instruction = new Code\Numeric\Neq($f64);
                break;
            case 0x63:
                $instruction = new Code\Numeric\Lt($f64);
                break;
            case 0x64:
                $instruction = new Code\Numeric\Gt($f64);
                break;
            case 0x65:
                $instruction = new Code\Numeric\Le($f64);
                break;
            case 0x66:
                $instruction = new Code\Numeric\Ge($f64);
                break;
        }

        return $instruction;
    }

    private static function computeInstr(int $opcode): ?Code\Instruction
    {
        $i32 = new ValueType(ExpressionCompiler::I32);
        $i64 = new ValueType(ExpressionCompiler::I64);
        $f32 = new ValueType(ExpressionCompiler::F32);
        $f64 = new ValueType(ExpressionCompiler::F64);

        $instruction = null;
        switch ($opcode) {
            case 0x67:
                $instruction = new Code\Numeric\Int\Clz($i32);
                break;
            case 0x68:
                $instruction = new Code\Numeric\Int\Ctz($i32);
                break;
            case 0x69:
                $instruction = new Code\Numeric\Int\Popcnt($i32);
                break;
            case 0x6A:
                $instruction = new Code\Numeric\Add($i32);
                break;
            case 0x6B:
                $instruction = new Code\Numeric\Sub($i32);
                break;
            case 0x6C:
                $instruction = new Code\Numeric\Mul($i32);
                break;
            case 0x6D:
            case 0x6E: // todo: differ signed/unsigned
                $instruction = new Code\Numeric\Div($i32);
                break;
            case 0x6F:
            case 0x70: // todo: differ signed/unsigned
                $instruction = new Code\Numeric\Int\Rem($i32);
                break;
            case 0x71:
                $instruction = new Code\Numeric\Int\BitAnd($i32);
                break;
            case 0x72:
                $instruction = new Code\Numeric\Int\BitOr($i32);
                break;
            case 0x73:
                $instruction = new Code\Numeric\Int\BitXor($i32);
                break;
            case 0x74:
                $instruction = new Code\Numeric\Int\BitShl($i32);
                break;
            case 0x75:
            case 0x76: // todo: differ signed/unsigned
                $instruction = new Code\Numeric\Int\BitShr($i32);
                break;
            case 0x77:
                $instruction = new Code\Numeric\Int\Rotl($i32);
                break;
            case 0x78:
                $instruction = new Code\Numeric\Int\Rotr($i32);
                break;
            case 0x79:
                $instruction = new Code\Numeric\Int\Clz($i64);
                break;
            case 0x7A:
                $instruction = new Code\Numeric\Int\Ctz($i64);
                break;
            case 0x7B:
                $instruction = new Code\Numeric\Int\Popcnt($i64);
                break;
            case 0x7C:
                $instruction = new Code\Numeric\Add($i64);
                break;
            case 0x7D:
                $instruction = new Code\Numeric\Sub($i64);
                break;
            case 0x7E:
                $instruction = new Code\Numeric\Mul($i64);
                break;
            case 0x7F:
            case 0x80: // todo: differ signed/unsigned
                $instruction = new Code\Numeric\Div($i64);
                break;
            case 0x81:
            case 0x82: // todo: differ signed/unsigned
                $instruction = new Code\Numeric\Int\Rem($i64);
                break;
            case 0x83:
                $instruction = new Code\Numeric\Int\BitAnd($i64);
                break;
            case 0x84:
                $instruction = new Code\Numeric\Int\BitOr($i64);
                break;
            case 0x85:
                $instruction = new Code\Numeric\Int\BitXor($i64);
                break;
            case 0x86:
                $instruction = new Code\Numeric\Int\BitShl($i64);
                break;
            case 0x87:
            case 0x88: // todo: differ signed/unsigned
                $instruction = new Code\Numeric\Int\BitShr($i64);
                break;
            case 0x89:
                $instruction = new Code\Numeric\Int\Rotl($i64);
                break;
            case 0x8A:
                $instruction = new Code\Numeric\Int\Rotr($i64);
                break;
            case 0x8B:
                $instruction = new Code\Numeric\Float\Abs($f32);
                break;
            case 0x8C:
                $instruction = new Code\Numeric\Float\Neg($f32);
                break;
            case 0x8D:
                $instruction = new Code\Numeric\Float\Ceil($f32);
                break;
            case 0x8E:
                $instruction = new Code\Numeric\Float\Floor($f32);
                break;
            case 0x8F:
                $instruction = new Code\Numeric\Float\Trunc($f32);
                break;
            case 0x90:
                $instruction = new Code\Numeric\Float\Nearest($f32);
                break;
            case 0x91:
                $instruction = new Code\Numeric\Float\Sqrt($f32);
                break;
            case 0x92:
                $instruction = new Code\Numeric\Add($f32);
                break;
            case 0x93:
                $instruction = new Code\Numeric\Sub($f32);
                break;
            case 0x94:
                $instruction = new Code\Numeric\Mul($f32);
                break;
            case 0x95:
                $instruction = new Code\Numeric\Div($f32);
                break;
            case 0x96:
                $instruction = new Code\Numeric\Float\Min($f32);
                break;
            case 0x97:
                $instruction = new Code\Numeric\Float\Max($f32);
                break;
            case 0x98:
                $instruction = new Code\Numeric\Float\Copysign($f32);
                break;
            case 0x99:
                $instruction = new Code\Numeric\Float\Abs($f64);
                break;
            case 0x9A:
                $instruction = new Code\Numeric\Float\Neg($f64);
                break;
            case 0x9B:
                $instruction = new Code\Numeric\Float\Ceil($f64);
                break;
            case 0x9C:
                $instruction = new Code\Numeric\Float\Floor($f64);
                break;
            case 0x9D:
                $instruction = new Code\Numeric\Float\Trunc($f64);
                break;
            case 0x9E:
                $instruction = new Code\Numeric\Float\Nearest($f64);
                break;
            case 0x9F:
                $instruction = new Code\Numeric\Float\Sqrt($f64);
                break;
            case 0xA0:
                $instruction = new Code\Numeric\Add($f64);
                break;
            case 0xA1:
                $instruction = new Code\Numeric\Sub($f64);
                break;
            case 0xA2:
                $instruction = new Code\Numeric\Mul($f64);
                break;
            case 0xA3:
                $instruction = new Code\Numeric\Div($f64);
                break;
            case 0xA4:
                $instruction = new Code\Numeric\Float\Min($f64);
                break;
            case 0xA5:
                $instruction = new Code\Numeric\Float\Max($f64);
                break;
            case 0xA6:
                $instruction = new Code\Numeric\Float\Copysign($f64);
                break;
        }

        return $instruction;
    }

    private static function convertInstr(int $opcode): ?Code\Instruction
    {
        $i32 = new ValueType(ExpressionCompiler::I32);
        $i64 = new ValueType(ExpressionCompiler::I64);
        $f32 = new ValueType(ExpressionCompiler::F32);
        $f64 = new ValueType(ExpressionCompiler::F64);

        $instruction = null;
        switch ($opcode) {
            case 0xA7:
                $instruction = new Code\Numeric\Conversion\Wrap();
                break;
            case 0xA8:
                $instruction = new Code\Numeric\Conversion\Cast($i32, $f32);
                break;
            case 0xA9:
                $instruction = new Code\Numeric\Conversion\Cast($i32, $f32, true);
                break;
            case 0xAA:
                $instruction = new Code\Numeric\Conversion\Cast($i32, $f64);
                break;
            case 0xAB:
                $instruction = new Code\Numeric\Conversion\Cast($i32, $f64, true);
                break;
            case 0xAC:
                $instruction = new Code\Numeric\Conversion\Extend();
                break;
            case 0xAD:
                $instruction = new Code\Numeric\Conversion\Extend(true);
                break;
            case 0xAE:
                $instruction = new Code\Numeric\Conversion\Cast($i64, $f32);
                break;
            case 0xAF:
                $instruction = new Code\Numeric\Conversion\Cast($i64, $f32, true);
                break;
            case 0xB0:
                $instruction = new Code\Numeric\Conversion\Cast($i64, $f64);
                break;
            case 0xB1:
                $instruction = new Code\Numeric\Conversion\Cast($i64, $f64, true);
                break;
            case 0xB2:
                $instruction = new Code\Numeric\Conversion\Cast($f32, $i32);
                break;
            case 0xB3:
                $instruction = new Code\Numeric\Conversion\Cast($f32, $i32, true);
                break;
            case 0xB4:
                $instruction = new Code\Numeric\Conversion\Cast($f32, $i64);
                break;
            case 0xB5:
                $instruction = new Code\Numeric\Conversion\Cast($f32, $i64, true);
                break;
            case 0xB6:
                $instruction = new Code\Numeric\Conversion\Promote($f32);
                break;
            case 0xB7:
                $instruction = new Code\Numeric\Conversion\Cast($f64, $i32);
                break;
            case 0xB8:
                $instruction = new Code\Numeric\Conversion\Cast($f64, $i32, true);
                break;
            case 0xB9:
                $instruction = new Code\Numeric\Conversion\Cast($f64, $i64);
                break;
            case 0xBA:
                $instruction = new Code\Numeric\Conversion\Cast($f64, $i64, true);
                break;
            case 0xBB:
                $instruction = new Code\Numeric\Conversion\Promote($f64);
                break;
            case 0xBC:
                $instruction = new Code\Numeric\Conversion\Reinterpret($i32);
                break;
            case 0xBD:
                $instruction = new Code\Numeric\Conversion\Reinterpret($i64);
                break;
            case 0xBE:
                $instruction = new Code\Numeric\Conversion\Reinterpret($f32);
                break;
            case 0xBF:
                $instruction = new Code\Numeric\Conversion\Reinterpret($f64);
                break;
            case 0xC0:
                $instruction = new Code\Numeric\Conversion\SignExtend($i32, 8);
                break;
            case 0xC1:
                $instruction = new Code\Numeric\Conversion\SignExtend($i32, 16);
                break;
            case 0xC2:
                $instruction = new Code\Numeric\Conversion\SignExtend($i64, 8);
                break;
            case 0xC3:
                $instruction = new Code\Numeric\Conversion\SignExtend($i64, 16);
                break;
            case 0xC4:
                $instruction = new Code\Numeric\Conversion\SignExtend($i64, 32);
                break;
        }

        return $instruction;
    }

    private static function miscInstr(int $opcode, BinaryParser $parser): Code\Instruction
    {
        switch ($opcode) {
            case 0xD0:
                $type = TypesBuilder::reftype($parser);
                $instruction = new Code\Reference\NullRef($type);
                break;
            case 0xD1:
                $instruction = new Code\Reference\IsNull();
                break;
            case 0xD2:
                $value = $parser->expectInt(true);
                $instruction = new Code\Reference\Func($value);
                break;
            case 0xFC: // subswitch
                $secondary = $parser->expectInt(true);
                switch ($secondary) {
                    case 8:
                        $dataIdx = $parser->expectInt(true);
                        $parser->expectByte(0x00);
                        $instruction = new Code\Memory\Init($dataIdx);
                        break;
                    case 9:
                        $dataIdx = $parser->expectInt(true);
                        $instruction = new Code\Memory\Drop($dataIdx);
                        break;
                    case 10:
                        $parser->expectByte(0x00);
                        $parser->expectByte(0x00);
                        $instruction = new Code\Memory\Copy();
                        break;
                    case 11:
                        $parser->expectByte(0x00);
                        $instruction = new Code\Memory\Fill();
                        break;
                    case 12:
                        $elemIdx = $parser->expectInt(true);
                        $tableIdx = $parser->expectInt(true);
                        $instruction = new Code\Table\Init($tableIdx, $elemIdx);
                        break;
                    case 13:
                        $dataIdx = $parser->expectInt(true);
                        $instruction = new Code\Table\Drop($dataIdx);
                        break;
                    case 14:
                        $x = $parser->expectInt(true);
                        $y = $parser->expectInt(true);
                        $instruction = new Code\Table\Copy($x, $y);
                        break;
                    case 15:
                        $tableIdx = $parser->expectInt(true);
                        $instruction = new Code\Table\Grow($tableIdx);
                        break;
                    case 16:
                        $tableIdx = $parser->expectInt(true);
                        $instruction = new Code\Table\Size($tableIdx);
                        break;
                    case 17:
                        $tableIdx = $parser->expectInt(true);
                        $instruction = new Code\Table\Fill($tableIdx);
                        break;
                    default:
                        throw new ParsingException('Unknown secondary opcode ' . strval($secondary));
                }
                break;
            default:
                $pos = str_pad(dechex($parser->position() - 1), 8, '0', STR_PAD_LEFT);
                throw new ParsingException('Unknown opcode 0x' . str_pad(dechex($opcode), 2, '0', STR_PAD_LEFT) . '@0x' . $pos);
        }
        return $instruction;
    }
}
