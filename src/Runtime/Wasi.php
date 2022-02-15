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

class Wasi extends Module
{
    const ERRNO_SUCCESS = 0;
    const ERRNO_2BIG = 1;
    const ERRNO_ACCES = 2;
    const ERRNO_ADDRINUSE = 3;
    const ERRNO_ADDRNOTAVAIL = 4;
    const ERRNO_AFNOSUPPORT = 5;
    const ERRNO_AGAIN = 6;
    const ERRNO_ALREADY = 7;
    const ERRNO_BADF = 8;
    const ERRNO_BADMSG = 9;
    const ERRNO_BUSY = 10;
    const ERRNO_CANCELED = 11;
    const ERRNO_CHILD = 12;
    const ERRNO_CONNABORTED = 13;
    const ERRNO_CONNREFUSED = 14;
    const ERRNO_CONNRESET = 15;
    const ERRNO_DEADLK = 16;
    const ERRNO_DESTADDRREQ = 17;
    const ERRNO_DOM = 18;
    const ERRNO_DQUOT = 19;
    const ERRNO_EXIST = 20;
    const ERRNO_FAULT = 21;
    const ERRNO_FBIG = 22;
    const ERRNO_HOSTUNREACH = 23;
    const ERRNO_IDRM = 24;
    const ERRNO_ILSEQ = 25;
    const ERRNO_INPROGRESS = 26;
    const ERRNO_INTR = 27;
    const ERRNO_INVAL = 28;
    const ERRNO_IO = 29;
    const ERRNO_ISCONN = 30;
    const ERRNO_ISDIR = 31;
    const ERRNO_LOOP = 32;
    const ERRNO_MFILE = 33;
    const ERRNO_MLINK = 34;
    const ERRNO_MSGSIZE = 35;
    const ERRNO_MULTIHOP = 36;
    const ERRNO_NAMETOOLONG = 37;
    const ERRNO_NETDOWN = 38;
    const ERRNO_NETRESET = 39;
    const ERRNO_NETUNREACH = 40;
    const ERRNO_NFILE = 41;
    const ERRNO_NOBUFS = 42;
    const ERRNO_NODEV = 43;
    const ERRNO_NOENT = 44;
    const ERRNO_NOEXEC = 45;
    const ERRNO_NOLCK = 46;
    const ERRNO_NOLINK = 47;
    const ERRNO_NOMEM = 48;
    const ERRNO_NOMSG = 49;
    const ERRNO_NOPROTOOPT = 50;
    const ERRNO_NOSPC = 51;
    const ERRNO_NOSYS = 52;
    const ERRNO_NOTCONN = 53;
    const ERRNO_NOTDIR = 54;
    const ERRNO_NOTEMPTY = 55;
    const ERRNO_NOTRECOVERABLE = 56;
    const ERRNO_NOTSOCK = 57;
    const ERRNO_NOTSUP = 58;
    const ERRNO_NOTTY = 59;
    const ERRNO_NXIO = 60;
    const ERRNO_OVERFLOW = 61;
    const ERRNO_OWNERDEAD = 62;
    const ERRNO_PERM = 63;
    const ERRNO_PIPE = 64;
    const ERRNO_PROTO = 65;
    const ERRNO_PROTONOSUPPORT = 66;
    const ERRNO_PROTOTYPE = 67;
    const ERRNO_RANGE = 68;
    const ERRNO_ROFS = 69;
    const ERRNO_SPIPE = 70;
    const ERRNO_SRCH = 71;
    const ERRNO_STALE = 72;
    const ERRNO_TIMEDOUT = 73;
    const ERRNO_TXTBSY = 74;
    const ERRNO_XDEV = 75;
    const ERRNO_NOTCAPABLE = 76;
    
    const RIGHTS_FD_DATASYNC = 0x1;
    const RIGHTS_FD_READ = 0x2;
    const RIGHTS_FD_SEEK = 0x4;
    const RIGHTS_FD_FDSTAT_SET_FLAGS = 0x8;
    const RIGHTS_FD_SYNC = 0x10;
    const RIGHTS_FD_TELL = 0x20;
    const RIGHTS_FD_WRITE = 0x40;
    const RIGHTS_FD_ADVISE = 0x80;
    const RIGHTS_FD_ALLOCATE = 0x100;
    const RIGHTS_PATH_CREATE_DIRECTORY = 0x200;
    const RIGHTS_PATH_CREATE_FILE = 0x400;
    const RIGHTS_PATH_LINK_SOURCE = 0x800;
    const RIGHTS_PATH_LINK_TARGET = 0x1000;
    const RIGHTS_PATH_OPEN = 0x2000;
    const RIGHTS_FD_READDIR = 0x4000;
    const RIGHTS_PATH_READLINK = 0x8000;
    const RIGHTS_PATH_RENAME_SOURCE = 0x10000;
    const RIGHTS_PATH_RENAME_TARGET = 0x20000;
    const RIGHTS_PATH_FILESTAT_GET = 0x40000;
    const RIGHTS_PATH_FILESTAT_SET_SIZE = 0x80000;
    const RIGHTS_PATH_FILESTAT_SET_TIMES = 0x100000;
    const RIGHTS_FD_FILESTAT_GET = 0x200000;
    const RIGHTS_FD_FILESTAT_SET_SIZE = 0x400000;
    const RIGHTS_FD_FILESTAT_SET_TIMES = 0x800000;
    const RIGHTS_PATH_SYMLINK = 0x1000000;
    const RIGHTS_PATH_REMOVE_DIRECTORY = 0x2000000;
    const RIGHTS_PATH_UNLINK_FILE = 0x4000000;
    const RIGHTS_POLL_FD_READWRITE = 0x8000000;
    const RIGHTS_SOCK_SHUTDOWN = 0x10000000;

    const FILETYPE_UNKNOWN = 0;
    const FILETYPE_BLOCK_DEVICE = 1;
    const FILETYPE_CHARACTER_DEVICE = 2;
    const FILETYPE_DIRECTORY = 3;
    const FILETYPE_REGULAR_FILE = 4;
    const FILETYPE_SOCKET_DGRAM = 5;
    const FILETYPE_SOCKET_STREAM = 6;
    const FILETYPE_SYMBOLIC_LINK = 7;
    
    const FDFLAGS_APPEND = 0x1;
    const FDFLAGS_DSYNC = 0x2;
    const FDFLAGS_NONBLOCK = 0x4;
    const FDFLAGS_RSYNC = 0x8;
    const FDFLAGS_SYNC = 0x10;
    
    const LOOKUPFLAGS_SYMLINK_FOLLOW = 0x1;
    
    const OFLAGS_CREAT = 0x1;
    const OFLAGS_DIRECTORY = 0x2;
    const OFLAGS_EXCL = 0x4;
    const OFLAGS_TRUNC = 0x8;
    
    const PREOPENTYPE_DIR = 0;

    /**
     * @var string[] Process arguments.
     */
    public $args = array();

    /**
     * @var array<string, string> Environment variables.
     */
    public $env = array();
    
    /**
     * @var array[] Opened file descriptor handles and metadata.
     */
    private $fds = array();

    /**
     * @var Wasm The Wasm environment.
     */
    private $wasm;
    
    public function __construct(Wasm $env, array $preopens = array())
    {
        $this->wasm = $env;

        // setup type declarations
        $this->funcs = [
            'args_get' => 'ii:i',
            'args_sizes_get' => 'ii:i',
            'environ_get' => 'ii:i',
            'environ_sizes_get' => 'ii:i',
            'fd_close' => 'i:i',
            'fd_fdstat_get' => 'ii:i',
            'fd_fdstat_set_flags' => 'ii:i',
            'fd_prestat_dir_name' => 'iii:i',
            'fd_prestat_get' => 'ii:i',
            'fd_read' => 'iiii:i',
            'fd_seek' => 'ijii:i',
            'fd_write' => 'iiii:i',
            'proc_exit' => 'i:',
            'path_filestat_get' => 'iiiii:i',
            'path_open' => 'iiiiijjii:i',
        ];

        // register stdin/stdout/stderr
        $this->fds = [
            [
                'rid' => STDIN,
                'type' => self::FILETYPE_CHARACTER_DEVICE,
                'flags' => self::FDFLAGS_APPEND,
            ],
            [
                'rid' => STDOUT,
                'type' => self::FILETYPE_CHARACTER_DEVICE,
                'flags' => self::FDFLAGS_APPEND,
            ],
            [
                'rid' => STDERR,
                'type' => self::FILETYPE_CHARACTER_DEVICE,
                'flags' => self::FDFLAGS_APPEND,
            ],
        ];

        foreach ($preopens as list($vpath, $path)) {
            array_push($this->fds, [
                'entries' => scandir($path),
                'path' => $path,
                'vpath' => $vpath,
            ]);
        }

        $env->export($this, 'wasi_unstable');
    }
    
    /**
     * Read command-line argument data. The size of the array should match that
     * returned by args_sizes_get()
     */
    public function args_get(int $list_ptr, int $buffer_ptr): array // [int]
    {
        foreach ($this->args as $arg) {
            $this->wasm->memory->storeInt($buffer_ptr, $list_ptr);
            $list_ptr += (32 / 8);

            $data = "$arg\0";
            $this->wasm->memory->write($data, $buffer_ptr);
            $buffer_ptr += strlen($data);
        }

        return array(self::ERRNO_SUCCESS);
    }
    
    /**
     * Return command-line argument data sizes.
     */
    public function args_sizes_get(int $count_ptr, int $bufsize_ptr): array // [int]
    {
        $this->wasm->memory->storeInt(count($this->args), $count_ptr);
        $this->wasm->memory->storeInt(array_sum(array_map(function ($arg) {
            return strlen("$arg\0");
        }, $this->args)), $bufsize_ptr);

        return array(self::ERRNO_SUCCESS);
    }
    
    /**
     * Read environment variable data. The sizes of the buffers should match
     * that returned by environ_sizes_get().
     */
    public function environ_get(int $list_ptr, int $buffer_ptr): array // [int]
    {
        foreach ($this->env as $key => $value) {
            $this->wasm->memory->storeInt($buffer_ptr, $list_ptr);
            $list_ptr += (32 / 8);

            $data = "$key=$value\0";
            $this->wasm->memory->write($data, $buffer_ptr);
            $buffer_ptr += strlen($data);
        }

        return array(self::ERRNO_SUCCESS);
    }
    
    /**
     * Return command-line argument data sizes.
     */
    public function environ_sizes_get(int $count_ptr, int $bufsize_ptr): array // [int]
    {
        $this->wasm->memory->storeInt(count($this->env), $count_ptr);
        $bufsize = 0;
        foreach ($this->env as $key => $value) {
            $bufsize += strlen("$key=$value\0");
        }
        $this->wasm->memory->storeInt($bufsize, $bufsize_ptr);

        return array(self::ERRNO_SUCCESS);
    }
    
    /**
     * Close a file descriptor. Note: This is similar to close in POSIX.
     */
    public function fd_close(int $fd): array // [int]
    {
        if (!isset($this->fds[$fd])) {
            return array(self::ERRNO_BADF);
        }
        $entry = $this->fds[$fd];

        if ($entry['rid']) {
            // don't close stdin/stdout/stderr
            if ($entry['rid'] != STDIN && $entry['rid'] != STDOUT && $entry['rid'] != STDERR) {
                fclose($entry['rid']);
            }
        }
        unset($this->fds[$fd]);

        return array(self::ERRNO_SUCCESS);
    }
    
    /**
     * Get the attributes of a file descriptor. Note: This returns similar flags
     * to fsync(fd, F_GETFL) in POSIX, as well as additional fields.
     */
    public function fd_fdstat_get(int $fd, int $offset): array // [int]
    {
        if (!isset($this->fds[$fd])) {
            return array(self::ERRNO_BADF);
        }
        $entry = $this->fds[$fd];

        $this->wasm->memory->storeInt($entry['type'], $offset, 8);
        $this->wasm->memory->storeInt($entry['flags'], $offset + 2, 16);

        // todo: handle rights
        $this->wasm->memory->storeInt(0, $offset + 8, 64);
        $this->wasm->memory->storeInt(0, $offset + 16, 64);

        return array(self::ERRNO_SUCCESS);
    }
    
    /**
     * Adjust the flags associated with a file descriptor. Note: This is similar
     * to fcntl(fd, F_SETFL, flags) in POSIX.
     */
    public function fd_fdstat_set_flags(int $fd, int $flags): array // [int]
    {
        return array(self::ERRNO_NOSYS);
    }
    
    /**
     * Return a description of the given preopened file descriptor.
     */
    public function fd_prestat_dir_name(int $fd, int $pathOffset, int $pathLength): array // [int]
    {
        if (!isset($this->fds[$fd])) {
            return array(self::ERRNO_BADF);
        }
        $entry = $this->fds[$fd];
        
        if (!isset($entry['vpath'])) {
            return array(self::ERRNO_BADF);
        }

        $this->wasm->memory->write(substr($entry['vpath'], 0, $pathLength), $pathOffset);
        return array(self::ERRNO_SUCCESS);
    }

    /**
     * Return a description of the given preopened file descriptor.
     */
    public function fd_prestat_get(int $fd, int $prestatOffset): array // [int]
    {
        if (!isset($this->fds[$fd])) {
            return array(self::ERRNO_BADF);
        }
        $entry = $this->fds[$fd];
        
        if (!isset($entry['vpath'])) {
            return array(self::ERRNO_BADF);
        }

        $this->wasm->memory->storeInt(self::PREOPENTYPE_DIR, $prestatOffset, 8);
        $this->wasm->memory->storeInt(strlen($entry['vpath']), $prestatOffset + 4);

        return array(self::ERRNO_SUCCESS);
    }
    
    /**
     * Read from a file descriptor. Note: This is similar to readv in POSIX.
     */
    public function fd_read(int $fd, int $iovsOffset, int $iovsLength, int $nreadOffset): array // [int]
    {
        if (!isset($this->fds[$fd])) {
            return array(self::ERRNO_BADF);
        }
        $entry = $this->fds[$fd];

        $nread = 0;
        for ($i = 0; $i < $iovsLength; $i++) {
            $dataOffset = $this->wasm->memory->loadInt($iovsOffset, 32, false);
            $iovsOffset += (32 / 8);

            $dataLength = $this->wasm->memory->loadInt($iovsOffset, 32, false);
            $iovsOffset += (32 / 8);

            $data = fread($entry['rid'], $dataLength);
            $this->wasm->memory->write($data, $dataOffset);
            $nread += strlen($data);
        }

        $this->wasm->memory->storeInt($nread, $nreadOffset);
        return array(self::ERRNO_SUCCESS);
    }

    /**
     * Move the offset of a file descriptor. Note: This is similar to lseek in
     * POSIX.
     */
    public function fd_seek(int $fd, int $offset, int $whence, int $newOffsetOffset): array // [int]
    {
        if (!isset($this->fds[$fd])) {
            return array(self::ERRNO_BADF);
        }
        $entry = $this->fds[$fd];
        
        fseek($entry['rid'], $offset, $whence); // todo: handle error
        $newOffset = ftell($entry['rid']);
        $this->wasm->memory->storeInt($newOffset, $newOffsetOffset, 64);

        return array(self::ERRNO_SUCCESS);
    }
    
    /**
     * Write to a file descriptor. Note: This is similar to writev in POSIX.
     */
    public function fd_write( int $fd, int $iovsOffset, int $iovsLength, int $nwrittenOffset): array // [int]
    {
        if (!isset($this->fds[$fd])) {
            return array(self::ERRNO_BADF);
        }
        $entry = $this->fds[$fd];

        $nwritten = 0;
        for ($i = 0; $i < $iovsLength; $i++) {
            $dataOffset = $this->wasm->memory->loadInt($iovsOffset, 32, false);
            $iovsOffset += (32 / 8);

            $dataLength = $this->wasm->memory->loadInt($iovsOffset, 32, false);
            $iovsOffset += (32 / 8);

            $data = $this->wasm->memory->read($dataOffset, $dataLength);
            $nwritten += fwrite($entry['rid'], $data) ?: 0;
        }

        $this->wasm->memory->storeInt($nwritten, $nwrittenOffset);
        return array(self::ERRNO_SUCCESS);
    }
    
    /**
     * Open a file or directory. The returned file descriptor is not guaranteed
     * to be the lowest-numbered file descriptor not currently open; it is
     * randomized to prevent applications from depending on making assumptions
     * about indexes. The returned file descriptor is guaranteed to be less than
     * 2**31. Note: This is similar to openat in POSIX.
     */
    public function path_open(
        int $fd,
        int $dirflags,
        int $pathOffset,
        int $pathLength,
        int $oflags,
        int $rightsBase,
        int $rightsInheriting,
        int $fdflags,
        int $openedFdOffset
    ): array // [int]
    {
        if (!isset($this->fds[$fd])) {
            return array(self::ERRNO_BADF);
        }
        $entry = $this->fds[$fd];

        if (!$entry['path']) {
            return array(self::ERRNO_INVAL);
        }

        // get and resolve path
        $pathData = $this->wasm->memory->read($pathOffset, $pathLength);
        $resolvedPath = $this->resolve($entry['path'], $pathData);

        // handle symlinks, set to plain path if necessary
        if (($dirflags & self::LOOKUPFLAGS_SYMLINK_FOLLOW) && !($path = realpath($resolvedPath))) {
            $path = $resolvedPath;
        }
        
        // check if path is outside of sandbox
        if (substr($this->relative($entry['path'], $path), 0, 2) == '..') {
            return array(self::ERRNO_NOTCAPABLE);
        }

        // path_open() flow for directory
        if ($oflags & self::OFLAGS_DIRECTORY) {
            $entries = scandir($path);
            if (!$entries) {
                return array(self::ERRNO_NOTDIR);
            }

            do {
                $openedFd = rand(0, 2147483647); // 2**31 - 1
            } while (isset($this->fds[$openedFd]));
            $this->fds[$openedFd] = [
                'type' => self::FILETYPE_DIRECTORY,
                'flags' => $fdflags,
                'path' => $path,
                'entries' => $entries,
            ];

            $this->wasm->memory->storeInt($openedFd, $openedFdOffset);
            return array(self::ERRNO_SUCCESS);
        }
        
        $write = (
            self::RIGHTS_FD_DATASYNC |
            self::RIGHTS_FD_WRITE |
            self::RIGHTS_FD_ALLOCATE |
            self::RIGHTS_FD_FILESTAT_SET_SIZE
        );

        $read = (
            self::RIGHTS_FD_READ |
            self::RIGHTS_FD_READDIR
        );

        /** 
         * note, ignored $fdflags: 
         *   self::FDFLAGS_DSYNC
         *   self::FDFLAGS_NONBLOCK
         *   self::FDFLAGS_RSYNC
         *   self::FDFLAGS_SYNC
         */
        $readable = ($rightsBase & $read) ? '+' : '';
        if ($fdflags & self::FDFLAGS_APPEND) {
            $flag = 'a'.$readable;
        } elseif ($rightsBase & $write) {
            if ($oflags & self::OFLAGS_EXCL) {
                $flag = 'x'.$readable;
            } elseif ($oflags & self::OFLAGS_TRUNC) {
                $flag = 'w'.$readable;
            } elseif ($oflags & self::OFLAGS_CREAT) {
                $flag = 'c'.$readable;
            }
        } else { // assume read
            $flag = 'r';
        }

        do {
            $openedFd = rand(0, 2147483647); // 2**31 - 1
        } while (isset($this->fds[$openedFd]));
        $this->fds[$openedFd] = [
            'rid' => fopen($path, $flag),
            'flags' => $fdflags,
            'path' => $path,
        ];

        $this->wasm->memory->storeInt($openedFd, $openedFdOffset);
        return array(self::ERRNO_SUCCESS);
    }
    
    /**
     * Return the attributes of a file or directory. Note: This is similar to
     * stat in POSIX.
     */
    public function path_filestat_get(int $fd, int $flags, int $pathOffset, int $pathLength, int $bufferOffset): array // [int]
    {
        if (!isset($this->fds[$fd])) {
            return array(self::ERRNO_BADF);
        }
        $entry = $this->fds[$fd];
        
        if (!isset($entry['path'])) {
            return array(self::ERRNO_INVAL);
        }

        $data = $this->wasm->memory->read($pathOffset, $pathLength);
        $path = $this->resolve($entry['path'], $data);

        $info = ($flags & self::LOOKUPFLAGS_SYMLINK_FOLLOW) ? stat($path) : lstat($path);

        $this->wasm->memory->storeInt($info['dev'] ?? 0, $bufferOffset, 64);
        $bufferOffset += 64 / 8;

        $this->wasm->memory->storeInt($info['ino'] ?? 0, $bufferOffset, 64);
        $bufferOffset += 64 / 8;

        switch ($info['mode'] & 0770000) {
            case 0060000:
                $ftype = self::FILETYPE_BLOCK_DEVICE;
                break;
            case 0020000:
                $ftype = self::FILETYPE_CHARACTER_DEVICE;
                break;
            case 0040000:
                $ftype = self::FILETYPE_DIRECTORY;
                break;
            case 0100000:
                $ftype = self::FILETYPE_REGULAR_FILE;
                break;
            case 0120000:
                $ftype = self::FILETYPE_SYMBOLIC_LINK;
                break;
            default:
                $ftype = self::FILETYPE_UNKNOWN;
                break;
        }

        $this->wasm->memory->storeInt($ftype, $bufferOffset, 8);
        $bufferOffset += 8;

        $this->wasm->memory->storeInt($info['nlink'] ?? 0, $bufferOffset);
        $bufferOffset += 64 / 8;

        $this->wasm->memory->storeInt($info['size'] ?? 0, $bufferOffset, 64);
        $bufferOffset += 64 / 8;
        
        $this->wasm->memory->storeInt($info['atime'] ?? 0, $bufferOffset, 64);
        $bufferOffset += 64 / 8;

        $this->wasm->memory->storeInt($info['mtime'] ?? 0, $bufferOffset, 64);
        $bufferOffset += 64 / 8;
        
        $this->wasm->memory->storeInt($info['ctime'] ?? 0, $bufferOffset, 64);
        $bufferOffset += 64 / 8;

        return array(self::ERRNO_SUCCESS);
    }
    
    /**
     * Terminate the process normally. An exit code of 0 indicates successful
     * termination of the program. The meanings of other values is dependent on
     * the environment.
     */
    public function proc_exit(int $errno): void
    {
        // cleanup wasi file descriptors
        foreach ($this->fds as $fd) {
            if (isset($fd['rid'])) {
                // skip stdin/stdout/stderr
                if ($fd['rid'] == STDIN || $fd['rid'] == STDOUT || $fd['rid'] == STDERR) {
                    continue;
                }

                // close the resource handle
                fclose($fd['rid']);
            }
        }
        $this->fds = array();

        // call exit exception (can be catched, instead of `exit`)
        throw new \RuntimeException('proc_exit called', $errno);
    }

    /**
     * Gives the relative travel path between two file paths.
     */
    private function relative(string $from, string $to)
    {
        // some compatibility fixes for Windows paths
        $from = is_dir($from) ? rtrim($from, '\/') . '/' : $from;
        $to   = is_dir($to)   ? rtrim($to, '\/') . '/'   : $to;
        $from = str_replace('\\', '/', $from);
        $to   = str_replace('\\', '/', $to);
    
        $from     = explode('/', $from);
        $to       = explode('/', $to);
        $relPath  = $to;
    
        foreach($from as $depth => $dir) {
            // find first non-matching dir
            if($dir === $to[$depth]) {
                // ignore this directory
                array_shift($relPath);
            } else {
                // get number of remaining dirs to $from
                $remaining = count($from) - $depth;
                if($remaining > 1) {
                    // add traversals up to first matching dir
                    $padLength = (count($relPath) + $remaining - 1) * -1;
                    $relPath = array_pad($relPath, $padLength, '..');
                    break;
                } else {
                    $relPath[0] = './' . $relPath[0];
                }
            }
        }
        return implode('/', $relPath);
    }
    
    /**
     * Resolves a path (possibly split up into multiple segments) into a single,
     * absolute path without relative links such as ./ or ../. Similar to
     * realpath, but doesn't follow symlinks.
     */
    private function resolve(string ...$pathSegments) {
        // preprocess
        $raw = implode('/', $pathSegments);
        $raw = rtrim($raw, '\/');
        $raw = str_replace('\\', '/', $raw);
    
        // handle parts
        $root = $raw[0] == '/' ? '/' : '';
        $parts = array();
        foreach (explode('/', $raw) as $part) {
            if ('..' == $part) {
                array_pop($parts);
            } elseif ('.' !== $part && '' !== $part) {
                array_push($parts, $part);
            }
        }

        return $root.implode(DIRECTORY_SEPARATOR, $parts);
    }
}
