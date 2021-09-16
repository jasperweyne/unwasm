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

namespace UnWasm\Compiler;

/**
 * Compiled source code builder.
 */
class Source
{
    /** @var string */
    private $source = '';

    /** @var int */
    private $indentation = 0;

    /**
     * Returns the compiled code.
     *
     * @return string Compiled code
     */
    public function read(): string
    {
        return $this->source;
    }

    /**
     * Adds a raw string to the compiled code (without indentation).
     *
     * @param string $string The string
     *
     * @return $this
     */
    public function raw($string)
    {
        $this->source .= $string;

        return $this;
    }

    /**
     * Add the current indentation to the compiled code.
     *
     * @return $this
     */
    public function start(): self
    {
        $this->source .= str_repeat(' ', $this->indentation * 4);

        return $this;
    }

    /**
     * Writes a string as a single indented line to the compiled code.
     *
     * @return $this
     */
    public function write(...$strings): self
    {
        $content = implode($strings).PHP_EOL;

        $this->start();
        $this->raw($content);

        return $this;
    }

    /**
     * Removes the last complete line written.
     *
     * @return $this
     */
    public function revert(): self
    {
        // find last end-of-line character
        $lastLine = strrpos($this->source, PHP_EOL) ?: 0;

        // find second to last end-of-line character and remove up to that point
        $length = strrpos($this->source, PHP_EOL, $lastLine - strlen($this->source) - 1) ?: 0;
        $this->source = substr($this->source, 0, $length + strlen(PHP_EOL));

        return $this;
    }

    /**
     * Indents the generated code.
     *
     * @param int $step The number of indentation to add
     *
     * @return $this
     */
    public function indent($step = 1): self
    {
        $this->indentation += $step;

        return $this;
    }

    /**
     * Outdents the generated code.
     *
     * @param int $step The number of indentation to remove
     *
     * @return $this
     *
     * @throws \LogicException When trying to outdent too much so the indentation would become negative
     */
    public function outdent($step = 1): self
    {
        // can't outdent by more steps than the current indentation level
        if ($this->indentation < $step) {
            throw new \LogicException('Step is larger than the current indentation level.');
        }

        $this->indentation -= $step;

        return $this;
    }
}
