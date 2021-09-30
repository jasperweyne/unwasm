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

namespace UnWasm\Compiler\Node\Store;

/**
 * Represents a data segment for memory initialization.
 */
class Data
{
    /** @var int The memory index the data segment refers to */
    public $memIdx;

    /** @var ?Instruction[] Value initiation expression */
    private $initExpr;

    /** @var string Value initialization data */
    private $initData;

    public function __construct(int $memIdx, ?array $initExpr, string $initData)
    {
        $this->memIdx = $memIdx;
        $this->initExpr = $initExpr;
        $this->initData = $initData;
    }
}
