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

namespace UnWasm\Cache;

class FilesystemCache implements CacheInterface
{
    private $directory;

    public function __construct(string $directory)
    {
        $this->directory = rtrim($directory, '\/').DIRECTORY_SEPARATOR;
    }

    public function load(string $key): bool
    {
        $location = $this->getLocation($key);
        if (is_file($location)) {
            @include_once $location;
            return true;
        }

        return false;
    }

    public function write(string $key, string $content): void
    {
        $location = $this->getLocation($key);
        $dir = \dirname($location);
        if (!is_dir($dir)) {
            if (false === @mkdir($dir, 0777, true)) {
                clearstatcache(true, $dir);
                if (!is_dir($dir)) {
                    throw new \RuntimeException(sprintf('Unable to create the cache directory (%s).', $dir));
                }
            }
        } elseif (!is_writable($dir)) {
            throw new \RuntimeException(sprintf('Unable to write in the cache directory (%s).', $dir));
        }

        $tmpFile = tempnam($dir, basename($location));
        if (false !== @file_put_contents($tmpFile, '<?php'.PHP_EOL.PHP_EOL.$content) && @rename($tmpFile, $location)) {
            @chmod($location, 0666 & ~umask());
            return;
        }

        throw new \RuntimeException(sprintf('Failed to write cache file "%s".', $key));
    }

    public function getTimestamp(string $key): ?int
    {
        $location = $this->getLocation($key);
        if (!is_file($location)) {
            return null;
        }

        return (int) @filemtime($location);
    }

    private function getLocation(string $key): string
    {
        return $this->directory.'WasmModule_'.$key.'.php';
    }
}
