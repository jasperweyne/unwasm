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

class Module
{
    /** @var \UnWasm\Runtime\MemoryInst[] */ public $mems = array();
    /** @var \UnWasm\Runtime\TableInst[] */ public $tables = array();
    /** @var \UnWasm\Runtime\GlobalInst[] */ public $globals = array();
    /** @var string[] */ public $funcs = array();
    /** @var string[] */ protected $datas = array();
    /** @var ?callable[] */ protected $elems = array();
}
