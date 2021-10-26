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
use UnWasm\Compiler\Node\Code\Control\Block;
use UnWasm\Compiler\Node\Code\Control\BranchCond;
use UnWasm\Compiler\Node\Code\Control\BranchUncond;
use UnWasm\Compiler\Node\Code\Control\Call;
use UnWasm\Compiler\Node\Code\Control\ElseStmt;
use UnWasm\Compiler\Node\Code\Control\IfElse;
use UnWasm\Compiler\Node\Code\Control\Loop;
use UnWasm\Compiler\Node\Code\Control\Nop;
use UnWasm\Compiler\Node\Code\Control\Unreachable;
use UnWasm\Compiler\Node\Code\Func;
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
use UnWasm\Compiler\Node\Type\RefType;
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
                        $type = new ValueType($parser->expectByte());
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
        $i32 = new ValueType(Token::INT_TYPE);
        $i64 = new ValueType(Token::INT_64_TYPE);
        $f32 = new ValueType(Token::FLOAT_TYPE);
        $f64 = new ValueType(Token::FLOAT_64_TYPE);

        $instructions = [];
        echo "Expression started\n";
        while (!$parser->eof()) {
            $current = $parser->expectByte();
            switch ($current) {
                case 0x00:
                    $instructions[] = new Unreachable();
                    break;
                case 0x01:
                    $instructions[] = new Nop();
                    break;
                case 0x02:
                    $bt = $parser->expectInt();
                    $inner = self::expression($parser, $constExpr, $termOpcode);
                    $instructions[] = new Block($inner);
                    break;
                case 0x03:
                    $bt = $parser->expectInt();
                    $inner = self::expression($parser, $constExpr, $termOpcode);
                    $instructions[] = new Loop($inner);
                    break;
                case 0x04:
                    $bt = $parser->expectInt();
                    $inner = self::expression($parser, $constExpr, $termOpcode);
                    $instructions[] = new IfElse($inner);
                    break;
                case 0x05:
                    $instructions[] = new ElseStmt();
                    break;
                case 0x0C:
                    $depth = $parser->expectInt(true);
                    $instructions[] = new BranchUncond($depth);
                    break;
                case 0x0D:
                    $depth = $parser->expectInt(true);
                    $instructions[] = new BranchCond($depth);
                    break;
                case 0x10:
                    $funcidx = $parser->expectInt(true);
                    $instructions[] = new Call($funcidx);
                    break;
                case 0x1A:
                    $instructions[] = new Drop();
                    break;
                case 0x1B:
                    $instructions[] = new Select();
                    break;
                case 0x1C:
                    $type = $parser->expectVector(function (BinaryParser $parser) {
                        return new ValueType($parser->expectByte());
                    });
                    $instructions[] = new Select($type);
                    break;
                case 0x20:
                    $local = $parser->expectInt(true);
                    $instructions[] = new LocalGet($local);
                    break;
                case 0x21:
                    $local = $parser->expectInt(true);
                    $instructions[] = new LocalSet($local);
                    break;
                case 0x22:
                    $local = $parser->expectInt(true);
                    $instructions[] = new LocalSet($local, true);
                    break;
                case 0x23:
                    $global = $parser->expectInt(true);
                    $instructions[] = new GlobalGet($global);
                    break;
                case 0x24:
                    $global = $parser->expectInt(true);
                    $instructions[] = new GlobalSet($global);
                    break;
                case 0x25:
                    $table = $parser->expectInt(true);
                    $instructions[] = new TableGet($table);
                    break;
                case 0x26:
                    $table = $parser->expectInt(true);
                    $instructions[] = new TableSet($table);
                    break;
                case 0x28:
                    /* $align = */ $parser->expectInt(true);
                    $offset = $parser->expectInt(true);
                    $instructions[] = new Load($i32, $offset);
                    break;
                case 0x29:
                    /* $align = */ $parser->expectInt(true);
                    $offset = $parser->expectInt(true);
                    $instructions[] = new Load($i64, $offset);
                    break;
                case 0x2A:
                    /* $align = */ $parser->expectInt(true);
                    $offset = $parser->expectInt(true);
                    $instructions[] = new Load($f32, $offset);
                    break;
                case 0x2B:
                    /* $align = */ $parser->expectInt(true);
                    $offset = $parser->expectInt(true);
                    $instructions[] = new Load($f64, $offset);
                    break;
                case 0x2C:
                    /* $align = */ $parser->expectInt(true);
                    $offset = $parser->expectInt(true);
                    $instructions[] = new Load($i32, $offset, 8, true);
                    break;
                case 0x2D:
                    /* $align = */ $parser->expectInt(true);
                    $offset = $parser->expectInt(true);
                    $instructions[] = new Load($i32, $offset, 8, false);
                    break;
                case 0x2E:
                    /* $align = */ $parser->expectInt(true);
                    $offset = $parser->expectInt(true);
                    $instructions[] = new Load($i32, $offset, 16, true);
                    break;
                case 0x2F:
                    /* $align = */ $parser->expectInt(true);
                    $offset = $parser->expectInt(true);
                    $instructions[] = new Load($i32, $offset, 16, false);
                    break;
                case 0x30:
                    /* $align = */ $parser->expectInt(true);
                    $offset = $parser->expectInt(true);
                    $instructions[] = new Load($i64, $offset, 8, true);
                    break;
                case 0x31:
                    /* $align = */ $parser->expectInt(true);
                    $offset = $parser->expectInt(true);
                    $instructions[] = new Load($i64, $offset, 8, false);
                    break;
                case 0x32:
                    /* $align = */ $parser->expectInt(true);
                    $offset = $parser->expectInt(true);
                    $instructions[] = new Load($i64, $offset, 16, true);
                    break;
                case 0x33:
                    /* $align = */ $parser->expectInt(true);
                    $offset = $parser->expectInt(true);
                    $instructions[] = new Load($i64, $offset, 16, false);
                    break;
                case 0x34:
                    /* $align = */ $parser->expectInt(true);
                    $offset = $parser->expectInt(true);
                    $instructions[] = new Load($i64, $offset, 32, true);
                    break;
                case 0x35:
                    /* $align = */ $parser->expectInt(true);
                    $offset = $parser->expectInt(true);
                    $instructions[] = new Load($i64, $offset, 32, false);
                    break;
                case 0x36:
                    /* $align = */ $parser->expectInt(true);
                    $offset = $parser->expectInt(true);
                    $instructions[] = new Store($i32, $offset);
                    break;
                case 0x37:
                    /* $align = */ $parser->expectInt(true);
                    $offset = $parser->expectInt(true);
                    $instructions[] = new Store($i64, $offset);
                    break;
                case 0x38:
                    /* $align = */ $parser->expectInt(true);
                    $offset = $parser->expectInt(true);
                    $instructions[] = new Store($f32, $offset);
                    break;
                case 0x39:
                    /* $align = */ $parser->expectInt(true);
                    $offset = $parser->expectInt(true);
                    $instructions[] = new Store($f64, $offset);
                    break;
                case 0x3A:
                    /* $align = */ $parser->expectInt(true);
                    $offset = $parser->expectInt(true);
                    $instructions[] = new Store($i32, $offset, 8);
                    break;
                case 0x3B:
                    /* $align = */ $parser->expectInt(true);
                    $offset = $parser->expectInt(true);
                    $instructions[] = new Store($i32, $offset, 16);
                    break;
                case 0x3C:
                    /* $align = */ $parser->expectInt(true);
                    $offset = $parser->expectInt(true);
                    $instructions[] = new Store($i64, $offset, 8);
                    break;
                case 0x3D:
                    /* $align = */ $parser->expectInt(true);
                    $offset = $parser->expectInt(true);
                    $instructions[] = new Store($i64, $offset, 16);
                    break;
                case 0x3E:
                    /* $align = */ $parser->expectInt(true);
                    $offset = $parser->expectInt(true);
                    $instructions[] = new Store($i64, $offset, 32);
                    break;
                case 0x3F:
                    $parser->expectByte(0x00);
                    $instructions[] = new Size();
                    break;
                case 0x40:
                    $parser->expectByte(0x00);
                    $instructions[] = new Grow();
                    break;
                case 0x41:
                    $value = $parser->expectInt();
                    $instructions[] = new ConstStmt($i32, $value);
                    break;
                case 0x42:
                    $value = $parser->expectInt(false, 64);
                    $instructions[] = new ConstStmt($i64, $value);
                    break;
                case 0x43:
                    $value = $parser->expectFloat();
                    $instructions[] = new ConstStmt($f32, $value);
                    break;
                case 0x44:
                    $value = $parser->expectFloat(64);
                    $instructions[] = new ConstStmt($f64, $value);
                    break;
                case 0x45:
                    $instructions[] = new Eqz($i32);
                    break;
                case 0x46:
                    $instructions[] = new Eq($i32);
                    break;
                case 0x47:
                    $instructions[] = new Neq($i32);
                    break;
                case 0x48:
                case 0x49: // todo: differ signed/unsigned
                    $instructions[] = new Lt($i32);
                    break;
                case 0x4A:
                case 0x4B: // todo: differ signed/unsigned
                    $instructions[] = new Gt($i32);
                    break;
                case 0x4C:
                case 0x4D: // todo: differ signed/unsigned
                    $instructions[] = new Le($i32);
                    break;
                case 0x4E:
                case 0x4F: // todo: differ signed/unsigned
                    $instructions[] = new Ge($i32);
                    break;
                case 0x50:
                    $instructions[] = new Eqz($i64);
                    break;
                case 0x51:
                    $instructions[] = new Eq($i64);
                    break;
                case 0x52:
                    $instructions[] = new Neq($i64);
                    break;
                case 0x53:
                case 0x54: // todo: differ signed/unsigned
                    $instructions[] = new Lt($i64);
                    break;
                case 0x55:
                case 0x56: // todo: differ signed/unsigned
                    $instructions[] = new Gt($i64);
                    break;
                case 0x57:
                case 0x58: // todo: differ signed/unsigned
                    $instructions[] = new Le($i64);
                    break;
                case 0x59:
                case 0x5A: // todo: differ signed/unsigned
                    $instructions[] = new Ge($i64);
                    break;
                case 0x5B:
                    $instructions[] = new Eq($f32);
                    break;
                case 0x5C:
                    $instructions[] = new Neq($f32);
                    break;
                case 0x5D:
                    $instructions[] = new Lt($f32);
                    break;
                case 0x5E:
                    $instructions[] = new Gt($f32);
                    break;
                case 0x5F:
                    $instructions[] = new Le($f32);
                    break;
                case 0x60:
                    $instructions[] = new Ge($f32);
                    break;
                case 0x61:
                    $instructions[] = new Eq($f64);
                    break;
                case 0x62:
                    $instructions[] = new Neq($f64);
                    break;
                case 0x63:
                    $instructions[] = new Lt($f64);
                    break;
                case 0x64:
                    $instructions[] = new Gt($f64);
                    break;
                case 0x65:
                    $instructions[] = new Le($f64);
                    break;
                case 0x66:
                    $instructions[] = new Ge($f64);
                    break;
                case 0x6A:
                    $instructions[] = new Add($i32);
                    break;
                case 0x6B:
                    $instructions[] = new Sub($i32);
                    break;
                case 0x6C:
                    $instructions[] = new Mul($i32);
                    break;
                case 0x6D:
                case 0x6E: // todo: differ signed/unsigned
                    $instructions[] = new Div($i32);
                    break;
                case 0x71:
                    $instructions[] = new BitAnd($i32);
                    break;
                case 0x72:
                    $instructions[] = new BitOr($i32);
                    break;
                case 0x73:
                    $instructions[] = new BitXor($i32);
                    break;
                case 0x74:
                    $instructions[] = new BitShl($i32);
                    break;
                case 0x75:
                case 0x76: // todo: differ signed/unsigned
                    $instructions[] = new BitShr($i32);
                    break;
                case 0x7C:
                    $instructions[] = new Add($i64);
                    break;
                case 0x7D:
                    $instructions[] = new Sub($i64);
                    break;
                case 0x7E:
                    $instructions[] = new Mul($i64);
                    break;
                case 0x7F:
                case 0x80: // todo: differ signed/unsigned
                    $instructions[] = new Div($i64);
                    break;
                case 0x83:
                    $instructions[] = new BitAnd($i64);
                    break;
                case 0x84:
                    $instructions[] = new BitOr($i64);
                    break;
                case 0x85:
                    $instructions[] = new BitXor($i64);
                    break;
                case 0x86:
                    $instructions[] = new BitShl($i64);
                    break;
                case 0x87:
                case 0x88: // todo: differ signed/unsigned
                    $instructions[] = new BitShr($i64);
                    break;
                case 0x92:
                    $instructions[] = new Add($f32);
                    break;
                case 0x93:
                    $instructions[] = new Sub($f32);
                    break;
                case 0x94:
                    $instructions[] = new Mul($f32);
                    break;
                case 0x95:
                    $instructions[] = new Div($f32);
                    break;
                case 0xA0:
                    $instructions[] = new Add($f64);
                    break;
                case 0xA1:
                    $instructions[] = new Sub($f64);
                    break;
                case 0xA2:
                    $instructions[] = new Mul($f64);
                    break;
                case 0xA3:
                    $instructions[] = new Div($f64);
                    break;
                case 0xD0:
                    $value = $parser->expectByte();
                    $instructions[] = new NullRef(new RefType($value));
                    break;
                case 0xD1:
                    $instructions[] = new IsNull();
                    break;
                case 0xD2:
                    $value = $parser->expectInt(true);
                    $instructions[] = new RefFunc($value);
                    break;
                case 0xFC: // subswitch
                    $secondary = $parser->expectInt(true);
                    switch ($secondary) {
                        case 8:
                            $dataIdx = $parser->expectInt(true);
                            $parser->expectByte(0x00);
                            $instructions[] = new Init($dataIdx);
                            break;
                        case 9:
                            $dataIdx = $parser->expectInt(true);
                            $instructions[] = new DataDrop($dataIdx);
                            break;
                        case 10:
                            $parser->expectByte(0x00);
                            $parser->expectByte(0x00);
                            $instructions[] = new Copy();
                            break;
                        case 11:
                            $parser->expectByte(0x00);
                            $instructions[] = new Fill();
                            break;
                        case 12:
                            $elemIdx = $parser->expectInt(true);
                            $tableIdx = $parser->expectInt(true);
                            $instructions[] = new TableInit($tableIdx, $elemIdx);
                            break;
                        case 13:
                            $dataIdx = $parser->expectInt(true);
                            $instructions[] = new ElemDrop($dataIdx);
                            break;
                        case 14:
                            $x = $parser->expectInt(true);
                            $y = $parser->expectInt(true);
                            $instructions[] = new TableCopy($x, $y);
                            break;
                        case 15:
                            $tableIdx = $parser->expectInt(true);
                            $instructions[] = new TableGrow($tableIdx);
                            break;
                        case 16:
                            $tableIdx = $parser->expectInt(true);
                            $instructions[] = new TableSize($tableIdx);
                            break;
                        case 17:
                            $tableIdx = $parser->expectInt(true);
                            $instructions[] = new TableFill($tableIdx);
                            break;
                        default:
                            throw new \RuntimeException('Unknown secondary opcode ' . strval($secondary));
                    }
                    break;
                case $termOpcode:
                    echo "Expression terminated\n";
                    return $instructions;
                default:
                    $pos = str_pad(dechex($parser->position() - 1), 8, '0', STR_PAD_LEFT);
                    throw new \RuntimeException('Unknown opcode 0x' . str_pad(dechex($current), 2, '0', STR_PAD_LEFT) . '@0x' . $pos);
            }
        }
    }
}
