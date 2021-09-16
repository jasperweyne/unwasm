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

namespace UnWasm\Compiler\Binary;

class Token
{
    private $value;
    private $type;
    private $pos;

    public const BYTE_TYPE = 0;
    public const STRING_TYPE = 1;
    public const FLOAT_TYPE = 0x7D;
    public const FLOAT_64_TYPE = 0x7C;
    public const INT_TYPE = 0x7F;
    public const INT_64_TYPE = 0x7E;
    public const UINT_TYPE = 6;
    public const UINT_64_TYPE = 7;

    public function __construct(int $type, $value, int $pos = null)
    {
        $this->type = $type;
        $this->value = $value;
        $this->pos = $pos;
    }

    public function getPos(): int
    {
        return $this->pos;
    }

    public function getType(): int
    {
        return $this->type;
    }

    public function getValue()
    {
        return $this->value;
    }

    public static function parse(int $type, string $raw, int $pos): self
    {
        return new self($type, self::decode($type, $raw), $pos);
    }

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
            case self::FLOAT_64_TYPE:
                list(, $result) = unpack("g", $raw); // todo: differ between 32/64 bits
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
                throw new \UnexpectedValueException();
        }
        return $result;
    }

    private static function decodeLeb128(string $raw, bool $signed = true): int
    {
        $len = 0;
        $x = 0;
        $orig = $raw;
        while ($raw) {
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
