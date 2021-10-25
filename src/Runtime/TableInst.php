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

namespace UnWasm\Store;

/**
 * A runtime table instance for a module
 */
class TableInst
{
    /** @var ?int Represents the size constraints on this memory instance, measured in pages */
    private $maximum;

    /**
     * @var ?callable[] The table contents
     */
    private $contents;

    public function __construct(int $minimum, ?int $maximum = null)
    {
        // todo: respect funcref/externref
        $this->maximum = $maximum;
        $this->contents = [];

        // zero initialise memory
        $this->grow($minimum, null);
    }

    public function get(int $offset): ?callable
    {
        // validate offset is inside memory bounds
        if ($offset >= $this->size()) {
            throw new \OutOfBoundsException();
        }

        // return the value from contents
        return $this->contents[$offset];
    }

    public function set(?callable $value, int $offset): void
    {
        // validate offset is inside memory bounds
        if ($offset >= $this->size()) {
            throw new \OutOfBoundsException();
        }

        // override contents with value at offset
        $this->contents[$offset] = $value;
    }

    public function size(): int
    {
        return count($this->contents);
    }

    public function grow(int $n, ?callable $init): int
    {
        // store the initial memory size
        $prev = $this->size();

        // validate limits
        if ($this->maximum !== null && $prev + $n > $this->maximum) {
            return -1;
        }

        // grow the contents array
        $this->contents = array_merge($this->contents, array_fill(0, $n, $init));

        // return initial memory size
        return $prev;
    }

    public function fill(int $n, ?callable $value, int $offset)
    {
        // validate offset+n is inside memory bounds
        if ($offset + $n > $this->size()) {
            throw new \OutOfBoundsException();
        }

        // override contents values
        for ($i = $offset; $i < $offset + $n; $i++) {
            $this->contents[$i] = $value; // shorthand set
        }
    }

    public function copy(TableInst $dest, int $sourceOffset, int $destOffset, int $n)
    {
        // validate offset+n is inside memory bounds
        if ($sourceOffset + $n > $this->size() || $destOffset + $n > $dest->size()) {
            throw new \OutOfBoundsException();
        }

        // read the data at source and write it to dest
        $dest->overwrite(array_slice($this->contents, $sourceOffset, $n), $destOffset);
    }

    public function overwrite(array $data, int $offset)
    {
        // validate offset+n is inside memory bounds
        if ($offset + count($data) > $this->size()) {
            throw new \OutOfBoundsException();
        }

        // overwrite per element from data
        for ($i = 0; $i < count($data); $i++) {
            $this->contents[$offset + $i] = $data[$i];
        }
    }
}
