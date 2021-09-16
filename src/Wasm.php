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

namespace UnWasm;

use UnWasm\Cache\CacheInterface;
use UnWasm\Cache\MemoryCache;
use UnWasm\Compiler\BinaryParser;
use UnWasm\Runtime\Environment;

/**
 * A general Wasm manager class for UnWasm
 */
class Wasm
{
    /** @var CacheInterface The module cache */
    private $cache;

    /** @var Environment The global data store */
    private $env;

    public function __construct(
        CacheInterface $cache,
        Environment $env
    ) {
        $this->cache = $cache;
        $this->env = $env;
    }


    public function loadBinaryFile(string $location, string $module, bool $forceCompile = false)
    {
        $stream = fopen($location, 'rb');
        $timestamp = $forceCompile ? null : (@filemtime($location) ?: null);
        $result = $this->loadBinary($stream, $module, $timestamp);
        fclose($stream);
        return $result;
    }

    public function loadBinary($stream, string $module, ?int $timestamp)
    {
        // Check whether cache is up-to-date
        $cacheTs = $this->cache->getTimestamp($module);
        if (!$timestamp || !$cacheTs || $timestamp > $cacheTs) {
            $parser = new BinaryParser($stream);
            $compiler = $parser->scan();
            $this->cache->write($module, $compiler->compile($module)->read());
        }

        return $this->load($module);
    }

    public function load(string $module)
    {
        // Load the module from cache
        $loaded = $this->cache->load($module);
        if (!$loaded) {
            return null;
        }

        // Instantiate module
        $classname = "WasmModule_$module";
        return new $classname($this->env);
    }

    public static function new($opts = []): self
    {
        $cache = $opts['cache'] ?? new MemoryCache();
        $env = $opts['env'] ?? new Environment();

        return new Wasm($cache, $env);
    }
}
