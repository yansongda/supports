<?php

namespace Yansongda\Supports\Tests;

use JsonException;
use PHPUnit\Framework\TestCase;
use stdClass;
use Yansongda\Supports\Collection;
use Yansongda\Supports\Json;

class JsonTest extends TestCase
{
    public function testEncodeDefaultFlags()
    {
        self::assertSame('{"name":"中文","u":"https:\/\/a\/b"}', Json::encode(['name' => '中文', 'u' => 'https://a/b']));
    }

    public function testEncodeZeroFlags()
    {
        self::assertSame('{"name":"\u4e2d\u6587"}', Json::encode(['name' => '中文'], 0));
    }

    public function testEncodeUnescapedSlashes()
    {
        self::assertSame('{"u":"https://a/b"}', Json::encode(['u' => 'https://a/b'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    public function testEncodeCollection()
    {
        self::assertSame('{"a":1}', Json::encode(new Collection(['a' => 1])));
    }

    public function testEncodeEmptyArray()
    {
        self::assertSame('[]', Json::encode([]));
    }

    public function testEncodeNan()
    {
        $this->expectException(JsonException::class);
        $this->expectExceptionCode(JSON_ERROR_INF_OR_NAN);

        Json::encode(NAN);
    }

    public function testEncodeRecursion()
    {
        $this->expectException(JsonException::class);
        $this->expectExceptionCode(JSON_ERROR_RECURSION);

        $array = [];
        $array['self'] = &$array;

        Json::encode($array);
    }

    public function testEncodeInvalidUtf8()
    {
        $this->expectException(JsonException::class);
        $this->expectExceptionCode(JSON_ERROR_UTF8);

        Json::encode(['s' => "\xB1\x31"]);
    }

    public function testEncodePartialOutputOnErrorStillThrows()
    {
        $this->expectException(JsonException::class);
        $this->expectExceptionCode(JSON_ERROR_INF_OR_NAN);

        Json::encode([NAN], JSON_PARTIAL_OUTPUT_ON_ERROR);
    }

    public function testDecodeAssocDefault()
    {
        self::assertSame(['a' => ['b' => 1]], Json::decode('{"a":{"b":1}}'));
    }

    public function testDecodeStdClass()
    {
        $object = Json::decode('{"a":1}', false);

        self::assertInstanceOf(stdClass::class, $object);
        self::assertSame(1, $object->a);
    }

    public function testDecodeNullLiteral()
    {
        self::assertNull(Json::decode('null'));
    }

    public function testDecodeScalars()
    {
        self::assertSame(0, Json::decode('0'));
        self::assertSame('x', Json::decode('"x"'));
        self::assertSame(true, Json::decode('true'));
    }

    public function testDecodeFlags()
    {
        self::assertSame('9223372036854775808', Json::decode('{"a":9223372036854775808}', true, 512, JSON_BIGINT_AS_STRING)['a']);
    }

    public function testDecodeSyntaxError()
    {
        $this->expectException(JsonException::class);
        $this->expectExceptionCode(JSON_ERROR_SYNTAX);

        Json::decode('{');
    }

    public function testDecodeDepthExceeded()
    {
        $this->expectException(JsonException::class);
        $this->expectExceptionCode(JSON_ERROR_DEPTH);

        Json::decode(str_repeat('[', 600).str_repeat(']', 600));
    }

    public function testDecodeInvalidUtf8()
    {
        $this->expectException(JsonException::class);
        $this->expectExceptionCode(JSON_ERROR_UTF8);

        Json::decode("\"\xB1\x31\"");
    }

    public function testEncodeDepthExceeded()
    {
        $this->expectException(JsonException::class);
        $this->expectExceptionCode(JSON_ERROR_DEPTH);

        Json::encode([[1]], 0, 1);
    }
}
