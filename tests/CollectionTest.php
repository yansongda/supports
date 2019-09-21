<?php

namespace Yansongda\Supports\Tests;

use Yansongda\Supports\Collection;

class CollectionTest extends TestCase
{
    /**
     * data.
     *
     * @var array
     */
    protected $data = [];

    /**
     * collection.
     *
     * @var Collection
     */
    protected $collection;

    public function setUp()
    {
        $this->data = [
            'name' => 'yansongda',
            'age' => 26,
            'sex' => 1,
            'language' => [
                'php',
                'java',
                'python',
            ],
        ];
        $this->collection = new Collection($this->data);
    }

    public function testToString()
    {
        $json = json_encode($this->data);

        $this->assertEquals($json, $this->collection->toJson());
        $this->assertEquals($json, $this->collection->__toString());
    }

    public function testMagicGet()
    {
        $this->assertEquals('yansongda', $this->collection->name);
        $this->assertEquals(['php', 'java', 'python'], $this->collection->language);
    }

    public function testMagicSet()
    {
        $this->collection->fuck = 'ok';
        $this->collection->foo = ['bar', 'fuck'];

        $this->assertEquals('ok', $this->collection->get('fuck'));
        $this->assertEquals(['bar', 'fuck'], $this->collection->get('foo'));
    }

    public function testIsset()
    {
        $this->assertTrue(isset($this->collection['name']));
        $this->assertFalse(isset($this->collection['notExistKey']));
    }

    public function testUnset()
    {
        unset($this->collection['name']);

        $this->assertFalse(isset($this->collection['name']));
    }

    public function testAll()
    {
        $this->assertEquals($this->data, $this->collection->all());
    }

    public function testOnly()
    {
        $this->assertEquals([
            'name' => 'yansongda',
        ], $this->collection->only(['name']));
    }

    public function testExcept()
    {
        $this->assertEquals([
            'name' => 'yansongda',
            'age' => 26,
            'sex' => 1,
        ], $this->collection->except('language')->all());
    }

    public function testMerge()
    {
        $merge = ['haha' => 'enen'];

        $this->assertEquals(array_merge($this->data, $merge), $this->collection->merge($merge));
    }
}
