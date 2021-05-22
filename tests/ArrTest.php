<?php

namespace Yansongda\Supports\Tests;

use PHPUnit\Framework\TestCase;
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
}
