<?php

namespace Yansongda\Supports\Tests;

use PHPUnit\Framework\TestCase;
use Yansongda\Supports\Collection;

class CollectionTest extends TestCase
{
    protected array $data = [];

    protected Collection $collection;

    protected function setUp(): void
    {
        $this->data = [
            'name' => 'yansongda',
            'age' => 26,
            'sex' => 1,
            'language' => [
                'php',
                'java',
                'rust',
            ],
        ];
        $this->collection = new Collection($this->data);
    }

    public function testToString()
    {
        $json = json_encode($this->data);

        self::assertEquals($json, $this->collection->toJson());
        self::assertEquals($json, $this->collection->__toString());
    }

    public function testMagicGet()
    {
        self::assertEquals('yansongda', $this->collection->name);
        self::assertEqualsCanonicalizing(['php', 'java', 'rust'], $this->collection->language);
    }

    public function testMagicSet()
    {
        $this->collection->fuck = 'ok';
        $this->collection->foo = ['bar', 'fuck'];

        self::assertEquals('ok', $this->collection->get('fuck'));
        self::assertEquals(['bar', 'fuck'], $this->collection->get('foo'));
    }

    public function testIsset()
    {
        self::assertTrue(isset($this->collection['name']));
        self::assertFalse(isset($this->collection['notExistKey']));
    }

    public function testUnset()
    {
        unset($this->collection['name']);

        self::assertFalse(isset($this->collection['name']));
    }

    public function testAll()
    {
        self::assertEquals($this->data, $this->collection->all());
    }

    public function testOnly()
    {
        self::assertEquals([
            'name' => 'yansongda',
        ], $this->collection->only(['name']));
    }

    public function testExcept()
    {
        self::assertEquals([
            'name' => 'yansongda',
            'age' => 26,
            'sex' => 1,
        ], $this->collection->except('language')->all());
    }

    public function testMerge()
    {
        $merge = ['haha' => 'enen'];

        self::assertEqualsCanonicalizing(array_merge($this->data, $merge), $this->collection->merge($merge)->all());
    }

    public function testIsEmpty()
    {
        self::assertTrue((new Collection())->isEmpty());
    }

    public function testIsNotEmpty()
    {
        self::assertTrue($this->collection->isNotEmpty());
    }

    public function testToJson()
    {
        $array = ['name' => 'yansongda', 'age' => 29];
        $str = '{"name":"yansongda","age":29}';

        self::assertEquals($str, Collection::wrap($array)->toJson());
    }

    public function testToXml()
    {
        $xml = '<xml><name><![CDATA[yansongda]]></name><age>29</age></xml>';
        $array = ['name' => 'yansongda', 'age' => 29];

        self::assertEquals($xml, Collection::wrap($array)->toXml());
    }
}
