<?php

namespace Yansongda\Supports\Tests;

use PHPUnit\Framework\TestCase;
use Yansongda\Supports\Str;

class StrTest extends TestCase
{
    public function testCamel()
    {
        self::assertSame('helloWorld', Str::camel('HelloWorld'));
        self::assertSame('helloWorld', Str::camel('hello_world'));
        self::assertSame('helloWorld', Str::camel('hello-world'));
        self::assertSame('helloWorld', Str::camel('hello world'));
    }

    public function testSnake()
    {
        self::assertSame('hello_world', Str::snake('HelloWorld'));
        self::assertSame('hello_world', Str::snake('hello_world'));
        self::assertSame('hello_world', Str::snake('hello world'));
    }

    public function testStudly()
    {
        self::assertSame('HelloWorld', Str::studly('helloWorld'));
        self::assertSame('HelloWorld', Str::studly('hello_world'));
        self::assertSame('HelloWorld', Str::studly('hello-world'));
        self::assertSame('HelloWorld', Str::studly('hello world'));
        self::assertSame('Hello-World', Str::studly('hello world', '-'));
    }

    public function testStartsWith()
    {
        self::assertTrue(Str::startsWith('_key', '_'));
        self::assertTrue(Str::startsWith('+key', ['_', '+']));
        self::assertFalse(Str::startsWith('0', '_'));
        self::assertFalse(Str::startsWith('0', ['_', '+']));
        self::assertFalse(Str::startsWith(0, '_'));
        self::assertFalse(Str::startsWith(0, ['_', '+']));
    }
}
