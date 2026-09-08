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

    public function testAscii()
    {
        self::assertSame('abc', Str::ascii('abc'));
        self::assertSame('test-', Str::ascii('test-你好'));
        self::assertSame('deja u', Str::ascii('déjà ü'));
        self::assertSame('schoene aenderung', Str::ascii('schöne änderung', 'de'));
    }

    public function testSlug()
    {
        self::assertSame('hello-world', Str::slug('hello world'));
        self::assertSame('hello-world-50-c-at-cafe', Str::slug('Hello_World 5° C @ cafe'));
    }

    public function testKebab()
    {
        self::assertSame('hello-world', Str::kebab('hello world'));
    }

    public function testUuidV4()
    {
        $uuids = [];

        for ($i = 0; $i < 10000; ++$i) {
            $uuid = Str::uuidV4();
            $uuids[$uuid] = true;

            self::assertMatchesRegularExpression('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/', $uuid);
        }

        self::assertCount(10000, $uuids);
    }
}
