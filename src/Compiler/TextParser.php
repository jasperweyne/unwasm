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

use UnWasm\Compiler\Text\BuilderInterface;
use UnWasm\Compiler\Text\DatasBuilder;
use UnWasm\Compiler\Text\ElemsBuilder;
use UnWasm\Compiler\Text\ExportsBuilder;
use UnWasm\Compiler\Text\FuncsBuilder;
use UnWasm\Compiler\Text\GlobalsBuilder;
use UnWasm\Compiler\Text\ImportsBuilder;
use UnWasm\Compiler\Text\MemsBuilder;
use UnWasm\Compiler\Text\StartBuilder;
use UnWasm\Compiler\Text\TablesBuilder;
use UnWasm\Compiler\Text\TypesBuilder;
use UnWasm\Exception\CompilationException;
use UnWasm\Exception\CompilerException;
use UnWasm\Exception\LexingException;
use UnWasm\Exception\ParsingException;

/**
 * Parses webassembly text format and generates an internal
 * representation for compilation.
 */
class TextParser implements ParserInterface
{
    /** @var string The string being parsed. */
    protected $data;

    /** @var int The current read offset within $this->data. */
    protected $offset;

    /** @var BuilderInterface[] A list of builder class instances */
    public $builders;

    private const IDCHAR = '[\x21\x23-\x27\x2A-\x2B\x2D-\x3A\x3C-\x5A\x5C\x5E-\x7A\x7C\x7E]';
    private const NUM = '[0-9](?:_?[0-9])*';
    private const HEXNUM = '[0-9A-Fa-f](?:_?[0-9A-Fa-f])*';

    /** @param resource $stream */
    public function __construct($stream)
    {
        // copy the stream to memory for regexes
        rewind($stream);
        $this->data = (string) stream_get_contents($stream);
        $this->offset = 0;

        // intialise builders
        $this->builders = [
            new TypesBuilder(),
            new ImportsBuilder(),
        ];
    }

    /**
     * Scan the stream passed during construction and result its contents in a structured ModuleCompiler object
     */
    public function scan(ModuleCompiler $compiler = null): ModuleCompiler
    {
        echo "Begin scanning text\n";
        $compiler = $compiler ?? new ModuleCompiler();

        // module parser function
        $parseModule = function () use ($compiler) {
            foreach ($this->builders as $builder) {
                $this->vec(function (self $parser) use ($builder, $compiler) {
                    $builder->scan($parser, $compiler);
                });
            }
        };

        // scan start of the module
        $this->oneOf(
            function () use ($compiler, $parseModule) {
                $this->parenthesised(function () use ($compiler, $parseModule) {
                    $this->expectKeyword('module');
                    $compiler->id = $this->expectId(true);
                    ($parseModule)();
                });
            },
            function () use ($parseModule) {
                ($parseModule)();
            }
        );

        echo "Finished scanning text\n";
        return $compiler;
    }

    /**
     * @phpstan-template T
     * @phpstan-param callable(TextParser): T $func
     * @phpstan-return T[]
     */
    public function vec(callable $func): array
    {
        $result = [];
        while (true) {
            $origOffset = $this->offset;
            try {
                $result[] = ($func)($this);
            } catch (CompilerException $e) {
                $this->offset = $origOffset;
                return $result;
            }
        }
    }

    /**
     * @phpstan-template T
     * @phpstan-param callable(TextParser): T $func
     * @phpstan-param T $default
     * @phpstan-return T
     */
    public function maybe(callable $func, $default = null)
    {
        $origOffset = $this->offset;
        try {
            return ($func)($this);
        } catch (CompilerException $e) {
            // continue to next item
            $this->offset = $origOffset;
            return $default;
        }
    }

    /**
     * @phpstan-template T
     * @phpstan-param callable(TextParser): T $funcs
     * @phpstan-return T
     */
    public function oneOf(callable ...$funcs)
    {
        $origOffset = $this->offset;
        foreach ($funcs as $func) {
            try {
                return ($func)($this);
            } catch (CompilerException $e) {
                // continue to next item
                $this->offset = $origOffset;
            }
        }

        throw new CompilationException('Expected a token');
    }

    /**
     * @phpstan-template T
     * @phpstan-param callable(TextParser): T $func
     * @phpstan-return T
     */
    public function parenthesised(callable $func)
    {
        // parse (
        if ($this->nextToken('/\G\(/') === null) {
            throw new ParsingException("Expected '('");
        }

        // parse contents
        $value = ($func)($this);

        // parse )
        if ($this->nextToken('/\G\)/') === null) {
            throw new ParsingException("Expected ')'");
        }

        // return contents
        return $value;
    }

    public function expectInt(bool $unsigned = false): int
    {
        $hexnum = self::HEXNUM;
        $num = self::NUM;

        // get int string
        $s = $unsigned ? '+?' : '[-+]?';
        $result = $this->nextToken("/\G$s(?:0x$hexnum|$num)/");
        if ($result === null) {
            throw new LexingException('Expected integer');
        }

        // parse and return int
        $base = strpos($result, '0x') !== false ? 16 : 10;
        return intval($result, $base);
    }

    public function expectFloat(): float
    {
        $hexnum = self::HEXNUM;
        $num = self::NUM;

        // get int string
        $result = $this->nextToken("/\G[-+]?(?:0x$hexnum(?:\\.$hexnum)?[Pp][+-]?$num|$num(?:\\.$num)?[Ee][+-]?$num)/");
        if ($result === null) {
            throw new LexingException('Expected float');
        }

        // convert hex to dec notation
        if (strpos($result, '0x') !== false) {
            throw new \LogicException('todo');
        }

        return floatval($result);
    }

    public function expectId(bool $maybe = true): ?string
    {
        $idchar = self::IDCHAR;
        $result = $this->nextToken("/\G\\$$idchar+/");
        if ($result === null) {
            if ($maybe) {
                return null;
            } else {
                throw new LexingException('Expected id');
            }
        }
        return $result;
    }
    
    public function expectPartialKeyword(string ...$options): string
    {
        if (count($options) == 0) {
            throw new \InvalidArgumentException('No options provided');
        }

        // if no keyword (option) found, return null
        $options = implode('|', array_map('preg_quote', $options));
        $result = $this->nextToken("/\G($options)/");
        if ($result === null) {
            throw new LexingException('Expected partial keyword '.$options);
        }

        return $result;
    }

    public function expectKeyword(string ...$options): string
    {
        // save the original offset and find a keyword
        $origOffset = $this->offset;
        $idchar = self::IDCHAR;
        $result = $this->nextToken("/\G[a-z]$idchar*/");

        // if no keyword (option) found, return null
        if ($result === null || (count($options) > 0 && !in_array($result, $options))) {
            $this->offset = $origOffset;
            throw new LexingException('Expected keyword');
        } else {
            return $result;
        }
    }

    public function expectString(): string
    {
        // setup constant matches
        $constRepl = ['\\t', '\\n', '\\r', '\\"', "\\'", '\\\\'];
        $utf8Repl = '\\\\u{([0-9A-Fa-f](?:_?[0-9A-Fa-f])*)}';
        $hexRepl = '\\\\([0-9A-Fa-f]{2})';

        // find a string token
        $origOffset = $this->offset;
        $escaped = implode('|', array_merge($constRepl, [$utf8Repl, $hexRepl]));
        $result = $this->nextToken("/\G\"((?:[^\\x00-\\x1f\\\"]|$escaped)*)\"/u", 1);
        if ($result === null) {
            throw new LexingException('Expected string');
        }

        // replace escaped characters
        $result = str_replace(
            $constRepl,
            [chr(0x09), chr(0x0A), chr(0x0D), chr(0x22), chr(0x27), 0x5C],
            $result
        );

        // replace UTF8 codepoint specifier
        $result = (string) preg_replace_callback("/$utf8Repl/", function (array $hex) {
            return (string) hex2bin(str_replace('_', '', $hex[1]));
        }, $result);

        // validate utf 8 encoding
        if (!mb_check_encoding($result, 'UTF-8')) {
            $this->offset = $origOffset;
            throw new ParsingException('Invalid string encoding');
        }

        // replace hex byte specifier
        $result = (string) preg_replace_callback("/$hexRepl/", function (array $hex) {
            return (string) hex2bin($hex[1]);
        }, $result);

        return $result;
    }

    private function nextToken(string $regex, int $group = 0): ?string
    {
        // skip empty lines and comments
        $empty = '\\s+';
        $linecomment = ';;[^\x0A]*(?:\x0A|\Z)';
        $blockcomment = '(\\(;(?:[^;\\(]|;(?!\\))|\\((?!;)|(?1))*;\\))'; // recursive
        while ($this->nextItem("/\G($empty|$linecomment|$blockcomment)/") !== null);

        // return regex
        return $this->nextItem($regex, $group);
    }

    private function nextItem(string $regex, int $group = 0): ?string
    {
        if (preg_match($regex, $this->data, $matches, PREG_OFFSET_CAPTURE, $this->offset)) {
            $res = $matches[$group];
            $this->offset = $res[1] + strlen($res[0]);
            return $res[0];
        }
        return null;
    }
}
