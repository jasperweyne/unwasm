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

namespace UnWasm\Cache;

class MemoryCache implements CacheInterface
{
    private $cache;

    public function __construct()
    {
        $this->cache = [];
    }

    public function load(string $key): bool
    {
        if (isset($this->cache[$key])) {
            eval($this->cache[$key]['content']);
            return true;
        }

        return false;
    }

    public function write(string $key, string $content): void
    {
        $this->cache[$key] = [
            'content' => $content,
            'timestamp' => \time(),
        ];
    }

    public function getTimestamp(string $key): ?int
    {
        if (isset($this->cache[$key])) {
            return $this->cache[$key]['timestamp'];
        }

        return null;
    }
}
