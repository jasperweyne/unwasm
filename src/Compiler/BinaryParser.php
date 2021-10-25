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

namespace UnWasm\Compiler;

use UnWasm\Compiler\Binary\BuilderInterface;
use UnWasm\Compiler\Binary\DatasBuilder;
use UnWasm\Compiler\Binary\ElemsBuilder;
use UnWasm\Compiler\Binary\ExportsBuilder;
use UnWasm\Compiler\Binary\FuncsBuilder;
use UnWasm\Compiler\Binary\GlobalsBuilder;
use UnWasm\Compiler\Binary\ImportsBuilder;
use UnWasm\Compiler\Binary\MemsBuilder;
use UnWasm\Compiler\Binary\StartBuilder;
use UnWasm\Compiler\Binary\TablesBuilder;
use UnWasm\Compiler\Binary\Token;
use UnWasm\Compiler\Binary\TypesBuilder;

/**
 * Parses webassembly binary format and generates an internal
 * representation for compilation.
 */
class BinaryParser implements ParserInterface
{
    protected $stream;

    /** @var BuilderInterface[] A list of builder class instances */
    public $builders;

    public function __construct($stream)
    {
        $this->stream = $stream;
        $this->builders = [
            new TypesBuilder(),
            new ImportsBuilder(),
            new FuncsBuilder(),
            new TablesBuilder(),
            new MemsBuilder(),
            new GlobalsBuilder(),
            new ExportsBuilder(),
            new StartBuilder(),
            new DatasBuilder(),
            new ElemsBuilder(),
        ];
    }

    /**
     * Scan the stream passed during construction and result its contents in a structured ModuleCompiler object
     */
    public function scan(): ModuleCompiler
    {
        echo "Begin scanning binary\n";
        rewind($this->stream);
        $this->scanHeader();

        $compiler = new ModuleCompiler();

        // Parse sections while they're available
        // todo: enforce correct section order
        while (1) {
            $section = $this->expectByte();

            $break = $this->assertSize(function (self $parser, int $start, int $size) use ($section, $compiler, &$unknown) {
                do { // do/while(0) block provides structured way to break when section has been scanned
                    if ($size === 0) {
                        return true;
                    } // break when sectionsize is zero
                    foreach ($this->builders as $builder) {
                        if ($builder->supported($section, $this)) {
                            $builder->scan($this, $compiler);
                            break 2; // break out of do/while(0) block
                        } else {
                            fseek($this->stream, $start);
                        }
                    }

                    // if no builder supported the section, skip to end of stream
                    echo "Unknown section $section@($start, $size)\n";
                    fseek($this->stream, $size, SEEK_CUR);
                } while (0);
            });

            if ($break) {
                break;
            }
        }

        echo "Finished scanning binary\n";
        return $compiler;
    }

    public function scanHeader()
    {
        // validate stream
        $magic = "\0asm";
        if (fread($this->stream, 4) !== $magic) {
            throw new \InvalidArgumentException('Provided stream does not represent a valid binary webassembly source');
        }

        // validate wasm version
        $read = fread($this->stream, 4);
        list(, $version) = unpack('V', $read);
        if ($version !== 1) {
            throw new \InvalidArgumentException('Only version 1 binary webassembly is supported');
        }
    }

    public function position(): int
    {
        $pos = ftell($this->stream);
        if ($pos === false) {
            throw new \RuntimeException("Can't tell position of stream");
        }
        return $pos;
    }

    public function expectByte(int $minIncl = null, int $maxIncl = null): int
    {
        $pos = ftell($this->stream);
        $value = fread($this->stream, 1);
        if ($value === false) {
            throw new \RuntimeException("No value read");
        }

        $result = Token::parse(Token::BYTE_TYPE, $value, $pos);
        if ($minIncl && $result->getValue() < $minIncl) {
            throw new \RuntimeException("Invalid value");
        }

        if (($maxIncl && $result->getValue() > $maxIncl) || ($minIncl && $result->getValue() != $minIncl)) {
            throw new \RuntimeException("Invalid value");
        }

        return $result->getValue();
    }

    public function expectVector(callable $elementFn): array
    {
        $size = $this->expectInt(true);
        $children = array_fill(0, $size, 0); // initialize array with length

        for ($i = 0; $i < $size; $i++) {
            $children[$i] = $elementFn($this, $i);
        }

        return $children;
    }

    public function expectString(string $regex = '//', bool $validate = true): string
    {
        // technically, expectVector could be wrapped, but this is more efficient
        // obtain raw string from stream
        $pos = ftell($this->stream);
        $size = $this->expectInt(true);
        if ($size === 0) return '';
        $value = fread($this->stream, $size);

        if ($value === false) {
            throw new \UnexpectedValueException();
        }

        // validate utf8 encoding
        if ($validate && !mb_check_encoding($value, 'UTF-8')) {
            throw new \UnexpectedValueException('Invalid utf8 string');
        }

        // parse and validate result value
        $result = Token::parse(Token::STRING_TYPE, $value, $pos);
        if (preg_match($regex, $result->getValue()) !== 1) {
            throw new \UnexpectedValueException();
        }

        return $result->getValue();
    }

    public function expectFloat($bits = 32): float
    {
        $pos = ftell($this->stream);
        $width = $bits / 8;
        $value = fread($this->stream, $width);
        if (!is_string($value)) {
            throw new \RuntimeException("Invalid value");
        }

        $type = $bits == 64 ? Token::FLOAT_64_TYPE : Token::FLOAT_TYPE;
        return Token::parse($type, $value, $pos)->getValue();
    }

    public function expectInt($unsigned = false, $bits = 32): int
    {
        $pos = ftell($this->stream);
        $width = $bits / 8;

        // lex LEB128
        $value = "";
        for ($i = 0; $i < $width; $i++) {
            $char = fread($this->stream, 1);
            if (!is_string($char)) {
                throw new \RuntimeException("Unexpected read error");
            }
            $value .= $char;

            // Check if encoded value has completed
            if ((ord($char) & 0x80) == 0) {
                break;
            }
        }

        // validate integer by checking last character
        if ((ord(substr($value, -1)) & 0x80) != 0) {
            throw new \RuntimeException("Invalid integer provided");
        }

        // set type
        $type = -1;
        if ($unsigned) {
            $type = $bits == 64 ? Token::UINT_64_TYPE : Token::UINT_TYPE;
        } else {
            $type = $bits == 64 ? Token::INT_64_TYPE : Token::INT_TYPE;
        }

        return Token::parse($type, $value, $pos)->getValue();
    }

    public function assertSize(callable $inner)
    {
        $size = $this->expectInt(true);
        $start = ftell($this->stream);

        $result = $inner($this, $start, $size);

        // validate section size with contents size
        $contents = $this->position() - $start;
        if ($size !== 0 && $size !== $contents) {
            throw new \UnexpectedValueException("Expected size $size does not match contents size $contents");
        }

        return $result;
    }

    public function eof(): bool
    {
        return feof($this->stream);
    }
}
