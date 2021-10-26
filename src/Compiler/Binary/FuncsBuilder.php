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
use UnWasm\Compiler\ExpressionCompiler;
use UnWasm\Compiler\ModuleCompiler;
use UnWasm\Compiler\Node\Code\Control\Block;
use UnWasm\Compiler\Node\Code\Control\BranchCond;
use UnWasm\Compiler\Node\Code\Control\BranchUncond;
use UnWasm\Compiler\Node\Code\Control\Call;
use UnWasm\Compiler\Node\Code\Control\CallIndirect;
use UnWasm\Compiler\Node\Code\Control\ElseStmt;
use UnWasm\Compiler\Node\Code\Control\IfElse;
use UnWasm\Compiler\Node\Code\Control\Loop;
use UnWasm\Compiler\Node\Code\Control\Nop;
use UnWasm\Compiler\Node\Code\Control\Unreachable;
use UnWasm\Compiler\Node\Code\Func;
use UnWasm\Compiler\Node\Code\Instruction;
use UnWasm\Compiler\Node\Code\Memory\Copy;
use UnWasm\Compiler\Node\Code\Memory\Drop as DataDrop;
use UnWasm\Compiler\Node\Code\Memory\Fill;
use UnWasm\Compiler\Node\Code\Memory\Grow;
use UnWasm\Compiler\Node\Code\Memory\Init;
use UnWasm\Compiler\Node\Code\Memory\Load;
use UnWasm\Compiler\Node\Code\Memory\Size;
use UnWasm\Compiler\Node\Code\Memory\Store;
use UnWasm\Compiler\Node\Code\Numeric\Add;
use UnWasm\Compiler\Node\Code\Numeric\BitAnd;
use UnWasm\Compiler\Node\Code\Numeric\BitOr;
use UnWasm\Compiler\Node\Code\Numeric\BitShl;
use UnWasm\Compiler\Node\Code\Numeric\BitShr;
use UnWasm\Compiler\Node\Code\Numeric\BitXor;
use UnWasm\Compiler\Node\Code\Numeric\ConstStmt;
use UnWasm\Compiler\Node\Code\Numeric\Div;
use UnWasm\Compiler\Node\Code\Numeric\Eq;
use UnWasm\Compiler\Node\Code\Numeric\Eqz;
use UnWasm\Compiler\Node\Code\Numeric\Ge;
use UnWasm\Compiler\Node\Code\Numeric\Gt;
use UnWasm\Compiler\Node\Code\Numeric\Le;
use UnWasm\Compiler\Node\Code\Numeric\Lt;
use UnWasm\Compiler\Node\Code\Numeric\Mul;
use UnWasm\Compiler\Node\Code\Numeric\Neq;
use UnWasm\Compiler\Node\Code\Numeric\Sub;
use UnWasm\Compiler\Node\Code\Parametric\Drop;
use UnWasm\Compiler\Node\Code\Parametric\Select;
use UnWasm\Compiler\Node\Code\Reference\Func as RefFunc;
use UnWasm\Compiler\Node\Code\Reference\IsNull;
use UnWasm\Compiler\Node\Code\Reference\NullRef;
use UnWasm\Compiler\Node\Code\Table\Copy as TableCopy;
use UnWasm\Compiler\Node\Code\Table\Drop as ElemDrop;
use UnWasm\Compiler\Node\Code\Table\Fill as TableFill;
use UnWasm\Compiler\Node\Code\Table\Get as TableGet;
use UnWasm\Compiler\Node\Code\Table\Grow as TableGrow;
use UnWasm\Compiler\Node\Code\Table\Init as TableInit;
use UnWasm\Compiler\Node\Code\Table\Set as TableSet;
use UnWasm\Compiler\Node\Code\Table\Size as TableSize;
use UnWasm\Compiler\Node\Code\Variable\GlobalGet;
use UnWasm\Compiler\Node\Code\Variable\GlobalSet;
use UnWasm\Compiler\Node\Code\Variable\LocalGet;
use UnWasm\Compiler\Node\Code\Variable\LocalSet;
use UnWasm\Compiler\Node\Type\FuncType;
use UnWasm\Compiler\Node\Type\ValueType;

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
                return $parser->assertSize(function (BinaryParser $parser) use ($index) {
                    // create array of arrays of local variables
                    $locals = $parser->expectVector(function (BinaryParser $parser) {
                        $n = $parser->expectInt(true);
                        $type = TypesBuilder::valuetype($parser);
                        return array_fill(0, $n, $type);
                    });

                    $expr = self::expression($parser);

                    return new Func($this->funcTypes[$index], array_merge([], ...$locals), $expr);
                });
            });
            echo 'Scanned '.count($compiler->funcs)." funcs\n";
        }
    }

    public static function expression(BinaryParser $parser, bool $constExpr = false, $termOpcode = 0x0B): array
    {
        $instructions = [];
        echo "Expression started\n";
        while (!$parser->eof()) {
            $opcode = $parser->expectByte();
            if ($opcode === $termOpcode) {
                echo "Expression terminated\n";
                return $instructions;
            }

            $instructions[] =
                self::controlInstr($opcode, $parser, $constExpr, $termOpcode) ??
                self::varInstr($opcode, $parser) ??
                self::memoryInstr($opcode, $parser) ??
                self::constInstr($opcode, $parser) ??
                self::compInstr($opcode) ??
                self::computeInstr($opcode) ??
                self::miscInstr($opcode, $parser)
            ;
        }
    }

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
    }

    private static function controlInstr(int $opcode, BinaryParser $parser, bool $constExpr, int $termOpcode): ?Instruction
    {
        $instruction = null;
        switch ($opcode) {
            case 0x00:
                $instruction = new Unreachable();
                break;
            case 0x01:
                $instruction = new Nop();
                break;
            case 0x02:
                list($functype, $typeIdx) = self::parseBlocktype($parser);
                $inner = self::expression($parser, $constExpr, $termOpcode);
                $instruction = new Block($inner, $functype, $typeIdx);
                break;
            case 0x03:
                list($functype, $typeIdx) = self::parseBlocktype($parser);
                $inner = self::expression($parser, $constExpr, $termOpcode);
                $instruction = new Loop($inner, $functype, $typeIdx);
                break;
            case 0x04:
                list($functype, $typeIdx) = self::parseBlocktype($parser);
                $inner = self::expression($parser, $constExpr, $termOpcode);
                $instruction = new IfElse($inner, $functype, $typeIdx);
                break;
            case 0x05:
                $instruction = new ElseStmt();
                break;
            case 0x0C:
                $depth = $parser->expectInt(true);
                $instruction = new BranchUncond($depth);
                break;
            case 0x0D:
                $depth = $parser->expectInt(true);
                $instruction = new BranchCond($depth);
                break;
            case 0x0F:
                $instruction = new BranchUncond();
                break;
            case 0x10:
                $funcIdx = $parser->expectInt(true);
                $instruction = new Call($funcIdx);
                break;
            case 0x11:
                $typeIdx = $parser->expectInt(true);
                $tableIdx = $parser->expectInt(true);
                $instruction = new CallIndirect($tableIdx, $typeIdx);
                break;
            case 0x1A:
                $instruction = new Drop();
                break;
            case 0x1B:
                $instruction = new Select();
                break;
            case 0x1C:
                $type = $parser->expectVector(function (BinaryParser $parser) {
                    return TypesBuilder::valuetype($parser);
                });
                $instruction = new Select($type);
                break;
        }

        return $instruction;
    }

    private static function varInstr(int $opcode, BinaryParser $parser): ?Instruction
    {
        $instruction = null;
        switch ($opcode) {
            case 0x20:
                $local = $parser->expectInt(true);
                $instruction = new LocalGet($local);
                break;
            case 0x21:
                $local = $parser->expectInt(true);
                $instruction = new LocalSet($local);
                break;
            case 0x22:
                $local = $parser->expectInt(true);
                $instruction = new LocalSet($local, true);
                break;
            case 0x23:
                $global = $parser->expectInt(true);
                $instruction = new GlobalGet($global);
                break;
            case 0x24:
                $global = $parser->expectInt(true);
                $instruction = new GlobalSet($global);
                break;
            case 0x25:
                $table = $parser->expectInt(true);
                $instruction = new TableGet($table);
                break;
            case 0x26:
                $table = $parser->expectInt(true);
                $instruction = new TableSet($table);
                break;
        }

        return $instruction;
    }

    private static function memoryInstr(int $opcode, BinaryParser $parser): ?Instruction
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
                $instruction = new Load($i32, $offset);
                break;
            case 0x29:
                /* $align = */ $parser->expectInt(true);
                $offset = $parser->expectInt(true);
                $instruction = new Load($i64, $offset);
                break;
            case 0x2A:
                /* $align = */ $parser->expectInt(true);
                $offset = $parser->expectInt(true);
                $instruction = new Load($f32, $offset);
                break;
            case 0x2B:
                /* $align = */ $parser->expectInt(true);
                $offset = $parser->expectInt(true);
                $instruction = new Load($f64, $offset);
                break;
            case 0x2C:
                /* $align = */ $parser->expectInt(true);
                $offset = $parser->expectInt(true);
                $instruction = new Load($i32, $offset, 8, true);
                break;
            case 0x2D:
                /* $align = */ $parser->expectInt(true);
                $offset = $parser->expectInt(true);
                $instruction = new Load($i32, $offset, 8, false);
                break;
            case 0x2E:
                /* $align = */ $parser->expectInt(true);
                $offset = $parser->expectInt(true);
                $instruction = new Load($i32, $offset, 16, true);
                break;
            case 0x2F:
                /* $align = */ $parser->expectInt(true);
                $offset = $parser->expectInt(true);
                $instruction = new Load($i32, $offset, 16, false);
                break;
            case 0x30:
                /* $align = */ $parser->expectInt(true);
                $offset = $parser->expectInt(true);
                $instruction = new Load($i64, $offset, 8, true);
                break;
            case 0x31:
                /* $align = */ $parser->expectInt(true);
                $offset = $parser->expectInt(true);
                $instruction = new Load($i64, $offset, 8, false);
                break;
            case 0x32:
                /* $align = */ $parser->expectInt(true);
                $offset = $parser->expectInt(true);
                $instruction = new Load($i64, $offset, 16, true);
                break;
            case 0x33:
                /* $align = */ $parser->expectInt(true);
                $offset = $parser->expectInt(true);
                $instruction = new Load($i64, $offset, 16, false);
                break;
            case 0x34:
                /* $align = */ $parser->expectInt(true);
                $offset = $parser->expectInt(true);
                $instruction = new Load($i64, $offset, 32, true);
                break;
            case 0x35:
                /* $align = */ $parser->expectInt(true);
                $offset = $parser->expectInt(true);
                $instruction = new Load($i64, $offset, 32, false);
                break;
            case 0x36:
                /* $align = */ $parser->expectInt(true);
                $offset = $parser->expectInt(true);
                $instruction = new Store($i32, $offset);
                break;
            case 0x37:
                /* $align = */ $parser->expectInt(true);
                $offset = $parser->expectInt(true);
                $instruction = new Store($i64, $offset);
                break;
            case 0x38:
                /* $align = */ $parser->expectInt(true);
                $offset = $parser->expectInt(true);
                $instruction = new Store($f32, $offset);
                break;
            case 0x39:
                /* $align = */ $parser->expectInt(true);
                $offset = $parser->expectInt(true);
                $instruction = new Store($f64, $offset);
                break;
            case 0x3A:
                /* $align = */ $parser->expectInt(true);
                $offset = $parser->expectInt(true);
                $instruction = new Store($i32, $offset, 8);
                break;
            case 0x3B:
                /* $align = */ $parser->expectInt(true);
                $offset = $parser->expectInt(true);
                $instruction = new Store($i32, $offset, 16);
                break;
            case 0x3C:
                /* $align = */ $parser->expectInt(true);
                $offset = $parser->expectInt(true);
                $instruction = new Store($i64, $offset, 8);
                break;
            case 0x3D:
                /* $align = */ $parser->expectInt(true);
                $offset = $parser->expectInt(true);
                $instruction = new Store($i64, $offset, 16);
                break;
            case 0x3E:
                /* $align = */ $parser->expectInt(true);
                $offset = $parser->expectInt(true);
                $instruction = new Store($i64, $offset, 32);
                break;
            case 0x3F:
                $parser->expectByte(0x00);
                $instruction = new Size();
                break;
            case 0x40:
                $parser->expectByte(0x00);
                $instruction = new Grow();
                break;
        }

        return $instruction;
    }

    private static function constInstr(int $opcode, BinaryParser $parser): ?Instruction
    {
        $i32 = new ValueType(ExpressionCompiler::I32);
        $i64 = new ValueType(ExpressionCompiler::I64);
        $f32 = new ValueType(ExpressionCompiler::F32);
        $f64 = new ValueType(ExpressionCompiler::F64);

        $instruction = null;
        switch ($opcode) {
            case 0x41:
                $value = $parser->expectInt();
                $instruction = new ConstStmt($i32, $value);
                break;
            case 0x42:
                $value = $parser->expectInt(false, 64);
                $instruction = new ConstStmt($i64, $value);
                break;
            case 0x43:
                $value = $parser->expectFloat();
                $instruction = new ConstStmt($f32, $value);
                break;
            case 0x44:
                $value = $parser->expectFloat(64);
                $instruction = new ConstStmt($f64, $value);
                break;
        }

        return $instruction;
    }

    private static function compInstr(int $opcode): ?Instruction
    {
        $i32 = new ValueType(ExpressionCompiler::I32);
        $i64 = new ValueType(ExpressionCompiler::I64);
        $f32 = new ValueType(ExpressionCompiler::F32);
        $f64 = new ValueType(ExpressionCompiler::F64);

        $instruction = null;
        switch ($opcode) {
            case 0x45:
                $instruction = new Eqz($i32);
                break;
            case 0x46:
                $instruction = new Eq($i32);
                break;
            case 0x47:
                $instruction = new Neq($i32);
                break;
            case 0x48:
            case 0x49: // todo: differ signed/unsigned
                $instruction = new Lt($i32);
                break;
            case 0x4A:
            case 0x4B: // todo: differ signed/unsigned
                $instruction = new Gt($i32);
                break;
            case 0x4C:
            case 0x4D: // todo: differ signed/unsigned
                $instruction = new Le($i32);
                break;
            case 0x4E:
            case 0x4F: // todo: differ signed/unsigned
                $instruction = new Ge($i32);
                break;
            case 0x50:
                $instruction = new Eqz($i64);
                break;
            case 0x51:
                $instruction = new Eq($i64);
                break;
            case 0x52:
                $instruction = new Neq($i64);
                break;
            case 0x53:
            case 0x54: // todo: differ signed/unsigned
                $instruction = new Lt($i64);
                break;
            case 0x55:
            case 0x56: // todo: differ signed/unsigned
                $instruction = new Gt($i64);
                break;
            case 0x57:
            case 0x58: // todo: differ signed/unsigned
                $instruction = new Le($i64);
                break;
            case 0x59:
            case 0x5A: // todo: differ signed/unsigned
                $instruction = new Ge($i64);
                break;
            case 0x5B:
                $instruction = new Eq($f32);
                break;
            case 0x5C:
                $instruction = new Neq($f32);
                break;
            case 0x5D:
                $instruction = new Lt($f32);
                break;
            case 0x5E:
                $instruction = new Gt($f32);
                break;
            case 0x5F:
                $instruction = new Le($f32);
                break;
            case 0x60:
                $instruction = new Ge($f32);
                break;
            case 0x61:
                $instruction = new Eq($f64);
                break;
            case 0x62:
                $instruction = new Neq($f64);
                break;
            case 0x63:
                $instruction = new Lt($f64);
                break;
            case 0x64:
                $instruction = new Gt($f64);
                break;
            case 0x65:
                $instruction = new Le($f64);
                break;
            case 0x66:
                $instruction = new Ge($f64);
                break;
        }

        return $instruction;
    }

    private static function computeInstr(int $opcode): ?Instruction
    {
        $i32 = new ValueType(ExpressionCompiler::I32);
        $i64 = new ValueType(ExpressionCompiler::I64);
        $f32 = new ValueType(ExpressionCompiler::F32);
        $f64 = new ValueType(ExpressionCompiler::F64);

        $instruction = null;
        switch ($opcode) {
            case 0x6A:
                $instruction = new Add($i32);
                break;
            case 0x6B:
                $instruction = new Sub($i32);
                break;
            case 0x6C:
                $instruction = new Mul($i32);
                break;
            case 0x6D:
            case 0x6E: // todo: differ signed/unsigned
                $instruction = new Div($i32);
                break;
            case 0x71:
                $instruction = new BitAnd($i32);
                break;
            case 0x72:
                $instruction = new BitOr($i32);
                break;
            case 0x73:
                $instruction = new BitXor($i32);
                break;
            case 0x74:
                $instruction = new BitShl($i32);
                break;
            case 0x75:
            case 0x76: // todo: differ signed/unsigned
                $instruction = new BitShr($i32);
                break;
            case 0x7C:
                $instruction = new Add($i64);
                break;
            case 0x7D:
                $instruction = new Sub($i64);
                break;
            case 0x7E:
                $instruction = new Mul($i64);
                break;
            case 0x7F:
            case 0x80: // todo: differ signed/unsigned
                $instruction = new Div($i64);
                break;
            case 0x83:
                $instruction = new BitAnd($i64);
                break;
            case 0x84:
                $instruction = new BitOr($i64);
                break;
            case 0x85:
                $instruction = new BitXor($i64);
                break;
            case 0x86:
                $instruction = new BitShl($i64);
                break;
            case 0x87:
            case 0x88: // todo: differ signed/unsigned
                $instruction = new BitShr($i64);
                break;
            case 0x92:
                $instruction = new Add($f32);
                break;
            case 0x93:
                $instruction = new Sub($f32);
                break;
            case 0x94:
                $instruction = new Mul($f32);
                break;
            case 0x95:
                $instruction = new Div($f32);
                break;
            case 0xA0:
                $instruction = new Add($f64);
                break;
            case 0xA1:
                $instruction = new Sub($f64);
                break;
            case 0xA2:
                $instruction = new Mul($f64);
                break;
            case 0xA3:
                $instruction = new Div($f64);
                break;
        }

        return $instruction;
    }

    private static function miscInstr(int $opcode, BinaryParser $parser): Instruction
    {
        switch ($opcode) {
            case 0xD0:
                $type = TypesBuilder::reftype($parser);
                $instruction = new NullRef($type);
                break;
            case 0xD1:
                $instruction = new IsNull();
                break;
            case 0xD2:
                $value = $parser->expectInt(true);
                $instruction = new RefFunc($value);
                break;
            case 0xFC: // subswitch
                $secondary = $parser->expectInt(true);
                switch ($secondary) {
                    case 8:
                        $dataIdx = $parser->expectInt(true);
                        $parser->expectByte(0x00);
                        $instruction = new Init($dataIdx);
                        break;
                    case 9:
                        $dataIdx = $parser->expectInt(true);
                        $instruction = new DataDrop($dataIdx);
                        break;
                    case 10:
                        $parser->expectByte(0x00);
                        $parser->expectByte(0x00);
                        $instruction = new Copy();
                        break;
                    case 11:
                        $parser->expectByte(0x00);
                        $instruction = new Fill();
                        break;
                    case 12:
                        $elemIdx = $parser->expectInt(true);
                        $tableIdx = $parser->expectInt(true);
                        $instruction = new TableInit($tableIdx, $elemIdx);
                        break;
                    case 13:
                        $dataIdx = $parser->expectInt(true);
                        $instruction = new ElemDrop($dataIdx);
                        break;
                    case 14:
                        $x = $parser->expectInt(true);
                        $y = $parser->expectInt(true);
                        $instruction = new TableCopy($x, $y);
                        break;
                    case 15:
                        $tableIdx = $parser->expectInt(true);
                        $instruction = new TableGrow($tableIdx);
                        break;
                    case 16:
                        $tableIdx = $parser->expectInt(true);
                        $instruction = new TableSize($tableIdx);
                        break;
                    case 17:
                        $tableIdx = $parser->expectInt(true);
                        $instruction = new TableFill($tableIdx);
                        break;
                    default:
                        throw new \RuntimeException('Unknown secondary opcode ' . strval($secondary));
                }
                break;
            default:
                $pos = str_pad(dechex($parser->position() - 1), 8, '0', STR_PAD_LEFT);
                throw new \RuntimeException('Unknown opcode 0x' . str_pad(dechex($opcode), 2, '0', STR_PAD_LEFT) . '@0x' . $pos);
        }
        return $instruction;
    }
}
