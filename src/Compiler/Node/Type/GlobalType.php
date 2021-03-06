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

namespace UnWasm\Compiler\Node\Type;

/**
 * Describes the type constraints of a global.
 */
class GlobalType
{
    /** @var ValueType Global value type. */
    public $valueType;

    /** @var bool Whether the global value is mutable during runtime. */
    public $mutable;

    public function __construct(ValueType $valueType, bool $mutable)
    {
        $this->valueType = $valueType;
        $this->mutable = $mutable;
    }
}
