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
use UnWasm\Exception\LexingException;
use UnWasm\Exception\ParsingException;

/**
 * Parses webassembly binary format and generates an internal
 * representation for compilation.
 */
class BinaryParser implements ParserInterface
{
    /** @var resource The data stream parsed. */
    protected $stream;

    /** @var BuilderInterface[] A list of builder class instances */
    public $builders;

    /** @param resource $stream */
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

            $break = $this->assertSize(function (self $parser, int $start, int $size) use ($section, $compiler) {
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

    public function scanHeader(): void
    {
        // validate stream
        $magic = "\0asm";
        if (fread($this->stream, 4) !== $magic) {
            throw new ParsingException('Provided stream does not represent a valid binary webassembly source');
        }

        // validate wasm version
        $read = (string) fread($this->stream, 4);
        list(, $version) = (array) unpack('V', $read);
        if ($version !== 1) {
            throw new ParsingException('Only version 1 binary webassembly is supported');
        }
    }

    public function position(): int
    {
        $pos = ftell($this->stream);
        if ($pos === false) {
            throw new LexingException("Can't tell position of stream");
        }
        return $pos;
    }

    public function expectByte(int $minIncl = null, int $maxIncl = null): int
    {
        $pos = $this->position();
        $value = fread($this->stream, 1);
        if ($value === false) {
            throw new LexingException("No value read");
        }

        $result = (int) Token::parse(Token::BYTE_TYPE, $value, $pos)->getValue();
        if ($minIncl && $result < $minIncl) {
            throw new ParsingException("Invalid value");
        }

        if (($maxIncl && $result > $maxIncl) || ($minIncl && $result != $minIncl)) {
            throw new ParsingException("Invalid value");
        }

        return $result;
    }

    /**
     * @phpstan-template X
     * @phpstan-param callable(self, int): X $elementFn
     * @phpstan-return array<int, X>
     */
    public function expectVector(callable $elementFn): array
    {
        $size = $this->expectInt(true);
        $children = [];

        for ($i = 0; $i < $size; $i++) {
            $children[$i] = $elementFn($this, $i);
        }

        return $children;
    }

    public function expectString(string $regex = '//', bool $validate = true): string
    {
        // technically, expectVector could be wrapped, but this is more efficient
        // obtain raw string from stream
        $pos = $this->position();
        $size = $this->expectInt(true);
        if ($size === 0) {
            return '';
        }
        $value = fread($this->stream, $size);

        if ($value === false) {
            throw new LexingException();
        }

        // validate utf8 encoding
        if ($validate && !mb_check_encoding($value, 'UTF-8')) {
            throw new ParsingException('Invalid utf8 string');
        }

        // parse and validate result value
        $result = (string) Token::parse(Token::STRING_TYPE, $value, $pos)->getValue();
        if (preg_match($regex, $result) !== 1) {
            throw new ParsingException();
        }

        return $result;
    }

    /**
     * @param int<0,max> $bits
     */
    public function expectFloat(int $bits = 32): float
    {
        $pos = $this->position();
        $width = $bits / 8;
        $value = fread($this->stream, $width);
        if (!is_string($value)) {
            throw new LexingException("Invalid value");
        }

        $type = $bits == 64 ? Token::FLOAT_64_TYPE : Token::FLOAT_TYPE;
        return (float) Token::parse($type, $value, $pos)->getValue();
    }

    /**
     * @param int<0,max> $bits
     */
    public function expectInt(bool $unsigned = false, int $bits = 32): int
    {
        $pos = $this->position();
        $width = $bits / 8;

        // lex LEB128
        $value = "";
        for ($i = 0;; $i++) {
            if ($i >= ceil($bits / 7.0)) {
                throw new LexingException("An int larger than $bits bits was provided");
            }

            $char = fread($this->stream, 1);
            if (!is_string($char)) {
                throw new LexingException("Unexpected read error");
            }
            $value .= $char;

            // Check if encoded value has completed
            if ((ord($char) & 0x80) == 0) {
                break;
            }
        }

        // validate integer by checking last character
        if ((ord(substr($value, -1)) & 0x80) != 0) {
            $str = bin2hex($value);
            $posstr = str_pad(dechex($this->position()), 8, '0', STR_PAD_LEFT);
            throw new ParsingException("Invalid integer provided $str@0x$posstr");
        }

        // set type
        $type = -1;
        if ($unsigned) {
            $type = $bits == 64 ? Token::UINT_64_TYPE : Token::UINT_TYPE;
        } else {
            $type = $bits == 64 ? Token::INT_64_TYPE : Token::INT_TYPE;
        }

        return (int) Token::parse($type, $value, $pos)->getValue();
    }

    /**
     * @template T
     * @return T
     * @param callable(self, int, int): T $inner
     */
    public function assertSize(callable $inner)
    {
        $size = $this->expectInt(true);
        $start = $this->position();

        $result = $inner($this, $start, $size);

        // validate section size with contents size
        $contents = $this->position() - $start;
        if ($size !== 0 && $size !== $contents) {
            throw new ParsingException("Expected size $size does not match contents size $contents");
        }

        return $result;
    }

    public function eof(): bool
    {
        return feof($this->stream);
    }
}
