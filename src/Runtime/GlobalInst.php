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

namespace UnWasm\Runtime;

/**
 * A runtime global instance for a module
 */
class GlobalInst
{
    /** @var int The current value of this global instance, in case the type is an int */
    private $intValue;

    /** @var int The current value of this global instance, in case the type is a float */
    private $floatValue;

    /** @var ?callable The current value of this global instance, in case the type is a callable */
    private $refValue;

    /** @var string The type of value this global holds */
    private $type;

    /** @var ?int The amount of times the value may be mutated */
    private $mutateCnt;

    public function __construct(string $type, bool $mutable)
    {
        $this->type = $type;
        $this->intValue = 0;
        $this->floatValue = 0;
        $this->refValue = null;
        $this->mutateCnt = $mutable ? 1 : null;
    }

    public function getInt(): int
    {
        if ($this->type !== 'i' && $this->type !== 'I') {
            throw new \LogicException('The type of this global is not an integer');
        }

        return $this->intValue;
    }

    public function getFloat(): float
    {
        if ($this->type !== 'f' && $this->type !== 'F') {
            throw new \LogicException('The type of this global is not a float');
        }

        return $this->floatValue;
    }

    public function getRef(): ?callable
    {
        if ($this->type !== 'r') {
            throw new \LogicException('The type of this global is not a callable reference');
        }

        return $this->refValue;
    }

    public function setInt(int $value): void
    {
        if ($this->type !== 'i' && $this->type !== 'I') {
            throw new \LogicException('The type of this global is not an integer');
        }

        if ($this->mutateCnt === 0) {
            throw new \LogicException('This global is immutable');
        } elseif ($this->mutateCnt !== null) {
            $this->mutateCnt--;
        }

        $this->intValue = $value;
    }

    public function setFloat(float $value): void
    {
        if ($this->type !== 'f' && $this->type !== 'F') {
            throw new \LogicException('The type of this global is not a float');
        }

        if ($this->mutateCnt === 0) {
            throw new \LogicException('This global is immutable');
        } elseif ($this->mutateCnt !== null) {
            $this->mutateCnt--;
        }

        $this->floatValue = $value;
    }

    public function setRef(?callable $value): void
    {
        if ($this->type !== 'r') {
            throw new \LogicException('The type of this global is not a callable reference');
        }

        if ($this->mutateCnt === 0) {
            throw new \LogicException('This global is immutable');
        } elseif ($this->mutateCnt !== null) {
            $this->mutateCnt--;
        }

        $this->refValue = $value;
    }
}
