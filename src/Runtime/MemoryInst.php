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
 * A runtime memory instance for a module
 */
class MemoryInst
{
    public const PAGE_SIZE = 65536;

    /** @var ?int Represents the size constraints on this memory instance, measured in pages */
    private $maximum;

    /**
     * @var resource A resource handle for a php://temp
     *
     * This stream represents a raw data buffer, stored in both RAM and on disk.
     * Although php://memory would store in RAM only (increasing speed), it
     * might exceed (usually low) memory limits, a fatal error that can not be
     * recovered from in PHP.
     */
    private $stream;

    /** @var int The number of currently allocated pages */
    private $size;

    public function __construct(int $minimum, ?int $maximum = null)
    {
        $this->maximum = $maximum;
        $this->stream = fopen('php://temp', 'r+b');
        $this->size = 0;

        // todo: create methods for store/load/data
        // todo: respect datas/elems?

        // zero initialise memory
        $this->grow($minimum);
    }

    public function size()
    {
        return $this->size;
    }

    public function grow(int $pages): int
    {
        // store the initial memory size
        $prev = $this->size();

        // validate limits
        if ($prev + $pages > $this->maximum) {
            return -1;
        }

        // create a block of zero-initialised data
        $emptyPage = str_repeat(chr(0), self::PAGE_SIZE);

        // move the pointer to the end of the stream
        fseek($this->stream, 0, SEEK_END);

        // increment the stream size in page-sized increments
        for ($i = 0; $i < $pages; $i++) {
            // write a page of zero-initialised data to the stream
            $written = fwrite($this->stream, $emptyPage);

            // if this did not succeed, abort and report error
            if ($written != self::PAGE_SIZE) {
                return -1;
            }
        }

        // succeeded, update current memory size
        $this->size += $pages;

        // return initial memory size
        return $prev;
    }

    public function fill(int $n, int $value, int $offset)
    {
        // validate offset+n is inside memory bounds
        if ($offset + $n > $this->size() * self::PAGE_SIZE) {
            throw new \OutOfBoundsException();
        }

        // create the block of data (note that $value is truncated to a single byte)
        $data = str_repeat(chr($value % 256), $n);

        // move the pointer to the offset
        fseek($this->stream, $offset);

        // overwrite the data
        fwrite($this->stream, $data);
    }

    public function copy()
    {
        // todo
    }
}
