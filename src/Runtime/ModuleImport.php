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

namespace UnWasm\Runtime;

use UnWasm\Wasm;

class ModuleImport
{
    /** @var Wasm */ private $env;
    /** @var string */ private $module;
    /** @var mixed */ private $instance;

    public function __construct(Wasm $env, string $module)
    {
        $this->env = $env;
        $this->module = $module;
        $this->instance = null;
    }

    public function __call($name, $arguments)
    {
        $callback = [$this->instance(), $name];
        return call_user_func_array($callback, $arguments);
    }

    public function __get($name)
    {
        return $this->instance()->$name;
    }

    private function instance()
    {
        if ($this->instance === null) {
            $this->instance = $this->env->import($this->module);
        }

        return $this->instance;
    }
}
