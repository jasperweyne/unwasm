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
 * Represents the WebAssembly execution context.
 * Handles imports and exports for modules.
 */
class Environment
{
    /** @var array The dictionary of module instances and their names. */
    private $modules = array();

    /** @var string[] Dictionary of exported func names and their type strings. */
    private $funcs = array();

    /** @var string[] Dictionary of exported table names and their instances. */
    private $tables = array();

    /** @var string[] Dictionary of exported memory names and their instances. */
    private $mems = array();

    /** @var string[] Dictionary of exported global names and their instances. */
    private $globals = array();

    public function import(string $module)
    {
        if (!isset($this->modules[$module])) {
            throw new \RuntimeException("Tried to import unregistered module '$module', make sure you've exported it to the environment.");
        }

        return $this->modules[$module];
    }

    public function exportFunc(string $func, string $type)
    {
        $this->funcTypes[$func] = $type;
    }

    public function assertFunc(string $module, string $func, string $type): bool
    {
        if (!isset($this->funcTypes[$module]) || $this->funcTypes[$module][$func] !== $type) {
            throw new \RuntimeException("Unexpected func type for ($module, $func)");
        }

        return true;
    }
}
