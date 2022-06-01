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

namespace UnWasm\Compiler\Binary;

use UnWasm\Exception\ParsingException;

class Token
{
    /** @var string|int|float The parsed token value. */
    private $value;

    /** @var int The type constant representing the token type. */
    private $type;
    
    /** @var ?int The position in the stream of the token. */
    private $pos;

    public const INT_TYPE = -1; // 0x7F
    public const INT_64_TYPE = -2; // 0x7E
    public const FLOAT_TYPE = -3; // 0x7D
    public const FLOAT_64_TYPE = -4; // 0x7C
    public const FUNCREF_TYPE = -16; // 0x70
    public const EXTREF_TYPE = -17; // 0x6F
    public const BYTE_TYPE = 0;
    public const STRING_TYPE = 1;
    public const UINT_TYPE = 6;
    public const UINT_64_TYPE = 7;

    /**
     * @param string|int|float $value The parsed value.
     */
    public function __construct(int $type, $value, int $pos = null)
    {
        $this->type = $type;
        $this->value = $value;
        $this->pos = $pos;
    }

    public function getPos(): ?int
    {
        return $this->pos;
    }

    public function getType(): int
    {
        return $this->type;
    }

    /**
     * @return string|int|float
     */
    public function getValue()
    {
        return $this->value;
    }

    public static function parse(int $type, string $raw, int $pos): self
    {
        return new self($type, self::decode($type, $raw), $pos);
    }

    /**
     * @return string|int|float
     */
    private static function decode(int $type, string $raw)
    {
        $result = 0;
        switch ($type) {
            case self::BYTE_TYPE:
                $result = ord($raw);
                break;
            case self::STRING_TYPE:
                $result = $raw;
                break;
            case self::FLOAT_TYPE:
                $unpacked = unpack("g", $raw);
                if (!$unpacked) {
                    throw new \InvalidArgumentException('Invalid float value');
                }
                list(, $result) = $unpacked; 
                break;
            case self::FLOAT_64_TYPE:
                $unpacked = unpack("e", $raw);
                if (!$unpacked) {
                    throw new \InvalidArgumentException('Invalid float value');
                }
                list(, $result) = $unpacked; 
                break;
            case self::INT_TYPE:
            case self::INT_64_TYPE:
                $result = self::decodeLeb128($raw);
                break;
            case self::UINT_TYPE:
            case self::UINT_64_TYPE:
                $result = self::decodeLeb128($raw, false);
                break;
            default:
                throw new ParsingException();
        }
        return $result;
    }

    private static function decodeLeb128(string $raw, bool $signed = true): int
    {
        $len = 0;
        $x = 0;
        $orig = $raw;
        while (isset($raw[0])) {
            $char = ord($raw);
            $raw = substr($raw, 1);

            $x |= ($char & 0x7f) << (7 * $len);
            $len++;
        }

        if ($signed) {
            // invert if negative
            $shift = 7 * $len;
            $last = substr($orig, -1);
            if (ord($last) & 0x40) {
                $x |= -(1 << $shift);
            }
        }

        return $x;
    }
}
