<?php

namespace Yansongda\Supports\Tests;

use PHPUnit\Framework\TestCase;
use stdClass;
use Yansongda\Supports\Arr;

class ArrTest extends TestCase
{
    public function testSnakeCaseKey()
    {
        $a = [
            'myName' => 'yansongda',
            'myAge' => 27,
            'family' => [
                'hasChildren' => false,
            ]
        ];
        $expect = [
            'my_name' => 'yansongda',
            'my_age' => 27,
            'family' => [
                'has_children' => false,
            ]
        ];
        self::assertEqualsCanonicalizing($expect, Arr::snakeCaseKey($a));

        $obj =  new stdClass();
        $a = [
            'myName' => 'yansongda',
            'myAge' => 27,
            'family' => [
                'hasChildren' => $obj,
            ]
        ];
        $expect = [
            'my_name' => 'yansongda',
            'my_age' => 27,
            'family' => [
                'has_children' => $obj,
            ]
        ];
        self::assertEqualsCanonicalizing($expect, Arr::snakeCaseKey($a));
    }

    public function testCamelCaseKey()
    {
        $a = [
            'my_name' => 'yansongda',
            'my_age' => 27,
            'family' => [
                'has_children' => false,
            ]
        ];
        $expect = [
            'myName' => 'yansongda',
            'myAge' => 27,
            'family' => [
                'hasChildren' => false,
            ]
        ];
        self::assertEqualsCanonicalizing($expect, Arr::camelCaseKey($a));
    }

    public function testCamelCaseKeyWithObject()
    {
        $obj = new Class {
            public function toArray(): array
            {
                return ['name' => 'yansongda'];
            }
        };

        $a = [
            'my_name' => 'yansongda',
            'my_age' => 27,
            'family' => [
                'has_children' => false,
            ],
            'objs' => [
                $obj,
            ]
        ];
        $expect = [
            'myName' => 'yansongda',
            'myAge' => 27,
            'family' => [
                'hasChildren' => false,
            ],
            'objs' => [
                ['name' => 'yansongda'],
            ]
        ];
        self::assertEqualsCanonicalizing($expect, Arr::camelCaseKey($a));
    }

    public function testToString()
    {
        $a = [
            'my_name' => 'yansongda',
            'my_age' => 27,
        ];

        self::assertEquals('my_name=yansongda&my_age=27', Arr::toString($a));
    }

    public function testWrapJson()
    {
        $array = ['name' => 'yansongda', 'age' => 29];
        $str = '{"name":"yansongda","age":29}';

        self::assertEquals($array, Arr::wrapJson($str));
    }

    public function testWrapXml()
    {
        $array = ['name' => 'yansongda', 'age' => 29];
        $str = '<xml><name><![CDATA[yansongda]]></name><age>29</age></xml>';

        self::assertEquals($array, Arr::wrapXml($str));
    }

    public function testWrapQuery()
    {
        $array = ['name' => 'yansongda', 'age' => 29];
        $str = 'name=yansongda&age=29';

        self::assertEquals($array, Arr::wrapQuery($str));
    }

    public function testWrapQuerySpace()
    {
        $array = ['name' => 'yan+song+da', 'age' => 29];
        $str = 'name=yan+song+da&age=29';

        self::assertEquals($array, Arr::wrapQuery($str, true));
    }
}
