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

namespace UnWasm;

use UnWasm\Cache\CacheInterface;
use UnWasm\Cache\MemoryCache;
use UnWasm\Compiler\BinaryParser;
use UnWasm\Compiler\ParserInterface;
use UnWasm\Compiler\Source;
use UnWasm\Exception\RuntimeValueException;
use UnWasm\Runtime\Wasi;

/**
 * The general Wasm environment for UnWasm
 */
class Wasm
{
    /** @var CacheInterface The module cache */
    private $cache;

    /** @var string[] The directories where a module may be loaded from */
    private $locations;

    /** @var string The namespace where module classes are registered */
    private $namespace;

    /** @var array The dictionary of module instances and their names. */
    private $modules = array();

    /** @var ?\UnWasm\Runtime\MemoryInst The global memory instance. */
    public $memory;

    public function __construct(
        ?CacheInterface $cache = null,
        $locations = [],
        $namespace = 'WasmModule'
    ) {
        $this->cache = $cache ?? new MemoryCache();
        $this->locations = $locations;
        $this->namespace = $namespace;
        $this->modules = array();
    }

    public function import(string $module)
    {
        if (!isset($this->modules[$module]) && $this->load($module) === null) {
            throw new RuntimeValueException("Tried to import unregistered module '$module', make sure you've exported it to the environment.");
        }

        return $this->modules[$module];
    }

    public function export($object, string $module): void
    {
        if (isset($object->mems) && count($object->mems) > 0) {
            $this->memory = reset($object->mems);
        }
        $this->modules[$module] = $object;
    }

    public function load(string $module)
    {
        // first, try loading from cache
        if ($inst = $this->instantiate($module)) {
            return $inst;
        }

        // then, try loading the module from disk
        foreach ($this->locations as $dir) {
            if ($results = glob($dir.$module)) {
                // a file was found
                if (substr($results[0], -4) == '.wat') {
                    return $this->loadText($results[0], $module);
                } else {
                    return $this->loadBinary($results[0], $module);
                }
            }
        }

        // module could not be found on disk, try loading it from registry
        return $this->loadWapm($module);
    }

    public function loadWapm(string $package, ?string $module = null)
    {
        $url = 'http://registry.wapm.io/graphql/';
        $data = <<<GRAPHQL
{
    getPackageVersion(name: "$package") {
        modules {
            name
            publicUrl
        }
    }
}

GRAPHQL;
        $options = array(
            'http' => array(
                'header'  => "Content-Type: application/json\r\nAccept: application/json\r\n",
                'method'  => 'POST',
                'content' => json_encode(['query' => $data])
            )
        );
        $context = stream_context_create($options);
        $result = json_decode(file_get_contents($url, false, $context) ?: 'null', true);

        // handle metadata download errors
        if (!$result || isset($result['errors']) || $result['data']['getPackageVersion'] === null) {
            return null;
        }

        // get module
        $pkgModules = $result['data']['getPackageVersion']['modules'];
        if ($module !== null) {
            foreach ($pkgModules as $pkgModule) {
                if ($pkgModule['name'] === $module) {
                    $pkgModules = [$pkgModule];
                    break;
                }
            }
        }

        // compile modules
        foreach ($pkgModules as $module_info) {
            // download module and compile it
            self::asStream(file_get_contents($module_info['publicUrl']), function ($stream) use ($module_info) {
                $parser = new BinaryParser($stream);
                $this->compile($parser, $module_info['name'], null);
            });
        }

        // instantiate it, cache it, return it
        if ($module !== null && $inst = $this->instantiate($module)) {
            $this->export($inst, $module);
            return $inst;
        }
    }

    public function loadBinary(string $location, string $module, bool $forceCompile = false)
    {
        // compile the .wasm file to a module
        $stream = fopen($location, 'rb');
        $timestamp = $forceCompile ? null : (@filemtime($location) ?: null);
        $parser = new BinaryParser($stream);
        $this->compile($parser, $module, $timestamp);
        fclose($stream);

        // instantiate it, cache it, return it
        $inst = $this->instantiate($module);
        $this->export($inst, $module);
        return $inst;
    }

    public function loadText(string $location, string $module, bool $forceCompile = false)
    {

        // create temporary file for binary
        $tmpFile = tempnam(__DIR__, 'wat');

        // convert and compile
        try {
            // setup cli application
            $clean = new Wasm($this->cache);
            $env = new Wasi($clean);
            $env->args = ['--disable-simd', "--output='$tmpFile'", $location];

            // start wat2wasm
            $wat2wasm = $clean->load('wat2wasm') ?? $clean->loadWapm('wabt', 'wat2wasm');
            $wat2wasm->_start();

            // instantiate it, cache it, remove cache data, return it
            $inst = $this->loadBinary($tmpFile, $module, $forceCompile);
            $this->export($inst, $module);
            return $inst;
        } finally {
            unlink($tmpFile);
        }
    }

    public function compile(ParserInterface $parser, string $module, ?int $timestamp)
    {
        // Check whether cache is up-to-date
        $cacheTs = $this->cache->getTimestamp($module);
        if (!$timestamp || !$cacheTs || $timestamp > $cacheTs) {
            $compiler = $parser->scan();
            $source = new Source();
            $compiler->compile($this->namespace.'\\'.$module, $source);
            $this->cache->write($module, $source->read());
        }
    }

    public function instantiate(string $module)
    {
        // Load the module from cache
        $loaded = $this->cache->load($module);
        if (!$loaded) {
            return null;
        }

        // Instantiate module
        $classname = "$this->namespace\\$module";
        return new $classname($this);
    }

    public static function asStream(string $data, callable $withStream)
    {
        $stream = fopen('php://memory', 'w+b');
        fwrite($stream, $data);
        rewind($stream);
        $ret = ($withStream)($stream);
        fclose($stream);
        return $ret;
    }
}
