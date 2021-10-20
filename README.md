# UnWasm
Do you want to code in C++ while getting absolutely zero performance gain? Do
you want to code your next Wordpress plugin in Rust? Or do you want to run other
programming languages on your shared PHP hosting provider? Welcome to UnWasm!

UnWasm is a PHP library which allows you to run WebAssembly on your server,
without the need for any PHP extensions! UnWasm transpiles WebAssembly to native
PHP, which allows for easy interoperability between your PHP and WebAssembly
code.

Since the code transpiles to PHP, the performance will never exceed native PHP
performance. If this is necessary for your application, we kindly refer you to
[wasmer-php](https://github.com/wasmerio/wasmer-php/), which is a PHP extension.
UnWasm is for people who want to run their code on platforms where Wasmer is not
available to them, for example on shared hosting platforms.

**UnWasm is still very experimental and unstable. Do not use it in production!**

## Support
UnWasm is currently in development. Please view below for the currently
supported language features. UnWasm will reach 1.0.0 when the binary form for
[WebAssembly 1.1](https://webassembly.github.io/spec/core/binary/index.html) is
fully supported. 

| Code Parsing   | Status      |
| -------------- | ----------- |
| .wasm          | Supported   |
| .wast          | Unsupported |

| Compilation    | Status      |
| -------------- | ----------- |
| Types          | Unsupported |
| Imports        | Partial     |
| Funcs          | Supported   |
| Tables         | Unsupported |
| Mems           | Supported   |
| Globals        | Unsupported |
| Exports        | Partial     |
| Start          | Supported   |

| Instructions   | Status      |
| -------------- | ----------- |
| Numeric        | Partial     |
| Reference      | Supported   |
| Parametric     | Unsupported |
| Variable       | Partial     |
| Table          | Unsupported |
| Memory         | Partial     |
| Control        | Partial     |

| Runtime        | Status      |
| -------------- | ----------- |
| Memory         | Partial     |
| Globals        | Unsupported |
| Tables         | Unsupported |
| Datas          | Unsupported |
| Elems          | Unsupported |
| Import/Export  | Partial     |

Note: runtime funcs are managed by PHP and therefore implicitly supported.

### Known caveats
* PHP integers are encoded with a bit length dependent upon the platform. This
  means that if you're running a 32-bit PHP installation, UnWasm currently won't
  support 64-bit integers.
* Integers are always encoded signed in PHP. When using large unsigned
  integers, this might currently result in unexpected behaviour.

## License
UnWasm is licensed under the Apache License, Version 2.0. Please refer to the
[LICENSE](LICENSE) file for more information.

## Contribute
Contributions are most welcome! Make sure your code follows the
[PSR-12](https://www.php-fig.org/psr/psr-12/) code style, please review these
before opening a pull request. You can (partially) automate this by running the
`composer fix`.

This project is licensed under the Apache license version 2.0. Therefore, your
contributions shall be under the Apache license version 2.0 unless explicitly
stated otherwise. For more information, refer to the [LICENSE](LICENSE).
