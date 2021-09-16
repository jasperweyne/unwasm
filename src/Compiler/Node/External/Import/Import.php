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

namespace UnWasm\Compiler\Node\External\Import;

/**
 * Represents an imported item from an external module.
 */
abstract class Import
{
    /** @var string The module where the item is defined */
    protected $module;

    /** @var string The imported item */
    protected $name;

    public function __construct(string $module, string $name)
    {
        $this->module = $module;
        $this->name = $name;
    }

    public function module(): string
    {
        return $this->module;
    }
}
